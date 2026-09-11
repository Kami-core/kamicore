<?php

/**
 * KamiCore
 *
 * SPDX-License-Identifier: Apache-2.0
 *
 * @see https://kamicore.org
 */

declare(strict_types=1);

namespace Core;

if (!defined('IN_KAMI')) die();

/**
 * Single write boundary for content structure metadata.
 *
 * Permissions belong to callers (plugins, API handlers, installers). This class
 * only validates structural integrity and applies atomic changes.
 */
final class ContentStructure
{
    private const SYSTEM_NAME_PATTERN = '/^[a-z][a-z0-9_]*$/';
    private const INDEX_TABLES = ['item_texts', 'item_nums', 'item_bools', 'item_dates'];
    private const GLOBAL_FIELD_SETTINGS = ['indexed', 'unique', 'translatable'];
    private const LOCAL_FIELD_SETTINGS = ['required', 'multiple', 'hidden', 'readonly'];

    public static function saveContentType(?int $id, array $data): array
    {
        return self::transaction(function () use ($id, $data): array {
            $existing = $id !== null && $id > 0
                ? \DB::getRow('select * from content_types where ct_id=$1 for update', [$id])
                : null;

            if ($id !== null && $id > 0 && !$existing) {
                throw new \OutOfBoundsException("Unknown content type: {$id}");
            }

            $systemName = trim((string)($data['system_name'] ?? $existing['system_name'] ?? ''));
            self::assertSystemName($systemName, 'content type');

            if (\DB::getOne(
                'select 1 from content_types where system_name=$1 and ct_id<>$2 limit 1',
                [$systemName, (int)($existing['ct_id'] ?? 0)]
            )) {
                throw new \DomainException("Content type '{$systemName}' already exists.");
            }

            $parentId = array_key_exists('parent_id', $data)
                ? self::nullablePositiveInt($data['parent_id'])
                : self::nullablePositiveInt($existing['parent_id'] ?? null);
            self::validateContentTypeParent((int)($existing['ct_id'] ?? 0), $parentId);

            $schema = array_key_exists('schema', $data)
                ? self::decodeObject($data['schema'])
                : self::decodeObject($existing['schema'] ?? null);
            $schema['fields'] = is_array($schema['fields'] ?? null) ? $schema['fields'] : [];
            self::validateSchemaReferences($schema);

            $write = [];
            foreach ([
                'author_id',
                'plugin_id',
                'default_manager_plugin_id',
                'manager_plugin_id',
                'manager_overridden',
                'has_slug',
            ] as $column) {
                if (array_key_exists($column, $data)) {
                    $write[$column] = $data[$column];
                }
            }
            $write['system_name'] = $systemName;
            $write['parent_id'] = $parentId;
            $write['schema'] = self::json($schema);

            if ($existing) {
                $searchWeightFields = self::changedSearchWeightFields(
                    self::decodeObject($existing['schema'] ?? null),
                    $schema
                );
                self::assertWrite(
                    \DB::update('content_types', $write, 'ct_id=$1', [(int)$existing['ct_id']]),
                    "Failed to update content type '{$systemName}'."
                );
                self::refreshFieldSearchVectors((int)$existing['ct_id'], $searchWeightFields);
                $row = array_replace($existing, $write);
                $row['ct_id'] = (int)$existing['ct_id'];
            } else {
                $row = \DB::insert(
                    'content_types',
                    $write,
                    'ct_id, uuid, system_name, plugin_id, parent_id, manager_plugin_id, manager_overridden'
                );
                if (!is_array($row)) {
                    throw new \RuntimeException("Failed to create content type '{$systemName}'.");
                }
                $row['ct_id'] = (int)$row['ct_id'];
            }

            self::invalidateContentType($row, $existing ? (string)$existing['system_name'] : null);
            return $row;
        });
    }

    public static function invalidateContentTypeCache(int|string $contentType): void
    {
        $row = is_int($contentType) || ctype_digit((string)$contentType)
            ? \DB::getRow(
                'select ct_id, uuid, system_name from content_types where ct_id=$1',
                [(int)$contentType]
            )
            : \DB::getRow(
                'select ct_id, uuid, system_name from content_types where system_name=$1',
                [(string)$contentType]
            );
        if ($row) self::invalidateContentType($row);
    }

    public static function invalidateFieldCache(int|string $field): void
    {
        $fieldId = is_int($field) || ctype_digit((string)$field)
            ? (int)$field
            : (int)(\DB::getOne('select field_id from fields where system_name=$1', [(string)$field]) ?: 0);
        if ($fieldId > 0) self::invalidateField($fieldId);
    }

    public static function deleteContentType(int $id): void
    {
        self::transaction(function () use ($id): void {
            $type = \DB::getRow(
                'select ct_id, uuid, system_name from content_types where ct_id=$1 for update',
                [$id]
            );
            if (!$type) {
                throw new \OutOfBoundsException("Unknown content type: {$id}");
            }

            if (\DB::getOne('select 1 from content_items where ct_id=$1 limit 1', [$id])) {
                throw new \DomainException('Content type contains items and cannot be deleted.');
            }
            if (\DB::getOne('select 1 from content_types where parent_id=$1 limit 1', [$id])) {
                throw new \DomainException('Content type has child types and cannot be deleted.');
            }

            \DB::delete('translations', 'entity_uuid=$1', [(string)$type['uuid']]);
            self::assertWrite(
                \DB::delete('content_types', 'ct_id=$1', [$id]),
                'Failed to delete content type.'
            );
            self::invalidateContentType($type);
        });
    }

