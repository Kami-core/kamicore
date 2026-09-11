<?php

/**
 * KamiCore
 *
 * SPDX-License-Identifier: Apache-2.0
 *
 * @see https://kamicore.org
 */

declare(strict_types=1);

final class Installer
{
    private const MIN_PHP_VERSION_ID = 80400;
    private const MIN_POSTGRES_VERSION_NUM = 170000;
    private const CONFIG_TEMPLATE = 'config/config.php.dist';
    private const CONFIG_FILE = 'config/config.php';
    private const SCHEMA_DUMP = 'install/database/schema.sql';
    private const DATA_DUMP = 'install/database/data.sql';
    private const SCHEMA_PRE_BEGIN = '-- KAMICORE_INSTALL_PRE_DATA_BEGIN';
    private const SCHEMA_PRE_END = '-- KAMICORE_INSTALL_PRE_DATA_END';
    private const SCHEMA_POST_BEGIN = '-- KAMICORE_INSTALL_POST_DATA_BEGIN';
    private const SCHEMA_POST_END = '-- KAMICORE_INSTALL_POST_DATA_END';

    private string $rootPath;
    private string $domain;

    public function __construct(string $rootPath)
    {
        $resolved = realpath($rootPath);
        if ($resolved === false) {
            throw new RuntimeException('Application root could not be resolved.');
        }

        $this->rootPath = rtrim($resolved, DIRECTORY_SEPARATOR);
        $this->domain = $this->detectDomain();
    }

    public function isInstalled(): bool
    {
        return is_file($this->path(self::CONFIG_FILE));
    }

    public function domain(): string
    {
        return $this->domain;
    }

    public function defaultSecretPath(): string
    {
        return dirname($this->rootPath)
            . DIRECTORY_SEPARATOR . 'private'
            . DIRECTORY_SEPARATOR . 'kami.secret';
    }

    public function environmentChecks(): array
    {
        $checks = [
            'PHP 8.4+' => PHP_VERSION_ID >= self::MIN_PHP_VERSION_ID,
            'PostgreSQL extension' => extension_loaded('pgsql'),
            'Sodium extension' => extension_loaded('sodium'),
            'Argon2id password hashing' => defined('PASSWORD_ARGON2ID'),
            'Configuration template' => is_readable($this->path(self::CONFIG_TEMPLATE)),
            'Database schema dump' => is_readable($this->path(self::SCHEMA_DUMP)),
            'Database data dump' => is_readable($this->path(self::DATA_DUMP)),
            'Configuration directory writable' => is_writable($this->path('config')),
        ];

        return $checks;
    }

    public function install(array $input): void
    {
        if ($this->isInstalled()) {
            throw new RuntimeException('KamiCore is already installed.');
        }

        $data = $this->validateInput($input);
        $this->assertEnvironment($data['cache_enabled']);
        $this->validateDumpFiles();
        $this->assertSecretPath($data['secret_path']);
        $this->assertConfigDirectoryWritable();

        $connection = $this->connectDatabase($data);
        $this->preflightDatabase($connection);

        if ($data['cache_enabled']) {
            $this->preflightRedis($data);
        }

        $passwordHash = password_hash($data['admin_password'], PASSWORD_ARGON2ID);
        if (!is_string($passwordHash) || $passwordHash === '') {
            throw new RuntimeException('Unable to hash the administrator password.');
        }

        $masterKey = random_bytes(SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_KEYBYTES);
        $cacheEncryptKey = bin2hex(random_bytes(32));
        $secretTemp = null;
        $configTemp = null;
        $committed = false;

        try {
            $secretTemp = $this->stageSecretFile($data['secret_path'], $masterKey);
            $configTemp = $this->stageConfigFile(
                $this->renderConfig($data, $cacheEncryptKey)
            );

            $this->query($connection, 'BEGIN');

            [$preData, $postData] = $this->loadSchemaSections();
            $this->query($connection, $preData);
            $this->importData($connection, $this->path(self::DATA_DUMP));
            $this->query($connection, $postData);

            // pg_dump deliberately clears search_path while restoring schema objects.
            // Restore the normal application lookup path before installer queries.
            $this->query($connection, 'SET search_path TO public, pg_catalog');

            $this->assertSnapshotTables($connection);

            $this->configureInstallation(
                $connection,
                $data,
                $passwordHash,
                $masterKey
            );

            $this->assertInstalledDatabase($connection);

            $this->query($connection, 'COMMIT');
            $committed = true;

            if (!@rename($secretTemp, $data['secret_path'])) {
                throw new RuntimeException('Unable to finalize the master secret file.');
            }
            $secretTemp = null;

            $configPath = $this->path(self::CONFIG_FILE);
            if (!@rename($configTemp, $configPath)) {
                throw new RuntimeException('Unable to finalize config.php.');
            }
            $configTemp = null;
            @chmod($configPath, 0600);
        } catch (Throwable $error) {
            if (!$committed && $connection instanceof \PgSql\Connection) {
                @pg_query($connection, 'ROLLBACK');
            }

            if ($secretTemp !== null && is_file($secretTemp)) {
                @unlink($secretTemp);
            }
            if ($configTemp !== null && is_file($configTemp)) {
                @unlink($configTemp);
            }

            if ($committed && !$this->isInstalled()) {
                throw new RuntimeException(
                    'The database initialization was committed, but local configuration could not be finalized. '
                    . 'Restore an empty database before retrying the installer.',
                    0,
                    $error
                );
            }

            throw $error;
        } finally {
            sodium_memzero($masterKey);
            if ($connection instanceof \PgSql\Connection) {
                @pg_close($connection);
            }
        }
    }

