<?php

/**
 * KamiCore
 *
 * SPDX-License-Identifier: Apache-2.0
 *
 * @see https://kamicore.org
 */

declare(strict_types=1);

namespace Plugins\ApiAccess;

use Core\PluginActionException;
use Core\Request;
use Core\Translation;
use Core\User;
use Plugins\Forms\Forms;
use Plugins\Formatter\Formatter;

if (!defined('IN_KAMI')) die();

final class ApiAccess extends \Core\BasePlugin
{
    private const CONTENT_OPERATIONS = ['view', 'create', 'edit', 'delete'];

    private ?Forms $forms = null;
    private ?Formatter $formatter = null;

    public function tokenList(array $instanceParams = []): string
    {
        $this->addCss('/plugins/ApiAccess/assets/api-access.css');
        $this->assertApiAccess();
        $userId = User::getId();
        $rows = [];

        $result = \DB::query(
            "SELECT token_id, name, token_hint, restrictions, is_enabled,
                    created_at, expires_at, last_used_at, revoked_at,
                    CASE
                        WHEN revoked_at IS NOT NULL THEN 'revoked'
                        WHEN expires_at IS NOT NULL AND expires_at <= CURRENT_TIMESTAMP THEN 'expired'
                        WHEN is_enabled = false THEN 'disabled'
                        ELSE 'active'
                    END AS token_status
             FROM api_tokens
             WHERE user_id=$1
             ORDER BY created_at DESC, token_id DESC",
            [$userId]
        );

        while ($row = \DB::fetchRow($result)) {
            $restrictions = $this->decodeRestrictions($row['restrictions'] ?? null);
            $actionCount = count($restrictions['actions']);
            $typeCount = count($restrictions['content']);
            $status = (string)$row['token_status'];

            $rows[] = [
                'template' => 'token-row',
                'params' => [
                    'name' => \Core\Html::escape((string)$row['name']),
                    'token_hint' => \Core\Html::escape('kami_…' . (string)$row['token_hint']),
                    'created' => \Core\Html::escape($this->formatter()->dateTime($row['created_at'] ?? null)),
                    'last_used' => $row['last_used_at']
                        ? \Core\Html::escape($this->formatter()->dateTime($row['last_used_at']))
                        : '{{phrase.never_used}}',
                    'expires' => $row['expires_at']
                        ? \Core\Html::escape($this->formatter()->dateTime($row['expires_at']))
                        : '{{phrase.never}}',
                    'access' => \Core\Html::escape(
                        $this->replaceCount('actions_count', '{count} actions', $actionCount)
                        . ' · '
                        . $this->replaceCount('types_count', '{count} content types', $typeCount)
                    ),
                    'status' => '{{phrase.' . $status . '}}',
                    'actions_html' => $this->tokenActions((int)$row['token_id'], $status),
                ],
            ];
        }

        if ($rows === []) {
            $rows[] = [
                'template' => 'tokens-empty',
                'params' => [],
            ];
        }

        $status = trim((string)$this->param('status', ''));
        $notice = $status !== '' ? $this->statusNotice($status) : '';

        return $this->render('token-list', [
            'notice' => $notice,
            'token_rows' => $rows,
            'create_url' => $this->actionUrl('tokenEdit'),
        ]);
    }

    public function tokenEdit(array $instanceParams = []): string
    {
        $this->assertApiAccess();
        $tokenId = (int)$this->param('token', 0);
        $token = null;

        if ($tokenId > 0) {
            $token = $this->ownedToken($tokenId);
            if (!$this->isEditable($token)) {
                return \Core\Response::redirect($this->actionUrl('tokenList'));
            }
        }

        return $this->renderEditor($token);
    }

