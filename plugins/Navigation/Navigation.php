<?php

/**
 * KamiCore
 *
 * SPDX-License-Identifier: Apache-2.0
 *
 * @see https://kamicore.org
 */

namespace Plugins\Navigation;

if(!IN_KAMI) die();

class Navigation extends \Core\BasePlugin {

	public function init() {

	}

	public function show(array $instance_params):string {
		return $this->showMenu($instance_params);
	}

	public function showMenu(array $instance_params):string {
		$menuId = (int)($instance_params['menu_id'] ?? 0);
		$template = (string)($instance_params['template'] ?? '');
		if ($menuId < 1 || $template === '') {
			return '';
		}

		$groupId = defined('USERGROUP_ID')
			? (int)USERGROUP_ID
			: (int)(\Core\User::getGroup()['usergroup_id'] ?? 0);
		$cacheKey = $this->menuCacheKey($menuId, $groupId);
		$cached = \Cache::get($cacheKey);
		if (is_array($cached) && array_key_exists('html', $cached)) {
			return (string)$cached['html'];
		}

		$menuItem = \Core\Content::getItem($menuId);

		$menuData = is_array($menuItem['data'] ?? null) ? $menuItem['data'] : [];
		if (!$menuItem || !$this->isVisibleToGroup($menuData, $groupId)) {
			\Cache::set($cacheKey, ['html' => '']);
			return '';
		}

		$instance_params['menu_items'] = $this->visibleMenuItemTemplates($menuId, $groupId, $template);
		$menu = $this->render($template, $instance_params);
		\Cache::set($cacheKey, ['html' => $menu]);

		return $menu;
	}

	public function list(array $instance_params = []): string {
		$menuType = \Core\Content::getContentType('navmenu');
		$menuIds = \DB::getArr(
			'SELECT item_id FROM content_items WHERE ct_id=$1 ORDER BY item_id',
			[(int)$menuType['ct_id']]
		);

		$rows = [];
		foreach ($menuIds as $menuId) {
			$menu = \Core\Content::getItem((int)$menuId);
			if (!$menu) {
				continue;
			}

			$data = is_array($menu['data'] ?? null) ? $menu['data'] : [];
			$title = (string)($data['menu_title'] ?? ('#' . $menuId));
			$rows[] = [
				'template' => 'menu_list_row',
				'params' => [
					'menu_key' => $this->escape((string)($data['menu_key'] ?? '')),
					'menu_title' => $this->escape($title),
					'menu_description' => $this->escape((string)($data['menu_description'] ?? '')),
					'title_attribute' => $this->escape($title),
					'edit_link' => $this->managerUrl('edit', (int)$menuId),
					'delete_link' => $this->managerUrl('delete', (int)$menuId),
					'text_edit' => $this->managerText('edit', 'Edit'),
				],
			];
		}

		$count = count($rows);
		return $this->render('menu_list', [
			'menu_rows' => $rows !== []
				? $rows
				: [[
					'template' => 'menu_list_empty',
					'params' => [],
				]],
			'menu_count' => (string)$count,
			'menu_summary' => $this->escape(str_replace(
				'{count}',
				(string)$count,
				$this->phrases['menu_count'] ?? '{count} menus'
			)),
			'create_action' => $this->managerUrl('create'),
			'create_fields' => $this->renderMenuFields(),
			'text_menu_key' => $this->managerText('menu_key', 'Menu key'),
			'ui_text' => $this->jsonForHtml([
				'confirmDeleteMenu' => $this->phrases['confirm_delete_menu']
					?? 'Delete “{title}”? This action cannot be undone.',
				'menuDeleted' => $this->phrases['menu_deleted'] ?? 'Menu deleted.',
				'deleteFailed' => $this->phrases['delete_failed'] ?? 'Failed to delete the menu.',
				'menuCount' => $this->phrases['menu_count'] ?? '{count} menus',
				'noMenus' => $this->phrases['no_menus'] ?? 'No menus found.',
			]),
		]);
	}

	public function create(array $instance_params = []): string {
		$data = $this->menuDataFromRequest();
		if (trim((string)($data['menu_key'] ?? '')) === '') {
			return $this->notice(
				$this->phrases['menu_key_required'] ?? 'Menu key is required.',
				'error'
			);
		}
		if (trim((string)($data['menu_title'] ?? '')) === '') {
			return $this->notice(
				$this->phrases['menu_name_required'] ?? 'Menu name is required.',
				'error'
			);
		}

		$menuId = \Core\Content::create('navmenu', ['plugin_id' => $this->id]);
		\Core\Content::update($menuId, $data);

		return $this->redirect($this->managerUrl('edit', $menuId));
	}

