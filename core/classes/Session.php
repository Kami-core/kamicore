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

final class Session
{
    private static ?string $sessionId = null;
    private static ?string $uaHash = null;
    private static ?array $session = null;
    private static bool $initialized = false;

    public static function init(): void
    {
        if (self::$initialized) {
            return;
        }

        self::$sessionId = Request::cookie()['session_id'] ?? null;
        self::$uaHash = hash('sha256', self::normalizeUserAgent());

        if (!self::$sessionId) {
            self::create();
        } else {
            self::$session = \Cache::get(self::cacheKey(DOMAIN_ID, self::$sessionId));

            if (
                is_array(self::$session)
                && (
                    (string)(self::$session['ua_hash'] ?? '') !== self::$uaHash
                    || (
                        strtotime((string)(self::$session['updated_at'] ?? '')) < TIME_NOW - (int)GLOBAL_SETTINGS['session_timeout']
                        && empty(self::$session['is_persistent'])
                    )
                )
            ) {
                self::create();
            } elseif (!is_array(self::$session)) {
                self::$session = \DB::getRow(
                    "SELECT domain_id, session_id, user_id, ua_hash, is_persistent, created_at, updated_at, data
                     FROM sessions
                     WHERE domain_id=$1
                       AND session_id=$2
                       AND ua_hash=$3
                       AND (is_persistent OR updated_at>=NOW()-($4::integer * INTERVAL '1 second'))
                     LIMIT 1",
                    [DOMAIN_ID, self::$sessionId, self::$uaHash, (int)GLOBAL_SETTINGS['session_timeout']]
                ) ?: null;

                if (!self::$session) {
                    self::create();
                }
            }
        }

        self::normalize();
        self::$initialized = true;
        self::touch();
    }

    public static function create(): string
    {
        self::$uaHash ??= hash('sha256', self::normalizeUserAgent());

        $sessionId = self::$sessionId = self::generateId();
        $createdAt = date('Y-m-d H:i:s.uP');
        $sessionData = [
            'domain_id' => DOMAIN_ID,
            'session_id' => $sessionId,
            'user_id' => 0,
            'ua_hash' => self::$uaHash,
            'is_persistent' => false,
            'created_at' => $createdAt,
            'updated_at' => $createdAt,
            'data' => [],
        ];

        $created = \DB::insert('sessions', [
            'domain_id' => $sessionData['domain_id'],
            'session_id' => $sessionData['session_id'],
            'user_id' => $sessionData['user_id'],
            'ua_hash' => $sessionData['ua_hash'],
            'is_persistent' => $sessionData['is_persistent'],
            'created_at' => $sessionData['created_at'],
            'updated_at' => $sessionData['updated_at'],
        ]);
        if ($created !== 1) {
            throw new \RuntimeException('Failed to create session.');
        }

        Response::addCookie('session_id', $sessionId, 0, true);

        self::$session = $sessionData;
        self::$initialized = true;
        \Cache::set(self::cacheKey(DOMAIN_ID, $sessionId), self::$session);

        return $sessionId;
    }

    public static function id(): ?string
    {
        return self::$session['session_id'] ?? self::$sessionId;
    }

    public static function userId(): int
    {
        return (int)(self::$session['user_id'] ?? 0);
    }

    public static function authorize(int $userId, bool $remember): void
    {
        if ($userId < 1) {
            throw new \InvalidArgumentException('A positive user ID is required.');
        }

        self::rotate($userId, $remember);
    }

    public static function logout(): void
    {
        self::init();
        if (self::userId() === 0) {
            return;
        }

        self::rotate(0, false);
    }

