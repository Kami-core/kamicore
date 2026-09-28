<?php

/**
 * KamiCore
 *
 * SPDX-License-Identifier: Apache-2.0
 *
 * @see https://kamicore.org
 */

declare(strict_types=1);

namespace Plugins\ContentManager;

if (!defined('IN_KAMI')) die();

/**
 * Portable import/export for ContentManager content type structures.
 *
 * IDs and UUIDs never leave the local installation. Cross-entity references
 * are serialized by system_name and resolved again during import.
 */
final class ContentTypeTransfer
{
    private const FORMAT = 'kami-content-types';
    private const VERSION = 1;
    private const SYSTEM_NAME_PATTERN = '/^[a-z][a-z0-9_]*$/';

    public function __construct(private readonly int $managerPluginId)
    {
        if ($managerPluginId < 1) {
            throw new \InvalidArgumentException('ContentManager plugin ID is required.');
        }
    }

    /**
     * @param array<int, int|string> $typeIds
     */
    public function export(array $typeIds): array
    {
        $ids = array_values(array_unique(array_filter(
            array_map('intval', $typeIds),
            static fn(int $id): bool => $id > 0
        )));
        if ($ids === []) {
            throw new \InvalidArgumentException('Select at least one content type to export.');
        }

        $types = [];
        $fieldNames = [];
        foreach ($ids as $id) {
            $row = \DB::getRow(
                'select ct.*, parent.system_name as parent_name
                 from content_types ct
                 left join content_types parent on parent.ct_id=ct.parent_id
                 where ct.ct_id=$1 and ct.manager_plugin_id=$2',
                [$id, $this->managerPluginId]
            );
            if (!$row) {
                throw new \DomainException("Content type {$id} is not managed by ContentManager.");
            }

            $schema = \Core\Utils\JsonTool::decodeArray($row['schema'] ?? null);
            $schema['fields'] = is_array($schema['fields'] ?? null)
                ? $schema['fields']
                : [];
            foreach (array_keys($schema['fields']) as $fieldName) {
                if (!is_string($fieldName)) {
                    throw new \RuntimeException("Invalid field reference in content type '{$row['system_name']}'.");
                }
                $fieldNames[$fieldName] = true;
            }

            $types[(string)$row['system_name']] = [
                'parent' => $row['parent_name'] !== null ? (string)$row['parent_name'] : null,
                'has_slug' => (bool)$row['has_slug'],
                'schema' => $schema,
                'translations' => $this->translations((string)$row['uuid']),
            ];
        }
        ksort($types, SORT_STRING);

        $fields = [];
        $queue = array_keys($fieldNames);
        while ($queue !== []) {
            $fieldName = array_shift($queue);
            if (isset($fields[$fieldName])) {
                continue;
            }

            $row = \DB::getRow(
                'select f.*, ft.system_name as type_name
                 from fields f
                 join field_types ft on ft.type_id=f.type_id
                 where f.system_name=$1',
                [$fieldName]
            );
            if (!$row) {
                throw new \RuntimeException("Unknown field referenced by exported structure: {$fieldName}");
            }

            $fieldType = \Core\Content::getFieldType((int)$row['type_id']);
            $settings = $this->normalizePortableSettings(
                $this->transformSettingsReferences(
                    \Core\Utils\JsonTool::decodeArray($row['field_settings'] ?? null),
                    $fieldType,
                    true
                )
            );

            foreach ($this->referencedNames($settings, $fieldType, 'field_id') as $dependency) {
                if (!isset($fields[$dependency]) && !in_array($dependency, $queue, true)) {
                    $queue[] = $dependency;
                }
            }

            $fields[$fieldName] = [
                'type' => (string)$row['type_name'],
                'settings' => $settings,
                'translations' => $this->translations((string)$row['uuid']),
            ];
        }
        ksort($fields, SORT_STRING);

        return [
            'format' => self::FORMAT,
            'version' => self::VERSION,
            'content_types' => $types,
            'fields' => $fields,
        ];
    }