    public function tokenSave(array $instanceParams = []): string
    {
        $this->assertApiAccess();
        $data = array_replace($instanceParams, Request::all());
        $userId = User::getId();
        $tokenId = (int)($data['token_id'] ?? 0);
        $name = trim((string)($data['name'] ?? ''));

        if ($name === '') {
            return $this->renderEditor(
                $tokenId > 0 ? $this->ownedToken($tokenId) : null,
                $this->phrase('invalid_name', 'Token name is required.'),
                $data
            );
        }

        try {
            $expiresAt = $this->normalizeExpiry($data['expires_at'] ?? null);
        } catch (\InvalidArgumentException $error) {
            return $this->renderEditor(
                $tokenId > 0 ? $this->ownedToken($tokenId) : null,
                $error->getMessage(),
                $data
            );
        }

        $restrictions = $this->normalizeRestrictions($data);
        $restrictionsJson = \Core\Utils\JsonTool::encode($restrictions, false);

        if ($tokenId > 0) {
            $token = $this->ownedToken($tokenId);
            if (!$this->isEditable($token)) {
                throw new \RuntimeException('Expired or revoked API tokens cannot be edited.');
            }

            if (\DB::update(
                'api_tokens',
                [
                    'name' => $name,
                    'expires_at' => $expiresAt,
                    'restrictions' => $restrictionsJson,
                ],
                'token_id=$1 AND user_id=$2',
                [$tokenId, $userId]
            ) === false) {
                throw new \RuntimeException('Failed to update API token.');
            }

            return \Core\Response::redirect($this->actionUrl('tokenList', ['status' => 'updated']));
        }

        $secret = $this->generateToken();
        $created = \DB::insert(
            'api_tokens',
            [
                'user_id' => $userId,
                'name' => $name,
                'token_hash' => \Core\Crypto::tokenHash($secret),
                'token_hint' => substr($secret, -6),
                'restrictions' => $restrictionsJson,
                'expires_at' => $expiresAt,
            ],
            'token_id, created_at'
        );
        if (!is_array($created)) {
            throw new \RuntimeException('Failed to create API token.');
        }

        return $this->render('token-created', [
            'token' => \Core\Html::escape($secret),
            'done_url' => $this->actionUrl('tokenList'),
        ]);
    }

    public function tokenDisable(array $instanceParams = []): string
    {
        $this->assertApiAccess();
        $token = $this->ownedTokenFromRequest();
        if (!$this->isEditable($token)) {
            throw new \RuntimeException('Expired or revoked API tokens cannot be disabled.');
        }

        \DB::update(
            'api_tokens',
            ['is_enabled' => false],
            'token_id=$1 AND user_id=$2',
            [(int)$token['token_id'], User::getId()]
        );

        return \Core\Response::redirect($this->actionUrl('tokenList', ['status' => 'disabled']));
    }

    public function tokenEnable(array $instanceParams = []): string
    {
        $this->assertApiAccess();
        $token = $this->ownedTokenFromRequest();
        if (!$this->isEditable($token)) {
            throw new \RuntimeException('Expired or revoked API tokens cannot be enabled.');
        }

        \DB::update(
            'api_tokens',
            ['is_enabled' => true],
            'token_id=$1 AND user_id=$2',
            [(int)$token['token_id'], User::getId()]
        );

        return \Core\Response::redirect($this->actionUrl('tokenList', ['status' => 'enabled']));
    }

    public function tokenRevoke(array $instanceParams = []): string
    {
        $this->assertApiAccess();
        $token = $this->ownedTokenFromRequest();
        if (!$this->isEditable($token)) {
            throw new \RuntimeException('Expired or revoked API tokens cannot be revoked again.');
        }

        \DB::query(
            'UPDATE api_tokens
             SET is_enabled=false, revoked_at=CURRENT_TIMESTAMP
             WHERE token_id=$1 AND user_id=$2',
            [(int)$token['token_id'], User::getId()]
        );

        return \Core\Response::redirect($this->actionUrl('tokenList', ['status' => 'revoked']));
    }

    public function tokenDelete(array $instanceParams = []): string
    {
        $this->assertApiAccess();
        $token = $this->ownedTokenFromRequest();
        if ($this->isEditable($token)) {
            throw new \RuntimeException('Active or disabled API tokens must be revoked before deletion.');
        }

        if (\DB::delete(
            'api_tokens',
            'token_id=$1 AND user_id=$2',
            [(int)$token['token_id'], User::getId()]
        ) === false) {
            throw new \RuntimeException('Failed to delete API token.');
        }

        return \Core\Response::redirect($this->actionUrl('tokenList', ['status' => 'deleted']));
    }

