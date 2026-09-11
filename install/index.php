<?php

/**
 * KamiCore
 *
 * SPDX-License-Identifier: Apache-2.0
 *
 * @see https://kamicore.org
 */

declare(strict_types=1);

$secureCookie = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
session_set_cookie_params([
    'httponly' => true,
    'secure' => $secureCookie,
    'samesite' => 'Lax',
]);
session_start();

header('X-Frame-Options: DENY');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: no-referrer');

require_once __DIR__ . '/Installer.php';

$installer = new Installer(dirname(__DIR__));
$installed = $installer->isInstalled();
$success = false;
$errors = [];

if (!isset($_SESSION['kami_install_csrf']) || !is_string($_SESSION['kami_install_csrf'])) {
    $_SESSION['kami_install_csrf'] = bin2hex(random_bytes(32));
}

$defaults = [
    'db_host' => 'localhost',
    'db_port' => '5432',
    'db_name' => '',
    'db_user' => '',
    'cache_enabled' => false,
    'cache_host' => 'localhost',
    'cache_port' => '6379',
    'cache_db' => '0',
    'secret_path' => $installer->defaultSecretPath(),
    'smtp_enabled' => false,
    'smtp_host' => '',
    'smtp_port' => '587',
    'smtp_username' => '',
    'smtp_encryption' => 'tls',
    'smtp_from_email' => '',
    'smtp_from_name' => 'KamiCore',
    'smtp_reply_email' => '',
    'smtp_reply_name' => '',
    'admin_username' => 'admin',
    'admin_email' => '',
];

$form = array_replace($defaults, $_POST);
$form['cache_enabled'] = isset($_POST['cache_enabled']) && $_POST['cache_enabled'] === '1';
$form['smtp_enabled'] = isset($_POST['smtp_enabled']) && $_POST['smtp_enabled'] === '1';

if (!$installed && ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $csrf = (string)($_POST['csrf'] ?? '');
    if (!hash_equals((string)$_SESSION['kami_install_csrf'], $csrf)) {
        $errors[] = 'The installation form expired. Reload the page and try again.';
    } else {
        try {
            $installer->install($_POST);
            $success = true;
            $installed = true;
            unset($_SESSION['kami_install_csrf']);
        } catch (Throwable $error) {
            $message = trim($error->getMessage());
            $errors = $message !== '' ? preg_split('/\R+/', $message) ?: [$message] : ['Installation failed.'];
        }
    }
}

if ($installed && !$success) {
    http_response_code(409);
}

