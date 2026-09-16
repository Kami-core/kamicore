<?php

/**
 * KamiCore
 *
 * SPDX-License-Identifier: Apache-2.0
 *
 * @see https://kamicore.org
 */

declare(strict_types=1);

namespace Plugins\UserManager;

use Core\Request;
use Core\User;
use Plugins\Forms\Forms;

if (!defined('IN_KAMI')) die();

final class UserManager extends \Core\BasePlugin
{
    private const CONTENT_CAPABILITIES = ['view', 'create', 'edit', 'delete'];

    private ?Forms $forms = null;

    public function overview(array $instanceParams = []): string
    {
        $this->assertAccess();

        return $this->render('overview', [
            'users_url' => $this->actionUrl('users'),
            'groups_url' => $this->actionUrl('groups'),
        ]);
    }

    public function users(array $instanceParams = []): string
    {
        $this->assertAccess();
        return $this->renderUsers();
    }

    public function userEdit(array $instanceParams = []): string
    {
        $this->assertAccess();
        return $this->renderUserEditor((int)$this->param('userId', 0));
    }

    public function userSave(array $instanceParams = []): string
    {
        $this->assertAccess();
        $data = Request::all();
        $userId = (int)($data['user_id'] ?? 0);

        if ($userId === 0 && isset($data['user_id']) && (string)$data['user_id'] !== '0') {
            throw new \InvalidArgumentException('Invalid user ID.');
        }
        if ($userId < 0) {
            throw new \InvalidArgumentException('Invalid user ID.');
        }
        if ($userId === 0 && !empty($data['editing_system_guest'])) {
            throw new \RuntimeException('The guest system user cannot be edited.');
        }

        $existing = $userId > 0
            ? \DB::getRow('SELECT * FROM users WHERE user_id=$1', [$userId])
            : null;
        if ($userId > 0 && !$existing) {
            throw new \RuntimeException('User not found.');
        }

        $username = trim((string)($data['username'] ?? ''));
        $email = strtolower(trim((string)($data['email'] ?? '')));
        $groupId = (int)($data['usergroup_id'] ?? 0);
        $isActive = !empty($data['is_active']);
        $password = (string)($data['password'] ?? '');

        if ($username === '') {
            throw new \InvalidArgumentException('Username is required.');
        }
        if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            throw new \InvalidArgumentException('Invalid email address.');
        }
        if (!\DB::getOne('SELECT 1 FROM usergroups WHERE usergroup_id=$1', [$groupId])) {
            throw new \InvalidArgumentException('Invalid user group.');
        }

        $duplicate = \DB::getOne(
            'SELECT user_id FROM users WHERE username=$1 AND ($2::integer=0 OR user_id<>$2) LIMIT 1',
            [$username, $userId]
        );
        if ($duplicate) {
            throw new \RuntimeException('Username is already in use.');
        }
        if ($email !== '') {
            $duplicate = \DB::getOne(
                'SELECT user_id FROM users WHERE lower(email)=lower($1) AND ($2::integer=0 OR user_id<>$2) LIMIT 1',
                [$email, $userId]
            );
            if ($duplicate) {
                throw new \RuntimeException('Email is already in use.');
            }
        }

        $values = [
            'username' => $username,
            'email' => $email !== '' ? $email : null,
            'usergroup_id' => $groupId,
            'is_active' => $isActive,
        ];
        if (
            $existing
            && strtolower(trim((string)($existing['email'] ?? ''))) !== strtolower($email)
        ) {
            $values['email_verified_at'] = null;
        }
        if ($password !== '') {
            $values['password_hash'] = password_hash($password, PASSWORD_ARGON2ID);
        }

        if ($existing) {
            if (\DB::update('users', $values, 'user_id=$1', [$userId]) === false) {
                throw new \RuntimeException('Failed to update user.');
            }
            if ((int)$existing['usergroup_id'] !== $groupId && !$this->groupHasApi($groupId)) {
                $this->revokeApiTokensForUser($userId);
            }
        } else {
            $created = \DB::insert('users', $values, 'user_id');
            if (!is_numeric($created)) {
                throw new \RuntimeException('Failed to create user.');
            }
            $userId = (int)$created;
        }

        User::clearUserCache($userId);

