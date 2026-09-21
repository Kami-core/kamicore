<?php

/**
 * KamiCore
 *
 * SPDX-License-Identifier: Apache-2.0
 *
 * @see https://kamicore.org
 */

declare(strict_types=1);

namespace Plugins\Forms;

if (!defined('IN_KAMI')) die();

final class Forms extends \Core\BasePlugin
{
    private bool $mediaContextResolved = false;
    private ?array $mediaContext = null;

    private const DEFAULT_FORM_TEMPLATE = 'form';

    /** @var list<string> */
    private const ENTITY_FIELD_TYPES = [
        'item_id',
        'content_type_id',
        'field_id',
        'field_type_id',
        'page_id',
        'domain_id',
        'lang_code',
        'plugin_id',
        'user_id',
        'usergroup_id',
    ];

    /** @var array<string, string> */
    private const TEMPORAL_INPUT_TYPES = [
        'date' => 'date',
        'date_full' => 'date',
        'datetime' => 'datetime-local',
        'time' => 'time',
        'year_month' => 'month',
        'year' => 'number',
    ];

    /**
     * Presentation fallback hierarchy.
     * Storage field types are intentionally not used as the source of truth here.
     *
     * @var array<string, string|null>
     */
    private const FIELD_TYPE_PARENTS = [
        'text' => 'string',
        'autocomplete' => 'string',
        'media' => 'url',
        'html_editor' => 'richtext',
        'html' => 'textarea',
        'richtext' => 'textarea',
        'markdown' => 'textarea',
        'textarea' => 'string',
        'email' => 'string',
        'url' => 'string',
        'slug' => 'string',
        'domain_id' => 'integer',
        'page_id' => 'integer',
        'lang_code' => 'string',
        'plugin_id' => 'integer',
        'content_type_id' => 'integer',
        'field_type_id' => 'integer',
        'field_id' => 'integer',
        'item_id' => 'integer',
        'user_id' => 'integer',
        'usergroup_id' => 'integer',
        'integer' => 'number',
        'decimal' => 'number',
        'number' => 'string',
        'year' => 'date',
        'year_month' => 'date',
        'date_full' => 'date',
        'datetime' => 'date',
        'time' => 'string',
        'date' => 'string',
        'yesno' => 'checkbox',
        'boolean' => 'checkbox',
        'tomselect' => 'select',
        'select' => 'string',
        'string' => null,
        'checkbox' => null,
    ];

    /**
     * Register dependencies required by entity-backed fields that will be
     * inserted into the page after the initial render.
     */
    public function registerEntityFieldAssets(): void
    {
        $this->registerTomSelectAssets();
    }

    public function renderForm(
        array $fields,
        string $action,
        array $options = []
    ): string {
        $ajax = (bool) ($options['ajax'] ?? false);
        $target = $options['target'] ?? null;

        if ($ajax && (!is_string($target) || $target === '')) {
            throw new \InvalidArgumentException(
                'Ajax form requires a non-empty target.'
            );
        }

        $method = strtolower((string) ($options['method'] ?? 'post'));
        if (!in_array($method, ['get', 'post'], true)) {
            throw new \InvalidArgumentException(
                "Unsupported form method: {$method}."
            );
        }

        $renderedFields = '';
        foreach ($fields as $name => $field) {
            if (!is_array($field)) {
                throw new \InvalidArgumentException('Invalid form field declaration.');
            }

            if (!isset($field['name']) && is_string($name)) {
                $field['name'] = $name;
            }

            $renderedFields .= $this->renderField($field) . "\n";
        }

        $template = (string) ($options['template'] ?? self::DEFAULT_FORM_TEMPLATE);
        if (!$this->templateExists($template)) {
            $template = self::DEFAULT_FORM_TEMPLATE;
        }

        $formAttributes = is_array($options['attributes'] ?? null)
            ? $options['attributes']
            : [];

        $ajaxAttributes = $ajax
            ? [
                'data-ajax-form' => true,
                'data-target' => $target,
            ]
            : [];

        return \Core\Renderer::render($template, $this->name, [
            'action' => \Core\Html::escape($action),
            'method' => \Core\Html::escape($method),
            'fields' => $renderedFields,
            'form_attributes' => self::renderAttributes($formAttributes),
            'ajax_attributes' => self::renderAttributes($ajaxAttributes),
            'submit_label' => \Core\Html::escape($this->phrase('submit', 'Submit')),
        ]);
    }