    private function validateInput(array $input): array
    {
        $data = [
            'db_host' => trim((string)($input['db_host'] ?? '')),
            'db_port' => (int)($input['db_port'] ?? 5432),
            'db_name' => trim((string)($input['db_name'] ?? '')),
            'db_user' => trim((string)($input['db_user'] ?? '')),
            'db_password' => (string)($input['db_password'] ?? ''),
            'cache_enabled' => !empty($input['cache_enabled']),
            'cache_host' => trim((string)($input['cache_host'] ?? 'localhost')),
            'cache_port' => (int)($input['cache_port'] ?? 6379),
            'cache_auth' => (string)($input['cache_auth'] ?? ''),
            'cache_db' => (int)($input['cache_db'] ?? 0),
            'secret_path' => trim((string)($input['secret_path'] ?? '')),
            'smtp_enabled' => !empty($input['smtp_enabled']),
            'smtp_host' => trim((string)($input['smtp_host'] ?? '')),
            'smtp_port' => (int)($input['smtp_port'] ?? 587),
            'smtp_username' => trim((string)($input['smtp_username'] ?? '')),
            'smtp_password' => (string)($input['smtp_password'] ?? ''),
            'smtp_encryption' => trim((string)($input['smtp_encryption'] ?? 'tls')),
            'smtp_from_email' => trim((string)($input['smtp_from_email'] ?? '')),
            'smtp_from_name' => trim((string)($input['smtp_from_name'] ?? 'KamiCore')),
            'smtp_reply_email' => trim((string)($input['smtp_reply_email'] ?? '')),
            'smtp_reply_name' => trim((string)($input['smtp_reply_name'] ?? '')),
            'admin_username' => trim((string)($input['admin_username'] ?? '')),
            'admin_email' => trim((string)($input['admin_email'] ?? '')),
            'admin_password' => (string)($input['admin_password'] ?? ''),
            'admin_password_repeat' => (string)($input['admin_password_repeat'] ?? ''),
        ];

        $errors = [];

        if ($data['db_host'] === '') {
            $errors[] = 'Database host is required.';
        }
        if ($data['db_port'] < 1 || $data['db_port'] > 65535) {
            $errors[] = 'Database port is invalid.';
        }
        if ($data['db_name'] === '') {
            $errors[] = 'Database name is required.';
        }
        if ($data['db_user'] === '') {
            $errors[] = 'Database user is required.';
        }

        if ($data['secret_path'] === '') {
            $errors[] = 'Master secret file path is required.';
        }

        if ($data['admin_username'] === '') {
            $errors[] = 'Administrator username is required.';
        } elseif (!preg_match('/^[A-Za-z0-9._-]+$/', $data['admin_username'])) {
            $errors[] = 'Administrator username may contain only letters, numbers, dots, underscores and hyphens.';
        }

        if ($data['admin_email'] === '' || filter_var($data['admin_email'], FILTER_VALIDATE_EMAIL) === false) {
            $errors[] = 'A valid administrator email is required.';
        }

        if (strlen($data['admin_password']) < 8) {
            $errors[] = 'Administrator password must contain at least 8 characters.';
        }
        if ($data['admin_password'] !== $data['admin_password_repeat']) {
            $errors[] = 'Administrator passwords do not match.';
        }

        if ($data['cache_enabled']) {
            if ($data['cache_host'] === '') {
                $errors[] = 'Redis host is required when cache is enabled.';
            }
            if ($data['cache_port'] < 1 || $data['cache_port'] > 65535) {
                $errors[] = 'Redis port is invalid.';
            }
            if ($data['cache_db'] < 0) {
                $errors[] = 'Redis database index cannot be negative.';
            }
        }

        if ($data['smtp_enabled']) {
            if ($data['smtp_host'] === '') {
                $errors[] = 'SMTP host is required.';
            }
            if ($data['smtp_port'] < 1 || $data['smtp_port'] > 65535) {
                $errors[] = 'SMTP port is invalid.';
            }
            if ($data['smtp_username'] === '') {
                $errors[] = 'SMTP username is required.';
            }
            if ($data['smtp_password'] === '') {
                $errors[] = 'SMTP password is required.';
            }
            if (!in_array($data['smtp_encryption'], ['', 'tls', 'ssl'], true)) {
                $errors[] = 'SMTP encryption value is invalid.';
            }
            if (
                $data['smtp_from_email'] === ''
                || filter_var($data['smtp_from_email'], FILTER_VALIDATE_EMAIL) === false
            ) {
                $errors[] = 'A valid SMTP sender email is required.';
            }
            if ($data['smtp_from_name'] === '') {
                $errors[] = 'SMTP sender name is required.';
            }
            if (
                $data['smtp_reply_email'] !== ''
                && filter_var($data['smtp_reply_email'], FILTER_VALIDATE_EMAIL) === false
            ) {
                $errors[] = 'SMTP reply-to email is invalid.';
            }
        }

        if ($errors !== []) {
            throw new InvalidArgumentException(implode("\n", $errors));
        }

        return $data;
    }