	public function edit(array $instance_params = []): string {
		$menuId = $this->requestedMenuId();
		$menu = $this->getMenu($menuId);
		if (!$menu) {
			return $this->notice(
				$this->phrases['menu_not_found'] ?? 'Menu not found.',
				'error'
			);
		}

		$menuData = is_array($menu['data'] ?? null) ? $menu['data'] : [];
		return $this->render('menu_edit', [
			'menu_id' => (string)$menuId,
			'menu_title' => $this->escape((string)($menuData['menu_title'] ?? '')),
			'menu_fields' => $this->renderMenuFields($menuData),
			'menu_key_warning' => $this->escape($this->managerText(
				'menu_key_warning',
				'Changing this key may break recipes, plugin settings, or theme bindings. Change it only if you understand the consequences.'
			)),
			'menu_items' => $this->menuItemTemplates($menuId),
			'new_item_visibility_field' => $this->renderItemVisibilityField([], 'menu-groups-new'),
			'save_action' => $this->managerUrl('save', $menuId),
			'cancel_url' => $this->managerUrl(),
		]);
	}

	public function save(array $instance_params = []): string {
		$menuId = $this->requestedMenuId();
		$menu = $this->getMenu($menuId);
		if (!$menu) {
			return $this->notice(
				$this->phrases['menu_not_found'] ?? 'Menu not found.',
				'error'
			);
		}

		$request = \Core\Request::all();
		$menuData = $this->menuDataFromRequest($request);
		if (trim((string)($menuData['menu_key'] ?? '')) === '') {
			return $this->notice(
				$this->phrases['menu_key_required'] ?? 'Menu key is required.',
				'error'
			);
		}
		if (trim((string)($menuData['menu_title'] ?? '')) === '') {
			return $this->notice(
				$this->phrases['menu_name_required'] ?? 'Menu name is required.',
				'error'
			);
		}

		$items = is_array($request['items'] ?? null) ? $request['items'] : [];
		$allowedIds = $this->menuItemIdSet($menuId);
		$keptIds = [];

		\Core\Content::update($menuId, $menuData);
		$this->saveItems($menuId, $items, $keptIds, $allowedIds);
		$this->deleteRemovedItems($menuId, $keptIds);
		$this->invalidateMenuCache($menuId);

		return $this->redirect($this->managerUrl('edit', $menuId));
	}

	public function delete(array $instance_params = []): string {
		$menuId = $this->requestedMenuId();
		$menu = $this->getMenu($menuId);
		if (!$menu) {
			return $this->jsonResponse([
				'status' => 'error',
				'error' => $this->phrases['menu_not_found'] ?? 'Menu not found.',
			], 404);
		}

		$this->deleteMenuItemTree($menuId);
		\Core\Content::delete($menuId);
		$this->invalidateMenuCache($menuId);

		return $this->jsonResponse([
			'status' => 'ok',
			'message' => $this->phrases['menu_deleted'] ?? 'Menu deleted.',
		]);
	}

	public function findMenuByKey(string $menuKey): ?array {
		$menuKey = trim($menuKey);
		if ($menuKey === '') {
			throw new \InvalidArgumentException('Menu key is required.');
		}

		$ids = \Core\Content::findByField('menu_key', $menuKey, 'navmenu');
		if (count($ids) > 1) {
			throw new \RuntimeException("Menu key is not unique: {$menuKey}.");
		}
		if ($ids === []) {
			return null;
		}

		$menu = $this->getMenu((int)$ids[0]);
		return $menu ?: null;
	}

