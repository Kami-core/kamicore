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

final class ClientContext
{
    private const COOKIE_NAME = 'context_id';
    private const COOKIE_TTL = 34560000; // 400 days.
    private const ID_PATTERN = '/^[a-f0-9]{64}$/D';

    private static ?string $contextId = null;
    private static bool $initialized = false;

    public static function init(): void
    {
        if (self::$initialized) {
            return;
        }

        $contextId = strtolower(trim((string)(Request::cookie()[self::COOKIE_NAME] ?? '')));
        if (!preg_match(self::ID_PATTERN, $contextId)) {
            $contextId = Crypto::randomHex();
            Response::addCookie(
                self::COOKIE_NAME,
                $contextId,
                TIME_NOW + self::COOKIE_TTL,
                true,
                'Lax'
            );
        }

        self::$contextId = $contextId;
        self::$initialized = true;
    }

    public static function id(): string
    {
        self::init();
        return self::$contextId ?? throw new \RuntimeException(
            'Client context is not initialized.'
        );
    }
}