    private function renderEditor(?array $token, ?string $error = null, array $submitted = []): string
    {
        $this->addCss('/plugins/ApiAccess/assets/api-access.css');
        $tokenId = (int)($token['token_id'] ?? 0);
        $restrictions = $submitted !== []
            ? $this->normalizeRestrictions($submitted)
            : $this->decodeRestrictions($token['restrictions'] ?? null);

        $nameValue = array_key_exists('name', $submitted)
            ? (string)$submitted['name']
            : (string)($token['name'] ?? '');
        $expiresValue = array_key_exists('expires_at', $submitted)
            ? (string)$submitted['expires_at']
            : $this->formatExpiryInput($token['expires_at'] ?? null);

        $fields = $this->forms()->renderField([
            'name' => 'name',
            'type' => 'string',
            'label' => $this->phrase('name', 'Name'),
            'description' => $this->phrase(
                'token_name_help',
                'Use a recognizable name such as Mobile app or CRM sync.'
            ),
            'value' => $nameValue,
            'required' => true,
        ]);
        $fields .= $this->forms()->renderField([
            'name' => 'expires_at',
            'type' => 'datetime',
            'label' => $this->phrase('expires_at', 'Expires at'),
            'description' => $this->phrase(
                'expires_help',
                'Leave empty for a token without an expiration date.'
            ),
            'value' => $expiresValue,
        ]);

        $tokenInfo = '';
        if ($token) {
            $tokenInfo = $this->render('token-info', [
                'token_hint' => \Core\Html::escape('kami_…' . (string)$token['token_hint']),
                'created_at' => \Core\Html::escape($this->formatter()->dateTime($token['created_at'] ?? null)),
            ]);
        }

        return $this->render('token-edit', [
            'page_title' => \Core\Html::escape(
                $token ? (string)$token['name'] : $this->phrase('create', 'Create token')
            ),
            'notice' => $error !== null && $error !== ''
                ? $this->render('notice-error', ['message' => \Core\Html::escape($error)])
                : '',
            'token_id' => (string)$tokenId,
            'fields' => $fields,
            'token_info' => $tokenInfo,
            'permissions_html' => $this->renderPermissions($restrictions),
            'save_url' => $this->actionUrl('tokenSave'),
            'back_url' => $this->actionUrl('tokenList'),
        ]);
    }

    private function renderPermissions(array $selected): string
    {
        $selectedActions = array_fill_keys($selected['actions'], true);
        $apiGroups = $this->availableApiActions();
        $apiHtml = '';

        foreach ($apiGroups as $group) {
            $apiHtml .= '<div class="aa-permission-group">'
                . '<h4>' . \Core\Html::escape($group['plugin_title']) . ' · '
                . \Core\Html::escape($group['handler_title']) . '</h4>';
            foreach ($group['actions'] as $action) {
                $id = (string)$action['id'];
                $apiHtml .= $this->permissionCheckbox(
                    'actions[]',
                    $id,
                    (string)$action['title'],
                    isset($selectedActions[$id]),
                    $id
                );
            }
            $apiHtml .= '</div>';
        }

        if ($apiHtml === '') {
            $apiHtml = '<p class="admin-page-description">'
                . \Core\Html::escape($this->phrase('no_api_actions', 'No API actions are currently available.'))
                . '</p>';
        }

        $contentHtml = '';
        foreach ($this->availableContentPermissions() as $type) {
            $typeName = (string)$type['system_name'];
            $selectedOps = array_fill_keys($selected['content'][$typeName] ?? [], true);
            $opsHtml = '';
            foreach ($type['operations'] as $operation) {
                $opsHtml .= $this->permissionCheckbox(
                    'content[' . $typeName . '][]',
                    $operation,
                    $this->operationTitle($operation),
                    isset($selectedOps[$operation])
                );
            }
            $contentHtml .= '<tr><td><strong>' . \Core\Html::escape((string)$type['title'])
                . '</strong><div class="admin-page-description">' . \Core\Html::escape($typeName)
                . '</div></td><td>' . $opsHtml . '</td></tr>';
        }

        if ($contentHtml === '') {
            $contentHtml = '<tr><td colspan="2" class="admin-page-description">'
                . \Core\Html::escape(
                    $this->phrase('no_content_permissions', 'No content permissions are currently available.')
                )
                . '</td></tr>';
        }

        return $this->render('permissions', [
            'api_actions' => $apiHtml,
            'content_rows' => $contentHtml,
        ]);
    }