function h(mixed $value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

$checks = $installer->environmentChecks();
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex,nofollow">
    <title>KamiCore installation</title>
    <link rel="stylesheet" href="../themes/default/assets/css/default.css">
    <link rel="stylesheet" href="./install.css">
</head>
<body class="install-page">
<main class="install-shell">
    <header class="install-header">
        <img src="../brand/logo/kamicore-logo.svg" alt="KamiCore" class="install-logo">
        <div>
            <div class="install-kicker">KamiCore</div>
            <h1>Installation</h1>
            <p>Prepare the database, create the first administrator, and generate the local configuration.</p>
        </div>
    </header>

    <?php if ($success): ?>
        <section class="install-card install-result">
            <div class="install-status install-status-success">Installation completed successfully.</div>
            <h2>KamiCore is ready</h2>
            <p>The database has been initialized, the administrator account created, and the local secrets and configuration written.</p>
            <div class="kc-btn-row is-left">
                <a class="kc-btn kc-btn-primary" href="/">Open site</a>
                <a class="kc-btn kc-btn-secondary" href="/admin">Administration</a>
            </div>
        </section>
    <?php elseif ($installed): ?>
        <section class="install-card install-result">
            <div class="install-status install-status-warning">Installation is disabled.</div>
            <h2>KamiCore is already configured</h2>
            <p><code>config/config.php</code> already exists. The installer will not overwrite an existing installation.</p>
            <a class="kc-btn kc-btn-primary" href="/">Return to site</a>
        </section>
    <?php else: ?>
        <section class="install-card install-preflight">
            <div>
                <h2>Server check</h2>
                <p class="kc-form-helper">The current domain will be written automatically as <strong><?= h($installer->domain()) ?></strong>.</p>
            </div>
            <div class="install-checks">
                <?php foreach ($checks as $label => $ok): ?>
                    <div class="install-check <?= $ok ? 'is-ok' : 'is-error' ?>">
                        <span><?= $ok ? '✓' : '×' ?></span>
                        <?= h($label) ?>
                    </div>
                <?php endforeach; ?>
            </div>
        </section>

        <?php if ($errors !== []): ?>
            <section class="install-alert" role="alert">
                <strong>Installation could not continue.</strong>
                <ul>
                    <?php foreach ($errors as $error): ?>
                        <li><?= h($error) ?></li>
                    <?php endforeach; ?>
                </ul>
            </section>
        <?php endif; ?>

        <form method="post" class="install-card install-form" autocomplete="off">
            <input type="hidden" name="csrf" value="<?= h($_SESSION['kami_install_csrf']) ?>">

            <fieldset class="install-section">
                <legend>PostgreSQL</legend>
                <p class="install-section-note">Use an empty database. The supplied user will own all objects created by the installer.</p>
                <div class="install-grid">
                    <label class="kc-form-group">
                        <span class="kc-form-label">Host</span>
                        <input class="kc-input" name="db_host" value="<?= h($form['db_host']) ?>" required>
                    </label>
                    <label class="kc-form-group install-small-field">
                        <span class="kc-form-label">Port</span>
                        <input class="kc-input" name="db_port" type="number" min="1" max="65535" value="<?= h($form['db_port']) ?>" required>
                    </label>
                    <label class="kc-form-group">
                        <span class="kc-form-label">Database</span>
                        <input class="kc-input" name="db_name" value="<?= h($form['db_name']) ?>" required>
                    </label>
                    <label class="kc-form-group">
                        <span class="kc-form-label">User</span>
                        <input class="kc-input" name="db_user" value="<?= h($form['db_user']) ?>" required>
                    </label>
                    <label class="kc-form-group install-wide-field">
                        <span class="kc-form-label">Password</span>
                        <input class="kc-input" name="db_password" type="password" required autocomplete="new-password">
                    </label>
                </div>
            </fieldset>

            <fieldset class="install-section">
                <legend>Cache</legend>
                <label class="kc-checkbox-label">
                    <input class="kc-checkbox" type="checkbox" name="cache_enabled" value="1" data-toggle="cache-settings" <?= $form['cache_enabled'] ? 'checked' : '' ?>>
                    Enable Redis cache
                </label>
                <div id="cache-settings" class="install-grid" data-optional-section>
                    <label class="kc-form-group">
                        <span class="kc-form-label">Host</span>
                        <input class="kc-input" name="cache_host" value="<?= h($form['cache_host']) ?>">
                    </label>
                    <label class="kc-form-group install-small-field">
                        <span class="kc-form-label">Port</span>
                        <input class="kc-input" name="cache_port" type="number" min="1" max="65535" value="<?= h($form['cache_port']) ?>">
                    </label>
                    <label class="kc-form-group">
                        <span class="kc-form-label">Password <span class="install-muted">optional if Redis authentication is disabled</span></span>
                        <input class="kc-input" name="cache_auth" type="password" autocomplete="new-password">
                    </label>
                    <label class="kc-form-group install-small-field">
                        <span class="kc-form-label">Database</span>
                        <input class="kc-input" name="cache_db" type="number" min="0" value="<?= h($form['cache_db']) ?>">
                    </label>
                </div>
            </fieldset>

            <fieldset class="install-section">
                <legend>Local secret</legend>
                <label class="kc-form-group">
                    <span class="kc-form-label">Master secret file</span>
                    <input class="kc-input" name="secret_path" value="<?= h($form['secret_path']) ?>" required>
                    <span class="kc-form-helper">Use an absolute writable path outside <code>public_html</code>. The installer creates the file with private permissions.</span>
                </label>
            </fieldset>

            <fieldset class="install-section">
                <legend>Administrator</legend>
                <div class="install-grid">
                    <label class="kc-form-group">
                        <span class="kc-form-label">Username</span>
                        <input class="kc-input" name="admin_username" pattern="[A-Za-z0-9._\-]+" value="<?= h($form['admin_username']) ?>" required autocomplete="username">
                    </label>
                    <label class="kc-form-group">
                        <span class="kc-form-label">Email</span>
                        <input class="kc-input" name="admin_email" type="email" value="<?= h($form['admin_email']) ?>" required autocomplete="email">
                    </label>
                    <label class="kc-form-group">
                        <span class="kc-form-label">Password</span>
                        <input class="kc-input" name="admin_password" type="password" minlength="8" required autocomplete="new-password">
                    </label>
                    <label class="kc-form-group">
                        <span class="kc-form-label">Repeat password</span>
                        <input class="kc-input" name="admin_password_repeat" type="password" minlength="8" required autocomplete="new-password">
                    </label>
                </div>
            </fieldset>

            <fieldset class="install-section">
                <legend>SMTP <span class="install-muted">optional</span></legend>
                <label class="kc-checkbox-label">
                    <input class="kc-checkbox" type="checkbox" name="smtp_enabled" value="1" data-toggle="smtp-settings" <?= $form['smtp_enabled'] ? 'checked' : '' ?>>
                    Configure SMTP now
                </label>
                <p class="install-section-note">You can skip this and configure Mailer later from the administration area.</p>
                <div id="smtp-settings" class="install-grid" data-optional-section>
                    <label class="kc-form-group">
                        <span class="kc-form-label">Host</span>
                        <input class="kc-input" name="smtp_host" value="<?= h($form['smtp_host']) ?>">
                    </label>
                    <label class="kc-form-group install-small-field">
                        <span class="kc-form-label">Port</span>
                        <input class="kc-input" name="smtp_port" type="number" min="1" max="65535" value="<?= h($form['smtp_port']) ?>">
                    </label>
                    <label class="kc-form-group">
                        <span class="kc-form-label">Username</span>
                        <input class="kc-input" name="smtp_username" value="<?= h($form['smtp_username']) ?>">
                    </label>
                    <label class="kc-form-group">
                        <span class="kc-form-label">Password</span>
                        <input class="kc-input" name="smtp_password" type="password" autocomplete="new-password">
                    </label>
                    <label class="kc-form-group">
                        <span class="kc-form-label">Encryption</span>
                        <select class="kc-input" name="smtp_encryption">
                            <option value="tls" <?= $form['smtp_encryption'] === 'tls' ? 'selected' : '' ?>>TLS / STARTTLS</option>
                            <option value="ssl" <?= $form['smtp_encryption'] === 'ssl' ? 'selected' : '' ?>>SSL / SMTPS</option>
                            <option value="" <?= $form['smtp_encryption'] === '' ? 'selected' : '' ?>>None</option>
                        </select>
                    </label>
                    <label class="kc-form-group">
                        <span class="kc-form-label">From email</span>
                        <input class="kc-input" name="smtp_from_email" type="email" value="<?= h($form['smtp_from_email']) ?>">
                    </label>
                    <label class="kc-form-group">
                        <span class="kc-form-label">From name</span>
                        <input class="kc-input" name="smtp_from_name" value="<?= h($form['smtp_from_name']) ?>">
                    </label>
                    <label class="kc-form-group">
                        <span class="kc-form-label">Reply-to email <span class="install-muted">optional</span></span>
                        <input class="kc-input" name="smtp_reply_email" type="email" value="<?= h($form['smtp_reply_email']) ?>">
                    </label>
                    <label class="kc-form-group">
                        <span class="kc-form-label">Reply-to name <span class="install-muted">optional</span></span>
                        <input class="kc-input" name="smtp_reply_name" value="<?= h($form['smtp_reply_name']) ?>">
                    </label>
                </div>
            </fieldset>

            <div class="install-submit">
                <p>The installer will initialize the empty database and write <code>config.php</code> only after the database setup succeeds.</p>
                <button class="kc-btn kc-btn-primary" type="submit">Install KamiCore</button>
            </div>
        </form>
    <?php endif; ?>
</main>
<script>
(() => {
    document.querySelectorAll('[data-toggle]').forEach((toggle) => {
        const target = document.getElementById(toggle.dataset.toggle);
        if (!target) return;

        const sync = () => {
            target.classList.toggle('is-disabled', !toggle.checked);
            target.querySelectorAll('input, select, textarea').forEach((field) => {
                field.disabled = !toggle.checked;
            });
        };

        toggle.addEventListener('change', sync);
        sync();
    });
})();
</script>
</body>
</html>