    public static function saveField(?int $id, array $data): array
    {
        return self::transaction(function () use ($id, $data): array {
            $existing = $id !== null && $id > 0
                ? \DB::getRow('select * from fields where field_id=$1 for update', [$id])
                : null;

            if ($id !== null && $id > 0 && !$existing) {
                throw new \OutOfBoundsException("Unknown field: {$id}");
            }

            $systemName = trim((string)($data['system_name'] ?? $existing['system_name'] ?? ''));
            self::assertSystemName($systemName, 'field');

            if (\DB::getOne(
                'select 1 from fields where system_name=$1 and field_id<>$2 limit 1',
                [$systemName, (int)($existing['field_id'] ?? 0)]
            )) {
                throw new \DomainException("Field '{$systemName}' already exists.");
            }

            $typeId = (int)($data['type_id'] ?? $existing['type_id'] ?? 0);
            if ($typeId < 1 || !\DB::getOne('select 1 from field_types where type_id=$1', [$typeId])) {
                throw new \InvalidArgumentException('Unknown field type.');
            }

            $fieldSettings = array_key_exists('field_settings', $data)
                ? self::normalizeGlobalFieldSettings(self::decodeObject($data['field_settings']))
                : self::normalizeGlobalFieldSettings(self::decodeObject($existing['field_settings'] ?? null));
            $fieldType = Content::getFieldType($typeId);
            if ((string)($fieldType['root_type_name'] ?? '') === 'compound') {
                if (!empty($fieldSettings['translatable'])) {
                    throw new \DomainException(
                        'Compound fields define translation per component and cannot be translatable as a whole.'
                    );
                }
                if (!empty($fieldSettings['unique'])) {
                    throw new \DomainException('Compound fields cannot be unique.');
                }

                $params = is_array($fieldSettings['params'] ?? null)
                    ? $fieldSettings['params']
                    : [];
                $params['components'] = self::normalizeCompoundComponents(
                    $params['components'] ?? []
                );
                $fieldSettings['params'] = $params;
            }
            if (!empty($fieldType['type_settings']['requires_indexed'])
                && empty($fieldSettings['indexed'])) {
                throw new \DomainException(
                    "Field type '{$fieldType['system_name']}' requires indexed=true."
                );
            }
            if (!empty($fieldSettings['unique']) && empty($fieldSettings['indexed'])) {
                throw new \DomainException('Unique fields must be indexed.');
            }

            $oldFieldSettings = $existing
                ? self::normalizeGlobalFieldSettings(
                    self::decodeObject($existing['field_settings'] ?? null)
                )
                : [];

            if ($existing && (string)($fieldType['root_type_name'] ?? '') === 'compound') {
                $oldFieldType = Content::getFieldType((int)$existing['type_id']);
                if ((string)($oldFieldType['root_type_name'] ?? '') === 'compound') {
                    self::assertCompoundDefinitionChangeAllowed(
                        (int)$existing['field_id'],
                        (string)$existing['system_name'],
                        $oldFieldSettings,
                        $fieldSettings
                    );
                }
            }

            $translatableChanged = $existing
                && !empty($oldFieldSettings['translatable']) !== !empty($fieldSettings['translatable']);
            if (
                $translatableChanged
                && self::fieldHasValues((int)$existing['field_id'], (string)$existing['system_name'])
            ) {
                throw new \DomainException(
                    'A field containing values cannot change translatable mode without a data migration.'
                );
            }

            $identityChanged = $existing && (
                (string)$existing['system_name'] !== $systemName
                || (int)$existing['type_id'] !== $typeId
            );
            if ($identityChanged && self::fieldHasValues((int)$existing['field_id'], (string)$existing['system_name'])) {
                throw new \DomainException('A field containing values cannot change its system name or type.');
            }

            $write = [
                'type_id' => $typeId,
                'system_name' => $systemName,
            ];
            if (array_key_exists('variant_id', $data)) {
                $write['variant_id'] = $data['variant_id'];
            }
            if (array_key_exists('field_settings', $data)) {
                $write['field_settings'] = self::json($fieldSettings);
            }

            if ($existing) {
                $oldName = (string)$existing['system_name'];
                if ($oldName !== $systemName) {
                    self::renameFieldInSchemas($oldName, $systemName);
                }
                self::assertWrite(
                    \DB::update('fields', $write, 'field_id=$1', [(int)$existing['field_id']]),
                    "Failed to update field '{$systemName}'."
                );
                self::syncFieldIndexSemantics(
                    (int)$existing['field_id'],
                    $systemName,
                    $oldFieldSettings,
                    $fieldSettings
                );
                $row = array_replace($existing, $write);
                $row['field_id'] = (int)$existing['field_id'];
            } else {
                $write['variant_id'] ??= null;
                $row = \DB::insert('fields', $write, 'field_id, uuid, system_name, type_id');
                if (!is_array($row)) {
                    throw new \RuntimeException("Failed to create field '{$systemName}'.");
                }
                $row['field_id'] = (int)$row['field_id'];
            }

            self::invalidateField((int)$row['field_id']);
            return $row;
        });
    }

    public static function deleteField(int $id): void
    {
        self::transaction(function () use ($id): void {
            $field = \DB::getRow(
                'select field_id, uuid, system_name from fields where field_id=$1 for update',
                [$id]
            );
            if (!$field) {
                throw new \OutOfBoundsException("Unknown field: {$id}");
            }

            $name = (string)$field['system_name'];
            if (\DB::getOne(
                "select 1 from content_types where coalesce(schema->'fields', '{}'::jsonb) ? $1 limit 1",
                [$name]
            )) {
                throw new \DomainException('Field is attached to a content type and cannot be deleted.');
            }
            self::purgeFieldValues($id, $name);

            \DB::delete('translations', 'entity_uuid=$1', [(string)$field['uuid']]);
            self::assertWrite(\DB::delete('fields', 'field_id=$1', [$id]), 'Failed to delete field.');
            self::invalidateField($id);
        });
    }

