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

final class Renderer
{
    /** @var array<string, string> */
    private static array $fileCache = [];

    /** @var array<string, array<string, string>> */
    private static array $bundleCache = [];

    public static function render(
        string $template = '',
        ?string $pluginName = null,
        array $params = [],
        bool $cacheable = false,
        ?string $compiledTemplate = null
    ): string {
        $pluginName = $pluginName !== '' ? $pluginName : null;

        if ($compiledTemplate === null) {
            if ($template === '') {
                throw new \InvalidArgumentException(
                    'Template name or compiled template must be provided.'
                );
            }

            $compiledTemplate = self::compile($template, $pluginName);
        }

        return self::renderCompiled($compiledTemplate, $pluginName, $params);
    }

    /** @return array{system_css: string, system_js: string, assets_css: string, assets_js: string, system_custom_code: string} */
    public static function systemTemplateParams(): array
    {
        return [
            // Kept as empty compatibility placeholders for existing themes.
            'system_css' => '',
            'system_js' => '',
            'assets_css' => Assets::renderCss(),
            'assets_js' => Assets::renderJs(),
            'system_custom_code' => (string) (
                defined('GLOBAL_SETTINGS')
                    ? (GLOBAL_SETTINGS['system_custom_code'] ?? '')
                    : ''
            ),
        ];
    }

    /**
     * Render a frontend HTTP error using the active theme.
     * Error templates are independent from pages, wrappers and plugins.
     */
    public static function renderError(int $status, array $params = []): string
    {
        $status = in_array($status, [403, 404], true) ? $status : 500;
        $defaults = match ($status) {
            403 => [
                'status' => '403',
                'title' => SYSTEM_DICTIONARY['lang_access_denied'] ?? 'Access denied',
                'message' => SYSTEM_DICTIONARY['lang_return_to_home'] ??  'Return to <a href="/">homepage</a>.',
            ],
            404 => [
                'status' => '404',
                'title' => SYSTEM_DICTIONARY['lang_page_not_found'] ?? 'Page not found',
                'message' => SYSTEM_DICTIONARY['lang_return_to_home'] ??  'Return to <a href="/">homepage</a>.',
            ],
            default => [
                'status' => (string)$status,
                'title' => 'Request failed',
                'message' => 'The requested page cannot be displayed.',
            ],
        };

        $params = array_replace($defaults, $params);
        $params['language'] ??= defined('LANG')
            ? LANG
            : (DOMAIN_CONFIG['default_language'] ?? 'en');
        $params['home_url'] ??= $params['language'] === (DOMAIN_CONFIG['default_language'] ?? 'en')
            ? '/'
            : '/' . $params['language'] . '/';

        try {
            $theme = (string)(DOMAIN_CONFIG['theme_path'] ?? 'default');
            $path = ROOT_PATH . "themes/{$theme}/templates/errors.tpl";
            $templates = self::loadBundle($path);
            $template = $templates["error-{$status}"] ?? $templates['error'] ?? null;

            if ($template === null) {
                throw new \RuntimeException("No error template found in {$path}.");
            }

            return self::renderCompiled(
                self::expandIncludes($template, null, ["error-{$status}"]),
                null,
                $params
            );
        } catch (\Throwable $error) {
            error_log('[Renderer] Failed to render error page: ' . $error->getMessage());

            $statusText = Html::escape((string)$params['status']);
            $title = Html::escape((string)$params['title']);
            $message = Html::escape((string)$params['message']);

            return '<!DOCTYPE html><html><head><meta charset="UTF-8">'
                . '<meta name="viewport" content="width=device-width, initial-scale=1.0">'
                . "<title>{$statusText} {$title}</title></head><body>"
                . "<main><h1>{$statusText}</h1><h2>{$title}</h2><p>{$message}</p></main>"
                . '</body></html>';
        }
    }

