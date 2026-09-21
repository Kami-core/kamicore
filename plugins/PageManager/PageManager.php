<?php

/**
 * KamiCore
 *
 * SPDX-License-Identifier: Apache-2.0
 *
 * @see https://kamicore.org
 */

namespace Plugins\PageManager;

if(!IN_KAMI) die();

class PageManager extends \Core\BasePlugin {

	private const RECIPE_KEY_PATTERN = '/^[a-z][a-z0-9-]*$/';

	private ?\Plugins\Forms\Forms $forms = null;

	public function list():string {
		$this->addCss('/plugins/PageManager/assets/page-manager.css');
		$this->forms()->registerEntityFieldAssets();
		// Domain selection is rendered client-side, but this path parameter belongs to the list route.
		$this->param('domainId');

		$domains = [];
		$domains[] = [
				"label" => $this->phrases['select_domain'] ?? "Select domain...",
				"value" => "",
		];

		$domain_rows = \DB::query("select domain_id, domain_name from domains order by domain_name");

		while ($domain = \DB::fetchRow($domain_rows)) {
			if (
				!\Core\User::isRoot()
				&& !\Core\User::canPlugin((int)$this->id, 'manage')
			) {
				continue;
			}
			$domains[] = [
				"label" => $domain['domain_name'],
				"value" => $domain['domain_id'],
			];
		}

		$domain_select = $this->forms()->renderField([
			'name' => 'domain-select',
			'id' => 'domain-select',
			'type' => 'select',
			'template' => 'control-select',
			'attributes' => ['class' => 'admin-input'],
			'options' => $domains,
		]);

		$data['domain_select'] = $domain_select;
		$data['create_action'] = $this->managerUrl('createPage');
		$data['recipe_create_action'] = $this->managerUrl('createPageFromRecipe');
		$data['page_route'] = \Core\Utils\JsonTool::encodeForHtml($this->managerUrl());

		$recipes = $this->getRecipes();
		$recipeOptions = [[
			'label' => $this->phrases['select_recipe'] ?? 'Select recipe…',
			'value' => '',
		]];
		$recipeMeta = [];
		foreach ($recipes as $recipe) {
			$recipeOptions[] = [
				'label' => (string)$recipe['name'] . ' (' . (string)$recipe['recipe_key'] . ')',
				'value' => (string)$recipe['recipe_key'],
			];
			$recipeMeta[(string)$recipe['recipe_key']] = [
				'page_prefix' => (string)$recipe['payload']['page_prefix'],
				'layout' => (string)$recipe['payload']['layout'],
			];
		}

		$data['recipe_select'] = $this->forms()->renderField([
			'name' => 'recipe_key',
			'id' => 'page-recipe-key',
			'type' => 'select',
			'template' => 'control-select',
			'attributes' => ['class' => 'admin-input'],
			'options' => $recipeOptions,
		]);
		$data['recipe_meta'] = \Core\Utils\JsonTool::encodeForHtml($recipeMeta);
		$data['recipe_create_disabled'] = $recipes === [] ? ' disabled' : '';
		$data['can_create_recipe'] = \Core\Utils\JsonTool::encodeForHtml(\Core\User::isRoot());
		$data['recipe_tools'] = \Core\User::isRoot()
			? '<a class="admin-button admin-button-secondary" href="'
				. $this->managerUrl('recipes') . '">'
				. '<svg class="icon icon-settings icon-sm"></svg><span>'
				. \Core\Html::escape($this->phrases['page_recipes'] ?? 'Page recipes')
				. '</span></a>'
			: '';

		$data['ui_text'] = \Core\Utils\JsonTool::encodeForHtml([
			'selectDomain' => $this->phrases['select_domain_to_view_pages'] ?? 'Select a domain to view its pages',
			'noDomain' => $this->phrases['no_domain_selected'] ?? 'No domain selected',
			'noPages' => $this->phrases['no_pages'] ?? 'No pages found.',
			'loading' => $this->phrases['loading_pages'] ?? 'Loading pages…',
			'loadFailed' => $this->phrases['load_pages_failed'] ?? 'Failed to load pages.',
			'retry' => $this->phrases['retry'] ?? 'Retry',
			'domainUnavailable' => $this->phrases['domain_unavailable'] ?? 'Domain is not available',
			'editPage' => $this->phrases['edit_page'] ?? 'Edit page',
			'deletePage' => $this->phrases['delete_page'] ?? 'Delete page',
			'createRecipeFromPage' => $this->phrases['create_recipe_from_page'] ?? 'Create recipe from page',
			'deleteConfirm' => $this->phrases['delete_page_confirm'] ?? 'Delete “{title}”? This action cannot be undone.',
			'deleteFailed' => $this->phrases['delete_page_failed'] ?? 'Failed to delete the page.',
			'recipePrefix' => $this->phrases['recipe_page_prefix'] ?? 'URL prefix: {prefix}',
			'recipeNoPrefix' => $this->phrases['recipe_no_page_prefix'] ?? 'No URL prefix',
			'pageCount' => [
				'one' => $this->phrases['page_count_one'] ?? '{count} page',
				'few' => $this->phrases['page_count_few'] ?? '{count} pages',
				'many' => $this->phrases['page_count_many'] ?? '{count} pages',
				'other' => $this->phrases['page_count_other'] ?? '{count} pages',
			],
		]);

		$content = $this->render("pages", $data);

		return $content;
	}