    public function import(array $package, ?int $authorId = null): array
    {
        $plan = $this->preflight($package);

        if (!\DB::beginTransaction()) {
            throw new \RuntimeException('Failed to start content type import transaction.');
        }

        try {
            $typeRows = [];
            foreach ($plan['content_types'] as $systemName => $definition) {
                $typeRows[$systemName] = \Core\ContentStructure::saveContentType(null, [
                    'author_id' => $authorId,
                    'plugin_id' => null,
                    'default_manager_plugin_id' => $this->managerPluginId,
                    'manager_plugin_id' => $this->managerPluginId,
                    'manager_overridden' => false,
                    'system_name' => $systemName,
                    'parent_id' => null,
                    'has_slug' => (bool)$definition['has_slug'],
                    'schema' => ['fields' => []],
                ]);
            }

            $fieldRows = $plan['existing_fields'];
            $newFieldNames = [];

            // Create non-compound fields first without params. This establishes all
            // field identities before portable references are resolved to local IDs.
            foreach ($plan['fields'] as $systemName => $definition) {
                if (isset($fieldRows[$systemName]) || $definition['is_compound']) {
                    continue;
                }

                $fieldRows[$systemName] = \Core\ContentStructure::saveField(null, [
                    'type_id' => $definition['type_id'],
                    'system_name' => $systemName,
                    'field_settings' => $this->settingsWithoutParams($definition['settings']),
                ]);
                $newFieldNames[] = $systemName;
            }

            // Compound definitions require their component metadata at creation.
            // At this point all ordinary imported fields already have local IDs.
            foreach ($plan['fields'] as $systemName => $definition) {
                if (isset($fieldRows[$systemName]) || !$definition['is_compound']) {
                    continue;
                }

                $fieldRows[$systemName] = \Core\ContentStructure::saveField(null, [
                    'type_id' => $definition['type_id'],
                    'system_name' => $systemName,
                    'field_settings' => $this->transformSettingsReferences(
                        $definition['settings'],
                        $definition['field_type'],
                        false
                    ),
                ]);
                $newFieldNames[] = $systemName;
            }

            // Apply complete settings after every imported entity has a local ID.
            foreach ($newFieldNames as $systemName) {
                $definition = $plan['fields'][$systemName];
                \Core\ContentStructure::saveField((int)$fieldRows[$systemName]['field_id'], [
                    'type_id' => $definition['type_id'],
                    'system_name' => $systemName,
                    'field_settings' => $this->transformSettingsReferences(
                        $definition['settings'],
                        $definition['field_type'],
                        false
                    ),
                ]);
            }

            foreach ($plan['content_types'] as $systemName => $definition) {
                $parentId = null;
                if ($definition['parent'] !== null) {
                    if (isset($typeRows[$definition['parent']])) {
                        $parentId = (int)$typeRows[$definition['parent']]['ct_id'];
                    } else {
                        $parentId = (int)\DB::getOne(
                            'select ct_id from content_types where system_name=$1',
                            [$definition['parent']]
                        );
                    }
                }

                \Core\ContentStructure::saveContentType((int)$typeRows[$systemName]['ct_id'], [
                    'system_name' => $systemName,
                    'parent_id' => $parentId,
                    'has_slug' => (bool)$definition['has_slug'],
                    'schema' => $definition['schema'],
                ]);
            }

            foreach ($newFieldNames as $systemName) {
                $this->writeTranslations(
                    (string)$fieldRows[$systemName]['uuid'],
                    $plan['fields'][$systemName]['translations']
                );
            }
            foreach ($plan['content_types'] as $systemName => $definition) {
                $this->writeTranslations(
                    (string)$typeRows[$systemName]['uuid'],
                    $definition['translations']
                );
            }

            if (!\DB::commit()) {
                throw new \RuntimeException('Failed to commit content type import.');
            }

            \Core\Content::resetStructureCache();

            return [
                'created_types' => count($plan['content_types']),
                'created_fields' => count($newFieldNames),
                'reused_fields' => count($plan['existing_fields']),
            ];
        } catch (\Throwable $error) {
            \DB::rollBack();
            throw $error;
        }
    }