    public function renderField(array $field): string
    {
        $name = $field['name'] ?? null;
        if (!is_string($name) || $name === '') {
            throw new \InvalidArgumentException('Form field requires a name.');
        }

        $type = strtolower((string) ($field['type'] ?? 'string'));
        $settings = is_array($field['settings'] ?? null)
            ? $field['settings']
            : [];
        $params = is_array($field['params'] ?? null)
            ? $field['params']
            : [];
        $id = (string) ($field['id'] ?? self::fieldId($name));
        $value = $field['value'] ?? $field['default'] ?? '';
        $label = (string) ($field['label'] ?? $field['title'] ?? $name);
        $placeholder = (string) ($field['placeholder'] ?? $params['placeholder'] ?? '');
        $attributes = is_array($field['attributes'] ?? null)
            ? $field['attributes']
            : [];

        foreach ([
            'min' => 'min',
            'max' => 'max',
            'step' => 'step',
            'min_length' => 'minlength',
            'max_length' => 'maxlength',
            'rows' => 'rows',
        ] as $parameter => $attribute) {
            if (
                !array_key_exists($attribute, $attributes)
                && array_key_exists($parameter, $params)
                && is_scalar($params[$parameter])
            ) {
                $attributes[$attribute] = $params[$parameter];
            }
        }

        if (
            in_array($type, ['datetime', 'time'], true)
            && !array_key_exists('step', $attributes)
        ) {
            $attributes['step'] = 1;
        }

        if (!empty($field['required'] ?? $settings['required'] ?? false)) {
            $attributes['required'] = true;
        }
        if (!empty($field['disabled'] ?? $settings['disabled'] ?? false)) {
            $attributes['disabled'] = true;
        }
        if (!empty($field['readonly'] ?? $settings['readonly'] ?? false)) {
            $attributes['readonly'] = true;
        }

        $multiple = !empty(
            $field['multiple']
            ?? $settings['multiple']
            ?? $params['multiple']
            ?? false
        );
        $entityField = self::isEntityFieldType($type);
        $explicitTemplate = isset($field['template']) && is_string($field['template'])
            ? trim($field['template'])
            : '';

        if ($type === 'compound' && $explicitTemplate === '') {
            return $this->renderCompoundField(
                $field,
                $name,
                $id,
                $value,
                $label,
                $settings,
                $params,
                $attributes,
                $multiple
            );
        }

        if ($multiple && self::isTypeOrDescendant($type, 'checkbox')) {
            throw new \InvalidArgumentException('Checkbox fields do not support multiple values.');
        }

        $selectLike = $entityField || self::isTypeOrDescendant($type, 'select');
        if ($multiple && ($selectLike || $type === 'autocomplete')) {
            $attributes['multiple'] = true;
            if (!str_ends_with($name, '[]')) {
                $name .= '[]';
            }
        }

        if (self::isTypeOrDescendant($type, 'checkbox') && self::isChecked($value)) {
            $attributes['checked'] = true;
        }

        $temporalInputType = self::TEMPORAL_INPUT_TYPES[$type] ?? null;
        $inputType = $temporalInputType ?? match ($type) {
            'email' => 'email',
            'number', 'integer', 'decimal', 'year' => 'number',
            default => 'text',
        };

        $template = '';
        $ajaxUrl = (string) ($field['ajax_url'] ?? '');
        $options = '';
        $multipleEmpty = '';
        $tomselectCreate = '0';
        $repeatableRows = '';
        $repeatableTemplate = '';
        $repeatableAddButton = '';
        $repeatableRequired = !empty($attributes['required']) ? '1' : '0';
        $mediaRows = '';
        $mediaRowTemplate = '';
        $mediaFooter = '';
        $mediaRoot = '';
        $mediaAcceptJson = '[]';
        $mediaCanManage = '0';
        $mediaMultiple = $multiple ? '1' : '0';

        if ($multiple && ($selectLike || $type === 'autocomplete')) {
            $multipleEmpty = '<input type="hidden" name="'
                . \Core\Html::escape($name)
                . '" value="">';
        }

        $useAutocomplete = $type === 'autocomplete' && $explicitTemplate === '';
        $mediaContext = $type === 'media' && $explicitTemplate === ''
            ? $this->mediaContext()
            : null;
        $useMedia = $type === 'media' && $explicitTemplate === '' && $mediaContext !== null;
        $useRepeatable = $multiple
            && $explicitTemplate === ''
            && !$selectLike
            && $type !== 'autocomplete'
            && !$useMedia;

        if ($useAutocomplete) {
            $template = 'field-autocomplete';
            $ajaxUrl = $this->autocompleteAjaxUrl($field, $params);
            $options = self::renderOptions(self::stringValueOptions($value), $value);
            $tomselectCreate = '1';
        } elseif ($useMedia) {
            $template = 'field-media';
            $this->registerFieldAssets();
            $this->registerMediaAssets();
            $mediaRoot = \Core\Html::escape(trim((string) ($params['root'] ?? '')));
            $mediaAcceptJson = \Core\Html::escape(\Core\Utils\JsonTool::encode(self::normalizeMediaAccept($params['accept'] ?? []), false));
            $mediaCanManage = !empty($mediaContext['can_manage']) ? '1' : '0';

            $mediaName = $name;
            if ($multiple && !str_ends_with($mediaName, '[]')) {
                $mediaName .= '[]';
            }
            if ($multiple) {
                $multipleEmpty = '<input type="hidden" name="'
                    . \Core\Html::escape($mediaName)
                    . '" value="">';
            }

            $values = self::multipleValues($value);
            if ($values === []) {
                $values = [''];
            }
            foreach ($values as $index => $itemValue) {
                $mediaRows .= $this->mediaRow(
                    $mediaName,
                    $id . '-' . $index,
                    self::scalarValue($itemValue),
                    $placeholder,
                    $attributes,
                    $multiple
                );
            }
            $mediaRowTemplate = $this->mediaRow(
                $mediaName,
                $id . '-__INDEX__',
                '',
                $placeholder,
                $attributes,
                $multiple
            );
            if ($multiple && empty($attributes['disabled']) && empty($attributes['readonly'])) {
                $mediaFooter = '<button class="admin-button admin-button-secondary admin-button-small" '
                    . 'type="button" data-media-add-url>'
                    . \Core\Html::escape($this->phrase('add_url', 'Add URL'))
                    . '</button> '
                    . '<button class="admin-button admin-button-secondary admin-button-small" '
                    . 'type="button" data-media-browse-all>'
                    . \Core\Html::escape($this->phrase('browse_media', 'Browse Media'))
                    . '</button>';
            }
        } elseif ($useRepeatable) {
            $template = 'field-repeatable';
            $this->registerFieldAssets();
            $repeatableName = str_ends_with($name, '[]') ? $name : $name . '[]';
            $multipleEmpty = '<input type="hidden" name="'
                . \Core\Html::escape($repeatableName)
                . '" value="">';
            $values = self::multipleValues($value);
            if ($values === []) {
                $values = [''];
            }
            foreach ($values as $index => $itemValue) {
                $repeatableRows .= $this->repeatableRow(
                    $type,
                    $repeatableName,
                    $id . '-' . $index,
                    $itemValue,
                    $placeholder,
                    $attributes
                );
            }
            $repeatableTemplate = $this->repeatableRow(
                $type,
                $repeatableName,
                $id . '-__INDEX__',
                '',
                $placeholder,
                $attributes
            );
            if (empty($attributes['disabled']) && empty($attributes['readonly'])) {
                $repeatableAddButton = '<button class="admin-button admin-button-secondary admin-button-small" '
                    . 'type="button" data-repeatable-add>'
                    . \Core\Html::escape($this->phrase('add_value', 'Add value'))
                    . '</button>';
            }
        } else {
            if ($entityField && $explicitTemplate === '') {
                $template = 'field-tomselect';
            } elseif ($temporalInputType !== null && $explicitTemplate === '') {
                $template = 'field-temporal';
            } else {
                $resolveType = $type === 'media' && $mediaContext === null ? 'url' : $type;
                $template = $this->resolveFieldTemplate(
                    $resolveType,
                    $explicitTemplate !== '' ? $explicitTemplate : null
                );
            }

            if ($entityField) {
                $entityParams = self::entityParameters($type, $field, $params);
                $ajaxUrl = $this->entityAjaxUrl($type, $entityParams);
                $selectedOptions = $this->selectedEntityOptions(
                    $type,
                    $value,
                    $entityParams
                );
                $options = self::renderOptions($selectedOptions, $value);
            } elseif (self::isTypeOrDescendant($type, 'select')) {
                $options = self::renderOptions(
                    is_array($field['options'] ?? null)
                        ? $field['options']
                        : (is_array($params['options'] ?? null) ? $params['options'] : []),
                    $value
                );
            }
        }

        if (in_array($template, ['field-tomselect', 'field-autocomplete'], true)) {
            $this->registerTomSelectAssets();
        }

        if (
            $template === 'field-richtext'
            || ($useRepeatable && self::isTypeOrDescendant($type, 'richtext'))
        ) {
            $this->addCss('/third-party/frontend/quill/quill.snow.css');
            $this->addJs('/third-party/frontend/quill/quill.js');
            if ($this->mediaContext() !== null) {
                $this->registerMediaAssets();
            }
            $this->addJs('/plugins/Forms/assets/forms-richtext.js');
        }

        return \Core\Renderer::render($template, $this->name, [
            'id' => \Core\Html::escape($id),
            'id_json' => \Core\Utils\JsonTool::encodeForHtml($id),
            'name' => \Core\Html::escape($name),
            'label' => \Core\Html::escape($label),
            'value' => \Core\Html::escape(self::inputValue($type, $value)),
            'placeholder' => \Core\Html::escape($placeholder),
            'field_attributes' => self::renderAttributes($attributes),
            'checkbox_value' => \Core\Html::escape(
                self::scalarValue($field['checkbox_value'] ?? 1)
            ),
            'options' => $options,
            'description' => \Core\Html::escape((string) ($field['description'] ?? '')),
            'input_type' => \Core\Html::escape($inputType),
            'ajax_url' => \Core\Html::escape($ajaxUrl),
            'tomselect_create' => $tomselectCreate,
            'multiple_empty' => $multipleEmpty,
            'repeatable_rows' => $repeatableRows,
            'repeatable_template' => $repeatableTemplate,
            'repeatable_add_button' => $repeatableAddButton,
            'repeatable_required' => $repeatableRequired,
            'media_rows' => $mediaRows,
            'media_row_template' => $mediaRowTemplate,
            'media_footer' => $mediaFooter,
            'media_root' => $mediaRoot,
            'media_accept_json' => $mediaAcceptJson,
            'media_can_manage' => $mediaCanManage,
            'media_multiple' => $mediaMultiple,
        ]);
    }

