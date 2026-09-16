<?php

/**
 * KamiCore
 *
 * SPDX-License-Identifier: Apache-2.0
 *
 * @see https://kamicore.org
 */

namespace Plugins\ContentManager;

if(!IN_KAMI) die();

class ContentManager extends \Core\BasePlugin {

	private ?\Plugins\Forms\Forms $forms = null;
	private ?\Plugins\Pagination\Pagination $pagination = null;

	/**
	 * Compatibility aliases for structures already installed in the database.
	 *
	 * @var array<string, array<int, string>>
	 */
	private const FIELD_LEGACY_NAMES = ['static_content_body' => ['content']];
	private const API_ITEMS_DEFAULT_LIMIT = 20;
	private const API_ITEMS_MAX_LIMIT = 100;

	public function typeList($context_vars) {
		$this->addCss('/plugins/ContentManager/assets/content-manager.css');

		$types = [];

		$rows = \DB::query(
			'select * from content_types
			where manager_plugin_id=$1',
			[$this->id]
		);
		while ($row = \DB::fetchRow($rows)) {
			$items_count = \DB::getOne(
				'select count(*) from content_items where ct_id=$1',
				[(int)$row['ct_id']]
			);
			$translation = \Core\Translation::get($row['uuid']) ?? [];
			$types[] = [
				"type_id" => $row['ct_id'],
				"title" => $translation['title'] ?? ucwords(str_replace(['_', '-'], ' ', $row['system_name'])),
				"description" => $translation['description'] ?? null,
				"items_count" => $items_count,
				"url_items" => PAGE_SLUG."/cm-action/itemList/cm-type/{$row['ct_id']}",
				"url_edit" => PAGE_SLUG."/cm-action/typeEdit/cm-type/{$row['ct_id']}",
			];
		}
		$types = \Core\Translation::sortByTitle($types, 'title', null);

		$typesJson = \Core\Utils\JsonTool::encodeForHtml($types);
		$uiText = \Core\Utils\JsonTool::encodeForHtml([
			'typeCount' => $this->phrases['type_count'] ?? '{count} content types',
			'noTypes' => $this->phrases['no_content_types'] ?? 'No content types found.',
			'openItems' => $this->phrases['open_items'] ?? 'Open items',
			'editStructure' => $this->phrases['edit_structure'] ?? 'Edit structure',
			'deleteType' => $this->phrases['delete_content_type'] ?? 'Delete content type',
			'confirmDeleteType' => $this->phrases['confirm_delete_content_type']
				?? 'Delete "{title}"? This action cannot be undone.',
			'deleteFailed' => $this->phrases['delete_content_type_failed']
				?? 'Failed to delete the content type.',
			'typeDeleted' => $this->phrases['content_type_deleted'] ?? 'Content type deleted.',
		]) ?: '{}';

		$managerTools = '';
		if ($this->isRoot()) {
			$managerTools = '<a class="admin-button admin-button-secondary" href="/'
				. PAGE_SLUG . '/cm-action/fieldList">'
				. '<svg class="icon icon-menu icon-sm"></svg>'
				. '<span>'
				. \Core\Html::escape($this->phrases['manage_fields'] ?? 'Fields')
				. '</span></a>'
				. '<a class="admin-button admin-button-secondary" href="/'
				. PAGE_SLUG . '/cm-action/typeManagerList">'
				. '<svg class="icon icon-settings icon-sm"></svg>'
				. '<span>'
				. \Core\Html::escape($this->phrases['manage_type_managers'] ?? 'Type managers')
				. '</span></a>';
		}

		return $this->render('types-list', [
			'types_json' => $typesJson,
			'ui_text' => $uiText,
			'manager_tools' => $managerTools,
			'create_link' => '/' . PAGE_SLUG . '/cm-action/typeEdit',
			'delete_endpoint' => '/ajax/ContentManager/typeDelete',
		]);
	}

	public function fieldList($contextVars): string {
		$this->addCss('/plugins/ContentManager/assets/content-manager.css');
		if (!$this->isRoot()) {
			return $this->notice(
				$this->phrases['root_only'] ?? 'Root access required.',
				'error'
			);
		}

		$usageByField = [];
		$typeRows = \DB::query('select ct_id, uuid, system_name, schema from content_types');
		while ($type = \DB::fetchRow($typeRows)) {
			$schema = \Core\Utils\JsonTool::decodeArray($type['schema'] ?? null);
			$fields = is_array($schema['fields'] ?? null) ? $schema['fields'] : [];
			if ($fields === []) continue;

			$translation = \Core\Translation::get((string)$type['uuid']) ?? [];
			$title = (string)($translation['title'] ?? $type['system_name']);
			foreach (array_keys($fields) as $fieldName) {
				$usageByField[(string)$fieldName][(int)$type['ct_id']] = $title;
			}
		}

		$fields = [];
		$rows = \DB::query(
			"select f.field_id, f.uuid, f.system_name, ft.system_name as type_name,\n"
			. "case when (\n"
			. "\texists(select 1 from item_texts v where v.field_id=f.field_id)\n"
			. "\tor exists(select 1 from item_nums v where v.field_id=f.field_id)\n"
			. "\tor exists(select 1 from item_bools v where v.field_id=f.field_id)\n"
			. "\tor exists(select 1 from item_dates v where v.field_id=f.field_id)\n"
			. "\tor exists(select 1 from content_items i\n"
			. "\t\twhere coalesce(i.common_data, '{}'::jsonb) ? f.system_name)\n"
			. "\tor exists(select 1 from translations t\n"
			. "\t\tjoin content_items i on i.item_uuid=t.entity_uuid\n"
			. "\t\twhere coalesce(t.translated_data, '{}'::jsonb) ? f.system_name)\n"
			. ") then 1 else 0 end as has_values\n"
			. "from fields f\n"
			. "join field_types ft on ft.type_id=f.type_id"
		);
		while ($field = \DB::fetchRow($rows)) {
			$translation = \Core\Translation::get((string)$field['uuid']) ?? [];
			$usageMap = $usageByField[(string)$field['system_name']] ?? [];
			$usageCount = count($usageMap);
			$usage = \Core\Translation::sortByTitle(array_map(
				static fn(string $title): array => ['title' => $title],
				array_values($usageMap)
			));
			$usage = array_column($usage, 'title');
			$hasValues = (int)$field['has_values'] === 1;

			$fields[] = [
				'field_id' => (int)$field['field_id'],
				'title' => (string)($translation['title'] ?? $field['system_name']),
				'description' => (string)($translation['description'] ?? ''),
				'system_name' => (string)$field['system_name'],
				'type_name' => (string)$field['type_name'],
				'usage' => $usage,
				'usage_count' => $usageCount,
				'has_values' => $hasValues,
				'deletable' => $usage === [],
				'edit_url' => '/' . PAGE_SLUG
					. '/cm-action/globalFieldEdit/cm-field/' . (int)$field['field_id'],
			];
		}
		$fields = \Core\Translation::sortByTitle($fields, 'title', 'system_name');

		return $this->render('fields-list', [
			'fields_json' => \Core\Utils\JsonTool::encodeForHtml($fields),
			'ui_text' => \Core\Utils\JsonTool::encode([
				'fieldCount' => $this->phrases['field_count'] ?? '{count} fields',
				'noFields' => $this->phrases['no_fields'] ?? 'No fields found.',
				'unused' => $this->phrases['field_unused'] ?? 'Unused',
				'storedValues' => $this->phrases['stored_values'] ?? 'Stored values',
				'noStoredValues' => $this->phrases['no_stored_values'] ?? 'No stored values',
				'editField' => $this->phrases['edit_global_field'] ?? 'Edit global field',
				'deleteField' => $this->phrases['delete_field_globally'] ?? 'Delete field globally',
				'confirmDelete' => $this->phrases['confirm_delete_field']
					?? 'Delete \"{title}\"? This action cannot be undone.',
				'confirmDeleteWithValues' => $this->phrases['confirm_delete_field_with_values']
					?? 'Delete \"{title}\" and all remaining stored values? This action cannot be undone.',
				'deleteFailed' => $this->phrases['delete_field_failed']
					?? 'Failed to delete the field.',
				'deleted' => $this->phrases['field_deleted'] ?? 'Field deleted.',
				'usedLock' => $this->phrases['field_used_lock']
					?? 'Detach this field from all content types before deleting it.',
			], false),
			'back_link' => '/' . PAGE_SLUG . '/cm-action/typeList',
			'delete_endpoint' => '/ajax/ContentManager/fieldDelete',
		]);
	}

	public function typeManagerList($contextVars): string {
		$this->addCss('/plugins/ContentManager/assets/content-manager.css');
		if (!$this->isRoot()) {
			return $this->accessDeniedNotice();
		}

		$plugins = [];
		$pluginRows = \DB::query(
			'select plugin_id, uuid, system_name, is_active
			from plugins'
		);
		while ($plugin = \DB::fetchRow($pluginRows)) {
			$translation = \Core\Translation::get($plugin['uuid']) ?? [];
			$plugins[] = [
				'id' => (int)$plugin['plugin_id'],
				'name' => (string)$plugin['system_name'],
				'title' => (string)($translation['title'] ?? $plugin['system_name']),
				'active' => !empty($plugin['is_active']),
			];
		}
		$plugins = \Core\Translation::sortByTitle($plugins, 'title', 'name');

		$rows = [];
		$typeRows = \DB::query(
			'select ct.ct_id, ct.uuid, ct.system_name, ct.plugin_id,
				ct.default_manager_plugin_id, ct.manager_plugin_id,
				ct.manager_overridden,
				owner.system_name as owner_name,
				default_manager.system_name as default_manager_name
			from content_types ct
			left join plugins owner on owner.plugin_id=ct.plugin_id
			left join plugins default_manager
				on default_manager.plugin_id=ct.default_manager_plugin_id'
		);
		while ($type = \DB::fetchRow($typeRows)) {
			$translation = \Core\Translation::get($type['uuid']) ?? [];
			$options = '<option value="">'
				. \Core\Html::escape($this->phrases['no_manager'] ?? 'No manager')
				. '</option>';

			foreach ($plugins as $plugin) {
				$selected = (int)$type['manager_plugin_id'] === $plugin['id']
					? ' selected'
					: '';
				$label = $plugin['title'] . ' (' . $plugin['name'] . ')';
				if (!$plugin['active']) {
					$label .= ' — ' . ($this->phrases['inactive'] ?? 'inactive');
				}
				$options .= '<option value="' . $plugin['id'] . '"' . $selected . '>'
					. \Core\Html::escape($label)
					. '</option>';
			}

			$rows[] = [
				'template' => 'type-manager-row',
				'params' => [
					'type_id' => (int)$type['ct_id'],
					'type_title' => \Core\Html::escape((string)($translation['title']
							?? ucwords(str_replace(['_', '-'], ' ', $type['system_name'])))),
					'system_name' => \Core\Html::escape((string)$type['system_name']),
					'owner_name' => \Core\Html::escape((string)($type['owner_name']
							?? ($this->phrases['no_owner'] ?? 'No owner'))),
					'default_manager' => \Core\Html::escape((string)($type['default_manager_name']
							?? ($this->phrases['no_manager'] ?? 'No manager'))),
					'manager_options' => $options,
					'override_label' => \Core\Html::escape(!empty($type['manager_overridden'])
							? ($this->phrases['overridden'] ?? 'Overridden')
							: ($type['plugin_id'] === null
								? ($this->phrases['manual_default'] ?? 'Manual default')
								: ($this->phrases['from_manifest'] ?? 'From manifest'))),
					'override_class' => !empty($type['manager_overridden'])
						? ' cm-manager-badge-overridden'
						: '',
					'default_source' => \Core\Html::escape($type['plugin_id'] === null
							? ($this->phrases['manual_default'] ?? 'Manual default')
							: ($this->phrases['from_manifest'] ?? 'From manifest')),
					'reset_disabled' => empty($type['manager_overridden'])
						? ' disabled'
						: '',
				],
			];
		}
		usort($rows, static fn(array $a, array $b): int => \Core\Translation::compareTitles(
			html_entity_decode((string)($a['params']['type_title'] ?? ''), ENT_QUOTES, 'UTF-8'),
			html_entity_decode((string)($b['params']['type_title'] ?? ''), ENT_QUOTES, 'UTF-8')
		));

		$uiText = \Core\Utils\JsonTool::encodeForHtml([
			'saving' => $this->phrases['saving_manager'] ?? 'Saving…',
			'saveFailed' => $this->phrases['manager_save_failed']
				?? 'Failed to save the content type manager.',
			'saved' => $this->phrases['manager_saved'] ?? 'Manager saved.',
			'reset' => $this->phrases['manager_reset'] ?? 'Default manager restored.',
			'fromManifest' => $this->phrases['from_manifest'] ?? 'From manifest',
			'manualDefault' => $this->phrases['manual_default'] ?? 'Manual default',
			'overridden' => $this->phrases['overridden'] ?? 'Overridden',
		]) ?: '{}';

		return $this->render('type-managers', [
			'manager_rows' => $rows,
			'back_link' => '/' . PAGE_SLUG . '/cm-action/typeList',
			'ui_text' => $uiText,
		]);
	}

	public function typeManagerUpdate(?array $data): string {
		if (!$this->isRoot()) {
			\Core\Response::addHeader('HTTP/1.1 403 Forbidden');
			return \Core\Utils\JsonTool::encode([
				'status' => 'error',
				'error' => $this->phrases['root_only'] ?? 'Root access required.',
			], false);
		}

		$data = array_replace($data ?? [], \Core\Request::all());
		$typeId = (int)($data['ct_id'] ?? 0);
		$mode = (string)($data['mode'] ?? 'override');
		$type = \DB::getRow(
			'select ct_id, default_manager_plugin_id from content_types where ct_id=$1',
			[$typeId]
		);
		if (!$type) {
			\Core\Response::addHeader('HTTP/1.1 404 Not Found');
			return \Core\Utils\JsonTool::encode([
				'status' => 'error',
				'error' => $this->phrases['content_type_not_found'] ?? 'Content type not found.',
			], false);
		}

		if ($mode === 'reset') {
			$managerId = $type['default_manager_plugin_id'] !== null
				? (int)$type['default_manager_plugin_id']
				: null;
			$overridden = false;
		} elseif ($mode === 'override') {
			$rawManagerId = $data['manager_plugin_id'] ?? null;
			$managerId = $rawManagerId === '' || $rawManagerId === null
				? null
				: (int)$rawManagerId;
			if ($managerId !== null && ($managerId < 1 || !\DB::getOne(
				'select 1 from plugins where plugin_id=$1',
				[$managerId]
			))) {
				return \Core\Utils\JsonTool::encode([
					'status' => 'error',
					'error' => $this->phrases['manager_not_found'] ?? 'Manager plugin not found.',
				], false);
			}
			$overridden = true;
		} else {
			return \Core\Utils\JsonTool::encode([
				'status' => 'error',
				'error' => $this->phrases['invalid_manager_mode'] ?? 'Invalid manager update mode.',
			], false);
		}

		try {
			\Core\ContentStructure::saveContentType($typeId, [
				'manager_plugin_id' => $managerId,
				'manager_overridden' => $overridden,
			]);
		} catch (\Throwable $error) {
			return \Core\Utils\JsonTool::encode([
				'status' => 'error',
				'error' => $error->getMessage(),
			], false);
		}

		$managerName = $managerId !== null
			? \DB::getOne('select system_name from plugins where plugin_id=$1', [$managerId])
			: null;

		return \Core\Utils\JsonTool::encode([
			'status' => 'ok',
			'manager_id' => $managerId,
			'manager_name' => $managerName,
			'overridden' => $overridden,
			'message' => $mode === 'reset'
				? ($this->phrases['manager_reset'] ?? 'Default manager restored.')
				: ($this->phrases['manager_saved'] ?? 'Manager saved.'),
		], false);
	}