    public static function saveFieldType(?int $id, array $data): array
    {
        return self::transaction(function () use ($id, $data): array {
            $existing = $id !== null && $id > 0
                ? \DB::getRow('select * from field_types where type_id=$1 for update', [$id])
                : null;

            if ($id !== null && $id > 0 && !$existing) {
                throw new \OutOfBoundsException("Unknown field type: {$id}");
            }

            $systemName = trim((string)($data['system_name'] ?? $existing['system_name'] ?? ''));
            self::assertSystemName($systemName, 'field type');

            if (\DB::getOne(
                'select 1 from field_types where system_name=$1 and type_id<>$2 limit 1',
                [$systemName, (int)($existing['type_id'] ?? 0)]
            )) {
                throw new \DomainException("Field type '{$systemName}' already exists.");
            }

            $parentId = array_key_exists('parent_id', $data)
                ? self::nullablePositiveInt($data['parent_id'])
                : self::nullablePositiveInt($existing['parent_id'] ?? null);
            self::validateFieldTypeParent((int)($existing['type_id'] ?? 0), $parentId);

            if (
                $existing
                && self::nullablePositiveInt($existing['parent_id'] ?? null) !== $parentId
                && self::fieldTypeIsUsed((int)$existing['type_id'])
            ) {
                throw new \DomainException('A field type in use cannot change its parent.');
            }

            $write = [
                'system_name' => $systemName,
                'parent_id' => $parentId ?? 0,
            ];
            if (array_key_exists('type_settings', $data)) {
                $write['type_settings'] = self::json(self::decodeObject($data['type_settings']));
            }

            if ($existing) {
                $oldName = (string)$existing['system_name'];
                self::assertWrite(
                    \DB::update('field_types', $write, 'type_id=$1', [(int)$existing['type_id']]),
                    "Failed to update field type '{$systemName}'."
                );
                $row = array_replace($existing, $write);
                $row['type_id'] = (int)$existing['type_id'];
            } else {
                $row = \DB::insert('field_types', $write, 'type_id, uuid, system_name, parent_id');
                if (!is_array($row)) {
                    throw new \RuntimeException("Failed to create field type '{$systemName}'.");
                }
                $row['type_id'] = (int)$row['type_id'];
            }

            self::invalidateFieldType();
            return $row;
        });
    }

    public static function deleteFieldType(int $id): void
    {
        self::transaction(function () use ($id): void {
            $type = \DB::getRow(
                'select type_id, uuid from field_types where type_id=$1 for update',
                [$id]
            );
            if (!$type) {
                throw new \OutOfBoundsException("Unknown field type: {$id}");
            }

            if (\DB::getOne('select 1 from fields where type_id=$1 limit 1', [$id])) {
                throw new \DomainException('Field type is used by fields and cannot be deleted.');
            }
            if (\DB::getOne('select 1 from field_types where parent_id=$1 limit 1', [$id])) {
                throw new \DomainException('Field type has child types and cannot be deleted.');
            }

            \DB::delete('translations', 'entity_uuid=$1', [(string)$type['uuid']]);
            self::assertWrite(
                \DB::delete('field_types', 'type_id=$1', [$id]),
                'Failed to delete field type.'
            );
            self::invalidateFieldType();
        });
    }

    public static function attachField(int $contentTypeId, int $fieldId, array $local = []): void
    {
        self::transaction(function () use ($contentTypeId, $fieldId, $local): void {
            $type = self::contentTypeForUpdate($contentTypeId);
            $field = \DB::getRow(
                'select f.* from fields f where f.field_id=$1',
                [$fieldId]
            );
            if (!$field) {
                throw new \OutOfBoundsException("Unknown field: {$fieldId}");
            }

            $schema = self::decodeObject($type['schema'] ?? null);
            $schema['fields'] = is_array($schema['fields'] ?? null) ? $schema['fields'] : [];
            $name = (string)$field['system_name'];
            if (isset($schema['fields'][$name])) {
                throw new \DomainException("Field '{$name}' is already attached.");
            }

            $entry = [
                'displayorder' => self::nextFieldOrder($schema['fields']),
            ];
            $schema['fields'][$name] = self::normalizeLocalFieldDefinition(
                array_replace_recursive($entry, $local)
            );
            self::storeTypeSchema($type, $schema);
        });
    }

    public static function configureField(int $contentTypeId, int $fieldId, array $local): void
    {
        self::transaction(function () use ($contentTypeId, $fieldId, $local): void {
            $type = self::contentTypeForUpdate($contentTypeId);
            $field = \DB::getRow(
                'select f.system_name from fields f where f.field_id=$1',
                [$fieldId]
            );
            if (!$field) {
                throw new \OutOfBoundsException("Unknown field: {$fieldId}");
            }

            $schema = self::decodeObject($type['schema'] ?? null);
            $schema['fields'] = is_array($schema['fields'] ?? null) ? $schema['fields'] : [];
            $name = (string)$field['system_name'];
            if (!isset($schema['fields'][$name])) {
                throw new \DomainException("Field '{$name}' is not attached.");
            }

            $oldLocal = is_array($schema['fields'][$name]) ? $schema['fields'][$name] : [];
            $normalized = self::normalizeLocalFieldDefinition($local);
            $oldMultiple = !empty($oldLocal['settings']['multiple']);
            $newMultiple = !empty($normalized['settings']['multiple']);
            if (
                $oldMultiple
                && !$newMultiple
                && self::fieldHasArrayValues((int)$type['ct_id'], $name)
            ) {
                throw new \DomainException(
                    "Field '{$name}' contains multiple values; changing it to single requires a data migration."
                );
            }

            $schema['fields'][$name] = $normalized;
            self::storeTypeSchema($type, $schema);
        });
    }

    public static function detachField(int $contentTypeId, int $fieldId): void
    {
        self::transaction(function () use ($contentTypeId, $fieldId): void {
            $type = self::contentTypeForUpdate($contentTypeId);
            $name = \DB::getOne('select system_name from fields where field_id=$1', [$fieldId]);
            if (!is_string($name) || $name === '') {
                throw new \OutOfBoundsException("Unknown field: {$fieldId}");
            }

            $schema = self::decodeObject($type['schema'] ?? null);
            $schema['fields'] = is_array($schema['fields'] ?? null) ? $schema['fields'] : [];
            if (!array_key_exists($name, $schema['fields'])) {
                throw new \DomainException("Field '{$name}' is not attached.");
            }

            self::purgeFieldValues($fieldId, $name, $contentTypeId);

            unset($schema['fields'][$name]);
            if (($schema['title_field'] ?? null) === $name) unset($schema['title_field']);
            if (($schema['summary_field'] ?? null) === $name) unset($schema['summary_field']);
            self::normalizeFieldOrder($schema['fields']);
            self::storeTypeSchema($type, $schema);
            self::removeFieldFromContentTypeTranslations((string)$type['uuid'], $name);
        });
    }