	public function appendPageLink(
		int $menuId,
		string $url,
		array $titles,
		string $defaultLanguage = 'en'
	): array {
		$menu = $this->getMenu($menuId);
		if (!$menu) {
			throw new \OutOfBoundsException("Unknown navigation menu: {$menuId}.");
		}

		$url = trim($url);
		if ($url === '') {
			throw new \InvalidArgumentException('Navigation URL is required.');
		}

		$defaultLanguage = trim($defaultLanguage) ?: 'en';
		$defaultTitle = trim((string)($titles[$defaultLanguage] ?? ''));
		if ($defaultTitle === '') {
			foreach ($titles as $title) {
				$defaultTitle = trim((string)$title);
				if ($defaultTitle !== '') {
					break;
				}
			}
		}
		if ($defaultTitle === '') {
			throw new \InvalidArgumentException('Navigation item title is required.');
		}
		$titles[$defaultLanguage] = $defaultTitle;

		$itemTypeId = $this->navItemTypeId();
		$displayOrder = (int)(\DB::getOne(
			"select coalesce(max((common_data->>'displayorder')::int), -1) + 1
			from content_items
			where parent_id=$1 and ct_id=$2",
			[$menuId, $itemTypeId]
		) ?? 0);

		$itemId = \Core\Content::create('navmenu_item', [
			'plugin_id' => $this->id,
			'parent_id' => $menuId,
		]);

		\Core\Content::update($itemId, [
			'parent_id' => $menuId,
			'item_type' => 'link',
			'item_title' => $defaultTitle,
			'item_url' => $url,
			'item_icon' => '',
			'visible_to_groups' => [],
			'displayorder' => $displayOrder,
		], $defaultLanguage);

		foreach ($titles as $language => $title) {
			$language = trim((string)$language);
			$title = trim((string)$title);
			if ($language === '' || $title === '' || $language === $defaultLanguage) {
				continue;
			}
			\Core\Content::update($itemId, ['item_title' => $title], $language);
		}

		$itemUuid = (string)(\DB::getOne(
			'select item_uuid from content_items where item_id=$1',
			[$itemId]
		) ?? '');
		$this->invalidateMenuCache($menuId);

		return [
			'item_id' => $itemId,
			'item_uuid' => $itemUuid,
			'displayorder' => $displayOrder,
		];
	}

	private function saveItems(
		int $parentId,
		array $items,
		array &$keptIds,
		array $allowedIds
	): void {
		$displayOrder = 0;

		foreach ($items as $item) {
			if (!is_array($item)) {
				continue;
			}

			$itemId = (int)($item['item_id'] ?? 0);
			$isNew = $itemId < 1;
			if (!$isNew) {
				if (!isset($allowedIds[$itemId])) {
					throw new \InvalidArgumentException('Menu item does not belong to this menu.');
				}
			} else {
				$itemId = \Core\Content::create('navmenu_item', [
					'plugin_id' => $this->id,
					'parent_id' => $parentId,
				]);
			}

			$itemData = [
				'parent_id' => $parentId,
				'displayorder' => $displayOrder++,
				'item_title' => (string)($item['item_title'] ?? ''),
				'item_url' => (string)($item['item_url'] ?? ''),
				'item_icon' => (string)($item['item_icon'] ?? ''),
				'visible_to_groups' => $this->normalizeGroupIds($item['visible_to_groups'] ?? []),
			];
			if ($isNew) {
				$itemData['item_type'] = 'link';
			}

			\Core\Content::update($itemId, $itemData);
			$keptIds[$itemId] = true;

			$children = is_array($item['children'] ?? null) ? $item['children'] : [];
			$this->saveItems($itemId, $children, $keptIds, $allowedIds);
		}
	}

	private function deleteRemovedItems(int $menuId, array $keptIds): void {
		$itemTypeId = $this->navItemTypeId();
		$rows = \DB::query(
			'WITH RECURSIVE menu_tree AS (
				SELECT item_id, parent_id, 1 AS depth
				FROM content_items
				WHERE parent_id=$1 AND ct_id=$2
				UNION ALL
				SELECT child.item_id, child.parent_id, tree.depth + 1
				FROM content_items child
				JOIN menu_tree tree ON child.parent_id=tree.item_id
				WHERE child.ct_id=$2
			)
			SELECT item_id, depth FROM menu_tree ORDER BY depth DESC, item_id DESC',
			[$menuId, $itemTypeId]
		);

