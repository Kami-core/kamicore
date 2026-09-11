<?php

/**
 * KamiCore
 *
 * SPDX-License-Identifier: Apache-2.0
 *
 * @see https://kamicore.org
 */

declare(strict_types=1);

namespace Plugins\TranslationManager;

use Core\Content;
use Core\Request;
use Plugins\Forms\Forms;
use Plugins\Pagination\Pagination;
use Plugins\TextProcessor\TextProcessor;

if (!defined('IN_KAMI')) die();

final class TranslationManager extends \Core\BasePlugin
{
    private const BATCH_SIZE = 10;

    private const SYSTEM_ENTITIES = [
        'plugins' => [
            'table' => 'plugins',
            'label' => 'system_name',
            'title' => 'Plugins',
        ],
        'themes' => [
            'table' => 'themes',
            'label' => 'system_name',
            'title' => 'Themes',
        ],
        'content_types' => [
            'table' => 'content_types',
            'label' => 'system_name',
            'title' => 'Content types',
        ],
        'field_types' => [
            'table' => 'field_types',
            'label' => 'system_name',
            'title' => 'Field types',
        ],
        'fields' => [
            'table' => 'fields',
            'label' => 'system_name',
            'title' => 'Fields',
        ],
        'pages' => [
            'table' => 'pages',
            'label' => 'system_name',
            'title' => 'Pages',
        ],
        'theme_layouts' => [
            'table' => 'theme_layouts',
            'label' => 'system_name',
            'title' => 'Theme layouts',
        ],
        'usergroups' => [
            'table' => 'usergroups',
            'label' => 'system_name',
            'title' => 'User groups',
        ],
    ];

    private ?Forms $forms = null;
    private ?Pagination $pagination = null;
    private ?TextProcessor $processor = null;

    public function overview(array $instanceParams = []): string
    {
        $systemCards = $this->render('system-card', [
            'title' => $this->phrase('system_dictionary', 'System dictionary'),
            'count' => $this->dictionaryPhraseCounts(),
            'url' => $this->url('dictionaryEdit'),
        ]);

        foreach (self::SYSTEM_ENTITIES as $key => $config) {
            $count = (int) \DB::getOne('SELECT count(*) FROM ' . $config['table']);
            $systemCards .= $this->render('system-card', [
                'title' => $config['title'],
                'count' => $count,
                'url' => $this->url('systemList', ['entityType' => $key]),
            ]);
        }

        $contentTypes = $this->translatableContentTypes();
        $contentStats = $this->contentPhraseStats($contentTypes);
        $contentCards = '';
        foreach ($contentTypes as $type) {
            $typeId = (int) $type['ct_id'];
            $stats = $contentStats[$typeId] ?? [
                'items' => 0,
                'phrases' => '',
            ];
            $contentCards .= $this->render('content-card', [
                'title' => (string) $type['_sort_title'],
                'system_name' => $type['system_name'],
                'count' => $stats['items'],
                'phrase_counts' => $stats['phrases'],
                'url' => $this->url('contentList', ['type' => $typeId]),
            ]);
        }

        return $this->render('overview', [
            'system_cards' => $systemCards,
            'content_cards' => $contentCards,
        ]);
    }

    public function dictionaryEdit(array $instanceParams = []): string
    {
        return $this->renderDictionaryEditor();
    }

    public function dictionaryTranslate(array $instanceParams = []): string
    {
        $data = $this->params(
            ['source', 'target', 'provider', 'context', 'instructions', 'new-key', 'new-source', 'new-target'],
            Request::getPrefixedParams($this->prefix)
        );
        $sourceLanguage = $this->language((string) ($data['source'] ?? ''));
        $targetLanguage = $this->language((string) ($data['target'] ?? ''));
        $this->assertDifferentLanguages($sourceLanguage, $targetLanguage);

        $source = $this->loadExactTranslation(\Core\Translation::SYSTEM_ENTITY_UUID, $sourceLanguage);
        $target = $this->loadExactTranslation(\Core\Translation::SYSTEM_ENTITY_UUID, $targetLanguage);
        $this->applyDictionaryPostedValues(
            $source,
            Request::getPrefixedParams($this->prefix . '-source-value')
        );
        $this->applyDictionaryPostedValues(
            $target,
            Request::getPrefixedParams($this->prefix . '-target-value')
        );

        $newKey = trim((string) ($data['new-key'] ?? ''));
        if ($newKey !== '') {
            $newKey = $this->dictionaryKey($newKey);
            if (in_array($newKey, $this->dictionaryAllKeys(), true)) {
                throw new \InvalidArgumentException(
                    $this->phrase('dictionary_key_exists', 'This phrase key already exists.')
                );
            }

            $newSource = (string) ($data['new-source'] ?? '');
            if ($newSource === '') {
                throw new \InvalidArgumentException(
                    $this->phrase('dictionary_source_required', 'Source text is required for a new phrase.')
                );
            }
            $source[$newKey] = $newSource;

            $newTarget = (string) ($data['new-target'] ?? '');
            if ($newTarget !== '') {
                $target[$newKey] = $newTarget;
            }
        }

        $items = [];
        foreach ($source as $key => $value) {
            if (!is_string($key) || !is_string($value) || $value === '') {
                continue;
            }
            $items[$key] = [
                'text' => $value,
                'format' => $this->looksLikeHtml($value) ? 'html' : 'text',
            ];
        }

        $provider = trim((string) ($data['provider'] ?? ''));
        $options = [
            'source_language' => $sourceLanguage,
            'target_language' => $targetLanguage,
            'profile' => 'translation_manager',
            'context' => trim((string) ($data['context'] ?? '')),
            'instructions' => trim((string) ($data['instructions'] ?? '')),
        ];
        if ($provider !== '') {
            $options['provider'] = $provider;
        }

        $draft = $items === []
            ? []
            : $this->processor()->process($items, 'translate', $options);
        foreach ($draft as $key => $value) {
            if (is_string($key) && is_string($value)) {
                $target[$key] = $value;
            }
        }

        return $this->renderDictionaryEditor(
            $sourceLanguage,
            $targetLanguage,
            $source,
            $target,
            $options['context'],
            $options['instructions'],
            $provider,
            $this->notice($this->phrase('translation_ready', 'Translation draft is ready.'))
        );
    }

    public function dictionarySave(array $instanceParams = []): string
    {
        $data = $this->params(
            ['source', 'target', 'provider', 'context', 'instructions', 'new-key', 'new-source', 'new-target'],
            Request::getPrefixedParams($this->prefix)
        );
        $sourceLanguage = $this->language((string) ($data['source'] ?? ''));
        $targetLanguage = $this->language((string) ($data['target'] ?? ''));
        $this->assertDifferentLanguages($sourceLanguage, $targetLanguage);

        $source = $this->loadExactTranslation(\Core\Translation::SYSTEM_ENTITY_UUID, $sourceLanguage);
        $target = $this->loadExactTranslation(\Core\Translation::SYSTEM_ENTITY_UUID, $targetLanguage);
        $deletedKeys = $this->dictionaryPostedKeys(
            Request::getPrefixedParams($this->prefix . '-delete')
        );

        $this->applyDictionaryPostedValues(
            $source,
            Request::getPrefixedParams($this->prefix . '-source-value'),
            $deletedKeys
        );
        $this->applyDictionaryPostedValues(
            $target,
            Request::getPrefixedParams($this->prefix . '-target-value'),
            $deletedKeys
        );

        $newKey = trim((string) ($data['new-key'] ?? ''));
        if ($newKey !== '') {
            $newKey = $this->dictionaryKey($newKey);
            if (in_array($newKey, $this->dictionaryAllKeys(), true)) {
                throw new \InvalidArgumentException(
                    $this->phrase('dictionary_key_exists', 'This phrase key already exists.')
                );
            }

            $newSource = (string) ($data['new-source'] ?? '');
            if ($newSource === '') {
                throw new \InvalidArgumentException(
                    $this->phrase('dictionary_source_required', 'Source text is required for a new phrase.')
                );
            }
            $source[$newKey] = $newSource;

            $newTarget = (string) ($data['new-target'] ?? '');
            if ($newTarget !== '') {
                $target[$newKey] = $newTarget;
            }
        }

        $this->saveExactTranslation(\Core\Translation::SYSTEM_ENTITY_UUID, $sourceLanguage, $source);
        $this->saveExactTranslation(\Core\Translation::SYSTEM_ENTITY_UUID, $targetLanguage, $target);
        if ($deletedKeys !== []) {
            $this->deleteDictionaryKeys($deletedKeys);
        }

        return $this->renderDictionaryEditor(
            $sourceLanguage,
            $targetLanguage,
            null,
            null,
            trim((string) ($data['context'] ?? '')),
            trim((string) ($data['instructions'] ?? '')),
            trim((string) ($data['provider'] ?? '')),
            $this->notice($this->phrase('dictionary_saved', 'System dictionary saved.'))
        );
    }

