<?php

/**
 * KamiCore
 *
 * SPDX-License-Identifier: Apache-2.0
 *
 * @see https://kamicore.org
 */

/**
 * Common interface for all cache drivers
 */

interface CacheDriverInterface
{
    public function connect(array $config): bool;
    public function disconnect(): void;
    public function set(string $key, $value, int $ttl = 0): bool;
    public function get(string $key);
    public function del(string $key): bool;
    public function exists(string $key): bool;
    public function flush(): void;
    public function info(): array;
}
