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

final class EndpointRegistry
{
    private const RESERVED_ENDPOINTS = ['ajax', 'api'];

    /** @var array<int, array<string, array{plugin: string, method: string}>> */
    private static array $domainMaps = [];

    /**
     * Resolve one plugin-owned root endpoint for a domain.
     *
     * @return array{plugin: string, method: string}|null
     */
    public static function resolve(string $endpoint, int $domainId): ?array
    {
        $endpoint = self::normalizeEndpoint($endpoint);
        if ($endpoint === '' || $domainId < 1) {
            return null;
        }

        $map = self::domainMap($domainId);
        return $map[$endpoint] ?? null;
    }

    public static function dispatch(array $route, Request $request): void
    {
        $pluginName = trim((string)($route['plugin'] ?? ''));
        $method = trim((string)($route['method'] ?? ''));

        if ($pluginName === '' || $method === '') {
            throw new \RuntimeException('Invalid plugin endpoint route.');
        }

        $plugins = new PluginRegistry();
        $plugin = $plugins->get($pluginName);
        if (!$plugin) {
            throw new \RuntimeException("Plugin endpoint owner is unavailable: {$pluginName}.");
        }

        self::assertRouteMethod($plugin, $pluginName, $method);

        $plugin->{$method}($request);
    }

    /**
     * Validate endpoint declarations from a plugin manifest.
     *
     * @return array<string, string>
     */
    public static function validateDeclarations(string $pluginName, mixed $declarations): array
    {
        if ($declarations === null) {
            return [];
        }
        if (!is_array($declarations)) {
            throw new \RuntimeException("Plugin {$pluginName} endpoints must be an object.");
        }

        $class = "\\Plugins\\{$pluginName}\\{$pluginName}";
        if (!class_exists($class)) {
            throw new \RuntimeException("Plugin class not found: {$pluginName}.");
        }

        $normalized = [];
        foreach ($declarations as $endpoint => $method) {
            if (!is_string($endpoint) || !is_string($method)) {
                throw new \RuntimeException(
                    "Plugin {$pluginName} endpoint declarations must map endpoint names to method names."
                );
            }

            $endpoint = self::normalizeEndpoint($endpoint);
            $method = trim($method);

            if (!preg_match('/^[a-z][a-z0-9_-]*$/', $endpoint)) {
                throw new \RuntimeException(
                    "Invalid plugin endpoint name '{$endpoint}' for {$pluginName}."
                );
            }
            if (in_array($endpoint, self::RESERVED_ENDPOINTS, true)) {
                throw new \RuntimeException(
                    "Plugin endpoint '{$endpoint}' is reserved by Kami."
                );
            }
            if (!preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $method)) {
                throw new \RuntimeException(
                    "Invalid plugin endpoint method '{$method}' for {$pluginName}."
                );
            }
            self::assertRouteMethod($class, $pluginName, $method);

            $normalized[$endpoint] = $method;
        }

        return $normalized;
    }

    public static function assertAvailableForPlugin(int $pluginId, array $domainIds): void
    {
        $domainIds = array_values(array_unique(array_filter(
            array_map('intval', $domainIds),
            static fn(int $id): bool => $id > 0
        )));
        if ($pluginId < 1 || $domainIds === []) {
            return;
        }

        $conflict = \DB::getRow(
            'SELECT pe.endpoint, p.system_name, pd.domain_id
             FROM plugin_endpoints own
             JOIN plugin_endpoints pe
               ON pe.endpoint=own.endpoint
              AND pe.plugin_id<>own.plugin_id
             JOIN plugins p
               ON p.plugin_id=pe.plugin_id
             JOIN plugin_domains pd
               ON pd.plugin_id=pe.plugin_id
             WHERE own.plugin_id=$1
               AND pd.domain_id=ANY($2::int[])
             ORDER BY pd.domain_id, pe.endpoint, p.system_name
             LIMIT 1',
            [$pluginId, '{' . implode(',', $domainIds) . '}']
        );

        if ($conflict) {
            throw new \RuntimeException(
                "Plugin endpoint '{$conflict['endpoint']}' is already owned by "
                . "{$conflict['system_name']} on domain #{$conflict['domain_id']}."
            );
        }
    }

    public static function invalidateDomains(array $domainIds): void
    {
        foreach (array_unique(array_map('intval', $domainIds)) as $domainId) {
            if ($domainId < 1) {
                continue;
            }
            unset(self::$domainMaps[$domainId]);
            \Cache::del(self::cacheKey($domainId));
        }
    }

    /**
     * @return array<string, array{plugin: string, method: string}>
     */
    private static function domainMap(int $domainId): array
    {
        if (isset(self::$domainMaps[$domainId])) {
            return self::$domainMaps[$domainId];
        }

        $cached = \Cache::get(self::cacheKey($domainId));
        if (is_array($cached)) {
            return self::$domainMaps[$domainId] = $cached;
        }

        $rows = \DB::query(
            'SELECT pe.endpoint, pe.route_method, p.system_name
             FROM plugin_endpoints pe
             JOIN plugins p
               ON p.plugin_id=pe.plugin_id
             JOIN plugin_domains pd
               ON pd.plugin_id=pe.plugin_id
              AND pd.domain_id=$1
             ORDER BY pe.endpoint, p.plugin_id',
            [$domainId]
        );

        $map = [];
        while ($row = \DB::fetchRow($rows)) {
            $endpoint = (string)$row['endpoint'];
            if (isset($map[$endpoint])) {
                throw new \RuntimeException(
                    "Multiple plugins own endpoint '{$endpoint}' on domain #{$domainId}."
                );
            }
            $map[$endpoint] = [
                'plugin' => (string)$row['system_name'],
                'method' => (string)$row['route_method'],
            ];
        }

        \Cache::set(self::cacheKey($domainId), $map);
        return self::$domainMaps[$domainId] = $map;
    }

    private static function assertRouteMethod(
        object|string $target,
        string $pluginName,
        string $method
    ): void {
        if (!method_exists($target, $method)) {
            throw new \RuntimeException(
                "Plugin endpoint method does not exist: {$pluginName}::{$method}()."
            );
        }

        $reflection = new \ReflectionMethod($target, $method);
        if (!$reflection->isPublic()) {
            throw new \RuntimeException(
                "Plugin endpoint method must be public: {$pluginName}::{$method}()."
            );
        }

        $parameters = $reflection->getParameters();
        if (count($parameters) !== 1 || $parameters[0]->isVariadic()) {
            throw new \RuntimeException(
                "Plugin endpoint method must accept exactly one Core\\Request argument: "
                . "{$pluginName}::{$method}()."
            );
        }

        $type = $parameters[0]->getType();
        if (
            !$type instanceof \ReflectionNamedType
            || $type->isBuiltin()
            || ltrim($type->getName(), '\\') !== Request::class
        ) {
            throw new \RuntimeException(
                "Plugin endpoint method argument must be typed as Core\\Request: "
                . "{$pluginName}::{$method}()."
            );
        }
    }

    private static function normalizeEndpoint(string $endpoint): string
    {
        return trim($endpoint, " /\t\n\r\0\x0B");
    }

    private static function cacheKey(int $domainId): string
    {
        return 'd_' . $domainId . ':plugin_endpoints';
    }
}
