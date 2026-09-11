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

use Core\Utils\JsonTool;

if(!defined('IN_KAMI')) die();

final class ExtensionManager
{
    public static function installPlugin(string $folder): bool
    {
        $manifest = self::loadPluginManifest($folder);
        $systemName = self::pluginSystemName($manifest);

        if (\DB::getOne(
            'select plugin_id from plugins where system_name=$1',
            [$systemName]
        )) {
            trigger_error(
                "Plugin {$systemName} is already installed.",
                E_USER_WARNING
            );
            return false;
        }

        self::assertPluginDependencies($manifest);
        EndpointRegistry::validateDeclarations(
            $systemName,
            $manifest['endpoints'] ?? null
        );

        return self::storePlugin($manifest, null, $folder);
    }

    public static function updatePlugin(string $folder): bool
    {
        $manifest = self::loadPluginManifest($folder);
        $systemName = self::pluginSystemName($manifest);
        $plugin = \DB::getRow(
            'select plugin_id, uuid, settings, plugin_prefix
            from plugins where system_name=$1',
            [$systemName]
        );

        if (!$plugin) {
            trigger_error(
                "Plugin {$systemName} is not installed.",
                E_USER_WARNING
            );
            return false;
        }

        self::assertPluginDependencies($manifest);
        EndpointRegistry::validateDeclarations(
            $systemName,
            $manifest['endpoints'] ?? null
        );

        return self::storePlugin($manifest, $plugin, $folder);
    }

    public static function uninstallPlugin(string $systemName): bool
    {
        $systemName = trim($systemName);
        if ($systemName === '') {
            throw new \InvalidArgumentException('Plugin system name is required.');
        }

        $plugin = \DB::getRow(
            'select plugin_id, uuid, system_name, is_active
            from plugins
            where system_name=$1',
            [$systemName]
        );

        if (!$plugin) {
            trigger_error(
                "Plugin {$systemName} is not installed.",
                E_USER_WARNING
            );
            return false;
        }

        $pluginId = (int)$plugin['plugin_id'];
        if (!empty($plugin['is_active']) || \DB::getOne(
            'select 1 from plugin_domains where plugin_id=$1 limit 1',
            [$pluginId]
        )) {
            throw new \RuntimeException(
                "Plugin {$systemName} must be inactive on all domains before uninstall."
            );
        }

        return self::transaction(function () use ($plugin, $pluginId): void {
            $pluginUuid = (string)$plugin['uuid'];

            $languages = \DB::getArr(
                'select lang_code from translations where entity_uuid=$1',
                [$pluginUuid]
            );

            // Keep plugin-created content and structures intact. Full destructive
            // cleanup is handled separately because plugin-owned tables and
            // content types require an explicit uninstall policy.
            self::assertWrite(
                \DB::update(
                    'content_items',
                    ['plugin_id' => null],
                    'plugin_id=$1',
                    [$pluginId]
                ),
                'Failed to detach plugin-owned content items.'
            );
            self::assertWrite(
                \DB::update(
                    'content_types',
                    ['plugin_id' => null],
                    'plugin_id=$1',
                    [$pluginId]
                ),
                'Failed to detach plugin-owned content types.'
            );
            self::assertWrite(
                \DB::delete('translations', 'entity_uuid=$1', [$pluginUuid]),
                'Failed to delete plugin translations.'
            );
            self::assertWrite(
                \DB::delete('plugins', 'plugin_id=$1', [$pluginId]),
                'Failed to delete plugin record.'
            );

            foreach ($languages as $language) {
                \Cache::del("globals:{$pluginUuid}_{$language}");
            }
        });
    }

    public static function installTheme(string $folder): bool
    {
        $manifest = self::loadThemeManifest($folder);
        $systemName = self::themeSystemName($manifest);

        if (\DB::getOne(
            'select theme_id from themes where system_name=$1',
            [$systemName]
        )) {
            trigger_error(
                "Theme {$systemName} is already installed.",
                E_USER_WARNING
            );
            return false;
        }

        return self::storeTheme($manifest);
    }

