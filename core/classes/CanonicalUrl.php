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

final class CanonicalUrl
{
    /** @var array<int, array<int, int>> 0 means a resolved item without a canonical page. */
    private static array $pageIds = [];

    /** @var array<int, array<int, string|false>> */
    private static array $pageSlugs = [];

    /** @var array<int, array<string, array<int, string|null>>> */
    private static array $pagePaths = [];

    /** @var array<int, string> */
    private static array $defaultLanguages = [];

    public static function itemPath(
        array $item,
        string $langCode,
        int $domainId,
        BasePlugin $viewer
    ): ?string {
        $slug = trim((string)($item['item_slug'] ?? ''));
        if ($slug === '') {
            return null;
        }

        $pageId = self::pageId($item, $domainId, $viewer);
        if ($pageId === null) {
            return null;
        }

        $base = self::pagePath($pageId, $domainId, $langCode);
        if ($base === null) {
            return null;
        }

        return rtrim($base, '/') . '/' . rawurlencode($slug);
    }

    public static function pageId(array $item, int $domainId, BasePlugin $viewer): ?int
    {
        $itemId = (int)($item['item_id'] ?? 0);
        if ($itemId < 1 || $domainId < 1) {
            return null;
        }

        if (!isset(self::$pageIds[$domainId][$itemId])) {
            self::prime([$item], $domainId, $viewer);
        }

        $pageId = self::$pageIds[$domainId][$itemId] ?? 0;
        return $pageId > 0 ? $pageId : null;
    }

    public static function prime(array $items, int $domainId, BasePlugin $viewer): void
    {
        if ($domainId < 1 || $items === []) {
            return;
        }

        $byId = [];
        foreach ($items as $item) {
            if (!is_array($item)) {
                continue;
            }
            $itemId = (int)($item['item_id'] ?? 0);
            if ($itemId > 0) {
                $byId[$itemId] = $item;
            }
        }
        if ($byId === []) {
            return;
        }

        $missing = [];
        foreach ($byId as $itemId => $_item) {
            if (!isset(self::$pageIds[$domainId][$itemId])) {
                $missing[$itemId] = true;
            }
        }

        if ($missing !== []) {
            $keyToItem = [];
            foreach ($missing as $itemId => $_) {
                $keyToItem[self::cacheKey($domainId, $itemId)] = $itemId;
            }

            $cached = \Cache::getMany(array_keys($keyToItem));
            foreach ($cached as $key => $value) {
                $itemId = $keyToItem[$key] ?? 0;
                if ($itemId < 1 || !is_numeric($value)) {
                    continue;
                }
                $pageId = max(0, (int)$value);
                self::$pageIds[$domainId][$itemId] = $pageId;
                unset($missing[$itemId]);
            }
        }

        $cacheValues = [];

        if ($missing !== []) {
            $ids = array_keys($missing);
            $rows = \DB::query(
                'SELECT item_id, page_id
                 FROM content_canonical_pages
                 WHERE domain_id=$1
                   AND item_id=ANY($2::bigint[])',
                [$domainId, \DB::prepareIdArray($ids)]
            );

            while ($row = \DB::fetchRow($rows)) {
                $itemId = (int)$row['item_id'];
                $pageId = $row['page_id'] === null ? 0 : (int)$row['page_id'];
                self::$pageIds[$domainId][$itemId] = $pageId;
                $cacheValues[self::cacheKey($domainId, $itemId)] = $pageId;
                unset($missing[$itemId]);
            }
        }

        if ($missing !== []) {
            $resolveItems = [];
            foreach ($missing as $itemId => $_) {
                $resolveItems[] = $byId[$itemId];
            }

            $resolved = [];
            if (method_exists($viewer, 'resolveCanonicalPageIds')) {
                $value = $viewer->resolveCanonicalPageIds($resolveItems, $domainId);
                $resolved = is_array($value) ? $value : [];
            } elseif (method_exists($viewer, 'resolveCanonicalPageId')) {
                foreach ($resolveItems as $item) {
                    $itemId = (int)$item['item_id'];
                    $resolved[$itemId] = $viewer->resolveCanonicalPageId($item, $domainId);
                }
            }

            $insertRows = [];
            foreach ($missing as $itemId => $_) {
                $value = $resolved[$itemId] ?? null;
                $pageId = null;
                $dependencyItemId = null;

                if (is_array($value)) {
                    $pageId = isset($value['page_id']) && (int)$value['page_id'] > 0
                        ? (int)$value['page_id']
                        : null;
                    $dependencyItemId = isset($value['dependency_item_id'])
                        && (int)$value['dependency_item_id'] > 0
                            ? (int)$value['dependency_item_id']
                            : null;
                } elseif ($value !== null && (int)$value > 0) {
                    $pageId = (int)$value;
                }

                $insertRows[] = [
                    'domain_id' => $domainId,
                    'item_id' => $itemId,
                    'page_id' => $pageId,
                    'dependency_item_id' => $dependencyItemId,
                ];

                $cachedPageId = $pageId ?? 0;
                self::$pageIds[$domainId][$itemId] = $cachedPageId;
                $cacheValues[self::cacheKey($domainId, $itemId)] = $cachedPageId;
            }

            self::storeRows($insertRows);
        }

        if ($cacheValues !== []) {
            \Cache::setMany($cacheValues);
        }

        $pageIds = [];
        foreach ($byId as $itemId => $_item) {
            $pageId = self::$pageIds[$domainId][$itemId] ?? 0;
            if ($pageId > 0) {
                $pageIds[$pageId] = $pageId;
            }
        }
        self::primePageSlugs($domainId, array_values($pageIds));
    }