    public static function moveField(int $contentTypeId, int $fieldId, string $direction): void
    {
        if (!in_array($direction, ['up', 'down'], true)) {
            throw new \InvalidArgumentException('Direction must be up or down.');
        }

        self::transaction(function () use ($contentTypeId, $fieldId, $direction): void {
            $type = self::contentTypeForUpdate($contentTypeId);
            $name = \DB::getOne('select system_name from fields where field_id=$1', [$fieldId]);
            if (!is_string($name) || $name === '') {
                throw new \OutOfBoundsException("Unknown field: {$fieldId}");
            }

            $schema = self::decodeObject($type['schema'] ?? null);
            $fields = is_array($schema['fields'] ?? null) ? $schema['fields'] : [];
            uasort($fields, static fn(array $a, array $b): int =>
                ((int)($a['displayorder'] ?? 0)) <=> ((int)($b['displayorder'] ?? 0))
            );
            $names = array_keys($fields);
            $index = array_search($name, $names, true);
            if ($index === false) {
                throw new \DomainException("Field '{$name}' is not attached.");
            }

            $other = $direction === 'up' ? $index - 1 : $index + 1;
            if (isset($names[$other])) {
                [$names[$index], $names[$other]] = [$names[$other], $names[$index]];
            }

            $ordered = [];
            foreach ($names as $position => $fieldName) {
                $fields[$fieldName]['displayorder'] = $position + 1;
                $ordered[$fieldName] = $fields[$fieldName];
            }
            $schema['fields'] = $ordered;
            self::storeTypeSchema($type, $schema);
        });
    }

    /**
     * Remove every stored value for one field, optionally limited to one content type.
     *
     * This is used by structural mutations so detached fields cannot leave searchable
     * orphan values behind. A global purge is safe only after the caller has verified
     * that the field is no longer attached to any content type.
     */
    private static function purgeFieldValues(
        int $fieldId,
        string $fieldName,
        ?int $contentTypeId = null
    ): void {
        $scopeSql = $contentTypeId !== null ? ' and i.ct_id=$3' : '';
        $scopeParams = $contentTypeId !== null
            ? [$fieldName, $fieldId, $contentTypeId]
            : [$fieldName, $fieldId];

        $affected = [];
        $rows = \DB::query(
            "select distinct i.item_id, i.item_uuid
"
            . "from content_items i
"
            . "where (
"
            . "    coalesce(i.common_data, '{}'::jsonb) ? $1
"
            . "    or exists (
"
            . "        select 1 from translations t
"
            . "        where t.entity_uuid=i.item_uuid
"
            . "          and coalesce(t.translated_data, '{}'::jsonb) ? $1
"
            . "    )
"
            . "    or exists (select 1 from item_texts v where v.item_id=i.item_id and v.field_id=$2)
"
            . "    or exists (select 1 from item_nums v where v.item_id=i.item_id and v.field_id=$2)
"
            . "    or exists (select 1 from item_bools v where v.item_id=i.item_id and v.field_id=$2)
"
            . "    or exists (select 1 from item_dates v where v.item_id=i.item_id and v.field_id=$2)
"
            . "){$scopeSql}",
            $scopeParams
        );
        while ($row = \DB::fetchRow($rows)) {
            $affected[(int)$row['item_id']] = (string)$row['item_uuid'];
        }

        foreach (self::INDEX_TABLES as $table) {
            if ($contentTypeId === null) {
                \DB::delete($table, 'field_id=$1', [$fieldId]);
                continue;
            }
            \DB::query(
                "delete from {$table} v using content_items i "
                . 'where v.item_id=i.item_id and v.field_id=$1 and i.ct_id=$2',
                [$fieldId, $contentTypeId]
            );
        }

        $itemWhere = "coalesce(common_data, '{}'::jsonb) ? $1";
        $itemParams = [$fieldName];
        if ($contentTypeId !== null) {
            $itemWhere .= ' and ct_id=$2';
            $itemParams[] = $contentTypeId;
        }
        \DB::query(
            "update content_items set common_data=coalesce(common_data, '{}'::jsonb) - $1 "
            . "where {$itemWhere}",
            $itemParams
        );

        $translationWhere = "coalesce(t.translated_data, '{}'::jsonb) ? $1";
        $translationParams = [$fieldName];
        if ($contentTypeId !== null) {
            $translationWhere .= ' and i.ct_id=$2';
            $translationParams[] = $contentTypeId;
        }
        \DB::query(
            "update translations t "
            . "set translated_data=coalesce(t.translated_data, '{}'::jsonb) - $1 "
            . 'from content_items i '
            . 'where t.entity_uuid=i.item_uuid and ' . $translationWhere,
            $translationParams
        );

        self::invalidateItemCaches($affected);
    }

    private static function invalidateItemCaches(array $items): void
    {
        if ($items === []) return;

        $ids = array_map('intval', array_keys($items));
        $languages = [];
        $rows = \DB::query(
            "select i.item_id, t.lang_code "
            . 'from content_items i '
            . 'left join translations t on t.entity_uuid=i.item_uuid '
            . 'where i.item_id=ANY($1::bigint[])',
            ['{' . implode(',', $ids) . '}']
        );
        while ($row = \DB::fetchRow($rows)) {
            if ($row['lang_code'] !== null && $row['lang_code'] !== '') {
                $languages[(int)$row['item_id']][(string)$row['lang_code']] = true;
            }
        }

        $fallbackLanguages = [];
        if (defined('LANG')) $fallbackLanguages[] = LANG;
        if (defined('DOMAIN_CONFIG') && !empty(DOMAIN_CONFIG['default_language'])) {
            $fallbackLanguages[] = (string)DOMAIN_CONFIG['default_language'];
        }

        foreach ($items as $itemId => $uuid) {
            $itemLanguages = array_unique(array_merge(
                array_keys($languages[$itemId] ?? []),
                $fallbackLanguages
            ));
            foreach ($itemLanguages as $language) {
                \Cache::del("globals:content_items:{$itemId}_{$language}");
                \Cache::del("globals:{$uuid}_{$language}");
            }
        }

        Content::$known_items = [];
    }