	public function typeEdit($context_vars) {
		$this->addCss('/plugins/ContentManager/assets/content-manager.css');
		$data = $this->params(['type'], \Core\Request::getPrefixedParams($this->prefix));
		$typeId = (int)($data['type'] ?? 0);
		$type = null;
		$schema = ['fields' => []];

		if ($typeId > 0) {
			if (!$this->managesContentType($typeId)) {
				return $this->accessDeniedNotice();
			}
			$type = \DB::getRow('select * from content_types where ct_id=$1', [$typeId]);
			if (!$type) {
				return $this->notice(
					$this->phrases['content_type_not_found'] ?? 'Content type not found.',
					'error'
				);
			}
			$schema = \Core\Utils\JsonTool::decodeArray($type['schema'] ?? null);
			$schema['fields'] = is_array($schema['fields'] ?? null)
				? $schema['fields']
				: [];
		}

		$translation = $type ? (\Core\Translation::get($type['uuid']) ?? []) : [];
		$resolvedFields = $type
			? \Core\Content::getStructure($typeId)
			: [];
		$fieldRows = [];
		$attachedNames = [];
		$fields = $schema['fields'];
		uasort($fields, static fn(array $left, array $right): int =>
			((int)($left['displayorder'] ?? 0)) <=> ((int)($right['displayorder'] ?? 0))
		);
		foreach ($fields as $fieldName => $fieldConfig) {
			$field = \DB::getRow(
				'select f.field_id, f.uuid, f.system_name,
					ft.system_name as type_name
				from fields f
				join field_types ft on ft.type_id=f.type_id
				where f.system_name=$1',
				[$fieldName]
			);
			if (!$field) continue;

			$attachedNames[] = (string)$fieldName;
			$fieldTranslation = \Core\Translation::get($field['uuid']) ?? [];
			$resolvedField = is_array($resolvedFields[$fieldName] ?? null)
				? $resolvedFields[$fieldName]
				: $fieldConfig;
			$fieldRows[] = [
				'template' => 'type-field-row',
				'params' => [
					'field_id' => (int)$field['field_id'],
					'field_name' => \Core\Html::escape((string)$fieldName),
					'field_title' => \Core\Html::escape((string)(
						$resolvedField['title'] ?? $fieldTranslation['title'] ?? $fieldName
					)),
					'field_description' => \Core\Html::escape((string)($resolvedField['description'] ?? '')),
					'field_type' => \Core\Html::escape((string)$field['type_name']),
					'field_order' => (int)($fieldConfig['displayorder'] ?? 0),
					'edit_link' => '/' . PAGE_SLUG
						. "/cm-action/fieldEdit/cm-type/{$typeId}/cm-field/{$field['field_id']}",
				],
			];
		}

		$availableOptions = '<option value="">'
			. \Core\Html::escape($this->phrases['select_existing_field'] ?? 'Select a field…')
			. '</option>';
		$availableFields = [];
		$fieldResult = \DB::query(
			'select f.field_id, f.uuid, f.system_name,
				ft.system_name as type_name
			from fields f
			join field_types ft on ft.type_id=f.type_id'
		);
		while ($field = \DB::fetchRow($fieldResult)) {
			if (in_array($field['system_name'], $attachedNames, true)) continue;

			$fieldTranslation = \Core\Translation::get($field['uuid']) ?? [];
			$availableFields[] = [
				'value' => (int)$field['field_id'],
				'title' => (string)($fieldTranslation['title'] ?? $field['system_name']),
				'system_name' => (string)$field['system_name'],
				'type_name' => (string)$field['type_name'],
			];
		}
		$availableFields = \Core\Translation::sortByTitle($availableFields);
		foreach ($availableFields as $field) {
			$label = $field['title'] . " ({$field['system_name']}: {$field['type_name']})";
			$availableOptions .= '<option value="' . $field['value'] . '">'
				. \Core\Html::escape($label) . '</option>';
		}

		$parentOptions = '<option value="">'
			. \Core\Html::escape($this->phrases['no_parent_type'] ?? 'No parent type')
			. '</option>';
		$parents = [];
		$parentResult = \DB::query(
			'select ct_id, uuid, system_name from content_types where ct_id<>$1',
			[$typeId]
		);
		while ($parent = \DB::fetchRow($parentResult)) {
			$parentTranslation = \Core\Translation::get($parent['uuid']) ?? [];
			$parents[] = [
				'value' => (int)$parent['ct_id'],
				'title' => (string)($parentTranslation['title'] ?? $parent['system_name']),
				'system_name' => (string)$parent['system_name'],
			];
		}
		$parents = \Core\Translation::sortByTitle($parents);
		foreach ($parents as $parent) {
			$selected = (int)($type['parent_id'] ?? 0) === $parent['value'] ? ' selected' : '';
			$parentOptions .= '<option value="' . $parent['value'] . '"' . $selected . '>'
				. \Core\Html::escape($parent['title']) . '</option>';
		}

		$titleFieldOptions = $this->fieldReferenceOptions(
			$resolvedFields,
			(string)($schema['title_field'] ?? '')
		);
		$summaryFieldOptions = $this->fieldReferenceOptions(
			$resolvedFields,
			(string)($schema['summary_field'] ?? '')
		);
		$declarativeNotice = !empty($type['plugin_id'])
			? $this->notice(
				$this->phrases['declarative_structure_notice']
					?? 'This structure is declared by a plugin and may be restored on plugin update.',
				'warning'
			)
			: '';

		return $this->render('type-edit', [
			'page_title' => \Core\Html::escape($typeId > 0
				? ($this->phrases['edit_content_type'] ?? 'Edit content type')
				: ($this->phrases['create_content_type'] ?? 'Create content type')),
			'type_id' => $typeId,
			'system_name' => \Core\Html::escape((string)($type['system_name'] ?? '')),
			'title' => \Core\Html::escape((string)($translation['title'] ?? '')),
			'description' => \Core\Html::escape((string)($translation['description'] ?? '')),
			'has_slug_checked' => !empty($type['has_slug']) ? ' checked' : '',
			'parent_options' => $parentOptions,
			'title_field_options' => $titleFieldOptions,
			'summary_field_options' => $summaryFieldOptions,
			'field_rows' => $fieldRows,
			'fields_hidden' => $typeId > 0 ? '' : ' hidden',
			'available_field_options' => $availableOptions,
			'new_field_link' => '/' . PAGE_SLUG . "/cm-action/fieldEdit/cm-type/{$typeId}",
			'back_link' => '/' . PAGE_SLUG . '/cm-action/typeList',
			'save_action' => '/' . PAGE_SLUG . '/cm-action/typeSave',
			'declarative_notice' => $declarativeNotice,
			'ui_text' => \Core\Utils\JsonTool::encode([
				'confirmDetach' => $this->phrases['confirm_detach_field']
					?? 'Remove this field from the structure?',
				'deleteField' => $this->phrases['delete_field_globally'] ?? 'Delete field globally',
				'confirmDelete' => $this->phrases['confirm_delete_field']
					?? 'Delete this field globally? This action cannot be undone.',
				'operationFailed' => $this->phrases['structure_operation_failed']
					?? 'Failed to update the structure.',
			], false),
		]);
	}

	public function typeSave($context_vars) {
		$data = \Core\Request::all();
		$typeId = (int)($data['ct_id'] ?? 0);
		if ($typeId > 0 && !$this->managesContentType($typeId)) {
			return $this->accessDeniedNotice();
		}

		$systemName = trim((string)($data['system_name'] ?? ''));
		$title = trim((string)($data['title'] ?? ''));
		if (!preg_match('/^[a-z][a-z0-9_]*$/', $systemName) || $title === '') {
			return $this->notice(
				$this->phrases['invalid_type_data']
					?? 'System name and title are required. Use lowercase Latin letters, digits and underscores.',
				'error'
			);
		}

		$parentId = ($data['parent_id'] ?? '') === '' ? null : (int)$data['parent_id'];
		try {
			\DB::beginTransaction();
			$existing = $typeId > 0
				? \DB::getRow('select * from content_types where ct_id=$1', [$typeId])
				: null;
			$schema = $existing ? \Core\Utils\JsonTool::decodeArray($existing['schema'] ?? null) : ['fields' => []];
			$fields = is_array($schema['fields'] ?? null) ? $schema['fields'] : [];
			$schema['fields'] = $fields;
			$schema['title_field'] = $this->validFieldReference($data['title_field'] ?? null, $fields);
			$schema['summary_field'] = $this->validFieldReference($data['summary_field'] ?? null, $fields);
			if ($schema['title_field'] === null) unset($schema['title_field']);
			if ($schema['summary_field'] === null) unset($schema['summary_field']);

			$write = [
				'system_name' => $systemName,
				'parent_id' => $parentId,
				'has_slug' => !empty($data['has_slug']),
				'schema' => $schema,
			];
			if (!$existing) {
				$write += [
					'author_id' => defined('USER_ID') ? USER_ID : null,
					'plugin_id' => null,
					'default_manager_plugin_id' => $this->id,
					'manager_plugin_id' => $this->id,
					'manager_overridden' => false,
				];
			}

			$type = \Core\ContentStructure::saveContentType($typeId > 0 ? $typeId : null, $write);
			$typeId = (int)$type['ct_id'];
			$this->upsertTranslation((string)$type['uuid'], [
				'title' => $title,
				'description' => trim((string)($data['description'] ?? '')),
			]);
			\DB::commit();
		} catch (\Throwable $error) {
			\DB::rollBack();
			return $this->notice($error->getMessage(), 'error');
		}

		if ($notifications = $this->plugins->get('Notifications')) {
			$notifications->store(
				\Core\User::getId(),
				$this->phrases['content_type_saved'] ?? 'Content type saved.',
				'success'
			);
		}

		return \Core\Response::seeOther(
			'/' . PAGE_SLUG . "/cm-action/typeEdit/cm-type/{$typeId}"
		);
	}

	public function typeDelete(): string {
		$data = \Core\Request::all();
		$typeId = (int)($data['cm-type'] ?? $data['ct_id'] ?? 0);
		if (!$this->managesContentType($typeId)) {
			\Core\Response::addHeader('HTTP/1.1 403 Forbidden');
			return \Core\Utils\JsonTool::encode(['status' => 'error', 'error' =>
				$this->phrases['content_type_access_denied']
					?? 'This content type is managed by another plugin.'], false);
		}

		try {
			\Core\ContentStructure::deleteContentType($typeId);
		} catch (\Throwable $error) {
			return \Core\Utils\JsonTool::encode(['status' => 'error', 'error' => $error->getMessage()], false);
		}

		return \Core\Utils\JsonTool::encode([
			'status' => 'ok',
			'message' => $this->phrases['content_type_deleted'] ?? 'Content type deleted.',
		], false);
	}

	public function globalFieldEdit($contextVars): string {
		$this->addCss('/plugins/ContentManager/assets/content-manager.css');
		if (!$this->isRoot()) {
			return $this->notice(
				$this->phrases['root_only'] ?? 'Root access required.',
				'error'
			);
		}

		$data = $this->params(['field'], \Core\Request::getPrefixedParams($this->prefix));
		$fieldId = (int)($data['field'] ?? 0);
		$field = $fieldId > 0
			? \Core\Content::getField($fieldId)
			: null;
		if (!$field) {
			return $this->notice($this->phrases['field_not_found'] ?? 'Field not found.', 'error');
		}

		$globalSettings = \Core\Utils\JsonTool::decodeArray($field['field_settings'] ?? null);
		$globalParams = is_array($globalSettings['params'] ?? null)
			? $globalSettings['params']
			: [];
		unset($globalSettings['params']);
		$translation = \Core\Translation::get((string)$field['uuid']) ?? [];

		$typeOptions = '';
		$fieldTypes = [];
		$fieldTypeRows = [];
		$typeResult = \DB::query('select type_id, uuid, system_name from field_types');
		while ($fieldType = \DB::fetchRow($typeResult)) {
			$typeTranslation = \Core\Translation::get((string)$fieldType['uuid']) ?? [];
			$fieldType['title'] = (string)($typeTranslation['title'] ?? $fieldType['system_name']);
			$fieldTypeRows[] = $fieldType;
		}
		$fieldTypeRows = \Core\Translation::sortByTitle($fieldTypeRows);
		foreach ($fieldTypeRows as $fieldType) {
			$selected = (int)$field['type_id'] === (int)$fieldType['type_id']
				? ' selected'
				: '';
			$typeOptions .= '<option value="' . (int)$fieldType['type_id'] . '"'
				. ' data-type-name="' . \Core\Html::escape((string)$fieldType['system_name']) . '"'
				. $selected . '>' . \Core\Html::escape((string)$fieldType['title'])
				. ' (' . \Core\Html::escape((string)$fieldType['system_name']) . ')</option>';
			$fieldTypes[] = \Core\Content::getFieldType((int)$fieldType['type_id']);
		}

		$selectedFieldType = \Core\Content::getFieldType((int)$field['type_id']);
		$params = $this->effectiveFieldParams($selectedFieldType, $globalParams, []);
		$parameterGroups = $this->renderFieldParameterGroups(
			$fieldTypes,
			(int)$field['type_id'],
			$params
		);
		$uses = (int)\DB::getOne(
			"select count(*) from content_types where coalesce(schema->'fields', '{}'::jsonb) ? $1",
			[(string)$field['system_name']]
		);

		return $this->render('global-field-edit', [
			'page_title' => \Core\Html::escape($this->phrases['edit_global_field'] ?? 'Edit global field'),
			'field_id' => $fieldId,
			'system_name' => \Core\Html::escape((string)$field['system_name']),
			'title' => \Core\Html::escape((string)($translation['title'] ?? $field['system_name'])),
			'description' => \Core\Html::escape((string)($translation['description'] ?? '')),
			'field_type_options' => $typeOptions,
			'field_parameters' => $parameterGroups,
			'indexed_checked' => !empty($globalSettings['indexed']) ? ' checked' : '',
			'unique_checked' => !empty($globalSettings['unique']) ? ' checked' : '',
			'translatable_checked' => !empty($globalSettings['translatable']) ? ' checked' : '',
			'usage_notice' => \Core\Html::escape(str_replace(
				'{count}',
				(string)$uses,
				$this->phrases['field_usage_count'] ?? 'Used in {count} content types.'
			)),
			'save_action' => '/' . PAGE_SLUG . '/cm-action/globalFieldSave',
			'back_link' => '/' . PAGE_SLUG . '/cm-action/fieldList',
		]);
	}

