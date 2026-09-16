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

/**
 * Date/time conversion policy.
 *
 * Internal and database values are UTC. A null display/source timezone means
 * the configured display timezone; callers may pass a user timezone explicitly.
 */
final class Date
{
    private const UTC = 'UTC';
    private const STORAGE_FORMAT = 'Y-m-d H:i:s';

    public static function timezone(?string $timezone = null): \DateTimeZone
    {
        $timezone = $timezone === null
            ? trim((string) Settings::get('default_timezone', self::UTC))
            : trim($timezone);
        $timezone = $timezone !== '' ? $timezone : self::UTC;

        try {
            return new \DateTimeZone($timezone);
        } catch (\Exception $error) {
            throw new \InvalidArgumentException(
                "Invalid timezone: {$timezone}",
                0,
                $error
            );
        }
    }

    public static function value(
        string|\DateTimeInterface|null $value,
        string|\DateTimeZone $sourceTimezone = self::UTC
    ): ?\DateTimeImmutable {
        if ($value === null || $value === '') {
            return null;
        }

        if ($value instanceof \DateTimeInterface) {
            return \DateTimeImmutable::createFromInterface($value);
        }

        $timezone = $sourceTimezone instanceof \DateTimeZone
            ? $sourceTimezone
            : self::timezone($sourceTimezone);

        try {
            return new \DateTimeImmutable($value, $timezone);
        } catch (\Exception $error) {
            throw new \InvalidArgumentException(
                'Invalid date or datetime value.',
                0,
                $error
            );
        }
    }

    public static function fromFormat(
        string $format,
        string $value,
        ?string $sourceTimezone = null
    ): \DateTimeImmutable {
        $date = \DateTimeImmutable::createFromFormat(
            $format,
            $value,
            self::timezone($sourceTimezone)
        );
        $errors = \DateTimeImmutable::getLastErrors();

        if (
            $date === false
            || ($errors !== false && ($errors['warning_count'] > 0 || $errors['error_count'] > 0))
        ) {
            throw new \InvalidArgumentException('Invalid date or datetime value.');
        }

        return $date;
    }

    /**
     * Format an internal UTC value in the configured or explicitly supplied timezone.
     */
    public static function format(
        string|\DateTimeInterface|null $value,
        string $format,
        ?string $timezone = null
    ): string {
        $date = self::value($value);
        if ($date === null) {
            return '';
        }

        return $date
            ->setTimezone(self::timezone($timezone))
            ->format($format);
    }

    /**
     * Convert a local/display value to the canonical UTC storage representation.
     */
    public static function storage(
        string|\DateTimeInterface|null $value,
        ?string $sourceTimezone = null
    ): string {
        if ($value === null || $value === '') {
            return '';
        }

        $date = $value instanceof \DateTimeInterface
            ? \DateTimeImmutable::createFromInterface($value)
            : self::value($value, self::timezone($sourceTimezone));

        return $date
            ->setTimezone(self::timezone(self::UTC))
            ->format(self::STORAGE_FORMAT);
    }
}