	public function edit($context_vars) {
		$this->addCss('/plugins/PageManager/assets/page-manager.css');
		$data = \Core\Request::all();
		$pageId = (int)$this->param('pageId', $data['pgm-pageId'] ?? 0);
		$page = \DB::getRow(
			"select p.*, l.system_name AS layout_system_name, l.uuid AS layout_uuid, l.wrappers,
				t.system_name as theme_name
			from pages p
			left join theme_layouts l using(layout_id)
			left join themes t on t.theme_id=l.theme_id
			where p.page_id=$1",
			[$pageId]
		);

		if (!$page) {
			return '<div class="kc-notice kc-notice-error">Page not found.</div>';
		}

		$pageTranslation = \Core\Translation::get($page['uuid']) ?? [];
		$layoutTranslation = \Core\Translation::get($page['layout_uuid']) ?? [];
		$wrappers = \Core\Utils\JsonTool::decodeArray($page['wrappers'] ?? null);
		$this->sortWrappers($wrappers);

		$pageData = $page;
		$pageData['page_title'] = \Core\Html::escape((string)($pageTranslation['title']
				?? ucwords(str_replace(['_', '-'], ' ', $page['system_name']))));
		$pageData['page_slug'] = \Core\Html::escape((string)$page['page_slug']);
		$pageData['layout_title'] = \Core\Html::escape((string)($layoutTranslation['title'] ?? $page['layout_system_name'] ?? ''));
		$pageData['layout_field'] = $this->renderLayoutSelect(
			(int)$page['domain_id'],
			(int)$page['layout_id'],
			'page-layout-id'
		);
		$pageData['layout_preview'] = $this->loadLayoutPreview($page, $wrappers);
		$pageData['save_action'] = $this->managerUrl(
			'save',
			['pgm-pageId' => $pageId]
		);
		$pageData['back_url'] = $this->managerUrl(
			'list',
			['pgm-domainId' => (int)$page['domain_id']]
		);
		$pageData['back_label'] = \Core\Html::escape((string)($this->phrases['back_to_pages'] ?? 'Back to pages'));
		$pageData['cancel_url'] = $pageData['back_url'];
		$pageData['recipe_from_page_url'] = $this->managerUrl(
			'recipes',
			['pgm-pageId' => $pageId]
		);
		$pageData['recipe_from_page_hidden'] = \Core\User::isRoot() ? '' : ' hidden';
		$pageData['recipe_from_page_label'] = \Core\Html::escape(
			$this->phrases['create_recipe_from_page'] ?? 'Create recipe from page'
		);
		$pageData['recipe_from_page_hint'] = \Core\Html::escape(
			$this->phrases['recipe_from_saved_page_hint']
				?? 'Create a recipe draft from the saved state of this page.'
		);
		$pageData['layout_data_url'] = '/ajax/PageManager/pageLayoutData/pgm-pageId/' . $pageId;
		$pageData['parent_field'] = $this->forms()->renderField([
			'name' => 'parent_id',
			'id' => 'page-parent-id',
			'type' => 'page_id',
			'label' => $this->phrases['parent_page'] ?? 'Parent page',
			'placeholder' => $this->phrases['no_parent_page'] ?? 'No parent page',
			'value' => (int)($page['parent_id'] ?? 0) > 0 ? (int)$page['parent_id'] : '',
			'params' => [
				'domain_ids' => [(int)$page['domain_id']],
				'exclude_ids' => $this->pageDescendantIds($pageId),
			],
		]);

		$pageSettings = \Core\Utils\JsonTool::decodeArray($page['page_settings'] ?? null);
		$lifecycleSettings = is_array($pageSettings['lifecycle_plugins'] ?? null)
			? $pageSettings['lifecycle_plugins']
			: [];
		$lifecycleFieldParams = ['domain_ids' => [(int)$page['domain_id']]];
		$pageData['lifecycle_enable_field'] = $this->forms()->renderField([
			'name' => 'lifecycle_plugins_enable',
			'id' => 'page-lifecycle-plugins-enable',
			'type' => 'plugin_id',
			'label' => $this->phrases['lifecycle_plugins_enable'] ?? 'Enable lifecycle plugins',
			'value' => $lifecycleSettings['enable'] ?? [],
			'multiple' => true,
			'params' => $lifecycleFieldParams,
		]);
		$pageData['lifecycle_disable_field'] = $this->forms()->renderField([
			'name' => 'lifecycle_plugins_disable',
			'id' => 'page-lifecycle-plugins-disable',
			'type' => 'plugin_id',
			'label' => $this->phrases['lifecycle_plugins_disable'] ?? 'Disable lifecycle plugins',
			'value' => $lifecycleSettings['disable'] ?? [],
			'multiple' => true,
			'params' => $lifecycleFieldParams,
		]);
		$pageData['lifecycle_plugins_help'] = \Core\Html::escape($this->phrases['lifecycle_plugins_help']
				?? 'Page-level overrides for plugins initialized outside wrappers.');
		$pageData['builder_text'] = \Core\Utils\JsonTool::encodeForHtml([
			'dragHere' => $this->phrases['drag_plugins_here'] ?? 'Drag plugins here',
			'loadingLayout' => $this->phrases['loading_layout'] ?? 'Loading page layout…',
			'layoutLoaded' => $this->phrases['layout_loaded'] ?? 'Layout loaded',
			'layoutFailed' => $this->phrases['layout_load_failed'] ?? 'Failed to load the page layout.',
			'retry' => $this->phrases['retry'] ?? 'Retry',
			'unknownWrapper' => $this->phrases['unknown_wrapper'] ?? 'Unknown wrapper',
			'unplacedWrappers' => $this->phrases['unplaced_wrappers'] ?? 'Unplaced wrappers',
			'pluginSettings' => $this->phrases['plugin_settings'] ?? 'Plugin settings',
			'loadingPluginSettings' => $this->phrases['loading_plugin_settings'] ?? 'Loading plugin settings…',
			'pluginSettingsFailed' => $this->phrases['plugin_settings_load_failed'] ?? 'Failed to load plugin settings. Please try again.',
			'removePlugin' => $this->phrases['remove_plugin'] ?? 'Remove plugin',
			'movePlugin' => $this->phrases['move_plugin'] ?? 'Move plugin',
		]);

		$plugins = [];
		$pluginRows = \DB::query(
			"select p.uuid, p.system_name
			from plugin_domains pd
			left join plugins p using(plugin_id)
			where pd.domain_id=$1 and p.is_active",
			[(int)$page['domain_id']]
		);
		while ($plugin = \DB::fetchRow($pluginRows)) {
			$pluginTranslation = \Core\Translation::get($plugin['uuid']) ?? [];
			$plugins[] = [
				'template' => 'plugin',
				'params' => [
					'plugin_name' => \Core\Html::escape((string)$plugin['system_name']),
					'plugin_title' => \Core\Html::escape((string)($pluginTranslation['title'] ?? $plugin['system_name'])),
				]
			];
		}
		usort($plugins, static fn(array $a, array $b): int => \Core\Translation::compareTitles(
			html_entity_decode((string)($a['params']['plugin_title'] ?? ''), ENT_QUOTES, 'UTF-8'),
			html_entity_decode((string)($b['params']['plugin_title'] ?? ''), ENT_QUOTES, 'UTF-8')
		));
		$pageData['plugins'] = $plugins;

		return $this->render('page_edit', $pageData);
	}

	public function save($context_vars) {
		$data = \Core\Request::all();
		$pageId = (int)$this->param('pageId', $data['page_id'] ?? 0);
		$layout = \Core\Utils\JsonTool::decodeArray($data['layout_json'] ?? null);
		$pagePlugins = [];

		foreach ($layout as $wrapper) {
			if (!is_array($wrapper)) {
				continue;
			}

			$wrapperName = (string)($wrapper['wrapper'] ?? '');
			if ($wrapperName === '') {
				continue;
			}

			$wrapperPlugins = [];
			foreach (($wrapper['items'] ?? []) as $plugin) {
				if (!is_array($plugin)) {
					continue;
				}

				$pluginName = (string)($plugin['plugin'] ?? '');
				$instanceId = (int)str_replace('plugin_', '', (string)($plugin['instance_id'] ?? ''));
				if ($pluginName === '' || $instanceId < 1) {
					continue;
				}

				$pluginRow = \DB::getRow(
					'select config from plugins where system_name=$1',
					[$pluginName]
				);
				$config = \Core\Utils\JsonTool::decodeArray($pluginRow['config'] ?? null);
				$handlers = is_array($config['handlers'] ?? null) ? $config['handlers'] : [];
				$handlerName = (string)($data['plugin_handler'][$instanceId]
					?? $config['default_handler']
					?? array_key_first($handlers)
					?? '');
				$handler = is_array($handlers[$handlerName] ?? null)
					? $handlers[$handlerName]
					: [];
				$instanceParams = is_array($handler['instance_params'] ?? null)
					? $handler['instance_params']
					: [];

				$params = [
					'handler' => $handlerName,
				];

				foreach ($instanceParams as $name => $structure) {
					$value = $data['plugin_params'][$instanceId][$name]
						?? (is_array($structure) ? ($structure['default'] ?? null) : null);
					$params[$name] = $this->normalizeInstanceParamValue($value, $structure);
				}

				$wrapperPlugins[] = [$pluginName => $params];
			}

			$pagePlugins[$wrapperName] = $wrapperPlugins;
		}

		$page = \DB::getRow(
			'select uuid, domain_id, layout_id, page_settings from pages where page_id=$1',
			[$pageId]
		);
		if (!$page) {
			return '<div class="kc-notice kc-notice-error">Page not found.</div>';
		}

		try {
			$layoutId = (int)($data['layout_id'] ?? $page['layout_id']);
			$selectedLayout = $this->resolveLayoutById((int)$page['domain_id'], $layoutId);
			if (!$selectedLayout) {
				throw new \InvalidArgumentException(
					$this->phrases['invalid_layout'] ?? 'The selected layout is not available for this domain.'
				);
			}

			// Saving is the cleanup boundary for wrappers that do not exist in
			// the selected layout. Their instances remain available in the UI
			// as unplaced wrappers until the user saves the page.
			$declaredWrappers = \Core\Utils\JsonTool::decodeArray($selectedLayout['wrappers'] ?? null);
			$pagePlugins = array_intersect_key($pagePlugins, $declaredWrappers);

			$parentId = $this->normalizeParentPageId(
				$data['parent_id'] ?? null,
				(int)$page['domain_id'],
				$pageId
			);
			$lifecycleEnable = $this->normalizeLifecyclePluginIds(
				$data['lifecycle_plugins_enable'] ?? [],
				(int)$page['domain_id']
			);
			$lifecycleDisable = $this->normalizeLifecyclePluginIds(
				$data['lifecycle_plugins_disable'] ?? [],
				(int)$page['domain_id']
			);
			if (array_intersect($lifecycleEnable, $lifecycleDisable) !== []) {
				throw new \InvalidArgumentException(
					$this->phrases['lifecycle_plugins_conflict']
						?? 'A lifecycle plugin cannot be both enabled and disabled for the same page.'
				);
			}
		} catch (\InvalidArgumentException $error) {
			return $this->pageError($error->getMessage());
		}

		$pageSettings = \Core\Utils\JsonTool::decodeArray($page['page_settings'] ?? null);
		if ($lifecycleEnable === [] && $lifecycleDisable === []) {
			unset($pageSettings['lifecycle_plugins']);
		} else {
			$pageSettings['lifecycle_plugins'] = [
				'enable' => $lifecycleEnable,
				'disable' => $lifecycleDisable,
			];
		}

		$pluginsJson = \Core\Utils\JsonTool::encode($pagePlugins, false);
		$pageSettingsJson = \Core\Utils\JsonTool::encode($pageSettings, false);
		\DB::query(
			'update pages set page_slug=$1, parent_id=$2, layout_id=$3, page_plugins=$4, page_settings=$5::jsonb where page_id=$6',
			[
				(string)($data['page_slug'] ?? ''),
				$parentId,
				$layoutId,
				$pluginsJson,
				$pageSettingsJson,
				$pageId,
			]
		);

		$translation = \Core\Translation::get($page['uuid']) ?? [];
		$translation['title'] = (string)($data['page_title'] ?? '');
		$translationJson = \Core\Utils\JsonTool::encode($translation, false);
		\DB::query(
			"insert into translations(entity_uuid, lang_code, translated_data)
			values($1, $2, $3)
			on conflict (entity_uuid, lang_code)
			do update set translated_data=$3",
			[$page['uuid'], LANG, $translationJson]
		);
		\Cache::del("globals:{$page['uuid']}_" . LANG);
		\Cache::del('d_' . (int)$page['domain_id'] . ':page_' . $pageId);
		\Cache::del('d_' . (int)$page['domain_id'] . ':pages');

		if ($notifications = $this->plugins->get('Notifications')) {
			$notifications->store(
				\Core\User::getId(),
				$this->phrases['page_saved'] ?? 'Page saved.',
				'success'
			);
		}

		return \Core\Response::seeOther(
			'/' . PAGE_SLUG . '/pgm-action/edit/pgm-pageId/' . $pageId
		);
	}
	public function resolveLayout(int $domainId, string $layoutName): ?array {
		$layout = \DB::getRow(
			'select l.layout_id, l.uuid, l.system_name,
				l.layout_filename, l.wrappers, t.system_name as theme_name
			from domains d
			join theme_layouts l on l.theme_id=d.theme_id
			join themes t on t.theme_id=d.theme_id
			where d.domain_id=$1 and l.system_name=$2',
			[$domainId, $layoutName]
		);

		return $layout ?: null;
	}

