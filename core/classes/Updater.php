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

final class Updater
{
    private const LOCK_KEY = 'kamicore:system-update';
    private const MIGRATIONS_DIR = 'install/database/migrations';

    /**
     * @return array{
     *     core_migrations:list<string>,
     *     plugins:list<string>,
     *     skipped_plugins:list<string>
     * }
     */
    public static function run(): array
    {
        if (!self::acquireLock()) {
            throw new \RuntimeException('Another KamiCore update is already running.');
        }

        try {
            self::ensureMigrationTable();

            $coreMigrations = self::runCoreMigrations();
            [$plugins, $skippedPlugins] = self::updateInstalledPlugins();
            self::invalidateExtensionCaches($plugins);

            return [
                'core_migrations' => $coreMigrations,
                'plugins' => $plugins,
                'skipped_plugins' => $skippedPlugins,
            ];
        } finally {
            self::releaseLock();
        }
    }

    private static function acquireLock(): bool
    {
        return (int)\DB::getOne(
            'select pg_try_advisory_lock(hashtext($1))::int',
            [self::LOCK_KEY]
        ) === 1;
    }

    private static function releaseLock(): void
    {
        \DB::query(
            'select pg_advisory_unlock(hashtext($1))',
            [self::LOCK_KEY]
        );
    }

    private static function ensureMigrationTable(): void
    {
        if (\DB::query(
            'create table if not exists public.core_migrations (
                migration_name text primary key,
                checksum text not null,
                applied_at timestamp without time zone default now() not null
            )'
        ) === false) {
            throw new \RuntimeException('Failed to prepare the core migration registry.');
        }
    }

    /** @return list<string> */
    private static function runCoreMigrations(): array
    {
        $directory = ROOT_PATH . self::MIGRATIONS_DIR;
        if (!is_dir($directory)) {
            return [];
        }

        $files = array_values(array_filter(
            scandir($directory) ?: [],
            static fn(string $file): bool => str_ends_with($file, '.sql')
        ));
        sort($files, SORT_STRING);

        $pending = [];
        foreach ($files as $file) {
            if (!preg_match('/^\d{3,}_[A-Za-z0-9][A-Za-z0-9_-]*\.sql$/', $file)) {
                throw new \RuntimeException("Invalid core migration filename: {$file}.");
            }

            $path = $directory . '/' . $file;
            $sql = trim((string)file_get_contents($path));
            if ($sql === '') {
                throw new \RuntimeException("Core migration is empty: {$file}.");
            }

            if (preg_match('/^\s*(BEGIN|COMMIT|ROLLBACK)\s*;/mi', $sql)) {
                throw new \RuntimeException(
                    "Core migration must not manage transactions: {$file}."
                );
            }

            $checksum = hash('sha256', $sql);
            $applied = \DB::getRow(
                'select checksum from core_migrations where migration_name=$1',
                [$file]
            );

            if ($applied) {
                if (!hash_equals((string)$applied['checksum'], $checksum)) {
                    throw new \RuntimeException(
                        "Applied core migration was modified: {$file}."
                    );
                }
                continue;
            }

            $pending[] = [$file, $sql, $checksum];
        }

        if ($pending === []) {
            return [];
        }

        if (!\DB::beginTransaction()) {
            throw new \RuntimeException('Failed to start the core migration transaction.');
        }

        $appliedFiles = [];
        try {
            foreach ($pending as [$file, $sql, $checksum]) {
                if (\DB::query($sql) === false) {
                    throw new \RuntimeException("Failed to apply core migration {$file}.");
                }

                if (\DB::insert('core_migrations', [
                    'migration_name' => $file,
                    'checksum' => $checksum,
                ]) === false) {
                    throw new \RuntimeException("Failed to record core migration {$file}.");
                }

                $appliedFiles[] = $file;
            }

            if (!\DB::commit()) {
                throw new \RuntimeException('Failed to commit core migrations.');
            }
        } catch (\Throwable $error) {
            \DB::rollBack();
            throw $error;
        }

        return $appliedFiles;
    }

    /** @return array{0:list<string>,1:list<string>} */
    private static function updateInstalledPlugins(): array
    {
        $rows = \DB::query(
            'select system_name from plugins order by plugin_id'
        );

        $updated = [];
        $skipped = [];

        while ($row = \DB::fetchRow($rows)) {
            $systemName = trim((string)($row['system_name'] ?? ''));
            if ($systemName === '') {
                continue;
            }

            $manifest = ROOT_PATH . 'plugins/' . $systemName . '/install/manifest.json';
            if (!is_file($manifest)) {
                $skipped[] = $systemName;
                continue;
            }

            if (!ExtensionManager::updatePlugin($systemName)) {
                throw new \RuntimeException("Failed to update plugin {$systemName}.");
            }

            $updated[] = $systemName;
        }

        return [$updated, $skipped];
    }

    /** @param list<string> $plugins */
    private static function invalidateExtensionCaches(array $plugins): void
    {
        \Cache::del('globals:field_map');
        \Cache::del('globals:content_type_map');

        foreach (\DB::getArr('select system_name from field_types') as $fieldType) {
            \Cache::del('globals:field_settings:' . (string)$fieldType);
        }

        if ($plugins === []) {
            return;
        }

        $domainIds = array_map('intval', \DB::getArr('select domain_id from domains'));
        foreach ($domainIds as $domainId) {
            if ($domainId < 1) {
                continue;
            }

            \Cache::del('d_' . $domainId . ':plugins');
            foreach ($plugins as $plugin) {
                \Cache::del('d_' . $domainId . ':plugin:' . $plugin);
            }
        }
    }
}
