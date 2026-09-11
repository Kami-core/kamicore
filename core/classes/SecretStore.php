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

final class SecretStore
{
    private const NAMESPACE_MAX_LENGTH = 100;
    private const NAME_MAX_LENGTH = 150;

    public static function get(
        string $namespace,
        string $name,
        ?int $domainId = null
    ): ?string {
        self::validateScope($namespace, $name, $domainId);

        if ($domainId === null) {
            $row = \DB::getRow(
                'SELECT encrypted_value, domain_id
                 FROM secrets
                 WHERE namespace=$1
                   AND secret_name=$2
                   AND domain_id IS NULL
                 LIMIT 1',
                [$namespace, $name]
            );
        } else {
            $row = \DB::getRow(
                'SELECT encrypted_value, domain_id
                 FROM secrets
                 WHERE namespace=$1
                   AND secret_name=$2
                   AND (domain_id=$3 OR domain_id IS NULL)
                 ORDER BY (domain_id=$3) DESC NULLS LAST
                 LIMIT 1',
                [$namespace, $name, $domainId]
            );
        }

        if (!$row) {
            return null;
        }

        $storedDomainId = $row['domain_id'] === null
            ? null
            : (int) $row['domain_id'];

        return self::decrypt(
            self::decodeBytea((string) $row['encrypted_value']),
            self::additionalData($namespace, $name, $storedDomainId)
        );
    }

    public static function set(
        string $namespace,
        string $name,
        string $value,
        ?int $domainId = null
    ): void {
        self::validateScope($namespace, $name, $domainId);

        $encrypted = self::encrypt(
            $value,
            self::additionalData($namespace, $name, $domainId)
        );

        $result = \DB::query(
            'INSERT INTO secrets(
                namespace,
                secret_name,
                domain_id,
                encrypted_value
             ) VALUES($1, $2, $3, $4::bytea)
             ON CONFLICT ON CONSTRAINT secrets_scope_unique
             DO UPDATE SET
                encrypted_value=EXCLUDED.encrypted_value,
                updated_at=now()',
            [
                $namespace,
                $name,
                $domainId,
                self::encodeBytea($encrypted),
            ]
        );

        if ($result === false) {
            throw new \RuntimeException('Failed to save secret.');
        }
    }

    public static function has(
        string $namespace,
        string $name,
        ?int $domainId = null
    ): bool {
        self::validateScope($namespace, $name, $domainId);

        if ($domainId === null) {
            return (bool) \DB::getOne(
                'SELECT 1
                 FROM secrets
                 WHERE namespace=$1
                   AND secret_name=$2
                   AND domain_id IS NULL
                 LIMIT 1',
                [$namespace, $name]
            );
        }

        return (bool) \DB::getOne(
            'SELECT 1
             FROM secrets
             WHERE namespace=$1
               AND secret_name=$2
               AND (domain_id=$3 OR domain_id IS NULL)
             LIMIT 1',
            [$namespace, $name, $domainId]
        );
    }

    public static function delete(
        string $namespace,
        string $name,
        ?int $domainId = null
    ): void {
        self::validateScope($namespace, $name, $domainId);

        if ($domainId === null) {
            $result = \DB::query(
                'DELETE FROM secrets
                 WHERE namespace=$1
                   AND secret_name=$2
                   AND domain_id IS NULL',
                [$namespace, $name]
            );
        } else {
            $result = \DB::query(
                'DELETE FROM secrets
                 WHERE namespace=$1
                   AND secret_name=$2
                   AND domain_id=$3',
                [$namespace, $name, $domainId]
            );
        }

        if ($result === false) {
            throw new \RuntimeException('Failed to delete secret.');
        }
    }

    private static function encrypt(string $value, string $additionalData): string
    {
        $key = self::loadMasterKey();
        $nonce = random_bytes(
            SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_NPUBBYTES
        );

        try {
            $ciphertext = sodium_crypto_aead_xchacha20poly1305_ietf_encrypt(
                $value,
                $additionalData,
                $nonce,
                $key
            );
        } finally {
            sodium_memzero($key);
        }

        return $nonce . $ciphertext;
    }

    private static function decrypt(string $payload, string $additionalData): string
    {
        $nonceLength = SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_NPUBBYTES;
        $minimumLength = $nonceLength
            + SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_ABYTES;

        if (strlen($payload) < $minimumLength) {
            throw new \RuntimeException('Stored secret payload is invalid.');
        }

        $nonce = substr($payload, 0, $nonceLength);
        $ciphertext = substr($payload, $nonceLength);
        $key = self::loadMasterKey();

        try {
            $value = sodium_crypto_aead_xchacha20poly1305_ietf_decrypt(
                $ciphertext,
                $additionalData,
                $nonce,
                $key
            );
        } finally {
            sodium_memzero($key);
        }

        if ($value === false) {
            throw new \RuntimeException(
                'Unable to decrypt secret. The master key or secret metadata may be invalid.'
            );
        }

        return $value;
    }

    private static function loadMasterKey(): string
    {
        if (!defined('KAMI_SECRET_KEY_FILE')) {
            throw new \RuntimeException('KAMI_SECRET_KEY_FILE is not configured.');
        }

        $path = (string) constant('KAMI_SECRET_KEY_FILE');
        if ($path === '' || !is_file($path) || !is_readable($path)) {
            throw new \RuntimeException('Kami master key file is not readable.');
        }

        $raw = file_get_contents($path);
        if ($raw === false) {
            throw new \RuntimeException('Unable to read Kami master key file.');
        }

        $decoded = base64_decode(trim($raw), true);
        if (
            $decoded !== false
            && strlen($decoded) === SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_KEYBYTES
        ) {
            return $decoded;
        }

        if (strlen($raw) === SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_KEYBYTES) {
            return $raw;
        }

        throw new \RuntimeException('Kami master key has an invalid length or format.');
    }

    private static function additionalData(
        string $namespace,
        string $name,
        ?int $domainId
    ): string {
        return $namespace . "\0"
            . $name . "\0"
            . ($domainId === null ? 'global' : (string) $domainId);
    }

    private static function encodeBytea(string $value): string
    {
        return '\\x' . bin2hex($value);
    }

    private static function decodeBytea(string $value): string
    {
        if (str_starts_with($value, '\\x')) {
            $decoded = hex2bin(substr($value, 2));
            if ($decoded === false) {
                throw new \RuntimeException('Stored secret has invalid bytea encoding.');
            }

            return $decoded;
        }

        return pg_unescape_bytea($value);
    }

    private static function validateScope(
        string $namespace,
        string $name,
        ?int $domainId
    ): void {
        if (
            $namespace === ''
            || strlen($namespace) > self::NAMESPACE_MAX_LENGTH
        ) {
            throw new \InvalidArgumentException('Invalid secret namespace.');
        }

        if ($name === '' || strlen($name) > self::NAME_MAX_LENGTH) {
            throw new \InvalidArgumentException('Invalid secret name.');
        }

        if ($domainId !== null && $domainId < 1) {
            throw new \InvalidArgumentException('Invalid secret domain ID.');
        }
    }
}
