<?php

/**
 * KamiCore
 *
 * SPDX-License-Identifier: Apache-2.0
 *
 * @see https://kamicore.org
 */

namespace Plugins\UserAccount;

if(!IN_KAMI) die();

class UserAccount extends \Core\BasePlugin {

	private $errors = [];

    private const GOOGLE_SECRET_NAME = 'google.oauth_config';
    private const GOOGLE_CALLBACK_PATH = '/auth/google';
    private const GOOGLE_STATE_COOKIE = 'oauth_google_state';
    private const GOOGLE_SCOPE = 'openid email profile';
    private const OAUTH_STATE_TTL = 600;
    private const OAUTH_STATE_METHOD_PREFIX = 'oauth_state_';
    private const OAUTH_PURPOSE_LOGIN = 'login';
    private const OAUTH_PURPOSE_LINK = 'link';
    private const OAUTH_PURPOSE_REPLACE = 'replace';
    private const GOOGLE_AUTH_URI = 'https://accounts.google.com/o/oauth2/v2/auth';
    private const GOOGLE_TOKEN_URI = 'https://oauth2.googleapis.com/token';
    private const GOOGLE_USERINFO_URI = 'https://openidconnect.googleapis.com/v1/userinfo';
    private const EMAIL_VERIFICATION_METHOD = 'email_verification';
    private const EMAIL_VERIFICATION_TTL = 86400;
    private const EMAIL_CHANGE_METHOD = 'email_change';
    private const EMAIL_CHANGE_TTL = 86400;
    private const PASSWORD_RESET_METHOD = 'password_reset';
    private const PASSWORD_RESET_TTL = 3600;

	public function getAuthUI(array $context_vars = []): string {
        $this->addCss('/plugins/UserAccount/assets/user-account.css');
        if (!\Core\User::isGuest()) {
            $user = \Core\User::getUser();
            $username = \Core\Html::escape((string)($user['username'] ?? ''));

            return $this->render('auth_authorized', [
                'username' => $username,
                'logout_url' => '/auth/logout',
            ]);
        }

        $googleAuth = '';

        try {
            $googleConfig = $this->googleConfig();
            $this->assertGoogleRedirectUri($googleConfig);
            $googleUrl = $this->getProviderStartUrl(
                'google',
                self::OAUTH_PURPOSE_LOGIN,
                $this->loginReturnUrl($context_vars)
            );
            $googleAuth = $this->render('google_auth_button', [
                'google_url' => \Core\Html::escape($googleUrl),
            ]);
        } catch (\Throwable $error) {
            // $this->log(
            //     'Google authentication is unavailable: ' . $error->getMessage(),
            //     'warning'
            // );
        }

		return $this->render('auth_ui', [
            'google_auth' => $googleAuth,
        ]);
	}

	public function loginForm(array $context_vars = []): string {
		return $this->getAuthUI($context_vars);
	}

	public function register(?array $data): string
    {
        if (!(bool)$this->settingValue('registration_enabled', true)) {
            return $this->render('login_errors', [
                'msg' => $this->phrases['action_not_allowed'],
            ]);
        }

        if (!\Core\User::isGuest()) {
            return $this->render('login_errors', [
                'msg' => $this->phrases['action_not_allowed'],
            ]);
        }

        $username = trim((string)($data['username'] ?? ''));
        $email = strtolower(trim((string)($data['useremail'] ?? '')));
        $password = (string)($data['userpassword'] ?? '');
        $passwordRepeat = (string)($data['userpassword_repeat'] ?? '');
        $alerts = [];

        if ($username === '') {
            $alerts[] = $this->phrases['username_required'];
        } elseif (!preg_match('/^[A-Za-z0-9._-]+$/', $username)) {
            $alerts[] = $this->phrases['username_invalid'];
        } elseif (\DB::getOne('SELECT 1 FROM users WHERE username=$1 LIMIT 1', [$username])) {
            $alerts[] = $this->phrases['username_taken'];
        }

        if ($email === '') {
            $alerts[] = $this->phrases['email_required'];
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $alerts[] = $this->phrases['email_invalid'];
        } elseif (\DB::getOne(
            'SELECT 1 FROM users WHERE lower(email)=lower($1) LIMIT 1',
            [$email]
        )) {
            $alerts[] = $this->phrases['email_taken'];
        }

        if ($password === '') {
            $alerts[] = $this->phrases['password_required'];
        } elseif (strlen($password) < 8) {
            $alerts[] = $this->phrases['password_short'];
        }

        if ($password !== $passwordRepeat) {
            $alerts[] = $this->phrases['password_mismatch'];
        }

        if ($alerts !== []) {
            return $this->render('login_errors', [
                'msg' => implode('<br />', $alerts),
            ]);
        }

        $passwordHash = password_hash($password, PASSWORD_ARGON2ID);
        if (!is_string($passwordHash) || $passwordHash === '') {
            $this->log('Password hashing failed during registration.', 'error');
            return $this->render('login_errors', [
                'msg' => $this->phrases['registration_failed'],
            ]);
        }

        $emailActivation = (bool)$this->settingValue('email_activation', true);
        $adminActivationRequired = (bool)$this->settingValue(
            'admin_activation_required',
            false
        );
        $userId = null;

        try {
            $userId = \DB::insert('users', [
                'username' => $username,
                'email' => $email,
                'password_hash' => $passwordHash,
                'is_active' => !$adminActivationRequired,
                'email_verified_at' => null,
                'usergroup_id' => (int)GLOBAL_SETTINGS['usergroup_default'],
            ], 'user_id');

            if ($userId === false || (int)$userId < 1) {
                throw new \RuntimeException('Failed to create user account.');
            }
            $userId = (int)$userId;

            if ($emailActivation) {
                $this->sendVerificationEmail($userId);

                return $this->render('register_result', [
                    'msg' => $this->phrases['verification_email_sent'],
                ]);
            }

            if ($adminActivationRequired) {
                return $this->render('register_result', [
                    'msg' => $this->phrases['registration_pending_approval'],
                ]);
            }

            \Core\Session::authorize($userId, false);
            return $this->renderLoginResult($this->phrases['registration_success']);
        } catch (\Throwable $error) {
            $this->log('Registration failed: ' . $error->getMessage(), 'error');

            if ($userId !== null) {
                \DB::query(
                    'DELETE FROM tokens WHERE user_id=$1 AND method=$2',
                    [$userId, self::EMAIL_VERIFICATION_METHOD]
                );
                \DB::delete('users', 'user_id=$1', [$userId]);
            }

            return $this->render('login_errors', [
                'msg' => $this->phrases['registration_failed'],
            ]);
        }
	}

	public function login(?array $data): string
    {
        if (!\Core\User::isGuest()) {
            return $this->getAuthUI();
        }

        $login = trim((string)($data['login'] ?? ''));
        $password = (string)($data['password'] ?? '');
        $remember = !empty($data['remember']);

        if ($login === '' || $password === '') {
            return $this->render('login_errors', [
                'msg' => $this->phrases['login_failed'],
            ]);
        }

        $where = filter_var($login, FILTER_VALIDATE_EMAIL)
            ? 'lower(email)=lower($1)'
            : 'username=$1';
        $userdata = \DB::getRow(
            "SELECT user_id, password_hash, is_active, email_verified_at FROM users WHERE {$where} LIMIT 1",
            [$login]
        );
        $passwordHash = is_array($userdata)
            ? (string)($userdata['password_hash'] ?? '')
            : '';

        if (
            $userdata
            && $passwordHash !== ''
            && password_verify($password, $passwordHash)
        ) {
            $emailVerificationRequired = (bool)$this->settingValue('email_activation', true);
            if ($emailVerificationRequired && empty($userdata['email_verified_at'])) {
                return $this->render('login_unverified', [
                    'msg' => $this->phrases['login_unverified'],
                    'identity' => \Core\Html::escape($login),
                ]);
            }

            if (empty($userdata['is_active'])) {
                return $this->render('login_errors', [
                    'msg' => $this->phrases['login_inactive'],
                ]);
            }

            \Core\Session::authorize((int)$userdata['user_id'], $remember);
            return $this->renderLoginResult($this->phrases['login_success']);
        }

        return $this->render('login_errors', [
            'msg' => $this->phrases['login_failed'],
        ]);
	}

    public function requestPasswordReset(?array $data): string
    {
        $identity = trim((string)(
            $data['identity']
            ?? $data['email']
            ?? $data['login']
            ?? ''
        ));

        if ($identity !== '') {
            $where = filter_var($identity, FILTER_VALIDATE_EMAIL)
                ? 'lower(email)=lower($1)'
                : 'username=$1';
            $user = \DB::getRow(
                "SELECT user_id, email FROM users WHERE {$where} LIMIT 1",
                [$identity]
            );

            if (
                $user
                && filter_var((string)($user['email'] ?? ''), FILTER_VALIDATE_EMAIL)
            ) {
                try {
                    $this->sendPasswordResetEmail((int)$user['user_id']);
                } catch (\Throwable $error) {
                    $this->log(
                        'Failed to send password reset email: ' . $error->getMessage(),
                        'error'
                    );
                }
            }
        }

        return $this->render('password_reset_request_result', [
            'msg' => $this->phrases['password_reset_request_neutral'],
        ]);
    }

