<?php

/**
 * KamiCore
 *
 * SPDX-License-Identifier: Apache-2.0
 *
 * @see https://kamicore.org
 */

declare(strict_types=1);

namespace Plugins\Search;

if (!defined('IN_KAMI')) die();

final class Search extends \Core\BasePlugin
{
    private const RESULTS_PER_PAGE = 20;
    private const AUTOCOMPLETE_LIMIT = 10;

    public function showForm(array $instanceParams = []): string
    {
        $contentTypes = $this->effectiveContentTypeIds($instanceParams);
        $forms = $this->forms();
        $forms->registerEntityFieldAssets();
        $this->addCss('/plugins/Search/assets/search.css');

        $query = trim((string)$this->param('q', ''));
        $autocompleteUrl = '/ajax/Search/autocomplete';
        if ($contentTypes !== []) {
            $autocompleteUrl .= '?' . http_build_query(
                ['content_types' => implode(',', $contentTypes)],
                '',
                '&',
                PHP_QUERY_RFC3986
            );
        }

        return $this->render('search-form', [
            'action' => \Core\Html::escape($this->resultsPageUrl($instanceParams)),
            'autocomplete_url' => \Core\Html::escape($autocompleteUrl),
            'placeholder' => \Core\Html::escape($this->phrases['placeholder'] ?? 'Search...'),
            'button_label' => \Core\Html::escape($this->phrases['search'] ?? 'Search'),
            'query_option' => $query !== ''
                ? '<option value="' . \Core\Html::escape($query) . '" selected>'
                    . \Core\Html::escape($query)
                    . '</option>'
                : '',
        ]);
    }

    public function showResults(array $instanceParams = []): string
    {
        $this->addCss('/plugins/Search/assets/search.css');

        $query = trim((string)$this->param('q', ''));
        $contentTypes = $this->effectiveContentTypeIds($instanceParams);
        $resultsHtml = '';
        $paginationHtml = '';
        $total = 0;

        if ($query !== '' && $contentTypes !== []) {
            $page = \Core\Request::page($this->prefix);
            $offset = ($page - 1) * self::RESULTS_PER_PAGE;
            $result = \Core\Content::search(
                contentTypes: $contentTypes,
                mode: 'fulltext',
                query: $query,
                offset: $offset,
                limit: self::RESULTS_PER_PAGE,
                lang: LANG
            );

            $total = (int)$result['totals'];
            $lastPage = max(1, (int)ceil($total / self::RESULTS_PER_PAGE));
            if ($total > 0 && $page > $lastPage) {
                $page = $lastPage;
                $offset = ($page - 1) * self::RESULTS_PER_PAGE;
                $result = \Core\Content::search(
                    contentTypes: $contentTypes,
                    mode: 'fulltext',
                    query: $query,
                    offset: $offset,
                    limit: self::RESULTS_PER_PAGE,
                    lang: LANG
                );
            }

            $items = [];
            foreach ($result['ids'] as $itemId) {
                $item = \Core\Content::getItem((int)$itemId, LANG);
                if ($item !== []) {
                    $items[] = $item;
                }
            }
            $resultsHtml = $this->viewContent()->renderListItems($items, $contentTypes);

            if ($total > 0) {
                $paginationHtml = $this->pagination()->renderPagination(
                    page: $page,
                    perPage: self::RESULTS_PER_PAGE,
                    total: $total,
                    base_url: $this->resultsBaseUrl($query),
                    options: [
                        'page_param' => \Core\Request::buildKey('page', $this->prefix),
                    ]
                );
            }
        }

        if ($query !== '' && $resultsHtml === '') {
            $resultsHtml = $this->render('search-empty', [
                'message' => \Core\Html::escape($this->phrases['no_results'] ?? 'No results found.'),
            ]);
        }

        $title = $query === ''
            ? ($this->phrases['search_results'] ?? 'Search results')
            : sprintf(
                $this->phrases['results_for'] ?? 'Search results for “%s”',
                \Core\Html::escape($query)
            );

        return $this->render('search-results', [
            'search_title' => $title,
            'results' => $resultsHtml,
            'pagination' => $paginationHtml,
            'total' => (string)$total,
        ]);
    }