    private function preflight(array $package): array
    {
        if (($package['format'] ?? null) !== self::FORMAT) {
            throw new \InvalidArgumentException('Unsupported content type import format.');
        }
        if ((int)($package['version'] ?? 0) !== self::VERSION) {
            throw new \InvalidArgumentException('Unsupported content type import version.');
        }

        $contentTypes = $package['content_types'] ?? null;
        $fields = $package['fields'] ?? null;
        if (!is_array($contentTypes) || $contentTypes === []) {
            throw new \InvalidArgumentException('The import contains no content types.');
        }
        if (!is_array($fields)) {
            throw new \InvalidArgumentException('Invalid fields section.');
        }

        $normalizedTypes = [];
        foreach ($contentTypes as $systemName => $definition) {
            $this->assertSystemName($systemName, 'content type');
            if (!is_array($definition)) {
                throw new \InvalidArgumentException("Invalid content type definition: {$systemName}");
            }
            if (\DB::getOne('select 1 from content_types where system_name=$1', [$systemName])) {
                throw new \DomainException(
                    "Content type '{$systemName}' already exists. Import does not overwrite existing content types."
                );
            }

            $parent = $definition['parent'] ?? null;
            if ($parent !== null && $parent !== '') {
                $this->assertSystemName($parent, "parent of {$systemName}");
                $parent = (string)$parent;
            } else {
                $parent = null;
            }

            $schema = $definition['schema'] ?? null;
            if (!is_array($schema)) {
                throw new \InvalidArgumentException("Invalid schema for content type '{$systemName}'.");
            }
            $schema['fields'] = is_array($schema['fields'] ?? null) ? $schema['fields'] : [];
            foreach ($schema['fields'] as $fieldName => $localDefinition) {
                $this->assertSystemName($fieldName, "field in {$systemName}");
                if (!is_array($localDefinition)) {
                    throw new \InvalidArgumentException(
                        "Invalid field attachment '{$fieldName}' in content type '{$systemName}'."
                    );
                }
                $this->validateLocalFieldDefinition(
                    $systemName,
                    $fieldName,
                    $localDefinition
                );
                if (!array_key_exists($fieldName, $fields)) {
                    throw new \InvalidArgumentException(
                        "Content type '{$systemName}' references field '{$fieldName}', but its definition is missing."
                    );
                }
            }
            foreach (['title_field', 'summary_field'] as $key) {
                $fieldName = $schema[$key] ?? null;
                if ($fieldName !== null && $fieldName !== ''
                    && !array_key_exists((string)$fieldName, $schema['fields'])) {
                    throw new \InvalidArgumentException(
                        "{$key} in content type '{$systemName}' must reference an attached field."
                    );
                }
            }

            $normalizedTypes[$systemName] = [
                'parent' => $parent,
                'has_slug' => (bool)($definition['has_slug'] ?? false),
                'schema' => $schema,
                'translations' => $this->normalizeTranslations(
                    $definition['translations'] ?? [],
                    "content type '{$systemName}'"
                ),
            ];
        }

        foreach ($normalizedTypes as $systemName => $definition) {
            $parent = $definition['parent'];
            if ($parent === null) {
                continue;
            }
            if (!isset($normalizedTypes[$parent])
                && !\DB::getOne('select 1 from content_types where system_name=$1', [$parent])) {
                throw new \DomainException(
                    "Content type '{$systemName}' requires missing parent content type '{$parent}'."
                );
            }
        }
        $this->assertNoImportedParentCycles($normalizedTypes);

        $normalizedFields = [];
        $existingFields = [];
        foreach ($fields as $systemName => $definition) {
            $this->assertSystemName($systemName, 'field');
            if (!is_array($definition)) {
                throw new \InvalidArgumentException("Invalid field definition: {$systemName}");
            }

            $typeName = trim((string)($definition['type'] ?? ''));
            $this->assertSystemName($typeName, "field type of {$systemName}");
            $typeRow = \DB::getRow(
                'select type_id from field_types where system_name=$1',
                [$typeName]
            );
            if (!$typeRow) {
                throw new \DomainException(
                    "Field '{$systemName}' requires unavailable field type '{$typeName}'."
                );
            }

            $fieldType = \Core\Content::getFieldType((int)$typeRow['type_id']);
            $rawSettings = $definition['settings'] ?? [];
            if (!is_array($rawSettings)) {
                throw new \InvalidArgumentException(
                    "Invalid settings for field '{$systemName}'."
                );
            }
            $this->validateImportedFieldSettings($systemName, $rawSettings, $fieldType);
            $settings = $this->normalizePortableSettings($rawSettings);

            $normalizedFields[$systemName] = [
                'type' => $typeName,
                'type_id' => (int)$typeRow['type_id'],
                'field_type' => $fieldType,
                'is_compound' => (string)($fieldType['root_type_name'] ?? '') === 'compound',
                'settings' => $settings,
                'translations' => $this->normalizeTranslations(
                    $definition['translations'] ?? [],
                    "field '{$systemName}'"
                ),
            ];

            $existing = \DB::getRow(
                'select f.*, ft.system_name as type_name
                 from fields f
                 join field_types ft on ft.type_id=f.type_id
                 where f.system_name=$1',
                [$systemName]
            );
            if (!$existing) {
                $this->validatePortableReferences(
                    $systemName,
                    $settings,
                    $fieldType,
                    $normalizedTypes,
                    $fields
                );
                continue;
            }

            $existingType = \Core\Content::getFieldType((int)$existing['type_id']);
            $existingPortable = [
                'type' => (string)$existing['type_name'],
                'settings' => $this->normalizePortableSettings(
                    $this->transformSettingsReferences(
                        \Core\Utils\JsonTool::decodeArray($existing['field_settings'] ?? null),
                        $existingType,
                        true
                    )
                ),
            ];
            $importedPortable = [
                'type' => $typeName,
                'settings' => $settings,
            ];

            if ($this->canonical($existingPortable) !== $this->canonical($importedPortable)) {
                throw new \DomainException(
                    "Field '{$systemName}' already exists with a different definition."
                );
            }

            $existingFields[$systemName] = $existing;
        }

        // Dependency fields exported because another field references them must also
        // be valid even when they are not attached directly to a selected type.
        foreach ($normalizedFields as $systemName => $definition) {
            if (isset($existingFields[$systemName])) {
                continue;
            }
            foreach ($this->referencedNames(
                $definition['settings'],
                $definition['field_type'],
                'field_id'
            ) as $dependency) {
                if (!isset($normalizedFields[$dependency])
                    && !\DB::getOne('select 1 from fields where system_name=$1', [$dependency])) {
                    throw new \DomainException(
                        "Field '{$systemName}' requires missing field '{$dependency}'."
                    );
                }
            }
        }

        return [
            'content_types' => $normalizedTypes,
            'fields' => $normalizedFields,
            'existing_fields' => $existingFields,
        ];
    }

