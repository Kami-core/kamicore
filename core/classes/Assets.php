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

/**
 * Request-scoped frontend asset registry.
 *
 * Assets are emitted in first-registration order. Registering the same URL
 * more than once has no effect.
 */
final class Assets
{
    /** @var array<string, true> */
    private static array $css = [];

    /** @var array<string, bool> path => defer */
    private static array $js = [];

    public static function css(string $path): void
    {
        $path = trim($path);
        if ($path === '') {
            return;
        }

        self::$css[$path] = true;
    }

    public static function renderCss(): string
    {
        if (self::$css === []) {
            return '';
        }

        $html = [];
        foreach (array_keys(self::$css) as $path) {
            $html[] = '<link rel="stylesheet" href="' . Html::escape($path) . '">';
        }

        return implode("\n", $html);
    }

    public static function js(string $path, bool $defer = true): void
    {
        $path = trim($path);
        if ($path === '' || array_key_exists($path, self::$js)) {
            return;
        }

        self::$js[$path] = $defer;
    }

    public static function renderJs(): string
    {
        if (self::$js === []) {
            return '';
        }

        $html = [];
        foreach (self::$js as $path => $defer) {
            $deferAttribute = $defer ? ' defer' : '';
            $html[] = '<script src="' . Html::escape($path) . '"' . $deferAttribute . '></script>';
        }

        return implode("\n", $html);
    }
}