	public function globalFieldSave($contextVars): string {
		if (!$this->isRoot()) {
			return $this->notice(
				$this->phrases['root_only'] ?? 'Root access required.',
				'error'
			);
		}

		$data = \Core\Request::all();
		$fieldId = (int)($data['field_id'] ?? 0);
		$field = $fieldId > 0
			? \DB::getRow('select * from fields where field_id=$1', [$fieldId])
			: null;
		if (!$field) {
			return $this->notice($this->phrases['field_not_found'] ?? 'Field not found.', 'error');
		}

		$systemName = trim((string)($data['system_name'] ?? ''));
		$title = trim((string)($data['title'] ?? ''));
		$fieldTypeId = (int)($data['type_id'] ?? 0);
		if (!preg_match('/^[a-z][a-z0-9_]*$/', $systemName) || $title === '') {
			return $this->notice(
				$this->phrases['invalid_field_data'] ?? 'System name and title are required.',
				'error'
			);
		}

		try {
			$fieldType = \Core\Content::getFieldType($fieldTypeId);
			$params = $this->parseFieldParams(
				is_array($fieldType['type_settings']['parameters'] ?? null)
					? $fieldType['type_settings']['parameters']
					: [],
				is_array($data['params'] ?? null) ? $data['params'] : []
			);
		} catch (\InvalidArgumentException $error) {
			return $this->notice($error->getMessage(), 'error');
		}

		$settings = [
			'indexed' => !empty($data['indexed']),
			'unique' => !empty($data['unique']),
			'translatable' => !empty($data['translatable']),
		];
		if (!$settings['indexed']) $settings['unique'] = false;
		if (!empty($fieldType['type_settings']['requires_indexed']) && !$settings['indexed']) {
			return $this->notice(
				$this->phrases['field_type_requires_indexed'] ?? 'This field type requires indexing.',
				'error'
			);
		}

		try {
			$this->validateSuggestionSources($fieldType, $params, $systemName, $settings);
		} catch (\InvalidArgumentException $error) {
			return $this->notice($error->getMessage(), 'error');
		}
		if ($params !== []) $settings['params'] = $params;

		try {
			\DB::beginTransaction();
			$field = \Core\ContentStructure::saveField($fieldId, [
				'type_id' => $fieldTypeId,
				'system_name' => $systemName,
				'field_settings' => $settings,
			]);
			$this->upsertTranslation((string)$field['uuid'], [
				'title' => $title,
				'description' => trim((string)($data['description'] ?? '')),
			]);
			\DB::commit();
		} catch (\Throwable $error) {
			\DB::rollBack();
			return $this->notice($error->getMessage(), 'error');
		}

		if ($notifications = $this->plugins->get('Notifications')) {
			$notifications->store(
				\Core\User::getId(),
				$this->phrases['global_field_saved'] ?? 'Global field saved.',
				'success'
			);
		}

		return \Core\Response::seeOther('/' . PAGE_SLUG . '/cm-action/fieldList');
	}

	public function fieldEdit($contextVars): string {
		$this->addCss('/plugins/ContentManager/assets/content-manager.css');
		$data = $this->params(['type', 'field'], \Core\Request::getPrefixedParams($this->prefix));
		$typeId = (int)($data['type'] ?? 0);
		$fieldId = (int)($data['field'] ?? 0);
		if (!$this->managesContentType($typeId)) {
			return $this->accessDeniedNotice();
		}

		$type = \DB::getRow('select * from content_types where ct_id=$1', [$typeId]);
		if (!$type) {
			return $this->notice($this->phrases['content_type_not_found'] ?? 'Content type not found.', 'error');
		}
		$schema = \Core\Utils\JsonTool::decodeArray($type['schema'] ?? null);
		$schemaFields = is_array($schema['fields'] ?? null) ? $schema['fields'] : [];

		if ($fieldId > 0) {
			$field = \DB::getRow('select * from fields where field_id=$1', [$fieldId]);
			if (!$field) {
				return $this->notice($this->phrases['field_not_found'] ?? 'Field not found.', 'error');
			}
			$fieldName = (string)$field['system_name'];
			if (!array_key_exists($fieldName, $schemaFields)) {
				return $this->notice(
					$this->phrases['field_not_in_structure'] ?? 'This field is not part of the current structure.',
					'error'
				);
			}

			$local = is_array($schemaFields[$fieldName] ?? null) ? $schemaFields[$fieldName] : [];
			$localSettings = is_array($local['settings'] ?? null) ? $local['settings'] : [];
			$globalTranslation = \Core\Translation::get((string)$field['uuid']) ?? [];
			$typeTranslation = \Core\Translation::get((string)$type['uuid']) ?? [];
			$localTranslation = is_array($typeTranslation['schema']['fields'][$fieldName] ?? null)
				? $typeTranslation['schema']['fields'][$fieldName]
				: [];
			$fieldType = \Core\Content::getFieldType((int)$field['type_id']);

			$searchWeightOptions = '<option value="">—</option>';
			foreach (['A', 'B', 'C', 'D'] as $weight) {
				$selected = (string)($local['search_weight'] ?? '') === $weight ? ' selected' : '';
				$searchWeightOptions .= '<option value="' . $weight . '"' . $selected . '>'
					. $weight . '</option>';
			}

			return $this->render('field-attachment-edit', [
				'page_title' => \Core\Html::escape($this->phrases['edit_field'] ?? 'Edit field'),
				'field_id' => $fieldId,
				'type_id' => $typeId,
				'system_name' => \Core\Html::escape($fieldName),
				'field_type' => \Core\Html::escape((string)$fieldType['system_name']),
				'title' => \Core\Html::escape((string)(
					$localTranslation['title'] ?? $globalTranslation['title'] ?? $fieldName
				)),
				'description' => \Core\Html::escape((string)(
					$localTranslation['description'] ?? $globalTranslation['description'] ?? ''
				)),
				'search_weight_options' => $searchWeightOptions,
				'default_value' => \Core\Html::escape((string)($local['default'] ?? '')),
				'required_checked' => !empty($localSettings['required']) ? ' checked' : '',
				'multiple_checked' => !empty($localSettings['multiple']) ? ' checked' : '',
				'hidden_checked' => !empty($localSettings['hidden']) ? ' checked' : '',
				'readonly_checked' => !empty($localSettings['readonly']) ? ' checked' : '',
				'save_action' => '/' . PAGE_SLUG . '/cm-action/fieldSave',
				'back_link' => '/' . PAGE_SLUG . "/cm-action/typeEdit/cm-type/{$typeId}",
			]);
		}

		$typeOptions = '';
		$fieldTypes = [];
		$fieldTypeRows = [];
		$typeResult = \DB::query('select type_id, uuid, system_name from field_types');
		while ($fieldType = \DB::fetchRow($typeResult)) {
			$typeTranslation = \Core\Translation::get((string)$fieldType['uuid']) ?? [];
			$fieldType['title'] = (string)($typeTranslation['title'] ?? $fieldType['system_name']);
			$fieldTypeRows[] = $fieldType;
		}
		$fieldTypeRows = \Core\Translation::sortByTitle($fieldTypeRows);
		foreach ($fieldTypeRows as $fieldType) {
			$typeOptions .= '<option value="' . (int)$fieldType['type_id'] . '"'
				. ' data-type-name="' . \Core\Html::escape((string)$fieldType['system_name']) . '">'
				. \Core\Html::escape((string)$fieldType['title'])
				. ' (' . \Core\Html::escape((string)$fieldType['system_name']) . ')</option>';
			$fieldTypes[] = \Core\Content::getFieldType((int)$fieldType['type_id']);
		}

		$parameterGroups = $this->renderFieldParameterGroups($fieldTypes, 0, []);
		$searchWeightOptions = '<option value="">—</option>';
		foreach (['A', 'B', 'C', 'D'] as $weight) {
			$searchWeightOptions .= '<option value="' . $weight . '">' . $weight . '</option>';
		}

		return $this->render('field-edit', [
			'page_title' => \Core\Html::escape($this->phrases['create_field'] ?? 'Create field'),
			'field_id' => 0,
			'type_id' => $typeId,
			'system_name' => '',
			'title' => '',
			'description' => '',
			'field_type_options' => $typeOptions,
			'search_weight_options' => $searchWeightOptions,
			'default_value' => '',
			'field_parameters' => $parameterGroups,
			'required_checked' => '',
			'indexed_checked' => '',
			'unique_checked' => '',
			'translatable_checked' => '',
			'multiple_checked' => '',
			'hidden_checked' => '',
			'readonly_checked' => '',
			'usage_notice' => '',
			'save_action' => '/' . PAGE_SLUG . '/cm-action/fieldSave',
			'back_link' => '/' . PAGE_SLUG . "/cm-action/typeEdit/cm-type/{$typeId}",
		]);
	}

	public function fieldSave($contextVars): string {
		$data = \Core\Request::all();
		$typeId = (int)($data['ct_id'] ?? 0);
		$fieldId = (int)($data['field_id'] ?? 0);
		if (!$this->managesContentType($typeId)) return $this->accessDeniedNotice();

		$type = \DB::getRow('select * from content_types where ct_id=$1', [$typeId]);
		if (!$type) {
			return $this->notice($this->phrases['content_type_not_found'] ?? 'Content type not found.', 'error');
		}
		$schema = \Core\Utils\JsonTool::decodeArray($type['schema'] ?? null);
		$schemaFields = is_array($schema['fields'] ?? null) ? $schema['fields'] : [];

		if ($fieldId > 0) {
			$field = \DB::getRow('select * from fields where field_id=$1', [$fieldId]);
			if (!$field || !array_key_exists((string)$field['system_name'], $schemaFields)) {
				return $this->notice(
					$this->phrases['field_not_in_structure'] ?? 'This field is not part of the current structure.',
					'error'
				);
			}

			$fieldName = (string)$field['system_name'];
			$fieldType = \Core\Content::getFieldType((int)$field['type_id']);
			$localSettings = [
				'required' => !empty($data['required']),
				'multiple' => !empty($data['multiple']),
				'hidden' => !empty($data['hidden']),
				'readonly' => !empty($data['readonly']),
			];
			if ($localSettings['multiple'] && (string)($fieldType['root_type_name'] ?? '') === 'boolean') {
				return $this->notice(
					$this->phrases['multiple_boolean_not_supported']
						?? 'Boolean fields cannot contain multiple values.',
					'error'
				);
			}

			$existingLocal = is_array($schemaFields[$fieldName] ?? null)
				? $schemaFields[$fieldName]
				: [];
			foreach (['type', 'options', 'content_type', 'content_types', 'params', 'title', 'description'] as $legacyKey) {
				unset($existingLocal[$legacyKey]);
			}
			$local = $existingLocal;
			$localSettings = array_filter($localSettings, static fn(bool $value): bool => $value);
			if ($localSettings === []) unset($local['settings']);
			else $local['settings'] = $localSettings;

			$searchWeight = in_array($data['search_weight'] ?? null, ['A', 'B', 'C', 'D'], true)
				? (string)$data['search_weight']
				: null;
			if ($searchWeight === null) unset($local['search_weight']);
			else $local['search_weight'] = $searchWeight;
			if ((string)($data['default_value'] ?? '') !== '') {
				$local['default'] = (string)$data['default_value'];
			} else {
				unset($local['default']);
			}

			$globalTranslation = \Core\Translation::get((string)$field['uuid']) ?? [];
			try {
				\DB::beginTransaction();
				\Core\ContentStructure::configureField($typeId, $fieldId, $local);
				$this->syncContentTypeFieldTranslation(
					(string)$type['uuid'],
					$fieldName,
					trim((string)($data['title'] ?? '')),
					trim((string)($data['description'] ?? '')),
					(string)($globalTranslation['title'] ?? $fieldName),
					(string)($globalTranslation['description'] ?? '')
				);
				\DB::commit();
			} catch (\Throwable $error) {
				\DB::rollBack();
				return $this->notice($error->getMessage(), 'error');
			}

			if ($notifications = $this->plugins->get('Notifications')) {
				$notifications->store(
					\Core\User::getId(),
					$this->phrases['field_saved'] ?? 'Field saved.',
					'success'
				);
			}

			return \Core\Response::seeOther(
				'/' . PAGE_SLUG . "/cm-action/typeEdit/cm-type/{$typeId}"
			);
		}

		$systemName = trim((string)($data['system_name'] ?? ''));
		$title = trim((string)($data['title'] ?? ''));
		$fieldTypeId = (int)($data['type_id'] ?? 0);
		if (!preg_match('/^[a-z][a-z0-9_]*$/', $systemName) || $title === '') {
			return $this->notice(
				$this->phrases['invalid_field_data'] ?? 'System name and title are required.',
				'error'
			);
		}

		try {
			$fieldType = \Core\Content::getFieldType($fieldTypeId);
			$params = $this->parseFieldParams(
				is_array($fieldType['type_settings']['parameters'] ?? null)
					? $fieldType['type_settings']['parameters']
					: [],
				is_array($data['params'] ?? null) ? $data['params'] : []
			);
		} catch (\InvalidArgumentException $error) {
			return $this->notice($error->getMessage(), 'error');
		}

		$globalSettings = [
			'indexed' => !empty($data['indexed']),
			'unique' => !empty($data['unique']),
			'translatable' => !empty($data['translatable']),
		];
		$localSettings = [
			'required' => !empty($data['required']),
			'multiple' => !empty($data['multiple']),
			'hidden' => !empty($data['hidden']),
			'readonly' => !empty($data['readonly']),
		];
		if (!$globalSettings['indexed']) $globalSettings['unique'] = false;
		if (!empty($fieldType['type_settings']['requires_indexed']) && !$globalSettings['indexed']) {
			return $this->notice(
				$this->phrases['field_type_requires_indexed'] ?? 'This field type requires indexing.',
				'error'
			);
		}
		if ($localSettings['multiple'] && (string)($fieldType['root_type_name'] ?? '') === 'boolean') {
			return $this->notice(
				$this->phrases['multiple_boolean_not_supported']
					?? 'Boolean fields cannot contain multiple values.',
				'error'
			);
		}
		try {
			$this->validateSuggestionSources($fieldType, $params, $systemName, $globalSettings);
		} catch (\InvalidArgumentException $error) {
			return $this->notice($error->getMessage(), 'error');
		}

		$fieldSettings = $globalSettings;
		if ($params !== []) $fieldSettings['params'] = $params;
		$local = ['displayorder' => $this->nextFieldOrder($schemaFields)];
		$localSettings = array_filter($localSettings, static fn(bool $value): bool => $value);
		if ($localSettings !== []) $local['settings'] = $localSettings;
		$searchWeight = in_array($data['search_weight'] ?? null, ['A', 'B', 'C', 'D'], true)
			? (string)$data['search_weight']
			: null;
		if ($searchWeight !== null) $local['search_weight'] = $searchWeight;
		if ((string)($data['default_value'] ?? '') !== '') {
			$local['default'] = (string)$data['default_value'];
		}

		try {
			\DB::beginTransaction();
			$field = \Core\ContentStructure::saveField(null, [
				'type_id' => $fieldTypeId,
				'variant_id' => null,
				'system_name' => $systemName,
				'field_settings' => $fieldSettings,
			]);
			$this->upsertTranslation((string)$field['uuid'], [
				'title' => $title,
				'description' => trim((string)($data['description'] ?? '')),
			]);
			\Core\ContentStructure::attachField($typeId, (int)$field['field_id'], $local);
			\DB::commit();
		} catch (\Throwable $error) {
			\DB::rollBack();
			return $this->notice($error->getMessage(), 'error');
		}

		if ($notifications = $this->plugins->get('Notifications')) {
			$notifications->store(
				\Core\User::getId(),
				$this->phrases['field_saved'] ?? 'Field saved.',
				'success'
			);
		}

		return \Core\Response::seeOther(
			'/' . PAGE_SLUG . "/cm-action/typeEdit/cm-type/{$typeId}"
		);
	}