    public static function fieldHasValues(int $fieldId, string $fieldName): bool
    {
        foreach (self::INDEX_TABLES as $table) {
            if (\DB::getOne("select 1 from {$table} where field_id=$1 limit 1", [$fieldId])) {
                return true;
            }
        }
        if (\DB::getOne(
            "select 1 from content_items where coalesce(common_data, '{}'::jsonb) ? $1 limit 1",
            [$fieldName]
        )) {
            return true;
        }
        return (bool)\DB::getOne(
            "select 1 from translations t "
            . 'join content_items i on i.item_uuid=t.entity_uuid '
            . "where coalesce(t.translated_data, '{}'::jsonb) ? $1 limit 1",
            [$fieldName]
        );
    }

    private static function validateContentTypeParent(int $typeId, ?int $parentId): void
    {
        if ($parentId === null) return;
        if ($parentId === $typeId) {
            throw new \DomainException('Content type cannot be its own parent.');
        }
        if (!\DB::getOne('select 1 from content_types where ct_id=$1', [$parentId])) {
            throw new \InvalidArgumentException("Unknown parent content type: {$parentId}");
        }
        if ($typeId > 0 && \DB::getOne(
            'with recursive descendants as ('
            . ' select ct_id from content_types where parent_id=$1'
            . ' union all'
            . ' select child.ct_id from content_types child'
            . ' join descendants parent on child.parent_id=parent.ct_id'
            . ') select 1 from descendants where ct_id=$2 limit 1',
            [$typeId, $parentId]
        )) {
            throw new \DomainException('Content type parent would create a cycle.');
        }
    }

    private static function validateFieldTypeParent(int $typeId, ?int $parentId): void
    {
        if ($parentId === null) return;
        if ($parentId === $typeId) {
            throw new \DomainException('Field type cannot be its own parent.');
        }
        if (!\DB::getOne('select 1 from field_types where type_id=$1', [$parentId])) {
            throw new \InvalidArgumentException("Unknown parent field type: {$parentId}");
        }
        if ($typeId > 0 && \DB::getOne(
            'with recursive descendants as ('
            . ' select type_id from field_types where parent_id=$1'
            . ' union all'
            . ' select child.type_id from field_types child'
            . ' join descendants parent on child.parent_id=parent.type_id'
            . ') select 1 from descendants where type_id=$2 limit 1',
            [$typeId, $parentId]
        )) {
            throw new \DomainException('Field type parent would create a cycle.');
        }
    }

    private static function validateSchemaReferences(array $schema): void
    {
        $fields = is_array($schema['fields'] ?? null) ? $schema['fields'] : [];
        foreach ($fields as $fieldName => $definition) {
            if (!is_string($fieldName) || !is_array($definition)) {
                throw new \DomainException('Content type fields must be named objects.');
            }
            if (!\DB::getOne('select 1 from fields where system_name=$1', [$fieldName])) {
                throw new \DomainException("Unknown field attached to content type: {$fieldName}");
            }
            self::normalizeLocalFieldDefinition($definition);
        }
        foreach (['title_field', 'summary_field'] as $key) {
            $name = $schema[$key] ?? null;
            if ($name !== null && $name !== '' && !array_key_exists((string)$name, $fields)) {
                throw new \DomainException("{$key} must reference a field attached to the content type.");
            }
        }
    }

    private static function normalizeGlobalFieldSettings(array $settings): array
    {
        $normalized = [];
        foreach (self::GLOBAL_FIELD_SETTINGS as $name) {
            if (array_key_exists($name, $settings)) {
                $normalized[$name] = (bool)$settings[$name];
            }
        }
        if (is_array($settings['params'] ?? null) && $settings['params'] !== []) {
            $normalized['params'] = $settings['params'];
        }

        $known = array_fill_keys([...self::GLOBAL_FIELD_SETTINGS, 'params'], true);
        foreach ($settings as $name => $_) {
            if (!isset($known[$name])) {
                throw new \DomainException(
                    "Field setting '{$name}' is local to a content type or unsupported globally."
                );
            }
        }
        return $normalized;
    }

    private static function normalizeLocalFieldDefinition(array $definition): array
    {
        foreach (['type', 'params', 'options'] as $name) {
            if (array_key_exists($name, $definition)) {
                throw new \DomainException(
                    "Field attachment '{$name}' belongs to the global field definition."
                );
            }
        }

        $settings = is_array($definition['settings'] ?? null)
            ? $definition['settings']
            : [];
        foreach (['indexed', 'unique', 'translatable'] as $name) {
            if (array_key_exists($name, $settings)) {
                throw new \DomainException(
                    "Field attachment setting '{$name}' belongs to the global field definition."
                );
            }
        }
        $localSettings = [];
        foreach (self::LOCAL_FIELD_SETTINGS as $name) {
            if (array_key_exists($name, $settings)) {
                $localSettings[$name] = (bool)$settings[$name];
            }
        }
        foreach ($settings as $name => $_) {
            if (!in_array($name, self::LOCAL_FIELD_SETTINGS, true)) {
                throw new \DomainException("Unsupported local field setting: {$name}");
            }
        }

        if ($localSettings === []) {
            unset($definition['settings']);
        } else {
            $definition['settings'] = $localSettings;
        }
        if (isset($definition['search_weight'])) {
            $weight = strtoupper(trim((string)$definition['search_weight']));
            if ($weight === '') {
                unset($definition['search_weight']);
            } elseif (!in_array($weight, ['A', 'B', 'C', 'D'], true)) {
                throw new \DomainException('Search weight must be A, B, C or D.');
            } else {
                $definition['search_weight'] = $weight;
            }
        }
        return $definition;
    }