    /**
     * AJAX source for standard system-entity fields.
     */
    public function autocompleteOptions(array $data = []): string
    {
        if (\Core\User::isGuest()) {
            return $this->entityJson([
                'status' => 'error',
                'items' => [],
                'error' => 'Authentication required.',
            ], 403);
        }

        $query = trim((string) ($data['q'] ?? ''));
        $limit = max(1, min(50, (int) ($data['limit'] ?? 20)));
        $sourceFields = self::normalizeEntityList($data['source_fields'] ?? []);

        try {
            if ($sourceFields === []) {
                throw new \InvalidArgumentException('Autocomplete requires at least one source field.');
            }
            $items = $this->loadAutocompleteOptions($sourceFields, $query, $limit);
        } catch (\InvalidArgumentException $error) {
            return $this->entityJson([
                'status' => 'error',
                'items' => [],
                'error' => $error->getMessage(),
            ], 400);
        } catch (\Throwable $error) {
            $this->log('Autocomplete options failed: ' . $error->getMessage(), 'error');
            return $this->entityJson([
                'status' => 'error',
                'items' => [],
                'error' => 'Unable to load autocomplete suggestions.',
            ], 500);
        }

        return $this->entityJson([
            'status' => 'ok',
            'items' => $items,
            'has_more' => false,
            'error' => null,
        ]);
    }

    public function entityOptions(array $data = []): string
    {
        $type = strtolower(trim((string) ($data['entity_type'] ?? '')));
        if (!self::isEntityFieldType($type)) {
            return $this->entityJson([
                'status' => 'error',
                'items' => [],
                'error' => 'Unsupported entity field type.',
            ], 400);
        }

        if (\Core\User::isGuest()) {
            return $this->entityJson([
                'status' => 'error',
                'items' => [],
                'error' => 'Authentication required.',
            ], 403);
        }

        $query = trim((string) ($data['q'] ?? ''));
        $ids = self::normalizeEntityValues($type, $data['ids'] ?? []);
        $limit = max(1, min(50, (int) ($data['limit'] ?? 20)));
        $parameters = self::entityParameters($type, $data, $data);

        try {
            $items = $this->loadEntityOptions(
                $type,
                $query,
                $ids,
                $parameters,
                $limit
            );
        } catch (\Throwable $error) {
            $this->log('Entity options failed: ' . $error->getMessage(), 'error');
            return $this->entityJson([
                'status' => 'error',
                'items' => [],
                'error' => 'Unable to load options.',
            ], 500);
        }

        return $this->entityJson([
            'status' => 'ok',
            'items' => $items,
            'has_more' => false,
            'error' => null,
        ]);
    }

    private static function isEntityFieldType(string $type): bool
    {
        return in_array($type, self::ENTITY_FIELD_TYPES, true);
    }

    /**
     * Keep only parameters understood by Forms for a specific entity type.
     *
     * @return array<string, list<string>>
     */
    private static function entityParameters(
        string $type,
        array $field,
        array $params
    ): array {
        $parameterNames = array_merge(['exclude_ids'], match ($type) {
            'item_id' => ['content_types'],
            'page_id', 'plugin_id' => ['domain_ids'],
            'field_id' => ['field_types'],
            'user_id' => ['usergroup_ids'],
            default => [],
        });

        $result = [];
        foreach ($parameterNames as $name) {
            $value = $params[$name] ?? $field[$name] ?? null;
            if ($name === 'content_types' && ($value === null || $value === '')) {
                $value = $field['content_type'] ?? null;
            }

            $items = self::normalizeEntityList($value);
            if ($items !== []) {
                $result[$name] = $items;
            }
        }

        return $result;
    }

    private function entityAjaxUrl(string $type, array $parameters): string
    {
        $query = ['entity_type' => $type];
        foreach ($parameters as $name => $values) {
            if ($values !== []) {
                $query[$name] = implode(',', $values);
            }
        }

        return '/ajax/Forms/entityOptions?' . http_build_query(
            $query,
            '',
            '&',
            PHP_QUERY_RFC3986
        );
    }

    private function selectedEntityOptions(
        string $type,
        mixed $value,
        array $parameters
    ): array {
        $ids = self::normalizeEntityValues($type, $value);
        if ($ids === []) {
            return [];
        }

        if (\Core\User::isGuest()) {
            return array_map(
                static fn(int|string $id): array => [
                    'value' => (string) $id,
                    'label' => self::entityFallbackLabel($type, $id),
                ],
                $ids
            );
        }

        try {
            $options = $this->loadEntityOptions(
                $type,
                '',
                $ids,
                $parameters,
                max(20, count($ids))
            );
        } catch (\Throwable $error) {
            $this->log('Selected entity preload failed: ' . $error->getMessage(), 'error');
            $options = [];
        }
        $byValue = [];
        foreach ($options as $option) {
            $byValue[(string) $option['value']] = $option;
        }

        $ordered = [];
        foreach ($ids as $id) {
            $key = (string) $id;
            $ordered[] = $byValue[$key] ?? [
                'value' => $key,
                'label' => self::entityFallbackLabel($type, $id),
            ];
        }

        return $ordered;
    }