	public function fieldAttach(?array $input): string {
		$data = array_replace($input ?? [], \Core\Request::all());
		$typeId = (int)($data['ct_id'] ?? 0);
		$fieldId = (int)($data['field_id'] ?? 0);
		if (!$this->managesContentType($typeId)) return $this->forbiddenJson();

		$field = \DB::getRow('select uuid from fields where field_id=$1', [$fieldId]);
		if (!$field) {
			return \Core\Utils\JsonTool::encode(['status' => 'error', 'error' =>
				$this->phrases['field_not_found'] ?? 'Field not found.'], false);
		}
		try {
			\Core\ContentStructure::attachField($typeId, $fieldId);
		} catch (\Throwable $error) {
			return \Core\Utils\JsonTool::encode(['status' => 'error', 'error' => $error->getMessage()], false);
		}

		return \Core\Utils\JsonTool::encode(['status' => 'ok', 'reload' => true,
			'message' => $this->phrases['field_attached'] ?? 'Field added to the structure.'], false);
	}

	public function fieldDetach(?array $input): string {
		$data = array_replace($input ?? [], \Core\Request::all());
		$typeId = (int)($data['ct_id'] ?? 0);
		$fieldId = (int)($data['field_id'] ?? 0);
		if (!$this->managesContentType($typeId)) return $this->forbiddenJson();

		try {
			\Core\ContentStructure::detachField($typeId, $fieldId);
		} catch (\Throwable $error) {
			return \Core\Utils\JsonTool::encode(['status' => 'error', 'error' => $error->getMessage()], false);
		}

		return \Core\Utils\JsonTool::encode(['status' => 'ok', 'message' =>
			$this->phrases['field_detached'] ?? 'Field removed from the structure.'], false);
	}

	public function fieldMove(?array $input): string {
		$data = array_replace($input ?? [], \Core\Request::all());
		$typeId = (int)($data['ct_id'] ?? 0);
		$fieldId = (int)($data['field_id'] ?? 0);
		$direction = (string)($data['direction'] ?? '');
		if (!$this->managesContentType($typeId)) return $this->forbiddenJson();

		try {
			\Core\ContentStructure::moveField($typeId, $fieldId, $direction);
		} catch (\Throwable $error) {
			return \Core\Utils\JsonTool::encode(['status' => 'error', 'error' => $error->getMessage()], false);
		}

		return \Core\Utils\JsonTool::encode(['status' => 'ok', 'reload' => true], false);
	}

	public function fieldDelete(?array $input): string {
		if (!$this->isRoot()) {
			\Core\Response::addHeader('HTTP/1.1 403 Forbidden');
			return \Core\Utils\JsonTool::encode([
				'status' => 'error',
				'error' => $this->phrases['root_only'] ?? 'Root access required.',
			], false);
		}

		$data = array_replace($input ?? [], \Core\Request::all());
		$fieldId = (int)($data['field_id'] ?? 0);
		try {
			\Core\ContentStructure::deleteField($fieldId);
		} catch (\Throwable $error) {
			return \Core\Utils\JsonTool::encode(['status' => 'error', 'error' => $error->getMessage()], false);
		}

		return \Core\Utils\JsonTool::encode(['status' => 'ok', 'message' =>
			$this->phrases['field_deleted'] ?? 'Field deleted.'], false);
	}
	public function itemList($context_vars) {
		$this->addCss('/plugins/ContentManager/assets/content-manager.css');
		$data = $this->params(['type', 'page', 'q'], \Core\Request::getPrefixedParams($this->prefix));

		$page = max(1, (int)($data['page'] ?? 1));
		$query = (string)($data['q'] ?? '');
		$offset = 20 * ($page - 1);

		$content_type = \Core\Content::getContentType($data['type']);
		if (!$this->managesContentType((int)$content_type['ct_id'])) {
			return $this->accessDeniedNotice();
		}

		$params = $items_list = [];

		$titleField = $content_type['schema']['title_field'] ?? null;
		$titleFieldId = $titleField ? \Core\Content::fieldMap($titleField) : null;
		$order = $titleFieldId
			? [["table" => "texts", "field_id" => $titleFieldId, "direction" => "asc"]]
			: null;

		$result = \Core\Content::search(
			[$data['type']],
			'substr',
			$query,
			null,
			$order,
			$offset,
			20
		);
		$totals = $result['totals'];

		foreach ($result['ids'] as $id) {
			$row = \Core\Content::getItem($id);
			$title = (string)($row['title'] ?? "Item #{$row['item_id']}");
			$items_list[] = [
				'template' => 'item-row',
				'params' => [
					'item_id' => (int)$row['item_id'],
					'title' => \Core\Html::escape($title),
					'title_attribute' => \Core\Html::escape($title),
					'edit_link' => "/" . PAGE_SLUG
						. "/cm-action/itemEdit/cm-type/{$data['type']}/cm-id/{$row['item_id']}",
					'delete_link' => "/ajax/ContentManager/itemDelete/cm-id/{$row['item_id']}",
				],
			];
		}

		if (!$items_list) {
			$items_list[] = [
				'template' => 'items-empty-row',
				'params' => [],
			];
		}

		$pagination_html = $this->pagination()->renderPagination(
			page: $page,
			perPage: 20,
			total: $totals,
			base_url: "/".PAGE_SLUG."/cm-action/itemList/cm-type/{$data['type']}",
			options: ['page_param' => $this->prefix . '-page']
		);

		$params['item_rows'] = $items_list;
		$params['pagination'] = $pagination_html;
		$params['items_count'] = $totals;
		$params['items_summary'] = \Core\Html::escape(str_replace(
				'{count}',
				(string)$totals,
				$this->phrases['item_count'] ?? '{count} items'
			));
		$params['type_name'] = \Core\Html::escape((string)$content_type['title']);

		$params['create_link'] = "/" . PAGE_SLUG
			. "/cm-action/itemEdit/cm-type/{$data['type']}";
		$params['search_link'] = "/" . PAGE_SLUG
			. "/cm-action/itemList/cm-type/{$data['type']}";
		$params['types_link'] = "/" . PAGE_SLUG . '/cm-action/typeList';
		$params['q'] = \Core\Html::escape($query);
		$params['ui_text'] = \Core\Utils\JsonTool::encodeForHtml([
			'itemCount' => $this->phrases['item_count'] ?? '{count} items',
			'noItems' => $this->phrases['no_items'] ?? 'No content items found.',
			'confirmDeleteItem' => $this->phrases['confirm_delete_item']
				?? 'Delete "{title}"? This action cannot be undone.',
			'itemDeleted' => $this->phrases['item_deleted'] ?? 'Item deleted.',
			'deleteFailed' => $this->phrases['delete_item_failed']
				?? 'Failed to delete item.',
		]) ?: '{}';

		return $this->render('items-list', $params);
	}

	public function itemEdit($context_vars) {
		$this->addCss('/plugins/ContentManager/assets/content-manager.css');
		$data = $this->params(['type', 'id'], \Core\Request::getPrefixedParams($this->prefix));
		$type = \Core\Content::getContentType($data['type']);
		if (!$this->managesContentType((int)$type['ct_id'])) {
			return $this->accessDeniedNotice();
		}

		if (isset($data['id'])) {
			$id = (int)$data['id'];
			$item = \Core\Content::getItem($id);
			if (!$item || (int)$item['ct_id'] !== (int)$type['ct_id']) {
				return '<div class="kc-notice kc-notice-error">'
					. \Core\Html::escape($this->phrases['item_not_found'] ?? 'Content item not found.')
					. '</div>';
			}
			$item_title = (string)($item['title'] ?? "Item #{$id}");
			$action = "/" . PAGE_SLUG
				. "/cm-action/itemSave/cm-type/{$data['type']}/cm-id/{$id}";
		} else {
			$id = 0;
			$item = [];
			$item_title = $this->phrases['new_item'] ?? 'New item';
			$action = "/" . PAGE_SLUG
				. "/cm-action/itemSave/cm-type/{$data['type']}";
		}

		$fields = $this->itemFormFields($type, $item);

		debug_step('content form start');
		$form = $this->forms()->renderForm(
			$fields,
			$action,
			[
				'method' => 'post',
				'attributes' => ['class' => 'admin-form cm-form'],
			]
		);
		debug_step('content form done');

		$params = [
			'form' => $form,
			'title' => \Core\Html::escape("{$type['title']}: {$item_title}"),
			'back_link' => "/" . PAGE_SLUG
				. "/cm-action/itemList/cm-type/{$data['type']}",
		];

		return $this->render('item-edit', $params);
	}

	private function itemFormFields(array $contentType, array $item): array {
		$fields = is_array($contentType['schema']['fields'] ?? null)
			? $contentType['schema']['fields']
			: [];
		uasort($fields, static function (array $left, array $right): int {
			return ((int)($left['displayorder'] ?? 0))
				<=> ((int)($right['displayorder'] ?? 0));
		});

		$values = is_array($item['data'] ?? null) ? $item['data'] : [];
		$formFields = [];

		foreach ($fields as $fieldName => $field) {
			if (!is_array($field)) {
				continue;
			}

			$definition = \Core\Content::getField((string)$fieldName);
			$typeSettings = is_array($definition['type_settings'] ?? null)
				? $definition['type_settings']
				: [];
			$globalSettings = is_array($definition['field_settings'] ?? null)
				? $definition['field_settings']
				: [];
			$globalParams = is_array($globalSettings['params'] ?? null)
				? $globalSettings['params']
				: [];
			unset($typeSettings['parameters'], $globalSettings['params']);
			$settings = array_replace(
				$typeSettings,
				$globalSettings,
				is_array($field['settings'] ?? null) ? $field['settings'] : []
			);
			$field['settings'] = $settings;
			$fieldType = \Core\Content::getFieldType((int)$definition['type_id']);
			$field['params'] = $this->effectiveFieldParams(
				$fieldType,
				$globalParams,
				$field
			);
			if (!empty($settings['hidden'])) {
				continue;
			}

			$field['name'] = (string)$fieldName;
			if (array_key_exists($fieldName, $values)) {
				$field['value'] = $values[$fieldName];
			} else {
				$field['value'] = $field['default'] ?? null;
				$legacyNames = array_values(array_unique([
					...(self::FIELD_LEGACY_NAMES[$fieldName] ?? []),
					...(is_array($field['legacy_names'] ?? null)
						? $field['legacy_names']
						: []),
				]));
				foreach ($legacyNames as $legacyName) {
					if (is_string($legacyName) && array_key_exists($legacyName, $values)) {
						$field['value'] = $values[$legacyName];
						break;
					}
				}
			}

			$field['value'] = $this->convertDateTimeFieldValue(
				$fieldType,
				$field['params'],
				$field['value'] ?? null,
				false
			);

			$formFields[$fieldName] = $field;
		}

		$allowedParentTypes = \Core\User::getAllowedContentTypeIds('view');
		$formFields['parent_id'] = [
			'name' => 'parent_id',
			'title' => $this->phrases['parent_item'] ?? 'Parent item',
			'description' => $this->phrases['parent_item_help']
				?? 'Optional parent content item.',
			'type' => 'item_id',
			'value' => $item['parent_id'] ?? null,
			'settings' => ['multiple' => false],
			'params' => [
				// An empty content_types list means "no filter" in Forms, so use
				// an impossible ID when the current user cannot view any types.
				'content_types' => $allowedParentTypes !== [] ? $allowedParentTypes : [-1],
				'exclude_ids' => !empty($item['item_id']) ? [(int)$item['item_id']] : [],
			],
		];

		if (!empty($contentType['has_slug'])) {
			$formFields['item_slug'] = [
				'name' => 'item_slug',
				'title' => 'Slug',
				'type' => 'slug',
				'value' => $item['item_slug'] ?? '',
			];
		}

		return $formFields;
	}

	private function effectiveFieldParams(
		array $fieldType,
		array $globalParams,
		array $local
	): array {
		$definitions = is_array($fieldType['type_settings']['parameters'] ?? null)
			? $fieldType['type_settings']['parameters']
			: [];
		$localParams = is_array($local['params'] ?? null)
			? $local['params']
			: [];
		$params = $this->fieldParameterDefaults($definitions);
		$params = array_replace($params, $globalParams);
		$params = array_replace($params, $localParams);

		if (
			!array_key_exists('options', $globalParams)
			&& !array_key_exists('options', $localParams)
			&& is_array($local['options'] ?? null)
		) {
			$params['options'] = $local['options'];
		}
		if (!array_key_exists('content_types', $globalParams)
			&& !array_key_exists('content_types', $localParams)) {
			$legacyTypes = $local['content_types'] ?? $local['content_type'] ?? null;
			if ($legacyTypes !== null && $legacyTypes !== '') {
				$legacyTypes = is_array($legacyTypes) ? $legacyTypes : [$legacyTypes];
				$params['content_types'] = array_values(array_unique(array_filter(
					array_map($this->contentTypeSystemName(...), $legacyTypes)
				)));
			}
		}

		return array_intersect_key($params, $definitions);
	}

	private function fieldParameterDefaults(array $definitions): array {
		$defaults = [];
		foreach ($definitions as $name => $definition) {
			if (
				is_string($name)
				&& is_array($definition)
				&& array_key_exists('default', $definition)
			) {
				$defaults[$name] = $definition['default'];
			}
		}
		return $defaults;
	}

	private function contentTypeSystemName(mixed $contentType): ?string {
		if (is_int($contentType) || (is_string($contentType) && ctype_digit($contentType))) {
			$name = \DB::getOne(
				'select system_name from content_types where ct_id=$1',
				[(int)$contentType]
			);
			return is_string($name) && $name !== '' ? $name : null;
		}
		$name = trim((string)$contentType);
		return $name !== '' ? $name : null;
	}

