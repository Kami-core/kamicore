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

final class Crypto
{
    public static function randomHex(int $bytes = 32): string
    {
        return bin2hex(random_bytes($bytes));
    }

    public static function randomBase64Url(int $bytes = 32): string
    {
        return rtrim(
            strtr(base64_encode(random_bytes($bytes)), '+/', '-_'),
            '='
        );
    }

    public static function tokenHash(string $token): string
    {
        return hash('sha256', $token);
    }
}