    public static function pagePath(int $pageId, int $domainId, string $langCode): ?string
    {
        if ($pageId < 1 || $domainId < 1) {
            return null;
        }

        if (array_key_exists($pageId, self::$pagePaths[$domainId][$langCode] ?? [])) {
            return self::$pagePaths[$domainId][$langCode][$pageId];
        }

        self::primePageSlugs($domainId, [$pageId]);
        $slug = self::$pageSlugs[$domainId][$pageId] ?? false;
        if ($slug === false) {
            return self::$pagePaths[$domainId][$langCode][$pageId] = null;
        }

        $defaultLanguage = self::defaultLanguage($domainId);
        $prefix = $langCode === $defaultLanguage ? '' : '/' . rawurlencode($langCode);

        $slug = trim($slug, '/');
        if ($slug === '') {
            return self::$pagePaths[$domainId][$langCode][$pageId] = $prefix;
        }

        $segments = explode('/', $slug);
        foreach ($segments as &$segment) {
            $segment = rawurlencode($segment);
        }
        unset($segment);

        return self::$pagePaths[$domainId][$langCode][$pageId]
            = $prefix . '/' . implode('/', $segments);
    }

    public static function invalidateItem(int $itemId, int $contentTypeId): void
    {
        if ($itemId < 1) {
            return;
        }

        if ($contentTypeId > 0 && defined('DOMAIN_ID')) {
            $type = \DB::getRow(
                'SELECT viewer.system_name AS viewer_name,
                        manager.system_name AS manager_name
                 FROM content_types ct
                 LEFT JOIN plugins viewer ON viewer.plugin_id=ct.canonical_viewer_plugin_id
                 LEFT JOIN plugins manager ON manager.plugin_id=ct.manager_plugin_id
                 WHERE ct.ct_id=$1',
                [$contentTypeId]
            );

            $names = array_values(array_unique(array_filter([
                (string)($type['viewer_name'] ?? ''),
                (string)($type['manager_name'] ?? ''),
                'ContentManager',
            ])));

            if ($names !== []) {
                $available = PluginRegistry::forDomain((int)DOMAIN_ID);
                $registry = new PluginRegistry();

                foreach ($names as $name) {
                    if (!isset($available[$name])) {
                        continue;
                    }
                    $plugin = $registry->get($name);
                    if ($plugin && method_exists($plugin, 'invalidateCanonicals')) {
                        $plugin->invalidateCanonicals($itemId, $contentTypeId);
                        return;
                    }
                }
            }
        }

        self::deleteItemAndDependents($itemId);
    }