	private function renderFieldParameterGroups(
		array $fieldTypes,
		int $selectedTypeId,
		array $selectedParams
	): string {
		$html = '';
		foreach ($fieldTypes as $fieldType) {
			$definitions = is_array($fieldType['type_settings']['parameters'] ?? null)
				? $fieldType['type_settings']['parameters']
				: [];
			if ($definitions === []) continue;

			$typeId = (int)$fieldType['type_id'];
			$values = $this->fieldParameterDefaults($definitions);
			if ($typeId === $selectedTypeId) {
				$values = array_replace($values, $selectedParams);
			}
			$controls = '';
			foreach ($definitions as $name => $definition) {
				if (!is_string($name) || !is_array($definition)) continue;
				$controls .= $this->renderFieldParameterControl(
					$typeId,
					$name,
					$definition,
					$values[$name] ?? null
				);
			}
			if ($controls === '') continue;

			$hidden = $typeId === $selectedTypeId ? '' : ' hidden disabled';
			$html .= '<fieldset class="cm-parameter-group" data-field-parameters="'
				. $typeId . '"' . $hidden . '><legend>'
				. \Core\Html::escape($this->phrases['field_type_parameters'] ?? 'Type parameters')
				. ': ' . \Core\Html::escape((string)$fieldType['system_name'])
				. '</legend><div class="cm-form-grid cm-parameter-grid">'
				. $controls . '</div></fieldset>';
		}

		return $html;
	}

	private function renderFieldParameterControl(
		int $typeId,
		string $name,
		array $definition,
		mixed $value
	): string {
		$id = 'cm-param-' . $typeId . '-' . preg_replace('/[^a-z0-9_-]+/i', '-', $name);
		$multiple = !empty($definition['multiple']);
		$required = !empty($definition['required']);
		$inputName = 'params[' . $name . ']' . ($multiple ? '[]' : '');
		$title = $this->fieldParameterText(
			(string)($definition['title'] ?? ''),
			ucwords(str_replace(['_', '-'], ' ', $name))
		);
		$description = $this->fieldParameterText(
			(string)($definition['description'] ?? ''),
			''
		);
		$attributes = is_array($definition['attributes'] ?? null)
			? $definition['attributes']
			: [];
		$attributes = array_replace($attributes, [
			'id' => $id,
			'name' => $inputName,
			'class' => 'admin-input',
		]);
		if ($required) $attributes['required'] = true;

		$type = (string)($definition['type'] ?? 'string');
		$format = (string)($definition['format'] ?? '');
		if ($type === 'compound_components') {
			return $this->renderCompoundComponentsControl(
				$typeId,
				$name,
				$definition,
				is_array($value) ? $value : []
			);
		}
		if ($format === 'json') {
			$attributes['rows'] = $attributes['rows'] ?? 8;
			$attributes['class'] .= ' cm-code-input';
			$encoded = is_array($value)
				? \Core\Utils\JsonTool::encode($value, true)
				: (string)($value ?? '');
			$control = '<textarea' . $this->renderHtmlAttributes($attributes) . '>'
				. \Core\Html::escape($encoded ?: '') . '</textarea>';
		} elseif ($type === 'textarea') {
			$attributes['rows'] = $attributes['rows'] ?? 4;
			$control = '<textarea' . $this->renderHtmlAttributes($attributes) . '>'
				. \Core\Html::escape((string)($value ?? '')) . '</textarea>';
		} elseif (in_array($type, ['content_type_id', 'field_id', 'select'], true)) {
			if ($multiple) $attributes['multiple'] = true;
			$control = '<select' . $this->renderHtmlAttributes($attributes) . '>'
				. $this->renderFieldParameterOptions($definition, $value)
				. '</select>';
		} elseif ($type === 'checkbox' || $type === 'boolean') {
			$attributes['type'] = 'checkbox';
			$attributes['value'] = '1';
			if (!empty($value)) $attributes['checked'] = true;
			$control = '<input' . $this->renderHtmlAttributes($attributes) . '>';
		} else {
			$attributes['type'] = in_array($type, ['number', 'integer', 'decimal'], true)
				? 'number'
				: 'text';
			if ($type === 'decimal' && !isset($attributes['step'])) {
				$attributes['step'] = 'any';
			}
			$attributes['value'] = $value ?? '';
			$control = '<input' . $this->renderHtmlAttributes($attributes) . '>';
		}

		$help = $description !== ''
			? '<small>' . \Core\Html::escape($description) . '</small>'
			: '';
		return '<label class="cm-field"><span>' . \Core\Html::escape($title)
			. ($required ? ' *' : '') . '</span>' . $control . $help . '</label>';
	}

	private function renderCompoundComponentsControl(
		int $typeId,
		string $name,
		array $definition,
		array $components
	): string {
		$typeOptions = [];
		$result = \DB::query('select type_id, uuid, system_name from field_types order by type_id');
		while ($row = \DB::fetchRow($result)) {
			$fieldType = \Core\Content::getFieldType((int)$row['type_id']);
			if (empty($fieldType['type_settings']['compound_component'])) continue;
			$translation = \Core\Translation::get((string)$row['uuid']) ?? [];
			$typeOptions[] = [
				'value' => (string)$row['system_name'],
				'label' => (string)($translation['title'] ?? $row['system_name']),
				'root' => (string)($fieldType['root_type_name'] ?? ''),
			];
		}
		$typeOptions = \Core\Translation::sortByTitle($typeOptions, 'label', null);

		$sourceOptions = [];
		$fieldRows = \DB::query('select field_id, uuid, system_name from fields order by system_name');
		while ($row = \DB::fetchRow($fieldRows)) {
			$field = \Core\Content::getField((int)$row['field_id']);
			$settings = array_replace(
				is_array($field['type_settings'] ?? null) ? $field['type_settings'] : [],
				is_array($field['field_settings'] ?? null) ? $field['field_settings'] : []
			);
			if (empty($settings['indexed']) || (string)($field['root_type_name'] ?? '') !== 'text') {
				continue;
			}
			$translation = \Core\Translation::get((string)$row['uuid']) ?? [];
			$sourceOptions[] = [
				'value' => (string)$row['system_name'],
				'label' => (string)($translation['title'] ?? $row['system_name'])
					. ' (' . $row['system_name'] . ')',
			];
		}
		$sourceOptions = \Core\Translation::sortByTitle($sourceOptions, 'label', null);

		$rows = '';
		foreach ($components as $componentName => $component) {
			if (!is_string($componentName) || !is_array($component)) continue;
			$rows .= $this->renderCompoundComponentRow(
				$componentName,
				$component,
				$typeOptions,
				$sourceOptions
			);
		}
		$template = $this->renderCompoundComponentRow('', [], $typeOptions, $sourceOptions);
		$hiddenValue = \Core\Html::escape(\Core\Utils\JsonTool::encode($components, false));
		$inputName = 'params[' . $name . ']';
		$title = $this->fieldParameterText(
			(string)($definition['title'] ?? ''),
			$this->phrases['compound_components'] ?? 'Components'
		);
		$description = $this->fieldParameterText(
			(string)($definition['description'] ?? ''),
			''
		);

		return '<fieldset class="cm-field cm-field-wide cm-compound-builder" data-compound-builder'
			. ' data-duplicate-name-message="' . \Core\Html::escape(
				$this->phrases['compound_component_name_duplicate']
					?? 'Compound component names must be unique.'
			) . '"'
			. ' data-invalid-options-message="' . \Core\Html::escape(
				$this->phrases['compound_select_options_invalid']
					?? 'Options must be a valid JSON array.'
			) . '">'
			. '<legend>' . \Core\Html::escape($title) . '</legend>'
			. '<input type="hidden" name="' . \Core\Html::escape($inputName)
			. '" value="' . $hiddenValue . '" data-compound-value>'
			. '<div class="cm-compound-components" data-compound-components>' . $rows . '</div>'
			. '<template data-compound-template>' . $template . '</template>'
			. '<button class="admin-button admin-button-secondary admin-button-small" '
			. 'type="button" data-compound-add>'
			. \Core\Html::escape($this->phrases['compound_add_component'] ?? 'Add component')
			. '</button>'
			. ($description !== '' ? '<small>' . \Core\Html::escape($description) . '</small>' : '')
			. $this->compoundBuilderScript()
			. '</fieldset>';
	}

	private function renderCompoundComponentRow(
		string $name,
		array $component,
		array $typeOptions,
		array $sourceOptions
	): string {
		$type = (string)($component['type'] ?? 'string');
		$params = is_array($component['params'] ?? null) ? $component['params'] : [];
		$optionsHtml = '';
		foreach ($typeOptions as $option) {
			$selected = $option['value'] === $type ? ' selected' : '';
			$optionsHtml .= '<option value="' . \Core\Html::escape((string)$option['value']) . '"'
				. ' data-root="' . \Core\Html::escape((string)$option['root']) . '"'
				. $selected . '>' . \Core\Html::escape((string)$option['label']) . '</option>';
		}

		$sources = array_map('strval', is_array($params['source_fields'] ?? null)
			? $params['source_fields'] : []);
		$sourceHtml = '';
		foreach ($sourceOptions as $option) {
			$selected = in_array((string)$option['value'], $sources, true) ? ' selected' : '';
			$sourceHtml .= '<option value="' . \Core\Html::escape((string)$option['value']) . '"'
				. $selected . '>' . \Core\Html::escape((string)$option['label']) . '</option>';
		}

		$selectOptions = is_array($params['options'] ?? null)
			? \Core\Utils\JsonTool::encode($params['options'], true)
			: '';

		return '<div class="cm-compound-component" data-compound-component>'
			. '<div class="cm-form-grid">'
			. '<label class="cm-field"><span>'
			. \Core\Html::escape($this->phrases['compound_component_name'] ?? 'Component name')
			. '</span><input class="admin-input" type="text" data-component-name '
			. 'pattern="[a-z][a-z0-9_]*" value="' . \Core\Html::escape($name) . '" required></label>'
			. '<label class="cm-field"><span>'
			. \Core\Html::escape($this->phrases['field_type'] ?? 'Field type')
			. '</span><select class="admin-input" data-component-type>' . $optionsHtml . '</select></label>'
			. '</div>'
			. '<div class="cm-settings-grid">'
			. '<label><input type="checkbox" data-component-translatable'
			. (!empty($component['translatable']) ? ' checked' : '') . '> '
			. \Core\Html::escape($this->phrases['translatable'] ?? 'Translatable') . '</label>'
			. '<label><input type="checkbox" data-component-required'
			. (!empty($component['required']) ? ' checked' : '') . '> '
			. \Core\Html::escape($this->phrases['required'] ?? 'Required') . '</label>'
			. '</div>'
			. '<div data-component-special="autocomplete">'
			. '<label class="cm-field"><span>'
			. \Core\Html::escape($this->phrases['field_parameter_source_fields'] ?? 'Suggestion source fields')
			. '</span><select class="admin-input" multiple data-component-source-fields>'
			. $sourceHtml . '</select></label></div>'
			. '<div data-component-special="select">'
			. '<label class="cm-field"><span>'
			. \Core\Html::escape($this->phrases['field_parameter_options'] ?? 'Options (JSON)')
			. '</span><textarea class="admin-input cm-code-input" rows="5" data-component-options>'
			. \Core\Html::escape($selectOptions) . '</textarea></label></div>'
			. '<div data-component-special="media" class="cm-form-grid">'
			. '<label class="cm-field"><span>'
			. \Core\Html::escape($this->phrases['field_parameter_media_root'] ?? 'Media root')
			. '</span><input class="admin-input" type="text" data-component-media-root value="'
			. \Core\Html::escape((string)($params['root'] ?? '')) . '"></label>'
			. '<label class="cm-field"><span>'
			. \Core\Html::escape($this->phrases['field_parameter_media_accept'] ?? 'Accepted media')
			. '</span><input class="admin-input" type="text" data-component-media-accept value="'
			. \Core\Html::escape((string)($params['accept'] ?? '')) . '"></label></div>'
			. '<div class="admin-actions">'
			. '<button class="admin-action-button" type="button" data-compound-up title="'
			. \Core\Html::escape($this->phrases['move_up'] ?? 'Move up') . '">↑</button>'
			. '<button class="admin-action-button" type="button" data-compound-down title="'
			. \Core\Html::escape($this->phrases['move_down'] ?? 'Move down') . '">↓</button>'
			. '<button class="admin-action-button" type="button" data-compound-remove title="'
			. \Core\Html::escape($this->phrases['remove'] ?? 'Remove') . '">×</button>'
			. '</div></div>';
	}

	private function compoundBuilderScript(): string {
		return <<<'HTML'
<script>
(() => {
    document.querySelectorAll('[data-compound-builder]').forEach(builder => {
        if (builder.dataset.initialized === '1') return;
        builder.dataset.initialized = '1';
        const list = builder.querySelector('[data-compound-components]');
        const template = builder.querySelector('template[data-compound-template]');
        const hidden = builder.querySelector('[data-compound-value]');
        if (!list || !template || !hidden) return;

        const syncRow = row => {
            const type = row.querySelector('[data-component-type]')?.value || '';
            const selected = row.querySelector('[data-component-type] option:checked');
            const root = selected?.dataset.root || '';
            const translatable = row.querySelector('[data-component-translatable]');
            if (translatable) {
                translatable.disabled = root !== 'text';
                if (translatable.disabled) translatable.checked = false;
            }
            row.querySelectorAll('[data-component-special]').forEach(group => {
                group.hidden = group.dataset.componentSpecial !== type;
            });
        };

        const serialize = () => {
            const components = {};
            const seenNames = new Map();
            const rows = [...list.querySelectorAll(':scope > [data-compound-component]')];
            rows.forEach(row => row.querySelector('[data-component-name]')?.setCustomValidity(''));

            rows.forEach(row => {
                const nameInput = row.querySelector('[data-component-name]');
                const name = (nameInput?.value || '').trim();
                const type = row.querySelector('[data-component-type]')?.value || '';
                if (name) {
                    const previous = seenNames.get(name);
                    if (previous) {
                        const message = builder.dataset.duplicateNameMessage
                            || 'Compound component names must be unique.';
                        previous.setCustomValidity(message);
                        nameInput?.setCustomValidity(message);
                    } else if (nameInput) {
                        seenNames.set(name, nameInput);
                    }
                }
                if (!name || !type) return;
                const component = {
                    type,
                    translatable: !!row.querySelector('[data-component-translatable]')?.checked,
                    required: !!row.querySelector('[data-component-required]')?.checked
                };
                const params = {};
                if (type === 'autocomplete') {
                    const select = row.querySelector('[data-component-source-fields]');
                    params.source_fields = select
                        ? Array.from(select.selectedOptions).map(option => option.value).filter(Boolean)
                        : [];
                } else if (type === 'select') {
                    const textarea = row.querySelector('[data-component-options]');
                    const raw = (textarea?.value || '').trim();
                    if (textarea) textarea.setCustomValidity('');
                    if (raw) {
                        try {
                            const options = JSON.parse(raw);
                            if (!Array.isArray(options)) throw new Error('array required');
                            params.options = options;
                        } catch (_) {
                            textarea?.setCustomValidity(builder.dataset.invalidOptionsMessage || 'Options must be a valid JSON array.');
                        }
                    }
                } else if (type === 'media') {
                    const root = (row.querySelector('[data-component-media-root]')?.value || '').trim();
                    const accept = (row.querySelector('[data-component-media-accept]')?.value || '').trim();
                    if (root) params.root = root;
                    if (accept) params.accept = accept;
                }
                if (Object.keys(params).length) component.params = params;
                components[name] = component;
            });
            hidden.value = JSON.stringify(components);
        };

        const bind = row => {
            syncRow(row);
            row.addEventListener('change', () => { syncRow(row); serialize(); });
            row.addEventListener('input', serialize);
        };
        list.querySelectorAll(':scope > [data-compound-component]').forEach(bind);

        builder.querySelector('[data-compound-add]')?.addEventListener('click', () => {
            const fragment = template.content.cloneNode(true);
            const row = fragment.querySelector('[data-compound-component]');
            list.append(fragment);
            if (row) {
                bind(row);
                row.querySelector('[data-component-name]')?.focus();
            }
            serialize();
        });

        list.addEventListener('click', event => {
            const button = event.target.closest('button');
            const row = button?.closest('[data-compound-component]');
            if (!button || !row) return;
            if (button.matches('[data-compound-remove]')) row.remove();
            else if (button.matches('[data-compound-up]') && row.previousElementSibling) {
                list.insertBefore(row, row.previousElementSibling);
            } else if (button.matches('[data-compound-down]') && row.nextElementSibling) {
                list.insertBefore(row.nextElementSibling, row);
            }
            serialize();
        });

        builder.closest('form')?.addEventListener('submit', () => {
            serialize();
            const invalid = builder.querySelector(':invalid');
            if (invalid) invalid.reportValidity();
        });
        serialize();
    });
})();
</script>
HTML;
	}

