<?php

/**
 * KamiCore
 *
 * SPDX-License-Identifier: Apache-2.0
 *
 * @see https://kamicore.org
 */

declare(strict_types=1);

namespace Plugins\ViewContent;

if (!IN_KAMI) die();

class ViewContent extends \Core\BasePlugin
{
    private const DEFAULT_SINGLE_TEMPLATE = 'default-single';
    private const DEFAULT_LIST_TEMPLATE = 'default-list-item';
    private const DEFAULT_TREE_TEMPLATE = 'default-tree-item';
    private ?string $itemBaseUrl = null;


    public function view(array $instance_params = []): string
    {
        $types = $this->effectiveTypeConfigs($instance_params['content_types'] ?? null);
        if ($types === []) {
            return '';
        }

        $peeked = $this->peekRoutedItem();
        if ($peeked === null) {
            return $this->list($instance_params);
        }

        $contentTypeId = (int)($peeked['ct_id'] ?? 0);
        if (!isset($types[$contentTypeId]) || trim((string)($peeked['item_slug'] ?? '')) === '') {
            return $this->list($instance_params);
        }

        $item = $this->routedItem();
        if ($item === null) {
            return $this->list($instance_params);
        }

        return $this->renderSingleItem($item, $types[$contentTypeId]);
    }

    public function list(array $instance_params = []): string
    {
        $this->layoutParams['listItems'] = [];
        $types = $this->effectiveTypeConfigs($instance_params['content_types'] ?? null);
        if ($types === []) {
            return $this->renderList([], '');
        }

        $parentId = $this->positiveIntOrNull($instance_params['parent_id'] ?? null);
        if ($parentId !== null && !$this->anchorIsViewable($parentId)) {
            return $this->renderList([], '');
        }

        $itemsPerPage = max(0, (int)($instance_params['items_per_page'] ?? 20));
        $showPagination = \boolValue($instance_params['show_pagination'] ?? true);
        $paginationEnabled = $showPagination && $itemsPerPage > 0;
        $page = $paginationEnabled ? \Core\Request::page($this->prefix) : 1;
        $offset = $paginationEnabled ? ($page - 1) * $itemsPerPage : 0;
        $sortFieldId = $this->positiveIntOrNull($instance_params['sort_field'] ?? null);
        $sortDirection = $this->sortDirection($instance_params['sort_direction'] ?? 'asc');

        $result = $this->listItemIds(
            array_keys($types),
            $parentId,
            $sortFieldId,
            $sortDirection,
            $offset,
            $itemsPerPage
        );

        if ($paginationEnabled && $result['total'] > 0) {
            $lastPage = max(1, (int)ceil($result['total'] / $itemsPerPage));
            if ($page > $lastPage) {
                $page = $lastPage;
                $offset = ($page - 1) * $itemsPerPage;
                $result = $this->listItemIds(
                    array_keys($types),
                    $parentId,
                    $sortFieldId,
                    $sortDirection,
                    $offset,
                    $itemsPerPage
                );
            }
        }

        $items = [];
        foreach ($result['ids'] as $itemId) {
            $item = \Core\Content::getItem($itemId);
            if ($item === []) {
                continue;
            }

            $contentTypeId = (int)($item['ct_id'] ?? 0);
            if (!isset($types[$contentTypeId])) {
                continue;
            }

            $seoUrl = $this->itemUrl($item);
            if ($seoUrl !== '') {
                $this->layoutParams['listItems'][] = ['name' => $item['title'], 'url' => $seoUrl];
            }
            $items[] = [
                'template' => 'content-list-raw',
                'params' => [
                    'content' => $this->renderListItem($item, $types[$contentTypeId]),
                ],
            ];
        }

        $pagination = '';
        if ($paginationEnabled) {
            $pagination = $this->pagination()->renderPagination(
                page: $page,
                perPage: $itemsPerPage,
                total: $result['total'],
                base_url: \Core\Request::path(),
                options: [
                    'page_param' => \Core\Request::buildKey('page', $this->prefix),
                ]
            );
        }

        return $this->renderList($items, $pagination);
    }

    public function tree(array $instance_params = []): string
    {
        $this->addCss('/plugins/ViewContent/assets/view-content.css');
        $types = $this->effectiveTypeConfigs($instance_params['content_types'] ?? null);
        if ($types === []) {
            return $this->renderTreeWrapper('', $types, null, 'asc', false);
        }

        $rootId = $this->positiveIntOrNull($instance_params['parent_id'] ?? null);
        if ($rootId !== null && !$this->anchorIsViewable($rootId)) {
            return $this->renderTreeWrapper('', $types, null, 'asc', false);
        }

        $mode = strtolower(trim((string)($instance_params['tree_mode'] ?? 'full'))) === 'lazy'
            ? 'lazy'
            : 'full';
        $sortFieldId = $this->positiveIntOrNull($instance_params['sort_field'] ?? null);
        $sortDirection = $this->sortDirection($instance_params['sort_direction'] ?? 'asc');
        $activePath = $this->activePath($rootId, $types);
        $peeked = $this->peekRoutedItem();
        $activeItemId = $peeked !== null && isset($activePath[(int)($peeked['item_id'] ?? 0)])
            ? (int)$peeked['item_id']
            : null;

        if ($mode === 'lazy') {
            $nodes = $this->renderLazyLevel(
                $rootId,
                $types,
                $sortFieldId,
                $sortDirection,
                $activePath,
                $activeItemId
            );
        } else {
            $nodes = $this->renderFullTree(
                $rootId,
                $types,
                $sortFieldId,
                $sortDirection,
                $activePath,
                $activeItemId
            );
        }

        return $this->renderTreeWrapper(
            $nodes,
            $types,
            $sortFieldId,
            $sortDirection,
            $mode === 'lazy'
        );
    }