    /**
     * Load a template and recursively resolve all static template includes.
     * Parameter and phrase placeholders are intentionally left untouched so
     * the compiled result can be cached and rendered later with fresh data.
     */
    public static function compile(
        string $template,
        ?string $pluginName = null
    ): string {
        if ($template === '') {
            throw new \InvalidArgumentException('Template name cannot be empty.');
        }

        $pluginName = $pluginName !== '' ? $pluginName : null;
        $content = self::loadTemplate($template, $pluginName);

        return self::expandIncludes($content, $pluginName, [$template]);
    }

    private static function renderCompiled(
        string $content,
        ?string $pluginName,
        array $params
    ): string {
        $systemPhrases = defined('SYSTEM_DICTIONARY') && is_array(constant('SYSTEM_DICTIONARY'))
            ? constant('SYSTEM_DICTIONARY')
            : [];
        $phrases = array_merge(
            $systemPhrases,
            is_array($params['phrases'] ?? null) ? $params['phrases'] : []
        );

        foreach ($params as $key => $value) {
            if (is_array($value)) {
                $value = self::renderNestedValue($value, $pluginName, $phrases);
            }

            if ($value !== null && !is_array($value)) {
                $content = str_replace(
                    '{{' . $key . '}}',
                    (string) $value,
                    $content
                );
            }
        }

        foreach ($phrases as $key => $value) {
            if ($value === null || $value === '') {
                continue;
            }

            $content = str_replace(
                '{{phrase.' . $key . '}}',
                (string) $value,
                $content
            );
        }

        return $content;
    }

    /**
     * Protect literal placeholders from subsequent renderer passes.
     * HTML decodes the entities back to braces in editable form controls.
     */
    public static function protectPlaceholders(string $content): string
    {
        return str_replace(
            ['{{', '}}'],
            ['&#123;&#123;', '&#125;&#125;'],
            $content
        );
    }

    public static function finalize(string $content): string
    {
        return preg_replace('/{{\s*[\w]+\s*}}/', '', $content) ?? $content;
    }

    public static function findTemplateFile(
        string $template,
        ?string $pluginName = null
    ): ?string {
        $source = self::findTemplateSource(
            $template,
            $pluginName !== '' ? $pluginName : null
        );

        return $source['path'] ?? null;
    }

    private static function renderNestedValue(
        array $value,
        ?string $pluginName,
        array $phrases
    ): string|array {
        if (array_is_list($value)) {
            $rendered = '';
            $hasTemplates = false;

            foreach ($value as $item) {
                if (!is_array($item) || !isset($item['template'], $item['params'])) {
                    continue;
                }

                $hasTemplates = true;
                $itemParams = is_array($item['params']) ? $item['params'] : [];
                $itemParams['phrases'] = $phrases;
                $rendered .= self::render(
                    (string) $item['template'],
                    $pluginName,
                    $itemParams
                );
            }

            return $hasTemplates ? $rendered : $value;
        }

        if (isset($value['template'], $value['params'])) {
            $itemParams = is_array($value['params']) ? $value['params'] : [];
            $itemParams['phrases'] = $phrases;

            return self::render(
                (string) $value['template'],
                $pluginName,
                $itemParams
            );
        }

        return $value;
    }

    private static function expandIncludes(
        string $content,
        ?string $pluginName,
        array $stack
    ): string {
        $result = preg_replace_callback(
            '/{{\s*template:([A-Za-z0-9_.-]+)\s*}}/',
            function (array $match) use ($pluginName, $stack): string {
                $template = $match[1];

                if (in_array($template, $stack, true)) {
                    $chain = implode(' -> ', [...$stack, $template]);
                    throw new \RuntimeException(
                        "Recursive template include detected: {$chain}"
                    );
                }

                $included = self::loadTemplate($template, $pluginName);

                return self::expandIncludes(
                    $included,
                    $pluginName,
                    [...$stack, $template]
                );
            },
            $content
        );

        return $result ?? $content;
    }

