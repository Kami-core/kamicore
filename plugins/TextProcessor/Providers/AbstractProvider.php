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

abstract class AbstractProvider implements ProviderInterface
{
    /** @var array<string, mixed> */
    protected array $config;

    public function __construct(array $config = [])
    {
        $this->config = $config;
    }

    protected function apiKey(): string
    {
        $key = $this->config['api_key'] ?? null;

        if (!is_string($key) || $key === '') {
            throw new \RuntimeException('Provider API key is not configured.');
        }

        return $key;
    }

    protected function postJson(
        string $url,
        array $payload,
        array $headers = [],
        int $timeout = 60
    ): array {
        if (!extension_loaded('curl')) {
            throw new \RuntimeException('PHP cURL extension is required for external providers.');
        }

        $body = \Core\Utils\JsonTool::encode($payload, false);

        $curl = curl_init($url);
        if ($curl === false) {
            throw new \RuntimeException('Unable to initialize cURL.');
        }

        $headers = [
            'Accept: application/json',
            'Content-Type: application/json',
            'User-Agent: Kami-TextProcessor/1.0',
            ...$headers,
        ];

        curl_setopt_array($curl, [
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_POSTFIELDS => $body,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_TIMEOUT => $timeout,
        ]);

        try {
            $responseBody = curl_exec($curl);
            $status = (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE);

            if ($responseBody === false) {
                throw new \RuntimeException(
                    'Provider request failed: ' . curl_error($curl)
                );
            }
        } finally {
            curl_close($curl);
        }

        try {
            $response = json_decode(
                (string) $responseBody,
                true,
                512,
                JSON_THROW_ON_ERROR
            );
        } catch (\JsonException $e) {
            throw new \RuntimeException(
                "Provider returned invalid JSON (HTTP {$status}).",
                0,
                $e
            );
        }

        if (!is_array($response)) {
            throw new \RuntimeException(
                "Provider returned an invalid response (HTTP {$status})."
            );
        }

        if ($status < 200 || $status >= 300) {
            $message = $response['error']['message']
                ?? $response['message']
                ?? $response['detail']
                ?? 'Unknown provider error.';

            if (!is_string($message)) {
                $message = 'Unknown provider error.';
            }

            throw new \RuntimeException(
                "Provider request failed (HTTP {$status}): {$message}"
            );
        }

        return $response;
    }
}