    public function autocomplete(array $data = []): string
    {
        $query = trim((string)($data['q'] ?? ''));
        if ($query === '') {
            return $this->json([]);
        }

        $contentTypes = $this->effectiveContentTypeIds([
            'content_types' => $data['content_types'] ?? null,
        ]);
        if ($contentTypes === []) {
            return $this->json([]);
        }

        $itemIds = \Core\Content::findByTitle(
            title: $query,
            contentTypes: $contentTypes,
            exact: false,
            limit: self::AUTOCOMPLETE_LIMIT,
            lang: LANG
        );

        $items = [];
        $seen = [];
        foreach ($itemIds as $itemId) {
            $item = \Core\Content::getItem((int)$itemId, LANG);
            if ($item === []) {
                continue;
            }
            $title = trim((string)($item['title'] ?? ''));
            if ($title === '' || isset($seen[$title])) {
                continue;
            }
            $seen[$title] = true;

            $contentType = \Core\Content::getContentType((int)$item['ct_id'], LANG);
            $items[] = [
                'value' => $title,
                'label' => $title,
                'subtitle' => (string)($contentType['title'] ?? $contentType['system_name'] ?? ''),
            ];
        }

        return $this->json($items);
    }

    /** @return list<int> */
    private function effectiveContentTypeIds(array $instanceParams): array
    {
        $selected = $instanceParams['content_types'] ?? null;
        if ($selected === null || $selected === '' || $selected === []) {
            $selected = $this->settings['content_types'] ?? null;
        }

        return $this->viewContent()->getRenderableContentTypeIds($selected);
    }

    private function resultsPageUrl(array $instanceParams): string
    {
        $pageId = $instanceParams['page_id'] ?? null;
        if ($pageId === null || $pageId === '') {
            $pageId = $this->settings['page_id'] ?? null;
        }
        $pageId = is_numeric($pageId) ? (int)$pageId : 0;

        $pages = \Core\PageRegistry::forDomain();
        if ($pageId > 0 && isset($pages[$pageId]) && \Core\User::canPage($pageId)) {
            return $this->localizedPagePath((string)$pages[$pageId]);
        }

        return \Core\Request::path();
    }

    private function resultsBaseUrl(string $query): string
    {
        $url = \Core\Request::path();
        return $url . '?' . http_build_query(['q' => $query], '', '&', PHP_QUERY_RFC3986);
    }

    private function localizedPagePath(string $pageSlug): string
    {
        $defaultLanguage = (string)(DOMAIN_CONFIG['default_language'] ?? LANG);
        return LANG === $defaultLanguage
            ? \Core\Url::path($pageSlug)
            : \Core\Url::path(LANG, $pageSlug);
    }

    private function forms(): \Plugins\Forms\Forms
    {
        $plugin = $this->plugins->get('Forms');
        if (!$plugin instanceof \Plugins\Forms\Forms) {
            throw new \RuntimeException('Forms plugin is not available.');
        }
        return $plugin;
    }

    private function viewContent(): \Plugins\ViewContent\ViewContent
    {
        $plugin = $this->plugins->get('ViewContent');
        if (!$plugin instanceof \Plugins\ViewContent\ViewContent) {
            throw new \RuntimeException('ViewContent plugin is not available.');
        }
        return $plugin;
    }

    private function pagination(): \Plugins\Pagination\Pagination
    {
        $plugin = $this->plugins->get('Pagination');
        if (!$plugin instanceof \Plugins\Pagination\Pagination) {
            throw new \RuntimeException('Pagination plugin is not available.');
        }
        return $plugin;
    }

    private function json(array $data): string
    {
        \Core\Response::addHeader('Content-Type: application/json; charset=utf-8');
        return \Core\Utils\JsonTool::encode($data, false);
    }
}