    public function treeChildren(array $params = []): string
    {
        $this->addCss('/plugins/ViewContent/assets/view-content.css');
        $this->itemBaseUrl = $this->normalizeBaseUrl($params['base_url'] ?? null);
        $parentId = $this->positiveIntOrNull($params['parent_id'] ?? null);
        if ($parentId === null) {
            return '';
        }

        $types = $this->effectiveTypeConfigs($params['content_types'] ?? null);
        if ($types === []) {
            return '';
        }

        $parent = \Core\Content::getItem($parentId);
        $parentTypeId = (int)($parent['ct_id'] ?? 0);
        if ($parent === [] || !isset($types[$parentTypeId])) {
            return '';
        }

        $sortFieldId = $this->positiveIntOrNull($params['sort_field'] ?? null);
        $sortDirection = $this->sortDirection($params['sort_direction'] ?? 'asc');
        $rows = $this->directChildren(
            $parentId,
            array_keys($types),
            $sortFieldId,
            $sortDirection
        );

        return $this->renderLazyRows($rows, $types);
    }

    public function config(array $instance_params = []): string
    {
        $configured = $this->configuredTypeConfigs();
        $rows = [];
        $contentTypes = \DB::getArr('SELECT ct_id FROM content_types ORDER BY ct_id');

        foreach ($contentTypes as $contentTypeId) {
            $contentTypeId = (int)$contentTypeId;
            $contentType = \Core\Content::getContentType($contentTypeId);
            $current = $configured[$contentTypeId] ?? [];

            $rows[] = [
                'template' => 'config-row',
                'params' => [
                    'ct_id' => (string)$contentTypeId,
                    'checked' => isset($configured[$contentTypeId]) ? ' checked' : '',
                    'title' => \Core\Html::escape((string)($contentType['title'] ?? $contentType['system_name'] ?? '')),
                    'system_name' => \Core\Html::escape((string)($contentType['system_name'] ?? '')),
                    'single_template' => \Core\Html::escape((string)($current['single_template'] ?? '')),
                    'list_template' => \Core\Html::escape((string)($current['list_template'] ?? '')),
                    'tree_template' => \Core\Html::escape((string)($current['tree_template'] ?? '')),
                ],
            ];
        }

        $notice = '';
        if ((string)($this->param('saved', '')) === '1') {
            $notice = $this->notice($this->phrases['saved'] ?? 'ViewContent configuration saved.');
        }

        return $this->render('config', [
            'heading' => \Core\Html::escape($this->phrases['configuration'] ?? 'Content viewer configuration'),
            'description' => \Core\Html::escape(
                $this->phrases['configuration_description']
                    ?? 'Enable content types and optionally assign templates for single, list and tree items.'
            ),
            'notice' => $notice,
            'type_rows' => $rows,
            'save_action' => $this->managerUrl('save_config'),
            'text_enabled' => \Core\Html::escape($this->phrases['enabled'] ?? 'Enabled'),
            'text_content_type' => \Core\Html::escape($this->phrases['content_type'] ?? 'Content type'),
            'text_single_template' => \Core\Html::escape($this->phrases['single_template'] ?? 'Single template'),
            'text_list_template' => \Core\Html::escape($this->phrases['list_template'] ?? 'List item template'),
            'text_tree_template' => \Core\Html::escape($this->phrases['tree_template'] ?? 'Tree item template'),
            'fallback_hint' => \Core\Html::escape(
                $this->phrases['template_fallback_hint']
                    ?? 'Leave empty to use the built-in fallback template.'
            ),
            'text_save' => \Core\Html::escape($this->phrases['save'] ?? 'Save'),
        ]);
    }

    public function saveConfig(array $instance_params = []): string
    {
        $request = \Core\Request::all();
        $submitted = is_array($request['types'] ?? null) ? $request['types'] : [];
        $knownTypeIds = array_fill_keys(
            array_map('intval', \DB::getArr('SELECT ct_id FROM content_types')),
            true
        );

        \DB::beginTransaction();
        try {
            if (\DB::query('DELETE FROM vc_content_types') === false) {
                throw new \RuntimeException('Failed to clear ViewContent configuration.');
            }

            foreach ($submitted as $contentTypeId => $values) {
                $contentTypeId = (int)$contentTypeId;
                if (!isset($knownTypeIds[$contentTypeId]) || !is_array($values) || empty($values['enabled'])) {
                    continue;
                }

                $singleTemplate = $this->normalizeTemplateName($values['single_template'] ?? null);
                $listTemplate = $this->normalizeTemplateName($values['list_template'] ?? null);
                $treeTemplate = $this->normalizeTemplateName($values['tree_template'] ?? null);

                if (\DB::insert('vc_content_types', [
                    'ct_id' => $contentTypeId,
                    'single_template' => $singleTemplate,
                    'list_template' => $listTemplate,
                    'tree_template' => $treeTemplate,
                ]) === false) {
                    throw new \RuntimeException('Failed to save ViewContent configuration.');
                }
            }

            \DB::commit();
        } catch (\Throwable $e) {
            \DB::rollBack();
            return $this->notice($e->getMessage(), 'error');
        }

        return \Core\Response::redirect($this->managerUrl('config') . '/' . $this->prefix . '-saved/1');
    }