    private function translations(string $uuid): array
    {
        $translations = [];
        $result = \DB::query(
            'select lang_code, translated_data from translations
             where entity_uuid=$1 order by lang_code',
            [$uuid]
        );
        while ($row = \DB::fetchRow($result)) {
            $translations[(string)$row['lang_code']] =
                \Core\Utils\JsonTool::decodeArray($row['translated_data'] ?? null);
        }
        return $translations;
    }

    private function normalizeTranslations(mixed $translations, string $entity): array
    {
        if (!is_array($translations)) {
            throw new \InvalidArgumentException("Invalid translations for {$entity}.");
        }

        $normalized = [];
        foreach ($translations as $language => $data) {
            if (!is_string($language)
                || !preg_match('/^[a-z]{2}(?:-[A-Z]{2})?$/', $language)
                || !is_array($data)) {
                throw new \InvalidArgumentException("Invalid translation in {$entity}.");
            }
            $normalized[$language] = $data;
        }
        ksort($normalized, SORT_STRING);
        return $normalized;
    }

    private function writeTranslations(string $uuid, array $translations): void
    {
        foreach ($translations as $language => $data) {
            $result = \DB::query(
                'insert into translations(entity_uuid, lang_code, translated_data)
                 values($1, $2, $3)
                 on conflict (entity_uuid, lang_code)
                 do update set translated_data=excluded.translated_data',
                [$uuid, $language, \Core\Utils\JsonTool::encode($data, false)]
            );
            if ($result === false) {
                throw new \RuntimeException("Failed to save {$language} translation.");
            }
            \Cache::del("globals:{$uuid}_{$language}");
        }
    }