	public function createPageFromPlan(array $page): array {
		$domainId = (int)($page['domain_id'] ?? 0);
		$systemName = trim((string)($page['system_name'] ?? ''));
		$slug = trim((string)($page['slug'] ?? $page['page_slug'] ?? ''));
		$layoutName = trim((string)($page['layout'] ?? $page['layout_name'] ?? ''));

		if ($domainId < 1) {
			throw new \InvalidArgumentException('Page domain_id is required.');
		}
		if ($systemName === '') {
			throw new \InvalidArgumentException('Page system_name is required.');
		}
		if ($slug === '') {
			throw new \InvalidArgumentException('Page slug is required.');
		}
		if ($layoutName === '') {
			throw new \InvalidArgumentException('Page layout is required.');
		}

		$parentId = $this->normalizeParentPageId(
			$page['parent_id'] ?? null,
			$domainId
		);

		if (\DB::getOne(
			'select 1 from pages where domain_id=$1 and system_name=$2',
			[$domainId, $systemName]
		)) {
			throw new \RuntimeException(
				"Page system_name already exists on domain {$domainId}: {$systemName}."
			);
		}

		if (\DB::getOne(
			'select 1 from pages where domain_id=$1 and page_slug=$2',
			[$domainId, $slug]
		)) {
			throw new \RuntimeException(
				"Page slug already exists on domain {$domainId}: {$slug}."
			);
		}

		$layout = $this->resolveLayout($domainId, $layoutName);
		if (!$layout) {
			throw new \RuntimeException(
				"Layout {$layoutName} is not available on domain {$domainId}."
			);
		}

		$wrappers = \Core\Utils\JsonTool::decodeArray($layout['wrappers'] ?? null);
		$pagePlugins = [];
		foreach ($wrappers as $wrapperName => $_wrapper) {
			$pagePlugins[(string)$wrapperName] = [];
		}

		$created = \DB::insert('pages', [
			'domain_id' => $domainId,
			'system_name' => $systemName,
			'page_slug' => $slug,
			'parent_id' => $parentId,
			'layout_id' => (int)$layout['layout_id'],
			'page_plugins' => \Core\Utils\JsonTool::encode($pagePlugins, false),
		], 'page_id, uuid');

		if (!is_array($created)) {
			throw new \RuntimeException('Failed to create page.');
		}

		$defaultLanguage = trim((string)($page['default_language'] ?? ''));
		if ($defaultLanguage === '') {
			$defaultLanguage = defined('LANG') ? LANG : 'en';
		}

		$titles = is_array($page['titles'] ?? null) ? $page['titles'] : [];
		$defaultTitle = trim((string)($page['title'] ?? ''));
		if ($defaultTitle === '') {
			$defaultTitle = $systemName;
		}
		if (!isset($titles[$defaultLanguage]) || trim((string)$titles[$defaultLanguage]) === '') {
			$titles[$defaultLanguage] = $defaultTitle;
		}

		foreach ($titles as $language => $title) {
			$language = trim((string)$language);
			$title = trim((string)$title);
			if ($language === '' || $title === '') {
				continue;
			}

			\DB::query(
				'insert into translations(entity_uuid, lang_code, translated_data)
				values($1, $2, $3)
				on conflict (entity_uuid, lang_code)
				do update set translated_data=excluded.translated_data',
				[
					$created['uuid'],
					$language,
					\Core\Utils\JsonTool::encode(['title' => $title], false),
				]
			);
			\Cache::del("globals:{$created['uuid']}_{$language}");
		}

		\Cache::del('d_' . $domainId . ':pages');

		return [
			'page_id' => (int)$created['page_id'],
			'uuid' => (string)$created['uuid'],
			'layout_id' => (int)$layout['layout_id'],
			'layout_name' => (string)$layout['system_name'],
		];
	}

	public function addPluginInstance(
		int $pageId,
		string $wrapper,
		string $pluginName,
		array $instanceParams = []
	): int {
		$page = \DB::getRow(
			'select p.page_plugins, l.wrappers
			from pages p
			join theme_layouts l using(layout_id)
			where p.page_id=$1',
			[$pageId]
		);
		if (!$page) {
			throw new \OutOfBoundsException("Unknown page: {$pageId}.");
		}

		$wrapper = trim($wrapper);
		if ($wrapper === '') {
			throw new \InvalidArgumentException('Wrapper name is required.');
		}

		$availableWrappers = \Core\Utils\JsonTool::decodeArray($page['wrappers'] ?? null);
		if (!array_key_exists($wrapper, $availableWrappers)) {
			throw new \RuntimeException(
				"Wrapper {$wrapper} does not exist on page {$pageId}."
			);
		}

		$pluginName = trim($pluginName);
		if ($pluginName === '') {
			throw new \InvalidArgumentException('Plugin system name is required.');
		}

		$pagePlugins = \Core\Utils\JsonTool::decodeArray($page['page_plugins'] ?? null);
		foreach ($availableWrappers as $wrapperName => $_definition) {
			$pagePlugins[(string)$wrapperName] = is_array($pagePlugins[$wrapperName] ?? null)
				? $pagePlugins[$wrapperName]
				: [];
		}

		$pagePlugins[$wrapper][] = [$pluginName => $instanceParams];
		$instanceIndex = count($pagePlugins[$wrapper]) - 1;

		if (\DB::update(
			'pages',
			['page_plugins' => \Core\Utils\JsonTool::encode($pagePlugins, false)],
			'page_id=$1',
			[$pageId]
		) === false) {
			throw new \RuntimeException("Failed to add plugin instance to page {$pageId}.");
		}

		return $instanceIndex;
	}

