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

final class Url
{
    public static function path(string ...$segments): string
    {
        $path = [];

        foreach ($segments as $segment) {
            $segment = trim($segment, '/');
            if ($segment === '') {
                continue;
            }
            $path[] = rawurlencode($segment);
        }

        return '/' . implode('/', $path);
    }

    public static function pathParams(
        string $url,
        array $params,
        ?string $prefix = null
    ): string {
        $url = rtrim($url, '/');

        foreach ($params as $key => $value) {
            $name = Request::buildKey((string) $key, $prefix);
            if ($name === '') {
                throw new \InvalidArgumentException('URL parameter name cannot be empty.');
            }

            $url .= '/'
                . rawurlencode($name)
                . '/'
                . rawurlencode((string) $value);
        }

        return $url !== '' ? $url : '/';
    }

    public static function pluginAction(
        string $page,
        string $prefix,
        string $action,
        array $params = []
    ): string {
        $url = self::path(
            $page,
            Request::buildKey('action', $prefix),
            $action
        );

        return self::pathParams($url, $params, $prefix);
    }
}