    private static function loadTemplate(
        string $template,
        ?string $pluginName
    ): string {
        $source = self::findTemplateSource($template, $pluginName);

        if ($source === null) {
            $scope = $pluginName ? " for plugin {$pluginName}" : '';
            throw new \RuntimeException(
                "Template '{$template}' not found{$scope}."
            );
        }

        if ($source['type'] === 'file') {
            return self::readFile($source['path']);
        }

        $templates = self::loadBundle($source['path']);

        if (!array_key_exists($template, $templates)) {
            throw new \RuntimeException(
                "Template '{$template}' not found in bundle {$source['path']}."
            );
        }

        return $templates[$template];
    }

    /**
     * @return array{type: 'file'|'bundle', path: string}|null
     */
    private static function findTemplateSource(
        string $template,
        ?string $pluginName
    ): ?array {
        $theme = (string) (DOMAIN_CONFIG['theme_path'] ?? 'default');
        $themeTemplates = ROOT_PATH . "themes/{$theme}/templates";

        if ($pluginName !== null) {
            $candidates = [
                ['bundle', "{$themeTemplates}/{$pluginName}.tpl"],
                ['bundle', ROOT_PATH . "plugins/{$pluginName}/templates.tpl"],
                ['bundle', "{$themeTemplates}/common.tpl"],
                ['bundle', ROOT_PATH . 'core/templates/common.tpl'],
                ['file', ROOT_PATH . "core/templates/{$template}.tpl"],
            ];
        } else {
            $layoutName = str_starts_with($template, 'layouts/')
                ? substr($template, 8)
                : $template;

            $candidates = [
                ['file', "{$themeTemplates}/layouts/{$layoutName}.tpl"],
                ['bundle', "{$themeTemplates}/common.tpl"],
                ['file', ROOT_PATH . "core/templates/layouts/{$layoutName}.tpl"],
                ['bundle', ROOT_PATH . 'core/templates/common.tpl'],
                ['file', ROOT_PATH . "core/templates/{$template}.tpl"],
            ];
        }

        foreach ($candidates as [$type, $path]) {
            if (!is_file($path)) {
                continue;
            }

            if ($type === 'bundle') {
                $templates = self::loadBundle($path);
                if (!array_key_exists($template, $templates)) {
                    continue;
                }
            }

            return ['type' => $type, 'path' => $path];
        }

        return null;
    }

    /**
     * @return array<string, string>
     */
    private static function loadBundle(string $path): array
    {
		$fingerprint = $path . ':' . (string)(@filemtime($path) ?: 0) . ':' . (string)(@filesize($path) ?: 0);
		$key_fname = hash('xxh3', $fingerprint);

        if (isset(self::$bundleCache[$key_fname])) {
            return self::$bundleCache[$key_fname];
        } else {
			$cached = \Cache::get("bundle:{$key_fname}");
			if($cached) {
				self::$bundleCache[$key_fname] = $cached;
				return $cached;
			}
        }

        $source = self::readFile($path);
        $templates = [];

        preg_match_all(
            '/<!--\s*kami:template\s+([A-Za-z0-9_.-]+)\s*-->(.*?)<!--\s*\/kami:template\s*-->/s',
            $source,
            $matches,
            PREG_SET_ORDER
        );

        foreach ($matches as $match) {
            $name = $match[1];

            if (array_key_exists($name, $templates)) {
                throw new \RuntimeException(
                    "Duplicate template '{$name}' in bundle {$path}."
                );
            }

            $templates[$name] = trim($match[2], "\r\n");
        }

        if ($templates === []) {
            throw new \RuntimeException(
                "No Kami templates found in bundle {$path}."
            );
        }

        self::$bundleCache[$key_fname] = $templates;
		\Cache::set("bundle:{$key_fname}", $templates);

        return $templates;
    }

    private static function readFile(string $path): string
    {
        if (array_key_exists($path, self::$fileCache)) {
            return self::$fileCache[$path];
        }

        $content = file_get_contents($path);

        if ($content === false) {
            throw new \RuntimeException("Failed to read template file: {$path}");
        }

        self::$fileCache[$path] = $content;

        return $content;
    }
}
