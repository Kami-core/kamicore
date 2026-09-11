<?php

/**
 * KamiCore
 *
 * SPDX-License-Identifier: Apache-2.0
 *
 * @see https://kamicore.org
 */

declare(strict_types=1);

final class FieldStructureImporter
{
    private const DEFAULT_TRANSLATION_STATUS = 'draft';

    public static function main(string $jsonPath): void
    {
        $data = self::loadJsonFile($jsonPath);
        self::validateRootStructure($data);

        if (!\DB::beginTransaction()) {
            throw new \RuntimeException('Failed to start field structure import transaction.');
        }

        try {
            $typeIdMap = self::importFieldTypes($data['field_types'] ?? []);
            self::importFields($data['fields'] ?? [], $typeIdMap);

            if (!\DB::commit()) {
                throw new \RuntimeException('Failed to commit field structure import transaction.');
            }
        } catch (\Throwable $e) {
            \DB::rollBack();
            throw $e;
        }
    }

    private static function importFieldTypes(array $items): array
    {
        $typeIdMap = self::loadFieldTypeIdMap();
        $pending = [];

        foreach ($items as $index => $item) {
            self::validateFieldTypeItem($item, $index);
            $pending[] = $item;
        }

        while ($pending !== []) {
            $progress = false;
            $nextPending = [];

            foreach ($pending as $item) {
                $name = self::normalizeName($item['name']);
                $parentName = self::normalizeNullableName($item['parent'] ?? null);

                if ($parentName !== null && !isset($typeIdMap[$parentName])) {
                    $nextPending[] = $item;
                    continue;
                }

                $parentId = $parentName !== null ? $typeIdMap[$parentName] : 0;
                $typeId = self::upsertFieldType($item, $parentId);

                $typeIdMap[$name] = $typeId;
                $progress = true;
            }

            if (!$progress) {
                $names = array_map(
                    static fn(array $item): string => (string)($item['name'] ?? '[unknown]'),
                    $nextPending
                );

                throw new \RuntimeException(
                    'Unable to resolve parent dependencies for field_types: ' . implode(', ', $names)
                );
            }

            $pending = $nextPending;
        }

        return self::loadFieldTypeIdMap();
    }

    private static function importFields(array $items, array $typeIdMap): void
    {
        foreach ($items as $index => $item) {
            self::validateFieldItem($item, $index);

            $name = self::normalizeName($item['name']);
            $fieldTypeName = self::normalizeName($item['field_type']);

            if (!isset($typeIdMap[$fieldTypeName])) {
                throw new \RuntimeException(
                    "Field '{$name}' references unknown field type '{$fieldTypeName}'."
                );
            }

            self::upsertField($item, $typeIdMap[$fieldTypeName]);
        }
    }

    private static function upsertFieldType(array $item, int $parentId): int
    {
        $name = self::normalizeName($item['name']);
        $titleMap = self::normalizeRequiredTitleMap($item['title'] ?? null, "field type '{$name}'");
        $descriptionMap = self::normalizeLangMap($item['description'] ?? []);

        $existing = self::fetchOne(
            'SELECT type_id, uuid FROM field_types WHERE system_name = $1 LIMIT 1',
            [$name]
        );

        $row = \Core\ContentStructure::saveFieldType(
            $existing !== null ? (int)$existing['type_id'] : null,
            [
                'system_name' => $name,
                'parent_id' => $parentId,
                'type_settings' => $item['settings'] ?? [],
            ]
        );
        $typeId = (int)$row['type_id'];
        $uuid = (string)$row['uuid'];

        self::syncTranslations($uuid, $titleMap, $descriptionMap);

        return $typeId;
    }

    private static function upsertField(array $item, int $typeId): int
    {
        $name = self::normalizeName($item['name']);
        $titleMap = self::normalizeRequiredTitleMap($item['title'] ?? null, "field '{$name}'");
        $descriptionMap = self::normalizeLangMap($item['description'] ?? []);

        $existing = self::fetchOne(
            'SELECT field_id, uuid FROM fields WHERE system_name = $1 LIMIT 1',
            [$name]
        );

        $settings = is_array($item['settings'] ?? null) ? $item['settings'] : [];
        if (is_array($item['params'] ?? null) && $item['params'] !== []) {
            $settings['params'] = $item['params'];
        }

        $row = \Core\ContentStructure::saveField(
            $existing !== null ? (int)$existing['field_id'] : null,
            [
                'type_id' => $typeId,
                'system_name' => $name,
                'field_settings' => $settings,
            ]
        );
        $fieldId = (int)$row['field_id'];
        $uuid = (string)$row['uuid'];

        self::syncTranslations($uuid, $titleMap, $descriptionMap);

        return $fieldId;
    }