    private function assertEnvironment(bool $cacheEnabled): void
    {
        $failed = array_keys(array_filter(
            $this->environmentChecks(),
            static fn(bool $ok): bool => !$ok
        ));

        if ($cacheEnabled && !extension_loaded('redis')) {
            $failed[] = 'Redis extension';
        }

        if ($failed !== []) {
            throw new RuntimeException(
                'Server requirements are not satisfied: ' . implode(', ', $failed) . '.'
            );
        }
    }

    private function validateDumpFiles(): void
    {
        $this->loadSchemaSections();

        $handle = @fopen($this->path(self::DATA_DUMP), 'rb');
        if ($handle === false) {
            throw new RuntimeException('Database data dump is not readable.');
        }

        $copyBlocks = 0;
        $inCopy = false;
        try {
            while (($line = fgets($handle)) !== false) {
                $trimmed = rtrim($line, "\r\n");
                if (!$inCopy && preg_match('/^COPY\s+/i', ltrim($trimmed))) {
                    $inCopy = true;
                    $copyBlocks++;
                    continue;
                }
                if ($inCopy && $trimmed === '\\.') {
                    $inCopy = false;
                }
            }
        } finally {
            fclose($handle);
        }

        if ($copyBlocks < 1 || $inCopy) {
            throw new RuntimeException('Database data dump has an invalid COPY structure.');
        }
    }

    private function assertSecretPath(string $path): void
    {
        if (!$this->isAbsolutePath($path)) {
            throw new RuntimeException('Master secret file path must be absolute.');
        }

        if (file_exists($path)) {
            throw new RuntimeException('Master secret file already exists. Choose another path or remove the old file.');
        }

        $directory = realpath(dirname($path));
        if ($directory === false || !is_dir($directory) || !is_writable($directory)) {
            throw new RuntimeException('Master secret directory does not exist or is not writable.');
        }

        $publicRoot = realpath($this->rootPath);
        if ($publicRoot !== false && $this->pathStartsWith($directory, $publicRoot)) {
            throw new RuntimeException('Master secret file must be stored outside public_html.');
        }

        $probe = tempnam($directory, '.kami-install-test-');
        if ($probe === false) {
            throw new RuntimeException('Unable to create a file in the master secret directory.');
        }
        $renamed = $probe . '.rename';
        $ok = @rename($probe, $renamed);
        @unlink($probe);
        @unlink($renamed);
        if (!$ok) {
            throw new RuntimeException('Master secret directory does not support file finalization.');
        }
    }

