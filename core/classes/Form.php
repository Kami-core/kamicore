<?php

/**
 * KamiCore
 *
 * SPDX-License-Identifier: Apache-2.0
 *
 * @see https://kamicore.org
 */

/**
 * Simple form generator for KamiCore
 * Supports template-based rendering (.tpl files with {{variable}} placeholders)
 */

namespace Core;

if(!IN_KAMI) die();

final class Form
{

    /**
     * Render full form
     */

	protected static function renderCsrfField(): string
    {
        $token = self::generateCsrfToken();
        return '<input type="hidden" name="csrf_token" value="' . Html::escape($token) . '">';
    }

    protected static function generateCsrfToken(): string
    {
        if (!isset($_SESSION)) session_start();
        $token = Crypto::randomHex();
        $_SESSION['csrf_token'] = $token;
        return $token;
    }

    public static function render(array $config, ?string $template = null): string
    {
		$template ??= 'form';

		$fields = $config['fields'] ?? [];
        $action = $config['action'] ?? '';
        $method = strtoupper($config['method'] ?? 'POST');
        $csrf = $config['csrf'] ?? true;

        $fieldsHtml = '';
		$tpl = $template;
        foreach ($fields as $field_name => $field) {

			$field['name'] = $field_name;

            $fieldsHtml .= self::renderField($field, $config['plugin_name']) . "\n";
        }

        $csrfField = $csrf ? self::renderCsrfField() : '';

		$content_type_field = $config['content_type'] ? "<input type=hidden name='content_type' value='{$config['content_type']}'>" : "";

        return Renderer::render($template, $config['plugin_name'], [
            'action' => Html::escape($action),
            'method' => $method,
            'fields' => $fieldsHtml . $csrfField . $content_type_field
        ]);
    }

