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

    private const SIGNATURE_PURPOSE = 'csrf:v1';
    private const TOKEN_PATTERN = '/^([a-f0-9]{64})\.([a-f0-9]{64})$/D';

    private static ?string $token = null;

    /**
     * Return a valid stateless CSRF token and ensure the matching cookie exists.
     */
    public static function token(): string
    {
        if (self::$token !== null) {
            return self::$token;
        }

        $cookieToken = (string)(Request::cookie()[self::FIELD] ?? '');
        if (self::verifyToken($cookieToken)) {
            return self::$token = $cookieToken;
        }

        $nonce = Crypto::randomHex();
        $signature = Crypto::sign(
            self::SIGNATURE_PURPOSE,
            DOMAIN_ID . "\0" . $nonce
        );

        self::$token = $nonce . '.' . $signature;
        Response::addCookie(self::FIELD, self::$token);

        return self::$token;
    }

    /**
     * Validate the submitted token against the first-party cookie and its signature.
     */
    public static function validate(?string $token): bool
    {
        $token = is_string($token) ? trim($token) : '';
        if ($token === '') {
            return false;
        }

        $cookieToken = (string)(Request::cookie()[self::FIELD] ?? '');
        if ($cookieToken === '' || !hash_equals($cookieToken, $token)) {
            return false;
        }

        return self::verifyToken($token);
    }

    private static function verifyToken(string $token): bool
    {
        if (!preg_match(self::TOKEN_PATTERN, $token, $matches)) {
            return false;
        }

        return Crypto::verifySignature(
            self::SIGNATURE_PURPOSE,
            DOMAIN_ID . "\0" . $matches[1],
            $matches[2]
        );
    }
}
