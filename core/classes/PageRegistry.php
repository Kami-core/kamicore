<?php

/**
 * KamiCore
 *
 * SPDX-License-Identifier: Apache-2.0
 *
 * @see https://kamicore.org
 */

declare(strict_types=1);

namespace Core;

if (!defined('IN_KAMI')) die();

final class PageRegistry
{
    /** @return array<int|string, int|string> */
    public static function forDomain(?int $domainId = null): array
    {
        $domainId ??= DOMAIN_ID;
        $pages = \Cache::get('d_' . $domainId . ':pages');

        if (!is_array($pages) || $pages === []) {
            $pages = [];
            $result = \DB::query(
                'SELECT page_id, page_slug FROM pages WHERE domain_id=$1',
                [$domainId]
            );

            while ($page = \DB::fetchRow($result)) {
                $pageId = (int) $page['page_id'];
                $pageSlug = (string) $page['page_slug'];
                $pages[$pageSlug] = $pageId;
                $pages[$pageId] = $pageSlug;
            }

            \Cache::set('d_' . $domainId . ':pages', $pages);
        }

        return $pages;
    }
}
