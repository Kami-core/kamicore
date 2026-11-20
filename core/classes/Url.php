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

    /**
     * Add or replace the language prefix of a root-relative frontend URL.
     * External, protocol-relative and relative URLs are returned unchanged.
     */
    public static function withLanguage(string $url, ?string $language = null): string
    {
        if ($url === '' || !str_starts_with($url, '/') || str_starts_with($url, '//')) {
            return $url;
        }

        $languages = array_values(array_filter(
            array_map(
                static fn(mixed $value): string => strtolower(trim((string)$value)),
                is_array(DOMAIN_CONFIG['languages'] ?? null) ? DOMAIN_CONFIG['languages'] : []
            ),
            static fn(string $value): bool => $value !== ''
        ));
        $defaultLanguage = strtolower(trim((string)(DOMAIN_CONFIG['default_language'] ?? '')));
        if ($defaultLanguage === '') {
            return $url;
        }
        if ($languages === []) {
            $languages = [$defaultLanguage];
        }

        $language = strtolower(trim((string)(
            $language ?? (defined('LANG') ? LANG : $defaultLanguage)
        )));
        if (!in_array($language, $languages, true)) {
            throw new \InvalidArgumentException("Language '{$language}' is not enabled for this domain.");
        }

        $fragment = '';
        $fragmentPos = strpos($url, '#');
        if ($fragmentPos !== false) {
            $fragment = substr($url, $fragmentPos);
            $url = substr($url, 0, $fragmentPos);
        }

        $query = '';
        $queryPos = strpos($url, '?');
        if ($queryPos !== false) {
            $query = substr($url, $queryPos);
            $url = substr($url, 0, $queryPos);
        }

        $hadTrailingSlash = str_ends_with($url, '/');
        $segments = trim($url, '/') === '' ? [] : explode('/', trim($url, '/'));
        if ($segments !== [] && in_array(strtolower($segments[0]), $languages, true)) {
            array_shift($segments);
        }

        $path = '/' . implode('/', $segments);
        if ($path !== '/' && $hadTrailingSlash) {
            $path .= '/';
        }

        if ($language !== $defaultLanguage) {
            $path = '/' . rawurlencode($language) . ($path === '/' ? '/' : $path);
        }

        return $path . $query . $fragment;
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
