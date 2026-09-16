<?php

/**
 * KamiCore
 *
 * SPDX-License-Identifier: Apache-2.0
 *
 * @see https://kamicore.org
 */

declare(strict_types=1);

namespace Plugins\Breadcrumbs;

if (!defined('IN_KAMI')) die();

class Breadcrumbs extends \Core\BasePlugin
{
    public function renderPlaceholder(array $instanceParams = []): string
    {
        $this->addCss('/plugins/Breadcrumbs/assets/breadcrumbs.css');
        return $this->render('breadcrumb-container', ['content' => '{{breadcrumb}}']);
    }

    public function getBreadcrumbItems($layoutParams): array
    {
        if (!isset($this->layoutParams['breadcrumb_items'])) {
            $this->layoutParams['breadcrumb_items'] = array_merge(
                $this->buildPageItems(),
                $this->buildContentItems($layoutParams)
            );
        }

        return $this->layoutParams['breadcrumb_items'];
    }

    private function buildPageItems(): array
    {
        $items = [
            [
                'title' => (string)($this->phrases['home'] ?? 'Home'),
                'link' => '/',
            ],
        ];

		$pages = \DB::query("WITH RECURSIVE page_tree AS (
    SELECT
        page_id,
        parent_id,
        domain_id,
        system_name,
        page_slug,
        uuid,
        0 AS depth,
        ARRAY[page_id] AS path
    FROM pages
    WHERE page_id = $1
      AND domain_id = $2

    UNION ALL

    SELECT
        p.page_id,
        p.parent_id,
        p.domain_id,
        p.system_name,
        p.page_slug,
        p.uuid,
        pt.depth + 1,
        pt.path || p.page_id
    FROM pages p
    JOIN page_tree pt
      ON p.page_id = pt.parent_id
     AND p.domain_id = pt.domain_id
    WHERE NOT p.page_id = ANY(pt.path)
)
SELECT
    page_id,
    parent_id,
    domain_id,
    system_name,
    page_slug,
    uuid
FROM page_tree
ORDER BY depth DESC;", [PAGE_ID, DOMAIN_ID]);

		while ($page = \DB::fetchRow($pages)) {
			$page_title = \Core\Translation::get($page['uuid'])['title'];
			$items[] = [
                'title' => $page_title,
                'link' => $page['page_slug'],
            ];
		}
        return $items;
    }

    private function buildContentItems(array $layoutParams): array
    {
        // Prefer breadcrumb items prepared by the content plugin and fall back to the routed item.
		$routedItemId = \Core\Request::routedItemId();
		if((!isset($layoutParams['breadcrumb_tail']) || empty($layoutParams['breadcrumb_tail'])) && $routedItemId) {
			$routedItem = \Core\Content::getItem($routedItemId);
			$items = [
				[
					'title' => $routedItem['title']
				]
			];
		} else {
			$items = $layoutParams['breadcrumb_tail'] ?? [];
		}

        return $items;
    }

    public function finalize(array $layoutParams): void
    {
        $items = $layoutParams['breadcrumb_items'] ?? $this->getBreadcrumbItems($layoutParams);
        if (!is_array($items)) {
            $items = [];
        }

        $parts = [];
        foreach ($items as $item) {
            if (!is_array($item)) {
                continue;
            }

            $title = \Core\Html::escape((string)($item['title'] ?? ''));
            $link = \Core\Html::escape((string)($item['link'] ?? ''));

            if ($title === '') {
                continue;
            }

            $parts[] = $link !== ''
                ? '<a href="' . $link . '">' . $title . '</a>'
                : $title;
        }

        $separator = "<span>" . \Core\Html::escape((string)($this->settings['separator'] ?? '❯')) . "</span>";

        $this->layoutParams['breadcrumb_items'] = $items;
        $this->layoutParams['breadcrumb'] = implode($separator, $parts);
    }
}