	private function renderFieldParameterOptions(array $definition, mixed $value): string {
		$selected = array_map(
			static fn(mixed $item): string => (string)$item,
			is_array($value) ? $value : [$value]
		);
		$options = [];
		$sortOptions = false;

		if (($definition['type'] ?? null) === 'content_type_id') {
			$sortOptions = true;
			$result = \DB::query(
				'select ct_id, uuid, system_name from content_types'
			);
			while ($contentType = \DB::fetchRow($result)) {
				$translation = \Core\Translation::get($contentType['uuid']) ?? [];
				$optionValue = ($definition['value'] ?? null) === 'system_name'
					? (string)$contentType['system_name']
					: (string)$contentType['ct_id'];
				$options[] = [
					'value' => $optionValue,
					'label' => (string)($translation['title'] ?? $contentType['system_name'])
						. ' (' . $contentType['system_name'] . ')',
				];
			}
		} elseif (($definition['type'] ?? null) === 'field_id') {
			$sortOptions = true;
			$result = \DB::query(
				'select field.field_id, field.uuid, field.system_name, type.system_name as type_name '
				. 'from fields field join field_types type on type.type_id=field.type_id'
			);
			while ($field = \DB::fetchRow($result)) {
				$translation = \Core\Translation::get($field['uuid']) ?? [];
				$optionValue = ($definition['value'] ?? null) === 'system_name'
					? (string)$field['system_name']
					: (string)$field['field_id'];
				$options[] = [
					'value' => $optionValue,
					'label' => (string)($translation['title'] ?? $field['system_name'])
						. ' (' . $field['system_name'] . ': ' . $field['type_name'] . ')',
				];
			}
		} elseif (is_array($definition['options'] ?? null)) {
			foreach ($definition['options'] as $key => $option) {
				if (is_array($option) && array_key_exists('value', $option)) {
					$options[] = [
						'value' => (string)$option['value'],
						'label' => (string)($option['label'] ?? $option['title'] ?? $option['value']),
					];
				} elseif (!is_array($option)) {
					$options[] = ['value' => (string)$key, 'label' => (string)$option];
				}
			}
		}

		if ($sortOptions) {
			$options = \Core\Translation::sortByTitle($options, 'label', null);
		}

		$html = '';
		foreach ($options as $option) {
			$optionAttributes = ['value' => $option['value']];
			if (in_array((string)$option['value'], $selected, true)) {
				$optionAttributes['selected'] = true;
			}
			$html .= '<option' . $this->renderHtmlAttributes($optionAttributes) . '>'
				. \Core\Html::escape((string)$option['label']) . '</option>';
		}
		return $html;
	}

	private function fieldParameterText(string $keyOrText, string $fallback): string {
		if ($keyOrText === '') return $fallback;
		if (isset($this->phrases[$keyOrText]) && is_string($this->phrases[$keyOrText])) {
			return $this->phrases[$keyOrText];
		}
		return preg_match('/\s/u', $keyOrText) ? $keyOrText : $fallback;
	}

	private function renderHtmlAttributes(array $attributes): string {
		$html = '';
		foreach ($attributes as $name => $value) {
			if (!is_string($name) || !preg_match('/^[A-Za-z_:][A-Za-z0-9:._-]*$/', $name)) {
				continue;
			}
			if ($value === false || $value === null) continue;
			if ($value === true) {
				$html .= ' ' . $name;
				continue;
			}
			if (!is_scalar($value)) continue;
			$html .= ' ' . $name . '="' . \Core\Html::escape((string)$value) . '"';
		}
		return $html;
	}

	private function parseFieldParams(array $definitions, array $input): array {
		$params = [];
		foreach ($definitions as $name => $definition) {
			if (!is_string($name) || !is_array($definition)) continue;
			$raw = $input[$name] ?? null;
			$multiple = !empty($definition['multiple']);
			$format = (string)($definition['format'] ?? '');

			if (($definition['type'] ?? null) === 'compound_components') {
				$raw = trim((string)$raw);
				$value = $raw === '' ? [] : json_decode($raw, true);
				if (!is_array($value) || ($raw !== '' && json_last_error() !== JSON_ERROR_NONE)) {
					throw new \InvalidArgumentException(
						$this->phrases['compound_components_invalid']
							?? 'Compound components are invalid.'
					);
				}
			} elseif ($format === 'json') {
				$raw = trim((string)$raw);
				if ($raw === '') {
					$value = [];
				} else {
					$value = json_decode($raw, true);
					if (!is_array($value) || json_last_error() !== JSON_ERROR_NONE) {
						throw new \InvalidArgumentException(str_replace(
							'{parameter}',
							ucwords(str_replace(['_', '-'], ' ', $name)),
							$this->phrases['invalid_field_parameter_json']
								?? 'Parameter {parameter} must contain valid JSON.'
						));
					}
				}
			} elseif ($multiple) {
				$items = is_array($raw) ? $raw : ($raw === null ? [] : [$raw]);
				$value = [];
				foreach ($items as $item) {
					$normalized = $this->normalizeFieldParamScalar($item, $definition, $name);
					if (!$this->fieldParamIsEmpty($normalized)) $value[] = $normalized;
				}
				$value = array_values(array_unique($value, SORT_REGULAR));
			} else {
				$value = $this->normalizeFieldParamScalar($raw, $definition, $name);
			}

			if ($this->fieldParamIsEmpty($value)) {
				if (!empty($definition['required'])) {
					throw new \InvalidArgumentException(str_replace(
						'{parameter}',
						ucwords(str_replace(['_', '-'], ' ', $name)),
						$this->phrases['field_parameter_required']
							?? 'Parameter {parameter} is required.'
					));
				}
				continue;
			}

			$this->validateFieldParamReferences($value, $definition, $name);
			$params[$name] = $value;
		}
		return $params;
	}

	private function normalizeFieldParamScalar(
		mixed $value,
		array $definition,
		string $name
	): mixed {
		if ($value === null) return null;
		$type = (string)($definition['type'] ?? 'string');
		if ($type === 'checkbox' || $type === 'boolean') return !empty($value);
		$value = trim((string)$value);
		if ($value === '') return null;

		if ($type === 'integer') {
			if (!preg_match('/^-?\d+$/', $value)) {
				throw $this->invalidFieldParameter($name);
			}
			return (int)$value;
		}
		if ($type === 'number' || $type === 'decimal') {
			if (!is_numeric($value)) throw $this->invalidFieldParameter($name);
			return (float)$value;
		}
		return $value;
	}

	private function validateFieldParamReferences(
		mixed $value,
		array $definition,
		string $name
	): void {
		$type = $definition['type'] ?? null;
		if (!in_array($type, ['content_type_id', 'field_id'], true)) return;

		$table = $type === 'content_type_id' ? 'content_types' : 'fields';
		$idColumn = $type === 'content_type_id' ? 'ct_id' : 'field_id';
		$column = ($definition['value'] ?? null) === 'system_name'
			? 'system_name'
			: $idColumn;
		$values = is_array($value) ? $value : [$value];
		foreach ($values as $reference) {
			if (!\DB::getOne("select 1 from {$table} where {$column}=$1", [$reference])) {
				throw $this->invalidFieldParameter($name);
			}
		}
	}

	private function validateSuggestionSources(
		array $fieldType,
		array $params,
		string $currentField,
		array $currentSettings
	): void {
		$definitions = is_array($fieldType['type_settings']['parameters'] ?? null)
			? $fieldType['type_settings']['parameters']
			: [];
		if (!array_key_exists('source_fields', $definitions)) {
			return;
		}

		$sources = $params['source_fields'] ?? [];
		$sources = is_array($sources) ? $sources : [$sources];
		foreach ($sources as $source) {
			$source = trim((string)$source);
			if ($source === '') continue;

			if ($source === $currentField) {
				if (empty($currentSettings['indexed'])
					|| (string)($fieldType['root_type_name'] ?? '') !== 'text') {
					throw new \InvalidArgumentException(
						$this->phrases['autocomplete_source_invalid']
							?? 'Autocomplete source fields must be indexed text fields.'
					);
				}
				continue;
			}

			try {
				$definition = \Core\Content::getField($source);
			} catch (\Throwable) {
				throw new \InvalidArgumentException(
					$this->phrases['autocomplete_source_invalid']
						?? 'Autocomplete source fields must be indexed text fields.'
				);
			}
			$settings = array_replace(
				is_array($definition['type_settings'] ?? null) ? $definition['type_settings'] : [],
				is_array($definition['field_settings'] ?? null) ? $definition['field_settings'] : []
			);
			if (empty($settings['indexed'])
				|| (string)($definition['root_type_name'] ?? '') !== 'text') {
				throw new \InvalidArgumentException(
					$this->phrases['autocomplete_source_invalid']
						?? 'Autocomplete source fields must be indexed text fields.'
				);
			}
		}
	}

	private function invalidFieldParameter(string $name): \InvalidArgumentException {
		return new \InvalidArgumentException(str_replace(
			'{parameter}',
			ucwords(str_replace(['_', '-'], ' ', $name)),
			$this->phrases['invalid_field_parameter']
				?? 'Invalid value for parameter {parameter}.'
		));
	}

	private function fieldParamIsEmpty(mixed $value): bool {
		return $value === null || $value === '' || $value === [];
	}

	private function forms(): \Plugins\Forms\Forms {
		return $this->plugins->get('Forms');
	}

	private function pagination(): \Plugins\Pagination\Pagination {
		$plugin = $this->pagination ??= $this->plugins->get('Pagination');
		if (!$plugin instanceof \Plugins\Pagination\Pagination) {
			throw new \RuntimeException('Pagination plugin is not available.');
		}
		return $plugin;
	}

private function notice(string $message, string $kind = 'success'): string {
		return '<div class="admin-notice admin-notice-' . \Core\Html::escape($kind) . '">'
			. \Core\Html::escape($message) . '</div>';
	}


	private function fieldReferenceOptions(array $fields, string $selectedName): string {
		$items = [];
		foreach ($fields as $name => $config) {
			$items[] = [
				'value' => (string)$name,
				'label' => (string)(is_array($config) ? ($config['title'] ?? $name) : $name),
			];
		}
		$items = \Core\Translation::sortByTitle($items, 'label', null);

		$options = '<option value="">—</option>';
		foreach ($items as $item) {
			$selected = $item['value'] === $selectedName ? ' selected' : '';
			$options .= '<option value="' . \Core\Html::escape($item['value']) . '"'
				. $selected . '>' . \Core\Html::escape($item['label']) . '</option>';
		}
		return $options;
	}

	private function validFieldReference(mixed $name, array $fields): ?string {
		$name = trim((string)$name);
		return $name !== '' && array_key_exists($name, $fields) ? $name : null;
	}

	private function syncContentTypeFieldTranslation(
		string $contentTypeUuid,
		string $fieldName,
		string $title,
		string $description,
		string $globalTitle,
		string $globalDescription
	): void {
		$language = defined('LANG') ? LANG : DOMAIN_CONFIG['default_language'];
		$translation = \Core\Translation::get($contentTypeUuid, $language) ?? [];
		$schema = is_array($translation['schema'] ?? null) ? $translation['schema'] : [];
		$fields = is_array($schema['fields'] ?? null) ? $schema['fields'] : [];
		$local = is_array($fields[$fieldName] ?? null) ? $fields[$fieldName] : [];

		if ($title === '' || $title === $globalTitle) unset($local['title']);
		else $local['title'] = $title;

		if ($description === $globalDescription) unset($local['description']);
		else $local['description'] = $description;

		if ($local === []) unset($fields[$fieldName]);
		else $fields[$fieldName] = $local;

		if ($fields === []) unset($schema['fields']);
		else $schema['fields'] = $fields;

		if ($schema === []) unset($translation['schema']);
		else $translation['schema'] = $schema;

		$this->replaceTranslation($contentTypeUuid, $language, $translation);
	}

	private function replaceTranslation(string $uuid, string $language, array $translation): void {
		if ($translation === []) {
			\DB::delete('translations', 'entity_uuid=$1 AND lang_code=$2', [$uuid, $language]);
		} else {
			\DB::query(
				'insert into translations(entity_uuid, lang_code, translated_data)
'
				. 'values($1, $2, $3)
'
				. 'on conflict (entity_uuid, lang_code)
'
				. 'do update set translated_data=excluded.translated_data',
				[$uuid, $language, \Core\Utils\JsonTool::encode($translation, false)]
			);
		}
		\Cache::del("globals:{$uuid}_{$language}");
		\Core\Content::resetStructureCache();
	}

	private function upsertTranslation(string $uuid, array $data): void {
		$language = defined('LANG') ? LANG : DOMAIN_CONFIG['default_language'];
		$current = \Core\Translation::get($uuid, $language) ?? [];
		$translation = array_replace($current, $data);
		\DB::query(
			'insert into translations(entity_uuid, lang_code, translated_data)
			values($1, $2, $3)
			on conflict (entity_uuid, lang_code)
			do update set translated_data=excluded.translated_data',
			[$uuid, $language, \Core\Utils\JsonTool::encode($translation, false)]
		);
		\Cache::del("globals:{$uuid}_{$language}");
	}

	private function nextFieldOrder(array $fields): int {
		$max = 0;
		foreach ($fields as $field) {
			if (is_array($field)) $max = max($max, (int)($field['displayorder'] ?? 0));
		}
		return $max + 1;
	}

	private function forbiddenJson(): string {
		\Core\Response::addHeader('HTTP/1.1 403 Forbidden');
		return \Core\Utils\JsonTool::encode(['status' => 'error', 'error' =>
			$this->phrases['content_type_access_denied']
				?? 'This content type is managed by another plugin.'], false);
	}

	private function fieldUsedOutsideManager(string $fieldName): bool {
		return (bool)\DB::getOne(
			"select 1 from content_types
			where coalesce(schema->'fields', '{}'::jsonb) ? $1
			and manager_plugin_id is distinct from $2 limit 1",
			[$fieldName, $this->id]
		);
	}