    private function renderSingleItem(array $item, array $config): string
    {

        $contentType = \Core\Content::getContentType((int)$item['ct_id']);
        $schema = is_array($contentType['schema'] ?? null) ? $contentType['schema'] : [];
        $fields = is_array($schema['fields'] ?? null) ? $schema['fields'] : [];
        $titleField = (string)($schema['title_field'] ?? '');
        $summaryField = (string)($schema['summary_field'] ?? '');
        $fieldRows = [];

        uksort($fields, static function (string $left, string $right) use ($fields): int {
            $leftOrder = (int)($fields[$left]['displayorder'] ?? PHP_INT_MAX);
            $rightOrder = (int)($fields[$right]['displayorder'] ?? PHP_INT_MAX);
            return $leftOrder <=> $rightOrder ?: strcmp($left, $right);
        });

        foreach ($fields as $fieldName => $fieldConfig) {
			// echo "<pre>".print_r($fieldConfig, true)."</pre>";
            $value = $item['data'][$fieldName] ?? null;
			$fieldRenderMethod = 'renderFieldValue_' . $fieldConfig['type'];

			if (method_exists($this, $fieldRenderMethod)) {
				$fieldRendered = $this->$fieldRenderMethod(
					$fieldName,
					$fieldConfig,
					$value
				);

				$item['data'][$fieldName] = $fieldRendered;
			} else {
				$fieldRendered = $this->render('default-single-field', [
					'label' => \Core\Html::escape($label),
					'value' => \Core\Html::escape(
						$this->debugValue($value, $fieldConfig)
					),
				]);
			}

			if ($fieldName === $titleField || $fieldName === $summaryField) {
				continue;
			}

			$fieldRows[] = $fieldRendered;
        }

        $template = trim((string)($config['single_template'] ?? ''));
        if ($template !== '') {
            return $this->render($template, $this->itemTemplateParams($item));
        }

        $summary = trim((string)($item['summary'] ?? ''));
        return $this->render(self::DEFAULT_SINGLE_TEMPLATE, [
            'title' => \Core\Html::escape((string)($item['title'] ?? "#{$item['item_id']}")),
            'summary' => $summary !== ''
                ? $this->render('default-single-summary', ['summary' => \Core\Html::escape($summary)])
                : '',
            'fields' => implode("\n", $fieldRows),
        ]);
    }

    //
    // Field type templates
    //

    private function renderFieldValue_markdown(string $fieldName, ?array $fieldConfig, $value) {
		$render = $this->plugins->get('ViewMd');
		return ($render) ? $render->renderMarkdown($value) : $value;
	}

    private function renderListItem(array $item, array $config): string
    {
        $template = trim((string)($config['list_template'] ?? ''));
        if ($template !== '') {
            return $this->render($template, $this->itemTemplateParams($item));
        }

        $summary = trim((string)($item['summary'] ?? ''));
        return $this->render(self::DEFAULT_LIST_TEMPLATE, [
            'title' => $this->defaultTitle($item, 'list'),
            'summary' => $summary !== ''
                ? $this->render('default-list-summary', ['summary' => \Core\Html::escape($summary)])
                : '',
        ]);
    }

    private function renderTreeItem(array $item, array $config, bool $active): string
    {
        $template = trim((string)($config['tree_template'] ?? ''));
        if ($template !== '') {
            $params = $this->itemTemplateParams($item);
            $params['active'] = $active ? '1' : '0';
            $params['active_class'] = $active ? ' is-active' : '';
            return $this->render($template, $params);
        }

        return $this->render(self::DEFAULT_TREE_TEMPLATE, [
            'title' => $this->defaultTitle($item, 'tree'),
        ]);
    }

    private function renderList(array $items, string $pagination): string
    {
        if ($items === []) {
            $items = [[
                'template' => 'content-list-empty',
                'params' => [
                    'message' => \Core\Html::escape($this->phrases['no_items'] ?? 'No content items found.'),
                ],
            ]];
        }

        return $this->render('content-list', [
            'items' => $items,
            'pagination' => $pagination,
        ]);
    }

    private function renderFullTree(
        ?int $rootId,
        array $types,
        ?int $sortFieldId,
        string $sortDirection,
        array $activePath,
        ?int $activeItemId
    ): string {
        $rows = $this->fullTreeRows(array_keys($types), $rootId, $sortFieldId, $sortDirection);
        $children = [];

        foreach ($rows as $row) {
            $parentId = $this->positiveIntOrNull($row['parent_id'] ?? null) ?? 0;
            $children[$parentId][] = (int)$row['item_id'];
        }

        return $this->renderFullLevel(
            $rootId ?? 0,
            $children,
            $types,
            $activePath,
            $activeItemId,
            []
        );
    }

