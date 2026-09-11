<?php

/**
 * KamiCore
 *
 * SPDX-License-Identifier: Apache-2.0
 *
 * @see https://kamicore.org
 */

namespace Plugins\UserProfile;

if (!IN_KAMI) die();

class UserProfile extends \Core\BasePlugin
{
    public function statusbar(array $context_vars = []): string
    {
        $account = $this->account();

        if (\Core\User::getId()) {
            $menu = '';

			$menuId = \Core\Content::findByField('menu_key', 'user-context', 'navmenu')[0];

            if ($menuId > 0) {
                $navigation = $this->plugins->get('Navigation');
                if ($navigation instanceof \Plugins\Navigation\Navigation) {
                    $menu = $navigation->showMenu([
                        'menu_id' => $menuId,
                        'template' => 'context',
                    ]);
                }
            }

            $userActions = $this->render('authorized', [
                'user_context_menu' => $menu,
            ]);
            $username = (string)(\Core\User::$user['username'] ?? '');
        } else {
            $userActions = $this->render('guest', [
                'auth' => $account->getAuthUI(),
            ]);
            $username = $this->phrases['guest'] ?? '';
        }

        return $this->render('status', [
            'username' => $username,
            'user_actions_icon' => $userActions,
        ]);
    }

    public function sidebarMenu(array $context_vars = []): string
    {
        return $this->render('sidebar_menu', [
            'profile_page' => defined('PAGE_SLUG') ? PAGE_SLUG : '',
        ]);
    }

    public function preferences(array $context_vars = []): string
    {
        return $this->render('preferences', []);
    }

    public function credentials(array $context_vars = []): string
    {
        $returnUrl = $this->credentialReturnUrl(
            isset($context_vars['return_url'])
                ? (string)$context_vars['return_url']
                : null
        );

        return $this->render('credentials', [
            'content' => $this->renderCredentialsContent(
                returnUrl: $returnUrl
            ),
        ]);
    }

    public function changeUsername(?array $data): string
    {
        return $this->runCredentialAction(
            fn() => $this->account()->changeUsername((string)($data['username'] ?? '')),
            'username_changed',
            $this->credentialReturnUrl((string)($data['return_url'] ?? ''))
        );
    }

    public function changeEmail(?array $data): string
    {
        return $this->runCredentialAction(
            fn() => $this->account()->requestEmailChange((string)($data['email'] ?? '')),
            'email_change_sent',
            $this->credentialReturnUrl((string)($data['return_url'] ?? ''))
        );
    }

    public function changePassword(?array $data): string
    {
        return $this->runCredentialAction(
            fn() => $this->account()->changePassword(
                isset($data['current_password'])
                    ? (string)$data['current_password']
                    : null,
                (string)($data['new_password'] ?? ''),
                (string)($data['new_password_repeat'] ?? '')
            ),
            'password_changed',
            $this->credentialReturnUrl((string)($data['return_url'] ?? ''))
        );
    }

    public function removePassword(?array $data): string
    {
        return $this->runCredentialAction(
            fn() => $this->account()->removePassword(
                (string)($data['current_password'] ?? '')
            ),
            'password_removed',
            $this->credentialReturnUrl((string)($data['return_url'] ?? ''))
        );
    }

    public function disconnectProvider(?array $data): string
    {
        $provider = strtolower(trim((string)($data['provider'] ?? '')));

        return $this->runCredentialAction(
            fn() => $this->account()->disconnectProvider($provider),
            'provider_disconnected',
            $this->credentialReturnUrl((string)($data['return_url'] ?? ''))
        );
    }

    private function renderCredentialsContent(
        ?string $noticeType = null,
        ?string $noticeMessage = null,
        ?string $returnUrl = null
    ): string {
        try {
            $credentials = $this->account()->getCredentials();
        } catch (\Throwable $error) {
            $this->log('Failed to load credentials: ' . $error->getMessage(), 'error');

            return $this->render('credentials_error', [
                'message' => $this->escape($this->phrase('credentials_load_failed')),
            ]);
        }

        $authentication = is_array($credentials['authentication'] ?? null)
            ? $credentials['authentication']
            : [];
        $password = is_array($authentication['password'] ?? null)
            ? $authentication['password']
            : [];
        $providers = is_array($authentication['providers'] ?? null)
            ? $authentication['providers']
            : [];

        $passwordConfigured = !empty($password['configured']);
        $google = is_array($providers['google'] ?? null)
            ? $providers['google']
            : null;

        $notice = '';
        if ($noticeType !== null && $noticeMessage !== null && $noticeMessage !== '') {
            $notice = $this->render('credentials_notice', [
                'notice_class' => $noticeType === 'success'
                    ? 'kc-notice-success'
                    : 'kc-notice-error',
                'message' => $this->escape($noticeMessage),
            ]);
        }

        $pendingEmail = trim((string)($credentials['pending_email'] ?? ''));
        $pendingEmailHtml = $pendingEmail !== ''
            ? $this->render('pending_email', [
                'email' => $this->escape($pendingEmail),
            ])
            : '';

        $returnUrl = $this->credentialReturnUrl($returnUrl);
        $passwordTemplateParams = [
            'return_url' => $this->escape($returnUrl),
        ];
        $passwordHtml = $passwordConfigured
            ? $this->render('password_configured', $passwordTemplateParams)
            : $this->render('password_not_configured', $passwordTemplateParams);

        $googleHtml = $this->renderGoogleProvider($google, $returnUrl);

        return $this->render('credentials_content', [
            'notice' => $notice,
            'return_url' => $this->escape($returnUrl),
            'username' => $this->escape((string)($credentials['username'] ?? '')),
            'email' => $this->escape((string)($credentials['email'] ?? '')),
            'email_status' => $this->escape(
                !empty($credentials['email_verified'])
                    ? $this->phrase('verified')
                    : $this->phrase('not_verified')
            ),
            'email_status_class' => !empty($credentials['email_verified'])
                ? 'is-success'
                : 'is-warning',
            'pending_email' => $pendingEmailHtml,
            'password' => $passwordHtml,
            'google_provider' => $googleHtml,
        ]);
    }