	private function isRoot(): bool {
		return \Core\User::isRoot();
	}

	private function managesContentType(int $typeId): bool {
		if ($typeId < 1 || !$this->id) {
			return false;
		}

		return (bool)\DB::getOne(
			'select 1 from content_types
			where ct_id=$1 and manager_plugin_id=$2',
			[$typeId, $this->id]
		);
	}

	private function accessDeniedNotice(): string {
		return '<div class="kc-notice kc-notice-error">'
			. \Core\Html::escape($this->phrases['content_type_access_denied']
					?? 'This content type is managed by another plugin.')
			. '</div>';
	}


	public function itemSave($context_vars) {
		$data = $this->params(['type', 'id'], \Core\Request::getPrefixedParams($this->prefix));
		$form_data = \Core\Request::all();
		$type = \Core\Content::getContentType($data['type']);
		if (!$this->managesContentType((int)$type['ct_id'])) {
			return $this->accessDeniedNotice();
		}
		$form_data = $this->normalizeDateTimeFormData($type, $form_data);
		$item_data = ['plugin_id' => $this->id];
		$item_id = isset($data['id']) ? (int)$data['id'] : 0;
		$parentId = $this->nullablePositiveInt($form_data['parent_id'] ?? null);
		$form_data['parent_id'] = $parentId;

		if ($parentId !== null) {
			$parent = \Core\Content::getItem($parentId);
			$allowedParentTypes = array_fill_keys(
				\Core\User::getAllowedContentTypeIds('view'),
				true
			);
			if ($parent === [] || !isset($allowedParentTypes[(int)($parent['ct_id'] ?? 0)])) {
				return $this->notice(
					$this->phrases['invalid_parent_item']
						?? 'The selected parent item is not available.',
					'error'
				);
			}

			if ($item_id > 0 && $this->wouldCreateItemCycle($item_id, $parentId)) {
				return $this->notice(
					$this->phrases['parent_item_cycle']
						?? 'The selected parent would create a hierarchy cycle.',
					'error'
				);
			}
		}

		if ($item_id > 0) {
			$item = \Core\Content::getItem($item_id);
			if (!$item || (int)$item['ct_id'] !== (int)$type['ct_id']) {
				return '<div class="kc-notice kc-notice-error">'
					. \Core\Html::escape($this->phrases['item_not_found'] ?? 'Content item not found.')
					. '</div>';
			}
		} else {
			$item_id = \Core\Content::create($type['system_name'], $item_data);
		}

		\Core\Content::update($item_id, $form_data);

		if ($notifications = $this->plugins->get('Notifications')) {
			$notifications->store(
				\Core\User::getId(),
				$this->phrases['item_saved'] ?? 'Item saved.',
				'success'
			);
		}

		return \Core\Response::seeOther(
			"/" . PAGE_SLUG
			. "/{$this->prefix}-action/itemList"
			. "/{$this->prefix}-type/{$type['ct_id']}"
		);
	}
	private function normalizeDateTimeFormData(array $contentType, array $data): array {
		$fields = is_array($contentType['schema']['fields'] ?? null)
			? $contentType['schema']['fields']
			: [];

		foreach ($fields as $fieldName => $field) {
			if (!is_string($fieldName) || !is_array($field) || !array_key_exists($fieldName, $data)) {
				continue;
			}

			$definition = \Core\Content::getField($fieldName);
			$fieldType = \Core\Content::getFieldType((int)$definition['type_id']);
			$globalSettings = is_array($definition['field_settings'] ?? null)
				? $definition['field_settings']
				: [];
			$globalParams = is_array($globalSettings['params'] ?? null)
				? $globalSettings['params']
				: [];
			$params = $this->effectiveFieldParams($fieldType, $globalParams, $field);

			$data[$fieldName] = $this->convertDateTimeFieldValue(
				$fieldType,
				$params,
				$data[$fieldName],
				true
			);
		}

		return $data;
	}

	private function convertDateTimeFieldValue(
		array $fieldType,
		array $params,
		mixed $value,
		bool $toStorage
	): mixed {
		if ($this->isDateTimeFieldType($fieldType)) {
			if (is_array($value)) {
				return array_map(
					fn(mixed $item): mixed => $this->convertDateTimeScalar($item, $toStorage),
					$value
				);
			}
			return $this->convertDateTimeScalar($value, $toStorage);
		}

		if ((string)($fieldType['root_type_name'] ?? '') !== 'compound' || !is_array($value)) {
			return $value;
		}

		$components = is_array($params['components'] ?? null) ? $params['components'] : [];
		if ($components === []) {
			return $value;
		}

		$convertRow = function (array $row) use ($components, $toStorage): array {
			foreach ($components as $componentName => $component) {
				if (
					!is_string($componentName)
					|| !is_array($component)
					|| !array_key_exists($componentName, $row)
				) {
					continue;
				}

				$componentType = \Core\Content::getFieldType(
					(string)($component['type'] ?? 'string')
				);
				if (!$this->isDateTimeFieldType($componentType)) {
					continue;
				}

				$row[$componentName] = $this->convertDateTimeScalar(
					$row[$componentName],
					$toStorage
				);
			}
			return $row;
		};

		$componentNames = array_fill_keys(array_keys($components), true);
		$isSingleRow = false;
		foreach ($value as $key => $_item) {
			if (is_string($key) && isset($componentNames[$key])) {
				$isSingleRow = true;
				break;
			}
		}

		if ($isSingleRow) {
			return $convertRow($value);
		}

		foreach ($value as $key => $row) {
			if (is_array($row)) {
				$value[$key] = $convertRow($row);
			}
		}
		return $value;
	}

	private function isDateTimeFieldType(array $fieldType): bool {
		return (string)($fieldType['system_name'] ?? '') === 'datetime'
			|| (string)($fieldType['type_settings']['input']['template'] ?? '') === 'datetime_input';
	}

	private function convertDateTimeScalar(mixed $value, bool $toStorage): mixed {
		if ($value === null || $value === '') {
			return '';
		}
		if (!is_scalar($value)) {
			return $value;
		}

		return $toStorage
			? \Core\Date::storage((string)$value)
			: \Core\Date::format((string)$value, 'Y-m-d\\TH:i:s');
	}

	private function nullablePositiveInt(mixed $value): ?int {
		if ($value === null || $value === '' || !is_numeric($value)) {
			return null;
		}

		$value = (int)$value;
		return $value > 0 ? $value : null;
	}

