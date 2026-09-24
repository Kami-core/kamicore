<?php

/**
 * KamiCore
 *
 * SPDX-License-Identifier: Apache-2.0
 *
 * @see https://kamicore.org
 */

declare(strict_types=1);

namespace Plugins\PluginManager;

use Core\Utils\JsonTool;

if (!defined('IN_KAMI')) die();

class PluginManager extends \Core\BasePlugin
{
    public function list(array $contextVars = []): string
    {
        $this->addCss('/plugins/PluginManager/assets/plugin-manager.css');
        $this->assertManagerAccess();

        $catalog = $this->pluginCatalog();
        $categoryCounts = array_count_values(array_column($catalog, 'category'));
        $rows = [];
        $currentCategory = null;

        foreach ($catalog as $plugin) {
            if ($plugin['category'] !== $currentCategory) {
                $currentCategory = $plugin['category'];
                $rows[] = [
                    'template' => 'plugin-category-row',
                    'params' => [
                        'category' => \Core\Html::escape($this->categoryTitle($currentCategory)),
                        'count' => (string)($categoryCounts[$currentCategory] ?? 0),
                    ],
                ];
            }

            $actions = [];
            if (!$plugin['installed']) {
                $actions[] = '<form method="post" action="' . $this->managerUrl('lifecycle') . '">'
                    . '<input type="hidden" name="operation" value="install">'
                    . '<input type="hidden" name="plugin" value="' . \Core\Html::escape($plugin['system_name']) . '">'
                    . '<button class="admin-button admin-button-primary" type="submit">'
                    . \Core\Html::escape($this->phrases['install'] ?? 'Install') . '</button></form>';
            } else {
                $actions[] = '<a class="admin-button admin-button-secondary" href="'
                    . $this->managerUrl('plugin', ['pm-plugin' => $plugin['system_name']]) . '">'
                    . \Core\Html::escape($this->phrases['settings'] ?? 'Manage') . '</a>';

                if ($plugin['update_available']) {
                    $actions[] = '<form method="post" action="' . $this->managerUrl('lifecycle') . '">'
                        . '<input type="hidden" name="operation" value="update">'
                        . '<input type="hidden" name="plugin" value="' . \Core\Html::escape($plugin['system_name']) . '">'
                        . '<button class="admin-button admin-button-primary" type="submit">'
                        . \Core\Html::escape($this->phrases['update'] ?? 'Update') . '</button></form>';
                }

                if ($plugin['has_setup']) {
                    $actions[] = '<a class="admin-button admin-button-secondary" href="'
                        . $this->managerUrl('setup', ['pm-plugin' => $plugin['system_name']]) . '">'
                        . \Core\Html::escape($this->phrases['setup'] ?? 'Setup') . '</a>';
                }

                if (!$plugin['active'] && $plugin['domain_count'] === 0) {
                    $actions[] = '<form method="post" action="' . $this->managerUrl('lifecycle') . '" '
                        . 'onsubmit="return confirm(' . $this->jsString(
                            $this->phrases['confirm_uninstall'] ?? 'Are you sure you want to uninstall this plugin?'
                        ) . ')">'
                        . '<input type="hidden" name="operation" value="uninstall">'
                        . '<input type="hidden" name="plugin" value="'
                        . \Core\Html::escape($plugin['system_name']) . '">'
                        . '<button class="admin-button admin-button-danger" type="submit">'
                        . \Core\Html::escape($this->phrases['uninstall'] ?? 'Uninstall')
                        . '</button></form>';
                }
            }

            $rows[] = [
                'template' => 'plugin-row',
                'params' => [
                    'title' => \Core\Html::escape($plugin['title']),
                    'system_name' => \Core\Html::escape($plugin['system_name']),
                    'description' => \Core\Html::escape($plugin['description']),
                    'version' => \Core\Html::escape($plugin['package_version'] ?: '—'),
                    'installed_version' => \Core\Html::escape($plugin['installed_version'] ?: '—'),
                    'status' => \Core\Html::escape($plugin['installed']
                        ? ($plugin['active']
                            ? ($this->phrases['active'] ?? 'Active')
                            : ($this->phrases['inactive'] ?? 'Inactive'))
                        : ($this->phrases['not_installed'] ?? 'Not installed')),
                    'status_class' => $plugin['installed']
                        ? ($plugin['active'] ? 'is-active' : 'is-inactive')
                        : 'is-available',
                    'domains' => \Core\Html::escape((string)$plugin['domain_count']),
                    'actions' => implode('', $actions),
                ],
            ];
        }

        return $this->render('plugins', [
            'plugin_rows' => $rows,
            'plugin_count' => (string)count($catalog),
        ]);
    }

    public function plugin(array $contextVars = []): string
    {
        $this->addCss('/plugins/PluginManager/assets/plugin-manager.css');
        $this->assertManagerAccess();
        $pluginName = $this->requestedPluginName();
        $plugin = \DB::getRow(
            'select plugin_id, uuid, system_name, plugin_prefix, settings,
                plugin_version, is_active
            from plugins where system_name=$1',
            [$pluginName]
        );
        if (!$plugin) {
            return $this->notice('Plugin not found.', 'error');
        }

        $activeDomains = array_fill_keys(array_map(
            'intval',
            \DB::getArr(
                'select domain_id from plugin_domains where plugin_id=$1',
                [(int)$plugin['plugin_id']]
            )
        ), true);

        $domainRows = [];
        $domains = \DB::query('select domain_id, domain_name from domains order by domain_name');
        while ($domain = \DB::fetchRow($domains)) {
            $domainId = (int)$domain['domain_id'];
            $domainRows[] = [
                'template' => 'domain-row',
                'params' => [
                    'domain_id' => (string)$domainId,
                    'domain_name' => \Core\Html::escape((string)$domain['domain_name']),
                    'checked' => isset($activeDomains[$domainId]) ? ' checked' : '',
                ],
            ];
        }

        $settingsStructure = $this->pluginManifestSettings($pluginName);
        // Local settings are loaded dynamically and may contain entity-backed fields.
        $this->forms()->registerEntityFieldAssets();
        $baseValues = \Core\Utils\JsonTool::decodeArray($plugin['settings'] ?? null);
        $settingsTranslation = \Core\Translation::get((string)$plugin['uuid'])['settings'] ?? [];
        $globalFields = '';
        foreach ($settingsStructure as $name => $structure) {
            if (!is_string($name) || !is_array($structure) || !$this->isGlobalSetting($structure)) {
                continue;
            }
            $globalFields .= $this->renderSettingFields(
                $name,
                $structure,
                $baseValues[$name] ?? null,
                $settingsTranslation,
                'global_settings',
                $this->settingLanguages()
            );
        }

        $domainOptions = [];
        foreach (array_keys($activeDomains) as $domainId) {
            $domainName = (string)(\DB::getOne(
                'select domain_name from domains where domain_id=$1',
                [$domainId]
            ) ?? $domainId);
            $domainOptions[] = '<option value="' . $domainId . '">'
                . \Core\Html::escape($domainName) . '</option>';
        }

        return $this->render('plugin-detail', [
            'title' => \Core\Html::escape($this->pluginTitle($plugin)),
            'system_name' => \Core\Html::escape((string)$plugin['system_name']),
            'version' => \Core\Html::escape((string)($plugin['plugin_version'] ?? '—')),
            'prefix' => \Core\Html::escape((string)($plugin['plugin_prefix'] ?? '—')),
            'domain_rows' => $domainRows,
            'activation_action' => $this->managerUrl('pluginActivation'),
            'settings_action' => $this->managerUrl('pluginSettingsSave'),
            'global_fields' => $globalFields,
            'has_global_settings' => $globalFields !== '' ? '' : ' hidden',
            'has_local_settings' => $this->hasLocalSettings($settingsStructure) ? '' : ' hidden',
            'domain_options' => implode('', $domainOptions),
            'settings_structure_json' => $this->jsonForHtml($settingsStructure),
            'settings_translation_json' => $this->jsonForHtml(
                is_array($settingsTranslation) ? $settingsTranslation : []
            ),
            'settings_load_url' => '/ajax/PluginManager/pluginDomainSettings',
            'back_url' => $this->managerUrl(),
            'back_label' => \Core\Html::escape($this->phrases['back_to_plugins'] ?? 'Back to plugins'),
        ]);
    }

