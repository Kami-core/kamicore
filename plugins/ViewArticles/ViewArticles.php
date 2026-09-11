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

		return $this->render("article-page", $item['data']);
	}
	public function list(array $instance_params = []): string {
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

		$articles = [];
		foreach ($result['ids'] as $id) {
			$articles[] = [
				'template' => 'article-card',
				'params' => $this->articleCardParams((int)$id),
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
						? $this->escape($this->phrases['all'] ?? 'All')
						: (string)$value,
					'selected' => $value === $selected ? ' selected' : '',
				],
			];
		}

		return $this->render('items-per-page-selector', [
			'action_url' => $this->escape(\Core\Request::path()),
			'select_name' => $this->escape(
				\Core\Request::buildKey('items_per_page', $this->prefix)
			),
			'label' => $this->escape($this->phrases['items_per_page'] ?? 'Items per page'),
			'options' => $renderedOptions,
		]);
	}

	private function categoryIds(mixed $value): array {
		$ids = array_map('intval', (array)$value);
		$ids = array_filter($ids, static fn(int $id): bool => $id > 0);
		return array_values(array_unique($ids));
	}

	private function articleCardParams(int $id): array {
		$article = \Core\Content::getItem($id);
		if ($article === []) {
			return [];
		}

		$data = is_array($article['data'] ?? null) ? $article['data'] : [];
		$publishedAt = trim((string)($data['published_at'] ?? ''));
		$preview = trim((string)($data['article_preview'] ?? ''));

		$published = $publishedAt !== ''
			? $this->render('article-card-date', [
				'datetime' => $this->escape($publishedAt),
				'date' => $this->escape($this->formatter()->dateTime($publishedAt)),
			])
			: '';
		$previewHtml = $preview !== ''
			? $this->render('article-card-preview', [
				'url' => $this->escape($this->articleUrl($article)),
				'preview' => $this->escape($preview),
				'alt' => $this->escape((string)($article['title'] ?? '')),
			])
			: '';

		return [
			'url' => $this->escape($this->articleUrl($article)),
			'title' => $this->escape((string)($article['title'] ?? '')),
			'summary' => $this->escape((string)($data['summary'] ?? '')),
			'published_at' => $published,
			'preview' => $previewHtml,
		];
	}

	private function articleUrl(array $article): string {
		$data = is_array($article['data'] ?? null) ? $article['data'] : [];
		$categories = $this->categoryIds($data['article_categories'] ?? null);
		$primaryCategory = (int)($data['article_primary_category'] ?? 0);
		if ($primaryCategory < 1) {
			$primaryCategory = $categories[0] ?? 0;
		}

		$pageSlug = '';
		if ($primaryCategory > 0) {
			$category = \Core\Content::getItem($primaryCategory);
			$pageId = (int)($category['data']['category_page'] ?? 0);
			if ($pageId > 0) {
				$pageSlug = (string)(getDomainPages(DOMAIN_ID)[$pageId] ?? '');
			}
		}

		$slug = rawurlencode((string)($article['item_slug'] ?? ''));
		if ($pageSlug !== '' && $slug !== '') {
			$languagePrefix = LANG === (DOMAIN_CONFIG['default_language'] ?? LANG)
				? ''
				: '/' . rawurlencode(LANG);
			return $languagePrefix . '/' . trim($pageSlug, '/') . '/' . $slug;
		}

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

	private function escape(string $value): string {
		return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
	}

	private function pagination(): \Plugins\Pagination\Pagination {
		$plugin = $this->plugins->get('Pagination');
		if (!$plugin instanceof \Plugins\Pagination\Pagination) {
			throw new \RuntimeException('Pagination plugin is not available.');
		}
		return $plugin;
	}

}