	private function wouldCreateItemCycle(int $itemId, int $parentId): bool {
		if ($itemId === $parentId) {
			return true;
		}

		return (bool)\DB::getOne(
			'WITH RECURSIVE descendants AS (
				SELECT child.item_id, ARRAY[child.item_id]::bigint[] AS path
				FROM content_items child
				WHERE child.parent_id=$1

				UNION ALL

				SELECT child.item_id, parent.path || child.item_id
				FROM content_items child
				JOIN descendants parent ON child.parent_id=parent.item_id
				WHERE NOT child.item_id=ANY(parent.path)
			)
			SELECT 1 FROM descendants WHERE item_id=$2 LIMIT 1',
			[$itemId, $parentId]
		);
	}

	private function apiContentType(string $identifier): ?array {
		if (ctype_digit($identifier)) {
			return \DB::getRow(
				'SELECT ct_id, uuid, system_name FROM content_types WHERE ct_id=$1',
				[(int)$identifier]
			) ?: null;
		}
		if (preg_match(
			'/^[0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i',
			$identifier
		)) {
			return \DB::getRow(
				'SELECT ct_id, uuid, system_name FROM content_types WHERE uuid=$1',
				[$identifier]
			) ?: null;
		}

		return \DB::getRow(
			'SELECT ct_id, uuid, system_name FROM content_types WHERE system_name=$1',
			[$identifier]
		) ?: null;
	}

	private function apiNonNegativeInt(mixed $value, string $name): int {
		$value = is_string($value) ? trim($value) : $value;
		if (!is_int($value) && !(is_string($value) && ctype_digit($value))) {
			throw new \Core\PluginActionException(
				"{$name} must be a non-negative integer.",
				\Core\PluginActionException::BAD_REQUEST
			);
		}

		$number = (int)$value;
		if ($number < 0) {
			throw new \Core\PluginActionException(
				"{$name} must be a non-negative integer.",
				\Core\PluginActionException::BAD_REQUEST
			);
		}
		return $number;
	}

	private function apiPositiveInt(mixed $value, string $name): int {
		$number = $this->apiNonNegativeInt($value, $name);
		if ($number < 1) {
			throw new \Core\PluginActionException(
				"{$name} must be a positive integer.",
				\Core\PluginActionException::BAD_REQUEST
			);
		}
		return $number;
	}

	private function assertApiFilterField(array $structure, string $fieldName, bool $range): void {
		$field = $structure[$fieldName] ?? null;
		if (!is_array($field)) {
			throw new \Core\PluginActionException(
				"Unknown field '{$fieldName}' for this content type.",
				\Core\PluginActionException::BAD_REQUEST
			);
		}
		if (empty($field['settings']['indexed'])) {
			throw new \Core\PluginActionException(
				"Field '{$fieldName}' is not indexed and cannot be used as a simple filter.",
				\Core\PluginActionException::BAD_REQUEST
			);
		}
		if (!$range) {
			return;
		}

		$definition = \Core\Content::getField($fieldName);
		if (!in_array((string)($definition['root_type_name'] ?? ''), ['number', 'date', 'time'], true)) {
			throw new \Core\PluginActionException(
				"Field '{$fieldName}' does not support range filtering.",
				\Core\PluginActionException::BAD_REQUEST
			);
		}
	}

	private function apiItem(array $item): array {
		$domains = [];
		if (is_string($item['domains'] ?? null) && trim((string)$item['domains']) !== '') {
			$domains = array_map('intval', \DB::convertArr((string)$item['domains']));
		} elseif (is_array($item['domains'] ?? null)) {
			$domains = array_map('intval', $item['domains']);
		}

		return [
			'item_id' => (int)$item['item_id'],
			'uuid' => (string)$item['item_uuid'],
			'ct_id' => (int)$item['ct_id'],
			'type' => (string)($item['content_type_name'] ?? ''),
			'author_id' => isset($item['author_id']) ? (int)$item['author_id'] : null,
			'plugin_id' => isset($item['plugin_id']) ? (int)$item['plugin_id'] : null,
			'slug' => $item['item_slug'] !== null ? (string)$item['item_slug'] : null,
			'parent_id' => isset($item['parent_id']) ? (int)$item['parent_id'] : null,
			'created_at' => (string)($item['created_at'] ?? ''),
			'updated_at' => (string)($item['updated_at'] ?? ''),
			'domain_scope' => (int)($item['domain_scope'] ?? 0),
			'domains' => $domains,
			'settings' => \Core\Utils\JsonTool::decodeArray($item['item_settings'] ?? null),
			'data' => is_array($item['data'] ?? null) ? $item['data'] : [],
			'title' => $item['title'] ?? null,
			'summary' => $item['summary'] ?? null,
		];
	}

	public function listTypes(array $data = []): array {
		$allowedTypeIds = \Core\User::getAllowedContentTypeIds('view');
		if ($allowedTypeIds === []) {
			return ['content_types' => []];
		}

		$contentTypes = [];
		$result = \DB::query(
			'SELECT ct.ct_id, ct.uuid, ct.system_name, ct.schema, ct.has_slug,
			        parent.system_name AS parent_name,
			        owner.system_name AS owner_name,
			        default_manager.system_name AS default_manager_name,
			        manager.system_name AS manager_name,
			        ct.manager_overridden
			 FROM content_types ct
			 LEFT JOIN content_types parent ON parent.ct_id=ct.parent_id
			 LEFT JOIN plugins owner ON owner.plugin_id=ct.plugin_id
			 LEFT JOIN plugins default_manager ON default_manager.plugin_id=ct.default_manager_plugin_id
			 LEFT JOIN plugins manager ON manager.plugin_id=ct.manager_plugin_id
			 WHERE ct.ct_id=ANY($1::int[])
			 ORDER BY ct.system_name',
			[\DB::prepareIdArray($allowedTypeIds)]
		);

		while ($row = \DB::fetchRow($result)) {
			$contentTypes[] = [
				'ct_id' => (int)$row['ct_id'],
				'uuid' => (string)$row['uuid'],
				'system_name' => (string)$row['system_name'],
				'parent' => $row['parent_name'] !== null ? (string)$row['parent_name'] : null,
				'has_slug' => (bool)$row['has_slug'],
				'owner' => $row['owner_name'] !== null ? (string)$row['owner_name'] : null,
				'default_manager' => $row['default_manager_name'] !== null
					? (string)$row['default_manager_name']
					: null,
				'manager' => $row['manager_name'] !== null ? (string)$row['manager_name'] : null,
				'manager_overridden' => (bool)$row['manager_overridden'],
				'own_schema' => \Core\Utils\JsonTool::decodeArray($row['schema'] ?? null),
			];
		}

		return ['content_types' => $contentTypes];
	}

	public function getType(array $data = []): array {
		$identifier = trim((string)($data['type'] ?? ''));
		if ($identifier === '') {
			throw new \Core\PluginActionException(
				'Content type not found.',
				\Core\PluginActionException::NOT_FOUND
			);
		}

		if (ctype_digit($identifier)) {
			$where = 'ct.ct_id=$1';
			$params = [(int)$identifier];
		} elseif (preg_match(
			'/^[0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i',
			$identifier
		)) {
			$where = 'ct.uuid=$1';
			$params = [$identifier];
		} else {
			$where = 'ct.system_name=$1';
			$params = [$identifier];
		}

		$row = \DB::getRow(
			'SELECT ct.*, parent.system_name AS parent_name,
			        owner.system_name AS owner_name,
			        default_manager.system_name AS default_manager_name,
			        manager.system_name AS manager_name
			 FROM content_types ct
			 LEFT JOIN content_types parent ON parent.ct_id=ct.parent_id
			 LEFT JOIN plugins owner ON owner.plugin_id=ct.plugin_id
			 LEFT JOIN plugins default_manager ON default_manager.plugin_id=ct.default_manager_plugin_id
			 LEFT JOIN plugins manager ON manager.plugin_id=ct.manager_plugin_id
			 WHERE ' . $where . '
			 LIMIT 1',
			$params
		);

		if (!$row) {
			throw new \Core\PluginActionException(
				'Content type not found.',
				\Core\PluginActionException::NOT_FOUND
			);
		}

		if (!\Core\User::canContent((int)$row['ct_id'], 'view')) {
			throw new \Core\PluginActionException(
				'Content type access denied.',
				\Core\PluginActionException::FORBIDDEN
			);
		}

		$effective = \Core\Content::getContentType((int)$row['ct_id']);

		return [
			'content_type' => [
				'ct_id' => (int)$row['ct_id'],
				'uuid' => (string)$row['uuid'],
				'system_name' => (string)$row['system_name'],
				'parent' => $row['parent_name'] !== null ? (string)$row['parent_name'] : null,
				'has_slug' => (bool)$row['has_slug'],
				'owner' => $row['owner_name'] !== null ? (string)$row['owner_name'] : null,
				'default_manager' => $row['default_manager_name'] !== null
					? (string)$row['default_manager_name']
					: null,
				'manager' => $row['manager_name'] !== null ? (string)$row['manager_name'] : null,
				'manager_overridden' => (bool)$row['manager_overridden'],
				'title' => (string)($effective['title'] ?? $row['system_name']),
				'description' => $effective['description'] ?? null,
				'own_schema' => \Core\Utils\JsonTool::decodeArray($row['schema'] ?? null),
				'effective_schema' => is_array($effective['schema'] ?? null)
					? $effective['schema']
					: [],
			],
		];
	}

	public function listFieldTypes(array $data = []): array {
		$fieldTypes = [];
		$result = \DB::query(
			'SELECT ft.type_id, ft.uuid, ft.system_name, ft.type_settings,
			        parent.system_name AS parent_name
			 FROM field_types ft
			 LEFT JOIN field_types parent ON parent.type_id=ft.parent_id
			 ORDER BY ft.system_name'
		);

		while ($row = \DB::fetchRow($result)) {
			$fieldTypes[] = [
				'type_id' => (int)$row['type_id'],
				'uuid' => (string)$row['uuid'],
				'system_name' => (string)$row['system_name'],
				'parent' => $row['parent_name'] !== null ? (string)$row['parent_name'] : null,
				'own_settings' => \Core\Utils\JsonTool::decodeArray($row['type_settings'] ?? null),
			];
		}

		return ['field_types' => $fieldTypes];
	}

	public function getFieldType(array $data = []): array {
		$identifier = trim((string)($data['type'] ?? ''));
		if ($identifier === '') {
			throw new \Core\PluginActionException(
				'Field type not found.',
				\Core\PluginActionException::NOT_FOUND
			);
		}

		if (ctype_digit($identifier)) {
			$row = \DB::getRow(
				'SELECT * FROM field_types WHERE type_id=$1',
				[(int)$identifier]
			);
		} elseif (preg_match(
			'/^[0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i',
			$identifier
		)) {
			$row = \DB::getRow(
				'SELECT * FROM field_types WHERE uuid=$1',
				[$identifier]
			);
		} else {
			$row = \DB::getRow(
				'SELECT * FROM field_types WHERE system_name=$1',
				[$identifier]
			);
		}

		if (!$row) {
			throw new \Core\PluginActionException(
				'Field type not found.',
				\Core\PluginActionException::NOT_FOUND
			);
		}

		$inheritanceChain = [];
		$visited = [];
		$current = $row;
		while ($current) {
			$currentId = (int)$current['type_id'];
			if (isset($visited[$currentId])) {
				throw new \RuntimeException('Circular field type inheritance detected.');
			}
			$visited[$currentId] = true;
			$inheritanceChain[] = (string)$current['system_name'];

			$parentId = (int)($current['parent_id'] ?? 0);
			$current = $parentId > 0
				? \DB::getRow('SELECT * FROM field_types WHERE type_id=$1', [$parentId])
				: false;
		}
		$inheritanceChain = array_reverse($inheritanceChain);

		$parentName = count($inheritanceChain) > 1
			? $inheritanceChain[count($inheritanceChain) - 2]
			: null;
		$effective = \Core\Content::getFieldType((int)$row['type_id']);

		return [
			'field_type' => [
				'type_id' => (int)$row['type_id'],
				'uuid' => (string)$row['uuid'],
				'system_name' => (string)$row['system_name'],
				'parent' => $parentName !== null ? (string)$parentName : null,
				'inheritance_chain' => $inheritanceChain,
				'own_settings' => \Core\Utils\JsonTool::decodeArray($row['type_settings'] ?? null),
				'effective_settings' => is_array($effective['type_settings'] ?? null)
					? $effective['type_settings']
					: [],
			],
		];
	}

	public function listFields(array $data = []): array {
		$fields = [];
		$result = \DB::query(
			'SELECT f.field_id, f.uuid, f.system_name, f.field_settings,
			        ft.system_name AS type_name, fv.variant_name
			 FROM fields f
			 JOIN field_types ft ON ft.type_id=f.type_id
			 LEFT JOIN field_variants fv ON fv.variant_id=f.variant_id
			 ORDER BY f.system_name'
		);

		while ($row = \DB::fetchRow($result)) {
			$fields[] = [
				'field_id' => (int)$row['field_id'],
				'uuid' => (string)$row['uuid'],
				'system_name' => (string)$row['system_name'],
				'type' => (string)$row['type_name'],
				'variant' => $row['variant_name'] !== null ? (string)$row['variant_name'] : null,
				'own_settings' => \Core\Utils\JsonTool::decodeArray($row['field_settings'] ?? null),
			];
		}

		return ['fields' => $fields];
	}

	public function getField(array $data = []): array {
		$identifier = trim((string)($data['field'] ?? ''));
		if ($identifier === '') {
			throw new \Core\PluginActionException(
				'Field not found.',
				\Core\PluginActionException::NOT_FOUND
			);
		}

		if (ctype_digit($identifier)) {
			$where = 'f.field_id=$1';
			$params = [(int)$identifier];
		} elseif (preg_match(
			'/^[0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i',
			$identifier
		)) {
			$where = 'f.uuid=$1';
			$params = [$identifier];
		} else {
			$where = 'f.system_name=$1';
			$params = [$identifier];
		}

		$row = \DB::getRow(
			'SELECT f.*, ft.system_name AS type_name, fv.variant_name
			 FROM fields f
			 JOIN field_types ft ON ft.type_id=f.type_id
			 LEFT JOIN field_variants fv ON fv.variant_id=f.variant_id
			 WHERE ' . $where . '
			 LIMIT 1',
			$params
		);

		if (!$row) {
			throw new \Core\PluginActionException(
				'Field not found.',
				\Core\PluginActionException::NOT_FOUND
			);
		}

		$field = \Core\Content::getField((int)$row['field_id']);
		$fieldType = \Core\Content::getFieldType((int)$row['type_id']);
		$ownSettings = \Core\Utils\JsonTool::decodeArray($row['field_settings'] ?? null);

		$typeSettings = is_array($field['type_settings'] ?? null)
			? $field['type_settings']
			: [];
		$parameterDefinitions = is_array($typeSettings['parameters'] ?? null)
			? $typeSettings['parameters']
			: [];
		unset($typeSettings['parameters']);

		$globalSettings = $ownSettings;
		$globalParams = is_array($globalSettings['params'] ?? null)
			? $globalSettings['params']
			: [];
		unset($globalSettings['params']);

		$effectiveSettings = array_replace($typeSettings, $globalSettings);
		$effectiveParams = $this->fieldParameterDefaults($parameterDefinitions);
		$effectiveParams = array_replace($effectiveParams, $globalParams);
		$effectiveParams = array_intersect_key($effectiveParams, $parameterDefinitions);
		if ($effectiveParams !== []) {
			$effectiveSettings['params'] = $effectiveParams;
		}

		return [
			'field' => [
				'field_id' => (int)$row['field_id'],
				'uuid' => (string)$row['uuid'],
				'system_name' => (string)$row['system_name'],
				'type' => (string)$row['type_name'],
				'variant' => $row['variant_name'] !== null ? (string)$row['variant_name'] : null,
				'root_type' => (string)($fieldType['root_type_name'] ?? $row['type_name']),
				'own_settings' => $ownSettings,
				'effective_settings' => $effectiveSettings,
			],
		];
	}

	public function searchItems(array $data = []): array {
		return [];
	}

	public function getItems(array $data = []): array {
		$typeIdentifier = trim((string)($data['type'] ?? ''));
		if ($typeIdentifier === '') {
			throw new \Core\PluginActionException(
				'Content type is required.',
				\Core\PluginActionException::BAD_REQUEST
			);
		}

		$contentType = $this->apiContentType($typeIdentifier);
		if (!$contentType) {
			throw new \Core\PluginActionException(
				'Content type not found.',
				\Core\PluginActionException::NOT_FOUND
			);
		}
		if (!\Core\User::canContent((int)$contentType['ct_id'], 'view')) {
			throw new \Core\PluginActionException(
				'Content type access denied.',
				\Core\PluginActionException::FORBIDDEN
			);
		}

		$offset = $this->apiNonNegativeInt($data['offset'] ?? 0, 'offset');
		$limit = $this->apiPositiveInt(
			$data['limit'] ?? self::API_ITEMS_DEFAULT_LIMIT,
			'limit'
		);
		if ($limit > self::API_ITEMS_MAX_LIMIT) {
			throw new \Core\PluginActionException(
				'Limit exceeds the maximum allowed value.',
				\Core\PluginActionException::BAD_REQUEST
			);
		}

		$structure = \Core\Content::getContentType((int)$contentType['ct_id'])['schema']['fields'] ?? [];
		$filters = [];
		$reserved = ['type', 'offset', 'limit', 'field', 'from', 'to'];

		foreach ($data as $name => $value) {
			if (!is_string($name) || in_array($name, $reserved, true)) {
				continue;
			}
			$this->assertApiFilterField($structure, $name, false);
			if (is_array($value) || is_object($value)) {
				throw new \Core\PluginActionException(
					"Filter '{$name}' must contain a scalar value.",
					\Core\PluginActionException::BAD_REQUEST
				);
			}
			$filters[] = [
				'field' => $name,
				'mode' => 'eq',
				'value' => $value,
			];
		}

		$rangeField = trim((string)($data['field'] ?? ''));
		$hasFrom = array_key_exists('from', $data);
		$hasTo = array_key_exists('to', $data);
		if ($rangeField !== '' || $hasFrom || $hasTo) {
			if ($rangeField === '' || (!$hasFrom && !$hasTo)) {
				throw new \Core\PluginActionException(
					'Range filter requires field and at least one of from/to.',
					\Core\PluginActionException::BAD_REQUEST
				);
			}
			$this->assertApiFilterField($structure, $rangeField, true);

			if ($hasFrom) {
				if (is_array($data['from']) || is_object($data['from'])) {
					throw new \Core\PluginActionException(
						'Range value from must be scalar.',
						\Core\PluginActionException::BAD_REQUEST
					);
				}
				$filters[] = ['field' => $rangeField, 'mode' => 'gte', 'value' => $data['from']];
			}
			if ($hasTo) {
				if (is_array($data['to']) || is_object($data['to'])) {
					throw new \Core\PluginActionException(
						'Range value to must be scalar.',
						\Core\PluginActionException::BAD_REQUEST
					);
				}
				$filters[] = ['field' => $rangeField, 'mode' => 'lte', 'value' => $data['to']];
			}
		}

		try {
			$result = \Core\Content::search(
				[(int)$contentType['ct_id']],
				'substr',
				null,
				$filters,
				null,
				$offset,
				$limit
			);
		} catch (\InvalidArgumentException $e) {
			throw new \Core\PluginActionException(
				$e->getMessage(),
				\Core\PluginActionException::BAD_REQUEST
			);
		}

		$items = [];
		foreach ($result['ids'] as $itemId) {
			$item = \Core\Content::getItem((int)$itemId);
			if ($item !== []) {
				$items[] = $this->apiItem($item);
			}
		}

		return [
			'items' => $items,
			'pagination' => [
				'offset' => $offset,
				'limit' => $limit,
				'total' => (int)$result['totals'],
			],
		];
	}

	public function getItem(array $data = []): array {
		$identifiers = [];
		foreach (['id', 'uuid', 'slug'] as $name) {
			if (array_key_exists($name, $data) && trim((string)$data[$name]) !== '') {
				$identifiers[$name] = trim((string)$data[$name]);
			}
		}
		if (count($identifiers) !== 1) {
			throw new \Core\PluginActionException(
				'Exactly one of id, uuid or slug is required.',
				\Core\PluginActionException::BAD_REQUEST
			);
		}

		$name = array_key_first($identifiers);
		$value = $identifiers[$name];
		if ($name === 'id') {
			if (!ctype_digit($value) || (int)$value < 1) {
				throw new \Core\PluginActionException(
					'Invalid item ID.',
					\Core\PluginActionException::BAD_REQUEST
				);
			}
			$where = 'item_id=$1';
			$params = [(int)$value];
		} elseif ($name === 'uuid') {
			if (!preg_match(
				'/^[0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i',
				$value
			)) {
				throw new \Core\PluginActionException(
					'Invalid item UUID.',
					\Core\PluginActionException::BAD_REQUEST
				);
			}
			$where = 'item_uuid=$1';
			$params = [$value];
		} else {
			$where = 'item_slug=$1';
			$params = [$value];
		}

		$row = \DB::getRow(
			'SELECT item_id, ct_id FROM content_items WHERE ' . $where . ' LIMIT 1',
			$params
		);
		if (!$row) {
			throw new \Core\PluginActionException(
				'Content item not found.',
				\Core\PluginActionException::NOT_FOUND
			);
		}
		if (!\Core\User::canContent((int)$row['ct_id'], 'view')) {
			throw new \Core\PluginActionException(
				'Content item access denied.',
				\Core\PluginActionException::FORBIDDEN
			);
		}

		$item = \Core\Content::getItem((int)$row['item_id']);
		if ($item === []) {
			throw new \Core\PluginActionException(
				'Content item not found.',
				\Core\PluginActionException::NOT_FOUND
			);
		}

		return ['item' => $this->apiItem($item)];
	}

	public function createItem(array $data = []): array {
		return [];
	}

	public function updateItem(array $data = []): array {
		return [];
	}

	public function deleteItem(array $data = []): array {
		return [];
	}

	public function updateType(array $data = []): array {
		return [];
	}

	public function attachField(array $data = []): array {
		return [];
	}

	public function detachField(array $data = []): array {
		return [];
	}

	public function reorderFields(array $data = []): array {
		return [];
	}
	public function itemDelete(?array $data): string {
		$itemId = (int)($data['cm-id'] ?? 0);
		if ($itemId < 1) {
			return \Core\Utils\JsonTool::encode([
				'status' => 'error',
				'error' => $this->phrases['invalid_item'] ?? 'Invalid content item.',
			], false);
		}

		$item = \DB::getRow(
			'select ct_id from content_items where item_id=$1',
			[$itemId]
		);
		if (!$item) {
			return \Core\Utils\JsonTool::encode([
				'status' => 'error',
				'error' => $this->phrases['item_not_found']
					?? 'Content item not found.',
			], false);
		}
		if (!$this->managesContentType((int)$item['ct_id'])) {
			\Core\Response::addHeader('HTTP/1.1 403 Forbidden');
			return \Core\Utils\JsonTool::encode([
				'status' => 'error',
				'error' => $this->phrases['content_type_access_denied']
					?? 'This content type is managed by another plugin.',
			], false);
		}

		if (!\Core\Content::delete($itemId)) {
			return \Core\Utils\JsonTool::encode([
				'status' => 'error',
				'error' => $this->phrases['item_not_found']
					?? 'Content item not found.',
			], false);
		}

		return \Core\Utils\JsonTool::encode([
			'status' => 'ok',
			'message' => $this->phrases['item_deleted'] ?? 'Item deleted.',
		], false);
	}
}