    private function loadEntityOptions(
        string $type,
        string $query,
        array $ids,
        array $parameters,
        int $limit
    ): array {
        if ($type === 'item_id') {
            return $this->loadItemOptions($query, $ids, $parameters, $limit);
        }

        return $this->loadSystemEntityOptions(
            $type,
            $query,
            $ids,
            $parameters,
            $limit
        );
    }

    private function loadItemOptions(
        string $query,
        array $ids,
        array $parameters,
        int $limit
    ): array {
        $contentTypes = $parameters['content_types'] ?? [];
        $contentTypeIds = [];
        foreach ($contentTypes as $contentType) {
            try {
                $contentTypeIds[] = (int) \Core\Content::getContentType($contentType)['ct_id'];
            } catch (\Throwable) {
                continue;
            }
        }
        $contentTypeIds = array_values(array_unique(array_filter($contentTypeIds)));
        if ($contentTypes !== [] && $contentTypeIds === []) {
            return [];
        }

        if ($ids !== []) {
            $bind = [\DB::prepareIdArray($ids)];
            $where = ['item_id=ANY($1::int[])'];
            if ($contentTypeIds !== []) {
                $bind[] = \DB::prepareIdArray($contentTypeIds);
                $where[] = 'ct_id=ANY($2::int[])';
            }
            $itemIds = array_map(
                'intval',
                \DB::getArr(
                    'SELECT item_id FROM content_items WHERE '
                        . implode(' AND ', $where)
                        . ' ORDER BY array_position($1::int[], item_id)',
                    $bind
                )
            );
        } else {
            $itemIds = \Core\Content::findByTitle(
                $query,
                $contentTypeIds !== [] ? $contentTypeIds : null,
                false,
                $limit
            );
        }

        $items = [];
        foreach (array_slice($itemIds, 0, $limit) as $itemId) {
            $item = \Core\Content::getItem((int) $itemId);
            if ($item === []) {
                continue;
            }

            $contentType = \Core\Content::getContentType((int) $item['ct_id']);
            $typeTitle = (string) ($contentType['title'] ?? $contentType['system_name']);
            $subtitle = $typeTitle;
            if (!empty($item['item_slug'])) {
                $subtitle .= ' · ' . $item['item_slug'];
            } elseif (!empty($item['summary'])) {
                $subtitle .= ' · ' . self::compactText((string) $item['summary']);
            }

            $items[] = [
                'value' => (string) $item['item_id'],
                'label' => (string) ($item['title'] ?? "#{$item['item_id']}"),
                'subtitle' => $subtitle,
            ];
        }

        return $items;
    }

