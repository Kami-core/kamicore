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

final class User
{
    public static ?array $user = null;
    public static ?array $group = null;

    /** @var null|array{actions:array<string,true>,content_by_id:array<int,array<string,true>>,content_by_name:array<string,array<string,true>>} */
    private static ?array $apiScope = null;

    private const ACL_CACHE_TTL = 300;
    private const ACL_CACHE_NS = 'acl_bag_v3:';

    public static function init(): void
    {
        Session::init();

        self::$user = self::getUser();
        debug_step('get User');

        if (!self::$user) {
            Session::create();
            self::$user = self::getUser(0);
        }

        self::$group = self::getGroup();
        debug_step('get group');

        if (!defined('USER_ID')) {
            define('USER_ID', Session::userId());
        }
        if (!defined('USERGROUP_ID')) {
            define('USERGROUP_ID', (int)self::$user['usergroup_id']);
        }
    }

    /**
     * Initialize the current user from an authenticated API token.
     * API scope can only narrow the user's current group permissions.
     */
    public static function initApi(int $userId, array $restrictions): bool
    {
        self::$user = null;
        self::$group = null;
        self::$apiScope = null;

        if ($userId < 1) {
            return false;
        }

        $user = self::getUser($userId);
        if (!$user || empty($user['is_active'])) {
            return false;
        }

        self::$user = $user;
        self::$group = self::getGroup((int)$user['usergroup_id']);
        if (!self::$group || empty(self::$group['has_api'])) {
            self::$user = null;
            self::$group = null;
            return false;
        }

        self::$apiScope = self::compileApiScope($restrictions);

        if (!defined('USER_ID')) {
            define('USER_ID', (int)$user['user_id']);
        }
        if (!defined('USERGROUP_ID')) {
            define('USERGROUP_ID', (int)$user['usergroup_id']);
        }

        return true;
    }

    public static function getUser(?int $userId = null): ?array
    {
        if ($userId === null && self::$apiScope !== null && self::$user !== null) {
            return self::$user;
        }

        $userId ??= Session::userId();
        $cacheKey = 'users:v2:' . $userId;

        $user = \Cache::get($cacheKey);
        if (!is_array($user)) {
            $user = \DB::getRow(
                'SELECT user_id, user_uuid, username, email, usergroup_id, is_active, email_verified_at, created_at, last_login
                 FROM users
                 WHERE user_id=$1
                 LIMIT 1',
                [$userId]
            ) ?: null;

            debug_step('User DB');

            if ($user) {
                $user['user_id'] = (int)$user['user_id'];
                $user['usergroup_id'] = (int)$user['usergroup_id'];
                $user['is_active'] = (bool)$user['is_active'];
                \Cache::set($cacheKey, $user);
            }
        }

        return $user;
    }

    public static function getGroup(?int $usergroupId = null): ?array
    {
        $usergroupId ??= (int)(self::$user['usergroup_id'] ?? 0);
        if ($usergroupId < 1) {
            return null;
        }

        $cacheKey = 'usergroups:' . $usergroupId;
        $group = \Cache::get($cacheKey);

        if (
            !is_array($group)
            || !isset($group['uuid'], $group['system_name'], $group['has_api'])
        ) {
            $group = \DB::getRow(
                'SELECT usergroup_id, uuid, system_name, is_system, has_api
                 FROM usergroups
                 WHERE usergroup_id=$1
                 LIMIT 1',
                [$usergroupId]
            ) ?: null;

            if ($group) {
                $group['usergroup_id'] = (int)$group['usergroup_id'];
                $group['is_system'] = (bool)$group['is_system'];
                $group['has_api'] = (bool)$group['has_api'];
                \Cache::set($cacheKey, $group);
            }
        }

        if (!$group) {
            return null;
        }

        $translation = Translation::get((string)$group['uuid']) ?? [];
        $group['title'] = (string)($translation['title'] ?? $group['system_name']);
        $group['description'] = (string)($translation['description'] ?? '');

        return $group;
    }

    public static function getId(): int
    {
        if (self::$apiScope !== null && self::$user && isset(self::$user['user_id'])) {
            return (int)self::$user['user_id'];
        }

        return Session::userId();
    }

    /**
     * Check whether the current API token explicitly allows one API action.
     */
    public static function canApiAction(string $plugin, string $action): bool
    {
        if (self::$apiScope === null) {
            return false;
        }

        $plugin = trim($plugin);
        $action = trim($action);
        if ($plugin === '' || $action === '') {
            return false;
        }

        return isset(self::$apiScope['actions'][$plugin . '.' . $action]);
    }

