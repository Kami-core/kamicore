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

final class PluginRegistry
{

    /** @return array<string, array{id:int, uuid:string, prefix:string}> */
    public static function forDomain(?int $domainId = null): array
    {
        $domainId ??= DOMAIN_ID;
        $plugins = \Cache::get('d_' . $domainId . ':plugins');

        if (!is_array($plugins)) {
            $plugins = [];
            $result = \DB::query(
                'SELECT p.plugin_id, p.uuid, p.system_name, p.plugin_prefix
                 FROM plugin_domains pd
                 JOIN plugins p USING(plugin_id)
                 WHERE pd.domain_id=$1',
                [$domainId]
            );

            while ($plugin = \DB::fetchRow($result)) {
                $plugins[(string) $plugin['system_name']] = [
                    'id' => (int) $plugin['plugin_id'],
                    'uuid' => (string) $plugin['uuid'],
                    'prefix' => (string) $plugin['plugin_prefix'],
                ];
            }

            \Cache::set('d_' . $domainId . ':plugins', $plugins);
        }

        return $plugins;
    }

    private array $instances = [];

    public function get(string $plugin_name): ?BasePlugin
    {
        if (isset($this->instances[$plugin_name])) {
            return $this->instances[$plugin_name];
        }

        $class = "\\Plugins\\{$plugin_name}\\{$plugin_name}";

        if (!class_exists($class)) {
            return null;
        }

        $instance = new $class($this);

        if (!$instance->active) {
            return null;
        }

        return $this->instances[$plugin_name] = $instance;
    }

    /** @return array<string, BasePlugin> */
    public function instances(): array
    {
        return $this->instances;
    }
}