    public function lifecycle(array $contextVars = []): string
    {
        $this->assertManagerAccess();
        $data = \Core\Request::all();
        $operation = trim((string)($data['operation'] ?? ''));
        $pluginName = $this->validatePluginName((string)($data['plugin'] ?? ''));

        try {
            $result = match ($operation) {
                'install' => $this->installPlugin($pluginName),
                'update' => $this->updatePlugin($pluginName),
                'uninstall' => $this->uninstallPlugin($pluginName),
                default => throw new \InvalidArgumentException('Unknown plugin lifecycle operation.'),
            };
        } catch (\Throwable $e) {
            return $this->notice($e->getMessage(), 'error');
        }

        if (!$result) {
            return $this->notice('Plugin operation failed.', 'error');
        }

        if ($notifications = $this->plugins->get('Notifications')) {
            $message = match ($operation) {
                'install' => $this->phrases['installed_success'] ?? 'Plugin installed.',
                'update' => $this->phrases['updated_success'] ?? 'Plugin updated.',
                'uninstall' => $this->phrases['uninstalled_success'] ?? 'Plugin uninstalled.',
                default => $this->phrases['operation_success'] ?? 'Plugin operation completed.',
            };
            $notifications->store(\Core\User::getId(), $message, 'success');
        }

        return \Core\Response::seeOther($this->managerUrl());
    }

    public function pluginActivation(array $contextVars = []): string
    {
        $this->assertManagerAccess();
        $data = \Core\Request::all();
        $pluginName = $this->validatePluginName((string)($data['plugin'] ?? ''));
        $domains = is_array($data['domains'] ?? null)
            ? array_values(array_unique(array_filter(array_map('intval', $data['domains']))))
            : [];

        $this->setDomainActivation($pluginName, $domains);

        if ($notifications = $this->plugins->get('Notifications')) {
            $notifications->store(
                \Core\User::getId(),
                $this->phrases['activation_saved'] ?? 'Plugin activation updated.',
                'success'
            );
        }

        return \Core\Response::seeOther(
            $this->managerUrl('plugin', ['pm-plugin' => $pluginName])
        );
    }

    public function pluginSettingsSave(array $contextVars = []): string
    {
        $this->assertManagerAccess();
        $data = \Core\Request::all();
        $pluginName = $this->validatePluginName((string)($data['plugin'] ?? ''));
        $domainId = (int)($data['domain_id'] ?? 0);
        $this->savePluginSettings(
            $pluginName,
            is_array($data['global_settings'] ?? null) ? $data['global_settings'] : [],
            $domainId,
            is_array($data['local_settings'] ?? null) ? $data['local_settings'] : []
        );

        if ($notifications = $this->plugins->get('Notifications')) {
            $notifications->store(
                \Core\User::getId(),
                $this->phrases['settings_saved'] ?? 'Plugin settings saved.',
                'success'
            );
        }

        return \Core\Response::seeOther(
            $this->managerUrl('plugin', ['pm-plugin' => $pluginName])
        );
    }

    public function pluginDomainSettings(array $data = []): string
    {
        $this->assertManagerAccess();
        $pluginName = $this->validatePluginName((string)($data['plugin'] ?? ''));
        $domainId = (int)($data['domain_id'] ?? 0);
        $plugin = \DB::getRow(
            'select plugin_id, uuid, settings
            from plugins where system_name=$1',
            [$pluginName]
        );
        if (!$plugin || $domainId < 1) {
            return $this->jsonResponse(['status' => 'error', 'error' => 'Invalid plugin or domain.'], 400);
        }
        $domain = \DB::getRow(
            'select local_settings from plugin_domains where plugin_id=$1 and domain_id=$2',
            [(int)$plugin['plugin_id'], $domainId]
        );
        if (!$domain) {
            return $this->jsonResponse(['status' => 'error', 'error' => 'Plugin is not active on this domain.'], 404);
        }

        $structure = $this->pluginManifestSettings($pluginName);
        $baseValues = \Core\Utils\JsonTool::decodeArray($plugin['settings'] ?? null);
        $locals = \Core\Utils\JsonTool::decodeArray($domain['local_settings'] ?? null);
        $translation = \Core\Translation::get((string)$plugin['uuid'])['settings'] ?? [];
        $html = '';
        foreach ($structure as $name => $setting) {
            if (!is_string($name) || !is_array($setting) || $this->isGlobalSetting($setting)) {
                continue;
            }
            $value = $this->effectiveSettingValue(
                $locals[$name] ?? null,
                $baseValues[$name] ?? null,
                $setting,
                array_key_exists($name, $locals)
            );
            $html .= $this->renderSettingFields(
                $name,
                $setting,
                $value,
                $translation,
                'local_settings',
                $this->settingLanguages($domainId)
            );
        }

        return $this->jsonResponse([
            'status' => 'ok',
            'html' => \Core\Renderer::finalize($html),
        ]);
    }

    public function setup(array $contextVars = []): string
    {
        $this->addCss('/plugins/PluginManager/assets/plugin-manager.css');
        $this->assertManagerAccess();
        $selectedPlugin = trim((string)$this->param('plugin', ''));
        $setupPlugins = [];
        foreach ($this->pluginCatalog() as $plugin) {
            if (!$plugin['installed'] || !$plugin['has_setup']) {
                continue;
            }
            $presets = $this->getSetupPresets($plugin['system_name']);
            if ($presets === []) {
                continue;
            }
            $setupPlugins[$plugin['system_name']] = [
                'title' => $plugin['title'],
                'presets' => array_keys($presets),
            ];
        }

        $domainOptions = [];
        $domains = \DB::query('select domain_id, domain_name from domains order by domain_name');
        while ($domain = \DB::fetchRow($domains)) {
            $domainOptions[] = [
                'id' => (int)$domain['domain_id'],
                'name' => (string)$domain['domain_name'],
            ];
        }

        return $this->render('setup', [
            'plugins_json' => $this->jsonForHtml($setupPlugins),
            'domains_json' => $this->jsonForHtml($domainOptions),
            'selected_plugin' => \Core\Html::escape($selectedPlugin),
            'plan_url' => '/ajax/PluginManager/resolveSetup',
            'apply_url' => '/ajax/PluginManager/applySetup',
            'back_url' => $this->managerUrl(),
            'back_label' => \Core\Html::escape($this->phrases['back_to_plugins'] ?? 'Back to plugins'),
        ]);
    }

    public function resolveSetup(array $data = []): string
    {
        $this->assertManagerAccess();
        try {
            $plan = $this->buildSetupPlan(
                (string)($data['plugin'] ?? ''),
                (string)($data['preset'] ?? ''),
                (int)($data['domain_id'] ?? 0)
            );
            return $this->jsonResponse(['status' => 'ok', 'plan' => $plan]);
        } catch (\Throwable $e) {
            return $this->jsonResponse(['status' => 'error', 'error' => $e->getMessage()], 400);
        }
    }