    public function systemList(array $instanceParams = []): string
    {
        $data = $this->params(['entityType', 'source'], Request::getPrefixedParams($this->prefix));
        $entityType = (string) ($data['entityType'] ?? '');
        $config = $this->systemEntityConfig($entityType);
        $sourceLanguage = $this->language((string) ($data['source'] ?? $this->defaultSourceLanguage()));

        $rows = \DB::query(
            'SELECT uuid AS entity_uuid, '
            . $config['label'] . ' AS entity_label '
            . 'FROM ' . $config['table']
        );

        $entities = [];
        while ($row = \DB::fetchRow($rows)) {
            $translation = $this->loadExactTranslation(
                (string) $row['entity_uuid'],
                $sourceLanguage
            );
            $entities[] = [
                'entity_uuid' => (string)$row['entity_uuid'],
                'system_name' => (string)$row['entity_label'],
                'title' => (string)($translation['title'] ?? $row['entity_label']),
            ];
        }
        $entities = \Core\Translation::sortByTitle($entities, 'title', 'system_name', $sourceLanguage);
        $phraseCounts = $this->systemPhraseCounts($entityType, $entities);

        $items = '';
        foreach ($entities as $entity) {
            $items .= $this->render('system-row', [
                'title' => $entity['title'],
                'system_name' => $entity['system_name'],
                'phrase_counts' => $phraseCounts[$entity['entity_uuid']] ?? '',
                'batch_id' => $entity['entity_uuid'],
                'url' => $this->url('systemEdit', [
                    'entityType' => $entityType,
                    'uuid' => $entity['entity_uuid'],
                    'source' => $sourceLanguage,
                ]),
            ]);
        }

        return $this->render('system-list', [
            'title' => $config['title'],
            'language_select' => $this->languageSelect('source', $sourceLanguage),
            'load_url' => $this->url('systemList', ['entityType' => $entityType]),
            'items' => $items,
            'batch_panel' => $this->batchPanel('system', $sourceLanguage, [
                'entity_type' => $entityType,
            ]),
            'back_url' => $this->url('overview'),
            'back_label' => $this->escape($this->phrase('back_to_translations', 'Back to translations')),
        ]);
    }

    public function systemEdit(array $instanceParams = []): string
    {
        return $this->renderSystemEditor();
    }

    public function systemTranslate(array $instanceParams = []): string
    {
        $data = $this->params(['entityType', 'uuid', 'source', 'target', 'provider', 'context', 'instructions'], Request::getPrefixedParams($this->prefix));
        $entityType = (string) ($data['entityType'] ?? '');
        $uuid = $this->uuid((string) ($data['uuid'] ?? ''));
        $sourceLanguage = $this->language((string) ($data['source'] ?? ''));
        $targetLanguage = $this->language((string) ($data['target'] ?? ''));
        $this->assertDifferentLanguages($sourceLanguage, $targetLanguage);
        $source = $this->loadExactTranslation($uuid, $sourceLanguage);
        $flat = $this->flattenStrings($source);
        $items = [];

        foreach ($flat as $token => $field) {
            if ($field['value'] === '') {
                continue;
            }
            $items[$token] = [
                'text' => $field['value'],
                'format' => $field['format'],
            ];
        }

        $provider = trim((string) ($data['provider'] ?? ''));
        $options = [
            'source_language' => $sourceLanguage,
            'target_language' => $targetLanguage,
            'profile' => 'translation_manager',
            'context' => trim((string) ($data['context'] ?? '')),
            'instructions' => trim((string) ($data['instructions'] ?? '')),
        ];
        if ($provider !== '') {
            $options['provider'] = $provider;
        }

        $draft = $items === []
            ? []
            : $this->processor()->process($items, 'translate', $options);

        return $this->renderSystemEditor(
            $entityType,
            $uuid,
            $sourceLanguage,
            $targetLanguage,
            $draft,
            $options['context'],
            $options['instructions'],
            $provider,
            $this->notice($this->phrase('translation_ready', 'Translation draft is ready.'))
        );
    }

    public function systemSave(array $instanceParams = []): string
    {
        $data = $this->params(['entityType', 'uuid', 'source', 'target', 'provider', 'context', 'instructions'], Request::getPrefixedParams($this->prefix));
        $entityType = (string) ($data['entityType'] ?? '');
        $uuid = $this->uuid((string) ($data['uuid'] ?? ''));
        $sourceLanguage = $this->language((string) ($data['source'] ?? ''));
        $targetLanguage = $this->language((string) ($data['target'] ?? ''));
        $this->assertDifferentLanguages($sourceLanguage, $targetLanguage);
        $this->findSystemEntity($entityType, $uuid);

        $source = $this->loadExactTranslation($uuid, $sourceLanguage);
        $target = $this->loadExactTranslation($uuid, $targetLanguage);
        $fields = $this->flattenStrings($source);
        $values = Request::getPrefixedParams($this->prefix . '-value');

        foreach ($fields as $token => $field) {
            $value = array_key_exists($token, $values) ? (string) $values[$token] : '';
            if ($value === '') {
                $this->unsetPath($target, $field['path']);
            } else {
                $this->setPath($target, $field['path'], $value);
            }
        }

        $this->saveExactTranslation($uuid, $targetLanguage, $target, $entityType);

        return $this->renderSystemEditor(
            $entityType,
            $uuid,
            $sourceLanguage,
            $targetLanguage,
            null,
            trim((string) ($data['context'] ?? '')),
            trim((string) ($data['instructions'] ?? '')),
            trim((string) ($data['provider'] ?? '')),
            $this->notice($this->phrase('saved', 'Translation saved.'))
        );
    }

    public function contentList(array $instanceParams = []): string
    {
        $data = $this->params(['type', 'source', 'page'], Request::getPrefixedParams($this->prefix));
        $typeId = (int) ($data['type'] ?? 0);
        $sourceLanguage = $this->language((string) ($data['source'] ?? $this->defaultSourceLanguage()));
        $type = $this->translatableContentType($typeId);
        $schema = $this->decodeJson($type['schema'] ?? null);
        $titleField = $schema['title_field'] ?? null;

        $page = max(1, (int) ($data['page'] ?? 1));
        $perPage = 100;
        $total = (int) \DB::getOne(
            'SELECT count(*) FROM content_items WHERE ct_id=$1',
            [$typeId]
        );
        $offset = ($page - 1) * $perPage;
        $rows = \DB::query(
            'SELECT i.item_id, i.item_uuid, i.item_slug, i.common_data,
                    t.translated_data
             FROM content_items i
             LEFT JOIN translations t
               ON t.entity_uuid=i.item_uuid AND t.lang_code=$2
             WHERE i.ct_id=$1
             ORDER BY i.item_id DESC
             LIMIT $3::int OFFSET $4::int',
            [$typeId, $sourceLanguage, $perPage, $offset]
        );

        $pageItems = \DB::fetchAll($rows);
        $phraseCounts = $this->contentItemPhraseCounts($pageItems);

        $items = '';
        foreach ($pageItems as $row) {
            $common = $this->decodeJson($row['common_data'] ?? null);
            $translated = $this->decodeJson($row['translated_data'] ?? null);
            $dataSet = array_replace($common, $translated);
            $title = $titleField && isset($dataSet[$titleField]) && is_scalar($dataSet[$titleField])
                ? strip_tags((string) $dataSet[$titleField])
                : '#' . $row['item_id'];
            $items .= $this->render('content-row', [
                'title' => $title,
                'slug' => (string) ($row['item_slug'] ?? ''),
                'phrase_counts' => $phraseCounts[(int) $row['item_id']] ?? '',
                'batch_id' => (string) $row['item_id'],
                'url' => $this->url('contentEdit', [
                    'type' => $typeId,
                    'id' => $row['item_id'],
                    'source' => $sourceLanguage,
                ]),
            ]);
        }

        $typeTranslation = $this->loadExactTranslation(
            (string) $type['uuid'],
            $sourceLanguage
        );

        return $this->render('content-list', [
            'title' => (string) ($typeTranslation['title'] ?? $type['system_name']),
            'language_select' => $this->languageSelect('source', $sourceLanguage),
            'load_url' => $this->url('contentList', ['type' => $typeId]),
            'items' => $items,
            'batch_panel' => $this->batchPanel('content', $sourceLanguage, [
                'type_id' => $typeId,
            ]),
            'pagination' => $this->pagination()->renderPagination(
                page: $page,
                perPage: $perPage,
                total: $total,
                base_url: $this->url('contentList', [
                    'type' => $typeId,
                    'source' => $sourceLanguage,
                ]),
                options: ['page_param' => $this->prefix . '-page']
            ),
            'back_url' => $this->url('overview'),
            'back_label' => $this->escape($this->phrase('back_to_translations', 'Back to translations')),
        ]);
    }

