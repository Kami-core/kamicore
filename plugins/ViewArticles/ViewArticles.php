<?php

/**
 * KamiCore
 *
 * SPDX-License-Identifier: Apache-2.0
 *
 * @see https://kamicore.org
 */

namespace Plugins\ViewArticles;

if(!IN_KAMI) die();

class ViewArticles extends \Core\BasePlugin {

	public function view(array $instance_params = []) {
		$this->addCss('/plugins/ViewArticles/assets/view-articles.css');
		$item = $this->routedItem();

		if($item && (
			(is_array($instance_params['articles_category_ids']) && count($instance_params['articles_category_ids']) && !array_intersect($item['data']['article_categories'], $instance_params['articles_category_ids']))
			|| $item['content_type_name']!='article')
		) {
			unset($item);
			$this->declineRoutedItem();
		}

		if(!$item) return $this->list($instance_params);

		$item['data']['title'] = $item['title'];
		$item['data']['published_at'] = $this->plugins->get('Formatter')->dateTime($item['data']['published_at']);

		$preview = trim((string)($item['data']['article_image'] ?? ''));

		$previewHtml = $preview !== ''
			? $this->render('article-img', [
				'preview' => \Core\Html::escape($preview),
				'alt' => \Core\Html::escape((string)($item['title'] ?? '')),
			])
			: '';

		$item['data']['preview'] = $previewHtml;

		return $this->render("article-page", $item['data']);
	}

	public function list(array $instance_params = []): string {
		$this->addCss('/plugins/ViewArticles/assets/view-articles.css');
		$itemsPerPageOptions = $this->itemsPerPageOptions();
		$hasItemsPerPageOverride = array_key_exists('items_per_page', $instance_params)
			&& $instance_params['items_per_page'] !== null
			&& $instance_params['items_per_page'] !== '';

		if ($hasItemsPerPageOverride) {
			$itemsPerPage = max(0, (int)$instance_params['items_per_page']);
		} else {
			$itemsPerPage = $this->selectedItemsPerPage($itemsPerPageOptions);
		}

		$showPagination = (bool)($instance_params['show_pagination'] ?? true);
		$paginationEnabled = $showPagination && $itemsPerPage > 0;
		$page = $paginationEnabled
			? \Core\Request::page($this->prefix)
			: 1;
		$offset = $paginationEnabled
			? ($page - 1) * $itemsPerPage
			: 0;

		$categoryIds = $this->categoryIds($instance_params['articles_category_ids'] ?? null);
		$filter = $categoryIds !== []
			? [[
				'field' => 'article_categories',
				'mode' => 'in',
				'values' => $categoryIds,
			]]
			: null;
		$order = [[
			'field' => 'published_at',
			'direction' => 'desc',
		]];

		$result = \Core\Content::search(
			['article'],
			'substr',
			null,
			$filter,
			$order,
			$offset,
			$itemsPerPage
		);

		$total = (int)$result['totals'];
		if ($paginationEnabled && $total > 0) {
			$lastPage = max(1, (int)ceil($total / $itemsPerPage));
			if ($page > $lastPage) {
				$page = $lastPage;
				$offset = ($page - 1) * $itemsPerPage;
				$result = \Core\Content::search(
					['article'],
					'substr',
					null,
					$filter,
					$order,
					$offset,
					$itemsPerPage
				);
			}
		}

		$articleItems = [];
		foreach ($result['ids'] as $id) {
			$article = \Core\Content::getItem((int)$id);
			if ($article !== []) {
				$articleItems[] = $article;
			}
		}

		\Core\CanonicalUrl::prime($articleItems, DOMAIN_ID, $this);

		$articles = [];
		$this->layoutParams['listItems'] = [];
		foreach ($articleItems as $article) {
			$url = $this->articleUrl($article);
			$this->layoutParams['listItems'][] = [
				'name' => $article['title'],
				'url' => $url,
			];
			$articles[] = [
				'template' => 'article-card',
				'params' => $this->articleCardParams($article, $url),
			];
		}
		if ($articles === []) {
			$articles[] = [
				'template' => 'articles-empty',
				'params' => [],
			];
		}

		$selector = '';
		if (!$hasItemsPerPageOverride && !empty($this->settings['items_count_selector'])) {
			$selector = $this->itemsPerPageSelector($itemsPerPageOptions, $itemsPerPage);
		}

		$pagination = '';
		if ($paginationEnabled) {
			$pagination = $this->pagination()->renderPagination(
				page: $page,
				perPage: $itemsPerPage,
				total: $total,
				base_url: \Core\Request::path(),
				options: [
					'page_param' => \Core\Request::buildKey('page', $this->prefix),
				]
			);
		}

		return $this->render('articles-list', [
			'items_per_page_selector' => $selector,
			'articles' => $articles,
			'pagination' => $pagination,
		]);
	}

	private function selectedItemsPerPage(array $options): int {
		$default = max(0, (int)($this->settings['default_count'] ?? 20));
		$selectorEnabled = !empty($this->settings['items_count_selector']);

		if ($selectorEnabled) {
			$requested = \Core\Request::param('items_per_page', $this->prefix, null);
			if ($requested !== null && $requested !== '' && is_numeric($requested)) {
				$requested = max(0, (int)$requested);
				if (in_array($requested, $options, true)) {
					\Core\Response::addCookie(
						'articles_items_per_page',
						(string)$requested,
						time() + 31536000
					);
					return $requested;
				}
			}
		}

		$cookie = \Core\Request::cookie()['articles_items_per_page'] ?? null;
		if ($cookie !== null && is_numeric($cookie)) {
			$cookie = max(0, (int)$cookie);
			if (in_array($cookie, $options, true)) {
				return $cookie;
			}
		}

		return $default;
	}