    private function renderFullLevel(
        int $parentId,
        array $children,
        array $types,
        array $activePath,
        ?int $activeItemId,
        array $visited
    ): string {
        $html = '';
        foreach ($children[$parentId] ?? [] as $itemId) {
            if (isset($visited[$itemId])) {
                continue;
            }

            $item = \Core\Content::getItem($itemId);
            $contentTypeId = (int)($item['ct_id'] ?? 0);
            if ($item === [] || !isset($types[$contentTypeId])) {
                continue;
            }

            $nextVisited = $visited;
            $nextVisited[$itemId] = true;
            $childHtml = $this->renderFullLevel(
                $itemId,
                $children,
                $types,
                $activePath,
                $activeItemId,
                $nextVisited
            );
            $hasChildren = isset($children[$itemId]) && $children[$itemId] !== [];
            $onActivePath = isset($activePath[$itemId]);
            $active = $activeItemId === $itemId;
            $html .= $this->renderTreeNode(
                $item,
                $types[$contentTypeId],
                $hasChildren,
                $childHtml,
                true,
                $onActivePath && $hasChildren,
                $active
            );
        }

        return $html;
    }

    private function renderLazyLevel(
        ?int $parentId,
        array $types,
        ?int $sortFieldId,
        string $sortDirection,
        array $activePath,
        ?int $activeItemId,
        array $visited = []
    ): string {
        $rows = $this->directChildren(
            $parentId,
            array_keys($types),
            $sortFieldId,
            $sortDirection
        );
        $html = '';

        foreach ($rows as $row) {
            $itemId = (int)$row['item_id'];
            if (isset($visited[$itemId])) {
                continue;
            }

            $item = \Core\Content::getItem($itemId);
            $contentTypeId = (int)($item['ct_id'] ?? 0);
            if ($item === [] || !isset($types[$contentTypeId])) {
                continue;
            }

            $hasChildren = \DB::readBool($row['has_children'] ?? false);
            $onActivePath = isset($activePath[$itemId]);
            $active = $activeItemId === $itemId;
            $loadChildren = $hasChildren && $onActivePath;
            $childHtml = '';

            if ($loadChildren) {
                $nextVisited = $visited;
                $nextVisited[$itemId] = true;
                $childHtml = $this->renderLazyLevel(
                    $itemId,
                    $types,
                    $sortFieldId,
                    $sortDirection,
                    $activePath,
                    $activeItemId,
                    $nextVisited
                );
            }

            $html .= $this->renderTreeNode(
                $item,
                $types[$contentTypeId],
                $hasChildren,
                $childHtml,
                $loadChildren,
                $loadChildren,
                $active
            );
        }

        return $html;
    }

    private function renderLazyRows(array $rows, array $types): string
    {
        $html = '';
        foreach ($rows as $row) {
            $itemId = (int)$row['item_id'];
            $item = \Core\Content::getItem($itemId);
            $contentTypeId = (int)($item['ct_id'] ?? 0);
            if ($item === [] || !isset($types[$contentTypeId])) {
                continue;
            }

            $hasChildren = \DB::readBool($row['has_children'] ?? false);
            $html .= $this->renderTreeNode(
                $item,
                $types[$contentTypeId],
                $hasChildren,
                '',
                !$hasChildren,
                false,
                false
            );
        }

        return $html;
    }

    private function renderTreeNode(
        array $item,
        array $config,
        bool $hasChildren,
        string $children,
        bool $loaded,
        bool $open,
        bool $active
    ): string {
        $params = [
            'item_id' => (string)$item['item_id'],
            'item' => $this->renderTreeItem($item, $config, $active),
            'active_class' => $active ? ' is-active' : '',
        ];

        if (!$hasChildren) {
            return $this->render('tree-node-leaf', $params);
        }

        $params['data_loaded'] = $loaded ? '1' : '0';
        $params['open'] = $open ? ' open' : '';
        $params['children'] = $children;
        return $this->render('tree-node-branch', $params);
    }

    private function renderTreeWrapper(
        string $nodes,
        array $types,
        ?int $sortFieldId,
        string $sortDirection,
        bool $lazy
    ): string {
        if ($lazy) {
            $this->addJs('/plugins/ViewContent/assets/tree.js');
        }

        return $this->render('content-tree', [
            'nodes' => $nodes,
            'base_url' => \Core\Html::escape($this->currentPageBaseUrl()),
            'content_types' => \Core\Html::escape(implode(',', array_keys($types))),
            'sort_field' => $sortFieldId !== null ? (string)$sortFieldId : '',
            'sort_direction' => $sortDirection,
        ]);
    }

