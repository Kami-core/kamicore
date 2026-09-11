<?php

/**
 * KamiCore
 *
 * SPDX-License-Identifier: Apache-2.0
 *
 * @see https://kamicore.org
 */

declare(strict_types=1);

namespace Plugins\Formatter;

if (!defined('IN_KAMI')) die();

final class Formatter extends \Core\BasePlugin
{
    public function date(string|\DateTimeInterface|null $value): string
    {
        if ($value === null || $value === '') {
            return '';
        }

        return $this->dateValue($value)->format(
            (string)($this->getLocalizedSetting('date_format') ?? 'd.m.Y')
        );
    }

    public function dateTime(string|\DateTimeInterface|null $value): string
    {
        if ($value === null || $value === '') {
            return '';
        }

        return $this->dateValue($value)->format(
            (string)($this->getLocalizedSetting('datetime_format') ?? 'd.m.Y, H:i')
        );
    }

    public function number(int|float|string $value, int $decimals = 0): string
    {
        if (!is_numeric($value)) {
            throw new \InvalidArgumentException('Value must be numeric.');
        }

        return number_format(
            (float)$value,
            max(0, $decimals),
            (string)($this->getLocalizedSetting('decimal_separator') ?? '.'),
            (string)($this->getLocalizedSetting('thousands_separator') ?? ',')
        );
    }

    public function currency(
        int|float|string $value,
        string $symbol,
        int $decimals = 2
    ): string {
        $format = (string)($this->getLocalizedSetting('currency_format') ?? '{{symbol}}{{value}}');

        return strtr($format, [
            '{{value}}' => $this->number($value, $decimals),
            '{{symbol}}' => $symbol,
        ]);
    }

    private function dateValue(string|\DateTimeInterface $value): \DateTimeImmutable
    {
        if ($value instanceof \DateTimeInterface) {
            return \DateTimeImmutable::createFromInterface($value);
        }

        try {
            return new \DateTimeImmutable($value);
        } catch (\Exception $e) {
            throw new \InvalidArgumentException('Invalid date or datetime value.', 0, $e);
        }
    }
}