    private function validateImportedFieldSettings(
        string $fieldName,
        array $settings,
        array $fieldType
    ): void {
        $allowed = array_fill_keys(['indexed', 'unique', 'translatable', 'params'], true);
        foreach ($settings as $name => $value) {
            if (!is_string($name) || !isset($allowed[$name])) {
                throw new \InvalidArgumentException(
                    "Unsupported global setting '{$name}' for field '{$fieldName}'."
                );
            }
            if (in_array($name, ['indexed', 'unique', 'translatable'], true)
                && !is_bool($value)) {
                throw new \InvalidArgumentException(
                    "Field '{$fieldName}' setting '{$name}' must be boolean."
                );
            }
        }
        if (array_key_exists('params', $settings) && !is_array($settings['params'])) {
            throw new \InvalidArgumentException(
                "Field '{$fieldName}' params must be an object."
            );
        }

        $indexed = !empty($settings['indexed']);
        $unique = !empty($settings['unique']);
        $translatable = !empty($settings['translatable']);
        if ($unique && !$indexed) {
            throw new \DomainException(
                "Field '{$fieldName}' cannot be unique without indexed=true."
            );
        }
        if (!empty($fieldType['type_settings']['requires_indexed']) && !$indexed) {
            throw new \DomainException(
                "Field '{$fieldName}' uses a field type that requires indexed=true."
            );
        }
        if ((string)($fieldType['root_type_name'] ?? '') === 'compound') {
            if ($unique || $translatable) {
                throw new \DomainException(
                    "Compound field '{$fieldName}' cannot be unique or globally translatable."
                );
            }
            $components = $settings['params']['components'] ?? null;
            if (!is_array($components) || $components === []) {
                throw new \DomainException(
                    "Compound field '{$fieldName}' requires components."
                );
            }
        }
    }

    private function validateLocalFieldDefinition(
        string $contentType,
        string $fieldName,
        array $definition
    ): void {
        foreach (['type', 'params', 'options'] as $name) {
            if (array_key_exists($name, $definition)) {
                throw new \InvalidArgumentException(
                    "Field attachment '{$fieldName}' in '{$contentType}' contains global property '{$name}'."
                );
            }
        }

        $settings = $definition['settings'] ?? [];
        if (!is_array($settings)) {
            throw new \InvalidArgumentException(
                "Invalid local settings for field '{$fieldName}' in '{$contentType}'."
            );
        }
        foreach ($settings as $name => $value) {
            if (!in_array($name, ['required', 'multiple', 'hidden', 'readonly'], true)) {
                throw new \InvalidArgumentException(
                    "Unsupported local setting '{$name}' for field '{$fieldName}' in '{$contentType}'."
                );
            }
            if (!is_bool($value)) {
                throw new \InvalidArgumentException(
                    "Local setting '{$name}' for field '{$fieldName}' must be boolean."
                );
            }
        }

        if (array_key_exists('search_weight', $definition)) {
            $weight = strtoupper(trim((string)$definition['search_weight']));
            if ($weight !== '' && !in_array($weight, ['A', 'B', 'C', 'D'], true)) {
                throw new \InvalidArgumentException(
                    "Invalid search weight for field '{$fieldName}' in '{$contentType}'."
                );
            }
        }
    }

    private function normalizePortableSettings(array $settings): array
    {
        $normalized = [
            'indexed' => !empty($settings['indexed']),
            'unique' => !empty($settings['unique']),
            'translatable' => !empty($settings['translatable']),
        ];
        if ($normalized['unique']) {
            $normalized['indexed'] = true;
        }

        if (is_array($settings['params'] ?? null) && $settings['params'] !== []) {
            $normalized['params'] = $settings['params'];
        }
        return $normalized;
    }

    private function settingsWithoutParams(array $settings): array
    {
        unset($settings['params']);
        return $settings;
    }

