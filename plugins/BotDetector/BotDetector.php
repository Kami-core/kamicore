<?php

/**
 * KamiCore
 *
 * SPDX-License-Identifier: Apache-2.0
 *
 * @see https://kamicore.org
 */

declare(strict_types=1);

namespace Plugins\BotDetector;

if (!defined('IN_KAMI')) die();

final class BotDetector extends \Core\BasePlugin
{
    /** @var null|list<string> */
    private ?array $parsedSignatures = null;

    /**
     * Heuristically detect known automated clients by User-Agent.
     *
     * This method is intended for analytics, logging, caching, and similar
     * behavior optimizations. User-Agent values are client-controlled and
     * must not be used for authentication, authorization, or access control.
     */
    public function userIsBot(?string $userAgent = null): bool
    {
        $userAgent ??= \Core\Request::header('User-Agent') ?? '';
        $userAgent = strtolower(trim($userAgent));

        // Modern interactive browsers normally send a User-Agent. Treat an
        // empty value as an automated client for analytics-oriented use cases.
        if ($userAgent === '') {
            return true;
        }

        foreach ($this->signatures() as $signature) {
            if (str_contains($userAgent, $signature)) {
                return true;
            }
        }

        return false;
    }

    /** @return list<string> */
    private function signatures(): array
    {
        if ($this->parsedSignatures !== null) {
            return $this->parsedSignatures;
        }

        $raw = (string)($this->getSetting('signatures') ?? '');
        if ($raw === '') {
            return $this->parsedSignatures = [];
        }

        $signatures = [];
        foreach (preg_split('/\R/u', $raw) ?: [] as $line) {
            $line = strtolower(trim($line));
            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }

            $signatures[$line] = true;
        }

        return $this->parsedSignatures = array_keys($signatures);
    }
}