    private function loadSystemEntityOptions(
        string $type,
        string $query,
        array $ids,
        array $parameters,
        int $limit
    ): array {
        $config = match ($type) {
            'content_type_id' => [
                'from' => 'content_types entity',
                'id' => 'entity.ct_id',
                'uuid' => 'entity.uuid',
                'name' => 'entity.system_name',
                'subtitle' => 'entity.system_name',
            ],
            'field_id' => [
                'from' => 'fields entity JOIN field_types ft ON ft.type_id=entity.type_id',
                'id' => 'entity.field_id',
                'uuid' => 'entity.uuid',
                'name' => 'entity.system_name',
                'subtitle' => "entity.system_name || ' · ' || ft.system_name",
            ],
            'field_type_id' => [
                'from' => 'field_types entity',
                'id' => 'entity.type_id',
                'uuid' => 'entity.uuid',
                'name' => 'entity.system_name',
                'subtitle' => 'entity.system_name',
            ],
            'page_id' => [
                'from' => 'pages entity JOIN domains domain ON domain.domain_id=entity.domain_id',
                'id' => 'entity.page_id',
                'uuid' => 'entity.uuid',
                'name' => 'entity.system_name',
                'subtitle' => "domain.domain_name || ' · /' || trim(leading '/' from COALESCE(entity.page_slug, ''))",
                'domain' => 'entity.domain_id',
            ],
            'domain_id' => [
                'from' => 'domains entity',
                'id' => 'entity.domain_id',
                'name' => 'entity.domain_name',
                'label' => 'COALESCE(NULLIF(entity.title, \'\'), entity.domain_name)',
                'subtitle' => 'entity.domain_name',
                'domain' => 'entity.domain_id',
            ],
            'lang_code' => [
                'from' => 'languages entity',
                'id' => 'entity.lang_code',
                'name' => 'entity.lang_name',
                'label' => "entity.lang_name || ' (' || entity.lang_code || ')'",
                'subtitle' => 'entity.lang_code',
                'value_type' => 'text',
            ],
            'plugin_id' => [
                'from' => 'plugins entity',
                'id' => 'entity.plugin_id',
                'uuid' => 'entity.uuid',
                'name' => 'entity.system_name',
                'subtitle' => "entity.system_name || COALESCE(' · ' || NULLIF(entity.plugin_version, ''), '')",
            ],
            'user_id' => [
                'from' => 'users entity',
                'id' => 'entity.user_id',
                'name' => 'entity.username::text',
                'label' => "COALESCE(NULLIF(entity.username::text, ''), NULLIF(entity.email, ''), '#' || entity.user_id::text)",
                'subtitle' => "COALESCE(entity.email, '')",
            ],
            'usergroup_id' => [
                'from' => 'usergroups entity',
                'id' => 'entity.usergroup_id',
                'uuid' => 'entity.uuid',
                'name' => 'entity.system_name',
                'subtitle' => "COALESCE(entity.system_name, '')",
            ],
            default => throw new \InvalidArgumentException('Unsupported entity type.'),
        };

        $lang = defined('LANG') ? LANG : (string) (DOMAIN_CONFIG['default_language'] ?? 'en');
        $fallbackLang = (string) (DOMAIN_CONFIG['default_language'] ?? $lang);
        $bind = [];
        $translationJoin = '';
        $translatedTitle = 'NULL';
        if (isset($config['uuid'])) {
            $bind = [$lang, $fallbackLang];
            $translationJoin = "
                LEFT JOIN LATERAL (
                    SELECT translated_data->>'title' AS title
                    FROM translations
                    WHERE entity_uuid={$config['uuid']}
                      AND lang_code IN ($1, $2)
                    ORDER BY CASE WHEN lang_code=$1 THEN 0 ELSE 1 END
                    LIMIT 1
                ) translation ON true";
            $translatedTitle = 'translation.title';
        }

        $label = $config['label']
            ?? "COALESCE(NULLIF({$translatedTitle}, ''), {$config['name']}, '#' || {$config['id']}::text)";
        $where = [];

        if ($type === 'user_id') {
            $where[] = 'entity.user_id > 0';
        }
        if ($type === 'lang_code') {
            $where[] = 'entity.is_active=true';
        }
        if ($ids !== []) {
            $valueType = (string) ($config['value_type'] ?? 'int');
            $bind[] = $valueType === 'text'
                ? \DB::prepareTextArray($ids)
                : \DB::prepareIdArray($ids);
            $where[] = $config['id'] . '=ANY($' . count($bind)
                . ($valueType === 'text' ? '::text[])' : '::int[])');
        } elseif ($query !== '') {
            $bind[] = $query;
            $exactParam = '$' . count($bind);
            $bind[] = '%' . $query . '%';
            $likeParam = '$' . count($bind);
            $where[] = '(' . $config['id'] . "::text={$exactParam}"
                . ' OR ' . $config['name'] . " ILIKE {$likeParam}"
                . ' OR ' . $label . " ILIKE {$likeParam})";
        }

        if ($ids === [] && !empty($parameters['exclude_ids'])) {
            $excludeIds = self::normalizeEntityIds($parameters['exclude_ids']);
            if ($excludeIds !== []) {
                $bind[] = \DB::prepareIdArray($excludeIds);
                $where[] = $config['id'] . '<>ALL($' . count($bind) . '::int[])';
            }
        }

        if ($type === 'page_id' && !empty($parameters['domain_ids'])) {
            $domainIds = self::normalizeEntityIds($parameters['domain_ids']);
            if ($domainIds !== []) {
                $bind[] = \DB::prepareIdArray($domainIds);
                $where[] = 'entity.domain_id=ANY($' . count($bind) . '::int[])';
            }
        }

        if ($type === 'plugin_id' && !empty($parameters['domain_ids'])) {
            $domainIds = self::normalizeEntityIds($parameters['domain_ids']);
            if ($domainIds !== []) {
                $bind[] = \DB::prepareIdArray($domainIds);
                $where[] = 'entity.is_active=true AND EXISTS (SELECT 1 FROM plugin_domains pd_filter'
                    . ' WHERE pd_filter.plugin_id=entity.plugin_id'
                    . ' AND pd_filter.domain_id=ANY($' . count($bind) . '::int[]))';
            }
        }

        if ($type === 'field_id' && !empty($parameters['field_types'])) {
            $fieldTypeIds = [];
            foreach ($parameters['field_types'] as $fieldType) {
                $row = ctype_digit((string) $fieldType)
                    ? \DB::getRow('SELECT type_id FROM field_types WHERE type_id=$1', [(int) $fieldType])
                    : \DB::getRow('SELECT type_id FROM field_types WHERE system_name=$1', [(string) $fieldType]);
                if ($row) {
                    $fieldTypeIds[] = (int) $row['type_id'];
                }
            }
            if ($fieldTypeIds !== []) {
                $bind[] = \DB::prepareIdArray(array_values(array_unique($fieldTypeIds)));
                $where[] = 'entity.type_id=ANY($' . count($bind) . '::int[])';
            }
        }

        if ($type === 'user_id') {
            if (!empty($parameters['usergroup_ids'])) {
                $usergroupIds = self::normalizeEntityIds($parameters['usergroup_ids']);
                if ($usergroupIds !== []) {
                    $bind[] = \DB::prepareIdArray($usergroupIds);
                    $where[] = 'entity.usergroup_id=ANY($' . count($bind) . '::int[])';
                }
            }
        }

        $whereSql = $where !== [] ? ' WHERE ' . implode(' AND ', $where) : '';
        $bind[] = $limit;
        $limitParam = '$' . count($bind);
        $result = \DB::query(
            'SELECT ' . $config['id'] . ' AS value, '
                . $label . ' AS label, '
                . $config['subtitle'] . ' AS subtitle '
                . 'FROM ' . $config['from']
                . $translationJoin
                . $whereSql
                . ' ORDER BY label, value LIMIT ' . $limitParam . '::int',
            $bind
        );

        $items = [];
        while ($row = \DB::fetchRow($result)) {
            $items[] = [
                'value' => (string) $row['value'],
                'label' => (string) $row['label'],
                'subtitle' => (string) ($row['subtitle'] ?? ''),
            ];
        }

        if ($ids !== []) {
            $positions = array_flip(array_map('strval', $ids));
            usort(
                $items,
                static fn(array $a, array $b): int => ($positions[$a['value']] ?? PHP_INT_MAX)
                    <=> ($positions[$b['value']] ?? PHP_INT_MAX)
            );
        }

        return $items;
    }

    private function autocompleteAjaxUrl(array $field, array $params): string
    {
        $sourceFields = self::normalizeEntityList($params['source_fields'] ?? []);
        if ($sourceFields === []) {
            $currentField = trim((string) ($field['name'] ?? ''));
            if ($currentField !== '') {
                $sourceFields[] = $currentField;
            }
        }

        if ($sourceFields === []) {
            return '';
        }

        return '/ajax/Forms/autocompleteOptions?' . http_build_query(
            ['source_fields' => implode(',', $sourceFields)],
            '',
            '&',
            PHP_QUERY_RFC3986
        );
    }

    private function loadAutocompleteOptions(
        array $sourceFields,
        string $query,
        int $limit
    ): array {
        $lang = defined('LANG')
            ? (string) LANG
            : (string) (DOMAIN_CONFIG['default_language'] ?? 'en');
        $bind = [];
        $sources = [];

        foreach ($sourceFields as $sourceField) {
            try {
                $definition = \Core\Content::getField($sourceField);
            } catch (\Throwable) {
                throw new \InvalidArgumentException(
                    "Unknown autocomplete source field: {$sourceField}."
                );
            }

            $settings = array_replace(
                is_array($definition['type_settings'] ?? null) ? $definition['type_settings'] : [],
                is_array($definition['field_settings'] ?? null) ? $definition['field_settings'] : []
            );
            if (empty($settings['indexed'])) {
                throw new \InvalidArgumentException(
                    "Autocomplete source field '{$definition['system_name']}' is not indexed."
                );
            }
            if ((string) ($definition['root_type_name'] ?? '') !== 'text') {
                throw new \InvalidArgumentException(
                    "Autocomplete source field '{$definition['system_name']}' is not a text field."
                );
            }

            $bind[] = (int) $definition['field_id'];
            $fieldParam = '$' . count($bind);
            if (!empty($settings['translatable'])) {
                $bind[] = $lang;
                $langParam = '$' . count($bind);
                $sources[] = "(idx.field_id={$fieldParam} AND idx.lang_code={$langParam})";
            } else {
                $sources[] = "(idx.field_id={$fieldParam} AND idx.lang_code IS NULL)";
            }
        }

        $where = '(' . implode(' OR ', $sources) . ')';
        $order = 'count(*) DESC, idx.value';
        if ($query !== '') {
            $bind[] = '%' . $query . '%';
            $likeParam = '$' . count($bind);
            $where .= " AND idx.value ILIKE {$likeParam}";
            $bind[] = $query;
            $queryParam = '$' . count($bind);
            $order = "CASE"
                . " WHEN lower(idx.value)=lower({$queryParam}) THEN 0"
                . " WHEN lower(idx.value) LIKE lower({$queryParam}) || '%' THEN 1"
                . " ELSE 2 END, count(*) DESC, idx.value";
        }

        $bind[] = $limit;
        $limitParam = '$' . count($bind);
        $result = \DB::query(
            'SELECT idx.value, count(*) AS uses '
                . 'FROM item_texts idx WHERE ' . $where
                . ' GROUP BY idx.value ORDER BY ' . $order
                . ' LIMIT ' . $limitParam . '::int',
            $bind
        );

        $items = [];
        while ($row = \DB::fetchRow($result)) {
            $value = trim((string) ($row['value'] ?? ''));
            if ($value === '') {
                continue;
            }
            $items[] = [
                'value' => $value,
                'label' => $value,
                'subtitle' => '',
            ];
        }

        return $items;
    }

