<?php

/**
 * KamiCore
 *
 * SPDX-License-Identifier: Apache-2.0
 *
 * @see https://kamicore.org
 */

declare(strict_types=1);

if(!defined('IN_KAMI')) die();

/**
 * Lightweight PSR-4 autoloader for:
 *   - Core\*           -> /core/classes/...
 *   - Plugins\*        -> /plugins/...
 *   - Third-party libs -> configured via /config/third_party.php
 *
 * Notes:
 * - Keep third-party libraries unmodified (no vendor code changes).
 * - Avoid serving server-side third-party libraries via the web; it's server-side only.
 */

spl_autoload_register(function ($class) {
	static $psr4 = null;

    if ($psr4 === null) {
        // Base PSR-4 prefixes
        $psr4 = [
            'Core\\'    => ROOT_PATH . 'core/classes/',
            'Plugins\\' => ROOT_PATH . 'plugins/',
        ];

        // Load third-party namespace map, if any
        $tpConfig = ROOT_PATH . 'config/third_party.php';
        if (is_file($tpConfig)) {
            $thirdPartyMap = require $tpConfig;
            // Merge while preserving existing keys
            foreach ($thirdPartyMap as $prefix => $baseDir) {
                // Normalize trailing slash
                $psr4[$prefix] = rtrim($baseDir, '/\\') . DIRECTORY_SEPARATOR;
            }
        }
    }

    // Resolve by the longest matching namespace prefix
    foreach ($psr4 as $prefix => $baseDir) {
        $len = strlen($prefix);
        if (strncmp($class, $prefix, $len) !== 0) {
            continue;
        }
        $relative = substr($class, $len);
        $path = $baseDir . str_replace('\\', DIRECTORY_SEPARATOR, $relative) . '.php';
        if (is_file($path)) {
            require $path;
        } else {
	    // echo "NOT FOUND: $path";
        }

    }
	});