    public function batchTranslate(array $data = []): string
    {
        \Core\Response::addHeader('Content-Type: application/json; charset=utf-8');
        \Core\Response::addHeader('Cache-Control: no-store, no-cache, must-revalidate');

        try {
            if (!defined('KAMI_AJAX')) {
                throw new \RuntimeException('Batch translation is available only through AJAX.');
            }

            $kind = trim((string) ($data['kind'] ?? ''));
            $scope = trim((string) ($data['scope'] ?? 'all'));
            if (!in_array($scope, ['all', 'current', 'selected'], true)) {
                throw new \InvalidArgumentException('Unknown batch translation scope.');
            }

            $sourceLanguage = $this->language((string) ($data['source'] ?? ''));
            $targetLanguage = $this->language((string) ($data['target'] ?? ''));
            $this->assertDifferentLanguages($sourceLanguage, $targetLanguage);
            $provider = trim((string) ($data['provider'] ?? ''));

            $result = match ($kind) {
                'content' => $this->batchContentStep(
                    (int) ($data['type_id'] ?? 0),
                    $sourceLanguage,
                    $targetLanguage,
                    $provider,
                    $scope,
                    is_array($data['ids'] ?? null) ? $data['ids'] : [],
                    (int) ($data['cursor'] ?? 0)
                ),
                'system' => $this->batchSystemStep(
                    (string) ($data['entity_type'] ?? ''),
                    $sourceLanguage,
                    $targetLanguage,
                    $provider,
                    is_array($data['ids'] ?? null) ? $data['ids'] : []
                ),
                default => throw new \InvalidArgumentException('Unknown batch translation kind.'),
            };

            return $this->batchJson(['status' => 'ok'] + $result);
        } catch (\Throwable $e) {
            return $this->batchJson([
                'status' => 'error',
                'message' => $e->getMessage(),
            ]);
        }
    }

    public function contentEdit(array $instanceParams = []): string
    {
        return $this->renderContentEditor();
    }

    public function contentTranslate(array $instanceParams = []): string
    {
        $data = $this->params(['type', 'id', 'source', 'target', 'provider', 'context', 'instructions'], Request::getPrefixedParams($this->prefix));
        $typeId = (int) ($data['type'] ?? 0);
        $itemId = (int) ($data['id'] ?? 0);
        $sourceLanguage = $this->language((string) ($data['source'] ?? ''));
        $targetLanguage = $this->language((string) ($data['target'] ?? ''));
        $this->assertDifferentLanguages($sourceLanguage, $targetLanguage);
        $type = $this->translatableContentType($typeId);
        $item = $this->contentItem($itemId, $typeId);
        $source = $this->loadExactTranslation((string) $item['item_uuid'], $sourceLanguage);
        $fields = $this->translatableFields($type);
        $items = [];

        foreach ($fields as $name => $field) {
            $value = $source[$name] ?? null;
            if (!is_scalar($value) || (string) $value === '') {
                continue;
            }
            $items[$name] = [
                'text' => (string) $value,
                'format' => $field['type'] === 'richtext' ? 'html' : 'text',
            ];
        }

        $provider = trim((string) ($data['provider'] ?? ''));
        $options = [
            'source_language' => $sourceLanguage,
            'target_language' => $targetLanguage,
            'profile' => 'translation_manager',
            'context' => trim((string) ($data['context'] ?? '')),
            'instructions' => trim((string) ($data['instructions'] ?? '')),
        ];
        if ($provider !== '') {
            $options['provider'] = $provider;
        }

        $draft = $items === []
            ? []
            : $this->processor()->process($items, 'translate', $options);

        return $this->renderContentEditor(
            $typeId,
            $itemId,
            $sourceLanguage,
            $targetLanguage,
            $draft,
            $options['context'],
            $options['instructions'],
            $provider,
            $this->notice($this->phrase('translation_ready', 'Translation draft is ready.'))
        );
    }

    public function contentSave(array $instanceParams = []): string
    {
        $data = $this->params(['type', 'id', 'source', 'target', 'provider', 'context', 'instructions'], Request::getPrefixedParams($this->prefix));
        $typeId = (int) ($data['type'] ?? 0);
        $itemId = (int) ($data['id'] ?? 0);
        $sourceLanguage = $this->language((string) ($data['source'] ?? ''));
        $targetLanguage = $this->language((string) ($data['target'] ?? ''));
        $this->assertDifferentLanguages($sourceLanguage, $targetLanguage);
        $type = $this->translatableContentType($typeId);
        $this->contentItem($itemId, $typeId);
        $fields = $this->translatableFields($type);
        $posted = Request::getPrefixedParams($this->prefix . '-value');
        $values = [];

        foreach ($fields as $name => $_field) {
            if (!array_key_exists($name, $posted)) {
                continue;
            }
            $value = $posted[$name];
            $values[$name] = is_string($value) && $value === '' ? null : $value;
        }

        Content::update($itemId, $values, $targetLanguage, [$sourceLanguage]);

        return $this->renderContentEditor(
            $typeId,
            $itemId,
            $sourceLanguage,
            $targetLanguage,
            null,
            trim((string) ($data['context'] ?? '')),
            trim((string) ($data['instructions'] ?? '')),
            trim((string) ($data['provider'] ?? '')),
            $this->notice($this->phrase('saved', 'Translation saved.'))
        );
    }

    private function renderDictionaryEditor(
        ?string $sourceLanguage = null,
        ?string $targetLanguage = null,
        ?array $sourceOverride = null,
        ?array $targetOverride = null,
        string $context = '',
        string $instructions = '',
        string $provider = '',
        string $notice = ''
    ): string {
        $data = $this->params(
            ['source', 'target', 'provider', 'context', 'instructions'],
            Request::getPrefixedParams($this->prefix)
        );
        $sourceLanguage ??= $this->language(
            (string) ($data['source'] ?? $this->defaultSourceLanguage())
        );
        $targetLanguage ??= $this->language(
            (string) ($data['target'] ?? $this->defaultTargetLanguage($sourceLanguage))
        );
        $this->assertDifferentLanguages($sourceLanguage, $targetLanguage);

        $source = $sourceOverride
            ?? $this->loadExactTranslation(\Core\Translation::SYSTEM_ENTITY_UUID, $sourceLanguage);
        $target = $targetOverride
            ?? $this->loadExactTranslation(\Core\Translation::SYSTEM_ENTITY_UUID, $targetLanguage);
        $keys = array_values(array_unique([
            ...$this->dictionaryAllKeys(),
            ...array_keys($source),
            ...array_keys($target),
        ]));
        $keys = array_values(array_filter(
            $keys,
            static fn(mixed $key): bool => is_string($key) && $key !== ''
        ));
        natcasesort($keys);

        $rows = '';
        foreach ($keys as $key) {
            $token = $this->pathToken([$key]);
            $rows .= $this->render('dictionary-row', [
                'phrase_key' => $this->escape($key),
                'source_name' => $this->escape($this->prefix . '-source-value-' . $token),
                'source_value' => $this->escape(
                    is_string($source[$key] ?? null) ? $source[$key] : ''
                ),
                'target_name' => $this->escape($this->prefix . '-target-value-' . $token),
                'target_value' => $this->escape(
                    is_string($target[$key] ?? null) ? $target[$key] : ''
                ),
                'delete_name' => $this->escape($this->prefix . '-delete-' . $token),
                'delete_label' => $this->escape(
                    $this->phrase('delete_phrase', 'Delete phrase from all languages')
                ),
            ]);
        }

        $providerData = $this->processor()->getProviders('translate');
        $provider = $provider !== '' ? $provider : (string) ($providerData['default'] ?? '');

        return $this->render('dictionary-edit', [
            'notice' => $notice,
            'entity_title' => $this->escape(
                $this->phrase('system_dictionary', 'System dictionary')
            ),
            'source_select' => $this->languageSelect('source', $sourceLanguage),
            'target_select' => $this->languageSelect('target', $targetLanguage),
            'provider_select' => $this->providerSelect($providerData, $provider),
            'context' => $this->escape($context),
            'instructions' => $this->escape($instructions),
            'rows' => $rows !== '' ? $rows : $this->notice(
                $this->phrase('dictionary_empty', 'The system dictionary is empty.'),
                'warning'
            ),
            'reload_url' => $this->url('dictionaryEdit'),
            'translate_url' => $this->url('dictionaryTranslate'),
            'save_url' => $this->url('dictionarySave'),
            'back_url' => $this->url('overview'),
            'back_label' => $this->escape(
                $this->phrase('back_to_translations', 'Back to translations')
            ),
        ]);
    }