    private function renderGoogleProvider(?array $google, string $returnUrl): string
    {
        $account = $this->account();

        if ($google !== null && !empty($google['connected'])) {
            $providerEmail = trim((string)($google['email'] ?? ''));
            $identity = $providerEmail !== ''
                ? $this->escape($providerEmail)
                : $this->escape($this->phrase('connected'));

            return $this->render('provider_connected', [
                'provider_name' => 'Google',
                'provider_identity' => $identity,
                'replace_url' => $this->escape(
                    $account->getProviderStartUrl(
                        'google',
                        'replace',
                        $returnUrl
                    )
                ),
                'provider' => 'google',
                'return_url' => $this->escape($returnUrl),
            ]);
        }

        return $this->render('provider_not_connected', [
            'provider_name' => 'Google',
            'connect_url' => $this->escape(
                $account->getProviderStartUrl(
                    'google',
                    'link',
                    $returnUrl
                )
            ),
        ]);
    }

    private function runCredentialAction(
        callable $action,
        string $successPhrase,
        string $returnUrl
    ): string
    {
        try {
            $action();

            return $this->renderCredentialsContent(
                'success',
                $this->phrase($successPhrase),
                $returnUrl
            );
        } catch (\Throwable $error) {
            $status = (int)$error->getCode();
            if ($status < 400 || $status >= 500) {
                $this->log('Credential action failed: ' . $error->getMessage(), 'error');
            }

            return $this->renderCredentialsContent(
                'error',
                $this->credentialErrorMessage($error),
                $returnUrl
            );
        }
    }

    private function credentialReturnUrl(?string $candidate = null): string
    {
        $candidate = $this->normalizeLocalReturnUrl($candidate);
        if ($candidate !== null) {
            return $candidate;
        }

        $referer = trim((string)($_SERVER['HTTP_REFERER'] ?? ''));
        if ($referer !== '') {
            $parts = parse_url($referer);
            $host = is_array($parts) ? strtolower((string)($parts['host'] ?? '')) : '';
            if ($host === strtolower((string)DOMAIN_NAME)) {
                $path = (string)($parts['path'] ?? '/');
                $query = trim((string)($parts['query'] ?? ''));
                $fromReferer = $path . ($query !== '' ? '?' . $query : '');
                $fromReferer = $this->normalizeLocalReturnUrl($fromReferer);
                if ($fromReferer !== null) {
                    return $fromReferer;
                }
            }
        }

        $path = \Core\Request::path();
        if (!str_starts_with($path, '/ajax/')) {
            return $this->normalizeLocalReturnUrl($path) ?? '/';
        }

        return '/';
    }

    private function normalizeLocalReturnUrl(?string $url): ?string
    {
        if ($url === null) {
            return null;
        }

        $url = trim($url);
        if (
            $url === ''
            || !str_starts_with($url, '/')
            || str_starts_with($url, '//')
            || str_contains($url, '\\')
        ) {
            return null;
        }

        $parts = parse_url($url);
        if (
            $parts === false
            || isset($parts['scheme'])
            || isset($parts['host'])
            || isset($parts['user'])
            || isset($parts['pass'])
        ) {
            return null;
        }

        return $url;
    }

    private function credentialErrorMessage(\Throwable $error): string
    {
        $map = [
            'Username is required.' => 'username_required',
            'Invalid username format.' => 'username_invalid',
            'This username is already in use.' => 'username_taken',
            'Email address is required.' => 'email_required',
            'Invalid email address.' => 'email_invalid',
            'The new email address is the same as the current address.' => 'email_same',
            'This email address is already in use.' => 'email_taken',
            'Password is required.' => 'password_required',
            'Password must be at least 8 characters long.' => 'password_short',
            'Passwords do not match.' => 'password_mismatch',
            'Current password is incorrect.' => 'current_password_invalid',
            'The last authentication method cannot be removed.' => 'last_auth_method',
            'The last authentication method cannot be disconnected.' => 'last_auth_method',
            'A Google account is already connected. Use replace instead.' => 'provider_already_connected',
            'This Google account is already connected to another user.' => 'provider_used_elsewhere',
        ];

        $key = $map[$error->getMessage()] ?? null;
        if ($key !== null) {
            return $this->phrase($key);
        }

        return $this->phrase('credentials_action_failed');
    }

    private function account(): \Plugins\UserAccount\UserAccount
    {
        $account = $this->plugins->get('UserAccount');
        if (!$account instanceof \Plugins\UserAccount\UserAccount) {
            throw new \RuntimeException('UserAccount plugin is required by UserProfile.');
        }

        return $account;
    }

    private function phrase(string $key): string
    {
        return (string)($this->phrases[$key] ?? $key);
    }

    private function escape(string $value): string
    {
        return htmlspecialchars(
            $value,
            ENT_QUOTES | ENT_SUBSTITUTE,
            'UTF-8'
        );
    }
}