	public function getRecipes(): array {
		$recipes = [];
		$rows = \DB::query(
			'select recipe_id, recipe_uuid, recipe_key, name, description,
				payload, created_at, updated_at
			from pgm_recipes
			order by recipe_key'
		);

		while ($row = \DB::fetchRow($rows)) {
			$recipes[] = $this->prepareRecipe($row);
		}

		return $recipes;
	}

	public function getRecipe(string $recipeKey): ?array {
		$recipeKey = trim($recipeKey);
		if ($recipeKey === '') {
			return null;
		}

		$row = \DB::getRow(
			'select recipe_id, recipe_uuid, recipe_key, name, description,
				payload, created_at, updated_at
			from pgm_recipes
			where recipe_key=$1',
			[$recipeKey]
		);

		return $row ? $this->prepareRecipe($row) : null;
	}

	public function resolveRecipe(
		string $recipeKey,
		int $domainId,
		?string $layoutOverride = null
	): array {
		$recipeKey = trim($recipeKey);
		if ($domainId < 1 || !\DB::getOne('select 1 from domains where domain_id=$1', [$domainId])) {
			throw new \InvalidArgumentException('Valid recipe domain_id is required.');
		}

		$recipe = $this->getRecipe($recipeKey);
		if (!$recipe) {
			return [
				'valid' => false,
				'recipe' => null,
				'payload' => $this->normalizeRecipePayload([]),
				'layout' => null,
				'layout_name' => '',
				'available_wrappers' => [],
				'instances' => [],
				'navigation_menus' => [],
				'issues' => [[
					'code' => 'missing_recipe',
					'severity' => 'error',
					'requested' => $recipeKey,
				]],
			];
		}

		$payload = $recipe['payload'];
		$layoutName = trim((string)($layoutOverride ?? $payload['layout']));
		$layout = $layoutName !== '' ? $this->resolveLayout($domainId, $layoutName) : null;
		$issues = [];

		if ($layoutName === '' || !$layout) {
			$issues[] = [
				'code' => 'missing_layout',
				'severity' => 'error',
				'requested' => $layoutName,
			];
		}

		$availableWrappers = $layout
			? \Core\Utils\JsonTool::decodeArray($layout['wrappers'] ?? null)
			: [];
		$instances = [];

		foreach ($payload['wrappers'] as $wrapper => $wrapperInstances) {
			if (!array_key_exists($wrapper, $availableWrappers)) {
				$issues[] = [
					'code' => 'missing_wrapper',
					'severity' => 'error',
					'requested' => $wrapper,
					'source' => 'recipe',
				];
			}

			foreach ($wrapperInstances as $instance) {
				if (!is_array($instance)) {
					continue;
				}

				$pluginName = trim((string)($instance['plugin'] ?? ''));
				if ($pluginName === '') {
					$issues[] = [
						'code' => 'invalid_recipe_instance',
						'severity' => 'notice',
						'wrapper' => $wrapper,
					];
					continue;
				}

				$plugin = \DB::getRow(
					'select plugin_id, is_active from plugins where system_name=$1',
					[$pluginName]
				);
				$activeOnDomain = $plugin
					&& !empty($plugin['is_active'])
					&& \DB::getOne(
						'select 1 from plugin_domains where plugin_id=$1 and domain_id=$2',
						[(int)$plugin['plugin_id'], $domainId]
					);
				$skip = !$plugin || !$activeOnDomain;
				if ($skip) {
					$issues[] = [
						'code' => 'recipe_plugin_unavailable',
						'severity' => 'notice',
						'plugin' => $pluginName,
						'wrapper' => $wrapper,
						'installed' => (bool)$plugin,
						'active_on_domain' => (bool)$activeOnDomain,
					];
				}

				$params = is_array($instance['instance_params'] ?? null)
					? $instance['instance_params']
					: [];
				if (array_key_exists('handler', $instance)) {
					$params['handler'] = (string)$instance['handler'];
				}

				$instances[] = [
					'wrapper' => $wrapper,
					'plugin' => $pluginName,
					'instance_params' => $params,
					'skip' => $skip,
					'requested' => $instance,
				];
			}
		}

		$valid = true;
		foreach ($issues as $issue) {
			if (($issue['severity'] ?? '') === 'error') {
				$valid = false;
				break;
			}
		}

		return [
			'valid' => $valid,
			'recipe' => $recipe,
			'payload' => $payload,
			'layout' => $layout,
			'layout_name' => $layoutName,
			'available_wrappers' => $availableWrappers,
			'instances' => $instances,
			'navigation_menus' => $payload['default_navigation_menus'],
			'issues' => $issues,
		];
	}

	public function recipes(array $contextVars = []): string {
		$this->addCss('/plugins/PageManager/assets/page-manager.css');
		$this->assertRecipeManagerAccess();
		$requestedId = (int)$this->param('id', 0);
		$sourcePageId = (int)$this->param('pageId', 0);
		$editing = null;
		$draftNotice = '';

		if ($sourcePageId > 0) {
			$editing = $this->buildRecipeDraftFromPage($sourcePageId);
			$sourceTitle = (string)($editing['source_title'] ?? '');
			$notice = str_replace(
				'{title}',
				$sourceTitle,
				$this->phrases['recipe_draft_from_page'] ?? 'Draft generated from saved page “{title}”.'
			);
			$draftNotice = '<p class="admin-page-description pm-recipe-source">'
				. \Core\Html::escape($notice)
				. '</p>';
		} elseif ($requestedId > 0) {
			foreach ($this->getRecipes() as $recipe) {
				if ((int)$recipe['recipe_id'] === $requestedId) {
					$editing = $recipe;
					break;
				}
			}
		}

		$rows = [];
		foreach ($this->getRecipes() as $recipe) {
			$rows[] = [
				'template' => 'recipe-row',
				'params' => [
					'recipe_key' => \Core\Html::escape((string)$recipe['recipe_key']),
					'name' => \Core\Html::escape((string)$recipe['name']),
					'description' => \Core\Html::escape((string)($recipe['description'] ?? '')),
					'layout' => \Core\Html::escape((string)$recipe['payload']['layout']),
					'edit_url' => $this->managerUrl('recipes', ['pgm-id' => (int)$recipe['recipe_id']]),
					'delete_url' => $this->managerUrl('recipeDelete', ['pgm-id' => (int)$recipe['recipe_id']]),
				],
			];
		}

		$payload = $editing['payload'] ?? $this->normalizeRecipePayload([]);
		$editorTitle = $sourcePageId > 0
			? ($this->phrases['create_recipe_from_page'] ?? 'Create recipe from page')
			: ($this->phrases['edit_recipe'] ?? 'Edit recipe');

		return $this->render('recipes', [
			'recipe_rows' => $rows !== [] ? $rows : [[
				'template' => 'recipe-empty',
				'params' => [],
			]],
			'editor_title' => \Core\Html::escape($editorTitle),
			'draft_notice' => $draftNotice,
			'recipe_id' => (string)($editing['recipe_id'] ?? 0),
			'recipe_key' => \Core\Html::escape((string)($editing['recipe_key'] ?? '')),
			'name' => \Core\Html::escape((string)($editing['name'] ?? '')),
			'description' => \Core\Html::escape((string)($editing['description'] ?? '')),
			'payload' => \Core\Html::escape(\Core\Utils\JsonTool::encode($payload, true)),
			'save_action' => $this->managerUrl('recipeSave'),
			'back_url' => $this->managerUrl(),
		]);
	}

	public function recipeSave(array $contextVars = []): string {
		$this->assertRecipeManagerAccess();
		$data = \Core\Request::all();

		try {
			$payload = json_decode(
				(string)($data['payload'] ?? '{}'),
				true,
				512,
				JSON_THROW_ON_ERROR
			);
			if (!is_array($payload)) {
				throw new \InvalidArgumentException('Recipe payload must be a JSON object.');
			}

			$this->saveRecipe([
				'recipe_id' => (int)($data['recipe_id'] ?? 0),
				'recipe_key' => (string)($data['recipe_key'] ?? ''),
				'name' => (string)($data['name'] ?? ''),
				'description' => (string)($data['description'] ?? ''),
				'payload' => $payload,
			]);
		} catch (\Throwable $error) {
			return '<div class="kc-notice kc-notice-error">'
				. \Core\Html::escape($error->getMessage())
				. '</div>';
		}

		if ($notifications = $this->plugins->get('Notifications')) {
			$notifications->store(
				\Core\User::getId(),
				$this->phrases['recipe_saved'] ?? 'Recipe saved.',
				'success'
			);
		}

		return \Core\Response::seeOther($this->managerUrl('recipes'));
	}