    private function transformSettingsReferences(
        array $settings,
        array $fieldType,
        bool $toPortable
    ): array {
        $params = is_array($settings['params'] ?? null) ? $settings['params'] : [];
        if ($params === []) {
            return $settings;
        }

        $definitions = is_array($fieldType['type_settings']['parameters'] ?? null)
            ? $fieldType['type_settings']['parameters']
            : [];
        $params = $this->transformParamReferences($params, $definitions, $toPortable);

        if ((string)($fieldType['root_type_name'] ?? '') === 'compound'
            && is_array($params['components'] ?? null)) {
            foreach ($params['components'] as $componentName => $component) {
                if (!is_array($component)) {
                    continue;
                }
                $componentTypeName = trim((string)($component['type'] ?? ''));
                if ($componentTypeName === '') {
                    continue;
                }
                try {
                    $componentType = \Core\Content::getFieldType($componentTypeName);
                } catch (\Throwable) {
                    continue;
                }
                $componentDefinitions = is_array($componentType['type_settings']['parameters'] ?? null)
                    ? $componentType['type_settings']['parameters']
                    : [];
                $componentParams = is_array($component['params'] ?? null)
                    ? $component['params']
                    : [];
                if ($componentParams !== []) {
                    $component['params'] = $this->transformParamReferences(
                        $componentParams,
                        $componentDefinitions,
                        $toPortable
                    );
                }
                $params['components'][$componentName] = $component;
            }
        }

        $settings['params'] = $params;
        return $settings;
    }

    private function transformParamReferences(
        array $params,
        array $definitions,
        bool $toPortable
    ): array {
        foreach ($params as $name => $value) {
            $definition = $definitions[$name] ?? null;
            if (!is_array($definition)
                || !in_array($definition['type'] ?? null, ['field_id', 'content_type_id'], true)) {
                continue;
            }

            $referenceType = (string)$definition['type'];
            $storedAsName = ($definition['value'] ?? null) === 'system_name';
            if ($storedAsName) {
                continue;
            }

            $multiple = !empty($definition['multiple']);
            $values = $multiple ? (is_array($value) ? $value : [$value]) : [$value];
            $mapped = [];
            foreach ($values as $reference) {
                if ($reference === null || $reference === '') {
                    continue;
                }
                $mapped[] = $toPortable
                    ? $this->referenceIdToName($referenceType, $reference)
                    : $this->referenceNameToId($referenceType, $reference);
            }
            $params[$name] = $multiple ? $mapped : ($mapped[0] ?? null);
        }

        return $params;
    }

    private function referenceIdToName(string $type, mixed $id): string
    {
        $table = $type === 'field_id' ? 'fields' : 'content_types';
        $idColumn = $type === 'field_id' ? 'field_id' : 'ct_id';
        $name = \DB::getOne(
            "select system_name from {$table} where {$idColumn}=$1",
            [(int)$id]
        );
        if (!is_string($name) || $name === '') {
            throw new \RuntimeException("Cannot export unresolved {$type} reference '{$id}'.");
        }
        return $name;
    }

    private function referenceNameToId(string $type, mixed $name): int
    {
        if (!is_string($name) || !preg_match(self::SYSTEM_NAME_PATTERN, $name)) {
            throw new \DomainException("Invalid portable {$type} reference.");
        }

        $table = $type === 'field_id' ? 'fields' : 'content_types';
        $idColumn = $type === 'field_id' ? 'field_id' : 'ct_id';
        $id = (int)(\DB::getOne(
            "select {$idColumn} from {$table} where system_name=$1",
            [$name]
        ) ?: 0);
        if ($id < 1) {
            throw new \DomainException("Cannot resolve {$type} reference '{$name}'.");
        }
        return $id;
    }