    public static function isGuest(): bool
    {
        return self::getId() === 0;
    }

    public static function isRoot(?int $groupId = null): bool
    {
        $groupId ??= self::currentGroupId();
        return $groupId === (int)(GLOBAL_SETTINGS['usergroup_root'] ?? -1);
    }

    /**
     * Check access to one exact page. Pages already belong to one domain, so
     * page ACL needs no additional scope.
     */
    public static function canPage(int $pageId, ?int $groupId = null): bool
    {
        if ($pageId < 1) {
            return false;
        }

        $groupId ??= self::currentGroupId();
        if (self::isRoot($groupId)) {
            return true;
        }

        $bag = self::getAclBag($groupId);
        return isset($bag['pages_by_id'][$pageId]);
    }

    /**
     * Check access to one exact plugin handler.
     */
    public static function canPlugin(
        int|string $plugin,
        string $handler,
        ?int $groupId = null
    ): bool {
        $handler = trim($handler);
        if ($handler === '') {
            return false;
        }

        $groupId ??= self::currentGroupId();
        if (self::isRoot($groupId)) {
            return true;
        }

        $bag = self::getAclBag($groupId);
        $bucket = is_int($plugin) || ctype_digit((string)$plugin)
            ? $bag['plugins_by_id'][(int)$plugin] ?? null
            : $bag['plugins_by_name'][(string)$plugin] ?? null;

        return is_array($bucket) && isset($bucket[$handler]);
    }

    /**
     * Check one exact content capability.
     * ACL permission describes a user operation, not a low-level data write.
     */
    public static function canContent(
        int|string $contentType,
        string $handler,
        ?int $groupId = null
    ): bool {
        $handler = trim($handler);
        if ($handler === '') {
            return false;
        }

        if (!self::apiAllowsContent($contentType, $handler)) {
            return false;
        }

        $groupId ??= self::currentGroupId();
        if (self::isRoot($groupId)) {
            return true;
        }

        $bag = self::getAclBag($groupId);
        $bucket = is_int($contentType) || ctype_digit((string)$contentType)
            ? $bag['content_types_by_id'][(int)$contentType] ?? null
            : $bag['content_types_by_name'][(string)$contentType] ?? null;

        return is_array($bucket) && isset($bucket[$handler]);
    }

    /**
     * Return content type IDs available for one exact capability.
     * Root returns every registered content type.
     *
     * @return int[]
     */
    public static function getAllowedContentTypeIds(
        string $handler,
        ?int $groupId = null
    ): array {
        $handler = trim($handler);
        if ($handler === '') {
            return [];
        }

        $groupId ??= self::currentGroupId();
        if (self::isRoot($groupId)) {
            $ids = array_map('intval', \DB::getArr('SELECT ct_id FROM content_types ORDER BY ct_id'));
        } else {
            $bag = self::getAclBag($groupId);
            $ids = [];
            foreach ($bag['content_types_by_id'] as $contentTypeId => $handlers) {
                if (isset($handlers[$handler])) {
                    $ids[] = (int)$contentTypeId;
                }
            }
        }

        if (self::$apiScope === null) {
            return $ids;
        }

        return array_values(array_filter(
            $ids,
            static fn(int $contentTypeId): bool => self::apiAllowsContent($contentTypeId, $handler)
        ));
    }

    public static function clearAclCache(?int $groupId = null): void
    {
        $groupIds = $groupId !== null
            ? [$groupId]
            : array_map('intval', \DB::getArr('SELECT usergroup_id FROM usergroups'));

        foreach ($groupIds as $id) {
            \Cache::del(self::aclCacheKey($id));
        }
    }

    public static function clearUserCache(int $userId): void
    {
        \Cache::del('users:v2:' . $userId);
        \Cache::del('users:' . $userId);
    }

    public static function clearGroupCache(int $groupId): void
    {
        \Cache::del('usergroups:' . $groupId);
        self::clearAclCache($groupId);
    }

    private static function apiAllowsContent(int|string $contentType, string $handler): bool
    {
        if (self::$apiScope === null) {
            return true;
        }

        $bucket = is_int($contentType) || ctype_digit((string)$contentType)
            ? self::$apiScope['content_by_id'][(int)$contentType] ?? null
            : self::$apiScope['content_by_name'][(string)$contentType] ?? null;

        return is_array($bucket) && isset($bucket[$handler]);
    }