    public function resetPassword(?array $data): string
    {
        $rawToken = trim((string)($data['token'] ?? ''));
        $password = (string)($data['password'] ?? '');
        $passwordRepeat = (string)($data['password_repeat'] ?? '');
        $errors = [];

        if ($rawToken === '') {
            $errors[] = $this->phrases['password_reset_invalid_or_expired'];
        }
        if ($password === '') {
            $errors[] = $this->phrases['password_required'];
        } elseif (strlen($password) < 8) {
            $errors[] = $this->phrases['password_short'];
        }
        if ($password !== $passwordRepeat) {
            $errors[] = $this->phrases['password_mismatch'];
        }

        if ($errors !== []) {
            return $this->render('password_reset_form_error', [
                'msg' => implode('<br />', $errors),
            ]);
        }

        $passwordHash = password_hash($password, PASSWORD_ARGON2ID);
        if (!is_string($passwordHash) || $passwordHash === '') {
            $this->log('Password hashing failed during password reset.', 'error');
            return $this->render('password_reset_form_error', [
                'msg' => $this->phrases['password_reset_failed'],
            ]);
        }

        $tokenHash = \Core\Crypto::tokenHash($rawToken);
        if (!\DB::beginTransaction()) {
            return $this->render('password_reset_form_error', [
                'msg' => $this->phrases['password_reset_failed'],
            ]);
        }

        try {
            $token = \DB::getRow(
                'SELECT token_id, user_id, expires_at
                 FROM tokens
                 WHERE method=$1 AND token=$2
                 LIMIT 1
                 FOR UPDATE',
                [self::PASSWORD_RESET_METHOD, $tokenHash]
            );

            if (!$token) {
                \DB::rollBack();
                return $this->render('password_reset_form_error', [
                    'msg' => $this->phrases['password_reset_invalid_or_expired'],
                ]);
            }

            $expiresAt = (string)($token['expires_at'] ?? '');
            if ($expiresAt !== '' && strtotime($expiresAt) < TIME_NOW) {
                \DB::query('DELETE FROM tokens WHERE token_id=$1', [(int)$token['token_id']]);
                if (!\DB::commit()) {
                    throw new \RuntimeException('Failed to commit expired password reset token cleanup.');
                }
                return $this->render('password_reset_form_error', [
                    'msg' => $this->phrases['password_reset_invalid_or_expired'],
                ]);
            }

            $userId = (int)$token['user_id'];
            if (\DB::query(
                'UPDATE users SET password_hash=$1 WHERE user_id=$2',
                [$passwordHash, $userId]
            ) === false) {
                throw new \RuntimeException('Failed to update user password.');
            }

            if (\DB::query(
                'DELETE FROM tokens WHERE user_id=$1 AND method=$2',
                [$userId, self::PASSWORD_RESET_METHOD]
            ) === false) {
                throw new \RuntimeException('Failed to consume password reset tokens.');
            }

            \Core\Session::invalidateUserSessions($userId);

            if (!\DB::commit()) {
                throw new \RuntimeException('Failed to commit password reset.');
            }

            $this->clearAuthenticatedNotifications($userId);

            return $this->render('password_reset_form_success', [
                'msg' => $this->phrases['password_reset_success'],
            ]);
        } catch (\Throwable $error) {
            \DB::rollBack();
            $this->log('Password reset failed: ' . $error->getMessage(), 'error');
            return $this->render('password_reset_form_error', [
                'msg' => $this->phrases['password_reset_failed'],
            ]);
        }
    }

    public function resendVerification(?array $data): string
    {
        $identity = trim((string)(
            $data['identity']
            ?? $data['email']
            ?? $data['login']
            ?? ''
        ));

        if (
            (bool)$this->settingValue('email_activation', true)
            && $identity !== ''
        ) {
            $where = filter_var($identity, FILTER_VALIDATE_EMAIL)
                ? 'lower(email)=lower($1)'
                : 'username=$1';

            $user = \DB::getRow(
                "SELECT user_id, email, email_verified_at
                 FROM users
                 WHERE {$where}
                 LIMIT 1",
                [$identity]
            );

            if (
                $user
                && empty($user['email_verified_at'])
                && filter_var((string)($user['email'] ?? ''), FILTER_VALIDATE_EMAIL)
            ) {
                try {
                    $this->sendVerificationEmail((int)$user['user_id']);
                } catch (\Throwable $error) {
                    $this->log(
                        'Failed to resend verification email: ' . $error->getMessage(),
                        'error'
                    );
                }
            }
        }

        return $this->render('verification_resend_result', [
            'msg' => $this->phrases['verification_resend_neutral'],
        ]);
    }

    public function logout(?array $data = null): void
    {
        // Plugin endpoints do not initialize user/session state automatically.
        \Core\User::init();

        $userId = \Core\User::getId();
        $wasAuthenticated = $userId > 0;

        if (isset(\Core\PluginRegistry::forDomain()['Notifications'])) {
            try {
                $notifications = $this->plugins->get('Notifications');
                if ($notifications) {
                    if ($wasAuthenticated) {
                        $notifications->clearCurrentUser($userId);
                        $notifications->store(
                            null,
                            $this->phrases['logout_success'],
                            'success'
                        );
                    }
                }
            } catch (\Throwable $error) {
                $this->log(
                    'Failed to prepare logout notification: ' . $error->getMessage(),
                    'warning'
                );
            }
        }

        \Core\Session::logout();

        \Core\Response::redirect('/');
        \Core\Response::addHeader('Cache-Control: no-store, no-cache, must-revalidate');
        \Core\Response::send('');
    }

	public function desktopStatus() {
		return \Core\Renderer::render("status_desktop", "UserAccount");
	}

    public function sendPasswordResetEmail(int $userId): bool
    {
        $user = \DB::getRow(
            'SELECT user_id, username, email FROM users WHERE user_id=$1 LIMIT 1',
            [$userId]
        );
        if (!$user) {
            throw new \RuntimeException('User account not found.');
        }

        $email = strtolower(trim((string)($user['email'] ?? '')));
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new \RuntimeException('User account has no valid email address.');
        }

        $mailer = $this->plugins->get('Mailer');
        if (!$mailer instanceof \Plugins\Mailer\Mailer) {
            throw new \RuntimeException('Mailer plugin is required for password reset.');
        }

        $rawToken = \Core\Crypto::randomHex();
        $tokenHash = \Core\Crypto::tokenHash($rawToken);
        $expiresAt = date('Y-m-d H:i:s', TIME_NOW + self::PASSWORD_RESET_TTL);

        if (\DB::insert('tokens', [
            'user_id' => $userId,
            'method' => self::PASSWORD_RESET_METHOD,
            'token' => $tokenHash,
            'expires_at' => $expiresAt,
        ]) === false) {
            throw new \RuntimeException('Failed to create password reset token.');
        }

        $resetUrl = \Core\Request::scheme()
            . '://' . DOMAIN_NAME
            . '/auth/reset-password?token=' . rawurlencode($rawToken);
        $username = (string)$user['username'];
        $siteName = DOMAIN_NAME;

        try {
            $message = $mailer->createMessage()
                ->addTo($email, $username)
                ->setSubject($this->phrases['password_reset_email_subject'])
                ->setHtmlBody($this->render('password_reset_email_html', [
                    'username' => \Core\Html::escape($username),
                    'reset_url' => \Core\Html::escape($resetUrl),
                    'site_name' => \Core\Html::escape($siteName),
                ]))
                ->setTextBody($this->render('password_reset_email_txt', [
                    'username' => $username,
                    'reset_url' => $resetUrl,
                    'site_name' => $siteName,
                ]));

            if (!$mailer->send($message)) {
                throw new \RuntimeException('Mailer did not confirm password reset email delivery.');
            }
        } catch (\Throwable $error) {
            \DB::query(
                'DELETE FROM tokens WHERE user_id=$1 AND method=$2 AND token=$3',
                [$userId, self::PASSWORD_RESET_METHOD, $tokenHash]
            );
            throw $error;
        }

        $cleanup = \DB::query(
            'DELETE FROM tokens
             WHERE user_id=$1
               AND method=$2
               AND token<>$3',
            [$userId, self::PASSWORD_RESET_METHOD, $tokenHash]
        );
        if ($cleanup === false) {
            $this->log(
                'Password reset email sent, but previous reset tokens could not be removed.',
                'warning'
            );
        }

