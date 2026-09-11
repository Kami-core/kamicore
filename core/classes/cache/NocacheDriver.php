<?php

/**
 * KamiCore
 *
 * SPDX-License-Identifier: Apache-2.0
 *
 * @see https://kamicore.org
 */

/**
 * No cache dummy driver
 */

class NocacheDriver implements CacheDriverInterface
{

    public function connect(array $config): bool
    {
        return true;
    }

    public function disconnect(): void
    {
    }

    public function set(string $key, $value, int $ttl = 0): bool
    {
        return true;
    }

    public function get(string $key)
    {
        return null;
    }

    public function del(string $key): bool
    {
        return true;
    }

    public function exists(string $key): bool
    {
        return false;
    }

    public function flush(): void
    {
    }

    public function info(): array
    {
        return null;
    }
}