    private function registerFieldAssets(): void
    {
        $this->addCss('/plugins/Forms/assets/forms-fields.css');
        $this->addJs('/plugins/Forms/assets/forms-fields.js');
    }

    private function registerMediaAssets(): void
    {
        $media = $this->plugins->get('Media');
        if ($media && method_exists($media, 'registerBrowserAssets')) {
            $media->registerBrowserAssets();
        }
    }

    private function registerTomSelectAssets(): void
    {
        $this->addCss('/assets/vendor/tom-select/tom-select.css');
        $this->addJs('/assets/vendor/tom-select/tom-select.complete.js');
        $this->addJs('/assets/js/tom-select-init.js');
    }

    private function mediaContext(): ?array
    {
        if ($this->mediaContextResolved) {
            return $this->mediaContext;
        }
        $this->mediaContextResolved = true;

        $media = $this->plugins->get('Media');
        if (!$media) {
            return null;
        }

        $pluginId = (int) $media->id;
        if (!\Core\User::canPlugin($pluginId, 'view')) {
            return null;
        }

        return $this->mediaContext = [
            'plugin_id' => $pluginId,
            'can_manage' => \Core\User::canPlugin($pluginId, 'manage'),
        ];
    }

    private function renderCompoundField(
        array $field,
        string $name,
        string $id,
        mixed $value,
        string $label,
        array $settings,
        array $params,
        array $attributes,
        bool $multiple
    ): string {
        $components = is_array($params['components'] ?? null)
            ? $params['components']
            : [];
        if ($components === []) {
            throw new \InvalidArgumentException('Compound field requires components.');
        }

        $description = (string)($field['description'] ?? '');
        $required = !empty($field['required'] ?? $settings['required'] ?? false);
        $rowsHtml = '';
        $rowTemplate = '';
        $addButton = '';
        $containerAttributes = '';

        if ($multiple) {
            $this->registerFieldAssets();
            $containerAttributes = ' data-repeatable data-repeatable-required="'
                . ($required ? '1' : '0') . '"';
            $rows = is_array($value) ? array_values($value) : [];
            if ($rows === [] && $required) {
                $rows = [['_key' => 'tmp-1']];
            }

            foreach ($rows as $index => $row) {
                if (!is_array($row)) continue;
                $key = trim((string)($row['_key'] ?? ''));
                if ($key === '') $key = 'tmp-' . ($index + 1);
                $rowsHtml .= $this->compoundRow(
                    $name,
                    $id,
                    $key,
                    $components,
                    $row,
                    true,
                    $attributes
                );
            }

            $rowTemplate = $this->compoundRow(
                $name,
                $id,
                'tmp-__INDEX__',
                $components,
                [],
                true,
                $attributes
            );
            if (empty($attributes['disabled']) && empty($attributes['readonly'])) {
                $addButton = '<button class="admin-button admin-button-secondary admin-button-small" '
                    . 'type="button" data-repeatable-add>'
                    . \Core\Html::escape($this->phrase('add_value', 'Add value'))
                    . '</button>';
            }
        } else {
            $rowsHtml = $this->compoundRow(
                $name,
                $id,
                '',
                $components,
                is_array($value) ? $value : [],
                false,
                $attributes
            );
        }

        return \Core\Renderer::render('field-compound', $this->name, [
            'compound_attributes' => $containerAttributes,
            'label' => \Core\Html::escape($label),
            'description' => \Core\Html::escape($description),
            'compound_rows' => $rowsHtml,
            'compound_template' => $rowTemplate,
            'compound_add_button' => $addButton,
        ]);
    }

    private function compoundRow(
        string $name,
        string $id,
        string $key,
        array $components,
        array $value,
        bool $multiple,
        array $attributes
    ): string {
        $fields = '';
        foreach ($components as $componentName => $component) {
            if (!is_string($componentName) || !is_array($component)) continue;

            $childName = $multiple
                ? $name . '[' . $key . '][' . $componentName . ']'
                : $name . '[' . $componentName . ']';
            $childId = $id . ($key !== '' ? '-' . $key : '') . '-' . $componentName;
            $childSettings = [
                'required' => !empty($component['required']),
            ];
            if (!empty($attributes['disabled'])) $childSettings['disabled'] = true;
            if (!empty($attributes['readonly'])) $childSettings['readonly'] = true;

            $fields .= $this->renderField([
                'name' => $childName,
                'id' => $childId,
                'type' => (string)($component['type'] ?? 'string'),
                'title' => self::humanizeName($componentName),
                'value' => $value[$componentName] ?? null,
                'settings' => $childSettings,
                'params' => is_array($component['params'] ?? null)
                    ? $component['params']
                    : [],
            ]);
        }

        $controls = $multiple ? $this->repeatableControls($attributes) : '';
        return '<div class="form-compound-row" data-repeatable-row>'
            . '<div class="form-compound-fields">' . $fields . '</div>'
            . $controls . '</div>';
    }

    private static function humanizeName(string $name): string
    {
        return ucwords(str_replace(['_', '-'], ' ', $name));
    }