    public static function rotate(int $userId, bool $persistent = false): string
    {
        self::init();

        if ($userId < 0 || !self::$sessionId || !is_array(self::$session)) {
            throw new \InvalidArgumentException('An active session and non-negative user ID are required.');
        }

        $oldSessionId = self::$sessionId;
        $newSessionId = self::generateId();
        $updatedAt = date('Y-m-d H:i:s.uP');

        $updatedSessionId = \DB::query(
            'UPDATE sessions
             SET session_id=$1,
                 user_id=$2,
                 is_persistent=$3,
                 created_at=$4,
                 updated_at=$4
             WHERE domain_id=$5
               AND session_id=$6
             RETURNING session_id',
            [
                $newSessionId,
                $userId,
                $persistent,
                $updatedAt,
                DOMAIN_ID,
                $oldSessionId,
            ]
        );

        if ($updatedSessionId !== $newSessionId) {
            throw new \RuntimeException('Failed to rotate session.');
        }

        \Cache::del(self::cacheKey(DOMAIN_ID, $oldSessionId));

        self::$sessionId = $newSessionId;
        self::$session['session_id'] = $newSessionId;
        self::$session['user_id'] = $userId;
        self::$session['is_persistent'] = $persistent;
        self::$session['created_at'] = $updatedAt;
        self::$session['updated_at'] = $updatedAt;

        \Cache::set(self::cacheKey(DOMAIN_ID, $newSessionId), self::$session);
        Response::addCookie('session_id', $newSessionId, 0, true);

        return $newSessionId;
    }

    /**
     * Invalidate every authenticated session for one user across all domains.
     *
     * @return list<array{domain_id:int, session_id:string}>
     */
    public static function invalidateUserSessions(int $userId): array
    {
        if ($userId < 1) {
            return [];
        }

        $result = \DB::query(
            'SELECT domain_id, session_id FROM sessions WHERE user_id=$1',
            [$userId]
        );
        if ($result === false) {
            throw new \RuntimeException('Failed to load user sessions for invalidation.');
        }

        $sessions = [];
        while ($row = \DB::fetchRow($result)) {
            $sessions[] = [
                'domain_id' => (int)$row['domain_id'],
                'session_id' => (string)$row['session_id'],
            ];
        }

        if ($sessions === []) {
            return [];
        }

        if (\DB::query(
            'UPDATE sessions SET user_id=0, is_persistent=false WHERE user_id=$1',
            [$userId]
        ) === false) {
            throw new \RuntimeException('Failed to invalidate user sessions.');
        }

        foreach ($sessions as $session) {
            \Cache::del(self::cacheKey($session['domain_id'], $session['session_id']));
        }

        return $sessions;
    }

    private static function touch(): void
    {
        if (!self::$sessionId || !is_array(self::$session)) {
            return;
        }

        $updatedAt = date('Y-m-d H:i:s.uP');
        self::$session['updated_at'] = $updatedAt;

        \Cache::set(self::cacheKey(DOMAIN_ID, self::$sessionId), self::$session);
        \DB::query(
            'UPDATE sessions SET updated_at=$1 WHERE domain_id=$2 AND session_id=$3',
            [$updatedAt, DOMAIN_ID, self::$sessionId]
        );
    }

    private static function normalize(): void
    {
        if (!is_array(self::$session)) {
            return;
        }

        self::$session['domain_id'] = (int)(self::$session['domain_id'] ?? DOMAIN_ID);
        self::$session['user_id'] = (int)(self::$session['user_id'] ?? 0);
        self::$session['is_persistent'] = (bool)(self::$session['is_persistent'] ?? false);

        self::$session['data'] = \Core\Utils\JsonTool::decodeArray(
            self::$session['data'] ?? null
        );
    }

    private static function generateId(): string
    {
        return Crypto::randomHex();
    }

    private static function normalizeUserAgent(?string $userAgent = null): string
    {
        $userAgent ??= $_SERVER['HTTP_USER_AGENT'] ?? '';
        $userAgent = strtolower(trim($userAgent));

        $patterns = [
            '/chrome\/\d+\.\d+\.\d+\.\d+/' => 'chrome',
            '/firefox\/\d+\.\d+/' => 'firefox',
            '/safari\/\d+\.\d+/' => 'safari',
            '/edg\/\d+\.\d+/' => 'edge',
        ];

        foreach ($patterns as $pattern => $replacement) {
            $userAgent = preg_replace($pattern, $replacement, $userAgent) ?? $userAgent;
        }

        return trim(preg_replace('/\s+/', ' ', $userAgent) ?? $userAgent);
    }

    private static function cacheKey(int $domainId, string $sessionId): string
    {
        return 'd_' . $domainId . ':sessions:' . $sessionId;
    }
}
