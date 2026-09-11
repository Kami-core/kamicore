<?php

/**
 * KamiCore
 *
 * SPDX-License-Identifier: Apache-2.0
 *
 * @see https://kamicore.org
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

define('IN_KAMI', true);
define('ROOT_PATH', dirname(__DIR__) . DIRECTORY_SEPARATOR);

$_SERVER['HTTP_HOST'] ??= 'localhost';
$_SERVER['REQUEST_URI'] ??= '/';

$configFile = ROOT_PATH . 'config/config.php';
if (!is_file($configFile)) {
    fwrite(STDERR, "KamiCore is not installed: config/config.php is missing.\n");
    exit(1);
}

require_once ROOT_PATH . 'core/autoload.php';
require_once $configFile;
require_once ROOT_PATH . 'core/functions.php';
require_once ROOT_PATH . 'core/classes/Pgsql.php';
require_once ROOT_PATH . 'core/classes/cache/Cache.php';

if (!DB::connect(
    $db_config['host'],
    $db_config['user'],
    $db_config['password'],
    $db_config['name'],
    'utf8',
    (int)$db_config['port']
)) {
    fwrite(STDERR, "Unable to connect to PostgreSQL.\n");
    exit(1);
}

Cache::configure($redis_config);
if (!Cache::connect()) {
    fwrite(STDERR, "Unable to connect to the configured cache backend.\n");
    exit(1);
}

try {
    $result = Core\Updater::run();

    echo 'KamiCore update completed.' . PHP_EOL;

    if ($result['core_migrations'] === []) {
        echo 'Core migrations: none.' . PHP_EOL;
    } else {
        echo 'Core migrations: ' . implode(', ', $result['core_migrations']) . PHP_EOL;
    }

    echo 'Plugins updated: ' . count($result['plugins']) . PHP_EOL;

    if ($result['skipped_plugins'] !== []) {
        echo 'Plugins skipped (package missing): '
            . implode(', ', $result['skipped_plugins']) . PHP_EOL;
    }
} catch (Throwable $error) {
    fwrite(STDERR, 'Update failed: ' . $error->getMessage() . PHP_EOL);
    exit(1);
} finally {
    Cache::disconnect();
}