    public function applySetup(array $data = []): string
    {
        $this->assertManagerAccess();
        try {
            $plan = is_array($data['plan'] ?? null) ? $data['plan'] : $data;
            return $this->jsonResponse($this->applySetupPlan($plan));
        } catch (\Throwable $e) {
            return $this->jsonResponse(['status' => 'error', 'error' => $e->getMessage()], 400);
        }
    }

    public function installPlugin(string $folder): bool
    {
        $this->assertManagerAccess();
        return $this->runLifecycleAction(
            'install',
            $folder,
            static fn(): bool => \Core\ExtensionManager::installPlugin($folder)
        );
    }

    public function updatePlugin(string $folder): bool
    {
        $this->assertManagerAccess();
        return $this->runLifecycleAction(
            'update',
            $folder,
            static fn(): bool => \Core\ExtensionManager::updatePlugin($folder)
        );
    }

    public function uninstallPlugin(string $systemName): bool
    {
        $this->assertManagerAccess();
        return $this->runLifecycleAction(
            'uninstall',
            $systemName,
            static fn(): bool => \Core\ExtensionManager::uninstallPlugin($systemName)
        );
    }

    public function getSetupPresets(string $pluginName): array
    {
        $pluginName = $this->validatePluginName($pluginName);
        $path = ROOT_PATH . "plugins/{$pluginName}/install/setup.json";
        if (!is_file($path)) {
            return [];
        }

        $data = JsonTool::loadFile($path, true);
        $presets = $data['presets'] ?? null;
        if (!is_array($presets)) {
            throw new \RuntimeException(
                "Plugin setup file has no presets object: {$pluginName}."
            );
        }

        $normalized = [];
        foreach ($presets as $presetName => $preset) {
            if (!is_string($presetName) || $presetName === '' || !is_array($preset)) {
                throw new \RuntimeException(
                    "Invalid setup preset declaration in {$pluginName}."
                );
            }

            $pages = $preset['pages'] ?? [];
            if (!is_array($pages) || !array_is_list($pages)) {
                throw new \RuntimeException(
                    "Setup preset pages must be a list: {$pluginName}/{$presetName}."
                );
            }

            $normalizedPages = [];
            foreach ($pages as $index => $page) {
                if (!is_array($page)) {
                    throw new \RuntimeException(
                        "Invalid setup page #{$index} in {$pluginName}/{$presetName}."
                    );
                }

                $systemName = trim((string)($page['system_name'] ?? ''));
                if ($systemName === '') {
                    throw new \RuntimeException(
                        "Missing system_name for setup page #{$index} in {$pluginName}/{$presetName}."
                    );
                }

                $recipeName = trim((string)($page['recipe_name'] ?? ''));
                if ($recipeName === '') {
                    throw new \RuntimeException(
                        "Missing recipe_name for setup page {$systemName} in {$pluginName}/{$presetName}."
                    );
                }

                $wrappers = $page['wrappers'] ?? [];
                if (!is_array($wrappers)) {
                    throw new \RuntimeException(
                        "Invalid wrappers for setup page {$systemName} in {$pluginName}/{$presetName}."
                    );
                }

                foreach ($wrappers as $wrapper => $instances) {
                    if (!is_string($wrapper) || $wrapper === '' || !is_array($instances) || !array_is_list($instances)) {
                        throw new \RuntimeException(
                            "Invalid wrapper declaration for setup page {$systemName}."
                        );
                    }
                }

                $normalizedPages[] = $page;
            }

            $preset['pages'] = $normalizedPages;
            $normalized[$presetName] = $preset;
        }

        return $normalized;
    }

    public function buildSetupPlan(
        string $pluginName,
        string $presetName,
        int $domainId
    ): array {
        $pluginName = $this->validatePluginName($pluginName);
        if ($domainId < 1 || !\DB::getOne('select 1 from domains where domain_id=$1', [$domainId])) {
            throw new \OutOfBoundsException("Unknown domain: {$domainId}.");
        }

        $manifest = $this->loadPluginManifest($pluginName);
        $presets = $this->getSetupPresets($pluginName);
        if (!isset($presets[$presetName])) {
            throw new \OutOfBoundsException(
                "Unknown setup preset {$pluginName}/{$presetName}."
            );
        }

        $defaultLanguage = (string)($manifest['config']['default_language'] ?? 'en');
        $pageManager = $this->pageManager();
        $navigation = $this->navigation();
        $pages = [];
        $hasErrors = false;

        foreach ($presets[$presetName]['pages'] as $index => $presetPage) {
            $systemName = trim((string)$presetPage['system_name']);
            $recipeName = trim((string)$presetPage['recipe_name']);
            $recipeResolution = $pageManager->resolveRecipe($recipeName, $domainId);
            $recipe = $recipeResolution['recipe'];
            $recipePayload = $recipeResolution['payload'];
            $layout = $recipeResolution['layout'];
            $layoutName = (string)($recipeResolution['layout_name'] ?? $recipePayload['layout'] ?? '');
            $availableWrappers = $recipeResolution['available_wrappers'];
            $recipeInstances = $recipeResolution['instances'];
            $issues = $recipeResolution['issues'];

            $presetInstances = $this->resolvePresetInstances(
                $presetPage['wrappers'] ?? [],
                $availableWrappers,
                $pluginName,
                $issues
            );

            foreach ($issues as $issue) {
                if (($issue['severity'] ?? '') === 'error') {
                    $hasErrors = true;
                }
            }

            $requestedSlug = trim((string)($presetPage['slug'] ?? $systemName));
            $resolvedSlug = (string)$recipePayload['page_prefix'] . $requestedSlug;
            if ($resolvedSlug === '') {
                $resolvedSlug = '/';
            }

            if (\DB::getOne(
                'select 1 from pages where domain_id=$1 and system_name=$2',
                [$domainId, $systemName]
            )) {
                $issues[] = $this->issue(
                    'duplicate_system_name',
                    'error',
                    [
                        'requested' => $systemName,
                        'suggested' => $this->suggestPageSystemName($domainId, $systemName),
                    ]
                );
                $hasErrors = true;
            }

            if (\DB::getOne(
                'select 1 from pages where domain_id=$1 and page_slug=$2',
                [$domainId, $resolvedSlug]
            )) {
                $issues[] = $this->issue(
                    'duplicate_url',
                    'error',
                    [
                        'requested' => $resolvedSlug,
                        'suggested' => $this->suggestPageSlug($domainId, $resolvedSlug),
                    ]
                );
                $hasErrors = true;
            }

            $titles = is_array($presetPage['titles'] ?? null)
                ? $presetPage['titles']
                : [];
            $title = trim((string)($titles[$defaultLanguage]
                ?? $presetPage['title']
                ?? $systemName));
            if ($title === '') {
                $title = $systemName;
            }
            if (!isset($titles[$defaultLanguage]) || trim((string)$titles[$defaultLanguage]) === '') {
                $titles[$defaultLanguage] = $title;
            }

            $menus = [];
            foreach ($recipePayload['default_navigation_menus'] as $menuKey) {
                try {
                    $menu = $navigation->findMenuByKey($menuKey);
                } catch (\Throwable $e) {
                    $menu = null;
                    $issues[] = $this->issue(
                        'invalid_menu',
                        'error',
                        ['requested' => $menuKey, 'message' => $e->getMessage()]
                    );
                    $hasErrors = true;
                }

                if (!$menu) {
                    $issues[] = $this->issue(
                        'missing_menu',
                        'error',
                        ['requested' => $menuKey]
                    );
                    $hasErrors = true;
                    $menus[] = [
                        'requested' => $menuKey,
                        'resolved' => null,
                        'menu_id' => null,
                    ];
                    continue;
                }

                $menus[] = [
                    'requested' => $menuKey,
                    'resolved' => $menuKey,
                    'menu_id' => (int)$menu['item_id'],
                ];
            }

            $pages[] = [
                'resource_key' => 'page:' . $index,
                'requested' => [
                    'system_name' => $systemName,
                    'slug' => $requestedSlug,
                    'recipe_name' => $recipeName,
                ],
                'resolved' => [
                    'system_name' => $systemName,
                    'slug' => $resolvedSlug,
                    'recipe_name' => $recipeName,
                    'recipe_id' => $recipe['recipe_id'] ?? null,
                    'layout' => $layoutName,
                ],
                'title' => $title,
                'titles' => $titles,
                'recipe_snapshot' => $recipe,
                'recipe_instances' => $recipeInstances,
                'preset_instances' => $presetInstances,
                'navigation' => $menus,
                'issues' => $issues,
            ];
        }

        return [
            'plugin' => $pluginName,
            'preset' => $presetName,
            'domain_id' => $domainId,
            'default_language' => $defaultLanguage,
            'valid' => !$hasErrors,
            'pages' => $pages,
        ];
    }