		while ($row = \DB::fetchRow($rows)) {
			$itemId = (int)$row['item_id'];
			if (!isset($keptIds[$itemId])) {
				\Core\Content::delete($itemId);
			}
		}
	}

	private function deleteMenuItemTree(int $parentId): void {
		$childIds = \DB::getArr(
			'SELECT item_id FROM content_items WHERE parent_id=$1 AND ct_id=$2',
			[$parentId, $this->navItemTypeId()]
		);

		foreach ($childIds as $childId) {
			$childId = (int)$childId;
			$this->deleteMenuItemTree($childId);
			\Core\Content::delete($childId);
		}
	}

	private function menuItemTemplates(int $parentId): array {
		$templates = [];
		$childIds = \DB::getArr(
			"SELECT item_id
			 FROM content_items
			 WHERE parent_id=$1 AND ct_id=$2
			 ORDER BY COALESCE(NULLIF(common_data->>'displayorder', '')::int, 0), item_id",
			[$parentId, $this->navItemTypeId()]
		);

		foreach ($childIds as $childId) {
			$item = \Core\Content::getItem((int)$childId);
			if (!$item || ($item['content_type_name'] ?? '') !== 'navmenu_item') {
				continue;
			}

			$data = is_array($item['data'] ?? null) ? $item['data'] : [];
			$templates[] = [
				'template' => 'menu_edit_row',
				'params' => [
					'id' => (string)(int)$childId,
					'item_title' => $this->escape((string)($data['item_title'] ?? '')),
					'item_url' => $this->escape((string)($data['item_url'] ?? '')),
					'item_icon' => $this->escape((string)($data['item_icon'] ?? '')),
					'visibility_field' => $this->renderItemVisibilityField(
						$data['visible_to_groups'] ?? [],
						'menu-groups-' . (int)$childId
					),
					'menu_children' => $this->menuItemTemplates((int)$childId),
				],
			];
		}

		return $templates;
	}

	private function menuItemIdSet(int $menuId): array {
		$itemTypeId = $this->navItemTypeId();
		$ids = \DB::getArr(
			'WITH RECURSIVE menu_tree AS (
				SELECT item_id
				FROM content_items
				WHERE parent_id=$1 AND ct_id=$2
				UNION ALL
				SELECT child.item_id
				FROM content_items child
				JOIN menu_tree tree ON child.parent_id=tree.item_id
				WHERE child.ct_id=$2
			)
			SELECT item_id FROM menu_tree',
			[$menuId, $itemTypeId]
		);

		return array_fill_keys(array_map('intval', $ids), true);
	}

	private function navItemTypeId(): int {
		return (int)\Core\Content::getContentType('navmenu_item')['ct_id'];
	}

	private function renderMenuFields(array $values = []): string {
		$fields = \Core\Content::getStructure('navmenu');
		uasort($fields, static fn(array $left, array $right): int =>
			((int)($left['displayorder'] ?? 0)) <=> ((int)($right['displayorder'] ?? 0))
		);

		$html = '';
		foreach ($fields as $fieldName => $field) {
			if (!is_array($field) || !empty($field['settings']['hidden'])) {
				continue;
			}

			$field['name'] = (string)$fieldName;
			$field['value'] = $values[$fieldName] ?? $field['default'] ?? '';
			$html .= $this->forms()->renderField($field);
		}

		return $html;
	}

	private function menuDataFromRequest(?array $request = null): array {
		$request ??= \Core\Request::all();
		return [
			'menu_key' => trim((string)($request['menu_key'] ?? '')),
			'menu_title' => trim((string)($request['menu_title'] ?? '')),
			'menu_description' => trim((string)($request['menu_description'] ?? '')),
			'visible_to_groups' => $this->normalizeGroupIds($request['visible_to_groups'] ?? []),
		];
	}

	private function requestedMenuId(): int {
		return (int)$this->param('id', 0);
	}

	private function getMenu(int $menuId): array {
		if ($menuId < 1) {
			return [];
		}

		$menu = \Core\Content::getItem($menuId);
		return $menu && ($menu['content_type_name'] ?? '') === 'navmenu' ? $menu : [];
	}

	private function managerUrl(string $action = 'list', ?int $menuId = null): string {
		$url = '/' . trim((string)PAGE_SLUG, '/');
		if ($action !== 'list') {
			$url .= '/' . $this->prefix . '-action/' . $action;
		}
		if ($menuId !== null && $menuId > 0) {
			$url .= '/' . $this->prefix . '-id/' . $menuId;
		}
		return $url;
	}

	private function visibleMenuItemTemplates(int $parentId, int $groupId, string $menuTemplate): array {
		$templates = [];

		$childIds = \DB::getArr(
			"SELECT item_id
			 FROM content_items
			 WHERE parent_id=$1 AND ct_id=$2
			 ORDER BY COALESCE(NULLIF(common_data->>'displayorder', '')::int, 0), item_id",
			[$parentId, $this->navItemTypeId()]
		);

		foreach ($childIds as $childId) {
			$item = \Core\Content::getItem((int)$childId);

			if (!$item || ($item['content_type_name'] ?? '') !== 'navmenu_item') {
				continue;
			}

			$data = is_array($item['data'] ?? null) ? $item['data'] : [];
			if (!$this->isVisibleToGroup($data, $groupId)) {
				continue;
			}

			if(!empty($data['item_icon'])) {
				$data['rendered_icon'] = $this->render('item_icon', ['icon' => $data['item_icon']]);
			}

			$data['menu_children'] = $this->visibleMenuItemTemplates((int)$childId, $groupId, $menuTemplate);
			if(is_array($data['menu_children']) && count($data['menu_children'])) {
				$data['submenu'] = $this->render('topnav_children', ['menu_children' => $data['menu_children']]);
			}
			$templates[] = [
				'template' => $menuTemplate.'_item',
				'params' => $data,
			];
		}

		return $templates;
	}

	private function isVisibleToGroup(array $data, int $groupId): bool {
		$groups = $this->normalizeGroupIds($data['visible_to_groups'] ?? []);
		return $groups === [] || in_array($groupId, $groups, true);
	}

	private function normalizeGroupIds(mixed $value): array {
		$values = is_array($value) ? $value : [$value];
		$ids = [];
		foreach ($values as $groupId) {
			if (!is_scalar($groupId) || !is_numeric($groupId)) {
				continue;
			}
			$groupId = (int)$groupId;
			if ($groupId > 0) {
				$ids[$groupId] = $groupId;
			}
		}
		return array_values($ids);
	}

	private function renderItemVisibilityField(mixed $value, string $id): string {
		$structure = \Core\Content::getStructure('navmenu_item');
		$field = $structure['visible_to_groups'] ?? null;
		if (!is_array($field)) {
			return '';
		}

		$field['name'] = 'visible_to_groups';
		$field['id'] = $id;
		$field['value'] = $this->normalizeGroupIds($value);
		$field['attributes'] = array_replace(
			is_array($field['attributes'] ?? null) ? $field['attributes'] : [],
			['class' => 'menu-groups-input']
		);
		return $this->forms()->renderField($field);
	}

	private function menuCacheKey(int $menuId, int $groupId, ?string $language = null): string {
		$language ??= defined('LANG') ? LANG : (string)(DOMAIN_CONFIG['default_language'] ?? 'en');
		return "globals:menu_{$menuId}:{$language}:group_{$groupId}";
	}

	private function invalidateMenuCache(int $menuId): void {
		$languages = array_map(
			'strval',
			\DB::getArr('SELECT lang_code FROM languages WHERE is_active ORDER BY lang_code')
		);
		if ($languages === []) {
			$languages = [defined('LANG') ? LANG : (string)(DOMAIN_CONFIG['default_language'] ?? 'en')];
		}

		$groupIds = array_map(
			'intval',
			\DB::getArr('SELECT usergroup_id FROM usergroups ORDER BY usergroup_id')
		);
		if ($groupIds === []) {
			$groupIds = [defined('USERGROUP_ID') ? (int)USERGROUP_ID : 0];
		}

		foreach (array_unique($languages) as $language) {
			foreach (array_unique($groupIds) as $groupId) {
				\Cache::del($this->menuCacheKey($menuId, (int)$groupId, (string)$language));
			}
		}
	}

	private function forms(): \Plugins\Forms\Forms {
		$forms = $this->plugins->get('Forms');
		if (!$forms instanceof \Plugins\Forms\Forms) {
			throw new \RuntimeException('Forms plugin is required by Navigation manager.');
		}
		return $forms;
	}

	private function redirect(string $url): string {
		\Core\Response::addHeader('Location: ' . $url, true, 302);
		return '';
	}

	private function jsonResponse(array $data, int $status = 200): string {
		\Core\Response::addHeader('Content-Type: application/json; charset=utf-8', true, $status);
		return json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
			?: '{"status":"error"}';
	}

	private function jsonForHtml(array $data): string {
		return json_encode(
			$data,
			JSON_UNESCAPED_UNICODE
			| JSON_UNESCAPED_SLASHES
			| JSON_HEX_TAG
			| JSON_HEX_AMP
			| JSON_HEX_APOS
			| JSON_HEX_QUOT
		) ?: '{}';
	}

	private function managerText(string $key, string $fallback): string {
		return $this->escape((string)($this->phrases[$key] ?? $fallback));
	}

	private function notice(string $message, string $kind = 'success'): string {
		return '<div class="kc-notice kc-notice-' . $this->escape($kind) . '">'
			. $this->escape($message)
			. '</div>';
	}

	private function escape(string $value): string {
		return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
	}
}