    private function renderSystemEditor(
        ?string $entityType = null,
        ?string $uuid = null,
        ?string $sourceLanguage = null,
        ?string $targetLanguage = null,
        ?array $draft = null,
        string $context = '',
        string $instructions = '',
        string $provider = '',
        string $notice = ''
    ): string {
        $data = $this->params(['entityType', 'uuid', 'source', 'target', 'provider', 'context', 'instructions'], Request::getPrefixedParams($this->prefix));
        $entityType ??= (string) ($data['entityType'] ?? '');
        $uuid ??= $this->uuid((string) ($data['uuid'] ?? ''));
        $sourceLanguage ??= $this->language((string) ($data['source'] ?? $this->defaultSourceLanguage()));
        $targetLanguage ??= $this->language((string) ($data['target'] ?? $this->defaultTargetLanguage($sourceLanguage)));
        $entity = $this->findSystemEntity($entityType, $uuid);
        $source = $this->loadExactTranslation($uuid, $sourceLanguage);
        $target = $this->loadExactTranslation($uuid, $targetLanguage);
        $fields = $this->flattenStrings($source);
        $rows = '';

        foreach ($fields as $token => $field) {
            $targetValue = $draft !== null && array_key_exists($token, $draft)
                ? $draft[$token]
                : $this->getPath($target, $field['path']);
            $rows .= $this->render('translation-row', [
                'label' => $field['label'],
                'source_value' => $this->escape($field['value']),
                'field_name' => $this->prefix . '-value-' . $token,
                'target_value' => $this->escape(is_string($targetValue) ? $targetValue : ''),
            ]);
        }

        $config = $this->systemEntityConfig($entityType);
        $providerData = $this->processor()->getProviders('translate');
        $provider = $provider !== '' ? $provider : (string) ($providerData['default'] ?? '');

        return $this->render('system-edit', [
            'notice' => $notice,
            'entity_title' => $config['title'] . ': ' . $entity['entity_label'],
            'source_select' => $this->languageSelect('source', $sourceLanguage),
            'target_select' => $this->languageSelect('target', $targetLanguage),
            'provider_select' => $this->providerSelect($providerData, $provider),
            'context' => $this->escape($context),
            'instructions' => $this->escape($instructions),
            'rows' => $rows !== '' ? $rows : $this->notice(
                $this->phrase('no_source_translation', 'No exact source translation is available.'),
                'warning'
            ),
            'reload_url' => $this->url('systemEdit', [
                'entityType' => $entityType,
                'uuid' => $uuid,
            ]),
            'translate_url' => $this->url('systemTranslate', [
                'entityType' => $entityType,
                'uuid' => $uuid,
            ]),
            'save_url' => $this->url('systemSave', [
                'entityType' => $entityType,
                'uuid' => $uuid,
            ]),
            'back_url' => $this->url('systemList', ['entityType' => $entityType]),
            'back_label' => $this->escape($this->phrase(
                'back_to_' . $entityType,
                'Back to ' . lcfirst((string) $config['title'])
            )),
        ]);
    }

    private function renderContentEditor(
        ?int $typeId = null,
        ?int $itemId = null,
        ?string $sourceLanguage = null,
        ?string $targetLanguage = null,
        ?array $draft = null,
        string $context = '',
        string $instructions = '',
        string $provider = '',
        string $notice = ''
    ): string {
        $data = $this->params(['type', 'id', 'source', 'target', 'provider', 'context', 'instructions'], Request::getPrefixedParams($this->prefix));
        $typeId ??= (int) ($data['type'] ?? 0);
        $itemId ??= (int) ($data['id'] ?? 0);
        $sourceLanguage ??= $this->language((string) ($data['source'] ?? $this->defaultSourceLanguage()));
        $targetLanguage ??= $this->language((string) ($data['target'] ?? $this->defaultTargetLanguage($sourceLanguage)));
        $type = $this->translatableContentType($typeId);
        $item = $this->contentItem($itemId, $typeId);
        $source = $this->loadExactTranslation((string) $item['item_uuid'], $sourceLanguage);
        $target = $this->loadExactTranslation((string) $item['item_uuid'], $targetLanguage);
        $fields = $this->translatableFields($type);
        $rows = '';

        foreach ($fields as $name => $field) {
            $sourceValue = $source[$name] ?? '';
            $targetValue = $draft !== null && array_key_exists($name, $draft)
                ? $draft[$name]
                : ($target[$name] ?? '');
            $editor = $this->forms()->renderField([
                'name' => $this->prefix . '-value-' . $name,
                'type' => (string) ($field['type'] ?? 'string'),
                'label' => '',
                'value' => $targetValue,
                'settings' => ['required' => false],
                'params' => is_array($field['params'] ?? null) ? $field['params'] : [],
            ]);
            $rows .= $this->render('content-translation-row', [
                'label' => $this->escape((string) ($field['title'] ?? $name)),
                'source_value' => $this->escape(is_scalar($sourceValue) ? (string) $sourceValue : ''),
                'editor' => $editor,
            ]);
        }

        $schema = $this->decodeJson($type['schema'] ?? null);
        $titleField = $schema['title_field'] ?? null;
        $sourceData = array_replace(
            $this->decodeJson($item['common_data'] ?? null),
            $source
        );
        $itemTitle = is_string($titleField)
            && isset($sourceData[$titleField])
            && is_scalar($sourceData[$titleField])
            && trim(strip_tags((string) $sourceData[$titleField])) !== ''
                ? strip_tags((string) $sourceData[$titleField])
                : '#' . $itemId;
        $providerData = $this->processor()->getProviders('translate');
        $provider = $provider !== '' ? $provider : (string) ($providerData['default'] ?? '');

        return $this->render('content-edit', [
            'notice' => $notice,
            'entity_title' => $this->escape($itemTitle),
            'source_select' => $this->languageSelect('source', $sourceLanguage),
            'target_select' => $this->languageSelect('target', $targetLanguage),
            'provider_select' => $this->providerSelect($providerData, $provider),
            'context' => $this->escape($context),
            'instructions' => $this->escape($instructions),
            'rows' => $rows,
            'reload_url' => $this->url('contentEdit', [
                'type' => $typeId,
                'id' => $itemId,
            ]),
            'translate_url' => $this->url('contentTranslate', [
                'type' => $typeId,
                'id' => $itemId,
            ]),
            'save_url' => $this->url('contentSave', [
                'type' => $typeId,
                'id' => $itemId,
            ]),
            'back_url' => $this->url('contentList', ['type' => $typeId]),
            'back_label' => $this->escape($this->phrase('back_to_content_items', 'Back to content items')),
        ]);
    }

    private function systemEntityConfig(string $entityType): array
    {
        $config = self::SYSTEM_ENTITIES[$entityType] ?? null;
        if (!is_array($config)) {
            throw new \InvalidArgumentException('Unknown system entity type.');
        }
        return $config;
    }

    private function findSystemEntity(string $entityType, string $uuid): array
    {
        $config = $this->systemEntityConfig($entityType);
        $row = \DB::getRow(
            'SELECT uuid AS entity_uuid, '
            . $config['label'] . ' AS entity_label '
            . 'FROM ' . $config['table'] . ' WHERE uuid=$1',
            [$uuid]
        );
        if (!$row) {
            throw new \OutOfBoundsException('System entity not found.');
        }
        return $row;
    }

