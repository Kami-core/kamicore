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

final class Csrf
{
    public const FIELD = 'csrf_token';
    public const HEADER = 'X-Kami-CSRF';

    private const SESSION_KEY = 'csrf_token';

    public static function token(): string
    {
        $token = Session::get(self::SESSION_KEY);

        if (is_string($token) && $token !== '') {
            return $token;
        }

        $token = Session::setIfAbsent(
            self::SESSION_KEY,
            Crypto::randomHex()
        );

        if (!is_string($token) || $token === '') {
            throw new \RuntimeException('Failed to initialize CSRF token.');
        }

        return $token;
    }

    public static function validate(?string $token): bool
    {
        if (!is_string($token) || $token === '') {
            return false;
        }

        $storedToken = Session::get(self::SESSION_KEY);

        return is_string($storedToken)
            && $storedToken !== ''
            && hash_equals($storedToken, $token);
    }
}