    /**
     * Render a single field based on its type
     */
    public static function renderField(array $field, ?string $pluginName = null): string
    {
		$field_settings = [];
		$field_settings_field = [];
		$field_settings_type = [];

		$field_settings_field = $field['settings'] ?? [];

		$type = $field['type'] ?? 'text';
		$field_settings_type = \Cache::get("globals:field_settings:{$type}");
		if(!$field_settings_type) {
			$row = \DB::getRow(
				'select * from field_types where system_name=$1',
				[$type]
			);
			$field_settings_type = \Core\Utils\JsonTool::decodeArray($row['type_settings'] ?? null);
			\Cache::set("globals:field_settings:{$type}", $field_settings_type);
		}

		$field['settings'] = array_replace($field_settings_type, $field_settings_field);
		$field['settings']['multiple'] ??= false;

        $tpl = !empty($field['tpl'])
            ? (string)$field['tpl']
            : ($field['settings']['templates']['edit'] ?? "form-{$type}");

        if ($tpl === 'form-html') {
            Assets::css('/third-party/frontend/quill/quill.snow.css');
            Assets::js('/third-party/frontend/quill/quill.js');
            Assets::js('/assets/js/quill-init.js');
        }

        if (in_array($tpl, ['form-ct_id', 'form-item_id'], true)) {
            Assets::css('/assets/vendor/tom-select/tom-select.css');
            Assets::js('/assets/vendor/tom-select/tom-select.complete.js');
            Assets::js('/assets/js/tom-select-init.js');
        }

		// if field pre-processing function exists
		if(isset($field['settings']['functions']['edit']) && $field['settings']['functions']['edit']) {
			$method = $field['settings']['functions']['edit'];
			$pluginClass = ($pluginName) ? "\\Plugins\\{$pluginName}\\{$pluginName}" : null;

			if(function_exists($method)) {
				$vars = $method($field);
			} elseif ($pluginClass && method_exists($pluginClass, $method)) {
				$vars = $pluginClass::$method($field);
			} elseif (method_exists(self::class, $method)) {
				$vars = self::$method($field);
			} else {
				trigger_error("unknown pre-processing function for field {$field['name']}: {$field['settings']['functions']['edit']}");
			}
		} else {
			// Default values
			$vars = [
				'name' => Html::escape($field['name'] ?? ''),
				'label' => Html::escape($field['title'] ?? ''),
				'value' => ($field['settings']['multiple']) ? "" : Html::escape($field['value'] ?? $field['default'] ?? ""),
				'placeholder' => Html::escape($field['placeholder'] ?? $field['title']),
				'required' => !empty($field['required']) ? 'required' : '',
				'options' => $field['options'] ?? [],
				'multiple' => $field['settings']['multiple']
			];
		}

		$vars['multiple'] ??= false;
		$vars['multiple_applied'] ??= false;

		if($vars['multiple'] && !$vars['multiple_applied']) {
			$field_container = "<div id='{$field['name']}_container'>";
			if(is_array($field['value'])) {
				foreach($field['value'] as $row_id => $value) {
					if(is_array($value)) $value = $value[0];
					$cur_vars = $vars;
					$cur_vars['name'] =  Html::escape($field['name'] ?? '')."[{$row_id}]";
					$cur_vars['id'] =  Html::escape($field['name'] ?? '')."_{$row_id}";
					$cur_vars['value'] =  Html::escape($value ?? $field['default'] ?? "");

					$field_container .= Renderer::render($tpl, $pluginName, $cur_vars);
				}
			} else {
				$cur_vars = $vars;
				$cur_vars['name'] =  Html::escape($field['name'] ?? '')."[]";
				$cur_vars['value'] =  Html::escape($field['value'] ?? $field['default'] ?? "");
				$field_container .= Renderer::render($tpl, $pluginName, $cur_vars);
			}

			$cur_vars = $vars;
			$cur_vars['name'] =  Html::escape($field['name'] ?? '')."[]";
			$cur_vars['value'] =  Html::escape($field['default'] ?? "");

			$field_container .= "</div>
			<button type='button' class='uk-button uk-button-primary' id='{$field['name']}_add'>Add {$field['title']}</button>
			<div id='{$field['name']}_add_field' hidden>".Renderer::render($tpl, $pluginName, $cur_vars)."</div>";

			$field_container .= <<<EJS
			<script>
				let counter_{$field['name']} = 0;
				document.getElementById('{$field['name']}_add').addEventListener('click', () => {
					const fieldset = document.getElementById('{$field['name']}_container');
					const template = document.getElementById('{$field['name']}_add_field');

					if (!fieldset || !template) return;

					const clone = template.cloneNode(true);

					clone.removeAttribute('id');
					clone.hidden = false;

					counter_{$field['name']}++;

					clone.querySelectorAll('input, select, textarea').forEach(el => {
						if (!el.id) return;

						const oldId = el.id;
						const newId = oldId + '_' + counter_{$field['name']};

						el.id = newId;

						// Update the matching label[for] as well.
						const label = clone.querySelector('label[for="' + oldId + '"]');
						if (label) {
							label.setAttribute('for', newId);
						}
					});

					fieldset.appendChild(clone);
				});
			</script>
EJS;
			return $field_container;
		}

        return Renderer::render($tpl, $pluginName, $vars);
    }

    // Utilities

