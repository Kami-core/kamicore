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

final class Settings
{
    public static function global(string $name, mixed $default = null): mixed
    {
        return defined('GLOBAL_SETTINGS') && array_key_exists($name, GLOBAL_SETTINGS)
            ? GLOBAL_SETTINGS[$name]
            : $default;
    }

    public static function domain(string $name, mixed $default = null): mixed
    {
        return defined('DOMAIN_CONFIG') && array_key_exists($name, DOMAIN_CONFIG)
            ? DOMAIN_CONFIG[$name]
            : $default;
    }

    public static function get(string $name, mixed $default = null): mixed
    {
        if (defined('DOMAIN_CONFIG') && array_key_exists($name, DOMAIN_CONFIG)) {
            return DOMAIN_CONFIG[$name];
        }

        return self::global($name, $default);
    }
}