    private function assertConfigDirectoryWritable(): void
    {
        $directory = $this->path('config');
        if (!is_dir($directory) || !is_writable($directory)) {
            throw new RuntimeException('Configuration directory is not writable.');
        }

        $probe = tempnam($directory, '.kami-install-test-');
        if ($probe === false) {
            throw new RuntimeException('Unable to create a file in the configuration directory.');
        }
        $renamed = $probe . '.rename';
        $ok = @rename($probe, $renamed);
        @unlink($probe);
        @unlink($renamed);
        if (!$ok) {
            throw new RuntimeException('Configuration directory does not support file finalization.');
        }
    }

    private function connectDatabase(array $data)
    {
        $connectionString = implode(' ', [
            'host=' . $this->quoteConnectionValue($data['db_host']),
            'port=' . $data['db_port'],
            'dbname=' . $this->quoteConnectionValue($data['db_name']),
            'user=' . $this->quoteConnectionValue($data['db_user']),
            'password=' . $this->quoteConnectionValue($data['db_password']),
            'connect_timeout=5',
        ]);

        $connection = @pg_connect($connectionString, PGSQL_CONNECT_FORCE_NEW);
        if ($connection === false) {
            throw new RuntimeException('Unable to connect to PostgreSQL using the supplied credentials.');
        }

        if (pg_set_client_encoding($connection, 'UTF8') !== 0) {
            pg_close($connection);
            throw new RuntimeException('Unable to set PostgreSQL client encoding to UTF-8.');
        }

        return $connection;
    }

    private function preflightDatabase($connection): void
    {
        $versionResult = $this->query($connection, 'SHOW server_version_num');
        $version = (int)pg_fetch_result($versionResult, 0, 0);
        if ($version < self::MIN_POSTGRES_VERSION_NUM) {
            throw new RuntimeException('PostgreSQL 17 or newer is required.');
        }

        $tablesResult = $this->query(
            $connection,
            "SELECT count(*) FROM pg_catalog.pg_tables WHERE schemaname='public'"
        );
        if ((int)pg_fetch_result($tablesResult, 0, 0) > 0) {
            throw new RuntimeException('The selected PostgreSQL database is not empty.');
        }

        $this->query($connection, 'BEGIN');
        try {
            $this->query($connection, 'CREATE EXTENSION IF NOT EXISTS citext WITH SCHEMA public');
            $this->query($connection, 'CREATE EXTENSION IF NOT EXISTS pg_trgm WITH SCHEMA public');
            $this->query($connection, 'SELECT gen_random_uuid()');
            $this->query($connection, 'CREATE TABLE public.__kami_install_probe (id integer)');
            $this->query($connection, 'ROLLBACK');
        } catch (Throwable $error) {
            @pg_query($connection, 'ROLLBACK');
            throw new RuntimeException(
                'Database preflight failed. The database user must be able to create tables and use citext and pg_trgm.',
                0,
                $error
            );
        }
    }

    private function preflightRedis(array $data): void
    {
        $redis = new Redis();
        try {
            if (!$redis->connect($data['cache_host'], $data['cache_port'], 2.0)) {
                throw new RuntimeException('Redis connection failed.');
            }
            if ($data['cache_auth'] !== '' && !$redis->auth($data['cache_auth'])) {
                throw new RuntimeException('Redis authentication failed.');
            }
            if (!$redis->select($data['cache_db'])) {
                throw new RuntimeException('Redis database selection failed.');
            }
            $redis->ping();
        } catch (RedisException $error) {
            $message = $error->getMessage();
            if ($data['cache_auth'] === '' && str_contains(strtoupper($message), 'NOAUTH')) {
                throw new RuntimeException(
                    'Redis requires authentication. Enter the Redis password and try again.',
                    0,
                    $error
                );
            }

            throw new RuntimeException('Redis preflight failed: ' . $message, 0, $error);
        } finally {
            try {
                $redis->close();
            } catch (Throwable) {
            }
        }
    }

    private function loadSchemaSections(): array
    {
        $schema = @file_get_contents($this->path(self::SCHEMA_DUMP));
        if (!is_string($schema) || $schema === '') {
            throw new RuntimeException('Database schema dump is empty or unreadable.');
        }

        $pre = $this->between($schema, self::SCHEMA_PRE_BEGIN, self::SCHEMA_PRE_END);
        $post = $this->between($schema, self::SCHEMA_POST_BEGIN, self::SCHEMA_POST_END);

        $pre = $this->stripPsqlMetaCommands($pre);
        $post = $this->stripPsqlMetaCommands($post);

        if (trim($pre) === '' || trim($post) === '') {
            throw new RuntimeException('Database schema dump sections are missing.');
        }

        return [$pre, $post];
    }