    private function itemTemplateParams(array $item): array
    {
        $data = is_array($item['data'] ?? null) ? $item['data'] : [];
        foreach ($data as $key => $value) {
            if (is_array($value) || is_object($value)) {
                $data[$key] = \Core\Utils\JsonTool::encode($value, false);
            }
        }
        $url = $this->itemUrl($item);
        $title = (string)($item['title'] ?? "#{$item['item_id']}");
        $summary = (string)($item['summary'] ?? '');

        $data['item_id'] = (string)$item['item_id'];
        $data['item_slug'] = (string)($item['item_slug'] ?? '');
        $data['item_parent_id'] = (string)($item['parent_id'] ?? '');
        $data['content_type_name'] = (string)($item['content_type_name'] ?? '');
        $data['content_url'] = $url;
        $data['content_title'] = $title;
        $data['content_summary'] = $summary;
        $data['url'] ??= $url;
        $data['title'] ??= $title;
        $data['summary'] ??= $summary;

        return $data;
    }

    private function defaultTitle(array $item, string $context): string
    {
        $title = \Core\Html::escape((string)($item['title'] ?? "#{$item['item_id']}"));
        $url = $this->itemUrl($item);
        $prefix = $context === 'tree' ? 'default-tree-title' : 'default-list-title';

        if ($url === '') {
            return $this->render($prefix . '-text', ['title' => $title]);
        }

        return $this->render($prefix . '-link', [
            'url' => \Core\Html::escape($url),
            'title' => $title,
            'summary' => \Core\Html::escape((string)($item['summary'] ?? '')),
        ]);
    }

    public function canonicalUrl(array $item, string $langCode, int $domainId, int $groupId): ?string
    {
        $contentTypeId = (int)($item['ct_id'] ?? 0);
        $slug = trim((string)($item['item_slug'] ?? ''));
        if ($contentTypeId < 1 || $slug === '' || !$this->id) return null;
        if (!\Core\User::canContent($contentTypeId, 'view', $groupId)
            || !\Core\User::canPlugin((int)$this->id, 'view', $groupId)) return null;
        if (!\DB::getOne('select 1 from vc_content_types where ct_id=$1', [$contentTypeId])) return null;

        $domain = \DB::getRow('select domain_config from domains where domain_id=$1', [$domainId]);
        if (!$domain) return null;
        $config = \Core\Utils\JsonTool::decodeArray($domain['domain_config'] ?? null);
        $languages = is_array($config['languages'] ?? null) ? $config['languages'] : [];
        if (!in_array($langCode, $languages, true)) return null;

        $pages = $this->canonicalPages($domainId, $groupId, $contentTypeId, $langCode);
        if ($pages === []) return null;
        $defaultLanguage = (string)($config['default_language'] ?? $langCode);
        $base = $this->canonicalPagePath((string)$pages[0]['page_slug'], $langCode, $defaultLanguage);
        return rtrim($base, '/') . '/' . rawurlencode($slug);
    }

    private function canonicalPages(int $domainId, int $groupId, int $contentTypeId, string $langCode): array
    {
        $pages = [];
        $result = \DB::query('select page_id, page_slug, page_plugins from pages where domain_id=$1 order by page_id', [$domainId]);
        while ($page = \DB::fetchRow($result)) {
            $pageId = (int)$page['page_id'];
            if (!\Core\User::canPage($pageId, $groupId)) continue;
            $priority = $this->canonicalPagePriority($page, $contentTypeId, $langCode);
            if ($priority === null) continue;
            $pages[] = ['priority'=>$priority, 'page_id'=>$pageId, 'page_slug'=>(string)$page['page_slug']];
        }
        usort($pages, static fn(array $a, array $b): int => $a['priority'] <=> $b['priority'] ?: $a['page_id'] <=> $b['page_id']);
        return $pages;
    }

    private function canonicalPagePriority(array $page, int $contentTypeId, string $langCode): ?int
    {
        $pagePlugins = \Core\Utils\JsonTool::decodeArray($page['page_plugins'] ?? null);
        $best = null;
        foreach ($pagePlugins as $instances) {
            if (!is_array($instances)) continue;
            foreach ($instances as $instance) {
                if (!is_array($instance) || !isset($instance['ViewContent']) || !is_array($instance['ViewContent'])) continue;
                $params = $instance['ViewContent'];
                if ((string)($params['handler'] ?? '') !== 'view') continue;
                $selected = $this->canonicalContentTypeIds($params['content_types'] ?? null, $langCode);
                if ($selected !== [] && !in_array($contentTypeId, $selected, true)) continue;
                $priority = $selected === [] ? 1 : 0;
                $best = $best === null ? $priority : min($best, $priority);
            }
        }
        return $best;
    }

    private function canonicalContentTypeIds(mixed $value, string $langCode): array
    {
        if (is_string($value) && str_contains($value, ',')) $value = explode(',', $value);
        $values = is_array($value) ? $value : [$value];
        $ids = [];
        foreach ($values as $contentType) {
            if ($contentType === null || $contentType === '') continue;
            try {
                $type = \Core\Content::getContentType(is_numeric($contentType) ? (int)$contentType : (string)$contentType, $langCode);
            } catch (\Throwable) {
                continue;
            }
            $id = (int)($type['ct_id'] ?? 0);
            if ($id > 0) $ids[$id] = $id;
        }
        return array_values($ids);
    }

    private function canonicalPagePath(string $pageSlug, string $langCode, string $defaultLanguage): string
    {
        $prefix = $langCode === $defaultLanguage ? '' : '/' . rawurlencode($langCode);
        $segments = array_values(array_filter(explode('/', trim($pageSlug, '/')), static fn(string $segment): bool => $segment !== ''));
        $path = implode('/', array_map('rawurlencode', $segments));
        return $prefix . ($path !== '' ? '/' . $path : '');
    }

