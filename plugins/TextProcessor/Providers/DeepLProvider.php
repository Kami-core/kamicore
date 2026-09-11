<?php

/**
 * KamiCore
 *
 * SPDX-License-Identifier: Apache-2.0
 *
 * @see https://kamicore.org
 */

declare(strict_types=1);

namespace Plugins\TextProcessor\Providers;

if (!defined('IN_KAMI')) die();

final class DeepLProvider extends AbstractProvider
{
    private const FREE_ENDPOINT = 'https://api-free.deepl.com/v2/translate';
    private const PRO_ENDPOINT = 'https://api.deepl.com/v2/translate';
    private const MAX_REQUEST_BYTES = 120000;

    public static function operations(): array
    {
        return ['translate'];
    }

    public function process(
        array $items,
        string $operation,
        array $options = []
    ): array {
        if ($operation !== 'translate') {
            throw new \InvalidArgumentException(
                "DeepL does not support operation: {$operation}."
            );
        }

        $sourceLanguage = $this->language($options['source_language'] ?? null, 'source');
        $targetLanguage = $this->language($options['target_language'] ?? null, 'target');
        $context = $options['context'] ?? null;

        if ($context !== null && !is_string($context)) {
            throw new \InvalidArgumentException('Translation context must be a string.');
        }

        $groups = [];
        foreach ($items as $key => $item) {
            $format = $item['format'];
            if (!in_array($format, ['text', 'html'], true)) {
                throw new \InvalidArgumentException(
                    "DeepL does not support format '{$format}' for item: {$key}."
                );
            }

            $groups[$format][$key] = $item['text'];
        }

        $result = [];
        foreach ($groups as $format => $texts) {
            foreach ($this->chunks(
                $texts,
                $sourceLanguage,
                $targetLanguage,
                $format,
                $context
            ) as $chunk) {
                $result += $this->translateChunk(
                    $chunk,
                    $sourceLanguage,
                    $targetLanguage,
                    $format,
                    $context,
                    $options
                );
            }
        }

        return $result;
    }

    private function chunks(
        array $texts,
        string $sourceLanguage,
        string $targetLanguage,
        string $format,
        ?string $context
    ): array {
        $chunks = [];
        $chunk = [];

        foreach ($texts as $key => $text) {
            $candidate = $chunk + [$key => $text];
            $payload = $this->payload(
                array_values($candidate),
                $sourceLanguage,
                $targetLanguage,
                $format,
                $context,
                []
            );
            $size = strlen(json_encode(
                $payload,
                JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
            ));

            if ($size > self::MAX_REQUEST_BYTES && $chunk !== []) {
                $chunks[] = $chunk;
                $chunk = [];
                $candidate = [$key => $text];
                $payload = $this->payload(
                    [$text],
                    $sourceLanguage,
                    $targetLanguage,
                    $format,
                    $context,
                    []
                );
                $size = strlen(json_encode(
                    $payload,
                    JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
                ));
            }

            if ($size > self::MAX_REQUEST_BYTES) {
                throw new \LengthException(
                    "DeepL request item is too large: {$key}."
                );
            }

            $chunk = $candidate;
        }

        if ($chunk !== []) {
            $chunks[] = $chunk;
        }

        return $chunks;
    }

    private function translateChunk(
        array $texts,
        string $sourceLanguage,
        string $targetLanguage,
        string $format,
        ?string $context,
        array $options
    ): array {
        $payload = $this->payload(
            array_values($texts),
            $sourceLanguage,
            $targetLanguage,
            $format,
            $context,
            $options
        );

        $endpoint = $this->endpoint();

        $response = $this->postJson(
            $endpoint,
            $payload,
            ['Authorization: DeepL-Auth-Key ' . $this->apiKey()]
        );

        $translations = $response['translations'] ?? null;
        if (!is_array($translations) || count($translations) !== count($texts)) {
            throw new \RuntimeException('DeepL returned an unexpected number of translations.');
        }

        $result = [];
        foreach (array_keys($texts) as $index => $key) {
            $text = $translations[$index]['text'] ?? null;
            if (!is_string($text)) {
                throw new \RuntimeException(
                    "DeepL returned invalid text for item: {$key}."
                );
            }
            $result[$key] = $text;
        }

        return $result;
    }

    private function payload(
        array $texts,
        string $sourceLanguage,
        string $targetLanguage,
        string $format,
        ?string $context,
        array $options
    ): array {
        $payload = [
            'text' => $texts,
            'source_lang' => $sourceLanguage,
            'target_lang' => $targetLanguage,
        ];

        if ($context !== null && $context !== '') {
            $payload['context'] = $context;
        }

        if ($format === 'html') {
            $payload['tag_handling'] = 'html';
            $payload['tag_handling_version'] = 'v2';
        }

        foreach (['formality', 'model_type', 'preserve_formatting'] as $name) {
            if (array_key_exists($name, $options)) {
                $payload[$name] = $options[$name];
            }
        }

        return $payload;
    }

    private function endpoint(): string
    {
        $configured = $this->config['endpoint'] ?? null;
        if ($configured !== null) {
            if (!is_string($configured) || trim($configured) === '') {
                throw new \RuntimeException('DeepL endpoint is invalid.');
            }

            return rtrim(trim($configured), '/');
        }

        return str_ends_with($this->apiKey(), ':fx')
            ? self::FREE_ENDPOINT
            : self::PRO_ENDPOINT;
    }

    private function language(mixed $language, string $kind): string
    {
        if (!is_string($language) || trim($language) === '') {
            throw new \InvalidArgumentException(
                ucfirst($kind) . ' language is required for translation.'
            );
        }

        return strtoupper(str_replace('_', '-', trim($language)));
    }
}