    private function repeatableRow(
        string $type,
        string $name,
        string $id,
        mixed $value,
        string $placeholder,
        array $attributes
    ): string {
        $controls = $this->repeatableControls($attributes);
        $safeName = \Core\Html::escape($name);
        $safeId = \Core\Html::escape($id);
        $safePlaceholder = \Core\Html::escape($placeholder);
        $normalizedValue = self::inputValue($type, $value);

        if (self::isTypeOrDescendant($type, 'richtext')) {
            $htmlValue = \Core\Html::escape(self::scalarValue($value));
            return '<div class="form-repeatable-row form-repeatable-row-richtext" data-repeatable-row>'
                . '<div class="form-field-richtext" data-richtext '
                . 'data-output-id="' . $safeId . '-output" '
                . 'data-html-id="' . $safeId . '-html" '
                . 'data-editor-id="' . $safeId . '-editor">'
                . '<div class="richtext-modes">'
                . '<button type="button" data-richtext-mode="visual" aria-pressed="true">Visual</button>'
                . '<button type="button" data-richtext-mode="html" aria-pressed="false">HTML</button>'
                . '</div>'
                . '<div data-richtext-panel="visual"><div id="' . $safeId . '-editor" class="richtext-editor"></div></div>'
                . '<div data-richtext-panel="html" hidden><textarea id="' . $safeId . '-html" rows="12" spellcheck="false">'
                . $htmlValue . '</textarea></div>'
                . '<input type="hidden" id="' . $safeId . '-output" name="' . $safeName . '" value="'
                . $htmlValue . '"' . self::renderAttributes($attributes) . '>'
                . '</div>' . $controls . '</div>';
        }

        $inputType = self::TEMPORAL_INPUT_TYPES[$type] ?? match ($type) {
            'email' => 'email',
            'number', 'integer', 'decimal', 'year' => 'number',
            default => 'text',
        };

        if (self::isTypeOrDescendant($type, 'textarea')) {
            $control = '<textarea id="' . $safeId . '" name="' . $safeName . '" placeholder="'
                . $safePlaceholder . '"' . self::renderAttributes($attributes) . '>'
                . \Core\Html::escape($normalizedValue) . '</textarea>';
        } else {
            $control = '<input type="' . \Core\Html::escape($inputType) . '" id="' . $safeId
                . '" name="' . $safeName . '" value="' . \Core\Html::escape($normalizedValue)
                . '" placeholder="' . $safePlaceholder . '"'
                . self::renderAttributes($attributes) . '>';
        }

        return '<div class="form-repeatable-row" data-repeatable-row>'
            . '<div class="form-repeatable-control">' . $control . '</div>'
            . $controls . '</div>';
    }

    private function mediaRow(
        string $name,
        string $id,
        string $value,
        string $placeholder,
        array $attributes,
        bool $multiple
    ): string {
        $safeName = \Core\Html::escape($name);
        $safeId = \Core\Html::escape($id);
        $control = '<input type="text" id="' . $safeId . '" name="' . $safeName
            . '" value="' . \Core\Html::escape($value) . '" placeholder="'
            . \Core\Html::escape($placeholder) . '"' . self::renderAttributes($attributes)
            . ' data-media-input>';

        $buttons = '';
        if (empty($attributes['disabled']) && empty($attributes['readonly'])) {
            $buttons .= '<button class="admin-button admin-button-secondary admin-button-small" '
                . 'type="button" data-media-browse-row>'
                . \Core\Html::escape($this->phrase('browse_media', 'Browse Media'))
                . '</button>';
            if ($multiple) {
                $buttons .= '<button class="admin-button admin-button-secondary admin-button-small" type="button" '
                    . 'data-repeatable-up title="' . \Core\Html::escape($this->phrase('move_up', 'Move up')) . '">↑</button>'
                    . '<button class="admin-button admin-button-secondary admin-button-small" type="button" '
                    . 'data-repeatable-down title="' . \Core\Html::escape($this->phrase('move_down', 'Move down')) . '">↓</button>'
                    . '<button class="admin-button admin-button-secondary admin-button-small" type="button" '
                    . 'data-repeatable-remove>' . \Core\Html::escape($this->phrase('remove', 'Remove')) . '</button>';
            }
        }

        return '<div class="form-repeatable-row media-field-row" data-media-row data-repeatable-row>'
            . '<div class="form-repeatable-control">' . $control . '</div>'
            . '<div class="form-repeatable-actions">' . $buttons . '</div>'
            . '</div>';
    }

    private function repeatableControls(array $attributes): string
    {
        if (!empty($attributes['disabled']) || !empty($attributes['readonly'])) {
            return '';
        }

        return '<div class="form-repeatable-actions">'
            . '<button class="admin-button admin-button-secondary admin-button-small" type="button" '
            . 'data-repeatable-up title="' . \Core\Html::escape($this->phrase('move_up', 'Move up')) . '">↑</button>'
            . '<button class="admin-button admin-button-secondary admin-button-small" type="button" '
            . 'data-repeatable-down title="' . \Core\Html::escape($this->phrase('move_down', 'Move down')) . '">↓</button>'
            . '<button class="admin-button admin-button-secondary admin-button-small" type="button" '
            . 'data-repeatable-remove>' . \Core\Html::escape($this->phrase('remove', 'Remove')) . '</button>'
            . '</div>';
    }

    private static function multipleValues(mixed $value): array
    {
        if ($value === null || $value === '') {
            return [];
        }
        return array_values(is_array($value) ? $value : [$value]);
    }

    private static function stringValueOptions(mixed $value): array
    {
        $values = self::multipleValues($value);
        $options = [];
        foreach ($values as $item) {
            $item = self::scalarValue($item);
            if ($item === '') {
                continue;
            }
            $options[] = ['value' => $item, 'label' => $item];
        }
        return $options;
    }

    private static function normalizeMediaAccept(mixed $value): array
    {
        if (is_string($value)) {
            $value = preg_split('/[\s,]+/', $value) ?: [];
        } elseif (!is_array($value)) {
            $value = $value === null ? [] : [$value];
        }

        $items = [];
        foreach ($value as $item) {
            if (!is_scalar($item)) {
                continue;
            }
            $item = trim((string) $item);
            if ($item !== '') {
                $items[] = $item;
            }
        }
        return array_values(array_unique($items));
    }

    private function entityJson(array $data, int $status = 200): string
    {
        \Core\Response::addHeader(
            'Content-Type: application/json; charset=utf-8',
            true,
            $status
        );

        return \Core\Utils\JsonTool::encode($data, false);
    }

    /** @return list<string> */
    private static function normalizeEntityList(mixed $value): array
    {
        if (is_string($value)) {
            $value = explode(',', $value);
        } elseif (!is_array($value)) {
            $value = $value === null || $value === '' ? [] : [$value];
        }

        $items = [];
        foreach ($value as $item) {
            if (!is_scalar($item)) {
                continue;
            }
            $item = trim((string) $item);
            if ($item !== '') {
                $items[] = $item;
            }
        }

        return array_values(array_unique($items));
    }

    private static function entityFallbackLabel(string $type, int|string $value): string
    {
        return $type === 'lang_code' ? (string) $value : '#' . $value;
    }

