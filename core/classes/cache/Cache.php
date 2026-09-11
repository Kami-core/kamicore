<?php

/**
 * KamiCore
 *
 * SPDX-License-Identifier: Apache-2.0
 *
 * @see https://kamicore.org
 */

/**
 * Unified cache facade for Redis / KeyDB / Memcached
 */

class Cache
{
    /** @var CacheDriverInterface|null */
    private static ?CacheDriverInterface $driver = null;

    /** @var array */
    private static array $config = [
        'driver' => 'redis', // redis | keydb | memcached
        'host' => '127.0.0.1',
        'port' => 6379,
        'timeout' => 1.5,
        'auth' => null,
        'db' => 0,
        'use_igbinary' => true,
    ];

    public static function configure(array $options): void
    {
        self::$config = array_merge(self::$config, $options);
    }

    public static function connect(): bool
    {
        $driverName = strtolower(self::$config['driver'] ?? 'redis');

        // Load only the selected cache driver.
        require_once ROOT_PATH . 'core/classes/cache/CacheDriverInterface.php';

		if (!USE_CACHE) {
			require_once ROOT_PATH . 'core/classes/cache/NocacheDriver.php';
            self::$driver = new NocacheDriver();
            return true;
        }

        switch ($driverName) {
            case 'memcached':
                require_once ROOT_PATH . 'core/classes/cache/MemcachedDriver.php';
                $driver = new MemcachedDriver();
                break;

            case 'keydb':
                require_once ROOT_PATH . 'core/classes/cache/RedisDriver.php';
                $driver = new RedisDriver();
                break;

            default:
                require_once ROOT_PATH . 'core/classes/cache/RedisDriver.php';
                $driver = new RedisDriver();
                break;
        }

        if ($driver->connect(self::$config)) {
            self::$driver = $driver;
            return true;
        }

        return false;
    }

    public static function disconnect(): void
    {
        if (self::$driver) {
            self::$driver->disconnect();
            self::$driver = null;
        }
    }

    public static function reconnect(): bool
    {
        self::disconnect();
        return self::connect();
    }

    public static function __callStatic(string $method, array $args)
    {
        if (!self::$driver && !self::connect()) {
            throw new RuntimeException('Cache connection failed.');
        }

        if (!method_exists(self::$driver, $method)) {
            throw new BadMethodCallException("Cache driver method not found: $method");
        }

        return self::$driver->$method(...$args);
    }

    public static function key(
		string|int|null $domain = null,
		string|int|null $language = null,
		string|int|null $page = null,
		string|int|null $plugin = null,
		string|int|null $item = null,
		string|int|null $group = null,
		string|int|null $user = null,
		?string $name = null
	): string {
		if ($name === null || $name === '') {
			throw new InvalidArgumentException('Cache key name cannot be empty.');
		}

		$parts = [
			$domain,
			$language,
			$page,
			$plugin,
			$item,
			$group,
			$user,
		];

		$last = null;

		foreach ($parts as $index => $value) {
			if ($value !== null && $value !== '') {
				$last = $index;
			}
		}

		if ($last === null) {
			return $name;
		}

		$parts = array_slice($parts, 0, $last + 1);

		foreach ($parts as &$part) {
			if ($part === null || $part === '') {
				$part = 'any';
			}
		}

		unset($part);

		$parts[] = $name;

		return implode(':', $parts);
	}
}