    private function importData($connection, string $path): void
    {
        $handle = @fopen($path, 'rb');
        if ($handle === false) {
            throw new RuntimeException('Database data dump is not readable.');
        }

        $buffer = '';
        $inCopy = false;

        try {
            while (($line = fgets($handle)) !== false) {
                $trimmedLine = rtrim($line, "\r\n");
                $left = ltrim($trimmedLine);

                if ($inCopy) {
                    if ($trimmedLine === '\\.') {
                        if (!pg_end_copy($connection)) {
                            throw new RuntimeException('PostgreSQL COPY could not be finalized.');
                        }
                        $inCopy = false;
                        continue;
                    }

                    if (!pg_put_line($connection, $line)) {
                        throw new RuntimeException('PostgreSQL COPY data transfer failed.');
                    }
                    continue;
                }

                if ($left === '' || str_starts_with($left, '--') || str_starts_with($left, '\\')) {
                    continue;
                }

                if (preg_match('/^COPY\s+/i', $left)) {
                    if (trim($buffer) !== '') {
                        $this->query($connection, $buffer);
                        $buffer = '';
                    }

                    $result = $this->query($connection, $trimmedLine);
                    if (pg_result_status($result) !== PGSQL_COPY_IN) {
                        throw new RuntimeException('PostgreSQL did not enter COPY mode.');
                    }
                    $inCopy = true;
                    continue;
                }

                $buffer .= $line;
                if (preg_match('/;\s*$/', $trimmedLine)) {
                    $this->query($connection, $buffer);
                    $buffer = '';
                }
            }

            if ($inCopy) {
                throw new RuntimeException('Database data dump ended inside a COPY block.');
            }
            if (trim($buffer) !== '') {
                $this->query($connection, $buffer);
            }
        } finally {
            fclose($handle);
        }
    }

    private function assertSnapshotTables($connection): void
    {
        foreach (['global_settings', 'users', 'domains', 'plugins', 'plugin_domains'] as $table) {
            $result = $this->queryParams(
                $connection,
                "SELECT to_regclass($1) IS NOT NULL",
                ['public.' . $table]
            );

            if (pg_fetch_result($result, 0, 0) !== 't') {
                throw new RuntimeException(
                    "Installation snapshot table is missing: public.{$table}."
                );
            }
        }
    }

    private function configureInstallation(
        $connection,
        array $data,
        string $passwordHash,
        string $masterKey
    ): void {
        $rootResult = $this->queryParams(
            $connection,
            "SELECT value FROM global_settings WHERE varname='usergroup_root' LIMIT 1",
            []
        );
        $rootGroupId = pg_num_rows($rootResult) > 0
            ? (int)pg_fetch_result($rootResult, 0, 0)
            : 0;
        if ($rootGroupId < 1) {
            throw new RuntimeException('Root user group is missing from the installation data.');
        }

        $adminResult = $this->queryParams(
            $connection,
            'UPDATE users
             SET username=$1,
                 email=$2,
                 password_hash=$3,
                 created_at=now(),
                 last_login=NULL,
                 login_data=NULL,
                 usergroup_id=$4,
                 is_active=true,
                 email_verified_at=now(),
                 user_uuid=gen_random_uuid()
             WHERE user_id=1',
            [
                $data['admin_username'],
                $data['admin_email'],
                $passwordHash,
                $rootGroupId,
            ]
        );
        if (pg_affected_rows($adminResult) !== 1) {
            throw new RuntimeException('Administrator placeholder is missing from the installation data.');
        }

        $domainResult = $this->queryParams(
            $connection,
            'UPDATE domains
             SET domain_name=$1,
                 domain_uuid=gen_random_uuid()
             WHERE domain_id=1',
            [$this->domain]
        );
        if (pg_affected_rows($domainResult) !== 1) {
            throw new RuntimeException('Domain placeholder is missing from the installation data.');
        }

        $this->query($connection, 'DELETE FROM domain_aliases');

        if ($data['smtp_enabled']) {
            $this->configureSmtp($connection, $data, $masterKey);
        }
    }