    private static function normalizeCompoundComponents(mixed $components): array
    {
        if (!is_array($components) || $components === []) {
            throw new \DomainException('Compound field requires at least one component.');
        }

        $normalized = [];
        $displayorder = 0;
        foreach ($components as $name => $definition) {
            if (!is_string($name) || !preg_match(self::SYSTEM_NAME_PATTERN, $name)) {
                throw new \DomainException('Compound component names must use lowercase Latin letters, digits and underscores.');
            }
            if (!is_array($definition)) {
                throw new \DomainException("Invalid compound component: {$name}");
            }

            $typeName = trim((string)($definition['type'] ?? ''));
            if ($typeName === '') {
                throw new \DomainException("Compound component '{$name}' requires a field type.");
            }

            try {
                $fieldType = Content::getFieldType($typeName);
            } catch (\Throwable) {
                throw new \DomainException("Unknown compound component type: {$typeName}");
            }

            if (empty($fieldType['type_settings']['compound_component'])) {
                throw new \DomainException(
                    "Field type '{$typeName}' cannot be used inside a compound field."
                );
            }
            if ((string)($fieldType['root_type_name'] ?? '') === 'compound') {
                throw new \DomainException('Nested compound fields are not supported.');
            }

            $translatable = !empty($definition['translatable']);
            if ($translatable && (string)($fieldType['root_type_name'] ?? '') !== 'text') {
                throw new \DomainException(
                    "Compound component '{$name}' can be translatable only when it uses a text-based type."
                );
            }

            $component = [
                'type' => $typeName,
                'translatable' => $translatable,
                'required' => !empty($definition['required']),
                'displayorder' => ++$displayorder,
            ];

            $params = self::normalizeCompoundComponentParams(
                $fieldType,
                is_array($definition['params'] ?? null) ? $definition['params'] : []
            );
            if ($params !== []) {
                $component['params'] = $params;
            }

            $normalized[$name] = $component;
        }

        return $normalized;
    }

    private static function normalizeCompoundComponentParams(array $fieldType, array $params): array
    {
        $definitions = is_array($fieldType['type_settings']['parameters'] ?? null)
            ? $fieldType['type_settings']['parameters']
            : [];

        foreach (array_keys($params) as $name) {
            if (!is_string($name) || !array_key_exists($name, $definitions)) {
                throw new \DomainException(
                    "Unsupported parameter '{$name}' for compound component type '{$fieldType['system_name']}'."
                );
            }
        }

        $normalized = [];
        foreach ($definitions as $name => $definition) {
            if (!is_string($name) || !is_array($definition)) {
                continue;
            }
            if (!array_key_exists($name, $params)) {
                if (!empty($definition['required'])) {
                    throw new \DomainException(
                        "Compound component parameter '{$name}' is required."
                    );
                }
                continue;
            }

            $value = $params[$name];
            if (!empty($definition['multiple'])) {
                $value = is_array($value) ? array_values($value) : [$value];
                $value = array_values(array_filter(
                    $value,
                    static fn(mixed $item): bool => is_scalar($item) && trim((string)$item) !== ''
                ));
            } elseif (($definition['format'] ?? null) === 'json') {
                if (!is_array($value)) {
                    throw new \DomainException(
                        "Compound component parameter '{$name}' must be structured data."
                    );
                }
            } elseif (is_scalar($value)) {
                $value = trim((string)$value);
            } elseif ($value !== null) {
                throw new \DomainException(
                    "Invalid compound component parameter '{$name}'."
                );
            }

            if ($value === null || $value === '' || $value === []) {
                if (!empty($definition['required'])) {
                    throw new \DomainException(
                        "Compound component parameter '{$name}' is required."
                    );
                }
                continue;
            }

            if (($definition['type'] ?? null) === 'field_id') {
                foreach ((array)$value as $fieldName) {
                    $column = ($definition['value'] ?? null) === 'system_name'
                        ? 'system_name'
                        : 'field_id';
                    if (!\DB::getOne("select 1 from fields where {$column}=$1", [$fieldName])) {
                        throw new \DomainException(
                            "Unknown field '{$fieldName}' in compound component parameter '{$name}'."
                        );
                    }
                }
            }

            $normalized[$name] = $value;
        }

        if ((string)($fieldType['system_name'] ?? '') === 'autocomplete') {
            $sources = $normalized['source_fields'] ?? [];
            if (!is_array($sources) || $sources === []) {
                throw new \DomainException(
                    'Autocomplete compound components require at least one source field.'
                );
            }
            foreach ($sources as $source) {
                $field = Content::getField((string)$source);
                $settings = array_replace(
                    is_array($field['type_settings'] ?? null) ? $field['type_settings'] : [],
                    is_array($field['field_settings'] ?? null) ? $field['field_settings'] : []
                );
                if (empty($settings['indexed']) || (string)($field['root_type_name'] ?? '') !== 'text') {
                    throw new \DomainException(
                        'Autocomplete source fields must be indexed text fields.'
                    );
                }
            }
        }

        return $normalized;
    }

    private static function assertCompoundDefinitionChangeAllowed(
        int $fieldId,
        string $fieldName,
        array $oldSettings,
        array $newSettings
    ): void {
        if (!self::fieldHasValues($fieldId, $fieldName)) {
            return;
        }

        $oldParams = is_array($oldSettings['params'] ?? null)
            ? $oldSettings['params']
            : [];
        $newParams = is_array($newSettings['params'] ?? null)
            ? $newSettings['params']
            : [];
        $oldComponents = is_array($oldParams['components'] ?? null)
            ? $oldParams['components']
            : [];
        $newComponents = is_array($newParams['components'] ?? null)
            ? $newParams['components']
            : [];

        foreach ($oldComponents as $name => $oldComponent) {
            if (!is_string($name) || !is_array($oldComponent)) {
                continue;
            }
            if (!isset($newComponents[$name]) || !is_array($newComponents[$name])) {
                throw new \DomainException(
                    "Compound component '{$name}' cannot be removed while field '{$fieldName}' contains values."
                );
            }

            $newComponent = $newComponents[$name];
            if ((string)($oldComponent['type'] ?? '') !== (string)($newComponent['type'] ?? '')) {
                throw new \DomainException(
                    "Compound component '{$name}' cannot change type while field '{$fieldName}' contains values."
                );
            }
            if (!empty($oldComponent['translatable']) !== !empty($newComponent['translatable'])) {
                throw new \DomainException(
                    "Compound component '{$name}' cannot change translatable mode while field '{$fieldName}' contains values."
                );
            }
            if (empty($oldComponent['required']) && !empty($newComponent['required'])) {
                throw new \DomainException(
                    "Compound component '{$name}' cannot become required while field '{$fieldName}' contains values."
                );
            }
        }

        foreach ($newComponents as $name => $newComponent) {
            if (!is_string($name) || !is_array($newComponent) || isset($oldComponents[$name])) {
                continue;
            }
            if (!empty($newComponent['required'])) {
                throw new \DomainException(
                    "New compound component '{$name}' must be optional while field '{$fieldName}' contains values."
                );
            }
        }
    }