    private function itemUrl(array $item): string
    {
        $slug = trim((string)($item['item_slug'] ?? ''));
        if ($slug === '') {
            return '';
        }

        if ($this->itemBaseUrl !== null) {
            $base = $this->itemBaseUrl;
        } elseif (defined('PAGE_SLUG')) {
            $base = $this->currentPageBaseUrl();
        } else {
            return '';
        }

        return rtrim($base, '/') . '/' . rawurlencode($slug);
    }

    private function currentPageBaseUrl(): string
    {
        if (!defined('PAGE_SLUG')) {
            return '';
        }

        $languagePrefix = LANG === (DOMAIN_CONFIG['default_language'] ?? LANG)
            ? ''
            : '/' . rawurlencode(LANG);
        $pageSlug = trim((string)PAGE_SLUG, '/');
        return $languagePrefix . ($pageSlug !== '' ? '/' . $pageSlug : '');
    }

    private function normalizeBaseUrl(mixed $value): ?string
    {
        $value = trim((string)$value);
        if ($value === '') {
            return '';
        }
        if (!str_starts_with($value, '/') || str_starts_with($value, '//')) {
            return null;
        }
        $path = parse_url($value, PHP_URL_PATH);
        if (!is_string($path) || $path === '') {
            return null;
        }
        return '/' . trim($path, '/');
    }

    /** @return list<int> */
    public function getRenderableContentTypeIds(mixed $selected = null): array
    {
        return array_keys($this->effectiveTypeConfigs($selected));
    }

    public function renderListItems(iterable $items, mixed $selected = null): string
    {
        $types = $this->effectiveTypeConfigs($selected);
        if ($types === []) {
            return '';
        }

        $html = '';
        foreach ($items as $item) {
            if (!is_array($item)) {
                continue;
            }
            if (!isset($item['data'])) {
                $item = \Core\Content::prepareItem($item);
            }

            $contentTypeId = (int)($item['ct_id'] ?? 0);
            if (!isset($types[$contentTypeId])) {
                continue;
            }

            $html .= $this->renderListItem($item, $types[$contentTypeId]);
        }

        return $html;
    }

    private function configuredTypeConfigs(): array
    {
        $configs = [];
        $rows = \DB::query(
            'SELECT ct_id, single_template, list_template, tree_template
             FROM vc_content_types
             ORDER BY ct_id'
        );

        while ($row = \DB::fetchRow($rows)) {
            $contentTypeId = (int)$row['ct_id'];
            $configs[$contentTypeId] = [
                'ct_id' => $contentTypeId,
                'single_template' => (string)($row['single_template'] ?? ''),
                'list_template' => (string)($row['list_template'] ?? ''),
                'tree_template' => (string)($row['tree_template'] ?? ''),
            ];
        }

        return $configs;
    }

    private function effectiveTypeConfigs(mixed $selected): array
    {
        $configs = $this->configuredTypeConfigs();
        if ($configs === []) {
            return [];
        }

        $selectedIds = $this->normalizeContentTypeIds($selected);
        if ($selectedIds !== []) {
            $selectedSet = array_fill_keys($selectedIds, true);
            $configs = array_intersect_key($configs, $selectedSet);
        }

        $allowed = array_fill_keys(\Core\User::getAllowedContentTypeIds('view'), true);
        return array_intersect_key($configs, $allowed);
    }

    private function normalizeContentTypeIds(mixed $value): array
    {
        if (is_string($value) && str_contains($value, ',')) {
            $value = explode(',', $value);
        }

        $values = is_array($value) ? $value : [$value];
        $ids = [];
        foreach ($values as $contentType) {
            if ($contentType === null || $contentType === '') {
                continue;
            }

            try {
                $id = (int)\Core\Content::getContentType(is_numeric($contentType) ? (int)$contentType : (string)$contentType)['ct_id'];
            } catch (\Throwable) {
                continue;
            }

            if ($id > 0) {
                $ids[$id] = $id;
            }
        }

        return array_values($ids);
    }

    private function anchorIsViewable(int $itemId): bool
    {
        $item = \Core\Content::getItem($itemId);
        if ($item === []) {
            return false;
        }

        return \Core\User::canContent((int)$item['ct_id'], 'view');
    }

    private function activePath(?int $rootId, array $types): array
    {
        $current = $this->peekRoutedItem();
        if ($current === null) {
            return [];
        }

        $allowedTypes = array_fill_keys(array_keys($types), true);
        $path = [];
        $visited = [];

        while ($current !== []) {
            $itemId = (int)($current['item_id'] ?? 0);
            if ($itemId < 1 || isset($visited[$itemId])) {
                return [];
            }
            $visited[$itemId] = true;

            if ($rootId !== null && $itemId === $rootId) {
                return $path;
            }

            $contentTypeId = (int)($current['ct_id'] ?? 0);
            if (!isset($allowedTypes[$contentTypeId])) {
                return [];
            }
            $path[$itemId] = true;

            $parentId = $this->positiveIntOrNull($current['parent_id'] ?? null);
            if ($rootId === null && $parentId === null) {
                return $path;
            }
            if ($rootId !== null && $parentId === $rootId) {
                return $path;
            }
            if ($parentId === null) {
                return [];
            }

            $current = \Core\Content::getItem($parentId);
        }

        return [];
    }