    /**
     * @return list<array{plugin_title:string,handler_title:string,actions:list<array{id:string,title:string}>}>
     */
    private function availableApiActions(): array
    {
        $groups = [];
        $rows = \DB::query(
            'SELECT p.plugin_id, p.uuid, p.system_name, p.config
             FROM plugins p
             JOIN plugin_domains pd USING(plugin_id)
             WHERE p.is_active=true AND pd.domain_id=$1
             ORDER BY p.system_name',
            [DOMAIN_ID]
        );

        while ($plugin = \DB::fetchRow($rows)) {
            $config = \Core\Utils\JsonTool::decodeArray($plugin['config'] ?? null);
            $translation = Translation::get((string)$plugin['uuid']) ?? [];
            $pluginTitle = (string)($translation['title'] ?? $plugin['system_name']);

            foreach ($config['handlers'] ?? [] as $handler => $handlerConfig) {
                if (!is_string($handler) || !is_array($handlerConfig)) {
                    continue;
                }
                if (!User::canPlugin((int)$plugin['plugin_id'], $handler)) {
                    continue;
                }

                $actions = [];
                foreach ($handlerConfig['actions'] ?? [] as $action => $actionConfig) {
                    if (
                        !is_string($action)
                        || !is_array($actionConfig)
                        || ($actionConfig['api'] ?? false) !== true
                    ) {
                        continue;
                    }

                    $actionTranslation = $translation['handlers'][$handler]['actions'][$action] ?? [];
                    $actions[] = [
                        'id' => (string)$plugin['system_name'] . '.' . $action,
                        'title' => (string)($actionTranslation['title'] ?? $actionConfig['title'] ?? $action),
                    ];
                }

                if ($actions === []) {
                    continue;
                }

                $handlerTranslation = $translation['handlers'][$handler] ?? [];
                $groups[] = [
                    'plugin_title' => $pluginTitle,
                    'handler_title' => (string)(
                        $handlerTranslation['title'] ?? $handlerConfig['title'] ?? $handler
                    ),
                    'actions' => $actions,
                ];
            }
        }

        return $groups;
    }

    /**
     * @return list<array{system_name:string,title:string,operations:list<string>}>
     */
    private function availableContentPermissions(): array
    {
        $types = [];
        $rows = \DB::query('SELECT ct_id, uuid, system_name FROM content_types ORDER BY system_name');

        while ($type = \DB::fetchRow($rows)) {
            $operations = [];
            foreach (self::CONTENT_OPERATIONS as $operation) {
                if (User::canContent((int)$type['ct_id'], $operation)) {
                    $operations[] = $operation;
                }
            }
            if ($operations === []) {
                continue;
            }

            $translation = Translation::get((string)$type['uuid']) ?? [];
            $types[] = [
                'system_name' => (string)$type['system_name'],
                'title' => (string)($translation['title'] ?? $type['system_name']),
                'operations' => $operations,
            ];
        }

        return Translation::sortByTitle($types, 'title', 'system_name');
    }