	public function recipeDelete(array $contextVars = []): string {
		$this->assertRecipeManagerAccess();
		$recipeId = (int)$this->param('id', 0);
		if (!$this->deleteRecipe($recipeId)) {
			return '<div class="kc-notice kc-notice-error">Failed to delete recipe.</div>';
		}

		if ($notifications = $this->plugins->get('Notifications')) {
			$notifications->store(
				\Core\User::getId(),
				$this->phrases['recipe_deleted'] ?? 'Recipe deleted.',
				'success'
			);
		}

		return \Core\Response::redirect($this->managerUrl('recipes'));
	}

	public function saveRecipe(array $recipe): int {
		$this->assertRecipeManagerAccess();
		$recipeId = (int)($recipe['recipe_id'] ?? 0);
		$recipeKey = trim((string)($recipe['recipe_key'] ?? ''));
		$name = trim((string)($recipe['name'] ?? ''));
		$description = trim((string)($recipe['description'] ?? ''));
		$payload = $this->normalizeRecipePayload(
			is_array($recipe['payload'] ?? null) ? $recipe['payload'] : []
		);

		if (!preg_match(self::RECIPE_KEY_PATTERN, $recipeKey)) {
			throw new \InvalidArgumentException(
				'Recipe key must contain lowercase ASCII letters, digits and hyphens.'
			);
		}
		if ($name === '') {
			throw new \InvalidArgumentException('Recipe name is required.');
		}

		$duplicateId = \DB::getOne(
			'select recipe_id from pgm_recipes where recipe_key=$1',
			[$recipeKey]
		);
		if ($duplicateId && (int)$duplicateId !== $recipeId) {
			throw new \RuntimeException("Recipe key already exists: {$recipeKey}.");
		}

		$data = [
			'recipe_key' => $recipeKey,
			'name' => $name,
			'description' => $description !== '' ? $description : null,
			'payload' => \Core\Utils\JsonTool::encode($payload, false),
			'updated_at' => date('Y-m-d H:i:s'),
		];

		if ($recipeId > 0) {
			if (\DB::update('pgm_recipes', $data, 'recipe_id=$1', [$recipeId]) === false) {
				throw new \RuntimeException('Failed to update recipe.');
			}
			return $recipeId;
		}

		unset($data['updated_at']);
		$created = \DB::insert('pgm_recipes', $data, 'recipe_id');
		if (!is_numeric($created)) {
			throw new \RuntimeException('Failed to create recipe.');
		}

		return (int)$created;
	}

	public function deleteRecipe(int $recipeId): bool {
		$this->assertRecipeManagerAccess();
		if ($recipeId < 1) {
			return false;
		}

		return \DB::delete('pgm_recipes', 'recipe_id=$1', [$recipeId]) !== false;
	}

	public function createPageFromRecipe(array $contextVars = []): string {
		$data = \Core\Request::all();
		$domainId = (int)($data['domain_id'] ?? 0);
		$recipeKey = trim((string)($data['recipe_key'] ?? ''));
		$title = trim((string)($data['title'] ?? ''));
		$requestedSlug = trim((string)($data['slug'] ?? ''));

		if ($domainId < 1 || $recipeKey === '' || $title === '' || $requestedSlug === '') {
			return '<div class="kc-notice kc-notice-error">Recipe, domain, title and slug are required.</div>';
		}

		if (!\Core\User::isRoot() && !\Core\User::canPlugin((int)$this->id, 'manage')) {
			throw new \RuntimeException('PageManager access denied for selected domain.');
		}

		$resolved = $this->resolveRecipe($recipeKey, $domainId);
		if (!$resolved['valid']) {
			$errors = array_values(array_filter(
				$resolved['issues'],
				static fn(array $issue): bool => ($issue['severity'] ?? '') === 'error'
			));
			$message = $errors !== []
				? implode('; ', array_map(
					static fn(array $issue): string => (string)($issue['code'] ?? 'invalid_recipe'),
					$errors
				))
				: 'Recipe cannot be resolved.';
			return '<div class="kc-notice kc-notice-error">'
				. \Core\Html::escape($message)
				. '</div>';
		}

		$slug = (string)$resolved['payload']['page_prefix'] . ltrim($requestedSlug, '/');
		if ($slug === '') {
			$slug = '/';
		}
		$language = defined('LANG') ? LANG : (string)(DOMAIN_CONFIG['default_language'] ?? 'en');

		if (!\DB::beginTransaction()) {
			throw new \RuntimeException('Failed to start page recipe transaction.');
		}

		try {
			$page = $this->createPageFromPlan([
				'domain_id' => $domainId,
				'system_name' => $title,
				'slug' => $slug,
				'layout' => $resolved['layout_name'],
				'title' => $title,
				'titles' => [$language => $title],
				'default_language' => $language,
			]);

			foreach ($resolved['instances'] as $instance) {
				if (!is_array($instance) || !empty($instance['skip'])) {
					continue;
				}
				$this->addPluginInstance(
					(int)$page['page_id'],
					(string)$instance['wrapper'],
					(string)$instance['plugin'],
					is_array($instance['instance_params'] ?? null)
						? $instance['instance_params']
						: []
				);
			}

			if ($resolved['navigation_menus'] !== []) {
				$navigation = $this->plugins->get('Navigation');
				if (!$navigation instanceof \Plugins\Navigation\Navigation) {
					throw new \RuntimeException('Navigation plugin is required by this recipe.');
				}

				foreach ($resolved['navigation_menus'] as $menuKey) {
					$menu = $navigation->findMenuByKey((string)$menuKey);
					if (!$menu) {
						throw new \RuntimeException("Navigation menu not found: {$menuKey}.");
					}
					$navigation->appendPageLink(
						(int)$menu['item_id'],
						'/' . ltrim($slug, '/'),
						[$language => $title],
						$language
					);
				}
			}

			if (!\DB::commit()) {
				throw new \RuntimeException('Failed to commit page recipe transaction.');
			}

			if ($notifications = $this->plugins->get('Notifications')) {
				$notifications->store(
					\Core\User::getId(),
					$this->phrases['page_created'] ?? 'Page created.',
					'success'
				);
			}

			return \Core\Response::seeOther(
				$this->managerUrl('edit', ['pgm-pageId' => (int)$page['page_id']])
			);
		} catch (\Throwable $error) {
			\DB::rollBack();
			return '<div class="kc-notice kc-notice-error">'
				. \Core\Html::escape($error->getMessage())
				. '</div>';
		}
	}

	public function createPage($context_vars) {
		$data = \Core\Request::all();
		$domainId = (int)($data['domain_id'] ?? 0);

		try {
			$parentId = $this->normalizeParentPageId(
				$data['parent_id'] ?? null,
				$domainId
			);
		} catch (\InvalidArgumentException $error) {
			return $this->pageError($error->getMessage());
		}

		$pageData = [
			'domain_id' => $domainId,
			'system_name' => $data['title'],
			'page_slug' => $data['slug'],
			'parent_id' => $parentId,
			'layout_id' => $data['layout_id'],
			'page_plugins' => $data['layout_json'] ?? "{}"
		];

		$pageId = $this->savePage($pageData);
		$pageUuid = \DB::getOne("select uuid from pages where page_id=$1", [$pageId]);
		\DB::query(
			"insert into translations(entity_uuid, lang_code, translated_data)
			values($1, $2, $3)
			on conflict (entity_uuid, lang_code)
			do update set translated_data=$3",
			[
				$pageUuid,
				LANG,
				\Core\Utils\JsonTool::encode(['title' => $data['title']], false),
			]
		);

		if ($notifications = $this->plugins->get('Notifications')) {
			$notifications->store(
				\Core\User::getId(),
				$this->phrases['page_created'] ?? 'Page created.',
				'success'
			);
		}

		return \Core\Response::seeOther(
			$this->managerUrl('edit', ['pgm-pageId' => $pageId])
		);
	}

