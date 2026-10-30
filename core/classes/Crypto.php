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
    /**
     * Cached for the lifetime of the worker process. Trades the previous
     * per-call sodium_memzero() wipe for avoiding a disk read + decode on
     * every sign() call — see PR description for the rationale.
     */
    private static ?string $installationKey = null;

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

    /**
     * Sign application data with the installation master key.
     *
     * The purpose string provides domain separation between independent token types.
     */
    public static function sign(string $purpose, string $value): string
    {
        $purpose = trim($purpose);
        if ($purpose === '') {
            throw new \InvalidArgumentException('Signature purpose cannot be empty.');
        }

        return hash_hmac('sha256', $purpose . "\0" . $value, self::loadInstallationKey());
    }

    public static function verifySignature(
        string $purpose,
        string $value,
        string $signature
    ): bool {
        if (!preg_match('/^[a-f0-9]{64}$/D', $signature)) {
            return false;
        }

        return hash_equals(self::sign($purpose, $value), $signature);
    }

    private static function loadInstallationKey(): string
    {
        if (self::$installationKey !== null) {
            return self::$installationKey;
        }

        if (!defined('KAMI_SECRET_KEY_FILE')) {
            throw new \RuntimeException('KAMI_SECRET_KEY_FILE is not configured.');
        }

        $path = (string)constant('KAMI_SECRET_KEY_FILE');
        if ($path === '' || !is_file($path) || !is_readable($path)) {
            throw new \RuntimeException('Kami master key file is not readable.');
        }

        $raw = file_get_contents($path);
        if ($raw === false) {
            throw new \RuntimeException('Unable to read Kami master key file.');
        }

        $decoded = base64_decode(trim($raw), true);
        if ($decoded !== false && strlen($decoded) === 32) {
            return self::$installationKey = $decoded;
        }

        if (strlen($raw) === 32) {
            return self::$installationKey = $raw;
        }

        throw new \RuntimeException('Kami master key has an invalid length or format.');
    }
}