    private function translatableContentTypes(): array
    {
        $sourceLanguage = $this->defaultSourceLanguage();
        $rows = \DB::query('SELECT * FROM content_types');
        $result = [];
        while ($row = \DB::fetchRow($rows)) {
            if ($this->translatableFields($row) === []) {
                continue;
            }
            $translation = $this->loadExactTranslation((string)$row['uuid'], $sourceLanguage);
            $row['_sort_title'] = (string)($translation['title'] ?? $row['system_name']);
            $result[] = $row;
        }
        return \Core\Translation::sortByTitle(
            $result,
            '_sort_title',
            'system_name',
            $sourceLanguage
        );
    }

    private function translatableContentType(int $typeId): array
    {
        $row = \DB::getRow('SELECT * FROM content_types WHERE ct_id=$1', [$typeId]);
        if (!$row || $this->translatableFields($row) === []) {
            throw new \OutOfBoundsException('Translatable content type not found.');
        }
        return $row;
    }

    private function translatableFields(array $type): array
    {
        $contentType = \Core\Content::getContentType(
            (int)$type['ct_id'],
            $this->defaultSourceLanguage()
        );
        $fields = is_array($contentType['schema']['fields'] ?? null)
            ? $contentType['schema']['fields']
            : [];
        $result = [];
        uasort($fields, static fn(array $a, array $b): int =>
            ((int) ($a['displayorder'] ?? 0)) <=> ((int) ($b['displayorder'] ?? 0))
        );
        foreach ($fields as $name => $field) {
            if (is_array($field) && !empty($field['settings']['translatable'])) {
                $result[(string)$name] = $field;
            }
        }
        return $result;
    }

    private function contentItem(int $itemId, int $typeId): array
    {
        $item = \DB::getRow(
            'SELECT * FROM content_items WHERE item_id=$1 AND ct_id=$2',
            [$itemId, $typeId]
        );
        if (!$item) {
            throw new \OutOfBoundsException('Content item not found.');
        }
        return $item;
    }

    private function loadExactTranslation(string $uuid, string $language): array
    {
        return $this->decodeJson(\DB::getOne(
            'SELECT translated_data FROM translations WHERE entity_uuid=$1 AND lang_code=$2',
            [$uuid, $language]
        ));
    }

    private function saveExactTranslation(
        string $uuid,
        string $language,
        array $data,
        ?string $entityType = null
    ): void {
        if ($data === []) {
            \DB::delete('translations', 'entity_uuid=$1 AND lang_code=$2', [$uuid, $language]);
        } else {
            \DB::query(
                'INSERT INTO translations(entity_uuid, lang_code, translated_data, updated_at)
                 VALUES($1, $2, $3::jsonb, NOW())
                 ON CONFLICT (entity_uuid, lang_code)
                 DO UPDATE SET
                     translated_data=EXCLUDED.translated_data,
                     updated_at=EXCLUDED.updated_at',
                [$uuid, $language, $this->json($data)]
            );
        }
        \Cache::del("globals:{$uuid}_{$language}");

        if ($entityType === 'fields') {
            $fieldId = (int)(\DB::getOne('select field_id from fields where uuid=$1', [$uuid]) ?: 0);
            if ($fieldId > 0) \Core\ContentStructure::invalidateFieldCache($fieldId);
        } elseif ($entityType === 'content_types') {
            $contentTypeId = (int)(\DB::getOne('select ct_id from content_types where uuid=$1', [$uuid]) ?: 0);
            if ($contentTypeId > 0) \Core\ContentStructure::invalidateContentTypeCache($contentTypeId);
        }
    }

    private function flattenStrings(array $data, array $path = []): array
    {
        $result = [];
        foreach ($data as $key => $value) {
            $currentPath = [...$path, $key];
            if (is_string($value)) {
                $token = $this->pathToken($currentPath);
                $result[$token] = [
                    'path' => $currentPath,
                    'label' => implode(' › ', array_map('strval', $currentPath)),
                    'value' => $value,
                    'format' => $this->looksLikeHtml($value) ? 'html' : 'text',
                ];
            } elseif (is_array($value)) {
                $result += $this->flattenStrings($value, $currentPath);
            }
        }
        return $result;
    }

    private function pathToken(array $path): string
    {
        return rtrim(strtr(base64_encode($this->json($path)), '+/', '-_'), '=');
    }

    private function pathFromToken(string $token): array
    {
        $padding = (4 - strlen($token) % 4) % 4;
        $decoded = base64_decode(
            strtr($token . str_repeat('=', $padding), '-_', '+/'),
            true
        );
        if (!is_string($decoded)) {
            return [];
        }
        $path = json_decode($decoded, true);
        return is_array($path) ? $path : [];
    }

    private function getPath(array $data, array $path): mixed
    {
        foreach ($path as $segment) {
            if (!is_array($data) || !array_key_exists($segment, $data)) {
                return null;
            }
            $data = $data[$segment];
        }
        return $data;
    }

    private function setPath(array &$data, array $path, string $value): void
    {
        $node =& $data;
        $last = array_pop($path);
        foreach ($path as $segment) {
            if (!isset($node[$segment]) || !is_array($node[$segment])) {
                $node[$segment] = [];
            }
            $node =& $node[$segment];
        }
        $node[$last] = $value;
    }

    private function unsetPath(array &$data, array $path): void
    {
        $this->unsetPathRecursive($data, $path, 0);
    }

    private function unsetPathRecursive(array &$data, array $path, int $offset): bool
    {
        $key = $path[$offset] ?? null;
        if ($key === null || !array_key_exists($key, $data)) {
            return $data === [];
        }
        if ($offset === count($path) - 1) {
            unset($data[$key]);
        } elseif (is_array($data[$key])) {
            if ($this->unsetPathRecursive($data[$key], $path, $offset + 1)) {
                unset($data[$key]);
            }
        }
        return $data === [];
    }

    private function batchPanel(string $kind, string $sourceLanguage, array $context): string
    {
        $providerData = $this->processor()->getProviders('translate');
        $provider = (string) ($providerData['default'] ?? '');
        $targetLanguage = $this->defaultTargetLanguage($sourceLanguage);

        return $this->render('batch-panel', [
            'kind' => $this->escape($kind),
            'source_language' => $this->escape($sourceLanguage),
            'source_code' => $this->escape(strtoupper($sourceLanguage)),
            'type_id' => (string) ((int) ($context['type_id'] ?? 0)),
            'entity_type' => $this->escape((string) ($context['entity_type'] ?? '')),
            'target_select' => $this->languageSelect('batch-target', $targetLanguage),
            'provider_select' => $this->providerSelect($providerData, $provider, 'batch-provider'),
            'endpoint' => '/ajax/TranslationManager/batchTranslate',
            'running_label' => $this->escape($this->phrase('batch_running', 'Translating...')),
            'done_label' => $this->escape($this->phrase('batch_done', 'Done.')),
            'stopped_label' => $this->escape($this->phrase('batch_stopped', 'Stopped.')),
            'stopping_label' => $this->escape($this->phrase('batch_stopping', 'Stopping...')),
            'select_items_label' => $this->escape($this->phrase(
                'batch_select_items',
                'Select at least one item.'
            )),
            'languages_differ_label' => $this->escape($this->phrase(
                'batch_languages_differ',
                'Source and target languages must be different.'
            )),
        ]);
    }