    private function listItemIds(
        array $contentTypeIds,
        ?int $parentId,
        ?int $sortFieldId,
        string $sortDirection,
        int $offset,
        int $limit
    ): array {
        $params = [\DB::prepareIdArray($contentTypeIds)];
        $where = ['ci.ct_id=ANY($1::int[])'];

        if ($parentId !== null) {
            $params[] = $parentId;
            $where[] = 'ci.parent_id=$' . count($params);
        }

        $countParams = $params;
        $sort = $this->sortSql('ci', $sortFieldId, $sortDirection, $params);
        $sql = 'SELECT ci.item_id
                FROM content_items ci
                ' . $sort['join'] . '
                WHERE ' . implode(' AND ', $where) . '
                ORDER BY ' . $sort['order'];

        if ($limit > 0) {
            $params[] = $limit;
            $sql .= ' LIMIT $' . count($params) . '::int';
        }
        if ($offset > 0) {
            $params[] = $offset;
            $sql .= ' OFFSET $' . count($params) . '::int';
        }

        $ids = array_map('intval', \DB::getArr($sql, $params));
        $total = (int)(\DB::getOne(
            'SELECT count(*) FROM content_items ci WHERE ' . implode(' AND ', $where),
            $countParams
        ) ?? 0);

        return ['ids' => $ids, 'total' => $total];
    }

    private function fullTreeRows(
        array $contentTypeIds,
        ?int $rootId,
        ?int $sortFieldId,
        string $sortDirection
    ): array {
        $params = [\DB::prepareIdArray($contentTypeIds)];
        if ($rootId === null) {
            $anchor = 'ci.parent_id IS NULL';
        } else {
            $params[] = $rootId;
            $anchor = 'ci.parent_id=$' . count($params);
        }

        $sort = $this->sortSql('tree', $sortFieldId, $sortDirection, $params);
        $result = \DB::query(
            'WITH RECURSIVE tree AS (
                SELECT ci.item_id, ci.parent_id, ci.ct_id, ARRAY[ci.item_id]::bigint[] AS path
                FROM content_items ci
                WHERE ci.ct_id=ANY($1::int[]) AND ' . $anchor . '

                UNION ALL

                SELECT child.item_id, child.parent_id, child.ct_id, parent.path || child.item_id
                FROM content_items child
                JOIN tree parent ON child.parent_id=parent.item_id
                WHERE child.ct_id=ANY($1::int[])
                  AND NOT child.item_id=ANY(parent.path)
            )
            SELECT tree.item_id, tree.parent_id
            FROM tree
            ' . $sort['join'] . '
            ORDER BY tree.parent_id NULLS FIRST, ' . $sort['order'],
            $params
        );