    private function referencedNames(array $settings, array $fieldType, string $referenceType): array
    {
        $names = [];
        $params = is_array($settings['params'] ?? null) ? $settings['params'] : [];
        $definitions = is_array($fieldType['type_settings']['parameters'] ?? null)
            ? $fieldType['type_settings']['parameters']
            : [];
        $this->collectReferencedNames($names, $params, $definitions, $referenceType);

        if ((string)($fieldType['root_type_name'] ?? '') === 'compound'
            && is_array($params['components'] ?? null)) {
            foreach ($params['components'] as $component) {
                if (!is_array($component)) {
                    continue;
                }
                $componentTypeName = trim((string)($component['type'] ?? ''));
                if ($componentTypeName === '') {
                    continue;
                }
                try {
                    $componentType = \Core\Content::getFieldType($componentTypeName);
                } catch (\Throwable) {
                    continue;
                }
                $componentDefinitions = is_array($componentType['type_settings']['parameters'] ?? null)
                    ? $componentType['type_settings']['parameters']
                    : [];
                $componentParams = is_array($component['params'] ?? null)
                    ? $component['params']
                    : [];
                $this->collectReferencedNames(
                    $names,
                    $componentParams,
                    $componentDefinitions,
                    $referenceType
                );
            }
        }

        return array_values(array_unique($names));
    }

    private function collectReferencedNames(
        array &$names,
        array $params,
        array $definitions,
        string $referenceType
    ): void {
        foreach ($params as $name => $value) {
            $definition = $definitions[$name] ?? null;
            if (!is_array($definition) || ($definition['type'] ?? null) !== $referenceType) {
                continue;
            }

            foreach ((array)$value as $reference) {
                if ($reference === null || $reference === '') {
                    continue;
                }
                if (!is_string($reference)
                    || !preg_match(self::SYSTEM_NAME_PATTERN, $reference)) {
                    throw new \DomainException(
                        "Invalid portable {$referenceType} reference in parameter '{$name}'."
                    );
                }
                $names[] = $reference;
            }
        }
    }

    private function validatePortableReferences(
        string $fieldName,
        array $settings,
        array $fieldType,
        array $importedTypes,
        array $importedFields
    ): void {
        foreach ([
            'field_id' => ['table' => 'fields', 'imported' => $importedFields],
            'content_type_id' => ['table' => 'content_types', 'imported' => $importedTypes],
        ] as $referenceType => $config) {
            foreach ($this->referencedNames($settings, $fieldType, $referenceType) as $reference) {
                if (array_key_exists($reference, $config['imported'])) {
                    continue;
                }
                if (!\DB::getOne(
                    "select 1 from {$config['table']} where system_name=$1",
                    [$reference]
                )) {
                    $label = $referenceType === 'field_id' ? 'field' : 'content type';
                    throw new \DomainException(
                        "Field '{$fieldName}' requires missing {$label} '{$reference}'."
                    );
                }
            }
        }
    }

    private function assertNoImportedParentCycles(array $types): void
    {
        $visiting = [];
        $visited = [];

        $visit = function (string $name) use (&$visit, &$visiting, &$visited, $types): void {
            if (isset($visited[$name])) {
                return;
            }
            if (isset($visiting[$name])) {
                throw new \DomainException("Imported content type hierarchy contains a cycle at '{$name}'.");
            }

            $visiting[$name] = true;
            $parent = $types[$name]['parent'] ?? null;
            if (is_string($parent) && isset($types[$parent])) {
                $visit($parent);
            }
            unset($visiting[$name]);
            $visited[$name] = true;
        };

        foreach (array_keys($types) as $name) {
            $visit($name);
        }
    }

    private function assertSystemName(mixed $name, string $entity): void
    {
        if (!is_string($name) || !preg_match(self::SYSTEM_NAME_PATTERN, $name)) {
            throw new \InvalidArgumentException("Invalid system name for {$entity}.");
        }
    }

    private function canonical(mixed $value): string
    {
        return \Core\Utils\JsonTool::encode($this->sortCanonical($value), false);
    }

    private function sortCanonical(mixed $value): mixed
    {
        if (!is_array($value)) {
            return $value;
        }

        if (array_is_list($value)) {
            return array_map(fn(mixed $item): mixed => $this->sortCanonical($item), $value);
        }

        ksort($value, SORT_STRING);
        foreach ($value as $key => $item) {
            $value[$key] = $this->sortCanonical($item);
        }
        return $value;
    }
}