    public static function deleteItemAndDependents(int $itemId): void
    {
        if ($itemId < 1) {
            return;
        }

        self::deleteRows(
            'WITH RECURSIVE affected(item_id) AS (
                 SELECT $1::bigint
                 UNION
                 SELECT cp.item_id
                 FROM content_canonical_pages cp
                 JOIN affected a ON cp.dependency_item_id=a.item_id
             )
             DELETE FROM content_canonical_pages cp
             USING affected a
             WHERE cp.item_id=a.item_id
             RETURNING cp.domain_id, cp.item_id',
            [$itemId]
        );
    }

    public static function invalidateType(int $contentTypeId): void
    {
        if ($contentTypeId < 1) {
            return;
        }

        self::deleteRows(
            'DELETE FROM content_canonical_pages cp
             USING content_items ci
             WHERE cp.item_id=ci.item_id
               AND ci.ct_id=$1
             RETURNING cp.domain_id, cp.item_id',
            [$contentTypeId]
        );
    }

    public static function invalidateDomain(int $domainId): void
    {
        if ($domainId < 1) {
            return;
        }

        self::deleteRows(
            'DELETE FROM content_canonical_pages
             WHERE domain_id=$1
             RETURNING domain_id, item_id',
            [$domainId]
        );

        unset(self::$pageIds[$domainId], self::$pageSlugs[$domainId], self::$pagePaths[$domainId]);
    }

    private static function storeRows(array $rows): void
    {
        if ($rows === []) {
            return;
        }

        $params = [];
        $values = [];
        $index = 1;

        foreach ($rows as $row) {
            $placeholders = [];
            foreach (['domain_id', 'item_id', 'page_id', 'dependency_item_id'] as $field) {
                $placeholders[] = '$' . $index++;
                $params[] = $row[$field];
            }
            $values[] = '(' . implode(',', $placeholders) . ')';
        }

        if (\DB::query(
            'INSERT INTO content_canonical_pages
                (domain_id, item_id, page_id, dependency_item_id)
             VALUES ' . implode(',', $values) . '
             ON CONFLICT (domain_id, item_id)
             DO UPDATE SET
                page_id=EXCLUDED.page_id,
                dependency_item_id=EXCLUDED.dependency_item_id',
            $params
        ) === false) {
            throw new \RuntimeException('Failed to store canonical page mappings.');
        }
    }

    private static function primePageSlugs(int $domainId, array $pageIds): void
    {
        if ($pageIds === []) {
            return;
        }

        $missing = [];
        foreach ($pageIds as $pageId) {
            $pageId = (int)$pageId;
            if ($pageId > 0 && !array_key_exists($pageId, self::$pageSlugs[$domainId] ?? [])) {
                $missing[$pageId] = $pageId;
            }
        }
        if ($missing === []) {
            return;
        }

        $rows = \DB::query(
            'SELECT page_id, page_slug
             FROM pages
             WHERE domain_id=$1
               AND page_id=ANY($2::int[])',
            [$domainId, \DB::prepareIdArray(array_values($missing))]
        );

        while ($row = \DB::fetchRow($rows)) {
            $pageId = (int)$row['page_id'];
            self::$pageSlugs[$domainId][$pageId] = (string)$row['page_slug'];
            unset($missing[$pageId]);
        }

        foreach ($missing as $pageId) {
            self::$pageSlugs[$domainId][$pageId] = false;
        }
    }

    private static function defaultLanguage(int $domainId): string
    {
        if (isset(self::$defaultLanguages[$domainId])) {
            return self::$defaultLanguages[$domainId];
        }

        if (
            defined('DOMAIN_ID')
            && defined('DOMAIN_CONFIG')
            && (int)DOMAIN_ID === $domainId
            && is_array(DOMAIN_CONFIG)
        ) {
            return self::$defaultLanguages[$domainId]
                = (string)(DOMAIN_CONFIG['default_language'] ?? '');
        }

        $config = Utils\JsonTool::decodeArray(
            \DB::getOne('SELECT domain_config FROM domains WHERE domain_id=$1', [$domainId])
        );

        return self::$defaultLanguages[$domainId]
            = (string)($config['default_language'] ?? '');
    }

    private static function deleteRows(string $sql, array $params): void
    {
        $result = \DB::query($sql, $params);
        if (!is_array($result) || $result === []) {
            return;
        }

        $rows = array_is_list($result) ? $result : [$result];
        $keys = [];

        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }

            $domainId = (int)($row['domain_id'] ?? 0);
            $itemId = (int)($row['item_id'] ?? 0);
            if ($domainId < 1 || $itemId < 1) {
                continue;
            }

            unset(self::$pageIds[$domainId][$itemId]);
            $keys[self::cacheKey($domainId, $itemId)] = true;
        }

        if ($keys !== []) {
            \Cache::delMany(array_keys($keys));
        }
    }

    private static function cacheKey(int $domainId, int $itemId): string
    {
        return 'd_' . $domainId . ':canonical_page:' . $itemId;
    }
}