    private function batchContentStep(
        int $typeId,
        string $sourceLanguage,
        string $targetLanguage,
        string $provider,
        string $scope,
        array $ids,
        int $cursor
    ): array {
        $type = $this->translatableContentType($typeId);

        if ($scope === 'all') {
            $itemIds = $this->contentMissingCandidateIds(
                $typeId,
                $sourceLanguage,
                $targetLanguage,
                $cursor
            );
        } else {
            $itemIds = $this->normalizeBatchItemIds($ids);
        }

        if ($itemIds === []) {
            return [
                'translated' => 0,
                'skipped' => 0,
                'errors' => 0,
                'cursor' => (string) $cursor,
                'done' => true,
            ];
        }

        $rows = \DB::query(
            <<<'SQL'
            SELECT
                i.item_id,
                i.item_uuid,
                source.translated_data AS source_data,
                target.translated_data AS target_data
            FROM content_items i
            LEFT JOIN translations source
              ON source.entity_uuid=i.item_uuid
             AND source.lang_code=$3
            LEFT JOIN translations target
              ON target.entity_uuid=i.item_uuid
             AND target.lang_code=$4
            WHERE i.ct_id=$1
              AND i.item_id=ANY($2::bigint[])
            ORDER BY i.item_id
            SQL,
            [
                $typeId,
                '{' . implode(',', $itemIds) . '}',
                $sourceLanguage,
                $targetLanguage,
            ]
        );

        $contentRows = \DB::fetchAll($rows);
        $fields = $this->translatableFields($type);
        $fragments = [];
        $metadata = [];
        $skipped = count($itemIds) - count($contentRows);

        foreach ($contentRows as $row) {
            $itemId = (int) $row['item_id'];
            $source = $this->decodeJson($row['source_data'] ?? null);
            $target = $this->decodeJson($row['target_data'] ?? null);
            $hasMissing = false;

            foreach ($fields as $name => $field) {
                $sourceValue = $source[$name] ?? null;
                if (!is_scalar($sourceValue) || trim((string) $sourceValue) === '') {
                    continue;
                }

                $targetValue = $target[$name] ?? null;
                if (is_scalar($targetValue) && trim((string) $targetValue) !== '') {
                    continue;
                }

                $key = 'c' . $itemId . ':' . $name;
                $fragments[$key] = [
                    'text' => (string) $sourceValue,
                    'format' => ($field['type'] ?? null) === 'richtext' ? 'html' : 'text',
                ];
                $metadata[$key] = [
                    'item_id' => $itemId,
                    'item_uuid' => (string) $row['item_uuid'],
                    'field' => $name,
                ];
                $hasMissing = true;
            }

            if (!$hasMissing) {
                $skipped++;
            }
        }

        $translated = 0;
        $errors = 0;

        if ($fragments !== []) {
            $options = [
                'source_language' => $sourceLanguage,
                'target_language' => $targetLanguage,
                'profile' => 'translation_manager_batch',
            ];
            if ($provider !== '') {
                $options['provider'] = $provider;
            }

            $processed = $this->processor()->process($fragments, 'translate', $options);
            $patches = [];
            $uuids = [];

            foreach ($processed as $key => $value) {
                $meta = $metadata[$key] ?? null;
                if (!is_array($meta)) {
                    continue;
                }
                $itemId = (int) $meta['item_id'];
                $patches[$itemId][(string) $meta['field']] = $value;
                $uuids[$itemId] = (string) $meta['item_uuid'];
            }

            foreach ($patches as $itemId => $patch) {
                try {
                    $currentTarget = $this->loadExactTranslation($uuids[$itemId], $targetLanguage);
                    foreach (array_keys($patch) as $fieldName) {
                        $currentValue = $currentTarget[$fieldName] ?? null;
                        if (is_scalar($currentValue) && trim((string) $currentValue) !== '') {
                            unset($patch[$fieldName]);
                        }
                    }

                    if ($patch === []) {
                        $skipped++;
                        continue;
                    }

                    Content::patchTranslation($itemId, $patch, $targetLanguage);
                    $translated += count($patch);
                } catch (\Throwable) {
                    $errors++;
                }
            }
        }

        $nextCursor = max($itemIds);

        return [
            'translated' => $translated,
            'skipped' => $skipped,
            'errors' => $errors,
            'cursor' => (string) $nextCursor,
            'done' => $scope !== 'all' || count($itemIds) < self::BATCH_SIZE,
        ];
    }

    private function batchSystemStep(
        string $entityType,
        string $sourceLanguage,
        string $targetLanguage,
        string $provider,
        array $ids
    ): array {
        $this->systemEntityConfig($entityType);
        $entityIds = [];
        foreach (array_slice($ids, 0, self::BATCH_SIZE) as $id) {
            if (!is_scalar($id)) {
                continue;
            }
            try {
                $entityIds[] = $this->uuid((string) $id);
            } catch (\Throwable) {
                // Invalid client-side selections are ignored and counted as skipped below.
            }
        }
        $entityIds = array_values(array_unique($entityIds));

        if ($entityIds === []) {
            return [
                'translated' => 0,
                'skipped' => count($ids),
                'errors' => 0,
                'cursor' => '',
                'done' => true,
            ];
        }

        $fragments = [];
        $metadata = [];
        $skipped = count(array_slice($ids, 0, self::BATCH_SIZE)) - count($entityIds);

        foreach ($entityIds as $index => $uuid) {
            try {
                $this->findSystemEntity($entityType, $uuid);
            } catch (\Throwable) {
                $skipped++;
                continue;
            }

            $source = $this->loadExactTranslation($uuid, $sourceLanguage);
            $target = $this->loadExactTranslation($uuid, $targetLanguage);
            $flat = $this->flattenStrings($source);
            $hasMissing = false;

            foreach ($flat as $token => $field) {
                if (trim((string) $field['value']) === '') {
                    continue;
                }
                $targetValue = $this->getPath($target, $field['path']);
                if (is_string($targetValue) && trim($targetValue) !== '') {
                    continue;
                }

                $key = 's' . $index . ':' . $token;
                $fragments[$key] = [
                    'text' => (string) $field['value'],
                    'format' => (string) $field['format'],
                ];
                $metadata[$key] = [
                    'uuid' => $uuid,
                    'path' => $field['path'],
                ];
                $hasMissing = true;
            }

            if (!$hasMissing) {
                $skipped++;
            }
        }

        $translated = 0;
        $errors = 0;

        if ($fragments !== []) {
            $options = [
                'source_language' => $sourceLanguage,
                'target_language' => $targetLanguage,
                'profile' => 'translation_manager_batch',
            ];
            if ($provider !== '') {
                $options['provider'] = $provider;
            }

            $processed = $this->processor()->process($fragments, 'translate', $options);
            $byEntity = [];
            foreach ($processed as $key => $value) {
                $meta = $metadata[$key] ?? null;
                if (!is_array($meta)) {
                    continue;
                }
                $uuid = (string) $meta['uuid'];
                $byEntity[$uuid][] = [
                    'path' => $meta['path'],
                    'value' => $value,
                ];
            }

            foreach ($byEntity as $uuid => $values) {
                try {
                    $target = $this->loadExactTranslation($uuid, $targetLanguage);
                    $changed = 0;
                    foreach ($values as $value) {
                        $current = $this->getPath($target, $value['path']);
                        if (is_string($current) && trim($current) !== '') {
                            continue;
                        }
                        $this->setPath($target, $value['path'], (string) $value['value']);
                        $changed++;
                    }
                    if ($changed === 0) {
                        $skipped++;
                        continue;
                    }
                    $this->saveExactTranslation($uuid, $targetLanguage, $target, $entityType);
                    $translated += $changed;
                } catch (\Throwable) {
                    $errors++;
                }
            }
        }

        return [
            'translated' => $translated,
            'skipped' => $skipped,
            'errors' => $errors,
            'cursor' => '',
            'done' => true,
        ];
    }