    private function configureSmtp($connection, array $data, string $masterKey): void
    {
        $settings = [
            'mailer' => 'smtp',
            'host' => $data['smtp_host'],
            'port' => $data['smtp_port'],
            'username' => $data['smtp_username'],
            'encryption' => $data['smtp_encryption'],
            'from_email' => $data['smtp_from_email'],
            'from_name' => $data['smtp_from_name'],
            'reply_to_email' => $data['smtp_reply_email'],
            'reply_to_name' => $data['smtp_reply_name'],
        ];

        $json = json_encode($settings, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
        $mailerResult = $this->queryParams(
            $connection,
            "INSERT INTO plugin_domains(plugin_id, domain_id, local_settings)
             SELECT plugin_id, 1, $1::jsonb
             FROM plugins
             WHERE system_name='Mailer'
             ON CONFLICT (plugin_id, domain_id)
             DO UPDATE SET local_settings=EXCLUDED.local_settings",
            [$json]
        );
        if (pg_affected_rows($mailerResult) < 1) {
            throw new RuntimeException('Mailer plugin is missing from the installation data.');
        }

        $namespace = 'Mailer';
        $name = 'smtp_password';
        $domainId = 1;
        $additionalData = $namespace . "\0" . $name . "\0" . $domainId;
        $nonce = random_bytes(SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_NPUBBYTES);
        $ciphertext = sodium_crypto_aead_xchacha20poly1305_ietf_encrypt(
            $data['smtp_password'],
            $additionalData,
            $nonce,
            $masterKey
        );
        $encoded = '\\x' . bin2hex($nonce . $ciphertext);

        $this->queryParams(
            $connection,
            'INSERT INTO secrets(namespace, secret_name, domain_id, encrypted_value)
             VALUES($1, $2, $3, $4::bytea)
             ON CONFLICT ON CONSTRAINT secrets_scope_unique
             DO UPDATE SET encrypted_value=EXCLUDED.encrypted_value, updated_at=now()',
            [$namespace, $name, $domainId, $encoded]
        );
    }

    private function assertInstalledDatabase($connection): void
    {
        $checks = [
            "SELECT count(*)=1 FROM users WHERE user_id=1 AND is_active=true AND password_hash IS NOT NULL",
            "SELECT count(*)=1 FROM users WHERE user_id=0",
            "SELECT count(*)=1 FROM domains WHERE domain_id=1 AND domain_name=$1",
            "SELECT count(*)>0 FROM pages",
            "SELECT count(*)>0 FROM plugins",
            "SELECT count(*)>0 FROM translations",
        ];

        foreach ($checks as $sql) {
            $params = str_contains($sql, '$1') ? [$this->domain] : [];
            $result = $this->queryParams($connection, $sql, $params);
            if (pg_num_rows($result) < 1 || pg_fetch_result($result, 0, 0) !== 't') {
                throw new RuntimeException('Installed database validation failed.');
            }
        }
    }

    private function stageSecretFile(string $finalPath, string $masterKey): string
    {
        $directory = dirname($finalPath);
        $temp = tempnam($directory, '.kami-secret-');
        if ($temp === false) {
            throw new RuntimeException('Unable to stage the master secret file.');
        }

        $written = file_put_contents($temp, base64_encode($masterKey) . PHP_EOL, LOCK_EX);
        if ($written === false) {
            @unlink($temp);
            throw new RuntimeException('Unable to write the staged master secret file.');
        }

        @chmod($temp, 0600);
        return $temp;
    }

    private function stageConfigFile(string $content): string
    {
        $directory = $this->path('config');
        $temp = tempnam($directory, '.config-install-');
        if ($temp === false) {
            throw new RuntimeException('Unable to stage config.php.');
        }

        $written = file_put_contents($temp, $content, LOCK_EX);
        if ($written === false) {
            @unlink($temp);
            throw new RuntimeException('Unable to write the staged config.php.');
        }

        @chmod($temp, 0600);
        return $temp;
    }

    private function renderConfig(array $data, string $cacheEncryptKey): string
    {
        $template = @file_get_contents($this->path(self::CONFIG_TEMPLATE));
        if (!is_string($template) || $template === '') {
            throw new RuntimeException('Configuration template is unreadable.');
        }

        $rawPlaceholders = [
            '__DB_HOST__',
            '__DB_PORT__',
            '__DB_USER__',
            '__DB_PASSWORD__',
            '__DB_NAME__',
            '__CACHE_ENABLED__',
            '__CACHE_HOST__',
            '__CACHE_PORT__',
            '__CACHE_AUTH__',
            '__CACHE_DB__',
            '__CACHE_ENCRYPT_KEY__',
            '__SECRET_KEY_FILE__',
        ];

        foreach ($rawPlaceholders as $placeholder) {
            if (!str_contains($template, $placeholder)) {
                throw new RuntimeException("Configuration placeholder is missing: {$placeholder}");
            }
        }

        $values = [
            '__DB_HOST__' => $data['db_host'],
            '__DB_PORT__' => (string)$data['db_port'],
            '__DB_USER__' => $data['db_user'],
            '__DB_PASSWORD__' => $data['db_password'],
            '__DB_NAME__' => $data['db_name'],
            '__CACHE_ENABLED__' => $data['cache_enabled'] ? '1' : '0',
            '__CACHE_HOST__' => $data['cache_host'],
            '__CACHE_PORT__' => (string)$data['cache_port'],
            '__CACHE_AUTH__' => $data['cache_auth'],
            '__CACHE_DB__' => (string)$data['cache_db'],
            '__CACHE_ENCRYPT_KEY__' => $cacheEncryptKey,
            '__SECRET_KEY_FILE__' => $data['secret_path'],
        ];

        $replacements = [];
        foreach ($values as $placeholder => $value) {
            $replacements["'{$placeholder}'"] = var_export($value, true);
        }

        return strtr($template, $replacements);
    }

    private function query($connection, string $sql)
    {
        $result = @pg_query($connection, $sql);
        if ($result === false) {
            throw new RuntimeException('PostgreSQL error: ' . pg_last_error($connection));
        }
        return $result;
    }

    private function queryParams($connection, string $sql, array $params)
    {
        $result = @pg_query_params($connection, $sql, array_values($params));
        if ($result === false) {
            throw new RuntimeException('PostgreSQL error: ' . pg_last_error($connection));
        }
        return $result;
    }

    private function between(string $source, string $begin, string $end): string
    {
        $start = strpos($source, $begin);
        $finish = strpos($source, $end);
        if ($start === false || $finish === false || $finish <= $start) {
            throw new RuntimeException('Database schema dump markers are invalid.');
        }

        $start += strlen($begin);
        return substr($source, $start, $finish - $start);
    }

    private function stripPsqlMetaCommands(string $sql): string
    {
        $lines = preg_split('/\R/', $sql) ?: [];
        $lines = array_filter(
            $lines,
            static fn(string $line): bool => !str_starts_with(ltrim($line), '\\')
        );
        return implode(PHP_EOL, $lines);
    }

    private function detectDomain(): string
    {
        $host = trim((string)($_SERVER['HTTP_HOST'] ?? ''));
        if ($host === '') {
            throw new RuntimeException('HTTP host is unavailable.');
        }

        if (preg_match('/^\[([^]]+)](?::\d+)?$/', $host, $match)) {
            $host = $match[1];
        } else {
            $host = preg_replace('/:\d+$/', '', $host) ?? $host;
        }

        $host = strtolower(rtrim($host, '.'));
        $valid = $host === 'localhost'
            || filter_var($host, FILTER_VALIDATE_IP) !== false
            || filter_var($host, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME) !== false;

        if (!$valid) {
            throw new RuntimeException('The current HTTP host is invalid.');
        }

        return $host;
    }

    private function quoteConnectionValue(string $value): string
    {
        return "'" . str_replace(['\\', "'"], ['\\\\', "\\'"], $value) . "'";
    }

    private function isAbsolutePath(string $path): bool
    {
        return str_starts_with($path, DIRECTORY_SEPARATOR)
            || preg_match('/^[A-Za-z]:[\\\\\/]/', $path) === 1;
    }

    private function pathStartsWith(string $path, string $prefix): bool
    {
        $path = rtrim($path, DIRECTORY_SEPARATOR);
        $prefix = rtrim($prefix, DIRECTORY_SEPARATOR);
        return $path === $prefix || str_starts_with($path, $prefix . DIRECTORY_SEPARATOR);
    }

    private function path(string $relative): string
    {
        return $this->rootPath . DIRECTORY_SEPARATOR
            . str_replace('/', DIRECTORY_SEPARATOR, $relative);
    }
}