        $rows = [];
        while ($row = \DB::fetchRow($result)) {
            $rows[] = $row;
        }
        return $rows;
    }

    private function directChildren(
        ?int $parentId,
        array $contentTypeIds,
        ?int $sortFieldId,
        string $sortDirection
    ): array {
        $params = [\DB::prepareIdArray($contentTypeIds)];
        if ($parentId === null) {
            $parentCondition = 'ci.parent_id IS NULL';
        } else {
            $params[] = $parentId;
            $parentCondition = 'ci.parent_id=$' . count($params);
        }

        $sort = $this->sortSql('ci', $sortFieldId, $sortDirection, $params);
        $result = \DB::query(
            'SELECT ci.item_id,
                    EXISTS(
                        SELECT 1
                        FROM content_items child
                        WHERE child.parent_id=ci.item_id
                          AND child.ct_id=ANY($1::int[])
                    ) AS has_children
             FROM content_items ci
             ' . $sort['join'] . '
             WHERE ci.ct_id=ANY($1::int[])
               AND ' . $parentCondition . '
             ORDER BY ' . $sort['order'],
            $params
        );

        $rows = [];
        while ($row = \DB::fetchRow($result)) {
            $rows[] = $row;
        }
        return $rows;
    }

    private function sortSql(
        string $itemAlias,
        ?int $fieldId,
        string $direction,
        array &$params
    ): array {
        if ($fieldId === null) {
            return ['join' => '', 'order' => $itemAlias . '.item_id ASC'];
        }

        try {
            $field = \Core\Content::getField($fieldId);
        } catch (\Throwable) {
            return ['join' => '', 'order' => $itemAlias . '.item_id ASC'];
        }

        $settings = array_replace(
            is_array($field['type_settings'] ?? null) ? $field['type_settings'] : [],
            is_array($field['field_settings'] ?? null) ? $field['field_settings'] : []
        );
        $rootType = (string)($field['root_type_name'] ?? 'text');
        if ($rootType === 'compound') {
            return ['join' => '', 'order' => $itemAlias . '.item_id ASC'];
        }

        if (!empty($settings['indexed'])) {
            $table = match ($rootType) {
                'number' => 'item_nums',
                'boolean' => 'item_bools',
                'date' => 'item_dates',
                default => 'item_texts',
            };
            $aggregate = $direction === 'desc' ? 'MAX' : 'MIN';
            $valueExpression = $table === 'item_bools' ? 'sort_value.value::int' : 'sort_value.value';

            $params[] = (int)$field['field_id'];
            $fieldParam = '$' . count($params);
            $languageSql = '';
            if ($table === 'item_texts') {
                $params[] = LANG;
                $languageSql = 'AND (sort_value.lang_code=$' . count($params) . ' OR sort_value.lang_code IS NULL)';
            }

            return [
                'join' => 'LEFT JOIN LATERAL (
                    SELECT ' . $aggregate . '(' . $valueExpression . ') AS value
                    FROM ' . $table . ' sort_value
                    WHERE sort_value.item_id=' . $itemAlias . '.item_id
                      AND sort_value.field_id=' . $fieldParam . '
                      ' . $languageSql . '
                ) vc_sort ON true',
                'order' => 'vc_sort.value ' . strtoupper($direction) . ' NULLS LAST, '
                    . $itemAlias . '.item_id ASC',
            ];
        }

        $fieldName = (string)($field['system_name'] ?? '');
        if ($fieldName === '') {
            return ['join' => '', 'order' => $itemAlias . '.item_id ASC'];
        }

        $params[] = $fieldName;
        $fieldParam = '$' . count($params);
        $params[] = LANG;
        $languageParam = '$' . count($params);
        $scalar = "vc_raw.raw_value #>> '{}'";
        $nonEmptyScalar = "NULLIF(BTRIM({$scalar}), '')";
        $valueExpression = match ($rootType) {
            'number' => '(' . $nonEmptyScalar . ')::numeric',
            'boolean' => '(' . $nonEmptyScalar . ')::boolean::int',
            'date' => '(' . $nonEmptyScalar . ')::timestamptz',
            default => $scalar,
        };

        return [
            'join' => 'LEFT JOIN LATERAL (
                SELECT CASE
                    WHEN jsonb_typeof(vc_raw.raw_value) IN (\'string\', \'number\', \'boolean\')
                    THEN ' . $valueExpression . '
                    ELSE NULL
                END AS value
                FROM (
                    SELECT COALESCE(
                        sort_translation.translated_data -> ' . $fieldParam . '::text,
                        sort_item.common_data -> ' . $fieldParam . '::text
                    ) AS raw_value
                    FROM content_items sort_item
                    LEFT JOIN translations sort_translation
                      ON sort_translation.entity_uuid=sort_item.item_uuid
                     AND sort_translation.lang_code=' . $languageParam . '
                    WHERE sort_item.item_id=' . $itemAlias . '.item_id
                ) vc_raw
            ) vc_sort ON true',
            'order' => 'vc_sort.value ' . strtoupper($direction) . ' NULLS LAST, '
                . $itemAlias . '.item_id ASC',
        ];
    }

    private function normalizeTemplateName(mixed $value): ?string
    {
        $value = trim((string)$value);
        if ($value === '') {
            return null;
        }
        if (!preg_match('/^[A-Za-z0-9][A-Za-z0-9._-]*$/', $value)) {
            throw new \InvalidArgumentException('Invalid template name: ' . $value);
        }
        return $value;
    }

    private function debugValue(mixed $value, array $fieldConfig = []): string
    {
        $fieldType = (string)($fieldConfig['type'] ?? '');
        if ($fieldType === 'media') {
            $values = is_array($value) ? $value : [$value];
            $files = [];
            foreach ($values as $file) {
                if (!is_scalar($file) || trim((string)$file) === '') {
                    continue;
                }
                $path = parse_url((string)$file, PHP_URL_PATH);
                $files[] = basename(is_string($path) && $path !== '' ? $path : (string)$file);
            }
            return implode(', ', $files);
        }

        if ($value === null) {
            return '';
        }
        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }
        if (is_scalar($value)) {
            return (string)$value;
        }

        return \Core\Utils\JsonTool::encode($value, false);
    }

    private function sortDirection(mixed $value): string
    {
        return strtolower(trim((string)$value)) === 'desc' ? 'desc' : 'asc';
    }

    private function positiveIntOrNull(mixed $value): ?int
    {
        if ($value === null || $value === '' || !is_numeric($value)) {
            return null;
        }
        $value = (int)$value;
        return $value > 0 ? $value : null;
    }


    private function managerUrl(string $action = 'config'): string
    {
        $url = \Core\Url::path((string) PAGE_SLUG);

        return $action === 'config'
            ? $url
            : \Core\Url::pathParams(
                $url,
                ['action' => $action],
                $this->prefix
            );
    }

    private function pagination(): \Plugins\Pagination\Pagination
    {
        $plugin = $this->plugins->get('Pagination');
        if (!$plugin instanceof \Plugins\Pagination\Pagination) {
            throw new \RuntimeException('Pagination plugin is not available.');
        }
        return $plugin;
    }

    private function notice(string $message, string $kind = 'success'): string
    {
        return '<div class="kc-notice kc-notice-' . \Core\Html::escape($kind) . '">'
            . \Core\Html::escape($message)
            . '</div>';
    }

}