	public static function editContentItem(array $field): array
	{
		$contentTypes = $field['content_types'] ?? [];
		if (!is_array($contentTypes)) {
			$contentTypes = [$contentTypes];
		}
		if (!$contentTypes && isset($field['content_type'])) {
			$contentTypes = [$field['content_type']];
		}

		$contentTypeIds = [];
		foreach ($contentTypes as $contentType) {
			$contentTypeId = is_numeric($contentType)
				? (int)$contentType
				: (int)(\DB::getOne(
					'select ct_id from content_types where system_name=$1',
					[(string)$contentType]
				) ?? 0);
			if ($contentTypeId > 0) {
				$contentTypeIds[] = $contentTypeId;
			}
		}
		$contentTypeIds = array_values(array_unique($contentTypeIds));

		$name = !empty($field['settings']['multiple'])
			? "{$field['name']}[]"
			: (string)$field['name'];
		$fieldId = 'field_' . trim(
			preg_replace('/[^A-Za-z0-9_-]+/', '_', (string)$field['name']) ?? '',
			'_'
		);
		$options = [];
		$values = is_array($field['value'] ?? null)
			? $field['value']
			: [$field['value'] ?? null];

		foreach ($values as $value) {
			if (!is_numeric($value)) {
				continue;
			}
			$item = \Core\Content::getItem((int)$value);
			if (!$item) {
				continue;
			}
			$options[] = [
				'template' => 'form-select-option',
				'params' => [
					'title' => $item['title'] ?? "#{$value}",
					'value' => $value,
					'selected' => 'selected',
				],
			];
		}

		return [
			'name' => Html::escape($name),
			'id' => Html::escape($fieldId),
			'label' => Html::escape((string)($field['title'] ?? '')),
			'value' => '',
			'placeholder' => Html::escape((string)($field['placeholder'] ?? $field['title'] ?? '')),
			'required' => !empty($field['required']) ? 'required' : '',
			'ct_ids' => implode(',', $contentTypeIds),
			'multiple' => !empty($field['settings']['multiple']) ? 'multiple' : '',
			'multiple_applied' => true,
			'options' => $options,
		];
	}

	public static function buildSelect(array $field): array
	{
		$value = $field['value'] ?? $field['default'] ?? '';
		$options = [];

		foreach (($field['options'] ?? []) as $option) {
			if (!is_array($option)) {
				continue;
			}

			$optionValue = (string)($option['value'] ?? '');
			$options[] = [
				'template' => 'form-select-option',
				'params' => [
					'title' => Html::escape((string)($option['title'] ?? $optionValue)),
					'value' => Html::escape($optionValue),
					'selected' => (string)$value === $optionValue ? 'selected' : '',
				],
			];
		}

		$fieldId = 'field_' . trim(
			preg_replace('/[^A-Za-z0-9_-]+/', '_', (string)($field['name'] ?? '')) ?? '',
			'_'
		);

		return [
			'name' => Html::escape((string)($field['name'] ?? '')),
			'id' => Html::escape($fieldId),
			'label' => Html::escape((string)($field['title'] ?? '')),
			'value' => Html::escape((string)$value),
			'placeholder' => Html::escape((string)($field['placeholder'] ?? $field['title'] ?? '')),
			'required' => !empty($field['required']) ? 'required' : '',
			'options' => $options,
			'multiple' => false,
			'multiple_applied' => false,
		];
	}

	public static function simpleSelect(string $name, ?string $id = null, ?string $class = null, array $options = []): string {
		$id ??= $name;
		$class_str = ($class) ? "class='$class'" : "";
		$select = "<select name='$name' id='$id' $class_str>\n";
		foreach($options as $option) {
			$select .= "<option value='{$option['value']}'>{$option['label']}</option>\n";
		}
		$select .= "</select>";

		return $select;
	}

	// AJAX helpers
	public function get_select_options($data) {

		$items = [];

		switch ($data['type']) {
			case 'item':
				$typeIds = array_values(array_filter(array_map(
					'intval',
					explode(',', (string)$data['type_ids'])
				)));
				$itemIds = \Core\Content::findByTitle(
					(string)($data['q'] ?? ''),
					$typeIds,
					false,
					20
				);

				foreach($itemIds as $itemId) {
					$item = \Core\Content::getItem((int)$itemId);
					$items[] = [
						'id' => $item['item_id'],
						'title' => $item['title'],
						'subtitle' => $item['summary'],
						'meta' => ['slug' => $item['item_slug']],
						'disabled' => false,
					];
				}

				break;

		}

		header('Content-Type: application/json; charset=utf-8');

		echo \Core\Utils\JsonTool::encode([
			'status' => 'ok',
			'data' => [
				'items' => $items,
				'pagination' => [
					'page' => 0,
					'limit' => 20,
					'has_more' => false,
					'total' => null,
				],
			],
			'error' => null,
		], false);

		exit;
	}
}