    /**
     * @return array{actions:list<string>,content:array<string,list<string>>}
     */
    private function normalizeRestrictions(array $data): array
    {
        $availableActions = [];
        foreach ($this->availableApiActions() as $group) {
            foreach ($group['actions'] as $action) {
                $availableActions[(string)$action['id']] = true;
            }
        }

        $actions = [];
        foreach (is_array($data['actions'] ?? null) ? $data['actions'] : [] as $action) {
            $action = trim((string)$action);
            if ($action !== '' && isset($availableActions[$action])) {
                $actions[$action] = true;
            }
        }
        $actions = array_keys($actions);
        sort($actions, SORT_STRING);

        $submittedContent = is_array($data['content'] ?? null) ? $data['content'] : [];
        $content = [];
        foreach ($this->availableContentPermissions() as $type) {
            $typeName = (string)$type['system_name'];
            $availableOperations = array_fill_keys($type['operations'], true);
            $selected = is_array($submittedContent[$typeName] ?? null)
                ? $submittedContent[$typeName]
                : [];

            $operations = [];
            foreach (self::CONTENT_OPERATIONS as $operation) {
                if (isset($availableOperations[$operation]) && in_array($operation, $selected, true)) {
                    $operations[] = $operation;
                }
            }
            if ($operations !== []) {
                $content[$typeName] = $operations;
            }
        }
        ksort($content, SORT_STRING);

        return ['actions' => $actions, 'content' => $content];
    }

    /**
     * @return array{actions:list<string>,content:array<string,list<string>>}
     */
    private function decodeRestrictions(mixed $value): array
    {
        $data = \Core\Utils\JsonTool::decodeArray($value);
        $actions = array_values(array_unique(array_filter(
            array_map('strval', is_array($data['actions'] ?? null) ? $data['actions'] : []),
            static fn(string $value): bool => $value !== ''
        )));

        $content = [];
        foreach (is_array($data['content'] ?? null) ? $data['content'] : [] as $type => $operations) {
            if (!is_string($type) || !is_array($operations)) {
                continue;
            }
            $normalized = [];
            foreach (self::CONTENT_OPERATIONS as $operation) {
                if (in_array($operation, $operations, true)) {
                    $normalized[] = $operation;
                }
            }
            if ($normalized !== []) {
                $content[$type] = $normalized;
            }
        }

        return ['actions' => $actions, 'content' => $content];
    }

    private function tokenActions(int $tokenId, string $status): string
    {
        if ($status === 'revoked' || $status === 'expired') {
            return $this->render('token-actions', [
                'actions' => [
                    $this->tokenActionTemplate('token-action-delete', $tokenId, [
                        'confirm' => $this->phrase('confirm_delete', 'Delete this token record?'),
                    ]),
                ],
            ]);
        }

        $actions = [
            $this->tokenActionTemplate('token-action-edit', $tokenId),
        ];

        if ($status === 'disabled') {
            $actions[] = $this->tokenActionTemplate('token-action-enable', $tokenId);
        } else {
            $actions[] = $this->tokenActionTemplate('token-action-disable', $tokenId, [
                'confirm' => $this->phrase(
                    'confirm_disable',
                    'Temporarily disable this token? Requests will be rejected until it is enabled again.'
                ),
            ]);
        }

        $actions[] = $this->tokenActionTemplate('token-action-revoke', $tokenId, [
            'confirm' => $this->phrase(
                'confirm_revoke',
                'Revoke this token permanently? It cannot be restored.'
            ),
        ]);

        return $this->render('token-actions', ['actions' => $actions]);
    }

    private function tokenActionTemplate(
        string $template,
        int $tokenId,
        array $params = []
    ): array {
        $params['token_id'] = (string)$tokenId;
        $params['edit_url'] ??= $this->actionUrl('tokenEdit', ['token' => $tokenId]);
        $params['action_url'] ??= match ($template) {
            'token-action-enable' => $this->actionUrl('tokenEnable'),
            'token-action-disable' => $this->actionUrl('tokenDisable'),
            'token-action-revoke' => $this->actionUrl('tokenRevoke'),
            'token-action-delete' => $this->actionUrl('tokenDelete'),
            default => '',
        };

        if (isset($params['confirm'])) {
            $params['confirm'] = $this->escapeJsQuoted((string)$params['confirm']);
        }

        return [
            'template' => $template,
            'params' => $params,
        ];
    }

    private function permissionCheckbox(
        string $name,
        string $value,
        string $label,
        bool $checked,
        ?string $hint = null
    ): string {
        return '<label class="aa-permission-option">'
            . '<input type="checkbox" name="' . \Core\Html::escape($name) . '" value="'
            . \Core\Html::escape($value) . '"' . ($checked ? ' checked' : '') . '> '
            . '<span>' . \Core\Html::escape($label) . '</span>'
            . ($hint !== null
                ? '<code class="aa-permission-code">' . \Core\Html::escape($hint) . '</code>'
                : '')
            . '</label>';
    }