    /** @return list<int> */
    private function contentMissingCandidateIds(
        int $typeId,
        string $sourceLanguage,
        string $targetLanguage,
        int $cursor
    ): array {
        $rows = \DB::query(
            <<<'SQL'
            SELECT i.item_id
            FROM content_items i
            JOIN content_types ct ON ct.ct_id=i.ct_id
            JOIN translations source
              ON source.entity_uuid=i.item_uuid
             AND source.lang_code=$2
            LEFT JOIN translations target
              ON target.entity_uuid=i.item_uuid
             AND target.lang_code=$3
            WHERE i.ct_id=$1
              AND i.item_id>$4
              AND EXISTS (
                  SELECT 1
                  FROM jsonb_each(COALESCE(source.translated_data, '{}'::jsonb)) field(field_name, field_value)
                  WHERE COALESCE(ct.schema->'fields', '{}'::jsonb) ? field.field_name
                    AND EXISTS (
                        SELECT 1
                        FROM fields definition
                        WHERE definition.system_name=field.field_name
                          AND COALESCE(
                              (definition.field_settings->>'translatable')::boolean,
                              false
                          )
                    )
                    AND jsonb_typeof(field.field_value)='string'
                    AND btrim(field.field_value #>> '{}') <> ''
                    AND (
                        target.translated_data IS NULL
                        OR NOT (target.translated_data ? field.field_name)
                        OR jsonb_typeof(target.translated_data -> field.field_name) <> 'string'
                        OR btrim(target.translated_data ->> field.field_name) = ''
                    )
              )
            ORDER BY i.item_id
            LIMIT 10
            SQL,
            [$typeId, $sourceLanguage, $targetLanguage, $cursor]
        );

        $result = [];
        while ($row = \DB::fetchRow($rows)) {
            $result[] = (int) $row['item_id'];
        }
        return $result;
    }

    private function normalizeBatchItemIds(array $ids): array
    {
        $result = [];
        foreach (array_slice($ids, 0, self::BATCH_SIZE) as $id) {
            $value = (int) $id;
            if ($value > 0) {
                $result[] = $value;
            }
        }
        return array_values(array_unique($result));
    }

    private function batchJson(array $data): string
    {
        return json_encode(
            $data,
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
        );
    }

    private function contentItemPhraseCounts(array $items): array
    {
        if ($items === []) {
            return [];
        }

        $languages = $this->activeLanguages();
        $languageCodes = array_column($languages, 'lang_code');
        $stats = [];
        $itemIds = [];

        foreach ($items as $item) {
            $itemId = (int) ($item['item_id'] ?? 0);
            if ($itemId < 1) {
                continue;
            }
            $itemIds[] = $itemId;
            $stats[$itemId] = [];
            foreach ($languageCodes as $code) {
                $stats[$itemId][$code] = [
                    'count' => 0,
                    'updated_at' => null,
                    'outdated' => false,
                ];
            }
        }

        if ($itemIds === []) {
            return [];
        }

        $rows = \DB::query(
            <<<'SQL'
            SELECT
                i.item_id,
                t.lang_code,
                t.updated_at,
                (t.updated_at < i.updated_at) AS outdated,
                count(field.field_name) FILTER (
                    WHERE COALESCE(ct.schema->'fields', '{}'::jsonb) ? field.field_name
                    AND EXISTS (
                        SELECT 1
                        FROM fields definition
                        WHERE definition.system_name=field.field_name
                          AND COALESCE(
                              (definition.field_settings->>'translatable')::boolean,
                              false
                          )
                    )
                    AND jsonb_typeof(field.field_value)='string'
                    AND btrim(field.field_value #>> '{}') <> ''
                ) AS phrase_count
            FROM content_items i
            JOIN content_types ct ON ct.ct_id=i.ct_id
            JOIN translations t ON t.entity_uuid=i.item_uuid
            JOIN languages l
              ON l.lang_code=t.lang_code
             AND l.is_active=true
            LEFT JOIN LATERAL jsonb_each(
                COALESCE(t.translated_data, '{}'::jsonb)
            ) AS field(field_name, field_value) ON true
            WHERE i.item_id=ANY($1::bigint[])
            GROUP BY i.item_id, t.lang_code, t.updated_at, i.updated_at
            SQL,
            ['{' . implode(',', $itemIds) . '}']
        );

        while ($row = \DB::fetchRow($rows)) {
            $itemId = (int) $row['item_id'];
            $language = (string) $row['lang_code'];
            if (!isset($stats[$itemId][$language])) {
                continue;
            }

            $translationUpdatedAt = (string) ($row['updated_at'] ?? '');
            $stats[$itemId][$language] = [
                'count' => (int) $row['phrase_count'],
                'updated_at' => $translationUpdatedAt !== '' ? $translationUpdatedAt : null,
                'outdated' => $row['outdated'] === true || $row['outdated'] === 't',
            ];
        }

        $result = [];
        foreach ($stats as $itemId => $languageStats) {
            $parts = [];
            foreach ($languages as $language) {
                $code = (string) $language['lang_code'];
                $entry = $languageStats[$code];
                $label = strtoupper($code) . '-' . $entry['count'];
                $title = $entry['updated_at'] === null
                    ? $this->phrase('translation_missing', 'Translation is missing.')
                    : $this->phrase('translation_updated_at', 'Last updated:')
                        . ' ' . $this->formatTranslationDate($entry['updated_at']);

                if ($entry['outdated']) {
                    $title = $this->phrase(
                        'translation_outdated',
                        'Translation may be outdated.'
                    ) . ' ' . $title;
                    $parts[] = '<span class="tm-language-count is-outdated" title="'
                        . $this->escape($title) . '">⚠ ' . $this->escape($label) . '</span>';
                } else {
                    $parts[] = '<span class="tm-language-count" title="'
                        . $this->escape($title) . '">' . $this->escape($label) . '</span>';
                }
            }
            $result[$itemId] = implode(' <span class="tm-language-separator">|</span> ', $parts);
        }

        return $result;
    }

    private function formatTranslationDate(string $value): string
    {
        try {
            $date = new \DateTimeImmutable($value);
            $timezone = (string) \Core\Settings::get('default_timezone', 'UTC');
            if (!in_array($timezone, \DateTimeZone::listIdentifiers(), true)) {
                $timezone = 'UTC';
            }
            return $date
                ->setTimezone(new \DateTimeZone($timezone))
                ->format('d.m.Y H:i');
        } catch (\Throwable) {
            return $value;
        }
    }

    private function contentPhraseStats(array $contentTypes): array
    {
        if ($contentTypes === []) {
            return [];
        }

        $languages = $this->activeLanguages();
        $languageCodes = array_column($languages, 'lang_code');
        $stats = [];

        foreach ($contentTypes as $type) {
            $typeId = (int) $type['ct_id'];
            $stats[$typeId] = [
                'items' => 0,
                'counts' => array_fill_keys($languageCodes, 0),
            ];
        }

        $rows = \DB::query(
            <<<'SQL'
            WITH item_counts AS (
                SELECT ct_id, count(*) AS item_count
                FROM content_items
                GROUP BY ct_id
            ),
            phrase_counts AS (
                SELECT
                    i.ct_id,
                    t.lang_code,
                    count(*) AS phrase_count
                FROM content_items i
                JOIN content_types ct ON ct.ct_id=i.ct_id
                JOIN translations t ON t.entity_uuid=i.item_uuid
                JOIN languages l
                  ON l.lang_code=t.lang_code
                 AND l.is_active=true
                CROSS JOIN LATERAL jsonb_each(
                    COALESCE(t.translated_data, '{}'::jsonb)
                ) AS field(field_name, field_value)
                WHERE COALESCE(ct.schema->'fields', '{}'::jsonb) ? field.field_name
                  AND EXISTS (
                      SELECT 1
                      FROM fields definition
                      WHERE definition.system_name=field.field_name
                        AND COALESCE(
                            (definition.field_settings->>'translatable')::boolean,
                            false
                        )
                  )
                  AND jsonb_typeof(field.field_value)='string'
                  AND btrim(field.field_value #>> '{}') <> ''
                GROUP BY i.ct_id, t.lang_code
            )
            SELECT
                item_counts.ct_id,
                item_counts.item_count,
                phrase_counts.lang_code,
                COALESCE(phrase_counts.phrase_count, 0) AS phrase_count
            FROM item_counts
            LEFT JOIN phrase_counts USING(ct_id)
            SQL
        );

        while ($row = \DB::fetchRow($rows)) {
            $typeId = (int) $row['ct_id'];
            if (!isset($stats[$typeId])) {
                continue;
            }

            $stats[$typeId]['items'] = (int) $row['item_count'];
            $language = $row['lang_code'];
            if (is_string($language) && isset($stats[$typeId]['counts'][$language])) {
                $stats[$typeId]['counts'][$language] = (int) $row['phrase_count'];
            }
        }

        $result = [];
        foreach ($stats as $typeId => $typeStats) {
            $parts = [];
            foreach ($languages as $language) {
                $code = (string) $language['lang_code'];
                $parts[] = strtoupper($code) . '-' . ($typeStats['counts'][$code] ?? 0);
            }
            $result[$typeId] = [
                'items' => $typeStats['items'],
                'phrases' => implode(' | ', $parts),
            ];
        }

        return $result;
    }

    private function dictionaryPhraseCounts(): string
    {
        $counts = [];
        foreach ($this->activeLanguages() as $language) {
            $code = (string) $language['lang_code'];
            $data = $this->loadExactTranslation(\Core\Translation::SYSTEM_ENTITY_UUID, $code);
            $count = 0;
            foreach ($data as $value) {
                if (is_string($value) && trim($value) !== '') {
                    $count++;
                }
            }
            $counts[] = strtoupper($code) . '-' . $count;
        }
        return implode(' | ', $counts);
    }

    /** @return list<string> */
    private function dictionaryAllKeys(): array
    {
        $keys = [];
        $rows = \DB::query(
            'SELECT translated_data FROM translations WHERE entity_uuid=$1',
            [\Core\Translation::SYSTEM_ENTITY_UUID]
        );
        while ($row = \DB::fetchRow($rows)) {
            $data = $this->decodeJson($row['translated_data'] ?? null);
            foreach ($data as $key => $value) {
                if (is_string($key) && is_string($value)) {
                    $keys[$key] = true;
                }
            }
        }
        $result = array_keys($keys);
        natcasesort($result);
        return array_values($result);
    }

    private function dictionaryKey(string $key): string
    {
        $key = trim($key);
        if (!preg_match('/^[a-z][a-z0-9_]*$/', $key)) {
            throw new \InvalidArgumentException(
                $this->phrase(
                    'dictionary_key_invalid',
                    'Phrase keys must use lowercase Latin letters, digits and underscores.'
                )
            );
        }
        return $key;
    }

    private function applyDictionaryPostedValues(
        array &$dictionary,
        array $posted,
        array $ignoredKeys = []
    ): void {
        foreach ($posted as $token => $value) {
            if (!is_string($token) || !is_scalar($value)) {
                continue;
            }
            $path = $this->pathFromToken($token);
            if (count($path) !== 1 || !is_string($path[0]) || $path[0] === '') {
                continue;
            }
            $key = $path[0];
            if (in_array($key, $ignoredKeys, true)) {
                continue;
            }
            $value = (string) $value;
            if ($value === '') {
                unset($dictionary[$key]);
            } else {
                $dictionary[$key] = $value;
            }
        }
    }

    private function dictionaryPostedKeys(array $posted): array
    {
        $keys = [];
        foreach ($posted as $token => $value) {
            if (!is_string($token) || !$value) {
                continue;
            }
            $path = $this->pathFromToken($token);
            if (count($path) === 1 && is_string($path[0]) && $path[0] !== '') {
                $keys[$path[0]] = true;
            }
        }
        return array_keys($keys);
    }

    private function deleteDictionaryKeys(array $keys): void
    {
        $rows = \DB::query(
            'SELECT lang_code, translated_data FROM translations WHERE entity_uuid=$1',
            [\Core\Translation::SYSTEM_ENTITY_UUID]
        );
        while ($row = \DB::fetchRow($rows)) {
            $language = (string) $row['lang_code'];
            $data = $this->decodeJson($row['translated_data'] ?? null);
            $changed = false;
            foreach ($keys as $key) {
                if (array_key_exists($key, $data)) {
                    unset($data[$key]);
                    $changed = true;
                }
            }
            if ($changed) {
                $this->saveExactTranslation(\Core\Translation::SYSTEM_ENTITY_UUID, $language, $data);
            }
        }
    }

    private function systemPhraseCounts(string $entityType, array $entities): array
    {
        if ($entities === []) {
            return [];
        }

        $config = $this->systemEntityConfig($entityType);
        $languages = $this->activeLanguages();
        $counts = [];

        foreach ($entities as $entity) {
            $uuid = $entity['entity_uuid'];
            $counts[$uuid] = array_fill_keys(
                array_column($languages, 'lang_code'),
                0
            );
        }

        $rows = \DB::query(
            'SELECT e.uuid AS entity_uuid, t.lang_code, t.translated_data
             FROM ' . $config['table'] . ' e
             JOIN translations t ON t.entity_uuid=e.uuid
             JOIN languages l ON l.lang_code=t.lang_code AND l.is_active=true'
        );

        while ($row = \DB::fetchRow($rows)) {
            $uuid = (string) $row['entity_uuid'];
            $language = (string) $row['lang_code'];
            if (!isset($counts[$uuid][$language])) {
                continue;
            }

            $translation = $this->decodeJson($row['translated_data'] ?? null);
            $counts[$uuid][$language] = count(array_filter(
                $this->flattenStrings($translation),
                static fn(array $field): bool => trim((string) $field['value']) !== ''
            ));
        }

        $result = [];
        foreach ($counts as $uuid => $languageCounts) {
            $parts = [];
            foreach ($languages as $language) {
                $code = (string) $language['lang_code'];
                $parts[] = strtoupper($code) . '-' . ($languageCounts[$code] ?? 0);
            }
            $result[$uuid] = implode(' | ', $parts);
        }

        return $result;
    }

    private function activeLanguages(): array
    {
        $rows = \DB::query(
            'SELECT lang_code, lang_name FROM languages WHERE is_active=true ORDER BY lang_name'
        );
        return \DB::fetchAll($rows);
    }

    private function language(string $language): string
    {
        $language = trim($language);
        foreach ($this->activeLanguages() as $row) {
            if ($row['lang_code'] === $language) {
                return $language;
            }
        }
        throw new \InvalidArgumentException('Language is not active.');
    }

    private function defaultSourceLanguage(): string
    {
        $active = array_column($this->activeLanguages(), 'lang_code');
        if (defined('LANG') && in_array(LANG, $active, true)) {
            return LANG;
        }
        if (in_array('en', $active, true)) {
            return 'en';
        }
        if ($active === []) {
            throw new \RuntimeException('No active languages are configured.');
        }
        return (string) $active[0];
    }

    private function defaultTargetLanguage(string $source): string
    {
        foreach ($this->activeLanguages() as $row) {
            if ($row['lang_code'] !== $source) {
                return (string) $row['lang_code'];
            }
        }
        return $source;
    }

    private function assertDifferentLanguages(string $source, string $target): void
    {
        if ($source === $target) {
            throw new \InvalidArgumentException('Source and target languages must be different.');
        }
    }

    private function languageSelect(string $name, string $selected): string
    {
        $html = '<select class="admin-input" name="' . $this->escape($this->prefix . '-' . $name) . '">';
        foreach ($this->activeLanguages() as $row) {
            $value = (string) $row['lang_code'];
            $html .= '<option value="' . $this->escape($value) . '"'
                . ($value === $selected ? ' selected' : '') . '>'
                . $this->escape((string) $row['lang_name']) . ' (' . $this->escape($value) . ')</option>';
        }
        return $html . '</select>';
    }

    private function providerSelect(
        array $providerData,
        string $selected,
        string $name = 'provider'
    ): string {
        $html = '<select class="admin-input" name="' . $this->escape($this->prefix . '-' . $name) . '">';
        foreach ($providerData['providers'] ?? [] as $key => $provider) {
            $configured = !empty($provider['configured']);
            $html .= '<option value="' . $this->escape((string) $key) . '"'
                . ((string) $key === $selected ? ' selected' : '')
                . ($configured ? '' : ' disabled') . '>'
                . $this->escape((string) ($provider['title'] ?? $key))
                . ($configured ? '' : ' — not configured') . '</option>';
        }
        return $html . '</select>';
    }

    private function pagination(): Pagination
    {
        $plugin = $this->pagination ??= $this->plugins->get('Pagination');
        if (!$plugin instanceof Pagination) {
            throw new \RuntimeException('Pagination plugin is not available.');
        }
        return $plugin;
    }

    private function processor(): TextProcessor
    {
        $plugin = $this->processor ??= $this->plugins->get('TextProcessor');
        if (!$plugin instanceof TextProcessor) {
            throw new \RuntimeException('TextProcessor plugin is not available.');
        }
        return $plugin;
    }

    private function forms(): Forms
    {
        $plugin = $this->forms ??= $this->plugins->get('Forms');
        if (!$plugin instanceof Forms) {
            throw new \RuntimeException('Forms plugin is not available.');
        }
        return $plugin;
    }

    private function url(string $action, array $params = []): string
    {
        $url = '/' . PAGE_SLUG . '/' . $this->prefix . '-action/' . $action;
        foreach ($params as $key => $value) {
            $url .= '/' . $this->prefix . '-' . $key . '/' . rawurlencode((string) $value);
        }
        return $url;
    }

    private function uuid(string $uuid): string
    {
        if (!preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i', $uuid)) {
            throw new \InvalidArgumentException('Invalid UUID.');
        }
        return $uuid;
    }

    private function phrase(string $key, string $fallback): string
    {
        return (string) ($this->phrases[$key] ?? $fallback);
    }

    private function decodeJson(mixed $value): array
    {
        if (is_array($value)) return $value;
        if (!is_string($value) || $value === '') return [];
        $decoded = json_decode($value, true);
        return is_array($decoded) ? $decoded : [];
    }

    private function json(array $data): string
    {
        return json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '{}';
    }

    private function looksLikeHtml(string $value): bool
    {
        return preg_match('/<\/?[a-z][^>]*>/i', $value) === 1;
    }

    private function notice(string $message, string $kind = 'success'): string
    {
        return '<div class="admin-notice admin-notice-' . $this->escape($kind) . '">'
            . $this->escape($message) . '</div>';
    }

    private function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }
}