	private function itemsPerPageOptions(): array {
		$options = is_array($this->settings['items_count_values'] ?? null)
			? $this->settings['items_count_values']
			: [];

		$options = array_map('intval', $options);
		$options = array_filter($options, static fn(int $value): bool => $value >= 0);
		return array_values(array_unique($options));
	}

	private function itemsPerPageSelector(array $options, int $selected): string {
		$renderedOptions = [];
		foreach ($options as $value) {
			$renderedOptions[] = [
				'template' => 'items-per-page-option',
				'params' => [
					'value' => (string)$value,
					'label' => $value === 0
						? \Core\Html::escape($this->phrases['all'] ?? 'All')
						: (string)$value,
					'selected' => $value === $selected ? ' selected' : '',
				],
			];
		}

		return $this->render('items-per-page-selector', [
			'action_url' => \Core\Html::escape(\Core\Request::path()),
			'select_name' => \Core\Html::escape(
				\Core\Request::buildKey('items_per_page', $this->prefix)
			),
			'label' => \Core\Html::escape($this->phrases['items_per_page'] ?? 'Items per page'),
			'options' => $renderedOptions,
		]);
	}

	private function categoryIds(mixed $value): array {
		$ids = array_map('intval', (array)$value);
		$ids = array_filter($ids, static fn(int $id): bool => $id > 0);
		return array_values(array_unique($ids));
	}

	private function articleCardParams(array $article, string $url): array {
		$data = is_array($article['data'] ?? null) ? $article['data'] : [];
		$publishedAt = trim((string)($data['published_at'] ?? ''));
		$preview = trim((string)($data['article_image'] ?? ''));

		$published = $publishedAt !== ''
			? $this->render('article-card-date', [
				'datetime' => \Core\Html::escape($publishedAt),
				'date' => \Core\Html::escape($this->formatter()->dateTime($publishedAt)),
			])
			: '';
		$previewHtml = $preview !== ''
			? $this->render('article-card-preview', [
				'url' => \Core\Html::escape($url),
				'preview' => \Core\Html::escape($preview),
				'alt' => \Core\Html::escape((string)($article['title'] ?? '')),
			])
			: '';

		return [
			'url' => \Core\Html::escape($url),
			'title' => \Core\Html::escape((string)($article['title'] ?? '')),
			'summary' => \Core\Html::escape((string)($data['summary'] ?? '')),
			'published_at' => $published,
			'preview' => $previewHtml,
		];
	}

	public function canonicalUrl(array $item, string $langCode, int $domainId, int $groupId): ?string {
		return \Core\CanonicalUrl::itemPath($item, $langCode, $domainId, $this);
	}

	public function resolveCanonicalPageIds(array $items, int $domainId): array {
		$categoryByItem = [];
		$categoryIds = [];

		foreach ($items as $item) {
			$itemId = (int)($item['item_id'] ?? 0);
			if ($itemId < 1) continue;

			$data = is_array($item['data'] ?? null)
				? $item['data']
				: \Core\Utils\JsonTool::decodeArray($item['common_data'] ?? null);
			$categoryId = (int)($data['article_primary_category'] ?? 0);
			$categoryByItem[$itemId] = $categoryId;
			if ($categoryId > 0) $categoryIds[$categoryId] = $categoryId;
		}

		$categoryPages = [];
		if ($categoryIds !== []) {
			$rows = \DB::query(
				"SELECT c.item_id AS category_id, p.page_id
				 FROM content_items c
				 JOIN pages p
				   ON p.page_id=CASE
				       WHEN (c.common_data->>'category_page') ~ '^[0-9]+$'
				       THEN (c.common_data->>'category_page')::int
				       ELSE NULL
				   END
				  AND p.domain_id=$1
				 WHERE c.item_id=ANY($2::bigint[])",
				[$domainId, \DB::prepareIdArray(array_values($categoryIds))]
			);
			while ($row = \DB::fetchRow($rows)) {
				$categoryPages[(int)$row['category_id']] = (int)$row['page_id'];
			}
		}

		$result = [];
		foreach ($categoryByItem as $itemId => $categoryId) {
			$result[$itemId] = [
				'page_id' => $categoryPages[$categoryId] ?? null,
				'dependency_item_id' => $categoryId > 0 ? $categoryId : null,
			];
		}

		return $result;
	}

	public function invalidateCanonicals(int $itemId, int $contentTypeId): void {
		\Core\CanonicalUrl::deleteItemAndDependents($itemId);
	}

	private function articleUrl(array $article): string {
		$groupId = defined('USERGROUP_ID')
			? (int)USERGROUP_ID
			: (int)(GLOBAL_SETTINGS['usergroup_guest'] ?? 0);
		$canonical = $this->canonicalUrl($article, LANG, DOMAIN_ID, $groupId);
		if ($canonical !== null) return $canonical;

		$slug = rawurlencode((string)($article['item_slug'] ?? ''));
		$path = rtrim(\Core\Request::path(), '/');
		return ($path !== '' ? $path : '') . ($slug !== '' ? '/' . $slug : '');
	}

	private function formatter(): \Plugins\Formatter\Formatter {
		$plugin = $this->plugins->get('Formatter');
		if (!$plugin instanceof \Plugins\Formatter\Formatter) {
			throw new \RuntimeException('Formatter plugin is not available.');
		}
		return $plugin;
	}

private function pagination(): \Plugins\Pagination\Pagination {
		$plugin = $this->plugins->get('Pagination');
		if (!$plugin instanceof \Plugins\Pagination\Pagination) {
			throw new \RuntimeException('Pagination plugin is not available.');
		}
		return $plugin;
	}

}