    private function normalizeExpiry(mixed $value): ?string
    {
        $raw = trim((string)$value);
        if ($raw === '') {
            return null;
        }

        try {
            $date = \Core\Date::fromFormat('!Y-m-d\\TH:i', $raw);
        } catch (\InvalidArgumentException) {
            throw new \InvalidArgumentException(
                $this->phrase('invalid_expiry', 'Expiration date must be in the future.')
            );
        }

        if ($date <= \Core\Date::value('now')) {
            throw new \InvalidArgumentException(
                $this->phrase('invalid_expiry', 'Expiration date must be in the future.')
            );
        }

        return \Core\Date::storage($date);
    }

    private function generateToken(): string
    {
        return 'kami_' . \Core\Crypto::randomBase64Url();
    }

    private function ownedTokenFromRequest(): array
    {
        $data = Request::all();
        return $this->ownedToken((int)($data['token_id'] ?? 0));
    }

    private function ownedToken(int $tokenId): array
    {
        $token = $tokenId > 0
            ? \DB::getRow(
                'SELECT * FROM api_tokens WHERE token_id=$1 AND user_id=$2',
                [$tokenId, User::getId()]
            )
            : null;

        if (!$token) {
            throw new \OutOfBoundsException($this->phrase('invalid_token', 'API token not found.'));
        }

        return $token;
    }

    private function isEditable(array $token): bool
    {
        if (!empty($token['revoked_at'])) {
            return false;
        }
        if (!empty($token['expires_at'])) {
            $expiresAt = \Core\Date::value((string)$token['expires_at']);
            if ($expiresAt !== null && $expiresAt <= \Core\Date::value('now')) {
                return false;
            }
        }
        return true;
    }

    private function assertApiAccess(): void
    {
        $group = User::getGroup();
        if (User::getId() < 1 || !$group || empty($group['has_api'])) {
            throw new PluginActionException(
                $this->phrase('access_denied', 'API access is not enabled for your user group.'),
                PluginActionException::FORBIDDEN
            );
        }
    }

    private function forms(): Forms
    {
        $forms = $this->forms ??= $this->plugins->get('Forms');
        if (!$forms instanceof Forms) {
            throw new \RuntimeException('Forms plugin is not available.');
        }
        return $forms;
    }

    private function formatter(): Formatter
    {
        $formatter = $this->formatter ??= $this->plugins->get('Formatter');
        if (!$formatter instanceof Formatter) {
            throw new \RuntimeException('Formatter plugin is not available.');
        }
        return $formatter;
    }

    private function statusNotice(string $status): string
    {
        $keys = [
            'updated' => 'updated_success',
            'disabled' => 'disabled_success',
            'enabled' => 'enabled_success',
            'revoked' => 'revoked_success',
            'deleted' => 'deleted_success',
        ];
        if (!isset($keys[$status])) {
            return '';
        }

        return $this->render('notice-success', [
            'message' => \Core\Html::escape($this->phrase($keys[$status], $status)),
        ]);
    }

    private function operationTitle(string $operation): string
    {
        return match ($operation) {
            'create' => $this->phrase('create_permission', 'Create'),
            'edit' => $this->phrase('edit_permission', 'Edit'),
            'delete' => $this->phrase('delete_permission', 'Delete'),
            default => $this->phrase('view', 'View'),
        };
    }

    private function replaceCount(string $key, string $fallback, int $count): string
    {
        return str_replace('{count}', (string)$count, $this->phrase($key, $fallback));
    }

    private function phrase(string $key, string $fallback): string
    {
        return (string)($this->phrases[$key] ?? $fallback);
    }

    private function formatExpiryInput(mixed $value): string
    {
        if ($value === null || $value === '') {
            return '';
        }

        return \Core\Date::format((string)$value, 'Y-m-d\\TH:i');
    }


private function escapeJsQuoted(string $value): string
    {
        return \Core\Html::escape(\Core\Utils\JsonTool::encodeForHtml($value));
    }
}