    private static function syncFieldIndexSemantics(
        int $fieldId,
        string $fieldName,
        array $oldSettings,
        array $newSettings
    ): void {
        $oldIndexed = !empty($oldSettings['indexed']);
        $newIndexed = !empty($newSettings['indexed']);
        $uniqueEnabled = empty($oldSettings['unique']) && !empty($newSettings['unique']);

        if (!$newIndexed && $oldIndexed) {
            foreach (self::INDEX_TABLES as $table) {
                \DB::delete($table, 'field_id=$1', [$fieldId]);
            }
            return;
        }

        if ((!$oldIndexed && $newIndexed) || $uniqueEnabled) {
            // Content::reindex() must see the field definition just written above.
            self::invalidateField($fieldId);
            $items = \DB::query(
                "select i.item_id from content_items i join content_types ct using(ct_id) "
                . "where coalesce(ct.schema->'fields', '{}'::jsonb) ? $1 order by i.item_id",
                [$fieldName]
            );
            while ($item = \DB::fetchRow($items)) {
                Content::reindex((int)$item['item_id']);
            }
        }
    }

    private static function fieldHasArrayValues(int $contentTypeId, string $fieldName): bool
    {
        if (\DB::getOne(
            "select 1 from content_items where ct_id=$1 "
            . "and jsonb_typeof(common_data->$2)='array' limit 1",
            [$contentTypeId, $fieldName]
        )) {
            return true;
        }

        return (bool)\DB::getOne(
            "select 1 from content_items i join translations t on t.entity_uuid=i.item_uuid "
            . "where i.ct_id=$1 and jsonb_typeof(t.translated_data->$2)='array' limit 1",
            [$contentTypeId, $fieldName]
        );
    }

    private static function fieldTypeIsUsed(int $typeId): bool
    {
        if (\DB::getOne('select 1 from fields where type_id=$1 limit 1', [$typeId])) {
            return true;
        }
        return (bool)\DB::getOne(
            'with recursive descendants as ('
            . ' select type_id from field_types where parent_id=$1'
            . ' union all'
            . ' select child.type_id from field_types child'
            . ' join descendants parent on child.parent_id=parent.type_id'
            . ') select 1 from fields where type_id in (select type_id from descendants) limit 1',
            [$typeId]
        );
    }

    private static function renameFieldInSchemas(string $oldName, string $newName): void
    {
        $result = \DB::query(
            "select * from content_types where coalesce(schema->'fields', '{}'::jsonb) ? $1 for update",
            [$oldName]
        );
        while ($type = \DB::fetchRow($result)) {
            $schema = self::decodeObject($type['schema'] ?? null);
            $fields = is_array($schema['fields'] ?? null) ? $schema['fields'] : [];
            if (!array_key_exists($oldName, $fields)) continue;

            $renamed = [];
            foreach ($fields as $name => $config) {
                $renamed[$name === $oldName ? $newName : $name] = $config;
            }
            $schema['fields'] = $renamed;
            if (($schema['title_field'] ?? null) === $oldName) $schema['title_field'] = $newName;
            if (($schema['summary_field'] ?? null) === $oldName) $schema['summary_field'] = $newName;
            self::storeTypeSchema($type, $schema);
            self::renameFieldInContentTypeTranslations(
                (string)$type['uuid'],
                $oldName,
                $newName
            );
        }
    }

    private static function renameFieldInContentTypeTranslations(
        string $contentTypeUuid,
        string $oldName,
        string $newName
    ): void {
        $rows = \DB::query(
            'select translation_id, lang_code, translated_data from translations '
            . 'where entity_uuid=$1 for update',
            [$contentTypeUuid]
        );
        while ($row = \DB::fetchRow($rows)) {
            $data = self::decodeObject($row['translated_data'] ?? null);
            $fields = is_array($data['schema']['fields'] ?? null)
                ? $data['schema']['fields']
                : [];
            if (!array_key_exists($oldName, $fields)) continue;

            $renamed = [];
            foreach ($fields as $name => $metadata) {
                $renamed[$name === $oldName ? $newName : $name] = $metadata;
            }
            $data['schema']['fields'] = $renamed;
            \DB::update(
                'translations',
                ['translated_data' => self::json($data)],
                'translation_id=$1',
                [(int)$row['translation_id']]
            );
            \Cache::del("globals:{$contentTypeUuid}_{$row['lang_code']}");
        }
    }

    private static function removeFieldFromContentTypeTranslations(
        string $contentTypeUuid,
        string $fieldName
    ): void {
        $rows = \DB::query(
            'select translation_id, lang_code, translated_data from translations '
            . 'where entity_uuid=$1 for update',
            [$contentTypeUuid]
        );
        while ($row = \DB::fetchRow($rows)) {
            $data = self::decodeObject($row['translated_data'] ?? null);
            $fields = is_array($data['schema']['fields'] ?? null)
                ? $data['schema']['fields']
                : [];
            if (!array_key_exists($fieldName, $fields)) continue;

            unset($fields[$fieldName]);
            if ($fields === []) unset($data['schema']['fields']);
            else $data['schema']['fields'] = $fields;
            if (isset($data['schema']) && $data['schema'] === []) unset($data['schema']);

            \DB::update(
                'translations',
                ['translated_data' => self::json($data)],
                'translation_id=$1',
                [(int)$row['translation_id']]
            );
            \Cache::del("globals:{$contentTypeUuid}_{$row['lang_code']}");
        }
    }

    /** @return array<string, mixed> */
    private static function contentTypeForUpdate(int $id): array
    {
        $type = \DB::getRow('select * from content_types where ct_id=$1 for update', [$id]);
        if (!$type) {
            throw new \OutOfBoundsException("Unknown content type: {$id}");
        }
        return $type;
    }

    private static function storeTypeSchema(array $type, array $schema): void
    {
        $searchWeightFields = self::changedSearchWeightFields(
            self::decodeObject($type['schema'] ?? null),
            $schema
        );
        self::assertWrite(
            \DB::update('content_types', ['schema' => self::json($schema)], 'ct_id=$1', [(int)$type['ct_id']]),
            'Failed to update content type schema.'
        );
        self::refreshFieldSearchVectors((int)$type['ct_id'], $searchWeightFields);
        self::invalidateContentType($type);
    }

