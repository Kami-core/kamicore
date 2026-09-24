<?php
/**
 * KamiCore
 * SPDX-License-Identifier: Apache-2.0
 */
declare(strict_types=1);
namespace Plugins\SimpleSEO;
if (!defined('IN_KAMI')) die();

use Core\Renderer;
use Core\Utils\JsonTool;

/** JSON preparation belongs to SEO; template substitution belongs to Renderer. */
final class Schema
{
    public static function validate(string $template): void
    {
        $data = JsonTool::decode($template);
        if (array_is_list($data) || (!isset($data['@type']) && !isset($data['@graph']))) {
            throw new \InvalidArgumentException('A schema must be an object with @type or @graph.');
        }
        if (isset($data['@graph'])) {
            if (!is_array($data['@graph']) || !array_is_list($data['@graph']) || $data['@graph'] === []) {
                throw new \InvalidArgumentException('@graph must be a non-empty list of objects.');
            }
            foreach ($data['@graph'] as $node) {
                if (!is_array($node) || array_is_list($node) || !isset($node['@type'])) {
                    throw new \InvalidArgumentException('Each graph node must be an object with @type.');
                }
            }
        }
        self::checkKeys($data);
    }

    private static function checkKeys(array $data): void
    {
        foreach ($data as $key => $value) {
            if (is_string($key) && str_contains($key, '{{')) {
                throw new \InvalidArgumentException('Placeholders are allowed in values only.');
            }
            if ($key === '@type') {
                $types = is_array($value) ? $value : [$value];
                if ($types === [] || !array_is_list($types)) throw new \InvalidArgumentException('Invalid @type.');
                foreach ($types as $type) {
                    if (!is_string($type) || trim($type) === '') throw new \InvalidArgumentException('Invalid @type.');
                }
            }
            if (is_array($value)) self::checkKeys($value);
        }
    }

    public static function variables(string $template): array
    {
        preg_match_all('/\{\{([A-Za-z_][A-Za-z0-9_.-]*)\}\}/', $template, $matches);
        return array_values(array_unique($matches[1]));
    }

    public static function render(string $template, array $values): array
    {
        self::validate($template);
        $params = [];
        // An exact quoted placeholder can carry a native JSON array, number or boolean.
        $template = preg_replace_callback('/"(?:[^"\\\\]|\\\\.)*"/s',
            static function (array $match) use ($values, &$params): string {
                $literal = json_decode($match[0], true, 512, JSON_THROW_ON_ERROR);
                if (!preg_match('/^\{\{([A-Za-z_][A-Za-z0-9_.-]*)\}\}$/D', $literal, $variable)) {
                    return $match[0];
                }
                $key = '__seo_json_' . count($params);
                $params[$key] = self::scriptJson($values[$variable[1]] ?? null);
                return '{{' . $key . '}}';
            }, $template);
        foreach (self::variables($template) as $key) {
            if (array_key_exists($key, $params)) continue;
            $value = $values[$key] ?? '';
            if (!is_scalar($value)) $value = '';
            $params[$key] = substr(self::scriptJson((string)$value), 1, -1);
        }
        $json = Renderer::render(params: $params, compiledTemplate: $template);
        return self::clean(JsonTool::decode($json));
    }

    public static function text(string $template, array $values): string
    {
        $params = [];
        foreach (self::variables($template) as $key) {
            $value = $values[$key] ?? '';
            // Prevent the sequential renderer from treating content as another placeholder.
            $params[$key] = str_replace(['{', '}'], ["\u{E000}", "\u{E001}"],
                is_scalar($value) ? (string)$value : '');
        }
        return str_replace(["\u{E000}", "\u{E001}"], ['{', '}'],
            Renderer::render(params: $params, compiledTemplate: $template));
    }

    public static function encode(mixed $data, bool $pretty = false): string
    {
        return str_replace(['<', '>', '&', "'"],
            ['\u003C', '\u003E', '\u0026', '\u0027'],
            JsonTool::encode($data, $pretty));
    }

    /** Escape braces inside strings without escaping structural JSON braces. */
    public static function scriptJson(mixed $data, bool $pretty = false): string
    {
        return preg_replace_callback('/"(?:[^"\\\\]|\\\\.)*"/s',
            static fn(array $m): string => str_replace(['{','}'], ['\u007B','\u007D'], $m[0]),
            self::encode($data, $pretty));
    }

    private static function clean(array $data): array
    {
        $list = array_is_list($data);
        foreach ($data as $key => $value) {
            if (is_array($value)) $value = self::clean($value);
            if ($value === null || $value === '' || $value === []) {
                unset($data[$key]);
            } else {
                $data[$key] = $value;
            }
        }
        // Optional nested entities without identifying data should not become type-only objects.
        if (!$list && count($data) === 1 && isset($data['@type'])) return [];
        return $list ? array_values($data) : $data;
    }
}
