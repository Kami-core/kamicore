<?php

/**
 * KamiCore
 *
 * SPDX-License-Identifier: Apache-2.0
 *
 * @see https://kamicore.org
 */

declare(strict_types=1);

namespace Plugins\Notifications;

use Core\ClientContext;
use Core\User;

if (!defined('IN_KAMI')) die();

final class Notifications extends \Core\BasePlugin
{
    private const STYLES = [
        'default',
        'success',
        'alert',
        'danger',
    ];

    private const CLEANUP_ONE_IN = 500;
    private const CLEANUP_BATCH = 1000;

    public function view(array $instanceParams = []): string
    {
        return $this->render('notifications', [
            'endpoint' => '/ajax/Notifications/get',
        ]);
    }

    public function store(
        ?int $userId,
        string $text,
        string $style = 'default'
    ): void {
        $contextId = ClientContext::id();
        $text = trim($text);
        $style = strtolower(trim($style));

        if ($userId !== null && $userId < 1) {
            throw new \InvalidArgumentException('Notification user ID must be positive or null.');
        }
        if ($text === '') {
            throw new \InvalidArgumentException('Notification text cannot be empty.');
        }
        if (!in_array($style, self::STYLES, true)) {
            throw new \InvalidArgumentException("Unsupported notification style: {$style}.");
        }

        $expire = max(0, (int)($this->settings['expire'] ?? 0));

        $result = \DB::query(
            "INSERT INTO notification_messages (
                context_id,
                user_id,
                text,
                style,
                expires_at
             ) VALUES (
                $1,
                $2,
                $3,
                $4,
                CASE
                    WHEN $5::integer > 0
                    THEN CURRENT_TIMESTAMP + ($5::integer * INTERVAL '1 second')
                    ELSE NULL
                END
             )",
            [$contextId, $userId, $text, $style, $expire]
        );

        if ($result === false) {
            throw new \RuntimeException('Failed to store notification.');
        }
    }

    public function get(): string
    {
        \Core\Response::addHeader('Content-Type: application/json; charset=utf-8');
        \Core\Response::addHeader('Cache-Control: no-store, no-cache, must-revalidate');

        $contextId = ClientContext::id();
        $userId = User::getId();

        if ($userId < 1) {
            $where = 'context_id=$1 AND user_id IS NULL';
            $params = [$contextId];
        } else {
            $where = 'context_id=$1 AND (user_id IS NULL OR user_id=$2)';
            $params = [$contextId, $userId];
        }

        $result = \DB::query(
            "WITH consumed AS (
                DELETE FROM notification_messages
                WHERE {$where}
                RETURNING notification_id, text, style, created_at, expires_at
             )
             SELECT notification_id, text, style, created_at
             FROM consumed
             WHERE expires_at IS NULL OR expires_at > CURRENT_TIMESTAMP
             ORDER BY created_at, notification_id",
            $params
        );

        if ($result === false) {
            throw new \RuntimeException('Failed to consume notifications.');
        }

        $messages = [];
        while ($row = \DB::fetchRow($result)) {
            $messages[] = [
                'id' => (int)$row['notification_id'],
                'text' => (string)$row['text'],
                'style' => (string)$row['style'],
                'created_at' => (string)$row['created_at'],
            ];
        }

        $this->maybeCleanupExpired();

        return $this->jsonResponse($messages);
    }

    public function clearCurrentUser(int $userId): void
    {
        if ($userId < 1) {
            return;
        }

        $result = \DB::query(
            'DELETE FROM notification_messages
             WHERE context_id=$1
               AND user_id=$2',
            [ClientContext::id(), $userId]
        );

        if ($result === false) {
            throw new \RuntimeException('Failed to clear current-context notifications.');
        }
    }

    public function clearUser(int $userId): void
    {
        if ($userId < 1) {
            return;
        }

        $result = \DB::query(
            'DELETE FROM notification_messages WHERE user_id=$1',
            [$userId]
        );

        if ($result === false) {
            throw new \RuntimeException('Failed to clear user notifications.');
        }
    }

    private function maybeCleanupExpired(): void
    {
        if (mt_rand(1, self::CLEANUP_ONE_IN) !== 1) {
            return;
        }

        \DB::query(
            'DELETE FROM notification_messages target
             USING (
                 SELECT notification_id
                 FROM notification_messages
                 WHERE expires_at IS NOT NULL
                   AND expires_at <= CURRENT_TIMESTAMP
                 ORDER BY expires_at, notification_id
                 LIMIT ' . self::CLEANUP_BATCH . '
             ) expired
             WHERE target.notification_id=expired.notification_id'
        );
    }

    private function jsonResponse(array $messages): string
    {
        return json_encode(
            [
                'status' => 'ok',
                'messages' => $messages,
            ],
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
        );
    }
}
