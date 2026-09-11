<?php

/**
 * KamiCore
 *
 * SPDX-License-Identifier: Apache-2.0
 *
 * @see https://kamicore.org
 */

declare(strict_types=1);

namespace Plugins\TextProcessor\Providers;

if (!defined('IN_KAMI')) die();

interface ProviderInterface
{
    public function __construct(array $config = []);

    /**
     * @return list<string>
     */
    public static function operations(): array;

    public function process(
        array $items,
        string $operation,
        array $options = []
    ): array;
}