    private static function changedSearchWeightFields(array $oldSchema, array $newSchema): array
    {
        $oldFields = is_array($oldSchema['fields'] ?? null) ? $oldSchema['fields'] : [];
        $newFields = is_array($newSchema['fields'] ?? null) ? $newSchema['fields'] : [];
        $names = array_unique([...array_keys($oldFields), ...array_keys($newFields)]);
        $ids = [];

        foreach ($names as $name) {
            if (!is_string($name) || $name === '') continue;
            $oldWeight = strtoupper(trim((string)($oldFields[$name]['search_weight'] ?? 'D')));
            $newWeight = strtoupper(trim((string)($newFields[$name]['search_weight'] ?? 'D')));
            $oldWeight = in_array($oldWeight, ['A', 'B', 'C', 'D'], true) ? $oldWeight : 'D';
            $newWeight = in_array($newWeight, ['A', 'B', 'C', 'D'], true) ? $newWeight : 'D';
            if ($oldWeight === $newWeight) continue;

            $fieldId = \DB::getOne('select field_id from fields where system_name=$1', [$name]);
            if ($fieldId) $ids[] = (int)$fieldId;
        }

        return array_values(array_unique($ids));
    }

    private static function refreshFieldSearchVectors(int $contentTypeId, array $fieldIds): void
    {
        foreach ($fieldIds as $fieldId) {
            \DB::query(
                'UPDATE item_texts value '
                . 'SET value=value.value '
                . 'FROM content_items item '
                . 'WHERE value.item_id=item.item_id '
                . 'AND item.ct_id=$1 AND value.field_id=$2',
                [$contentTypeId, $fieldId]
            );
        }
    }

    private static function nextFieldOrder(array $fields): int
    {
        $max = 0;
        foreach ($fields as $field) {
            if (is_array($field)) $max = max($max, (int)($field['displayorder'] ?? 0));
        }
        return $max + 1;
    }

    private static function normalizeFieldOrder(array &$fields): void
    {
        uasort($fields, static fn(array $a, array $b): int =>
            ((int)($a['displayorder'] ?? 0)) <=> ((int)($b['displayorder'] ?? 0))
        );
        $ordered = [];
        foreach ($fields as $name => $field) {
            $field['displayorder'] = count($ordered) + 1;
            $ordered[$name] = $field;
        }
        $fields = $ordered;
    }

    private static function invalidateContentType(array $type, ?string $oldSystemName = null): void
    {
        \Cache::del('globals:content_type_map');
        foreach (self::languages() as $language) {
            if (isset($type['ct_id'])) {
                \Cache::del("globals:content_types:{$type['ct_id']}_{$language}");
            }
            if (!empty($type['system_name'])) {
                \Cache::del("globals:content_types:{$type['system_name']}_{$language}");
            }
            if ($oldSystemName !== null && $oldSystemName !== '') {
                \Cache::del("globals:content_types:{$oldSystemName}_{$language}");
            }
            if (!empty($type['uuid'])) {
                \Cache::del("globals:{$type['uuid']}_{$language}");
            }
        }
        Content::resetStructureCache();
    }

    private static function invalidateField(int $fieldId): void
    {
        \Cache::del('globals:field_map');
        \Cache::del("globals:fields:f_{$fieldId}");

        $fieldName = \DB::getOne('select system_name from fields where field_id=$1', [$fieldId]);
        if (is_string($fieldName) && $fieldName !== '') {
            $types = \DB::query(
                "select ct_id, uuid, system_name from content_types "
                . "where coalesce(schema->'fields', '{}'::jsonb) ? $1",
                [$fieldName]
            );
            while ($type = \DB::fetchRow($types)) {
                self::invalidateContentType($type);
            }
        }

        Content::resetStructureCache();
    }

    private static function invalidateFieldType(): void
    {
        Content::resetStructureCache();
    }

    /** @return list<string> */
    private static function languages(): array
    {
        $languages = [];
        if (defined('DOMAIN_CONFIG') && is_array(DOMAIN_CONFIG['languages'] ?? null)) {
            $languages = DOMAIN_CONFIG['languages'];
        }
        foreach (\DB::getArr(
            "select distinct lang_code from translations where lang_code is not null and lang_code<>$1",
            ['']
        ) as $language) {
            if (is_string($language) && $language !== '') $languages[] = $language;
        }
        return array_values(array_unique($languages));
    }

    private static function assertSystemName(string $name, string $entity): void
    {
        if (!preg_match(self::SYSTEM_NAME_PATTERN, $name)) {
            throw new \InvalidArgumentException(
                ucfirst($entity) . ' system name must use lowercase Latin letters, digits and underscores.'
            );
        }
    }

    private static function nullablePositiveInt(mixed $value): ?int
    {
        if ($value === null || $value === '' || (int)$value === 0) return null;
        $value = (int)$value;
        return $value > 0 ? $value : null;
    }

    /** @return array<string, mixed> */
    private static function decodeObject(mixed $value): array
    {
        if (is_array($value)) return $value;
        if ($value === null || $value === '') return [];
        $decoded = json_decode((string)$value, true);
        if (!is_array($decoded)) {
            throw new \InvalidArgumentException('Invalid JSON object.');
        }
        return $decoded;
    }

    private static function json(array $data): string
    {
        $json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if (!is_string($json)) {
            throw new \RuntimeException('Failed to encode structure data.');
        }
        return $json;
    }

    private static function assertWrite(mixed $result, string $message): void
    {
        if ($result === false) throw new \RuntimeException($message);
    }

    private static function transaction(callable $operation): mixed
    {
        if (!\DB::beginTransaction()) {
            throw new \RuntimeException('Failed to start structure transaction.');
        }
        try {
            $result = $operation();
            if (!\DB::commit()) {
                throw new \RuntimeException('Failed to commit structure transaction.');
            }
            return $result;
        } catch (\Throwable $error) {
            \DB::rollBack();
            throw $error;
        }
    }
}