    public static function updateTheme(string $folder): bool
    {
        $manifest = self::loadThemeManifest($folder);
        $systemName = self::themeSystemName($manifest);
        $theme = \DB::getRow(
            'select theme_id, uuid from themes where system_name=$1',
            [$systemName]
        );

        if (!$theme) {
            trigger_error(
                "Theme {$systemName} is not installed.",
                E_USER_WARNING
            );
            return false;
        }

        return self::storeTheme($manifest, $theme);
    }

    public static function uninstallTheme(string $systemName): bool
    {
        $systemName = trim($systemName);
        if ($systemName === '') {
            throw new \InvalidArgumentException('Theme system name is required.');
        }

        $theme = \DB::getRow(
            'select theme_id, uuid, system_name
            from themes
            where system_name=$1',
            [$systemName]
        );

        if (!$theme) {
            trigger_error(
                "Theme {$systemName} is not installed.",
                E_USER_WARNING
            );
            return false;
        }

        return self::transaction(function () use ($theme): void {
            $themeId = (int) $theme['theme_id'];
            $themeUuid = (string) $theme['uuid'];
            $domains = \DB::getArr(
                'select distinct d.domain_name
                 from (
                     select theme_id, domain_id
                     from domains
                     union
                     select l.theme_id, p.domain_id
                     from pages p
                     join theme_layouts l on l.layout_id=p.layout_id
                 ) used
                 join domains d on d.domain_id=used.domain_id
                 where used.theme_id=$1
                 order by d.domain_name',
                [$themeId]
            );

            if ($domains !== []) {
                throw new \RuntimeException(
                    'Theme ' . $theme['system_name']
                    . ' is used by domain(s): ' . implode(', ', $domains) . '.'
                );
            }

            $translations = \DB::query(
                'select entity_uuid, lang_code
                 from translations
                 where entity_uuid=$1
                    or entity_uuid in (
                        select uuid
                        from theme_layouts
                        where theme_id=$2
                    )',
                [$themeUuid, $themeId]
            );
            while ($translation = \DB::fetchRow($translations)) {
                \Cache::del(
                    'globals:' . $translation['entity_uuid'] . '_' . $translation['lang_code']
                );
            }

            self::assertWrite(
                \DB::delete(
                    'translations',
                    'entity_uuid=$1 OR entity_uuid IN (
                        SELECT uuid FROM theme_layouts WHERE theme_id=$2
                    )',
                    [$themeUuid, $themeId]
                ),
                'Failed to delete theme translations.'
            );
            self::assertWrite(
                \DB::delete('theme_layouts', 'theme_id=$1', [$themeId]),
                'Failed to delete theme layouts.'
            );
            self::assertWrite(
                \DB::delete('themes', 'theme_id=$1', [$themeId]),
                'Failed to delete theme record.'
            );
        });
    }

    private static function storePlugin(
        array $manifest,
        ?array $existingPlugin = null,
        ?string $folder = null
    ): bool {
        return self::transaction(function () use ($manifest, $existingPlugin, $folder): void {
            $pluginData = self::pluginData(
                $manifest,
                $existingPlugin === null,
                self::decodeObject($existingPlugin['settings'] ?? null)
            );

            if ($existingPlugin === null) {
                $plugin = \DB::insert(
                    'plugins',
                    $pluginData,
                    'plugin_id, uuid'
                );

                if (!is_array($plugin)) {
                    throw new \RuntimeException('Failed to create plugin record.');
                }
            } else {
                self::assertWrite(
                    \DB::update(
                        'plugins',
                        $pluginData,
                        'plugin_id=$1',
                        [$existingPlugin['plugin_id']]
                    ),
                    'Failed to update plugin record.'
                );

                $plugin = $existingPlugin;
            }

            if ($folder !== null) {
                self::runPluginMigrations((int) $plugin['plugin_id'], $folder);
            }

            self::syncPluginEndpoints(
                (int) $plugin['plugin_id'],
                self::pluginSystemName($manifest),
                $manifest['endpoints'] ?? null
            );

            $languages = ExtensionLanguage::resolvePluginLanguages($manifest);

            self::syncPluginTranslations(
                (string) $plugin['uuid'],
                $languages
            );

            self::syncFieldTypes(
                is_array($manifest['field_types'] ?? null)
                    ? $manifest['field_types']
                    : [],
                $languages
            );

            self::syncStandaloneFields(
                is_array($manifest['fields'] ?? null)
                    ? $manifest['fields']
                    : [],
                $languages
            );

            self::syncContentTypes(
                (int) $plugin['plugin_id'],
                is_array($manifest['content_types'] ?? null)
                    ? $manifest['content_types']
                    : [],
                $languages
            );
        });
    }

    private static function pluginData(
        array $manifest,
        bool $includeUuid,
        ?array $currentSettings = null
    ): array {
        $declarations = is_array($manifest['settings'] ?? null)
            ? $manifest['settings']
            : [];
        $settings = [];

        foreach ($declarations as $name => $structure) {
            if (!is_string($name) || !is_array($structure)) {
                continue;
            }
            $settings[$name] = $structure['default'] ?? null;
        }

        if ($currentSettings !== null) {
            // Preserve values explicitly set by the site while adding defaults
            // for settings introduced by a newer package version.
            $settings = array_replace($settings, $currentSettings);
        }

        $info = $manifest['info'];

        $data = [
            'system_name' => self::pluginSystemName($manifest),
            'plugin_prefix' => $manifest['prefix'] ?? null,
            'settings' => self::json($settings),
            'config' => self::json(
                is_array($manifest['config'] ?? null)
                    ? $manifest['config']
                    : []
            ),
            'plugin_version' => $manifest['version'] ?? null,
            'plugin_author' => $info['author'] ?? null,
            'default_language' =>
                $manifest['config']['default_language'] ?? 'en',
        ];

        $uuid = $info['uuid'] ?? null;
        if ($includeUuid && is_string($uuid) && self::isUuid($uuid)) {
            $data['uuid'] = $uuid;
        }

        return $data;
    }

    private static function isGlobalSetting(array $structure): bool
    {
        return !empty($structure['is_global'] ?? $structure['global'] ?? false);
    }

    private static function syncPluginEndpoints(
        int $pluginId,
        string $pluginName,
        mixed $declarations
    ): void {
        $endpoints = EndpointRegistry::validateDeclarations(
            $pluginName,
            $declarations
        );

        self::assertWrite(
            \DB::delete('plugin_endpoints', 'plugin_id=$1', [$pluginId]),
            "Failed to clear plugin endpoints for {$pluginName}."
        );

        foreach ($endpoints as $endpoint => $method) {
            self::assertWrite(
                \DB::insert('plugin_endpoints', [
                    'plugin_id' => $pluginId,
                    'endpoint' => $endpoint,
                    'route_method' => $method,
                ]),
                "Failed to store plugin endpoint {$endpoint} for {$pluginName}."
            );
        }

        $domainIds = array_map(
            'intval',
            \DB::getArr(
                'select domain_id from plugin_domains where plugin_id=$1',
                [$pluginId]
            )
        );

        EndpointRegistry::assertAvailableForPlugin($pluginId, $domainIds);
        EndpointRegistry::invalidateDomains($domainIds);
    }

    private static function assertPluginDependencies(array $manifest): void
    {
        $pluginName = self::pluginSystemName($manifest);
        $dependencies = $manifest['dependencies'] ?? [];

        foreach ($dependencies as $dependency) {
            if (!is_string($dependency) || $dependency === '') {
                throw new \RuntimeException('Invalid plugin dependency name.');
            }

            $installed = \DB::getOne(
                'select 1 from plugins where system_name=$1',
                [$dependency]
            );

            if (!$installed) {
                throw new \RuntimeException(
                    "Cannot install or update "
                    . $pluginName
                    . ": missing plugin dependency {$dependency}."
                );
            }
        }

        foreach ($manifest['content_types'] ?? [] as $systemName => $contentType) {
            if (!is_array($contentType)) {
                continue;
            }

            $manager = $contentType['manager'] ?? null;
            if (
                $manager === null
                || $manager === $pluginName
            ) {
                continue;
            }
            if (
                !is_string($manager)
                || $manager === ''
                || !in_array($manager, $dependencies, true)
            ) {
                throw new \RuntimeException(
                    "Content type {$systemName} uses manager {$manager}, "
                    . "but {$pluginName} does not declare it as a dependency."
                );
            }
        }
    }

    private static function syncPluginTranslations(
        string $pluginUuid,
        array $languages
    ): void {
        foreach ($languages as $language => $data) {
            self::upsertTranslation(
                $pluginUuid,
                (string) $language,
                ExtensionLanguage::pluginTranslation($data)
            );
        }
    }

    private static function syncFieldTypes(
        array $fieldTypes,
        array $languages
    ): void {
        $pending = $fieldTypes;

        while ($pending !== []) {
            $progress = false;

            foreach ($pending as $systemName => $fieldTypeData) {
                if (!is_string($systemName) || !is_array($fieldTypeData)) {
                    throw new \RuntimeException('Invalid field type declaration.');
                }

                $parentName = $fieldTypeData['parent'] ?? null;
                if ($parentName !== null && (!is_string($parentName) || $parentName === '')) {
                    throw new \RuntimeException(
                        "Field type {$systemName} parent must be a system name or null."
                    );
                }

                $parentId = null;
                if (is_string($parentName) && $parentName !== '') {
                    $parentId = \DB::getOne(
                        'select type_id from field_types where system_name=$1',
                        [$parentName]
                    );
                    if (!$parentId) {
                        if (array_key_exists($parentName, $pending)) {
                            continue;
                        }
                        throw new \RuntimeException(
                            "Unknown parent field type {$parentName} for {$systemName}."
                        );
                    }
                    $parentId = (int)$parentId;
                }

                $existing = \DB::getRow(
                    'select type_id from field_types where system_name=$1',
                    [$systemName]
                );
                $fieldType = \Core\ContentStructure::saveFieldType(
                    $existing ? (int)$existing['type_id'] : null,
                    [
                        'system_name' => $systemName,
                        'parent_id' => $parentId,
                        'type_settings' => is_array($fieldTypeData['settings'] ?? null)
                            ? $fieldTypeData['settings']
                            : [],
                    ]
                );

                foreach ($languages as $language => $languageData) {
                    self::upsertTranslation(
                        (string)$fieldType['uuid'],
                        (string)$language,
                        ExtensionLanguage::fieldTypeTranslation(
                            $systemName,
                            $languageData,
                            $fieldTypeData
                        )
                    );
                }

                unset($pending[$systemName]);
                $progress = true;
            }

            if (!$progress) {
                throw new \RuntimeException(
                    'Cannot resolve field type inheritance order. Check for a parent cycle.'
                );
            }
        }
    }

    private static function syncStandaloneFields(
        array $fields,
        array $languages
    ): void {
        foreach ($fields as $systemName => $fieldData) {
            if (!is_string($systemName) || !is_array($fieldData)) {
                throw new \RuntimeException('Invalid standalone field declaration.');
            }

            $fieldType = \DB::getRow(
                'select type_id from field_types where system_name=$1',
                [$fieldData['type'] ?? null]
            );
            if (!$fieldType) {
                throw new \RuntimeException("Unknown field type for {$systemName}.");
            }

            $settings = is_array($fieldData['settings'] ?? null)
                ? $fieldData['settings']
                : [];
            $params = is_array($fieldData['params'] ?? null)
                ? $fieldData['params']
                : [];
            if (is_array($fieldData['options'] ?? null)) {
                $params['options'] = $fieldData['options'];
            }
            if ($params !== []) {
                $settings['params'] = $params;
            }

            $existing = \DB::getRow(
                'select field_id from fields where system_name=$1',
                [$systemName]
            );
            $field = \Core\ContentStructure::saveField(
                $existing ? (int)$existing['field_id'] : null,
                [
                    'type_id' => (int)$fieldType['type_id'],
                    'variant_id' => null,
                    'system_name' => $systemName,
                    'field_settings' => $settings,
                ]
            );

            foreach ($languages as $language => $languageData) {
                self::upsertTranslation(
                    (string)$field['uuid'],
                    (string)$language,
                    ExtensionLanguage::standaloneFieldTranslation(
                        $systemName,
                        $languageData,
                        $fieldData
                    )
                );
            }
        }
    }

    private static function syncContentTypes(
        int $pluginId,
        array $contentTypes,
        array $languages
    ): void {
        foreach ($contentTypes as $systemName => $contentTypeData) {
            if (!is_string($systemName) || !is_array($contentTypeData)) {
                throw new \RuntimeException('Invalid content type declaration.');
            }

            $existingType = \DB::getRow(
                'select ct_id, uuid, plugin_id, manager_plugin_id, manager_overridden '
                . 'from content_types where system_name=$1',
                [$systemName]
            );

            if (
                $existingType
                && $existingType['plugin_id'] !== null
                && (int)$existingType['plugin_id'] !== $pluginId
            ) {
                throw new \RuntimeException(
                    "Content type {$systemName} belongs to another plugin."
                );
            }

            $defaultManagerId = self::resolveContentTypeManager($contentTypeData);
            $schema = is_array($contentTypeData['schema'] ?? null)
                ? $contentTypeData['schema']
                : ['fields' => []];
            $schema['fields'] = is_array($schema['fields'] ?? null) ? $schema['fields'] : [];
            $schema['title_field'] = $contentTypeData['title_field'] ?? null;
            if (array_key_exists('summary_field', $contentTypeData)) {
                $schema['summary_field'] = $contentTypeData['summary_field'];
            }

            foreach ($schema['fields'] as $fieldName => $localDefinition) {
                if (!is_string($fieldName) || !is_array($localDefinition)) {
                    throw new \RuntimeException(
                        "Invalid field attachment in content type {$systemName}."
                    );
                }
                if (!\DB::getOne('select 1 from fields where system_name=$1', [$fieldName])) {
                    throw new \RuntimeException(
                        "Unknown field {$fieldName} in content type {$systemName}. "
                        . 'Declare it in structures.json.fields first.'
                    );
                }
                self::assertLocalFieldDeclaration($systemName, $fieldName, $localDefinition);
            }

            $data = [
                'plugin_id' => $pluginId,
                'system_name' => $systemName,
                'schema' => $schema,
                'has_slug' => (bool)($contentTypeData['has_slug'] ?? false),
                'default_manager_plugin_id' => $defaultManagerId,
            ];
            if (!$existingType) {
                $data['manager_plugin_id'] = $defaultManagerId;
                $data['manager_overridden'] = false;
            } elseif (empty($existingType['manager_overridden'])) {
                $data['manager_plugin_id'] = $defaultManagerId;
            }

            $contentType = \Core\ContentStructure::saveContentType(
                $existingType ? (int)$existingType['ct_id'] : null,
                $data
            );

            foreach ($languages as $language => $languageData) {
                self::upsertTranslation(
                    (string)$contentType['uuid'],
                    (string)$language,
                    ExtensionLanguage::contentTypeTranslation(
                        $systemName,
                        $languageData,
                        $contentTypeData
                    )
                );
            }
        }
    }

    private static function resolveContentTypeManager(
        array $contentTypeData
    ): ?int {
        $manager = $contentTypeData['manager'] ?? null;
        if ($manager === null) {
            return null;
        }
        if (!is_string($manager) || $manager === '') {
            throw new \RuntimeException(
                'Content type manager must be a plugin system name or null.'
            );
        }

        $managerId = \DB::getOne(
            'select plugin_id from plugins where system_name=$1',
            [$manager]
        );
        if (!$managerId) {
            throw new \RuntimeException(
                "Content type manager plugin is not installed: {$manager}."
            );
        }

        return (int) $managerId;
    }

    private static function assertLocalFieldDeclaration(
        string $contentType,
        string $fieldName,
        array $definition
    ): void {
        foreach (['type', 'params', 'options'] as $key) {
            if (array_key_exists($key, $definition)) {
                throw new \RuntimeException(
                    "content_types.{$contentType}.schema.fields.{$fieldName}.{$key} "
                    . 'belongs to the global field declaration.'
                );
            }
        }

        $settings = is_array($definition['settings'] ?? null)
            ? $definition['settings']
            : [];
        foreach (['indexed', 'unique', 'translatable'] as $key) {
            if (array_key_exists($key, $settings)) {
                throw new \RuntimeException(
                    "content_types.{$contentType}.schema.fields.{$fieldName}.settings.{$key} "
                    . 'belongs to the global field declaration.'
                );
            }
        }
    }

    private static function storeTheme(
        array $manifest,
        ?array $existingTheme = null
    ): bool {
        return self::transaction(function () use ($manifest, $existingTheme): void {
            $themeData = [
                'system_name' => self::themeSystemName($manifest),
                'theme_version' => $manifest['info']['version'] ?? null,
                'theme_settings' => self::json(
                    is_array($manifest['settings'] ?? null)
                        ? $manifest['settings']
                        : []
                ),
            ];

            if ($existingTheme === null) {
                $theme = \DB::insert(
                    'themes',
                    $themeData,
                    'theme_id, uuid'
                );

                if (!is_array($theme)) {
                    throw new \RuntimeException('Failed to create theme record.');
                }
            } else {
                self::assertWrite(
                    \DB::update(
                        'themes',
                        $themeData,
                        'theme_id=$1',
                        [$existingTheme['theme_id']]
                    ),
                    'Failed to update theme record.'
                );

                $theme = $existingTheme;
            }

            $themeLanguage = (string) ($manifest['info']['language'] ?? 'en');
            self::syncThemeLayouts(
                (int) $theme['theme_id'],
                $manifest['layouts'] ?? [],
                $themeLanguage
            );

            $translation = \findTranslatables($manifest);
            $translation = is_array($translation) ? $translation : [];
            unset($translation['info'], $translation['layouts']);
            $translation['title'] = $manifest['info']['title']
                ?? self::themeSystemName($manifest);
            $translation['description'] =
                $manifest['info']['description'] ?? '';

            self::upsertTranslation(
                (string) $theme['uuid'],
                $themeLanguage,
                $translation
            );
        });
    }

    private static function syncThemeLayouts(
        int $themeId,
        array $layouts,
        string $language
    ): void {
        foreach ($layouts as $systemName => $layoutData) {
            if (!is_string($systemName) || !is_array($layoutData)) {
                throw new \RuntimeException('Invalid theme layout declaration.');
            }

            $wrappers = is_array($layoutData['wrappers'] ?? null)
                ? $layoutData['wrappers']
                : [];
            foreach ($wrappers as &$wrapper) {
                if (!is_array($wrapper)) {
                    continue;
                }
                unset($wrapper['title'], $wrapper['description']);
            }
            unset($wrapper);

            $data = [
                'theme_id' => $themeId,
                'system_name' => $systemName,
                'layout_filename' => $layoutData['filename'] ?? null,
                'wrappers' => self::json($wrappers),
            ];

            if (!$data['layout_filename']) {
                throw new \RuntimeException(
                    "Missing filename for theme layout {$systemName}."
                );
            }

            $layout = \DB::getRow(
                'select layout_id, uuid
                from theme_layouts
                where theme_id=$1 and system_name=$2',
                [$themeId, $systemName]
            );

            if ($layout) {
                self::assertWrite(
                    \DB::update(
                        'theme_layouts',
                        $data,
                        'layout_id=$1',
                        [(int)$layout['layout_id']]
                    ),
                    "Failed to update theme layout {$systemName}."
                );
            } else {
                $layout = \DB::insert(
                    'theme_layouts',
                    $data,
                    'layout_id, uuid'
                );
                if (!is_array($layout)) {
                    throw new \RuntimeException(
                        "Failed to create theme layout {$systemName}."
                    );
                }
            }

            $translation = \findTranslatables($layoutData);
            $translation = is_array($translation) ? $translation : [];
            $translation['title'] = $layoutData['title'] ?? $systemName;
            self::upsertTranslation(
                (string)$layout['uuid'],
                $language,
                $translation
            );
        }
    }

    private static function runPluginMigrations(int $pluginId, string $folder): void
    {
        $directory = self::packageDirectory('plugins', $folder)
            . '/install/db/migrations';

        if (!is_dir($directory)) {
            return;
        }

        $files = array_values(array_filter(
            scandir($directory) ?: [],
            static fn(string $file): bool => str_ends_with($file, '.sql')
        ));
        sort($files, SORT_STRING);

        foreach ($files as $file) {
            if (!preg_match('/^\d{3,}_[A-Za-z0-9][A-Za-z0-9_-]*\.sql$/', $file)) {
                throw new \RuntimeException(
                    "Invalid plugin migration filename: {$folder}/{$file}."
                );
            }

            $path = $directory . '/' . $file;
            $sql = trim((string) file_get_contents($path));
            if ($sql === '') {
                throw new \RuntimeException(
                    "Plugin migration is empty: {$folder}/{$file}."
                );
            }

            if (preg_match('/^\s*(BEGIN|COMMIT|ROLLBACK)\s*;/mi', $sql)) {
                throw new \RuntimeException(
                    "Plugin migration must not manage transactions: {$folder}/{$file}."
                );
            }

            $checksum = hash('sha256', $sql);
            $applied = \DB::getRow(
                'select checksum from plugin_migrations
                where plugin_id=$1 and migration_name=$2',
                [$pluginId, $file]
            );

            if ($applied) {
                if (!hash_equals((string) $applied['checksum'], $checksum)) {
                    throw new \RuntimeException(
                        "Applied plugin migration was modified: {$folder}/{$file}."
                    );
                }
                continue;
            }

            self::assertWrite(
                \DB::query($sql),
                "Failed to apply plugin migration {$folder}/{$file}."
            );

            self::assertWrite(
                \DB::insert('plugin_migrations', [
                    'plugin_id' => $pluginId,
                    'migration_name' => $file,
                    'checksum' => $checksum,
                ]),
                "Failed to record plugin migration {$folder}/{$file}."
            );
        }
    }

    /**
     * @return array<string, mixed>
     */
    private static function loadPluginManifest(string $folder): array
    {
        $directory = self::packageDirectory('plugins', $folder);
        $installDirectory = $directory . '/install';
        $bundle = JsonTool::loadFile($installDirectory . '/manifest.json', true);

        if (($bundle['type'] ?? null) !== 'plugin') {
            throw new \RuntimeException(
                "Invalid plugin package type: {$folder}."
            );
        }

        $manifest = $bundle['data'] ?? null;

        if (!is_array($manifest)) {
            throw new \RuntimeException(
                "Plugin manifest has no data object: {$folder}."
            );
        }

        $languageFiles = [];
        $languageDirectory = $installDirectory . '/lang';

        if (is_dir($languageDirectory)) {
            foreach (scandir($languageDirectory) ?: [] as $file) {
                if (!str_ends_with($file, '.json')) {
                    continue;
                }

                $language = pathinfo($file, PATHINFO_FILENAME);
                if (!preg_match('/^[a-z]{2}(?:-[A-Z]{2})?$/', $language)) {
                    throw new \RuntimeException(
                        "Invalid plugin language code: {$language}."
                    );
                }

                $languageFiles[$language] = JsonTool::loadFile(
                    $languageDirectory . '/' . $file,
                    true
                );
            }
        }

        $structuresFile = $installDirectory . '/structures.json';
        $structures = is_file($structuresFile)
            ? JsonTool::loadFile($structuresFile, true)
            : [];
        $structures = self::normalizeStructures($structures);
        $manifest['field_types'] = $structures['field_types'];
        $manifest['fields'] = $structures['fields'];
        $manifest['content_types'] = $structures['content_types'];
        $manifest['language_files'] = $languageFiles;

        self::pluginSystemName($manifest);

        foreach ($languageFiles as $language => $languageData) {
            ExtensionLanguage::validatePlugin(
                $languageData,
                $manifest,
                "{$folder}/install/lang/{$language}.json"
            );
        }

        return $manifest;
    }

    private static function normalizeStructures(array $structures): array
    {
        $keys = ['field_types', 'fields', 'content_types'];
        $isCanonical = false;
        foreach ($keys as $key) {
            if (array_key_exists($key, $structures)) {
                $isCanonical = true;
                break;
            }
        }

        if (!$isCanonical) {
            return [
                'field_types' => [],
                'fields' => [],
                'content_types' => $structures,
            ];
        }

        foreach (array_keys($structures) as $key) {
            if (!is_string($key) || !in_array($key, $keys, true)) {
                throw new \RuntimeException(
                    "Unknown structures.json section: {$key}."
                );
            }
        }

        $normalized = [];
        foreach ($keys as $key) {
            $value = $structures[$key] ?? [];
            if (!is_array($value)) {
                throw new \RuntimeException("structures.json.{$key} must be an object.");
            }
            $normalized[$key] = $value;
        }

        return $normalized;
    }

    /**
     * @return array<string, mixed>
     */
    private static function loadThemeManifest(string $folder): array
    {
        $directory = self::packageDirectory('themes', $folder);
        $bundle = JsonTool::loadFile($directory . '/manifest.json', true);

        if (($bundle['type'] ?? null) !== 'theme') {
            throw new \RuntimeException(
                "Invalid theme package type: {$folder}."
            );
        }

        $manifest = $bundle['data'] ?? null;

        if (!is_array($manifest)) {
            throw new \RuntimeException(
                "Theme manifest has no data object: {$folder}."
            );
        }

        self::themeSystemName($manifest);

        return $manifest;
    }

    private static function pluginSystemName(array $manifest): string
    {
        return self::requiredSystemName(
            $manifest['info']['name'] ?? null,
            'plugin'
        );
    }

    private static function themeSystemName(array $manifest): string
    {
        return self::requiredSystemName(
            $manifest['info']['name'] ?? null,
            'theme'
        );
    }

    private static function requiredSystemName(
        mixed $systemName,
        string $type
    ): string {
        if (!is_string($systemName) || $systemName === '') {
            throw new \RuntimeException(
                "Missing {$type} system name."
            );
        }

        return $systemName;
    }

    private static function packageDirectory(
        string $type,
        string $folder
    ): string {
        if (!preg_match('/^[A-Za-z0-9][A-Za-z0-9_-]*$/', $folder)) {
            throw new \InvalidArgumentException(
                "Invalid package folder: {$folder}."
            );
        }

        $directory = ROOT_PATH . $type . '/' . $folder;

        if (!is_dir($directory)) {
            throw new \RuntimeException(
                "Package directory not found: {$directory}."
            );
        }

        return $directory;
    }

    private static function upsertTranslation(
        string $uuid,
        string $language,
        array $translation
    ): void {
        self::assertWrite(
            \DB::query(
                'insert into translations(
                    entity_uuid,
                    lang_code,
                    translated_data
                ) values($1, $2, $3)
                on conflict (entity_uuid, lang_code)
                do update set translated_data=excluded.translated_data',
                [$uuid, $language, self::json($translation)]
            ),
            "Failed to save {$language} translation for {$uuid}."
        );

        \Cache::del("globals:{$uuid}_{$language}");
    }

    private static function json(array $data): string
    {
        return JsonTool::encode($data, false);
    }

    /**
     * @return array<string, mixed>|null
     */
    private static function decodeObject(mixed $value): ?array
    {
        if ($value === null) {
            return null;
        }

        return is_array($value)
            ? $value
            : JsonTool::decode((string) $value);
    }

    private static function isUuid(string $value): bool
    {
        return preg_match(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-'
            . '[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i',
            $value
        ) === 1;
    }

    private static function transaction(callable $operation): bool
    {
        if (!\DB::beginTransaction()) {
            return false;
        }

        try {
            $operation();

            if (!\DB::commit()) {
                throw new \RuntimeException(
                    'Failed to commit extension lifecycle transaction.'
                );
            }

            return true;
        } catch (\Throwable $e) {
            \DB::rollBack();
            throw $e;
        }
    }

    private static function assertWrite(
        mixed $result,
        string $message
    ): void {
        if ($result === false) {
            throw new \RuntimeException($message);
        }
    }

    private static function notImplemented(string $operation): bool
    {
        trigger_error(
            "{$operation} is not implemented yet.",
            E_USER_WARNING
        );

        return false;
    }
}