    /**
     * Build fast lookup maps from restrictions stored with one API token.
     *
     * @return array{actions:array<string,true>,content_by_id:array<int,array<string,true>>,content_by_name:array<string,array<string,true>>}
     */
    private static function compileApiScope(array $restrictions): array
    {
        $actions = [];
        foreach (is_array($restrictions['actions'] ?? null) ? $restrictions['actions'] : [] as $action) {
            $action = trim((string)$action);
            if ($action !== '') {
                $actions[$action] = true;
            }
        }

        $requestedContent = [];
        foreach (is_array($restrictions['content'] ?? null) ? $restrictions['content'] : [] as $type => $handlers) {
            if (!is_string($type) || !is_array($handlers)) {
                continue;
            }

            $type = trim($type);
            if ($type === '') {
                continue;
            }

            foreach ($handlers as $handler) {
                $handler = trim((string)$handler);
                if ($handler !== '') {
                    $requestedContent[$type][$handler] = true;
                }
            }
        }

        $contentById = [];
        $contentByName = [];
        if ($requestedContent !== []) {
            $rows = \DB::query('SELECT ct_id, system_name FROM content_types');
            while ($row = \DB::fetchRow($rows)) {
                $name = (string)$row['system_name'];
                if (!isset($requestedContent[$name])) {
                    continue;
                }

                $handlers = $requestedContent[$name];
                $contentById[(int)$row['ct_id']] = $handlers;
                $contentByName[$name] = $handlers;
            }
        }

        return [
            'actions' => $actions,
            'content_by_id' => $contentById,
            'content_by_name' => $contentByName,
        ];
    }

    private static function currentGroupId(): int
    {
        if (self::$user && isset(self::$user['usergroup_id'])) {
            return (int)self::$user['usergroup_id'];
        }
        if (defined('USERGROUP_ID')) {
            return (int)USERGROUP_ID;
        }
        return (int)(GLOBAL_SETTINGS['usergroup_guest'] ?? 0);
    }

    private static function compileAclBag(int $groupId): array
    {
        $bag = [
            'meta' => [
                'group_id' => $groupId,
                'generated_at' => date('Y-m-d H:i:s'),
            ],
            'plugins_by_id' => [],
            'plugins_by_name' => [],
            'content_types_by_id' => [],
            'content_types_by_name' => [],
            'pages_by_id' => [],
            'pages_by_name' => [],
        ];

        $plugins = \DB::query(
            'SELECT pa.plugin_id, p.system_name AS plugin_name, pa.handler
             FROM plugin_acl pa
             JOIN plugins p USING(plugin_id)
             WHERE pa.usergroup_id=$1',
            [$groupId]
        );
        while ($row = \DB::fetchRow($plugins)) {
            $pluginId = (int)$row['plugin_id'];
            $pluginName = (string)$row['plugin_name'];
            $handler = (string)$row['handler'];
            $bag['plugins_by_id'][$pluginId][$handler] = true;
            $bag['plugins_by_name'][$pluginName][$handler] = true;
        }

        $content = \DB::query(
            'SELECT ca.ct_id, ct.system_name AS content_type_name, ca.handler
             FROM content_acl ca
             JOIN content_types ct USING(ct_id)
             WHERE ca.usergroup_id=$1',
            [$groupId]
        );
        while ($row = \DB::fetchRow($content)) {
            $contentTypeId = (int)$row['ct_id'];
            $contentTypeName = (string)$row['content_type_name'];
            $handler = (string)$row['handler'];
            $bag['content_types_by_id'][$contentTypeId][$handler] = true;
            $bag['content_types_by_name'][$contentTypeName][$handler] = true;
        }

        $pages = \DB::query(
            'SELECT pa.page_id, p.system_name AS page_name
             FROM page_acl pa
             JOIN pages p USING(page_id)
             WHERE pa.usergroup_id=$1',
            [$groupId]
        );
        while ($row = \DB::fetchRow($pages)) {
            $pageId = (int)$row['page_id'];
            $pageName = (string)$row['page_name'];
            $bag['pages_by_id'][$pageId] = true;
            $bag['pages_by_name'][$pageName] = true;
        }

        return $bag;
    }

    private static function aclCacheKey(int $groupId): string
    {
        return self::ACL_CACHE_NS . $groupId;
    }

    private static function getAclBag(int $groupId): array
    {
        $key = self::aclCacheKey($groupId);
        $cached = \Cache::get($key);
        if (is_array($cached)) {
            return $cached;
        }

        $bag = self::compileAclBag($groupId);
        \Cache::set($key, $bag, self::ACL_CACHE_TTL);
        return $bag;
    }

}