	private function savePage($pageData) {

		if(!empty($pageData['page_id'])) {
			$pageId = $pageData['page_id'];
		} else {
			// create
			$pageId = \DB::insert('pages', $pageData, 'page_id');
		}

		return $pageId;

	}


	private function buildRecipeDraftFromPage(int $pageId): array {
		$page = \DB::getRow(
			'select p.page_id, p.uuid, p.system_name, p.page_slug, p.page_plugins,
				l.system_name as layout_name, l.wrappers as layout_wrappers
			from pages p
			left join theme_layouts l using(layout_id)
			where p.page_id=$1',
			[$pageId]
		);
		if (!$page) {
			throw new \OutOfBoundsException('Page not found.');
		}

		$pagePlugins = \Core\Utils\JsonTool::decodeArray($page['page_plugins'] ?? null);
		$declaredWrappers = \Core\Utils\JsonTool::decodeArray($page['layout_wrappers'] ?? null);
		$wrappers = [];
		foreach ($declaredWrappers as $wrapper => $_definition) {
			if (is_string($wrapper) && $wrapper !== '') {
				$wrappers[$wrapper] = [];
			}
		}

		foreach ($pagePlugins as $wrapper => $instances) {
			if (
				!is_string($wrapper)
				|| !array_key_exists($wrapper, $wrappers)
				|| !is_array($instances)
			) {
				continue;
			}
			foreach ($instances as $instance) {
				if (!is_array($instance)) {
					continue;
				}

				foreach ($instance as $pluginName => $params) {
					$pluginName = trim((string)$pluginName);
					if ($pluginName === '') {
						continue;
					}

					$params = is_array($params) ? $params : [];
					$handler = trim((string)($params['handler'] ?? ''));
					unset($params['handler']);

					$recipeInstance = [
						'plugin' => $pluginName,
						'instance_params' => $params,
					];
					if ($handler !== '') {
						$recipeInstance['handler'] = $handler;
					}
					$wrappers[$wrapper][] = $recipeInstance;
				}
			}
		}

		$translation = \Core\Translation::get((string)$page['uuid']) ?? [];
		$title = trim((string)($translation['title'] ?? ''));
		if ($title === '') {
			$title = ucwords(str_replace(['_', '-'], ' ', (string)$page['system_name']));
		}

		return [
			'recipe_id' => 0,
			'recipe_key' => $this->suggestRecipeKey($page),
			'name' => $title,
			'description' => '',
			'source_title' => $title,
			'payload' => $this->normalizeRecipePayload([
				'page_prefix' => '',
				'default_navigation_menus' => [],
				'layout' => (string)($page['layout_name'] ?? ''),
				'wrappers' => $wrappers,
			]),
		];
	}

	private function suggestRecipeKey(array $page): string {
		$slug = trim((string)($page['page_slug'] ?? ''), '/');
		$source = $slug !== '' ? (string)basename($slug) : (string)($page['system_name'] ?? '');
		$key = strtolower($source);
		$key = preg_replace('/[^a-z0-9]+/', '-', $key) ?? '';
		$key = trim($key, '-');

		if ($key === '' || !preg_match('/^[a-z]/', $key)) {
			$key = 'page-' . ($key !== '' ? $key : (int)($page['page_id'] ?? 0));
		}

		$base = $key;
		$suffix = 2;
		while (\DB::getOne('select 1 from pgm_recipes where recipe_key=$1', [$key])) {
			$key = $base . '-' . $suffix;
			$suffix++;
		}

		return $key;
	}

	private function normalizeRecipePayload(array $payload): array {
		$pagePrefix = trim((string)($payload['page_prefix'] ?? ''));
		$layout = trim((string)($payload['layout'] ?? ''));

		$menus = $payload['default_navigation_menus'] ?? [];
		if (!is_array($menus) || !array_is_list($menus)) {
			throw new \InvalidArgumentException('Recipe default_navigation_menus must be a list.');
		}
		$menus = array_values(array_filter(array_map(
			static fn(mixed $menu): string => trim((string)$menu),
			$menus
		), static fn(string $menu): bool => $menu !== ''));

		$wrappers = $payload['wrappers'] ?? [];
		if (!is_array($wrappers)) {
			throw new \InvalidArgumentException('Recipe wrappers must be an object.');
		}
		foreach ($wrappers as $wrapper => $instances) {
			if (!is_string($wrapper) || $wrapper === '' || !is_array($instances) || !array_is_list($instances)) {
				throw new \InvalidArgumentException('Invalid recipe wrapper declaration.');
			}
		}

		return [
			'page_prefix' => $pagePrefix,
			'default_navigation_menus' => $menus,
			'layout' => $layout,
			'wrappers' => $wrappers,
		];
	}

	private function prepareRecipe(array $row): array {
		$row['recipe_id'] = (int)$row['recipe_id'];
		$row['payload'] = $this->normalizeRecipePayload(
			\Core\Utils\JsonTool::decodeArray($row['payload'] ?? null)
		);
		return $row;
	}

	private function assertRecipeManagerAccess(): void {
		if (!\Core\User::isRoot()) {
			throw new \RuntimeException('Page recipe management requires root access.');
		}
	}

	private function managerUrl(string $action = 'list', array $params = []): string {
		$url = LANG === (DOMAIN_CONFIG['default_language'] ?? LANG)
			? \Core\Url::path((string) PAGE_SLUG)
			: \Core\Url::path((string) LANG, (string) PAGE_SLUG);

		if ($action !== 'list') {
			$url = \Core\Url::pathParams(
				$url,
				['action' => $action],
				$this->prefix
			);
		}

		return \Core\Url::pathParams($url, $params);
	}


	private function pageError(string $message): string {
		return '<div class="kc-notice kc-notice-error">'
			. \Core\Html::escape($message)
			. '</div>';
	}

	private function normalizeLifecyclePluginIds(mixed $raw, int $domainId): array {
		$values = is_array($raw) ? $raw : [$raw];
		$result = [];

		foreach ($values as $value) {
			if (is_string($value)) {
				$value = trim($value);
			}
			if ($value === '' || $value === null) {
				continue;
			}
			if (filter_var($value, FILTER_VALIDATE_INT) === false || (int)$value < 1) {
				throw new \InvalidArgumentException('Invalid lifecycle plugin ID.');
			}

			$pluginId = (int)$value;
			if (!\DB::getOne(
				'SELECT 1 FROM plugins p JOIN plugin_domains pd USING(plugin_id) '
				. 'WHERE p.plugin_id=$1 AND pd.domain_id=$2 AND p.is_active=true LIMIT 1',
				[$pluginId, $domainId]
			)) {
				throw new \InvalidArgumentException('Lifecycle plugin is not enabled for this domain.');
			}

			$result[$pluginId] = $pluginId;
		}

		return array_values($result);
	}

	private function normalizeParentPageId(
		mixed $value,
		int $domainId,
		int $pageId = 0
	): ?int {
		if ($value === null || $value === '' || $value === 0 || $value === '0') {
			return null;
		}

		if (!is_int($value) && !(is_string($value) && ctype_digit($value))) {
			throw new \InvalidArgumentException(
				$this->phrases['invalid_parent_page'] ?? 'The selected parent page is invalid.'
			);
		}

		$parentId = (int)$value;
		if ($parentId < 1) {
			return null;
		}

		$parent = \DB::getRow(
			'select page_id, domain_id from pages where page_id=$1',
			[$parentId]
		);
		if (!$parent || (int)$parent['domain_id'] !== $domainId) {
			throw new \InvalidArgumentException(
				$this->phrases['invalid_parent_page'] ?? 'The selected parent page is invalid.'
			);
		}

		if ($pageId > 0 && \DB::getOne(
			'with recursive ancestors(page_id, parent_id) as (
				select page_id, parent_id from pages where page_id=$1
				union
				select p.page_id, p.parent_id
				from pages p
				join ancestors a on p.page_id=a.parent_id
			)
			select 1 from ancestors where page_id=$2 limit 1',
			[$parentId, $pageId]
		)) {
			throw new \InvalidArgumentException(
				$this->phrases['parent_page_cycle'] ?? 'A page cannot be placed under itself or one of its child pages.'
			);
		}

