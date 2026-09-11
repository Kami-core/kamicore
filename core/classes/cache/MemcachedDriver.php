<?php

/**
 * KamiCore
 *
 * SPDX-License-Identifier: Apache-2.0
 *
 * @see https://kamicore.org
 */

/**
 * Memcached cache driver
 */

class MemcachedDriver implements CacheDriverInterface
{
    private ?Memcached $mem = null;

    public function connect(array $config): bool
    {
        $this->mem = new Memcached();
        $this->mem->addServer($config['host'] ?? '127.0.0.1', $config['port'] ?? 11211);
        return true;
    }

    public function disconnect(): void
    {
        $this->mem?->quit();
    }

    public function set(string $key, $value, int $ttl = 0): bool
    {
        $ttl = $ttl ?: (defined('CACHE_DEFAULT_TTL') ? CACHE_DEFAULT_TTL : 3600);
        return $this->mem->set($key, $value, $ttl);
    }

    public function get(string $key, ?string $type = null)
    {
        return $this->mem->get($key);
    }

    public function del(string $key, ?string $type = null): bool
    {
        return $this->mem->delete($key);
    }

    public function exists(string $key, ?string $type = null): bool
    {
        $this->mem->get($key);
        return $this->mem->getResultCode() !== Memcached::RES_NOTFOUND;
    }

    public function flush(): void
    {
        $this->mem->flush();
    }

    public function info(): array
    {
        return $this->mem->getStats();
    }
}