    /** @return list<int|string> */
    private static function normalizeEntityValues(string $type, mixed $value): array
    {
        if ($type === 'lang_code') {
            return array_values(array_unique(array_filter(
                array_map(
                    static fn(string $code): string => strtolower(trim($code)),
                    self::normalizeEntityList($value)
                ),
                static fn(string $code): bool => $code !== ''
                    && preg_match('/^[a-z0-9_-]+$/', $code) === 1
            )));
        }

        return self::normalizeEntityIds($value);
    }

    /** @return list<int> */
    private static function normalizeEntityIds(mixed $value): array
    {
        return array_values(array_unique(array_filter(
            array_map('intval', self::normalizeEntityList($value)),
            static fn(int $id): bool => $id > 0
        )));
    }


    private static function compactText(string $value, int $limit = 80): string
    {
        $value = trim(preg_replace('/\s+/u', ' ', strip_tags($value)) ?? '');
        if (mb_strlen($value) <= $limit) {
            return $value;
        }

        return rtrim(mb_substr($value, 0, $limit - 1)) . '…';
    }

    private function resolveFieldTemplate(
        string $type,
        ?string $template = null
    ): string {
        if ($template !== null && $template !== '' && $this->templateExists($template)) {
            return $template;
        }

        $visited = [];
        $current = $type;

        while ($current !== '') {
            if (isset($visited[$current])) {
                throw new \RuntimeException(
                    "Recursive field type fallback detected for {$type}."
                );
            }
            $visited[$current] = true;

            $candidate = "field-{$current}";
            if ($this->templateExists($candidate)) {
                return $candidate;
            }

            if (!array_key_exists($current, self::FIELD_TYPE_PARENTS)) {
                break;
            }

            $parent = self::FIELD_TYPE_PARENTS[$current];
            $current = is_string($parent) ? $parent : '';
        }

        throw new \RuntimeException(
            "No form template found for field type '{$type}'."
        );
    }

    private function templateExists(string $template): bool
    {
        return \Core\Renderer::findTemplateFile($template, $this->name) !== null;
    }

    private function phrase(string $key, string $fallback): string
    {
        $phrases = is_array($this->translation['phrases'] ?? null)
            ? $this->translation['phrases']
            : [];

        $value = $phrases[$key] ?? null;
        return is_string($value) && $value !== '' ? $value : $fallback;
    }

    private static function isTypeOrDescendant(string $type, string $ancestor): bool
    {
        $visited = [];
        $current = $type;

        while ($current !== '') {
            if ($current === $ancestor) {
                return true;
            }
            if (isset($visited[$current])) {
                return false;
            }
            $visited[$current] = true;

            if (!array_key_exists($current, self::FIELD_TYPE_PARENTS)) {
                return false;
            }

            $parent = self::FIELD_TYPE_PARENTS[$current];
            $current = is_string($parent) ? $parent : '';
        }

        return false;
    }

    private static function renderOptions(array $options, mixed $selectedValue): string
    {
        $html = '';
        $selected = array_map(
            self::scalarValue(...),
            is_array($selectedValue) ? $selectedValue : [$selectedValue]
        );
        $isList = array_is_list($options);

        foreach ($options as $key => $option) {
            if (is_array($option)) {
                if (!array_key_exists('value', $option)) {
                    continue;
                }

                $value = self::scalarValue($option['value']);
                $label = self::scalarValue(
                    $option['label'] ?? $option['title'] ?? $value
                );
                $disabled = !empty($option['disabled']);
            } elseif (!$isList) {
                $value = self::scalarValue($key);
                $label = self::scalarValue($option);
                $disabled = false;
            } else {
                $value = self::scalarValue($option);
                $label = $value;
                $disabled = false;
            }

            $attributes = ['value' => $value];
            if (in_array($value, $selected, true)) {
                $attributes['selected'] = true;
            }
            if ($disabled) {
                $attributes['disabled'] = true;
            }

            $html .= '        <option'
                . self::renderAttributes($attributes)
                . '>'
                . \Core\Html::escape($label)
                . "</option>\n";
        }

        return rtrim($html, "\n");
    }

    private static function renderAttributes(array $attributes): string
    {
        $html = '';

        foreach ($attributes as $name => $value) {
            if (!is_string($name) || !preg_match('/^[A-Za-z_:][A-Za-z0-9:._-]*$/', $name)) {
                continue;
            }

            if ($value === false || $value === null) {
                continue;
            }

            if ($value === true) {
                $html .= ' ' . $name;
                continue;
            }

            if (!is_scalar($value)) {
                continue;
            }

            $html .= ' ' . $name . '="' . \Core\Html::escape((string) $value) . '"';
        }

        return $html;
    }

    private static function fieldId(string $name): string
    {
        $id = preg_replace('/[^A-Za-z0-9_-]+/', '-', $name) ?? '';
        $id = trim($id, '-');

        return $id !== '' ? 'field-' . $id : 'field-' . \Core\Crypto::randomHex(4);
    }

    private static function inputValue(string $type, mixed $value): string
    {
        $value = self::scalarValue($value);
        if ($value === '') {
            return '';
        }

        return match ($type) {
            'date', 'date_full' => preg_match(
                '/^(\d{4}-\d{2}-\d{2})/',
                $value,
                $matches
            ) ? $matches[1] : $value,
            'datetime' => preg_match(
                '/^(\d{4}-\d{2}-\d{2})[ T](\d{2}:\d{2})(?::(\d{2}))?/',
                $value,
                $matches
            ) ? $matches[1] . 'T' . $matches[2]
                . (isset($matches[3]) && $matches[3] !== '' ? ':' . $matches[3] : '')
                : $value,
            'time' => preg_match(
                '/^(\d{2}:\d{2})(?::(\d{2}))?/',
                $value,
                $matches
            ) ? $matches[1]
                . (isset($matches[2]) && $matches[2] !== '' ? ':' . $matches[2] : '')
                : $value,
            'year_month' => preg_match('/^(\d{4}-\d{2})/', $value, $matches)
                ? $matches[1]
                : $value,
            'year' => preg_match('/^(\d{4})/', $value, $matches)
                ? $matches[1]
                : $value,
            default => $value,
        };
    }

    private static function scalarValue(mixed $value): string
    {
        if ($value === null || $value === false) {
            return '';
        }
        if ($value === true) {
            return '1';
        }
        if (is_scalar($value)) {
            return (string) $value;
        }

        return '';
    }

    private static function isChecked(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }
        if (is_int($value) || is_float($value)) {
            return $value != 0;
        }
        if (is_string($value)) {
            return !in_array(strtolower($value), ['', '0', 'false', 'off', 'no'], true);
        }

        return false;
    }

}
