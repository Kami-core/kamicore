<?php

/**
 * KamiCore
 *
 * SPDX-License-Identifier: Apache-2.0
 *
 * @see https://kamicore.org
 */

declare(strict_types=1);

namespace Plugins\TextProcessor;

use Core\SecretStore;
use Plugins\TextProcessor\Providers\DeepLProvider;
use Plugins\TextProcessor\Providers\OpenAIProvider;
use Plugins\TextProcessor\Providers\ProviderInterface;

if (!defined('IN_KAMI')) die();

final class TextProcessor extends \Core\BasePlugin
{
    /**
     * @var array<string, array{
     *     title:string,
     *     class:class-string<ProviderInterface>,
     *     secret:string
     * }>
     */
    private const PROVIDERS = [
        'deepl' => [
            'title' => 'DeepL',
            'class' => DeepLProvider::class,
            'secret' => 'deepl.api_key',
        ],
        'openai' => [
            'title' => 'OpenAI',
            'class' => OpenAIProvider::class,
            'secret' => 'openai.api_key',
        ],
    ];

    /** @var array<string, string> */
    private const DEFAULT_PROVIDERS = [
        'translate' => 'deepl',
        'generate' => 'openai',
        'rewrite' => 'openai',
        'summarize' => 'openai',
        'custom' => 'openai',
    ];

    /**
     * Return registered providers, optionally filtered by operation.
     *
     * @return array{
     *     default:string|array<string,string>|null,
     *     providers:array<string, array{
     *         title:string,
     *         operations:list<string>,
     *         configured:bool
     *     }>
     * }
     */
    public function getProviders(?string $operation = null): array
    {
        $operation = $operation === null ? null : $this->normalizeOperation($operation);
        $providers = [];
        $domainId = defined('DOMAIN_ID') ? (int) DOMAIN_ID : null;

        foreach (self::PROVIDERS as $key => $config) {
            $operations = $config['class']::operations();
            if ($operation !== null && !in_array($operation, $operations, true)) {
                continue;
            }

            $providers[$key] = [
                'title' => $config['title'],
                'operations' => $operations,
                'configured' => SecretStore::has(
                    $this->name,
                    $config['secret'],
                    $domainId
                ),
            ];
        }

        return [
            'default' => $operation === null
                ? self::DEFAULT_PROVIDERS
                : (self::DEFAULT_PROVIDERS[$operation] ?? null),
            'providers' => $providers,
        ];
    }

    public function process(
        array $items,
        string $operation,
        array $options = []
    ): array {
        if ($items === []) {
            return [];
        }

        $operation = $this->normalizeOperation($operation);
        $providerKey = $options['provider']
            ?? self::DEFAULT_PROVIDERS[$operation]
            ?? null;

        if (!is_string($providerKey) || $providerKey === '') {
            throw new \RuntimeException(
                "No provider selected and no default provider configured for operation: {$operation}."
            );
        }

        $providerConfig = self::PROVIDERS[$providerKey] ?? null;
        if ($providerConfig === null) {
            throw new \InvalidArgumentException(
                "Unknown text processing provider: {$providerKey}."
            );
        }

        $providerClass = $providerConfig['class'];
        if (!in_array($operation, $providerClass::operations(), true)) {
            throw new \InvalidArgumentException(
                "Provider {$providerKey} does not support operation: {$operation}."
            );
        }

        $domainId = defined('DOMAIN_ID') ? (int) DOMAIN_ID : null;
        $apiKey = SecretStore::get(
            $this->name,
            $providerConfig['secret'],
            $domainId
        );

        if ($apiKey === null || $apiKey === '') {
            throw new \RuntimeException(
                "Provider {$providerKey} is not configured."
            );
        }

        $providerOptions = $options;
        unset($providerOptions['provider']);

        $provider = new $providerClass([
            'api_key' => $apiKey,
            'model' => $providerKey === 'openai'
                ? ($this->settings['openai_model'] ?? null)
                : null,
            'endpoint' => $this->settings[$providerKey . '_endpoint'] ?? null,
        ]);

        if (!$provider instanceof ProviderInterface) {
            throw new \RuntimeException(
                "Invalid text processing provider class: {$providerClass}."
            );
        }

        $normalizedItems = $this->normalizeItems($items);
        $processed = $provider->process(
            $normalizedItems,
            $operation,
            $providerOptions
        );

        $this->validateResult($normalizedItems, $processed, $providerKey);

        $result = [];
        foreach ($normalizedItems as $key => $_item) {
            $result[$key] = $processed[$key];
        }

        return $result;
    }

    private function normalizeOperation(string $operation): string
    {
        $operation = strtolower(trim($operation));
        if ($operation === '') {
            throw new \InvalidArgumentException('Text processing operation cannot be empty.');
        }

        return $operation;
    }

    private function normalizeItems(array $items): array
    {
        $normalized = [];

        foreach ($items as $key => $item) {
            if (!is_string($key) || $key === '') {
                throw new \InvalidArgumentException(
                    'Text processing item keys must be non-empty strings.'
                );
            }

            if (is_string($item)) {
                $normalized[$key] = [
                    'text' => $item,
                    'format' => 'text',
                ];
                continue;
            }

            if (!is_array($item) || !is_string($item['text'] ?? null)) {
                throw new \InvalidArgumentException(
                    "Invalid text processing item: {$key}."
                );
            }

            $format = $item['format'] ?? 'text';
            if (!is_string($format) || trim($format) === '') {
                throw new \InvalidArgumentException(
                    "Invalid text processing format for item: {$key}."
                );
            }

            $normalized[$key] = [
                'text' => $item['text'],
                'format' => strtolower(trim($format)),
            ];
        }

        return $normalized;
    }

    private function validateResult(
        array $items,
        array $processed,
        string $providerKey
    ): void {
        $expectedKeys = array_keys($items);
        $returnedKeys = array_keys($processed);
        sort($expectedKeys);
        sort($returnedKeys);

        if ($returnedKeys !== $expectedKeys) {
            throw new \RuntimeException(
                "Provider {$providerKey} returned a different set of item keys."
            );
        }

        foreach ($processed as $key => $text) {
            if (!is_string($text)) {
                throw new \RuntimeException(
                    "Provider {$providerKey} returned invalid text for item: {$key}."
                );
            }
        }
    }
}
