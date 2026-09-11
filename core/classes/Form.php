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
        return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars($token) . '">';
    }

    protected static function generateCsrfToken(): string
    {
        if (!isset($_SESSION)) session_start();
        $token = bin2hex(random_bytes(16));
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
            'action' => htmlspecialchars($action),
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
		$field_settings_variant = [];
		$field_settings_type = [];
			 // default tpl

		$field_settings_field = $field['settings'] ?? [];

		if(isset($field['variant']) && !empty($field['variant'])) {
			$field_settings_variant = \Cache::get("globals:field_settings:{$field['variant']}");
			if(!$field_settings_variant) {
				$row = \DB::getRow(
					'select * from field_variants where variant_name=$1',
					[(string)$field['variant']]
				);
				$field_settings_variant = json_decode($row['variant_settings'] ?? "", true) ?? [];
			}
		}

		$type = $field['type'] ?? 'text';
		$field_settings_type = \Cache::get("globals:field_settings:{$type}");
		if(!$field_settings_type) {
			$row = \DB::getRow(
				'select * from field_types where system_name=$1',
				[$type]
			);
			$field_settings_type = json_decode($row['type_settings'] ?? '', true) ?? [];
			\Cache::set("globals:field_settings:{$type}", $field_settings_type);
		}

		$field['settings'] = array_replace($field_settings_type, $field_settings_variant, $field_settings_field);
		$field['settings']['multiple'] ??= false;

        $tpl = !empty($field['tpl'])
            ? (string)$field['tpl']
            : ($field['settings']['templates']['edit']
                ?? (!empty($field['variant'])
                    ? "form-{$field['variant']}"
                    : "form-{$type}"));

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
				'name' => htmlspecialchars($field['name'] ?? ''),
				'label' => htmlspecialchars($field['title'] ?? ''),
				'value' => ($field['settings']['multiple']) ? "" : htmlspecialchars($field['value'] ?? $field['default'] ?? ""),
				'placeholder' => htmlspecialchars($field['placeholder'] ?? $field['title']),
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
					$cur_vars['name'] =  htmlspecialchars($field['name'] ?? '')."[{$row_id}]";
					$cur_vars['id'] =  htmlspecialchars($field['name'] ?? '')."_{$row_id}";
					$cur_vars['value'] =  htmlspecialchars($value ?? $field['default'] ?? "");

					$field_container .= Renderer::render($tpl, $pluginName, $cur_vars);
				}
			} else {
				$cur_vars = $vars;
				$cur_vars['name'] =  htmlspecialchars($field['name'] ?? '')."[]";
				$cur_vars['value'] =  htmlspecialchars($field['value'] ?? $field['default'] ?? "");
				$field_container .= Renderer::render($tpl, $pluginName, $cur_vars);
			}

			$cur_vars = $vars;
			$cur_vars['name'] =  htmlspecialchars($field['name'] ?? '')."[]";
			$cur_vars['value'] =  htmlspecialchars($field['default'] ?? "");

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
			'name' => htmlspecialchars($name, ENT_QUOTES, 'UTF-8'),
			'id' => htmlspecialchars($fieldId, ENT_QUOTES, 'UTF-8'),
			'label' => htmlspecialchars((string)($field['title'] ?? ''), ENT_QUOTES, 'UTF-8'),
			'value' => '',
			'placeholder' => htmlspecialchars(
				(string)($field['placeholder'] ?? $field['title'] ?? ''),
				ENT_QUOTES,
				'UTF-8'
			),
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
					'title' => htmlspecialchars(
						(string)($option['title'] ?? $optionValue),
						ENT_QUOTES,
						'UTF-8'
					),
					'value' => htmlspecialchars($optionValue, ENT_QUOTES, 'UTF-8'),
					'selected' => (string)$value === $optionValue ? 'selected' : '',
				],
			];
		}

		$fieldId = 'field_' . trim(
			preg_replace('/[^A-Za-z0-9_-]+/', '_', (string)($field['name'] ?? '')) ?? '',
			'_'
		);

		return [
			'name' => htmlspecialchars((string)($field['name'] ?? ''), ENT_QUOTES, 'UTF-8'),
			'id' => htmlspecialchars($fieldId, ENT_QUOTES, 'UTF-8'),
			'label' => htmlspecialchars((string)($field['title'] ?? ''), ENT_QUOTES, 'UTF-8'),
			'value' => htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8'),
			'placeholder' => htmlspecialchars(
				(string)($field['placeholder'] ?? $field['title'] ?? ''),
				ENT_QUOTES,
				'UTF-8'
			),
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

		echo json_encode([
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
		], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

		exit;
	}
}