    public function applySetupPlan(array $plan): array
    {
        $this->assertManagerAccess();
        $validated = $this->validateResolvedPlan($plan);
        $pluginName = $validated['plugin'];
        $presetName = $validated['preset'];
        $domainId = $validated['domain_id'];
        $pluginId = (int)(\DB::getOne(
            'select plugin_id from plugins where system_name=$1',
            [$pluginName]
        ) ?? 0);
        if ($pluginId < 1) {
            throw new \RuntimeException("Plugin is not installed: {$pluginName}.");
        }

        if (!\DB::beginTransaction()) {
            throw new \RuntimeException('Failed to start setup transaction.');
        }

        try {
            $setupId = $this->createHistory([
                'plugin_id' => $pluginId,
                'plugin_system_name' => $pluginName,
                'domain_id' => $domainId,
                'action' => 'setup',
                'status' => 'success',
                'preset_name' => $presetName,
                'config' => [
                    'requested' => $plan,
                    'resolved' => $validated,
                ],
            ]);

            $pageManager = $this->pageManager();
            $navigation = $this->navigation();
            $createdPages = [];

            foreach ($validated['pages'] as $pageIndex => $pagePlan) {
                $page = $pageManager->createPageFromPlan([
                    'domain_id' => $domainId,
                    'system_name' => $pagePlan['resolved']['system_name'],
                    'slug' => $pagePlan['resolved']['slug'],
                    'layout' => $pagePlan['resolved']['layout'],
                    'title' => $pagePlan['title'],
                    'titles' => $pagePlan['titles'],
                    'default_language' => $validated['default_language'],
                ]);

                $recipeId = isset($pagePlan['resolved']['recipe_id'])
                    ? (int)$pagePlan['resolved']['recipe_id']
                    : null;
                $recipeSnapshot = $pagePlan['recipe_snapshot'];

                $this->recordResource($setupId, [
                    'resource_key' => 'page:' . $pageIndex,
                    'resource_type' => 'page',
                    'resource_id' => $page['page_id'],
                    'resource_uuid' => $page['uuid'],
                    'ownership' => 'created',
                    'recipe_id' => $recipeId,
                    'recipe_snapshot' => $recipeSnapshot,
                    'config' => [
                        'source' => 'preset',
                        'requested' => $pagePlan['requested'],
                        'resolved' => $pagePlan['resolved'],
                    ],
                ]);

                foreach (['recipe_instances', 'preset_instances'] as $sourceKey) {
                    $source = $sourceKey === 'recipe_instances' ? 'recipe' : 'preset';
                    foreach ($pagePlan[$sourceKey] as $instanceIndex => $instance) {
                        if (!empty($instance['skip'])) {
                            continue;
                        }

                        $index = $pageManager->addPluginInstance(
                            (int)$page['page_id'],
                            (string)$instance['wrapper'],
                            (string)$instance['plugin'],
                            is_array($instance['instance_params'] ?? null)
                                ? $instance['instance_params']
                                : []
                        );

                        $this->recordResource($setupId, [
                            'resource_key' => 'page:' . $pageIndex
                                . ':' . $source . ':instance:' . $instanceIndex,
                            'resource_type' => 'page_plugin_instance',
                            'resource_id' => $page['page_id'],
                            'resource_uuid' => $page['uuid'],
                            'ownership' => 'created',
                            'recipe_id' => $recipeId,
                            'recipe_snapshot' => $recipeSnapshot,
                            'config' => [
                                'source' => $source,
                                'requested' => $instance['requested'] ?? $instance,
                                'resolved' => [
                                    'page_id' => $page['page_id'],
                                    'wrapper' => $instance['wrapper'],
                                    'plugin' => $instance['plugin'],
                                    'instance_index' => $index,
                                    'instance_params' => $instance['instance_params'] ?? [],
                                ],
                            ],
                        ]);
                    }
                }

                foreach ($pagePlan['navigation'] as $navIndex => $navPlan) {
                    if (empty($navPlan['menu_id']) || $navPlan['resolved'] === null) {
                        continue;
                    }

                    $navItem = $navigation->appendPageLink(
                        (int)$navPlan['menu_id'],
                        '/' . ltrim((string)$pagePlan['resolved']['slug'], '/'),
                        $pagePlan['titles'],
                        $validated['default_language']
                    );

                    $this->recordResource($setupId, [
                        'resource_key' => 'page:' . $pageIndex . ':navigation:' . $navIndex,
                        'resource_type' => 'navigation_item',
                        'resource_id' => $navItem['item_id'],
                        'resource_uuid' => $navItem['item_uuid'],
                        'ownership' => 'created',
                        'recipe_id' => $recipeId,
                        'recipe_snapshot' => $recipeSnapshot,
                        'config' => [
                            'source' => 'recipe',
                            'requested' => ['menu_key' => $navPlan['requested']],
                            'resolved' => [
                                'menu_key' => $navPlan['resolved'],
                                'menu_id' => $navPlan['menu_id'],
                                'item_id' => $navItem['item_id'],
                            ],
                        ],
                    ]);
                }

                $createdPages[] = $page;
            }

            if (!\DB::commit()) {
                throw new \RuntimeException('Failed to commit setup transaction.');
            }

            return [
                'status' => 'ok',
                'setup_id' => $setupId,
                'pages' => $createdPages,
            ];
        } catch (\Throwable $e) {
            \DB::rollBack();
            $this->createHistory([
                'plugin_id' => $pluginId,
                'plugin_system_name' => $pluginName,
                'domain_id' => $domainId,
                'action' => 'setup',
                'status' => 'failed',
                'preset_name' => $presetName,
                'config' => [
                    'requested' => $plan,
                ],
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    private function validateResolvedPlan(array $plan): array
    {
        $pluginName = $this->validatePluginName((string)($plan['plugin'] ?? ''));
        $presetName = trim((string)($plan['preset'] ?? ''));
        $domainId = (int)($plan['domain_id'] ?? 0);
        if ($presetName === '' || $domainId < 1) {
            throw new \InvalidArgumentException('Setup plugin, preset and domain are required.');
        }

        $basePlan = $this->buildSetupPlan($pluginName, $presetName, $domainId);
        $presets = $this->getSetupPresets($pluginName);
        $presetPages = $presets[$presetName]['pages'] ?? [];
        $submittedPages = is_array($plan['pages'] ?? null) ? $plan['pages'] : [];
        if (count($submittedPages) !== count($basePlan['pages'])) {
            throw new \RuntimeException('Setup plan no longer matches the selected preset.');
        }

        foreach ($basePlan['pages'] as $index => &$basePage) {
            $submitted = $submittedPages[$index] ?? null;
            $presetPage = $presetPages[$index] ?? null;
            if (!is_array($submitted) || !is_array($presetPage)) {
                throw new \RuntimeException("Missing resolved setup page #{$index}.");
            }

            $resolved = is_array($submitted['resolved'] ?? null)
                ? $submitted['resolved']
                : [];
            $systemName = trim((string)($resolved['system_name'] ?? ''));
            $slug = trim((string)($resolved['slug'] ?? ''));
            $recipeName = trim((string)($resolved['recipe_name'] ?? ''));
            if ($systemName === '' || $slug === '' || $recipeName === '') {
                throw new \RuntimeException("Incomplete resolved setup page #{$index}.");
            }

            $layoutName = trim((string)($resolved['layout'] ?? ''));
            $recipeResolution = $this->pageManager()->resolveRecipe(
                $recipeName,
                $domainId,
                $layoutName !== '' ? $layoutName : null
            );
            $recipe = $recipeResolution['recipe'];
            if (!$recipe) {
                throw new \RuntimeException("Resolved recipe does not exist: {$recipeName}.");
            }
            $layout = $recipeResolution['layout'];
            $layoutName = (string)($recipeResolution['layout_name'] ?? '');
            if (!$layout) {
                throw new \RuntimeException("Resolved layout does not exist: {$layoutName}.");
            }

            if (\DB::getOne(
                'select 1 from pages where domain_id=$1 and system_name=$2',
                [$domainId, $systemName]
            )) {
                throw new \RuntimeException(
                    "Resolved page system_name already exists: {$systemName}."
                );
            }
            if (\DB::getOne(
                'select 1 from pages where domain_id=$1 and page_slug=$2',
                [$domainId, $slug]
            )) {
                throw new \RuntimeException("Resolved page URL already exists: {$slug}.");
            }

            $availableWrappers = $recipeResolution['available_wrappers'];
            $issues = $recipeResolution['issues'];
            $expectedRecipeInstances = $recipeResolution['instances'];
            $expectedPresetInstances = $this->resolvePresetInstances(
                is_array($presetPage['wrappers'] ?? null) ? $presetPage['wrappers'] : [],
                $availableWrappers,
                $pluginName,
                $issues
            );

            $basePage['recipe_instances'] = $this->applyResolvedWrappers(
                $expectedRecipeInstances,
                $submitted['recipe_instances'] ?? null,
                $availableWrappers,
                'recipe'
            );
            $basePage['preset_instances'] = $this->applyResolvedWrappers(
                $expectedPresetInstances,
                $submitted['preset_instances'] ?? null,
                $availableWrappers,
                'preset'
            );

            $expectedMenus = $recipeResolution['navigation_menus'];
            $submittedNavigation = is_array($submitted['navigation'] ?? null)
                ? $submitted['navigation']
                : [];
            if (count($submittedNavigation) !== count($expectedMenus)) {
                throw new \RuntimeException(
                    "Resolved navigation no longer matches recipe {$recipeName}."
                );
            }

            $navigation = [];
            foreach ($expectedMenus as $navIndex => $requestedMenuKey) {
                $submittedNav = $submittedNavigation[$navIndex] ?? null;
                if (!is_array($submittedNav)) {
                    throw new \RuntimeException('Invalid resolved navigation entry.');
                }

                $resolvedMenuKey = $submittedNav['resolved'] ?? null;
                if ($resolvedMenuKey === null || trim((string)$resolvedMenuKey) === '') {
                    $navigation[] = [
                        'requested' => $requestedMenuKey,
                        'resolved' => null,
                        'menu_id' => null,
                    ];
                    continue;
                }

                $resolvedMenuKey = trim((string)$resolvedMenuKey);
                $menu = $this->navigation()->findMenuByKey($resolvedMenuKey);
                if (!$menu) {
                    throw new \RuntimeException(
                        "Resolved navigation menu does not exist: {$resolvedMenuKey}."
                    );
                }
                $navigation[] = [
                    'requested' => $requestedMenuKey,
                    'resolved' => $resolvedMenuKey,
                    'menu_id' => (int)$menu['item_id'],
                ];
            }

            $basePage['resolved'] = array_replace($basePage['resolved'], [
                'system_name' => $systemName,
                'slug' => $slug,
                'recipe_name' => $recipeName,
                'recipe_id' => $recipe['recipe_id'],
                'layout' => $layoutName,
            ]);
            $basePage['recipe_snapshot'] = $recipe;
            $basePage['navigation'] = $navigation;
            $basePage['issues'] = [];
        }
        unset($basePage);

        $basePlan['valid'] = true;
        return $basePlan;
    }

    private function applyResolvedWrappers(
        array $expectedInstances,
        mixed $submittedInstances,
        array $availableWrappers,
        string $source
    ): array {
        if (!is_array($submittedInstances)) {
            $submittedInstances = $expectedInstances;
        }
        if (count($submittedInstances) !== count($expectedInstances)) {
            throw new \RuntimeException(
                "Resolved {$source} instances no longer match the source declaration."
            );
        }

        foreach ($expectedInstances as $index => &$expected) {
            $submitted = $submittedInstances[$index] ?? null;
            if (!is_array($submitted)) {
                throw new \RuntimeException("Invalid resolved {$source} instance #{$index}.");
            }

            $wrapper = trim((string)($submitted['wrapper'] ?? $expected['wrapper'] ?? ''));
            if ($wrapper === '' || !array_key_exists($wrapper, $availableWrappers)) {
                throw new \RuntimeException("Resolved wrapper does not exist: {$wrapper}.");
            }

            // Plugin name, parameters and skip state always come from the server-side source.
            $expected['wrapper'] = $wrapper;
        }
        unset($expected);

        return $expectedInstances;
    }

    private function resolvePresetInstances(
        array $wrappers,
        array $availableWrappers,
        string $pluginName,
        array &$issues
    ): array {
        $instances = [];
        foreach ($wrappers as $wrapper => $wrapperInstances) {
            if (!array_key_exists($wrapper, $availableWrappers)) {
                $issues[] = $this->issue(
                    'missing_wrapper',
                    'error',
                    ['requested' => $wrapper, 'source' => 'preset']
                );
            }

            foreach ($wrapperInstances as $instance) {
                if (!is_array($instance)) {
                    continue;
                }

                $params = is_array($instance['instance_params'] ?? null)
                    ? $instance['instance_params']
                    : [];
                if (isset($instance['handler'])) {
                    $params['handler'] = (string)$instance['handler'];
                }

                $instances[] = [
                    'wrapper' => $wrapper,
                    'plugin' => $pluginName,
                    'instance_params' => $params,
                    'skip' => false,
                    'requested' => $instance,
                ];
            }
        }

        return $instances;
    }

    private function loadPluginManifest(string $pluginName): array
    {
        $path = ROOT_PATH . "plugins/{$pluginName}/install/manifest.json";
        if (!is_file($path)) {
            throw new \OutOfBoundsException("Plugin package not found: {$pluginName}.");
        }

        $bundle = JsonTool::loadFile($path, true);
        if (($bundle['type'] ?? null) !== 'plugin' || !is_array($bundle['data'] ?? null)) {
            throw new \RuntimeException("Invalid plugin manifest: {$pluginName}.");
        }

        return $bundle['data'];
    }

    private function validatePluginName(string $pluginName): string
    {
        $pluginName = trim($pluginName);
        if (!preg_match('/^[A-Za-z0-9][A-Za-z0-9_-]*$/', $pluginName)) {
            throw new \InvalidArgumentException('Invalid plugin system name.');
        }
        return $pluginName;
    }

    private function pageManager(): \Plugins\PageManager\PageManager
    {
        $manager = $this->plugins->get('PageManager');
        if (!$manager instanceof \Plugins\PageManager\PageManager) {
            throw new \RuntimeException('PageManager plugin is required by PluginManager.');
        }
        return $manager;
    }

    private function navigation(): \Plugins\Navigation\Navigation
    {
        $navigation = $this->plugins->get('Navigation');
        if (!$navigation instanceof \Plugins\Navigation\Navigation) {
            throw new \RuntimeException('Navigation plugin is required by PluginManager.');
        }
        return $navigation;
    }

    private function createHistory(array $data): int
    {
        $created = \DB::insert('pm_setup_history', [
            'plugin_id' => $data['plugin_id'] ?? null,
            'plugin_system_name' => (string)$data['plugin_system_name'],
            'domain_id' => $data['domain_id'] ?? null,
            'action' => (string)$data['action'],
            'status' => (string)$data['status'],
            'preset_name' => $data['preset_name'] ?? null,
            'config' => \Core\Utils\JsonTool::encode(
                is_array($data['config'] ?? null) ? $data['config'] : []
            , false),
            'error' => $data['error'] ?? null,
        ], 'setup_id');

        if (!is_numeric($created)) {
            throw new \RuntimeException('Failed to record PluginManager history.');
        }
        return (int)$created;
    }

    private function recordResource(int $setupId, array $resource): void
    {
        $result = \DB::insert('pm_setup_resources', [
            'setup_id' => $setupId,
            'resource_key' => (string)$resource['resource_key'],
            'resource_type' => (string)$resource['resource_type'],
            'resource_id' => $resource['resource_id'] ?? null,
            'resource_uuid' => $resource['resource_uuid'] ?? null,
            'ownership' => (string)($resource['ownership'] ?? 'created'),
            'recipe_id' => $resource['recipe_id'] ?? null,
            'recipe_snapshot' => isset($resource['recipe_snapshot'])
                ? \Core\Utils\JsonTool::encode(is_array($resource['recipe_snapshot']) ? $resource['recipe_snapshot'] : [], false)
                : null,
            'config' => \Core\Utils\JsonTool::encode(
                is_array($resource['config'] ?? null) ? $resource['config'] : []
            , false),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        if ($result === false) {
            throw new \RuntimeException('Failed to record setup resource.');
        }
    }

    private function runLifecycleAction(
        string $action,
        string $pluginReference,
        callable $operation
    ): bool {
        $pluginName = $pluginReference;
        if (in_array($action, ['install', 'update'], true)) {
            try {
                $pluginName = (string)$this->loadPluginManifest($pluginReference)['info']['name'];
            } catch (\Throwable) {
                $pluginName = $pluginReference;
            }
        }

        try {
            $result = (bool)$operation();
            $pluginId = \DB::getOne(
                'select plugin_id from plugins where system_name=$1',
                [$pluginName]
            );
            $this->createHistory([
                'plugin_id' => $pluginId ? (int)$pluginId : null,
                'plugin_system_name' => $pluginName,
                'action' => $action,
                'status' => $result ? 'success' : 'failed',
                'config' => ['reference' => $pluginReference],
                'error' => $result ? null : 'ExtensionManager returned false.',
            ]);
            return $result;
        } catch (\Throwable $e) {
            $pluginId = \DB::getOne(
                'select plugin_id from plugins where system_name=$1',
                [$pluginName]
            );
            $this->createHistory([
                'plugin_id' => $pluginId ? (int)$pluginId : null,
                'plugin_system_name' => $pluginName,
                'action' => $action,
                'status' => 'failed',
                'config' => ['reference' => $pluginReference],
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    private function pluginCatalog(): array
    {
        $installed = [];
        $rows = \DB::query(
            'select p.plugin_id, p.uuid, p.system_name, p.plugin_prefix,
                p.plugin_version, p.is_active,
                count(pd.domain_id) as domain_count
            from plugins p
            left join plugin_domains pd using(plugin_id)
            group by p.plugin_id'
        );
        while ($row = \DB::fetchRow($rows)) {
            $installed[(string)$row['system_name']] = $row;
        }

        $catalog = [];
        foreach (scandir(ROOT_PATH . 'plugins') ?: [] as $folder) {
            if ($folder === '.' || $folder === '..') {
                continue;
            }
            $manifestPath = ROOT_PATH . "plugins/{$folder}/install/manifest.json";
            if (!is_file($manifestPath)) {
                continue;
            }

            try {
                $manifest = $this->loadPluginManifest($folder);
            } catch (\Throwable) {
                continue;
            }

            $systemName = trim((string)($manifest['info']['name'] ?? ''));
            if ($systemName === '') {
                continue;
            }
            $row = $installed[$systemName] ?? null;
            $packageVersion = trim((string)($manifest['version'] ?? ''));
            $installedVersion = trim((string)($row['plugin_version'] ?? ''));
            $translation = $row ? \Core\Translation::get((string)$row['uuid']) : [];

            $catalog[$systemName] = [
                'system_name' => $systemName,
                'folder' => $folder,
                'title' => (string)($translation['title']
                    ?? $manifest['info']['title']
                    ?? $systemName),
                'description' => (string)($translation['description']
                    ?? $manifest['info']['description']
                    ?? ''),
                'package_version' => $packageVersion,
                'installed_version' => $installedVersion,
                'category' => $this->normalizeCategory($manifest['category'] ?? null),
                'installed' => $row !== null,
                'active' => !empty($row['is_active']),
                'domain_count' => (int)($row['domain_count'] ?? 0),
                'has_setup' => is_file(ROOT_PATH . "plugins/{$folder}/install/setup.json"),
                'update_available' => $row !== null
                    && $packageVersion !== ''
                    && ($installedVersion === '' || version_compare($packageVersion, $installedVersion, '>')),
            ];
            unset($installed[$systemName]);
        }

        // Keep installed packages visible even if their source directory is missing.
        foreach ($installed as $systemName => $row) {
            $translation = \Core\Translation::get((string)$row['uuid']) ?? [];
            $catalog[$systemName] = [
                'system_name' => $systemName,
                'folder' => null,
                'title' => (string)($translation['title'] ?? $systemName),
                'description' => (string)($translation['description'] ?? ''),
                'package_version' => '',
                'installed_version' => (string)($row['plugin_version'] ?? ''),
                'category' => 'others',
                'installed' => true,
                'active' => !empty($row['is_active']),
                'domain_count' => (int)($row['domain_count'] ?? 0),
                'has_setup' => false,
                'update_available' => false,
            ];
        }

        $sorted = \Core\Translation::sortByTitle(array_values($catalog));
        $groups = [];
        foreach ($sorted as $plugin) {
            $groups[$plugin['category']][] = $plugin;
        }

        uksort($groups, function (string $a, string $b): int {
            if ($a === 'others') {
                return $b === 'others' ? 0 : 1;
            }
            if ($b === 'others') {
                return -1;
            }
            return strcasecmp($a, $b);
        });

        return array_merge(...array_values($groups));
    }

    private function normalizeCategory(mixed $category): string
    {
        $category = strtolower(trim((string)$category));
        return preg_match('/^[a-z][a-z0-9_-]*$/', $category) ? $category : 'others';
    }

    private function categoryTitle(string $category): string
    {
        $phraseKey = 'category_' . $category;
        if (isset($this->phrases[$phraseKey]) && is_string($this->phrases[$phraseKey])) {
            return $this->phrases[$phraseKey];
        }

        return ucwords(str_replace(['-', '_'], ' ', $category));
    }

    private function pluginManifestSettings(string $pluginName): array
    {
        $folder = $this->packageFolderForPlugin($pluginName);
        if ($folder === null) {
            return [];
        }
        $manifest = $this->loadPluginManifest($folder);
        return is_array($manifest['settings'] ?? null) ? $manifest['settings'] : [];
    }

    private function packageFolderForPlugin(string $pluginName): ?string
    {
        foreach (scandir(ROOT_PATH . 'plugins') ?: [] as $folder) {
            if ($folder === '.' || $folder === '..') {
                continue;
            }
            $path = ROOT_PATH . "plugins/{$folder}/install/manifest.json";
            if (!is_file($path)) {
                continue;
            }
            try {
                $manifest = $this->loadPluginManifest($folder);
            } catch (\Throwable) {
                continue;
            }
            if ((string)($manifest['info']['name'] ?? '') === $pluginName) {
                return $folder;
            }
        }
        return null;
    }

    private function requestedPluginName(): string
    {
        return $this->validatePluginName((string)$this->param('plugin', ''));
    }

    private function setDomainActivation(string $pluginName, array $domainIds): void
    {
        $plugin = \DB::getRow(
            'select plugin_id from plugins where system_name=$1',
            [$pluginName]
        );
        if (!$plugin) {
            throw new \OutOfBoundsException("Plugin is not installed: {$pluginName}.");
        }

        $domainIds = array_values(array_unique(array_filter(
            array_map('intval', $domainIds),
            static fn(int $id): bool => $id > 0
        )));
        if ($domainIds !== []) {
            $valid = \DB::getArr(
                'select domain_id from domains where domain_id=ANY($1::int[])',
                ['{' . implode(',', $domainIds) . '}']
            );
            sort($valid);
            $expected = $domainIds;
            sort($expected);
            if (array_map('intval', $valid) !== $expected) {
                throw new \InvalidArgumentException('Unknown domain in activation request.');
            }
        }

        $pluginId = (int)$plugin['plugin_id'];
        \Core\EndpointRegistry::assertAvailableForPlugin($pluginId, $domainIds);

        $oldDomains = array_map('intval', \DB::getArr(
            'select domain_id from plugin_domains where plugin_id=$1',
            [$pluginId]
        ));

        if (!\DB::beginTransaction()) {
            throw new \RuntimeException('Failed to start plugin activation transaction.');
        }
        try {
            foreach ($domainIds as $domainId) {
                if (\DB::query(
                    'insert into plugin_domains(plugin_id, domain_id, local_settings)
                    values($1, $2, $3)
                    on conflict(plugin_id, domain_id) do nothing',
                    [$pluginId, $domainId, '{}']
                ) === false) {
                    throw new \RuntimeException('Failed to activate plugin on domain.');
                }
            }

            if ($domainIds === []) {
                \DB::delete('plugin_domains', 'plugin_id=$1', [$pluginId]);
            } else {
                \DB::query(
                    'delete from plugin_domains
                    where plugin_id=$1 and not (domain_id=ANY($2::int[]))',
                    [$pluginId, '{' . implode(',', $domainIds) . '}']
                );
            }

            if (\DB::update(
                'plugins',
                ['is_active' => $domainIds !== []],
                'plugin_id=$1',
                [$pluginId]
            ) === false) {
                throw new \RuntimeException('Failed to update plugin active state.');
            }

            if (!\DB::commit()) {
                throw new \RuntimeException('Failed to commit plugin activation.');
            }
        } catch (\Throwable $e) {
            \DB::rollBack();
            throw $e;
        }

        $affectedDomains = array_values(array_unique([...$oldDomains, ...$domainIds]));
        foreach ($affectedDomains as $domainId) {
            \Cache::del('d_' . $domainId . ':plugins');
            \Cache::del('d_' . $domainId . ':plugin:' . $pluginName);
        }
        \Core\EndpointRegistry::invalidateDomains($affectedDomains);
    }

    private function savePluginSettings(
        string $pluginName,
        array $submittedGlobals,
        int $domainId,
        array $submittedLocals
    ): void {
        $plugin = \DB::getRow(
            'select plugin_id, settings
            from plugins where system_name=$1',
            [$pluginName]
        );
        if (!$plugin) {
            throw new \OutOfBoundsException("Plugin is not installed: {$pluginName}.");
        }

        $structure = $this->pluginManifestSettings($pluginName);
        $baseSettings = \Core\Utils\JsonTool::decodeArray($plugin['settings'] ?? null);
        foreach ($structure as $name => $setting) {
            if (!is_string($name) || !is_array($setting) || !$this->isGlobalSetting($setting)) {
                continue;
            }
            if (array_key_exists($name, $submittedGlobals)) {
                $baseSettings[$name] = $this->normalizeSubmittedSetting(
                    $submittedGlobals[$name],
                    $setting,
                    $baseSettings[$name] ?? null
                );
            }
        }

        if (\DB::update(
            'plugins',
            ['settings' => \Core\Utils\JsonTool::encode($baseSettings, false)],
            'plugin_id=$1',
            [(int)$plugin['plugin_id']]
        ) === false) {
            throw new \RuntimeException('Failed to save plugin settings.');
        }

        if ($domainId > 0) {
            $domain = \DB::getRow(
                'select local_settings from plugin_domains where plugin_id=$1 and domain_id=$2',
                [(int)$plugin['plugin_id'], $domainId]
            );
            if (!$domain) {
                throw new \RuntimeException('Plugin is not active on selected domain.');
            }
            $locals = \Core\Utils\JsonTool::decodeArray($domain['local_settings'] ?? null);
            foreach ($structure as $name => $setting) {
                if (!is_string($name) || !is_array($setting) || $this->isGlobalSetting($setting)) {
                    continue;
                }
                if (array_key_exists($name, $submittedLocals)) {
                    $locals[$name] = $this->normalizeSubmittedSetting(
                        $submittedLocals[$name],
                        $setting,
                        $this->effectiveSettingValue(
                            $locals[$name] ?? null,
                            $baseSettings[$name] ?? null,
                            $setting,
                            array_key_exists($name, $locals)
                        )
                    );
                }
            }
            if (\DB::update(
                'plugin_domains',
                ['local_settings' => \Core\Utils\JsonTool::encode($locals, false)],
                'plugin_id=$1 and domain_id=$2',
                [(int)$plugin['plugin_id'], $domainId]
            ) === false) {
                throw new \RuntimeException('Failed to save domain plugin settings.');
            }
        }

        $domains = \DB::getArr(
            'select domain_id from plugin_domains where plugin_id=$1',
            [(int)$plugin['plugin_id']]
        );
        foreach ($domains as $activeDomainId) {
            \Cache::del('d_' . (int)$activeDomainId . ':plugin:' . $pluginName);
            \Cache::del('d_' . (int)$activeDomainId . ':plugins');
        }
    }

    private function normalizeSubmittedSetting(
        mixed $value,
        array $setting,
        mixed $existingValue = null
    ): mixed {
        if (empty($setting['translatable'])) {
            return $this->normalizeSettingValue($value, $setting);
        }

        $normalized = is_array($existingValue) ? $existingValue : [];
        $submitted = is_array($value)
            ? $value
            : [(defined('LANG') ? LANG : 'en') => $value];

        foreach ($submitted as $lang => $langValue) {
            $lang = strtolower(trim((string)$lang));
            if ($lang === '' || !preg_match('/^[a-z0-9_-]+$/', $lang)) {
                continue;
            }
            $normalized[$lang] = $this->normalizeSettingValue($langValue, $setting);
        }

        return $normalized;
    }

    private function normalizeSettingValue(mixed $value, array $setting): mixed
    {
        if (empty($setting['multiple'])) {
            return $this->normalizeSingleSettingValue($value, $setting);
        }

        $values = is_array($value) ? $value : [$value];
        $normalized = [];
        foreach ($values as $item) {
            $item = $this->normalizeSingleSettingValue($item, $setting);
            if ($item === null || $item === '') {
                continue;
            }
            $normalized[] = $item;
        }

        return $normalized;
    }

    private function normalizeSingleSettingValue(mixed $value, array $setting): mixed
    {
        $type = strtolower((string)($setting['type'] ?? 'string'));
        return match ($type) {
            'checkbox', 'boolean', 'yesno' => !empty($value) ? 1 : 0,
            'integer', 'domain_id', 'page_id', 'content_type_id', 'field_id',
            'field_type_id', 'item_id', 'plugin_id', 'user_id', 'usergroup_id' => $value === '' ? null : (int)$value,
            'number', 'decimal' => $value === '' ? null : (float)$value,
            default => is_scalar($value) || $value === null ? $value : $value,
        };
    }

    private function settingField(
        string $name,
        array $structure,
        mixed $value,
        mixed $translation
    ): array {
        $translated = is_array($translation) && is_array($translation[$name] ?? null)
            ? $translation[$name]
            : [];
        $field = $structure;
        $field['name'] = $name;
        $field['title'] = (string)($translated['title'] ?? $structure['title'] ?? $name);
        $field['description'] = (string)(
            $translated['description'] ?? $structure['description'] ?? ''
        );
        $field['value'] = $value ?? $structure['default'] ?? '';
        return $field;
    }

    private function renderSettingFields(
        string $name,
        array $structure,
        mixed $value,
        mixed $translation,
        string $scope,
        array $languages
    ): string {
        if (empty($structure['translatable'])) {
            $field = $this->settingField($name, $structure, $value, $translation);
            $field['name'] = $scope . '[' . $name . ']';
            return $this->forms()->renderField($field);
        }

        $languages = $languages !== []
            ? $languages
            : [defined('LANG') ? LANG : 'en'];
        $html = '';

        foreach ($languages as $lang) {
            $lang = strtolower(trim((string)$lang));
            if ($lang === '') {
                continue;
            }

            $langValue = $this->settingLanguageValue(
                $value,
                $structure['default'] ?? null,
                $lang
            );
            $fieldStructure = $structure;
            $fieldStructure['default'] = $this->settingLanguageValue(
                $structure['default'] ?? null,
                null,
                $lang
            );
            $field = $this->settingField($name, $fieldStructure, $langValue, $translation);
            $field['name'] = $scope . '[' . $name . '][' . $lang . ']';
            $field['title'] .= ' (' . strtoupper($lang) . ')';
            $html .= $this->forms()->renderField($field);
        }

        return $html;
    }

    private function effectiveSettingValue(
        mixed $localValue,
        mixed $baseValue,
        array $structure,
        bool $hasLocalValue
    ): mixed {
        if (!$hasLocalValue) {
            return $baseValue;
        }
        if (empty($structure['translatable']) || !is_array($localValue)) {
            return $localValue;
        }

        return array_replace(is_array($baseValue) ? $baseValue : [], $localValue);
    }

    private function settingLanguageValue(mixed $value, mixed $fallback, string $lang): mixed
    {
        if (!is_array($value)) {
            if ($value !== null) {
                return $value;
            }
            return $fallback !== null
                ? $this->settingLanguageValue($fallback, null, $lang)
                : null;
        }
        if (array_key_exists($lang, $value)) {
            return $value[$lang];
        }

        $defaultLanguage = (string)(DOMAIN_CONFIG['default_language'] ?? '');
        if ($defaultLanguage !== '' && array_key_exists($defaultLanguage, $value)) {
            return $value[$defaultLanguage];
        }
        if ($value !== []) {
            return reset($value);
        }

        return $fallback !== null
            ? $this->settingLanguageValue($fallback, null, $lang)
            : null;
    }

    private function settingLanguages(?int $domainId = null): array
    {
        $languages = [];
        $configs = $domainId !== null && $domainId > 0
            ? [\DB::getOne('select domain_config from domains where domain_id=$1', [$domainId])]
            : \DB::getArr('select domain_config from domains');

        foreach ($configs as $configJson) {
            $config = \Core\Utils\JsonTool::decodeArray($configJson);
            if (!is_array($config) || !is_array($config['languages'] ?? null)) {
                continue;
            }
            foreach ($config['languages'] as $lang) {
                $lang = strtolower(trim((string)$lang));
                if ($lang !== '') {
                    $languages[$lang] = true;
                }
            }
        }

        if ($languages === [] && defined('DOMAIN_CONFIG') && is_array(DOMAIN_CONFIG['languages'] ?? null)) {
            foreach (DOMAIN_CONFIG['languages'] as $lang) {
                $lang = strtolower(trim((string)$lang));
                if ($lang !== '') {
                    $languages[$lang] = true;
                }
            }
        }

        return array_keys($languages);
    }

    private function hasLocalSettings(array $structure): bool
    {
        foreach ($structure as $setting) {
            if (is_array($setting) && !$this->isGlobalSetting($setting)) {
                return true;
            }
        }
        return false;
    }

    private function isGlobalSetting(array $structure): bool
    {
        return !empty($structure['is_global'] ?? $structure['global'] ?? false);
    }

    private function pluginTitle(array $plugin): string
    {
        $translation = \Core\Translation::get((string)$plugin['uuid']) ?? [];
        return (string)($translation['title'] ?? $plugin['system_name']);
    }

    private function forms(): \Plugins\Forms\Forms
    {
        $forms = $this->plugins->get('Forms');
        if (!$forms instanceof \Plugins\Forms\Forms) {
            throw new \RuntimeException('Forms plugin is required by PluginManager.');
        }
        return $forms;
    }

    private function managerUrl(string $action = 'list', array $params = []): string
    {
        $url = \Core\Url::path((string) PAGE_SLUG);
        if ($action !== 'list') {
            $url = \Core\Url::pathParams(
                $url,
                ['action' => $action],
                $this->prefix
            );
        }

        return \Core\Url::pathParams($url, $params);
    }

    private function notice(string $message, string $kind = 'success'): string
    {
        return '<div class="kc-notice kc-notice-' . \Core\Html::escape($kind) . '">'
            . \Core\Html::escape($message) . '</div>';
    }

    private function jsonResponse(array $data, int $status = 200): string
    {
        \Core\Response::addHeader(
            'Content-Type: application/json; charset=utf-8',
            true,
            $status
        );
        return \Core\Utils\JsonTool::encode($data, false);
    }

    private function jsonForHtml(array $data): string
    {
        return \Core\Utils\JsonTool::encodeForHtml($data);
    }

private function jsString(string $value): string
    {
        return \Core\Html::escape(\Core\Utils\JsonTool::encodeForHtml($value));
    }

    private function assertManagerAccess(): void
    {
        $user = defined('USERDATA') && is_array(USERDATA)
            ? USERDATA
            : \Core\User::getUser();
        $rootGroupId = (int)(GLOBAL_SETTINGS['usergroup_root'] ?? -1);

        if ((int)($user['usergroup_id'] ?? 0) !== $rootGroupId) {
            throw new \RuntimeException('PluginManager access denied.');
        }
    }

    private function suggestPageSystemName(int $domainId, string $systemName): string
    {
        $candidate = $systemName;
        $suffix = 2;
        while (\DB::getOne(
            'select 1 from pages where domain_id=$1 and system_name=$2',
            [$domainId, $candidate]
        )) {
            $candidate = $systemName . '_' . $suffix++;
        }
        return $candidate;
    }

    private function suggestPageSlug(int $domainId, string $slug): string
    {
        $candidate = $slug;
        $suffix = 2;
        while (\DB::getOne(
            'select 1 from pages where domain_id=$1 and page_slug=$2',
            [$domainId, $candidate]
        )) {
            $candidate = $slug . '_' . $suffix++;
        }
        return $candidate;
    }

    private function issue(string $code, string $severity, array $data = []): array
    {
        return array_merge([
            'code' => $code,
            'severity' => $severity,
        ], $data);
    }


}
