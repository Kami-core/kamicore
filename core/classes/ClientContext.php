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
    /** @var array<int, string|null> */
    private static array $languages = [];

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

    public static function language(int $domainId): ?string
    {
        self::init();
        if ($domainId < 1) {
            throw new \InvalidArgumentException('Domain ID must be positive.');
        }

        if (array_key_exists($domainId, self::$languages)) {
            return self::$languages[$domainId];
        }

        $language = \DB::getOne(
            'SELECT lang_code FROM client_contexts WHERE context_id=$1 AND domain_id=$2 LIMIT 1',
            [self::id(), $domainId]
        );
        $language = is_string($language) ? trim($language) : '';

        return self::$languages[$domainId] = $language !== '' ? $language : null;
    }

    public static function setLanguage(int $domainId, string $language): void
    {
        self::init();
        $language = strtolower(trim($language));
        if ($domainId < 1) {
            throw new \InvalidArgumentException('Domain ID must be positive.');
        }
        if ($language === '') {
            throw new \InvalidArgumentException('Language code cannot be empty.');
        }
        if (self::language($domainId) === $language) {
            return;
        }

        $result = \DB::query(
            'INSERT INTO client_contexts (context_id, domain_id, lang_code)
             VALUES ($1, $2, $3)
             ON CONFLICT (context_id, domain_id) DO UPDATE
             SET lang_code=EXCLUDED.lang_code, updated_at=now()',
            [self::id(), $domainId, $language]
        );
        if ($result === false) {
            throw new \RuntimeException('Failed to persist client language preference.');
        }

        self::$languages[$domainId] = $language;
    }
}
