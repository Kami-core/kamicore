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

namespace Core;

final class PluginRegistry
{
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