    private static function syncTranslations(string $entityUuid, array $titleMap, array $descriptionMap): void
    {
        $languages = array_unique(array_merge(array_keys($titleMap), array_keys($descriptionMap)));

        foreach ($languages as $langCode) {
            if (empty($titleMap[$langCode])) {
                continue;
            }

            $payload = [
                'title' => $titleMap[$langCode],
            ];

            if (!empty($descriptionMap[$langCode])) {
                $payload['description'] = $descriptionMap[$langCode];
            }

            $payloadJson = self::encodeJsonObject($payload);

            $existing = self::fetchOne(
                '
                SELECT translation_id
                FROM translations
                WHERE entity_uuid = $1::uuid
                  AND lang_code = $2
                LIMIT 1
                ',
                [$entityUuid, $langCode]
            );

            if ($existing !== null) {
                \DB::query(
                    '
                    UPDATE translations
                    SET translated_data = $1::jsonb,
                        updated_at = NOW()
                    WHERE translation_id = $2
                    ',
                    [$payloadJson, $existing['translation_id']]
                );
            } else {
                \DB::query(
                    '
                    INSERT INTO translations (
                        entity_uuid,
                        lang_code,
                        translated_data,
                        translation_status
                    )
                    VALUES ($1::uuid, $2, $3::jsonb, $4)
                    ',
                    [$entityUuid, $langCode, $payloadJson, self::DEFAULT_TRANSLATION_STATUS]
                );
            }
        }
    }

    private static function loadFieldTypeIdMap(): array
    {
        $rows = self::fetchAll('SELECT type_id, system_name FROM field_types');

        $map = [];

        foreach ($rows as $row) {
            $map[$row['system_name']] = (int)$row['type_id'];
        }

        return $map;
    }

    private static function loadJsonFile(string $path): array
    {
        if (!is_file($path) || !is_readable($path)) {
            throw new \RuntimeException("JSON file not found or not readable: {$path}");
        }

        $json = file_get_contents($path);

        if ($json === false) {
            throw new \RuntimeException("Unable to read JSON file: {$path}");
        }

        $data = json_decode($json, true);

        if (!is_array($data)) {
            throw new \RuntimeException('Invalid JSON structure.');
        }

        return $data;
    }

    private static function validateRootStructure(array $data): void
    {
        if (isset($data['field_types']) && !is_array($data['field_types'])) {
            throw new \RuntimeException('"field_types" must be an array.');
        }

        if (isset($data['fields']) && !is_array($data['fields'])) {
            throw new \RuntimeException('"fields" must be an array.');
        }
    }

    private static function validateFieldTypeItem(mixed $item, int $index): void
    {
        if (!is_array($item)) {
            throw new \RuntimeException("field_types[{$index}] must be an object.");
        }

        if (empty($item['name']) || !is_string($item['name'])) {
            throw new \RuntimeException("field_types[{$index}].name is required.");
        }

        if (isset($item['parent']) && $item['parent'] !== null && !is_string($item['parent'])) {
            throw new \RuntimeException("field_types[{$index}].parent must be string or null.");
        }

        if (empty($item['title']) || !is_array($item['title'])) {
            throw new \RuntimeException("field_types[{$index}].title is required.");
        }
    }

    private static function validateFieldItem(mixed $item, int $index): void
    {
        if (!is_array($item)) {
            throw new \RuntimeException("fields[{$index}] must be an object.");
        }

        if (empty($item['name']) || !is_string($item['name'])) {
            throw new \RuntimeException("fields[{$index}].name is required.");
        }

        if (empty($item['field_type']) || !is_string($item['field_type'])) {
            throw new \RuntimeException("fields[{$index}].field_type is required.");
        }

        if (empty($item['title']) || !is_array($item['title'])) {
            throw new \RuntimeException("fields[{$index}].title is required.");
        }
    }

    private static function normalizeRequiredTitleMap(mixed $value, string $entityLabel): array
    {
        $map = self::normalizeLangMap($value);

        if ($map === []) {
            throw new \RuntimeException("Missing required title translations for {$entityLabel}.");
        }

        return $map;
    }

    private static function normalizeLangMap(mixed $value): array
    {
        if (!is_array($value)) {
            return [];
        }

        $result = [];

        foreach ($value as $langCode => $text) {
            if (!is_string($langCode) || !is_string($text)) {
                continue;
            }

            $langCode = trim($langCode);
            $text = trim($text);

            if ($langCode === '' || $text === '') {
                continue;
            }

            $result[$langCode] = $text;
        }

        return $result;
    }

    private static function normalizeName(string $value): string
    {
        $value = trim($value);

        if (!preg_match('/^[a-z0-9_]+$/', $value)) {
            throw new \RuntimeException(
                "Invalid name '{$value}'. Allowed characters: lowercase latin letters, digits, underscore."
            );
        }

        return $value;
    }

    private static function normalizeNullableName(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        if (!is_string($value)) {
            return null;
        }

        $value = trim($value);

        return $value === '' ? null : self::normalizeName($value);
    }

    private static function encodeJsonObject(mixed $value): string
    {
        if (!is_array($value)) {
            $value = [];
        }

        $json = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        if ($json === false) {
            throw new \RuntimeException('Failed to encode JSON.');
        }

        return $json;
    }

    private static function fetchOne(string $sql, array $params = []): ?array
    {
        $result = \DB::query($sql, $params);

        if (is_array($result)) {
            if ($result === []) {
                return null;
            }

            return self::isListArray($result)
                ? ($result[0] ?? null)
                : $result;
        }

        $row = \DB::fetchRow($result);

        return is_array($row) ? $row : null;
    }

    private static function fetchAll(string $sql, array $params = []): array
    {
        $result = \DB::query($sql, $params);

        if (is_array($result)) {
            return self::isListArray($result) ? $result : [$result];
        }

        return \DB::fetchAll($result);
    }

    private static function isListArray(array $array): bool
    {
        return array_keys($array) === range(0, count($array) - 1);
    }
}