        return true;
    }

	public function sendVerificationEmail(int $userId): bool
    {
        $user = \DB::getRow(
            'SELECT user_id, username, email FROM users WHERE user_id=$1 LIMIT 1',
            [$userId]
        );
        if (!$user) {
            throw new \RuntimeException('User account not found.');
        }

        $email = strtolower(trim((string)($user['email'] ?? '')));
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new \RuntimeException('User account has no valid email address.');
        }

        $mailer = $this->plugins->get('Mailer');
        if (!$mailer instanceof \Plugins\Mailer\Mailer) {
            throw new \RuntimeException('Mailer plugin is required for email verification.');
        }

        $rawToken = \Core\Crypto::randomHex();
        $tokenHash = \Core\Crypto::tokenHash($rawToken);
        $expiresAt = date('Y-m-d H:i:s', TIME_NOW + self::EMAIL_VERIFICATION_TTL);

        $inserted = \DB::insert('tokens', [
            'user_id' => $userId,
            'method' => self::EMAIL_VERIFICATION_METHOD,
            'token' => $tokenHash,
            'expires_at' => $expiresAt,
        ]);
        if ($inserted === false) {
            throw new \RuntimeException('Failed to create email verification token.');
        }

        $verificationUrl = \Core\Request::scheme()
            . '://' . DOMAIN_NAME
            . '/auth/verify-email?token=' . rawurlencode($rawToken);
        $username = (string)$user['username'];
        $siteName = DOMAIN_NAME;

        try {
            $message = $mailer->createMessage()
                ->addTo($email, $username)
                ->setSubject($this->phrases['verification_email_subject'])
                ->setHtmlBody($this->render('verify_email_html', [
                    'username' => \Core\Html::escape($username),
                    'verification_url' => \Core\Html::escape($verificationUrl),
                    'site_name' => \Core\Html::escape($siteName),
                ]))
                ->setTextBody($this->render('verify_email_txt', [
                    'username' => $username,
                    'verification_url' => $verificationUrl,
                    'site_name' => $siteName,
                ]));

            if (!$mailer->send($message)) {
                throw new \RuntimeException('Mailer did not confirm email delivery.');
            }
        } catch (\Throwable $error) {
            \DB::query(
                'DELETE FROM tokens WHERE user_id=$1 AND method=$2 AND token=$3',
                [$userId, self::EMAIL_VERIFICATION_METHOD, $tokenHash]
            );
            throw $error;
        }

        $cleanup = \DB::query(
            'DELETE FROM tokens
             WHERE user_id=$1
               AND method IN ($2, $3)
               AND token<>$4',
            [$userId, 'email', self::EMAIL_VERIFICATION_METHOD, $tokenHash]
        );
        if ($cleanup === false) {
            $this->log(
                'Verification email sent, but previous verification tokens could not be removed.',
                'warning'
            );
        }

        return true;
	}

    private function handleEmailVerification(): void
    {
        $request = \Core\Request::all();
        $rawToken = trim((string)($request['token'] ?? ''));
        if ($rawToken === '') {
            $this->sendVerificationResult(
                false,
                $this->phrases['verification_invalid_or_used'],
                400,
                true
            );
            return;
        }

        $tokenHash = \Core\Crypto::tokenHash($rawToken);

        if (!\DB::beginTransaction()) {
            $this->sendVerificationResult(
                false,
                $this->phrases['verification_failed'],
                500
            );
            return;
        }

        try {
            $token = \DB::getRow(
                'SELECT t.token_id, t.user_id, t.expires_at, u.is_active, u.email_verified_at
                 FROM tokens t
                 JOIN users u ON u.user_id=t.user_id
                 WHERE t.method=$1 AND t.token=$2
                 LIMIT 1
                 FOR UPDATE',
                [self::EMAIL_VERIFICATION_METHOD, $tokenHash]
            );

            if (!$token) {
                \DB::rollBack();
                $this->sendVerificationResult(
                    false,
                    $this->phrases['verification_invalid_or_used'],
                    400,
                    true
                );
                return;
            }

            $userId = (int)$token['user_id'];
            $expiresAt = (string)($token['expires_at'] ?? '');
            if ($expiresAt !== '' && strtotime($expiresAt) < TIME_NOW) {
                \DB::query('DELETE FROM tokens WHERE token_id=$1', [(int)$token['token_id']]);
                if (!\DB::commit()) {
                    throw new \RuntimeException('Failed to commit expired verification token cleanup.');
                }
                $this->sendVerificationResult(
                    false,
                    $this->phrases['verification_expired'],
                    410,
                    true
                );
                return;
            }

            if (\DB::query(
                'UPDATE users SET email_verified_at=CURRENT_TIMESTAMP WHERE user_id=$1',
                [$userId]
            ) === false) {
                throw new \RuntimeException('Failed to mark user email as verified.');
            }

            if (\DB::query(
                'DELETE FROM tokens WHERE user_id=$1 AND method=$2',
                [$userId, self::EMAIL_VERIFICATION_METHOD]
            ) === false) {
                throw new \RuntimeException('Failed to consume email verification token.');
            }

            if (!\DB::commit()) {
                throw new \RuntimeException('Failed to commit email verification.');
            }

            $this->refreshCurrentUserCache($userId);

            if (empty($token['is_active'])) {
                $this->sendAccountInactivePage();
                return;
            }

            \Core\User::init();
            \Core\Session::authorize($userId, false);

            $this->sendVerificationResult(
                true,
                $this->phrases['verification_success_login'],
                200
            );
        } catch (\Throwable $error) {
            \DB::rollBack();
            $this->log('Email verification failed: ' . $error->getMessage(), 'error');
            $this->sendVerificationResult(
                false,
                $this->phrases['verification_failed'],
                500
            );
        }
    }

    private function sendVerificationResult(
        bool $success,
        string $message,
        int $status,
        bool $allowResend = false
    ): void {
        $homeUrl = '/';
        $template = $success ? 'verification_success_page' : 'verification_error_page';
        $resendForm = $allowResend
            ? $this->render('verification_resend_form', [
                'ajax_url' => '/ajax/UserAccount/resend_verification',
            ])
            : '';
        $content = $this->render($template, [
            'message' => \Core\Html::escape($message),
            'resend_form' => $resendForm,
            'home_url' => \Core\Html::escape($homeUrl),
            'language' => \Core\Html::escape((string)(defined('LANG') ? LANG : (DOMAIN_CONFIG['default_language'] ?? 'en'))),
        ]);

        \Core\Response::addHeader(
            'Content-Type: text/html; charset=utf-8',
            true,
            $status
        );
        \Core\Response::addHeader('Cache-Control: no-store, no-cache, must-revalidate');
        \Core\Response::addHeader('X-Powered-By: Kami');
        \Core\Response::send(\Core\Renderer::finalize($content));
	}


    private function sendAccountInactivePage(int $status = 403): void
    {
        $content = $this->render('account_inactive_page', [
            'home_url' => '/',
            'language' => \Core\Html::escape((string)(defined('LANG') ? LANG : (DOMAIN_CONFIG['default_language'] ?? 'en'))),
        ]);

        \Core\Response::addHeader(
            'Content-Type: text/html; charset=utf-8',
            true,
            $status
        );
        \Core\Response::addHeader('Cache-Control: no-store, no-cache, must-revalidate');
        \Core\Response::addHeader('X-Powered-By: Kami');
        \Core\Response::send(\Core\Renderer::finalize($content));
    }


    private function handlePasswordReset(): void
    {
        $rawToken = trim((string)(\Core\Request::all()['token'] ?? ''));
        if ($rawToken === '') {
            $this->sendPasswordResetError(400);
            return;
        }

        $tokenHash = \Core\Crypto::tokenHash($rawToken);
        $token = \DB::getRow(
            'SELECT token_id, expires_at
             FROM tokens
             WHERE method=$1 AND token=$2
             LIMIT 1',
            [self::PASSWORD_RESET_METHOD, $tokenHash]
        );

        if (!$token) {
            $this->sendPasswordResetError(400);
            return;
        }

        $expiresAt = (string)($token['expires_at'] ?? '');
        if ($expiresAt !== '' && strtotime($expiresAt) < TIME_NOW) {
            \DB::query('DELETE FROM tokens WHERE token_id=$1', [(int)$token['token_id']]);
            $this->sendPasswordResetError(410);
            return;
        }

        $content = $this->render('password_reset_page', [
            'token' => \Core\Html::escape($rawToken),
            'ajax_url' => '/ajax/UserAccount/reset_password',
            'home_url' => '/',
            'language' => \Core\Html::escape((string)(defined('LANG') ? LANG : (DOMAIN_CONFIG['default_language'] ?? 'en'))),
        ]);

        \Core\Response::addHeader('Content-Type: text/html; charset=utf-8', true, 200);
        \Core\Response::addHeader('Cache-Control: no-store, no-cache, must-revalidate');
        \Core\Response::addHeader('X-Powered-By: Kami');
        \Core\Response::send(\Core\Renderer::finalize($content));
    }

    private function sendPasswordResetError(int $status): void
    {
        $content = $this->render('password_reset_error_page', [
            'message' => \Core\Html::escape($this->phrases['password_reset_invalid_or_expired']),
            'home_url' => '/',
            'language' => \Core\Html::escape((string)(defined('LANG') ? LANG : (DOMAIN_CONFIG['default_language'] ?? 'en'))),
        ]);

        \Core\Response::addHeader(
            'Content-Type: text/html; charset=utf-8',
            true,
            $status
        );
        \Core\Response::addHeader('Cache-Control: no-store, no-cache, must-revalidate');
        \Core\Response::addHeader('X-Powered-By: Kami');
        \Core\Response::send(\Core\Renderer::finalize($content));
    }

    /**
     * Remove authenticated notifications for a user after credential reset.
     * Notifications remains an optional service.
     */
    private function clearAuthenticatedNotifications(int $userId): void
    {
        if ($userId < 1 || !isset(\Core\PluginRegistry::forDomain()['Notifications'])) {
            return;
        }

        try {
            $notifications = $this->plugins->get('Notifications');
            if (!$notifications) {
                return;
            }

            $notifications->clearUser($userId);
        } catch (\Throwable $error) {
            $this->log(
                'Failed to clear notifications after password reset: ' . $error->getMessage(),
                'warning'
            );
        }
    }

    /**
     * Return credential data for the current authenticated user.
     */
    public function getCredentials(): array
    {
        $userId = $this->requireAuthenticatedUserId();
        $user = \DB::getRow(
            'SELECT username, email, email_verified_at
             FROM users
             WHERE user_id=$1
             LIMIT 1',
            [$userId]
        );
        if (!$user) {
            throw new \RuntimeException('User account not found.', 404);
        }

        $pendingEmail = null;
        $pending = \DB::getRow(
            'SELECT token_data
             FROM tokens
             WHERE user_id=$1
               AND method=$2
               AND (expires_at IS NULL OR expires_at >= CURRENT_TIMESTAMP)
             ORDER BY token_id DESC
             LIMIT 1',
            [$userId, self::EMAIL_CHANGE_METHOD]
        );
        if ($pending) {
            $tokenData = \Core\Utils\JsonTool::decodeArray($pending['token_data'] ?? null);
            $candidate = is_array($tokenData)
                ? strtolower(trim((string)($tokenData['email'] ?? '')))
                : '';
            if (filter_var($candidate, FILTER_VALIDATE_EMAIL)) {
                $pendingEmail = $candidate;
            }
        }

        return [
            'username' => (string)$user['username'],
            'email' => (string)($user['email'] ?? ''),
            'email_verified' => !empty($user['email_verified_at']),
            'pending_email' => $pendingEmail,
            'authentication' => $this->getAuthenticationMethods(),
        ];
    }

    public function changeUsername(string $username): bool
    {
        $userId = $this->requireAuthenticatedUserId();
        $username = trim($username);

        if ($username === '') {
            throw new \RuntimeException('Username is required.', 400);
        }
        if (!preg_match('/^[A-Za-z0-9._-]+$/', $username)) {
            throw new \RuntimeException('Invalid username format.', 400);
        }

        if (!\DB::beginTransaction()) {
            throw new \RuntimeException('Failed to start credential transaction.');
        }

        try {
            $user = \DB::getRow(
                'SELECT username FROM users WHERE user_id=$1 LIMIT 1 FOR UPDATE',
                [$userId]
            );
            if (!$user) {
                throw new \RuntimeException('User account not found.', 404);
            }

            if ((string)$user['username'] === $username) {
                if (!\DB::commit()) {
                    throw new \RuntimeException('Failed to commit credential transaction.');
                }
                return true;
            }

            if (\DB::getOne(
                'SELECT 1 FROM users WHERE username=$1 AND user_id<>$2 LIMIT 1',
                [$username, $userId]
            )) {
                throw new \RuntimeException('This username is already in use.', 409);
            }

            if (\DB::query(
                'UPDATE users SET username=$1 WHERE user_id=$2',
                [$username, $userId]
            ) === false) {
                throw new \RuntimeException('Failed to update username.');
            }

            if (!\DB::commit()) {
                throw new \RuntimeException('Failed to commit credential transaction.');
            }

            $this->refreshCurrentUserCache($userId);
            return true;
        } catch (\Throwable $error) {
            \DB::rollBack();
            throw $error;
        }
    }

    /**
     * Set a password for a provider-only account or change an existing password.
     */
    public function changePassword(
        ?string $currentPassword,
        string $newPassword,
        string $newPasswordRepeat
    ): bool {
        $userId = $this->requireAuthenticatedUserId();

        if ($newPassword === '') {
            throw new \RuntimeException('Password is required.', 400);
        }
        if (strlen($newPassword) < 8) {
            throw new \RuntimeException('Password must be at least 8 characters long.', 400);
        }
        if ($newPassword !== $newPasswordRepeat) {
            throw new \RuntimeException('Passwords do not match.', 400);
        }

        $passwordHash = password_hash($newPassword, PASSWORD_ARGON2ID);
        if (!is_string($passwordHash) || $passwordHash === '') {
            throw new \RuntimeException('Password hashing failed.');
        }

        if (!\DB::beginTransaction()) {
            throw new \RuntimeException('Failed to start credential transaction.');
        }

        try {
            $user = \DB::getRow(
                'SELECT password_hash, is_active
                 FROM users
                 WHERE user_id=$1
                 LIMIT 1
                 FOR UPDATE',
                [$userId]
            );
            if (!$user || empty($user['is_active'])) {
                throw new \RuntimeException('User account is not active.', 403);
            }

            $existingHash = trim((string)($user['password_hash'] ?? ''));
            if ($existingHash !== '') {
                if (
                    $currentPassword === null
                    || $currentPassword === ''
                    || !password_verify($currentPassword, $existingHash)
                ) {
                    throw new \RuntimeException('Current password is incorrect.', 400);
                }
            }

            if (\DB::query(
                'UPDATE users SET password_hash=$1 WHERE user_id=$2',
                [$passwordHash, $userId]
            ) === false) {
                throw new \RuntimeException('Failed to update password.');
            }

            if (\DB::query(
                'DELETE FROM tokens WHERE user_id=$1 AND method=$2',
                [$userId, self::PASSWORD_RESET_METHOD]
            ) === false) {
                throw new \RuntimeException('Failed to invalidate password reset tokens.');
            }

            if (!\DB::commit()) {
                throw new \RuntimeException('Failed to commit credential transaction.');
            }

            return true;
        } catch (\Throwable $error) {
            \DB::rollBack();
            throw $error;
        }
    }

    /**
     * Remove password authentication while keeping at least one external provider.
     */
    public function removePassword(string $currentPassword): bool
    {
        $userId = $this->requireAuthenticatedUserId();

        if (!\DB::beginTransaction()) {
            throw new \RuntimeException('Failed to start credential transaction.');
        }

        try {
            $user = \DB::getRow(
                'SELECT password_hash, is_active
                 FROM users
                 WHERE user_id=$1
                 LIMIT 1
                 FOR UPDATE',
                [$userId]
            );
            if (!$user || empty($user['is_active'])) {
                throw new \RuntimeException('User account is not active.', 403);
            }

            $passwordHash = trim((string)($user['password_hash'] ?? ''));
            if ($passwordHash === '') {
                if (!\DB::commit()) {
                    throw new \RuntimeException('Failed to commit credential transaction.');
                }
                return true;
            }

            if ($currentPassword === '' || !password_verify($currentPassword, $passwordHash)) {
                throw new \RuntimeException('Current password is incorrect.', 400);
            }

            $providerCount = (int)\DB::getOne(
                'SELECT count(*) FROM user_auth_identities WHERE user_id=$1',
                [$userId]
            );
            if ($providerCount < 1) {
                throw new \RuntimeException(
                    'The last authentication method cannot be removed.',
                    409
                );
            }

            if (\DB::query(
                'UPDATE users SET password_hash=NULL WHERE user_id=$1',
                [$userId]
            ) === false) {
                throw new \RuntimeException('Failed to remove password authentication.');
            }

            if (\DB::query(
                'DELETE FROM tokens WHERE user_id=$1 AND method=$2',
                [$userId, self::PASSWORD_RESET_METHOD]
            ) === false) {
                throw new \RuntimeException('Failed to invalidate password reset tokens.');
            }

            if (!\DB::commit()) {
                throw new \RuntimeException('Failed to commit credential transaction.');
            }

            return true;
        } catch (\Throwable $error) {
            \DB::rollBack();
            throw $error;
        }
    }

    /**
     * Send a confirmation link to a new address without changing the current email.
     */
    public function requestEmailChange(string $newEmail): bool
    {
        $userId = $this->requireAuthenticatedUserId();
        $newEmail = strtolower(trim($newEmail));

        if ($newEmail === '') {
            throw new \RuntimeException('Email address is required.', 400);
        }
        if (!filter_var($newEmail, FILTER_VALIDATE_EMAIL)) {
            throw new \RuntimeException('Invalid email address.', 400);
        }

        $user = \DB::getRow(
            'SELECT username, email, is_active
             FROM users
             WHERE user_id=$1
             LIMIT 1',
            [$userId]
        );
        if (!$user || empty($user['is_active'])) {
            throw new \RuntimeException('User account is not active.', 403);
        }

        $currentEmail = strtolower(trim((string)($user['email'] ?? '')));
        if ($currentEmail === $newEmail) {
            throw new \RuntimeException(
                'The new email address is the same as the current address.',
                409
            );
        }

        if (\DB::getOne(
            'SELECT 1 FROM users WHERE lower(email)=lower($1) AND user_id<>$2 LIMIT 1',
            [$newEmail, $userId]
        )) {
            throw new \RuntimeException('This email address is already in use.', 409);
        }

        $mailer = $this->plugins->get('Mailer');
        if (!$mailer instanceof \Plugins\Mailer\Mailer) {
            throw new \RuntimeException('Mailer plugin is required for email changes.');
        }

        $rawToken = \Core\Crypto::randomHex();
        $tokenHash = \Core\Crypto::tokenHash($rawToken);
        $expiresAt = date('Y-m-d H:i:s', TIME_NOW + self::EMAIL_CHANGE_TTL);
        $tokenData = \Core\Utils\JsonTool::encode(['email' => $newEmail], false);

        if (\DB::insert('tokens', [
            'user_id' => $userId,
            'method' => self::EMAIL_CHANGE_METHOD,
            'token' => $tokenHash,
            'expires_at' => $expiresAt,
            'token_data' => $tokenData,
        ]) === false) {
            throw new \RuntimeException('Failed to create email change token.');
        }

        $verificationUrl = \Core\Request::scheme()
            . '://' . DOMAIN_NAME
            . '/auth/change-email?token=' . rawurlencode($rawToken);
        $username = (string)$user['username'];
        $siteName = DOMAIN_NAME;

        try {
            $message = $mailer->createMessage()
                ->addTo($newEmail, $username)
                ->setSubject($this->phrases['email_change_subject'])
                ->setHtmlBody($this->render('change_email_html', [
                    'username' => \Core\Html::escape($username),
                    'verification_url' => \Core\Html::escape($verificationUrl),
                    'site_name' => \Core\Html::escape($siteName),
                ]))
                ->setTextBody($this->render('change_email_txt', [
                    'username' => $username,
                    'verification_url' => $verificationUrl,
                    'site_name' => $siteName,
                ]));

            if (!$mailer->send($message)) {
                throw new \RuntimeException('Mailer did not confirm email change delivery.');
            }
        } catch (\Throwable $error) {
            \DB::query(
                'DELETE FROM tokens WHERE user_id=$1 AND method=$2 AND token=$3',
                [$userId, self::EMAIL_CHANGE_METHOD, $tokenHash]
            );
            throw $error;
        }

        $cleanup = \DB::query(
            'DELETE FROM tokens
             WHERE user_id=$1
               AND method=$2
               AND token<>$3',
            [$userId, self::EMAIL_CHANGE_METHOD, $tokenHash]
        );
        if ($cleanup === false) {
            $this->log(
                'Email change request sent, but previous email change tokens could not be removed.',
                'warning'
            );
        }

        return true;
    }

    private function handleEmailChange(): void
    {
        $rawToken = trim((string)(\Core\Request::all()['token'] ?? ''));
        if ($rawToken === '') {
            $this->sendEmailChangeResult(false, 400);
            return;
        }

        $tokenHash = \Core\Crypto::tokenHash($rawToken);
        if (!\DB::beginTransaction()) {
            $this->sendEmailChangeResult(false, 500);
            return;
        }

        $oldEmail = null;
        $newEmail = null;
        $username = '';
        $userId = 0;

        try {
            $token = \DB::getRow(
                'SELECT t.token_id, t.user_id, t.expires_at, t.token_data,
                        u.username, u.email, u.is_active
                 FROM tokens t
                 JOIN users u ON u.user_id=t.user_id
                 WHERE t.method=$1 AND t.token=$2
                 LIMIT 1
                 FOR UPDATE',
                [self::EMAIL_CHANGE_METHOD, $tokenHash]
            );

            if (!$token) {
                \DB::rollBack();
                $this->sendEmailChangeResult(false, 400);
                return;
            }

            $expiresAt = (string)($token['expires_at'] ?? '');
            if ($expiresAt !== '' && strtotime($expiresAt) < TIME_NOW) {
                \DB::query('DELETE FROM tokens WHERE token_id=$1', [(int)$token['token_id']]);
                if (!\DB::commit()) {
                    throw new \RuntimeException('Failed to commit expired email change cleanup.');
                }
                $this->sendEmailChangeResult(false, 410);
                return;
            }

            $tokenData = \Core\Utils\JsonTool::decode(
                (string)($token['token_data'] ?? '')
            );
            $newEmail = strtolower(trim((string)($tokenData['email'] ?? '')));
            if (!filter_var($newEmail, FILTER_VALIDATE_EMAIL)) {
                throw new \RuntimeException('Email change token has invalid data.');
            }

            $userId = (int)$token['user_id'];
            $oldEmail = strtolower(trim((string)($token['email'] ?? '')));
            $username = (string)$token['username'];

            if (empty($token['is_active'])) {
                throw new \RuntimeException('User account is not active.', 403);
            }

            if (\DB::getOne(
                'SELECT 1 FROM users WHERE lower(email)=lower($1) AND user_id<>$2 LIMIT 1',
                [$newEmail, $userId]
            )) {
                if (\DB::query(
                    'DELETE FROM tokens WHERE user_id=$1 AND method=$2',
                    [$userId, self::EMAIL_CHANGE_METHOD]
                ) === false) {
                    throw new \RuntimeException('Failed to invalidate unusable email change token.');
                }
                if (!\DB::commit()) {
                    throw new \RuntimeException('Failed to commit email change conflict cleanup.');
                }
                $this->sendEmailChangeResult(false, 409);
                return;
            }

            if (\DB::query(
                'UPDATE users
                 SET email=$1, email_verified_at=CURRENT_TIMESTAMP
                 WHERE user_id=$2',
                [$newEmail, $userId]
            ) === false) {
                throw new \RuntimeException('Failed to update email address.');
            }

            if (\DB::query(
                'DELETE FROM tokens WHERE user_id=$1 AND method IN ($2, $3)',
                [$userId, self::EMAIL_CHANGE_METHOD, self::EMAIL_VERIFICATION_METHOD]
            ) === false) {
                throw new \RuntimeException('Failed to consume email change token.');
            }

            if (!\DB::commit()) {
                throw new \RuntimeException('Failed to commit email change.');
            }

            $this->refreshCurrentUserCache($userId);
            $this->sendEmailChangedNotice($oldEmail, $username, $newEmail);
            $this->sendEmailChangeResult(true, 200);
        } catch (\Throwable $error) {
            \DB::rollBack();
            $this->log('Email change failed: ' . $error->getMessage(), 'error');
            $status = (int)$error->getCode();
            $this->sendEmailChangeResult(
                false,
                $status >= 400 && $status < 500 ? $status : 500
            );
        }
    }

    private function sendEmailChangedNotice(
        ?string $oldEmail,
        string $username,
        string $newEmail
    ): void {
        $oldEmail = strtolower(trim((string)$oldEmail));
        if (
            !filter_var($oldEmail, FILTER_VALIDATE_EMAIL)
            || $oldEmail === strtolower($newEmail)
        ) {
            return;
        }

        try {
            $mailer = $this->plugins->get('Mailer');
            if (!$mailer instanceof \Plugins\Mailer\Mailer) {
                return;
            }

            $message = $mailer->createMessage()
                ->addTo($oldEmail, $username)
                ->setSubject($this->phrases['email_changed_notice_subject'])
                ->setHtmlBody($this->render('email_changed_notice_html', [
                    'username' => \Core\Html::escape($username),
                    'new_email' => \Core\Html::escape($newEmail),
                    'site_name' => \Core\Html::escape(DOMAIN_NAME),
                ]))
                ->setTextBody($this->render('email_changed_notice_txt', [
                    'username' => $username,
                    'new_email' => $newEmail,
                    'site_name' => DOMAIN_NAME,
                ]));

            if (!$mailer->send($message)) {
                throw new \RuntimeException('Mailer did not confirm email change notice delivery.');
            }
        } catch (\Throwable $error) {
            $this->log(
                'Failed to send previous-address email change notice: ' . $error->getMessage(),
                'warning'
            );
        }
    }

    private function sendEmailChangeResult(bool $success, int $status): void
    {
        $template = $success ? 'email_change_success_page' : 'email_change_error_page';
        $message = $success
            ? $this->phrases['email_change_success']
            : ($status === 409
                ? $this->phrases['email_change_conflict']
                : $this->phrases['email_change_invalid_or_expired']);

        $content = $this->render($template, [
            'message' => \Core\Html::escape($message),
            'home_url' => '/',
            'language' => \Core\Html::escape((string)(defined('LANG') ? LANG : (DOMAIN_CONFIG['default_language'] ?? 'en'))),
        ]);

        \Core\Response::addHeader(
            'Content-Type: text/html; charset=utf-8',
            true,
            $status
        );
        \Core\Response::addHeader('Cache-Control: no-store, no-cache, must-revalidate');
        \Core\Response::addHeader('X-Powered-By: Kami');
        \Core\Response::send(\Core\Renderer::finalize($content));
    }

    private function requireAuthenticatedUserId(): int
    {
        $userId = \Core\User::getId();
        if ($userId < 1) {
            throw new \RuntimeException('Authentication is required.', 401);
        }
        return $userId;
    }

    private function refreshCurrentUserCache(int $userId): void
    {
        \Core\User::clearUserCache($userId);
        if (\Core\User::getId() === $userId) {
            \Core\User::$user = \Core\User::getUser($userId);
        }
    }

    public function routeAuth(\Core\Request $request): void
    {
        $segments = $request::segments();
        if (($segments[0] ?? null) !== 'auth') {
            $this->sendAuthError(404);
            return;
        }

        if (
            count($segments) === 3
            && strtolower((string)$segments[1]) === 'prepare'
        ) {
            $this->handleProviderStart(strtolower((string)$segments[2]));
            return;
        }

        if (count($segments) !== 2) {
            $this->sendAuthError(404);
            return;
        }

        match (strtolower((string)$segments[1])) {
            'logout' => $this->logout(),
            'google' => $this->handleGoogleCallback(),
            'verify-email' => $this->handleEmailVerification(),
            'reset-password' => $this->handlePasswordReset(),
            'change-email' => $this->handleEmailChange(),
            default => $this->sendAuthError(404),
        };
    }

    /**
     * Return the current user authentication methods without relying on cache.
     * Authentication state is security-sensitive and inexpensive to read.
     *
     * @return array{
     *     password: array{configured: bool},
     *     providers: array<string, array{connected: bool, email: ?string}>
     * }
     */
    public function getAuthenticationMethods(): array
    {
        $userId = \Core\User::getId();
        if ($userId < 1) {
            return [
                'password' => ['configured' => false],
                'providers' => [],
            ];
        }

        $user = \DB::getRow(
            'SELECT password_hash FROM users WHERE user_id=$1 LIMIT 1',
            [$userId]
        );
        if (!$user) {
            throw new \RuntimeException('User account not found.');
        }

        $providers = [];
        $result = \DB::query(
            'SELECT provider, provider_email
             FROM user_auth_identities
             WHERE user_id=$1
             ORDER BY provider',
            [$userId]
        );
        if ($result === false) {
            throw new \RuntimeException('Failed to load authentication methods.');
        }

        while ($identity = \DB::fetchRow($result)) {
            $provider = strtolower(trim((string)$identity['provider']));
            if ($provider === '') {
                continue;
            }

            $email = trim((string)($identity['provider_email'] ?? ''));
            $providers[$provider] = [
                'connected' => true,
                'email' => $email !== '' ? $email : null,
            ];
        }

        return [
            'password' => [
                'configured' => trim((string)($user['password_hash'] ?? '')) !== '',
            ],
            'providers' => $providers,
        ];
    }

    /**
     * Return a local OAuth start URL without creating state during page rendering.
     */
    public function getProviderStartUrl(
        string $provider,
        string $purpose = self::OAUTH_PURPOSE_LOGIN,
        ?string $returnUrl = null
    ): string {
        $provider = strtolower(trim($provider));
        $purpose = strtolower(trim($purpose));

        if (!preg_match('/^[a-z0-9_-]+$/', $provider)) {
            throw new \InvalidArgumentException('Invalid authentication provider.');
        }
        if (!in_array($purpose, [
            self::OAUTH_PURPOSE_LOGIN,
            self::OAUTH_PURPOSE_LINK,
            self::OAUTH_PURPOSE_REPLACE,
        ], true)) {
            throw new \InvalidArgumentException('Unsupported OAuth purpose.');
        }

        $query = ['purpose' => $purpose];
        $returnUrl = $this->normalizeLocalReturnUrl($returnUrl);
        if ($returnUrl !== null) {
            $query['return'] = $returnUrl;
        }

        return '/auth/prepare/' . rawurlencode($provider)
            . '?' . http_build_query($query);
    }

    private function handleProviderStart(string $provider): void
    {
        $purpose = strtolower(trim((string)(
            \Core\Request::all()['purpose'] ?? self::OAUTH_PURPOSE_LOGIN
        )));

        try {
            $returnUrl = $this->normalizeLocalReturnUrl(
                isset(\Core\Request::all()['return'])
                    ? (string)\Core\Request::all()['return']
                    : null
            );

            if ($purpose !== self::OAUTH_PURPOSE_LOGIN) {
                // Plugin endpoints do not initialize user/session state automatically.
                \Core\User::init();
            }

            $authorizationUrl = $this->getProviderAuthorizationUrl(
                $provider,
                $purpose,
                $returnUrl
            );
            \Core\Response::addHeader('Cache-Control: no-store, no-cache, must-revalidate');
            \Core\Response::redirect($authorizationUrl);
            \Core\Response::send('');
        } catch (\Throwable $error) {
            $this->log(
                'Authentication provider start failed: ' . $error->getMessage(),
                'error'
            );
            $status = (int)$error->getCode();
            $this->sendAuthError($status >= 400 && $status < 500 ? $status : 500);
        }
    }

    /**
     * Create an OAuth authorization URL for login or authenticated account linking.
     */
    public function getProviderAuthorizationUrl(
        string $provider,
        string $purpose = self::OAUTH_PURPOSE_LOGIN,
        ?string $returnUrl = null
    ): string {
        $provider = strtolower(trim($provider));
        $purpose = strtolower(trim($purpose));

        if (!in_array($purpose, [
            self::OAUTH_PURPOSE_LOGIN,
            self::OAUTH_PURPOSE_LINK,
            self::OAUTH_PURPOSE_REPLACE,
        ], true)) {
            throw new \InvalidArgumentException('Unsupported OAuth purpose.');
        }

        $userId = null;
        if ($purpose !== self::OAUTH_PURPOSE_LOGIN) {
            $userId = \Core\User::getId();
            if ($userId < 1) {
                throw new \RuntimeException(
                    'Authentication is required to modify a connected provider.',
                    401
                );
            }
        }

        $returnUrl = $this->normalizeLocalReturnUrl($returnUrl);

        return match ($provider) {
            'google' => $this->googleAuthorizationUrl($purpose, $userId, $returnUrl),
            default => throw new \InvalidArgumentException(
                "Unsupported authentication provider: {$provider}."
            ),
        };
    }

    /**
     * Disconnect one provider from the current account.
     * The final usable authentication method can never be removed.
     */
    public function disconnectProvider(string $provider): bool
    {
        $provider = strtolower(trim($provider));
        if ($provider === '') {
            throw new \InvalidArgumentException('Authentication provider is required.');
        }

        $userId = \Core\User::getId();
        if ($userId < 1) {
            throw new \RuntimeException('Authentication is required.', 401);
        }

        if (!\DB::beginTransaction()) {
            throw new \RuntimeException('Failed to start authentication transaction.');
        }

        try {
            $user = \DB::getRow(
                'SELECT password_hash
                 FROM users
                 WHERE user_id=$1
                 LIMIT 1
                 FOR UPDATE',
                [$userId]
            );
            if (!$user) {
                throw new \RuntimeException('User account not found.', 404);
            }

            $result = \DB::query(
                'SELECT id, provider
                 FROM user_auth_identities
                 WHERE user_id=$1
                 ORDER BY id
                 FOR UPDATE',
                [$userId]
            );
            if ($result === false) {
                throw new \RuntimeException('Failed to load connected providers.');
            }

            $identities = \DB::fetchAll($result);
            $providerIdentity = null;
            foreach ($identities as $identity) {
                if (strtolower((string)$identity['provider']) === $provider) {
                    $providerIdentity = $identity;
                    break;
                }
            }

            if ($providerIdentity === null) {
                if (!\DB::commit()) {
                    throw new \RuntimeException('Failed to commit authentication transaction.');
                }
                return true;
            }

            $methodCount = count($identities);
            if (trim((string)($user['password_hash'] ?? '')) !== '') {
                $methodCount++;
            }

            if ($methodCount <= 1) {
                throw new \RuntimeException(
                    'The last authentication method cannot be disconnected.',
                    409
                );
            }

            if (\DB::query(
                'DELETE FROM user_auth_identities WHERE id=$1 AND user_id=$2',
                [(int)$providerIdentity['id'], $userId]
            ) === false) {
                throw new \RuntimeException('Failed to disconnect authentication provider.');
            }

            if (!\DB::commit()) {
                throw new \RuntimeException('Failed to commit authentication transaction.');
            }

            return true;
        } catch (\Throwable $error) {
            \DB::rollBack();
            throw $error;
        }
    }

    private function googleAuthorizationUrl(
        string $purpose,
        ?int $userId,
        ?string $returnUrl
    ): string {
        $config = $this->googleConfig();
        $callbackUrl = $this->googleCallbackUrl();
        $this->assertGoogleRedirectUri($config);

        $state = $this->createOAuthState(
            'google',
            $purpose,
            $userId,
            $returnUrl
        );
        \Core\Response::addCookie(
            self::GOOGLE_STATE_COOKIE,
            $state,
            time() + self::OAUTH_STATE_TTL,
            true,
            'Lax'
        );

        return ($config['auth_uri'] ?? self::GOOGLE_AUTH_URI) . '?' . http_build_query([
            'response_type' => 'code',
            'client_id' => $config['client_id'],
            'redirect_uri' => $callbackUrl,
            'scope' => self::GOOGLE_SCOPE,
            'state' => $state,
            'include_granted_scopes' => 'true',
            'prompt' => 'select_account',
        ]);
    }

    private function handleGoogleCallback(): void
    {
        $request = \Core\Request::all();
        $state = (string)($request['state'] ?? '');
        $expectedState = (string)(
            \Core\Request::cookie()[self::GOOGLE_STATE_COOKIE] ?? ''
        );

        \Core\Response::addCookie(
            self::GOOGLE_STATE_COOKIE,
            '',
            time() - 3600,
            true,
            'Lax'
        );

        if (
            $state === ''
            || $expectedState === ''
            || !hash_equals($expectedState, $state)
        ) {
            $this->log('Google authentication failed: invalid OAuth state.', 'warning');
            $this->sendAuthError(400);
            return;
        }

        try {
            $flow = $this->consumeOAuthState('google', $state);
            if ($flow === null) {
                $this->log('Google authentication failed: expired or unknown OAuth state.', 'warning');
                $this->sendAuthError(400);
                return;
            }

            $purpose = (string)$flow['purpose'];
            $flowUserId = $flow['user_id'];
            $returnUrl = $flow['return_url'];

            if ($purpose !== self::OAUTH_PURPOSE_LOGIN) {
                // Plugin endpoints do not initialize user/session state automatically.
                \Core\User::init();
                $currentUserId = \Core\User::getId();
                if (
                    $currentUserId < 1
                    || $flowUserId === null
                    || $currentUserId !== $flowUserId
                ) {
                    throw new \RuntimeException(
                        'OAuth account modification session does not match the initiating user.',
                        401
                    );
                }
            }

            if (isset($request['error'])) {
                $this->log(
                    'Google authentication was rejected: ' . (string)$request['error'],
                    'warning'
                );
                $this->sendAuthError(401);
                return;
            }

            $code = trim((string)($request['code'] ?? ''));
            if ($code === '') {
                $this->sendAuthError(400);
                return;
            }

            $config = $this->googleConfig();
            $token = $this->httpJson(
                (string)($config['token_uri'] ?? self::GOOGLE_TOKEN_URI),
                'POST',
                [
                    'Content-Type: application/x-www-form-urlencoded',
                ],
                http_build_query([
                    'code' => $code,
                    'client_id' => $config['client_id'],
                    'client_secret' => $config['client_secret'],
                    'redirect_uri' => $this->googleCallbackUrl(),
                    'grant_type' => 'authorization_code',
                ])
            );

            $accessToken = trim((string)($token['access_token'] ?? ''));
            if ($accessToken === '') {
                throw new \RuntimeException('Google token response has no access token.');
            }

            $googleUser = $this->httpJson(
                self::GOOGLE_USERINFO_URI,
                'GET',
                ['Authorization: Bearer ' . $accessToken]
            );

            $subject = trim((string)($googleUser['sub'] ?? ''));
            if ($subject === '') {
                throw new \RuntimeException('Google userinfo response has no subject identifier.');
            }

            if ($purpose === self::OAUTH_PURPOSE_LOGIN) {
                $userId = $this->resolveGoogleUser($googleUser);
                $user = \DB::getRow(
                    'SELECT user_id, is_active FROM users WHERE user_id=$1 LIMIT 1',
                    [$userId]
                );

                if (!$user) {
                    throw new \RuntimeException('Resolved local user does not exist.');
                }
                if (empty($user['is_active'])) {
                    $this->sendAccountInactivePage();
                    return;
                }

                // Plugin endpoints do not initialize user/session state automatically.
                \Core\User::init();
                \Core\Session::authorize($userId, false);
            } else {
                $this->linkGoogleUser(
                    (int)$flowUserId,
                    $googleUser,
                    $purpose === self::OAUTH_PURPOSE_REPLACE
                );
            }

            $redirectUrl = $purpose === self::OAUTH_PURPOSE_LOGIN
                ? $this->loginRedirectUrl($returnUrl)
                : ($returnUrl ?? '/');
            \Core\Response::redirect($redirectUrl);
            \Core\Response::send('');
        } catch (\Throwable $error) {
            $this->log('Google authentication failed: ' . $error->getMessage(), 'error');
            $status = (int)$error->getCode();
            $this->sendAuthError($status >= 400 && $status < 500 ? $status : 500);
        }
    }

    private function createOAuthState(
        string $provider,
        string $purpose,
        ?int $userId,
        ?string $returnUrl
    ): string {
        $rawState = \Core\Crypto::randomHex();
        $stateHash = \Core\Crypto::tokenHash($rawState);
        $method = $this->oauthStateMethod($provider, $purpose);
        $expiresAt = date('Y-m-d H:i:s', TIME_NOW + self::OAUTH_STATE_TTL);

        $tokenData = $returnUrl !== null
            ? \Core\Utils\JsonTool::encode(['return_url' => $returnUrl], false)
            : null;

        if (\DB::insert('tokens', [
            'user_id' => $userId,
            'method' => $method,
            'token' => $stateHash,
            'expires_at' => $expiresAt,
            'token_data' => $tokenData,
        ]) === false) {
            throw new \RuntimeException('Failed to create OAuth state.');
        }

        $cleanup = \DB::query(
            'DELETE FROM tokens
             WHERE method LIKE $1
               AND expires_at IS NOT NULL
               AND expires_at < CURRENT_TIMESTAMP',
            [self::OAUTH_STATE_METHOD_PREFIX . $provider . '_%']
        );
        if ($cleanup === false) {
            $this->log('Expired OAuth states could not be removed.', 'warning');
        }

        return $rawState;
    }

    /**
     * Consume one browser-bound OAuth state and return its server-side flow context.
     *
     * @return array{purpose:string, user_id:?int, return_url:?string}|null
     */
    private function consumeOAuthState(string $provider, string $rawState): ?array
    {
        $stateHash = \Core\Crypto::tokenHash($rawState);
        $methods = [
            self::OAUTH_PURPOSE_LOGIN => $this->oauthStateMethod(
                $provider,
                self::OAUTH_PURPOSE_LOGIN
            ),
            self::OAUTH_PURPOSE_LINK => $this->oauthStateMethod(
                $provider,
                self::OAUTH_PURPOSE_LINK
            ),
            self::OAUTH_PURPOSE_REPLACE => $this->oauthStateMethod(
                $provider,
                self::OAUTH_PURPOSE_REPLACE
            ),
        ];

        if (!\DB::beginTransaction()) {
            throw new \RuntimeException('Failed to start OAuth state transaction.');
        }

        try {
            $token = \DB::getRow(
                'SELECT token_id, user_id, method, expires_at, token_data
                 FROM tokens
                 WHERE token=$1
                   AND method IN ($2, $3, $4)
                 LIMIT 1
                 FOR UPDATE',
                [
                    $stateHash,
                    $methods[self::OAUTH_PURPOSE_LOGIN],
                    $methods[self::OAUTH_PURPOSE_LINK],
                    $methods[self::OAUTH_PURPOSE_REPLACE],
                ]
            );

            if (!$token) {
                \DB::rollBack();
                return null;
            }

            if (\DB::query(
                'DELETE FROM tokens WHERE token_id=$1',
                [(int)$token['token_id']]
            ) === false) {
                throw new \RuntimeException('Failed to consume OAuth state.');
            }

            if (!\DB::commit()) {
                throw new \RuntimeException('Failed to commit OAuth state consumption.');
            }

            $expiresAt = (string)($token['expires_at'] ?? '');
            if ($expiresAt !== '' && strtotime($expiresAt) < TIME_NOW) {
                return null;
            }

            $purpose = array_search((string)$token['method'], $methods, true);
            if (!is_string($purpose)) {
                return null;
            }

            $userId = isset($token['user_id']) && $token['user_id'] !== null
                ? (int)$token['user_id']
                : null;

            if ($purpose === self::OAUTH_PURPOSE_LOGIN && $userId !== null) {
                return null;
            }
            if ($purpose !== self::OAUTH_PURPOSE_LOGIN && ($userId ?? 0) < 1) {
                return null;
            }

            $tokenData = \Core\Utils\JsonTool::decodeArray($token['token_data'] ?? null);
            $returnUrl = is_array($tokenData)
                ? $this->normalizeLocalReturnUrl(
                    isset($tokenData['return_url'])
                        ? (string)$tokenData['return_url']
                        : null
                )
                : null;

            return [
                'purpose' => $purpose,
                'user_id' => $userId,
                'return_url' => $returnUrl,
            ];
        } catch (\Throwable $error) {
            \DB::rollBack();
            throw $error;
        }
    }

    private function normalizeLocalReturnUrl(?string $returnUrl): ?string
    {
        if ($returnUrl === null) {
            return null;
        }

        $returnUrl = trim($returnUrl);
        if (
            $returnUrl === ''
            || !str_starts_with($returnUrl, '/')
            || str_starts_with($returnUrl, '//')
            || str_contains($returnUrl, '\\')
        ) {
            return null;
        }

        $parts = parse_url($returnUrl);
        if (
            $parts === false
            || isset($parts['scheme'])
            || isset($parts['host'])
            || isset($parts['user'])
            || isset($parts['pass'])
        ) {
            return null;
        }

        return $returnUrl;
    }

    private function oauthStateMethod(string $provider, string $purpose): string
    {
        return self::OAUTH_STATE_METHOD_PREFIX
            . strtolower(trim($provider))
            . '_'
            . strtolower(trim($purpose));
    }

    private function resolveGoogleUser(array $googleUser): int
    {
        $subject = trim((string)($googleUser['sub'] ?? ''));
        if ($subject === '') {
            throw new \RuntimeException('Google subject identifier is required.');
        }

        if (!\DB::beginTransaction()) {
            throw new \RuntimeException('Failed to start authentication transaction.');
        }

        try {
            $identity = \DB::getRow(
                'SELECT id, user_id
                 FROM user_auth_identities
                 WHERE provider=$1 AND provider_user_id=$2
                 LIMIT 1
                 FOR UPDATE',
                ['google', $subject]
            );

            $email = filter_var(
                (string)($googleUser['email'] ?? ''),
                FILTER_VALIDATE_EMAIL
            ) ?: null;

            if ($identity) {
                $userId = (int)$identity['user_id'];
                $updated = \DB::query(
                    'UPDATE user_auth_identities
                     SET provider_email=COALESCE($3, provider_email), last_used_at=now()
                     WHERE provider=$1 AND provider_user_id=$2',
                    ['google', $subject, $email]
                );
                if ($updated === false) {
                    throw new \RuntimeException('Failed to update Google identity.');
                }
            } else {
                if ($email === null || empty($googleUser['email_verified'])) {
                    throw new \RuntimeException(
                        'A verified Google email is required to link a new identity.',
                        400
                    );
                }

                $users = \DB::query(
                    'SELECT user_id
                     FROM users
                     WHERE lower(email)=lower($1)
                     ORDER BY user_id
                     LIMIT 2
                     FOR UPDATE',
                    [$email]
                );
                if ($users === false) {
                    throw new \RuntimeException('Failed to resolve local user by email.');
                }

                $matches = \DB::fetchAll($users);
                if (count($matches) > 1) {
                    throw new \RuntimeException(
                        'Multiple local users match the verified Google email.'
                    );
                }

                if ($matches !== []) {
                    $userId = (int)$matches[0]['user_id'];
                    $existingProvider = \DB::getRow(
                        'SELECT id, provider_user_id
                         FROM user_auth_identities
                         WHERE user_id=$1 AND provider=$2
                         LIMIT 1
                         FOR UPDATE',
                        [$userId, 'google']
                    );
                    if ($existingProvider) {
                        throw new \RuntimeException(
                            'This local account is already connected to another Google account.',
                            409
                        );
                    }
                } else {
                    $userId = $this->createGoogleUser($email);
                }

                $linked = \DB::insert('user_auth_identities', [
                    'user_id' => $userId,
                    'provider' => 'google',
                    'provider_user_id' => $subject,
                    'provider_email' => $email,
                    'last_used_at' => date('Y-m-d H:i:sP'),
                ], 'id');

                if ($linked === false || (int)$linked < 1) {
                    throw new \RuntimeException('Failed to link Google identity.');
                }
            }

            if (
                $email !== null
                && !empty($googleUser['email_verified'])
                && \DB::query(
                    'UPDATE users
                     SET email_verified_at=COALESCE(email_verified_at, CURRENT_TIMESTAMP)
                     WHERE user_id=$1 AND lower(email)=lower($2)',
                    [$userId, $email]
                ) === false
            ) {
                throw new \RuntimeException('Failed to update verified Google email state.');
            }

            if (!\DB::commit()) {
                throw new \RuntimeException('Failed to commit authentication transaction.');
            }

            \Core\User::clearUserCache($userId);
            return $userId;
        } catch (\Throwable $error) {
            \DB::rollBack();
            throw $error;
        }
    }

    private function linkGoogleUser(
        int $userId,
        array $googleUser,
        bool $replace
    ): void {
        $subject = trim((string)($googleUser['sub'] ?? ''));
        if ($subject === '') {
            throw new \RuntimeException('Google subject identifier is required.');
        }

        $email = filter_var(
            (string)($googleUser['email'] ?? ''),
            FILTER_VALIDATE_EMAIL
        ) ?: null;

        if (!\DB::beginTransaction()) {
            throw new \RuntimeException('Failed to start authentication transaction.');
        }

        try {
            $user = \DB::getRow(
                'SELECT user_id, is_active
                 FROM users
                 WHERE user_id=$1
                 LIMIT 1
                 FOR UPDATE',
                [$userId]
            );
            if (!$user || empty($user['is_active'])) {
                throw new \RuntimeException('User account is not active.', 403);
            }

            $subjectIdentity = \DB::getRow(
                'SELECT id, user_id
                 FROM user_auth_identities
                 WHERE provider=$1 AND provider_user_id=$2
                 LIMIT 1
                 FOR UPDATE',
                ['google', $subject]
            );
            if ($subjectIdentity && (int)$subjectIdentity['user_id'] !== $userId) {
                throw new \RuntimeException(
                    'This Google account is already connected to another user.',
                    409
                );
            }

            $currentIdentity = \DB::getRow(
                'SELECT id, provider_user_id
                 FROM user_auth_identities
                 WHERE user_id=$1 AND provider=$2
                 LIMIT 1
                 FOR UPDATE',
                [$userId, 'google']
            );

            if ($currentIdentity) {
                if ((string)$currentIdentity['provider_user_id'] === $subject) {
                    if (\DB::query(
                        'UPDATE user_auth_identities
                         SET provider_email=COALESCE($2, provider_email), last_used_at=now()
                         WHERE id=$1',
                        [(int)$currentIdentity['id'], $email]
                    ) === false) {
                        throw new \RuntimeException('Failed to update Google identity.');
                    }
                } elseif (!$replace) {
                    throw new \RuntimeException(
                        'A Google account is already connected. Use replace instead.',
                        409
                    );
                } elseif (\DB::query(
                    'UPDATE user_auth_identities
                     SET provider_user_id=$2, provider_email=$3, last_used_at=now()
                     WHERE id=$1',
                    [(int)$currentIdentity['id'], $subject, $email]
                ) === false) {
                    throw new \RuntimeException('Failed to replace Google identity.');
                }
            } elseif ($replace) {
                throw new \RuntimeException(
                    'There is no connected Google account to replace.',
                    409
                );
            } else {
                $identityId = \DB::insert('user_auth_identities', [
                    'user_id' => $userId,
                    'provider' => 'google',
                    'provider_user_id' => $subject,
                    'provider_email' => $email,
                    'last_used_at' => date('Y-m-d H:i:sP'),
                ], 'id');
                if ($identityId === false || (int)$identityId < 1) {
                    throw new \RuntimeException('Failed to connect Google identity.');
                }
            }

            if (!\DB::commit()) {
                throw new \RuntimeException('Failed to commit authentication transaction.');
            }
        } catch (\Throwable $error) {
            \DB::rollBack();
            throw $error;
        }
    }

    private function createGoogleUser(string $email): int
    {
        $username = $this->googleUsername($email);
        $adminActivationRequired = (bool)$this->settingValue(
            'admin_activation_required',
            false
        );
        $userId = \DB::insert('users', [
            'username' => $username,
            'email' => $email,
            'password_hash' => null,
            'usergroup_id' => (int)GLOBAL_SETTINGS['usergroup_default'],
            'is_active' => !$adminActivationRequired,
            'email_verified_at' => date('Y-m-d H:i:s'),
        ], 'user_id');

        if ($userId === false || (int)$userId < 1) {
            throw new \RuntimeException('Failed to create local Google user.');
        }

        return (int)$userId;
    }

    private function googleUsername(string $email): string
    {
        $base = strtolower((string)strtok($email, '@'));
        $base = preg_replace('/[^a-z0-9._-]+/', '_', $base) ?? '';
        $base = trim($base, '._-');
        $base = $base !== '' ? $base : 'user';

        if (!\DB::getOne('SELECT 1 FROM users WHERE username=$1 LIMIT 1', [$base])) {
            return $base;
        }

        for ($attempt = 0; $attempt < 10; $attempt++) {
            $candidate = $base . '_' . \Core\Crypto::randomHex(3);
            if (!\DB::getOne('SELECT 1 FROM users WHERE username=$1 LIMIT 1', [$candidate])) {
                return $candidate;
            }
        }

        throw new \RuntimeException('Unable to generate a unique username.');
    }

    private function googleConfig(): array
    {
        $raw = \Core\SecretStore::get(
            'UserAccount',
            self::GOOGLE_SECRET_NAME,
            DOMAIN_ID
        );
        if ($raw === null || trim($raw) === '') {
            throw new \RuntimeException('Google OAuth configuration is not configured.');
        }

        try {
            $decoded = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $error) {
            throw new \RuntimeException('Google OAuth configuration is invalid JSON.', 0, $error);
        }

        $config = is_array($decoded['web'] ?? null)
            ? $decoded['web']
            : null;
        if ($config === null) {
            throw new \RuntimeException('Google OAuth configuration has no web client section.');
        }

        foreach (['client_id', 'client_secret'] as $key) {
            if (!is_string($config[$key] ?? null) || trim($config[$key]) === '') {
                throw new \RuntimeException("Google OAuth configuration has no {$key}.");
            }
        }

        return $config;
    }

    private function assertGoogleRedirectUri(array $config): void
    {
        $callbackUrl = $this->googleCallbackUrl();
        $redirectUris = is_array($config['redirect_uris'] ?? null)
            ? $config['redirect_uris']
            : [];

        if ($redirectUris !== [] && !in_array($callbackUrl, $redirectUris, true)) {
            throw new \RuntimeException(
                "Google OAuth configuration does not allow redirect URI {$callbackUrl}."
            );
        }
    }

    private function googleCallbackUrl(): string
    {
        return \Core\Request::scheme() . '://' . DOMAIN_NAME . self::GOOGLE_CALLBACK_PATH;
    }

    private function renderLoginResult(string $message): string
    {
        $action = $this->loginAction();
        $postLoginAction = match ($action) {
            'nothing' => '',
            'redirect' => $this->render('login_redirect', [
                'redirect_url' => \Core\Utils\JsonTool::encodeForHtml($this->loginRedirectUrl()),
            ]),
            default => $this->render('login_reload'),
        };

        return $this->render('login_result', [
            'msg' => $message,
            'post_login_action' => $postLoginAction,
        ]);
    }

    private function loginAction(): string
    {
        $action = strtolower(trim((string)$this->settingValue('login_action', 'reload')));

        return in_array($action, ['reload', 'redirect', 'nothing'], true)
            ? $action
            : 'reload';
    }

    private function loginRedirectUrl(?string $returnUrl = null): string
    {
        if ($this->loginAction() === 'redirect') {
            $url = trim((string)$this->settingValue('redirect_page', '/'));
            if ($url !== '') {
                return $url;
            }
        }

        return $this->normalizeLocalReturnUrl($returnUrl) ?? '/';
    }

    private function loginReturnUrl(array $contextVars = []): ?string
    {
        if (isset($contextVars['return_url'])) {
            $returnUrl = $this->normalizeLocalReturnUrl(
                (string)$contextVars['return_url']
            );
            if ($returnUrl !== null) {
                return $returnUrl;
            }
        }

        return $this->normalizeLocalReturnUrl(
            (string)($_SERVER['REQUEST_URI'] ?? '/')
        );
    }

    private function httpJson(
        string $url,
        string $method = 'GET',
        array $headers = [],
        ?string $body = null
    ): array {
        $curl = curl_init($url);
        if ($curl === false) {
            throw new \RuntimeException('Unable to initialize HTTP client.');
        }

        curl_setopt_array($curl, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_TIMEOUT => 15,
            CURLOPT_CUSTOMREQUEST => strtoupper($method),
            CURLOPT_HTTPHEADER => $headers,
        ]);
        if ($body !== null) {
            curl_setopt($curl, CURLOPT_POSTFIELDS, $body);
        }

        try {
            $response = curl_exec($curl);
            if ($response === false) {
                throw new \RuntimeException(
                    'HTTP request failed: ' . curl_error($curl)
                );
            }
            $status = (int)curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
        } finally {
            curl_close($curl);
        }

        if ($status < 200 || $status >= 300) {
            throw new \RuntimeException("HTTP request returned status {$status}.");
        }

        try {
            $decoded = json_decode((string)$response, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $error) {
            throw new \RuntimeException('HTTP response is not valid JSON.', 0, $error);
        }

        if (!is_array($decoded)) {
            throw new \RuntimeException('HTTP response JSON is not an object.');
        }

        return $decoded;
    }

    private function settingValue(string $name, mixed $fallback = null): mixed
    {
        $value = $this->settings[$name] ?? $fallback;
        if (is_array($value) && array_key_exists('default', $value)) {
            return $value['default'];
        }

        return $value;
    }

    private function sendAuthError(int $status): void
    {
        \Core\Response::addHeader(
            'Content-Type: text/plain; charset=utf-8',
            true,
            $status
        );
        \Core\Response::addHeader('X-Powered-By: Kami');
        \Core\Response::send('Authentication failed.');
    }

}