		return $parentId;
	}

	private function pageDescendantIds(int $pageId): array {
		$ids = [];
		$result = \DB::query(
			'with recursive descendants(page_id) as (
				select page_id from pages where page_id=$1
				union
				select p.page_id
				from pages p
				join descendants d on p.parent_id=d.page_id
			)
			select page_id from descendants',
			[$pageId]
		);
		while ($row = \DB::fetchRow($result)) {
			$ids[] = (int)$row['page_id'];
		}

		return $ids;
	}

	private function sortPagesAsTree(array $pages): array {
		$knownIds = [];
		foreach ($pages as $page) {
			$knownIds[(int)$page['id']] = true;
		}

		$children = [];
		foreach ($pages as $page) {
			$pageId = (int)$page['id'];
			$parentId = (int)($page['parent_id'] ?? 0);
			if ($parentId < 1 || $parentId === $pageId || !isset($knownIds[$parentId])) {
				$parentId = 0;
			}
			$children[$parentId][] = $page;
		}
		foreach ($children as &$siblings) {
			$siblings = \Core\Translation::sortByTitle($siblings, 'title', null);
		}
		unset($siblings);

		$ordered = [];
		$visited = [];
		$appendChildren = function(int $parentId, int $depth) use (
			&$appendChildren,
			&$children,
			&$ordered,
			&$visited
		): void {
			foreach ($children[$parentId] ?? [] as $page) {
				$pageId = (int)$page['id'];
				if (isset($visited[$pageId])) {
					continue;
				}
				$visited[$pageId] = true;
				$page['depth'] = $depth;
				$ordered[] = $page;
				$appendChildren($pageId, $depth + 1);
			}
		};

		$appendChildren(0, 0);
		foreach (\Core\Translation::sortByTitle($pages, 'title', null) as $page) {
			$pageId = (int)$page['id'];
			if (isset($visited[$pageId])) {
				continue;
			}
			$visited[$pageId] = true;
			$page['depth'] = 0;
			$ordered[] = $page;
			$appendChildren($pageId, 1);
		}

		return $ordered;
	}

	private function resolveLayoutById(int $domainId, int $layoutId): ?array {
		if ($domainId < 1 || $layoutId < 1) {
			return null;
		}

		$layout = \DB::getRow(
			'select l.layout_id, l.uuid, l.system_name, l.layout_filename, l.wrappers,
				t.system_name as theme_name
			from domains d
			join theme_layouts l on l.theme_id=d.theme_id
			join themes t on t.theme_id=d.theme_id
			where d.domain_id=$1 and l.layout_id=$2',
			[$domainId, $layoutId]
		);

		return $layout ?: null;
	}

	private function renderLayoutSelect(
		int $domainId,
		?int $selectedLayoutId = null,
		string $fieldId = 'layout_id'
	): string {
		$options = [];
		$layouts = \DB::query(
			'select l.layout_id, l.uuid, l.system_name
			from domains d
			join theme_layouts l on l.theme_id=d.theme_id
			where d.domain_id=$1',
			[$domainId]
		);

		while ($layout = \DB::fetchRow($layouts)) {
			$translated = \Core\Translation::get($layout['uuid']) ?? [];
			$options[] = [
				'label' => $translated['title'] ?? $layout['system_name'],
				'value' => (int)$layout['layout_id'],
			];
		}

		$options = \Core\Translation::sortByTitle($options, 'label', null);

		return $this->forms()->renderField([
			'name' => 'layout_id',
			'id' => $fieldId,
			'type' => 'select',
			'template' => 'control-select',
			'attributes' => ['class' => 'admin-input'],
			'options' => $options,
			'value' => $selectedLayoutId,
		]);
	}

	private function sortWrappers(array &$wrappers): void {
		uasort($wrappers, static function ($left, $right): int {
			return ((int)($left['displayorder'] ?? 0))
				<=> ((int)($right['displayorder'] ?? 0));
		});
	}

	private function loadLayoutPreview(array $layout, array $wrappers): string {
		$themeName = (string)($layout['theme_name'] ?? '');
		$layoutName = (string)($layout['layout_system_name'] ?? $layout['system_name'] ?? '');

		if (
			preg_match('/^[A-Za-z0-9_-]+$/', $themeName)
			&& preg_match('/^[A-Za-z0-9_-]+$/', $layoutName)
		) {
			$previewFile = ROOT_PATH
				. "themes/{$themeName}/templates/layout-previews/{$layoutName}.tpl";
			if (is_file($previewFile)) {
				return (string)file_get_contents($previewFile);
			}
		}

		$html = '<div class="pm-layout-preview pm-layout-preview-fallback">';
		foreach ($wrappers as $wrapperName => $wrapper) {
			$name = \Core\Html::escape((string)$wrapperName);
			$html .= '<section class="pm-layout-zone">'
				. '<header class="pm-zone-header">'
				. '<strong data-wrapper-title></strong>'
				. '<span data-wrapper-description></span>'
				. '</header>'
				. '<div class="plugin-dropzone" data-wrapper="' . $name . '"></div>'
				. '</section>';
		}
		$html .= '</div>';

		return $html;
	}

	private function pluginParamsForm(
		string $pluginName,
		?array $instanceParams,
		int $instanceId
	): string {
		$instanceParams ??= [];
		$pluginRow = \DB::getRow(
			'select uuid, config from plugins where system_name=$1',
			[$pluginName]
		);
		if (!$pluginRow) {
			return '<div class="kc-notice kc-notice-error">Plugin configuration not found.</div>';
		}

		$config = \Core\Utils\JsonTool::decodeArray($pluginRow['config'] ?? null);
		$handlers = is_array($config['handlers'] ?? null) ? $config['handlers'] : [];
		$translation = \Core\Translation::get($pluginRow['uuid']) ?? [];
		$translatedHandlers = is_array($translation['handlers'] ?? null)
			? $translation['handlers']
			: [];

		$currentHandler = (string)($instanceParams['handler']
			?? $config['default_handler']
			?? array_key_first($handlers)
			?? '');
		if (!isset($handlers[$currentHandler])) {
			$currentHandler = (string)(array_key_first($handlers) ?? '');
		}

		$handlerOptions = [];
		foreach ($handlers as $handlerName => $handler) {
			$handlerName = (string)$handlerName;
			$handlerOptions[] = [
				'title' => $translatedHandlers[$handlerName]['title']
					?? $handler['title']
					?? $handlerName,
				'value' => $handlerName,
			];
		}

		$currentConfig = is_array($handlers[$currentHandler] ?? null)
			? $handlers[$currentHandler]
			: [];
		$currentTranslation = is_array($translatedHandlers[$currentHandler] ?? null)
			? $translatedHandlers[$currentHandler]
			: [];

		$handlerField = [
			'name' => "plugin_handler[{$instanceId}]",
			'title' => $this->phrases['select_handler'] ?? 'Select handler',
			'type' => 'select',
			'options' => $handlerOptions,
			'value' => $currentHandler,
		];

		$form = "<div id='subform_{$instanceId}' class='admin-form-fields pm-plugin-instance-params'>";
		$form .= $this->forms()->renderField($handlerField);

		$formStructure = is_array($currentConfig['instance_params'] ?? null)
			? $currentConfig['instance_params']
			: [];
		$translatedFields = is_array($currentTranslation['instance_params'] ?? null)
			? $currentTranslation['instance_params']
			: [];

		foreach ($formStructure as $fieldName => $field) {
			if (!is_array($field)) {
				continue;
			}

			$translatedField = is_array($translatedFields[$fieldName] ?? null)
				? $translatedFields[$fieldName]
				: [];
			$field['title'] = $translatedField['title'] ?? $field['title'] ?? $fieldName;
			$field['description'] = $translatedField['description']
				?? $field['description']
				?? '';
			if (is_array($field['options'] ?? null)) {
				foreach ($field['options'] as &$option) {
					if (!is_array($option)) {
						continue;
					}
					$optionValue = (string)($option['value'] ?? '');
					$option['title'] = $translatedField['options'][$optionValue]
						?? $option['title']
						?? $optionValue;
				}
				unset($option);
			}

			$field['name'] = "plugin_params[{$instanceId}][{$fieldName}]";
			$field['value'] = $instanceParams[$fieldName] ?? $field['default'] ?? null;
			$form .= $this->forms()->renderField($field);
		}

		$form .= '</div>';
		return $form;
	}

	private function normalizeInstanceParamValue(mixed $value, mixed $structure): mixed {
		if (!is_array($structure) || empty($structure['multiple'])) {
			return $value;
		}

		$values = is_array($value) ? $value : [$value];
		return array_values(array_filter(
			$values,
			static fn(mixed $item): bool => $item !== null && $item !== ''
		));
	}

	private function forms(): \Plugins\Forms\Forms {
		return $this->plugins->get('Forms');
	}
	public function pageLayoutData(array $data): string {
		$this->addCss('/plugins/PageManager/assets/page-manager.css');
		$pageId = (int)($data['pgm-pageId'] ?? $data['page_id'] ?? 0);
		$page = \DB::getRow(
			'select page_id, domain_id, layout_id, page_plugins from pages where page_id=$1',
			[$pageId]
		);

		if (!$page) {
			return \Core\Utils\JsonTool::encode([
				'status' => 'error',
				'error' => 'Page not found.',
			], false);
		}

		$layoutId = (int)($data['pgm-layoutId'] ?? $data['layout_id'] ?? $page['layout_id']);
		$layout = $this->resolveLayoutById((int)$page['domain_id'], $layoutId);
		if (!$layout) {
			return \Core\Utils\JsonTool::encode([
				'status' => 'error',
				'error' => $this->phrases['invalid_layout']
					?? 'The selected layout is not available for this domain.',
			], false);
		}

		$declaredWrappers = \Core\Utils\JsonTool::decodeArray($layout['wrappers'] ?? null);
		$this->sortWrappers($declaredWrappers);
		$pagePlugins = \Core\Utils\JsonTool::decodeArray($page['page_plugins'] ?? null);
		$layoutTranslation = \Core\Translation::get($layout['uuid']) ?? [];
		$translatedWrappers = is_array($layoutTranslation['wrappers'] ?? null)
			? $layoutTranslation['wrappers']
			: [];

		$wrapperNames = array_values(array_unique([
			...array_keys($declaredWrappers),
			...array_keys($pagePlugins),
		]));
		$wrappers = [];
		$instanceId = 1;

		foreach ($wrapperNames as $wrapperName) {
			$wrapper = is_array($declaredWrappers[$wrapperName] ?? null)
				? $declaredWrappers[$wrapperName]
				: [];
			$translatedWrapper = is_array($translatedWrappers[$wrapperName] ?? null)
				? $translatedWrappers[$wrapperName]
				: [];
			$pluginsHtml = '';

			foreach (($pagePlugins[$wrapperName] ?? []) as $plugin) {
				if (!is_array($plugin) || !$plugin) {
					continue;
				}

				$pluginName = (string)array_key_first($plugin);
				$params = is_array($plugin[$pluginName] ?? null)
					? $plugin[$pluginName]
					: [];
				$pluginRow = \DB::getRow(
					'select uuid from plugins where system_name=$1',
					[$pluginName]
				);
				$pluginTranslation = $pluginRow
					? (\Core\Translation::get($pluginRow['uuid']) ?? [])
					: [];

				try {
					$contextForm = $this->pluginParamsForm(
						$pluginName,
						$params,
						$instanceId
					);
				} catch (\Throwable $error) {
					trigger_error(
						"PageManager failed to render {$pluginName} instance settings: "
						. $error->getMessage(),
						E_USER_WARNING
					);
					$contextForm = '<div class="kc-notice kc-notice-error">'
						. \Core\Html::escape($this->phrases['plugin_settings_load_failed']
								?? 'Failed to load plugin settings.')
						. '</div>';
				}

				$pluginsHtml .= $this->render('wrapper_plugin', [
					'plugin_name' => \Core\Html::escape($pluginName),
					'plugin_title' => \Core\Html::escape((string)($pluginTranslation['title'] ?? $pluginName)),
					'instance_id' => $instanceId,
					'wrapper_name' => \Core\Html::escape((string)$wrapperName),
					'context_form' => $contextForm,
				]);

				$instanceId++;
			}

			$wrappers[$wrapperName] = [
				'name' => $wrapperName,
				'title' => $translatedWrapper['title']
					?? $wrapper['title']
					?? $wrapperName,
				'description' => $translatedWrapper['description']
					?? $wrapper['description']
					?? '',
				'known' => array_key_exists($wrapperName, $declaredWrappers),
				'plugins_html' => \Core\Renderer::finalize($pluginsHtml),
			];
		}

		return \Core\Utils\JsonTool::encode([
			'status' => 'ok',
			'layout_id' => $layoutId,
			'layout_preview' => \Core\Renderer::finalize(
				$this->loadLayoutPreview($layout, $declaredWrappers)
			),
			'wrappers' => $wrappers,
			'last_instance' => $instanceId - 1,
		], false);
	}

	public function domainPages($data) {
		$this->addCss('/plugins/PageManager/assets/page-manager.css');
		$domainId = (int)($data['domain_id'] ?? 0);
		$pages = [];
		$page_rows = \DB::query(
			'select page_id, parent_id, uuid, system_name, page_slug, layout_id
			from pages where domain_id=$1',
			[$domainId]
		);
		while($row = \DB::fetchRow($page_rows)) {
			$translated = \Core\Translation::get($row['uuid']);
			$layout = \DB::getRow(
				'select * from theme_layouts where layout_id=$1',
				[(int)$row['layout_id']]
			);
			$layout_translated = \Core\Translation::get($layout['uuid']);
			$pages[] = [
				'id'          => (int)$row['page_id'],
				'parent_id'   => $row['parent_id'] !== null ? (int)$row['parent_id'] : null,
				'uuid'        => $row['uuid'],
				'title'       => $translated['title'] ?? ucwords(str_replace(['_', '-'], ' ', $row['system_name'])),
				'slug'        => $row['page_slug'],
				'layout'      => $layout_translated['title'] ?? $layout['system_name']
			];
		}
		$pages = $this->sortPagesAsTree($pages);

		$layouts_select = $this->renderLayoutSelect($domainId);
		$parent_field = $this->forms()->renderField([
			'name' => 'parent_id',
			'id' => 'page-create-parent-id',
			'type' => 'page_id',
			'label' => $this->phrases['parent_page'] ?? 'Parent page',
			'placeholder' => $this->phrases['no_parent_page'] ?? 'No parent page',
			'params' => ['domain_ids' => [$domainId]],
		]);

		$response = [
			'status' => 'ok',
			'pages' => $pages,
			'layouts' => \Core\Renderer::finalize($layouts_select),
			'parent_field' => \Core\Renderer::finalize($parent_field),
			'error'  => ''
		];

		return \Core\Utils\JsonTool::encode($response, false);
	}

	public function pluginContextForm($data) {
		$this->addCss('/plugins/PageManager/assets/page-manager.css');
		$instance_id = str_replace('plugin_', '', $data['instance_id']);

		$plugin_name = $data['plugin'];
		$context_vars = [];
		if (isset($data['handler']) && is_string($data['handler'])) {
			$context_vars['handler'] = $data['handler'];
		}

		return $this->pluginParamsForm($plugin_name, $context_vars, $instance_id);

	}

	public function deletePage($data) {
		$pageId = (int)($data['pgm-id'] ?? 0);
		if ($pageId < 1) {
			throw new \InvalidArgumentException('Invalid page id.');
		}

		$pg = \DB::getRow('select uuid from pages where page_id=$1', [$pageId]);
		if (!$pg) {
			throw new \OutOfBoundsException('Page not found.');
		}

		\DB::query('delete from pages where page_id=$1', [$pageId]);
		\DB::query('delete from translations where entity_uuid=$1', [(string)$pg['uuid']]);

		// page_acl rows are removed by the foreign key ON DELETE CASCADE.

		return "del: {$pageId}";
	}
}

