<?php

/**
 * KamiCore
 *
 * SPDX-License-Identifier: Apache-2.0
 *
 * @see https://kamicore.org
 */

declare(strict_types=1);

namespace Core\Utils;

use JsonException;
use RuntimeException;

final class JsonTool
{
    public static function loadFile(string $file, bool $allowComments = false): array
    {
        if (!is_file($file)) {
            throw new RuntimeException("JSON file not found: {$file}");
        }

        if (!is_readable($file)) {
            throw new RuntimeException("JSON file is not readable: {$file}");
        }

        $json = file_get_contents($file);

        if ($json === false) {
            throw new RuntimeException("Failed to read JSON file: {$file}");
        }

        return self::decode($json, $allowComments, $file);
    }

    public static function decode(string $json, bool $allowComments = false, ?string $source = null): array
    {
        if ($allowComments) {
            $json = self::stripComments($json);
        }

        try {
            $data = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            $message = $source !== null
                ? "Invalid JSON in {$source}: {$e->getMessage()}"
                : "Invalid JSON: {$e->getMessage()}";

            throw new RuntimeException($message, 0, $e);
        }

        if (!is_array($data)) {
            $message = $source !== null
                ? "JSON root must be an object or array in {$source}"
                : "JSON root must be an object or array";

            throw new RuntimeException($message);
        }

        return $data;
    }

    public static function decodeArray(mixed $value): array
    {
        if (is_array($value)) {
            return $value;
        }

        if (!is_string($value) || trim($value) === '') {
            return [];
        }

        try {
            $data = json_decode($value, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return [];
        }

        return is_array($data) ? $data : [];
    }

    public static function encode(
        mixed $data,
        bool $pretty = true,
        bool $unescapedUnicode = true,
        bool $unescapedSlashes = true
    ): string {
        $flags = JSON_THROW_ON_ERROR;

        if ($pretty) {
            $flags |= JSON_PRETTY_PRINT;
        }

        if ($unescapedUnicode) {
            $flags |= JSON_UNESCAPED_UNICODE;
        }

        if ($unescapedSlashes) {
            $flags |= JSON_UNESCAPED_SLASHES;
        }

        return self::encodeWithFlags($data, $flags);
    }

    public static function encodeForHtml(mixed $data): string
    {
        return self::encodeWithFlags(
            $data,
            JSON_UNESCAPED_UNICODE
                | JSON_UNESCAPED_SLASHES
                | JSON_HEX_TAG
                | JSON_HEX_AMP
                | JSON_HEX_APOS
                | JSON_HEX_QUOT
                | JSON_THROW_ON_ERROR
        );
    }

    public static function saveFile(
        string $file,
        array $data,
        bool $pretty = true,
        bool $unescapedUnicode = true,
        bool $unescapedSlashes = true,
        bool $createDir = false
    ): void {
        $dir = dirname($file);

        if (!is_dir($dir)) {
            if (!$createDir) {
                throw new RuntimeException("Target directory does not exist: {$dir}");
            }

            if (!mkdir($dir, 0775, true) && !is_dir($dir)) {
                throw new RuntimeException("Failed to create directory: {$dir}");
            }
        }

        if (!is_writable($dir)) {
            throw new RuntimeException("Target directory is not writable: {$dir}");
        }

        $json = self::encode($data, $pretty, $unescapedUnicode, $unescapedSlashes);

        // Final newline is useful for git diffs and editors
        $json .= PHP_EOL;

        $tempFile = $file . '.tmp';

        $bytes = file_put_contents($tempFile, $json, LOCK_EX);

        if ($bytes === false) {
            throw new RuntimeException("Failed to write temporary JSON file: {$tempFile}");
        }

        if (!rename($tempFile, $file)) {
            @unlink($tempFile);
            throw new RuntimeException("Failed to move temporary JSON file to target: {$file}");
        }
    }

    public static function exists(string $file): bool
    {
        return is_file($file) && is_readable($file);
    }

    private static function encodeWithFlags(mixed $data, int $flags): string
    {
        try {
            $json = json_encode($data, $flags);
        } catch (JsonException $e) {
            throw new RuntimeException("Failed to encode JSON: {$e->getMessage()}", 0, $e);
        }

        if (!is_string($json)) {
            throw new RuntimeException('Failed to encode JSON: unknown error');
        }

        return $json;
    }

    private static function stripComments(string $json): string
    {
        $result = '';
        $length = strlen($json);
        $inString = false;

        for ($i = 0; $i < $length; $i++) {
            $char = $json[$i];
            $next = $json[$i + 1] ?? '';

            if ($inString) {
                if ($char === '\\') {
                    $result .= $char;

                    if ($i + 1 < $length) {
                        $result .= $json[$i + 1];
                        $i++;
                    }

                    continue;
                }

                if ($char === '"') {
                    $inString = false;
                }

                $result .= $char;
                continue;
            }

            if ($char === '"') {
                $inString = true;
                $result .= $char;
                continue;
            }

            // Single-line comment: //
            if ($char === '/' && $next === '/') {
                $i += 2;

                while ($i < $length && $json[$i] !== "\n" && $json[$i] !== "\r") {
                    $i++;
                }

                if ($i < $length) {
                    $result .= $json[$i];
                }

                continue;
            }

            // Multi-line comment: /* ... */
            if ($char === '/' && $next === '*') {
                $i += 2;

                while (
                    $i < $length - 1 &&
                    !($json[$i] === '*' && ($json[$i + 1] ?? '') === '/')
                ) {
                    $i++;
                }

                $i++;
                continue;
            }

            $result .= $char;
        }

        return $result;
    }
}