        if ($notifications = $this->plugins->get('Notifications')) {
            $notifications->store(
                User::getId(),
                $this->phrase('user_saved', 'User saved.'),
                'success'
            );
        }

        return \Core\Response::seeOther($this->actionUrl('users'));
    }

    public function groups(array $instanceParams = []): string
    {
        $this->assertAccess();
        return $this->renderGroups();
    }

    public function groupEdit(array $instanceParams = []): string
    {
        $this->assertAccess();
        return $this->renderGroupEditor((int)$this->param('groupId', 0));
    }

    public function groupSave(array $instanceParams = []): string
    {
        $this->assertAccess();
        $data = Request::all();
        $groupId = (int)($data['usergroup_id'] ?? 0);
        $existing = $groupId > 0
            ? \DB::getRow('SELECT * FROM usergroups WHERE usergroup_id=$1', [$groupId])
            : null;

        if ($groupId > 0 && !$existing) {
            throw new \RuntimeException('User group not found.');
        }

        $systemName = trim((string)($data['system_name'] ?? ''));
        $title = trim((string)($data['title'] ?? ''));
        $description = trim((string)($data['description'] ?? ''));
        $hasApi = !empty($data['has_api']);

        if ($existing && !empty($existing['is_system'])) {
            $systemName = (string)$existing['system_name'];
        }
        if (!preg_match('/^[A-Za-z][A-Za-z0-9_-]*$/', $systemName)) {
            throw new \InvalidArgumentException('Group system name must use ASCII letters, digits, underscore or hyphen.');
        }
        if ($title === '') {
            throw new \InvalidArgumentException('Group title is required.');
        }

        $duplicate = \DB::getOne(
            'SELECT usergroup_id FROM usergroups
             WHERE system_name=$1 AND ($2::integer=0 OR usergroup_id<>$2)
             LIMIT 1',
            [$systemName, $groupId]
        );
        if ($duplicate) {
            throw new \RuntimeException('Group system name is already in use.');
        }

        if (!\DB::beginTransaction()) {
            throw new \RuntimeException('Failed to start user group transaction.');
        }

        try {
            if ($existing) {
                if (\DB::update(
                    'usergroups',
                    [
                        'system_name' => $systemName,
                        'has_api' => $hasApi,
                    ],
                    'usergroup_id=$1',
                    [$groupId]
                ) === false) {
                    throw new \RuntimeException('Failed to update user group.');
                }
                $groupUuid = (string)$existing['uuid'];
            } else {
                $created = \DB::insert(
                    'usergroups',
                    [
                        'system_name' => $systemName,
                        'is_system' => false,
                        'has_api' => $hasApi,
                    ],
                    'usergroup_id, uuid'
                );
                if (!is_array($created)) {
                    throw new \RuntimeException('Failed to create user group.');
                }
                $groupId = (int)$created['usergroup_id'];
                $groupUuid = (string)$created['uuid'];
            }

            $this->saveGroupTranslation($groupUuid, $title, $description);
            $this->syncApiAccessAcl($groupId, $hasApi);
            if ($existing && !empty($existing['has_api']) && !$hasApi) {
                $this->revokeApiTokensForGroup($groupId);
            }

            if (!\DB::commit()) {
                throw new \RuntimeException('Failed to commit user group transaction.');
            }
        } catch (\Throwable $e) {
            \DB::rollBack();
            throw $e;
        }

        User::clearGroupCache($groupId);

        if ($notifications = $this->plugins->get('Notifications')) {
            $notifications->store(
                User::getId(),
                $this->phrase('group_saved', 'Group saved.'),
                'success'
            );
        }

        return \Core\Response::seeOther($this->actionUrl('groups'));
    }

    public function groupDelete(array $instanceParams = []): string
    {
        $this->assertAccess();
        $data = Request::all();
        $groupId = (int)($data['usergroup_id'] ?? 0);
        $group = \DB::getRow('SELECT * FROM usergroups WHERE usergroup_id=$1', [$groupId]);

        if (!$group) {
            throw new \RuntimeException('User group not found.');
        }
        if (!empty($group['is_system'])) {
            throw new \RuntimeException('System groups cannot be deleted.');
        }
        if (\DB::getOne('SELECT 1 FROM users WHERE usergroup_id=$1 LIMIT 1', [$groupId])) {
            throw new \RuntimeException('Move users to another group before deleting this group.');
        }

        if (!\DB::beginTransaction()) {
            throw new \RuntimeException('Failed to start user group transaction.');
        }

        try {
            if (\DB::delete('translations', 'entity_uuid=$1', [(string)$group['uuid']]) === false) {
                throw new \RuntimeException('Failed to delete user group translations.');
            }
            if (\DB::delete('usergroups', 'usergroup_id=$1', [$groupId]) === false) {
                throw new \RuntimeException('Failed to delete user group.');
            }
            if (!\DB::commit()) {
                throw new \RuntimeException('Failed to commit user group deletion.');
            }
        } catch (\Throwable $e) {
            \DB::rollBack();
            throw $e;
        }

        \Core\Translation::forget((string)$group['uuid']);
        User::clearGroupCache($groupId);

        if ($notifications = $this->plugins->get('Notifications')) {
            $notifications->store(
                User::getId(),
                $this->phrase('group_deleted', 'Group deleted.'),
                'success'
            );
        }

        return \Core\Response::seeOther($this->actionUrl('groups'));
    }

    public function acl(array $instanceParams = []): string
    {
        $this->assertAccess();
        $groupId = (int)$this->param('groupId', 0);

        return $this->renderAcl($groupId);
    }

    public function aclSave(array $instanceParams = []): string
    {
        $this->assertAccess();
        $data = Request::all();
        $groupId = (int)($data['usergroup_id'] ?? 0);

        if (!\DB::getOne('SELECT 1 FROM usergroups WHERE usergroup_id=$1', [$groupId])) {
            throw new \InvalidArgumentException('Invalid user group.');
        }

        $pageIds = $this->normalizeIds($data['pages'] ?? []);
        $plugins = is_array($data['plugins'] ?? null) ? $data['plugins'] : [];
        $content = is_array($data['content'] ?? null) ? $data['content'] : [];

        \DB::beginTransaction();
        try {
            $this->savePageAcl($groupId, $pageIds);
            $this->savePluginAcl($groupId, $plugins);
            $this->saveContentAcl($groupId, $content);

            if (!\DB::commit()) {
                throw new \RuntimeException('Failed to commit ACL changes.');
            }
        } catch (\Throwable $e) {
            \DB::rollBack();
            throw $e;
        }

        User::clearAclCache($groupId);

        if ($notifications = $this->plugins->get('Notifications')) {
            $notifications->store(
                User::getId(),
                $this->phrase('acl_saved', 'Permissions saved.'),
                'success'
            );
        }

        return \Core\Response::seeOther(
            $this->actionUrl('acl', ['groupId' => $groupId])
        );
    }

    private function renderUsers(): string
    {
        $rows = '';
        $result = \DB::query(
            'SELECT u.user_id, u.username, u.email, u.is_active, u.email_verified_at,
                    g.uuid, g.system_name AS group_system_name
             FROM users u
             JOIN usergroups g USING(usergroup_id)
             WHERE u.user_id > 0
             ORDER BY lower(u.username::text), u.user_id'
        );

        while ($row = \DB::fetchRow($result)) {
            $translation = \Core\Translation::get((string)$row['uuid']) ?? [];
            $groupTitle = (string)($translation['title'] ?? $row['group_system_name']);
            $rows .= $this->render('user-row', [
                'username' => \Core\Html::escape((string)$row['username']),
                'email' => \Core\Html::escape((string)($row['email'] ?? '—')),
                'group' => \Core\Html::escape($groupTitle),
                'group_name' => \Core\Html::escape((string)$row['group_system_name']),
                'status' => $this->phrase(!empty($row['is_active']) ? 'active' : 'inactive', !empty($row['is_active']) ? 'Active' : 'Inactive'),
                'verification' => !empty($row['email_verified_at'])
                    ? $this->phrase('verified', 'Verified')
                    : $this->phrase('unverified', 'Unverified'),
                'edit_url' => $this->actionUrl('userEdit', ['userId' => (int)$row['user_id']]),
            ]);
        }

        return $this->render('users', [
            'user_rows' => $rows,
            'create_url' => $this->actionUrl('userEdit'),
            'groups_url' => $this->actionUrl('groups'),
            'back_url' => $this->actionUrl('overview'),
        ]);
    }

    private function renderUserEditor(int $userId): string
    {
        $user = $userId > 0
            ? \DB::getRow('SELECT * FROM users WHERE user_id=$1', [$userId])
            : null;
        if ($userId > 0 && !$user) {
            throw new \RuntimeException('User not found.');
        }

        $groupOptions = [];
        $groups = \DB::query('SELECT usergroup_id, uuid, system_name FROM usergroups');
        while ($group = \DB::fetchRow($groups)) {
            $translation = \Core\Translation::get((string)$group['uuid']) ?? [];
            $title = (string)($translation['title'] ?? $group['system_name']);
            $groupOptions[] = [
                'value' => (int)$group['usergroup_id'],
                'label' => $title . ' (' . (string)$group['system_name'] . ')',
                'system_name' => (string)$group['system_name'],
            ];
        }
        $groupOptions = \Core\Translation::sortByTitle($groupOptions, 'label', 'system_name');

        $fields = '';
        $fields .= $this->forms()->renderField([
            'name' => 'username',
            'type' => 'string',
            'label' => $this->phrase('username', 'Username'),
            'value' => (string)($user['username'] ?? ''),
            'required' => true,
        ]);
        $fields .= $this->forms()->renderField([
            'name' => 'email',
            'type' => 'email',
            'label' => $this->phrase('email', 'Email'),
            'value' => (string)($user['email'] ?? ''),
        ]);
        $fields .= $this->forms()->renderField([
            'name' => 'usergroup_id',
            'type' => 'select',
            'label' => $this->phrase('group', 'Group'),
            'value' => (int)($user['usergroup_id'] ?? GLOBAL_SETTINGS['usergroup_default']),
            'options' => $groupOptions,
            'required' => true,
        ]);
        $fields .= $this->forms()->renderField([
            'name' => 'is_active',
            'type' => 'checkbox',
            'label' => $this->phrase('is_active', 'Active'),
            'value' => !isset($user['is_active']) || !empty($user['is_active']) ? 1 : 0,
        ]);

        $passwordField = '<div class="form-field">'
            . '<label for="um-password">' . \Core\Html::escape($this->phrase('new_password', 'New password')) . '</label>'
            . '<input type="password" id="um-password" name="password" value="" autocomplete="new-password">'
            . '<p class="form-hint">' . \Core\Html::escape($this->phrase('password_help', 'Leave empty to keep the current password.')) . '</p>'
            . '</div>';

        return $this->render('user-edit', [
            'page_title' => \Core\Html::escape($user ? (string)$user['username'] : $this->phrase('new_user', 'New user')),
            'user_id' => (string)$userId,
            'fields' => $fields,
            'password_field' => $passwordField,
            'save_url' => $this->actionUrl('userSave'),
            'back_url' => $this->actionUrl('users'),
        ]);
    }

    private function renderGroups(): string
    {
        $groupRows = [];
        $result = \DB::query(
            'SELECT g.usergroup_id, g.uuid, g.system_name, g.is_system,
                    count(u.user_id) FILTER (WHERE u.user_id > 0) AS user_count
             FROM usergroups g
             LEFT JOIN users u USING(usergroup_id)
             GROUP BY g.usergroup_id'
        );

        while ($row = \DB::fetchRow($result)) {
            $translation = \Core\Translation::get((string)$row['uuid']) ?? [];
            $row['title'] = (string)($translation['title'] ?? $row['system_name']);
            $row['description'] = (string)($translation['description'] ?? '');
            $groupRows[] = $row;
        }
        $groupRows = \Core\Translation::sortByTitle($groupRows);

        $rows = '';
        foreach ($groupRows as $row) {
            $delete = '';
            if (empty($row['is_system'])) {
                $delete = $this->render('group-delete', [
                    'delete_url' => $this->actionUrl('groupDelete'),
                    'group_id' => (string)$row['usergroup_id'],
                    'confirm' => \Core\Html::escape($this->phrase('confirm_delete_group', 'Delete this group?')),
                ]);
            }

            $rows .= $this->render('group-row', [
                'title' => \Core\Html::escape((string)$row['title']),
                'name' => \Core\Html::escape((string)$row['system_name']),
                'description' => \Core\Html::escape((string)$row['description']),
                'users' => (string)$row['user_count'],
                'system_badge' => !empty($row['is_system'])
                    ? ' <span class="admin-page-description">(' . \Core\Html::escape($this->phrase('system', 'system')) . ')</span>'
                    : '',
                'edit_url' => $this->actionUrl('groupEdit', ['groupId' => (int)$row['usergroup_id']]),
                'acl_url' => $this->actionUrl('acl', ['groupId' => (int)$row['usergroup_id']]),
                'delete' => $delete,
            ]);
        }

        return $this->render('groups', [
            'group_rows' => $rows,
            'create_url' => $this->actionUrl('groupEdit'),
            'users_url' => $this->actionUrl('users'),
            'back_url' => $this->actionUrl('overview'),
        ]);
    }

    private function renderGroupEditor(int $groupId): string
    {
        $group = $groupId > 0
            ? \DB::getRow('SELECT * FROM usergroups WHERE usergroup_id=$1', [$groupId])
            : null;
        if ($groupId > 0 && !$group) {
            throw new \RuntimeException('User group not found.');
        }

        $translation = $group
            ? (\Core\Translation::get((string)$group['uuid']) ?? [])
            : [];
        $title = (string)($translation['title'] ?? ($group['system_name'] ?? ''));
        $description = (string)($translation['description'] ?? '');

        $fields = '';
        $fields .= $this->forms()->renderField([
            'name' => 'system_name',
            'type' => 'string',
            'label' => $this->phrase('group_name', 'System name'),
            'value' => (string)($group['system_name'] ?? ''),
            'required' => true,
            'readonly' => !empty($group['is_system']),
        ]);
        $fields .= $this->forms()->renderField([
            'name' => 'title',
            'type' => 'string',
            'label' => $this->phrase('group_title', 'Title'),
            'value' => $title,
            'required' => true,
        ]);
        $fields .= $this->forms()->renderField([
            'name' => 'description',
            'type' => 'textarea',
            'label' => $this->phrase('description', 'Description'),
            'value' => $description,
            'params' => ['rows' => 4],
        ]);
        $fields .= $this->forms()->renderField([
            'name' => 'has_api',
            'type' => 'checkbox',
            'label' => $this->phrase('has_api', 'Allow API access'),
            'description' => $this->phrase(
                'has_api_help',
                'Users in this group can create and use personal API tokens within their existing permissions.'
            ),
            'value' => !empty($group['has_api']) ? 1 : 0,
        ]);

        return $this->render('group-edit', [
            'page_title' => \Core\Html::escape($group ? $title : $this->phrase('new_group', 'New group')),
            'group_id' => (string)$groupId,
            'fields' => $fields,
            'save_url' => $this->actionUrl('groupSave'),
            'back_url' => $this->actionUrl('groups'),
        ]);
    }

    private function renderAcl(int $groupId): string
    {
        $group = \DB::getRow('SELECT * FROM usergroups WHERE usergroup_id=$1', [$groupId]);
        if (!$group) {
            throw new \RuntimeException('User group not found.');
        }
        $groupTranslation = \Core\Translation::get((string)$group['uuid']) ?? [];
        $groupTitle = (string)($groupTranslation['title'] ?? $group['system_name']);

        $selectedPages = array_fill_keys(array_map('intval', \DB::getArr(
            'SELECT page_id FROM page_acl WHERE usergroup_id=$1',
            [$groupId]
        )), true);
        $pageFields = '';
        $pageRows = [];
        $pages = \DB::query(
            'SELECT p.page_id, p.uuid, p.system_name, p.page_slug, d.domain_name
             FROM pages p
             JOIN domains d USING(domain_id)'
        );
        while ($page = \DB::fetchRow($pages)) {
            $translation = \Core\Translation::get((string)$page['uuid']) ?? [];
            $page['title'] = (string)($translation['title'] ?? $page['system_name']);
            $pageRows[] = $page;
        }
        usort($pageRows, static function (array $a, array $b): int {
            $domainComparison = \Core\Translation::compareTitles(
                (string)$a['domain_name'],
                (string)$b['domain_name']
            );
            return $domainComparison !== 0
                ? $domainComparison
                : \Core\Translation::compareTitles((string)$a['title'], (string)$b['title']);
        });
        $lastDomain = null;
        foreach ($pageRows as $page) {
            if ($lastDomain !== $page['domain_name']) {
                $lastDomain = (string)$page['domain_name'];
                $pageFields .= '<h4 class="admin-panel-title" style="margin:16px 0 8px">'
                    . \Core\Html::escape($lastDomain) . '</h4>';
            }
            $pageId = (int)$page['page_id'];
            $label = (string)$page['title'] . ' (' . (string)$page['system_name'] . ') · /'
                . ltrim((string)$page['page_slug'], '/');
            $pageFields .= $this->aclCheckbox(
                'pages[]',
                (string)$pageId,
                $label,
                isset($selectedPages[$pageId])
            );
        }

        $selectedPlugins = [];
        $rows = \DB::query(
            'SELECT plugin_id, handler FROM plugin_acl WHERE usergroup_id=$1',
            [$groupId]
        );
        while ($row = \DB::fetchRow($rows)) {
            $selectedPlugins[(int)$row['plugin_id']][(string)$row['handler']] = true;
        }

        $pluginFields = '';
        $pluginRows = [];
        $plugins = \DB::query('SELECT plugin_id, uuid, system_name, config FROM plugins');
        while ($plugin = \DB::fetchRow($plugins)) {
            $config = \Core\Utils\JsonTool::decodeArray($plugin['config'] ?? null);
            $handlers = is_array($config['handlers'] ?? null) ? $config['handlers'] : [];
            if ($handlers === []) {
                continue;
            }
            $translation = \Core\Translation::get((string)$plugin['uuid']) ?? [];
            $plugin['title'] = (string)($translation['title'] ?? $plugin['system_name']);
            $plugin['handlers'] = $handlers;
            $pluginRows[] = $plugin;
        }
        $pluginRows = \Core\Translation::sortByTitle($pluginRows);
        foreach ($pluginRows as $plugin) {
            $pluginId = (int)$plugin['plugin_id'];
            $pluginFields .= '<h4 class="admin-panel-title" style="margin:16px 0 8px">'
                . \Core\Html::escape((string)$plugin['title'])
                . ' <span class="admin-page-description">(' . \Core\Html::escape((string)$plugin['system_name']) . ')</span></h4>';
            foreach (array_keys($plugin['handlers']) as $handler) {
                if (!is_string($handler) || $handler === '') {
                    continue;
                }
                $pluginFields .= $this->aclCheckbox(
                    'plugins[' . $pluginId . '][]',
                    $handler,
                    $handler,
                    isset($selectedPlugins[$pluginId][$handler])
                );
            }
        }

        $selectedContent = [];
        $rows = \DB::query(
            "SELECT ct_id, handler FROM content_acl
             WHERE usergroup_id=$1
               AND handler IN ('view', 'create', 'edit', 'delete')",
            [$groupId]
        );
        while ($row = \DB::fetchRow($rows)) {
            $selectedContent[(int)$row['ct_id']][(string)$row['handler']] = true;
        }

        $contentRows = '';
        $typeRows = [];
        $types = \DB::query('SELECT ct_id, uuid, system_name FROM content_types');
        while ($type = \DB::fetchRow($types)) {
            $translation = \Core\Translation::get((string)$type['uuid']) ?? [];
            $type['title'] = (string)($translation['title'] ?? $type['system_name']);
            $typeRows[] = $type;
        }
        $typeRows = \Core\Translation::sortByTitle($typeRows);
        foreach ($typeRows as $type) {
            $contentTypeId = (int)$type['ct_id'];
            $checks = '';
            foreach (self::CONTENT_CAPABILITIES as $capability) {
                $checks .= $this->aclCheckbox(
                    'content[' . $contentTypeId . '][]',
                    $capability,
                    $capability,
                    isset($selectedContent[$contentTypeId][$capability]),
                    true
                );
            }
            $contentRows .= $this->render('content-acl-row', [
                'content_type' => \Core\Html::escape((string)$type['title'])
                    . ' <span class="admin-page-description">('
                    . \Core\Html::escape((string)$type['system_name']) . ')</span>',
                'capabilities' => $checks,
            ]);
        }

        return $this->render('acl', [
            'group_title' => \Core\Html::escape($groupTitle),
            'group_name' => \Core\Html::escape((string)$group['system_name']),
            'page_fields' => $pageFields,
            'plugin_fields' => $pluginFields,
            'content_rows' => $contentRows,
            'group_id' => (string)$groupId,
            'save_url' => $this->actionUrl('aclSave'),
            'back_url' => $this->actionUrl('groups'),
        ]);
    }

    private function saveGroupTranslation(string $uuid, string $title, string $description): void
    {
        $language = defined('LANG')
            ? (string)LANG
            : (string)(DOMAIN_CONFIG['default_language'] ?? 'en');
        $translation = \Core\Translation::getExact($uuid, $language) ?? [];
        $translation['title'] = $title;
        if ($description !== '') {
            $translation['description'] = $description;
        } else {
            unset($translation['description']);
        }

        if (\DB::query(
            'INSERT INTO translations(entity_uuid, lang_code, translated_data, updated_at)
             VALUES($1, $2, $3::jsonb, NOW())
             ON CONFLICT (entity_uuid, lang_code)
             DO UPDATE SET
                 translated_data=excluded.translated_data,
                 updated_at=excluded.updated_at',
            [
                $uuid,
                $language,
                \Core\Utils\JsonTool::encode($translation, false),
            ]
        ) === false) {
            throw new \RuntimeException('Failed to save user group translation.');
        }

        \Core\Translation::forget($uuid, $language);
    }

    private function savePageAcl(int $groupId, array $pageIds): void
    {
        \DB::query('DELETE FROM page_acl WHERE usergroup_id=$1', [$groupId]);
        foreach ($pageIds as $pageId) {
            if (!\DB::getOne('SELECT 1 FROM pages WHERE page_id=$1', [$pageId])) {
                continue;
            }
            if (\DB::query(
                'INSERT INTO page_acl(page_id, usergroup_id) VALUES($1, $2)',
                [$pageId, $groupId]
            ) === false) {
                throw new \RuntimeException('Failed to save page ACL.');
            }
        }
    }

    private function savePluginAcl(int $groupId, array $plugins): void
    {
        \DB::query('DELETE FROM plugin_acl WHERE usergroup_id=$1', [$groupId]);

        foreach ($plugins as $pluginId => $handlers) {
            $pluginId = (int)$pluginId;
            if ($pluginId < 1 || !is_array($handlers)) {
                continue;
            }
            $config = \Core\Utils\JsonTool::decodeArray(\DB::getOne(
                'SELECT config FROM plugins WHERE plugin_id=$1',
                [$pluginId]
            ));
            $knownHandlers = is_array($config['handlers'] ?? null)
                ? array_fill_keys(array_keys($config['handlers']), true)
                : [];

            foreach (array_unique(array_map('strval', $handlers)) as $handler) {
                if ($handler === '' || !isset($knownHandlers[$handler])) {
                    continue;
                }
                if (\DB::query(
                    'INSERT INTO plugin_acl(usergroup_id, plugin_id, handler)
                     VALUES($1, $2, $3)',
                    [$groupId, $pluginId, $handler]
                ) === false) {
                    throw new \RuntimeException('Failed to save plugin ACL.');
                }
            }
        }
    }

    private function saveContentAcl(int $groupId, array $content): void
    {
        \DB::query(
            "DELETE FROM content_acl
             WHERE usergroup_id=$1
               AND handler IN ('view', 'create', 'edit', 'delete')",
            [$groupId]
        );

        $known = array_fill_keys(self::CONTENT_CAPABILITIES, true);
        foreach ($content as $contentTypeId => $handlers) {
            $contentTypeId = (int)$contentTypeId;
            if ($contentTypeId < 1 || !is_array($handlers)) {
                continue;
            }
            if (!\DB::getOne('SELECT 1 FROM content_types WHERE ct_id=$1', [$contentTypeId])) {
                continue;
            }

            foreach (array_unique(array_map('strval', $handlers)) as $handler) {
                if (!isset($known[$handler])) {
                    continue;
                }
                if (\DB::query(
                    'INSERT INTO content_acl(usergroup_id, ct_id, handler)
                     VALUES($1, $2, $3)',
                    [$groupId, $contentTypeId, $handler]
                ) === false) {
                    throw new \RuntimeException('Failed to save content ACL.');
                }
            }
        }
    }

    private function aclCheckbox(
        string $name,
        string $value,
        string $label,
        bool $checked,
        bool $compact = false
    ): string {
        return '<label style="display:' . ($compact ? 'inline-flex' : 'flex')
            . ';align-items:center;gap:7px;margin:' . ($compact ? '0 14px 0 0' : '5px 0') . '">'
            . '<input type="checkbox" name="' . \Core\Html::escape($name) . '" value="' . \Core\Html::escape($value) . '"'
            . ($checked ? ' checked' : '') . '> '
            . \Core\Html::escape($label)
            . '</label>';
    }

    /** @return int[] */
    private function normalizeIds(mixed $value): array
    {
        if (!is_array($value)) {
            return [];
        }
        return array_values(array_unique(array_filter(
            array_map('intval', $value),
            static fn(int $id): bool => $id > 0
        )));
    }

    private function groupHasApi(int $groupId): bool
    {
        return (bool)\DB::getOne(
            'SELECT has_api FROM usergroups WHERE usergroup_id=$1',
            [$groupId]
        );
    }

    private function apiTokensAvailable(): bool
    {
        return (bool)\DB::getOne("SELECT to_regclass('api_tokens') IS NOT NULL");
    }

    private function revokeApiTokensForUser(int $userId): void
    {
        if ($userId < 1 || !$this->apiTokensAvailable()) {
            return;
        }

        if (\DB::query(
            'UPDATE api_tokens
             SET is_enabled=false, revoked_at=COALESCE(revoked_at, CURRENT_TIMESTAMP)
             WHERE user_id=$1 AND revoked_at IS NULL',
            [$userId]
        ) === false) {
            throw new \RuntimeException('Failed to revoke API tokens for user.');
        }
    }

    private function revokeApiTokensForGroup(int $groupId): void
    {
        if ($groupId < 1 || !$this->apiTokensAvailable()) {
            return;
        }

        if (\DB::query(
            'UPDATE api_tokens t
             SET is_enabled=false, revoked_at=COALESCE(t.revoked_at, CURRENT_TIMESTAMP)
             FROM users u
             WHERE t.user_id=u.user_id
               AND u.usergroup_id=$1
               AND t.revoked_at IS NULL',
            [$groupId]
        ) === false) {
            throw new \RuntimeException('Failed to revoke API tokens for user group.');
        }
    }

    private function syncApiAccessAcl(int $groupId, bool $hasApi): void
    {
        $pluginId = (int)(\DB::getOne(
            'SELECT plugin_id FROM plugins WHERE system_name=$1',
            ['ApiAccess']
        ) ?: 0);
        if ($pluginId < 1) {
            return;
        }

        if ($hasApi) {
            if (\DB::query(
                'INSERT INTO plugin_acl(usergroup_id, plugin_id, handler)
                 VALUES($1, $2, $3)
                 ON CONFLICT (usergroup_id, plugin_id, handler) DO NOTHING',
                [$groupId, $pluginId, 'manage']
            ) === false) {
                throw new \RuntimeException('Failed to enable API access ACL for group.');
            }
            return;
        }

        if (\DB::delete(
            'plugin_acl',
            'usergroup_id=$1 AND plugin_id=$2 AND handler=$3',
            [$groupId, $pluginId, 'manage']
        ) === false) {
            throw new \RuntimeException('Failed to disable API access ACL for group.');
        }
    }

    private function forms(): Forms
    {
        $plugin = $this->forms ??= $this->plugins->get('Forms');
        if (!$plugin instanceof Forms) {
            throw new \RuntimeException('Forms plugin is not available.');
        }
        return $plugin;
    }

    private function assertAccess(): void
    {
        if (!User::canPlugin((int)$this->id, 'manage')) {
            throw new \RuntimeException('UserManager access denied.');
        }
    }

    private function phrase(string $key, string $fallback): string
    {
        return (string)($this->phrases[$key] ?? $fallback);
    }


}
