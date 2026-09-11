<?php

/**
 * KamiCore
 *
 * SPDX-License-Identifier: Apache-2.0
 *
 * @see https://kamicore.org
 */

/**
 * Redis / KeyDB cache driver (unified serialization)
 * Simplified version: all values are serialized (igbinary if available)
 */

class RedisDriver implements CacheDriverInterface
{
    private ?Redis $redis = null;
    private array $config = [];

    /**
     * Connect to Redis or KeyDB
     */
    public function connect(array $config): bool
    {
        $this->config = $config;
        $r = new Redis();

        try {
            $r->pconnect(
                $config['host'] ?? '127.0.0.1',
                $config['port'] ?? 6379,
                $config['timeout'] ?? 1.5,
                'cache_' . ($config['db'] ?? 0)
            );

            if (!empty($config['auth'])) {
                $r->auth($config['auth']);
            }

            if (!empty($config['db'])) {
                $r->select($config['db']);
            }

            $this->redis = $r;
            return true;

        } catch (RedisException) {
            $this->redis = null;
            return false;
        }
    }

    public function disconnect(): void
    {
        if ($this->redis) {
            $this->redis->close();
        }
        $this->redis = null;
    }

    /**
     * Encode value for storage
     */
    private function encode($value): string
	{
		$serialized = ($this->config['use_igbinary'] ?? false) && function_exists('igbinary_serialize')
			? igbinary_serialize($value)
			: serialize($value);

		if (defined('CACHE_ENCRYPT_KEY') && CACHE_ENCRYPT_KEY) {
			$iv = random_bytes(16);
			$encrypted = openssl_encrypt(
				$serialized,
				'aes-256-ctr',
				substr(hash('sha256', CACHE_ENCRYPT_KEY, true), 0, 32),
				OPENSSL_RAW_DATA,
				$iv
			);
			// prepend IV
			return base64_encode($iv . $encrypted);
		}

		return $serialized;
	}

    /**
     * Decode stored value
     */
    private function decode($value)
	{
		if ($value === false || $value === null) {
			return null;
		}

		// detect encryption (base64 + IV length check)
		if (defined('CACHE_ENCRYPT_KEY') && CACHE_ENCRYPT_KEY) {
			$raw = base64_decode($value, true);
			if ($raw && strlen($raw) > 16) {
				$iv = substr($raw, 0, 16);
				$ciphertext = substr($raw, 16);
				$decrypted = openssl_decrypt(
					$ciphertext,
					'aes-256-ctr',
					substr(hash('sha256', CACHE_ENCRYPT_KEY, true), 0, 32),
					OPENSSL_RAW_DATA,
					$iv
				);
				if ($decrypted !== false) {
					$value = $decrypted;
				}
			}
		}

		if (($this->config['use_igbinary'] ?? false) && function_exists('igbinary_unserialize')) {
			$decoded = @igbinary_unserialize($value);
			if ($decoded !== false) {
				return $decoded;
			}
		}

		$decoded = @unserialize($value);
		return ($decoded !== false || $value === serialize(false)) ? $decoded : $value;
	}

    /**
     * Store value with TTL
     */
    public function set(string $key, $value, int $ttl = 0): bool
    {
        if (!$this->redis) {
            return false;
        }

        $ttl = $ttl ?: (defined('CACHE_DEFAULT_TTL') ? CACHE_DEFAULT_TTL : 3600);
        $stored = $this->encode($value);

        return $this->redis->setex($key, $ttl, $stored);
    }

    /**
     * Retrieve cached value
     */
    public function get(string $key)
    {
        if (!$this->redis) {
            return null;
        }

        $raw = $this->redis->get($key);
        return $this->decode($raw);
    }

    /**
     * Delete key
     */
    public function del(string $key): bool
    {
        if (!$this->redis) {
            return false;
        }

        return (bool)$this->redis->del($key);
    }

    /**
     * Check if key exists
     */
    public function exists(string $key): bool
    {
        if (!$this->redis) {
            return false;
        }

        return (bool)$this->redis->exists($key);
    }

    /**
     * Flush current DB
     */
    public function flush(): void
    {
        if ($this->redis) {
            $this->redis->flushDB();
        }
    }

    /**
     * Get memory and general info
     */
    public function info(): array
    {
        if (!$this->redis) {
            return [];
        }
        return $this->redis->info();
    }
}
