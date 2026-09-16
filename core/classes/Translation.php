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

/**
 * Centralized translation reader with language fallback support.
 *
 * Exact translations are cached independently. Resolved translations are
 * assembled per request so the same cached rows can be reused with different
 * fallback chains.
 */
final class Translation
{
    public const SYSTEM_ENTITY_UUID = '00000000-0000-0000-0000-000000000000';

    /** @var array<string, array<string, array<string, mixed>|null>> */
    private static array $exact = [];

    /**
     * Resolve a translation using:
     * requested language -> domain default language -> entity native language.
     */
    public static function get(
        string $uuid,
        ?string $lang = null,
        ?string $nativeLanguage = null
    ): ?array {
        $uuid = trim($uuid);
        if ($uuid === '') {
            return null;
        }

        $chain = self::languageChain($lang, $nativeLanguage);
        if ($chain === []) {
            return null;
        }

        self::loadPairs([$uuid => $chain]);

        return self::resolve($uuid, $chain);
    }

    /**
     * Read only the explicitly stored translation for one language.
     */
    public static function getExact(string $uuid, string $lang): ?array
    {
        $uuid = trim($uuid);
        $lang = trim($lang);

        if ($uuid === '' || $lang === '') {
            return null;
        }

        self::loadPairs([$uuid => [$lang]]);

        return self::$exact[$uuid][$lang] ?? null;
    }

    public static function getMany(
        array $uuids,
        ?string $lang = null,
        array $nativeLanguages = []
    ): array {
        $uuids = array_values(array_unique(array_filter(
            array_map(static fn($uuid): string => trim((string) $uuid), $uuids),
            static fn(string $uuid): bool => $uuid !== ''
        )));

        if ($uuids === []) {
            return [];
        }

        $pairs = [];
        foreach ($uuids as $uuid) {
            $native = isset($nativeLanguages[$uuid])
                ? trim((string) $nativeLanguages[$uuid])
                : null;
            $pairs[$uuid] = self::languageChain($lang, $native ?: null);
        }

        self::loadPairs($pairs);

        $result = [];
        foreach ($pairs as $uuid => $chain) {
            $result[$uuid] = self::resolve($uuid, $chain);
        }

        return $result;
    }

    public static function sortByTitle(
        array $items,
        string $titleKey = 'title',
        ?string $fallbackKey = 'system_name',
        ?string $lang = null
    ): array {
        usort($items, static function (array $a, array $b) use ($titleKey, $fallbackKey, $lang): int {
            $left = trim((string) ($a[$titleKey] ?? ($fallbackKey !== null ? ($a[$fallbackKey] ?? '') : '')));
            $right = trim((string) ($b[$titleKey] ?? ($fallbackKey !== null ? ($b[$fallbackKey] ?? '') : '')));

            $comparison = self::compareTitles($left, $right, $lang);
            if ($comparison !== 0 || $fallbackKey === null) {
                return $comparison;
            }

            return self::compareTitles(
                (string) ($a[$fallbackKey] ?? ''),
                (string) ($b[$fallbackKey] ?? ''),
                $lang
            );
        });

        return $items;
    }

    public static function compareTitles(string $left, string $right, ?string $lang = null): int
    {
        $language = trim((string) ($lang ?? (defined('LANG') ? LANG : self::domainDefaultLanguage() ?? 'en')));

        if (class_exists('\Collator')) {
            static $collators = [];
            $key = $language !== '' ? $language : 'en';
            $collator = $collators[$key] ??= new \Collator($key);
            $comparison = $collator->compare($left, $right);
            if ($comparison !== false) {
                return $comparison <=> 0;
            }
        }

        return strnatcasecmp($left, $right) <=> 0;
    }

    /**
     * Invalidate exact translation cache entries.
     *
     * Resolved translations are never persisted, so no derived cache keys need
     * to be invalidated.
     */
    public static function forget(string $uuid, ?string $lang = null): void
    {
        $uuid = trim($uuid);
        if ($uuid === '') {
            return;
        }

        if ($lang !== null && trim($lang) !== '') {
            $lang = trim($lang);
            unset(self::$exact[$uuid][$lang]);
            if ((self::$exact[$uuid] ?? []) === []) {
                unset(self::$exact[$uuid]);
            }
            \Cache::del(self::cacheKey($uuid, $lang));
            return;
        }

        $languages = array_keys(self::$exact[$uuid] ?? []);

        try {
            $languages = array_merge(
                $languages,
                array_map('strval', \DB::getArr('SELECT lang_code FROM languages'))
            );
        } catch (\Throwable) {
            // Cache invalidation must still work with the request-local entries
            // when the language registry is temporarily unavailable.
        }

        if (defined('LANG')) {
            $languages[] = (string) LANG;
        }

        $domainDefault = self::domainDefaultLanguage();
        if ($domainDefault !== null) {
            $languages[] = $domainDefault;
        }

        foreach (array_unique(array_filter($languages)) as $language) {
            \Cache::del(self::cacheKey($uuid, (string) $language));
        }

        unset(self::$exact[$uuid]);
    }

    private static function loadPairs(array $pairs): void
    {
        $missing = [];

        foreach ($pairs as $uuid => $languages) {
            foreach (array_unique($languages) as $language) {
                if ($language === '' || array_key_exists($language, self::$exact[$uuid] ?? [])) {
                    continue;
                }

                $cached = \Cache::get(self::cacheKey($uuid, $language));
                if (is_array($cached)) {
                    self::$exact[$uuid][$language] = $cached === [] ? null : $cached;
                    continue;
                }

                $missing[$uuid][$language] = true;
            }
        }

        if ($missing === []) {
            return;
        }

        $uuids = array_keys($missing);
        $languages = [];
        foreach ($missing as $entityLanguages) {
            $languages = array_merge($languages, array_keys($entityLanguages));
        }
        $languages = array_values(array_unique($languages));

        $found = [];
        $rows = \DB::query(
            'SELECT entity_uuid::text AS entity_uuid, lang_code, translated_data
             FROM translations
             WHERE entity_uuid=ANY($1::uuid[])
               AND lang_code=ANY($2::text[])',
            [\DB::prepareTextArray($uuids), \DB::prepareTextArray($languages)]
        );

        while ($row = \DB::fetchRow($rows)) {
            $uuid = (string) $row['entity_uuid'];
            $language = (string) $row['lang_code'];
            $data = \Core\Utils\JsonTool::decodeArray($row['translated_data'] ?? null);

            self::$exact[$uuid][$language] = $data === [] ? null : $data;
            $found[$uuid][$language] = true;
            \Cache::set(self::cacheKey($uuid, $language), $data);
        }

        foreach ($missing as $uuid => $entityLanguages) {
            foreach (array_keys($entityLanguages) as $language) {
                if (isset($found[$uuid][$language])) {
                    continue;
                }

                self::$exact[$uuid][$language] = null;
                \Cache::set(self::cacheKey($uuid, $language), []);
            }
        }
    }

    private static function resolve(string $uuid, array $chain): ?array
    {
        $result = null;

        foreach (array_reverse($chain) as $language) {
            $translation = self::$exact[$uuid][$language] ?? null;
            if ($translation === null) {
                continue;
            }

            $result = $result === null
                ? $translation
                : self::merge($result, $translation);
        }

        return $result;
    }

    /**
     * Merge translated JSON objects while replacing lists as complete values.
     */
    private static function merge(array $fallback, array $translation): array
    {
        foreach ($translation as $key => $value) {
            if (
                array_key_exists($key, $fallback)
                && is_array($fallback[$key])
                && is_array($value)
                && !array_is_list($fallback[$key])
                && !array_is_list($value)
            ) {
                $fallback[$key] = self::merge($fallback[$key], $value);
                continue;
            }

            $fallback[$key] = $value;
        }

        return $fallback;
    }

    /**
     * @return list<string>
     */
    private static function languageChain(?string $lang, ?string $nativeLanguage): array
    {
        $requested = $lang !== null ? trim($lang) : '';
        if ($requested === '' && defined('LANG')) {
            $requested = trim((string) LANG);
        }

        $languages = [
            $requested,
            self::domainDefaultLanguage(),
            $nativeLanguage !== null ? trim($nativeLanguage) : null,
        ];

        return array_values(array_unique(array_filter(
            $languages,
            static fn($language): bool => is_string($language) && $language !== ''
        )));
    }

    private static function domainDefaultLanguage(): ?string
    {
        if (!defined('DOMAIN_CONFIG') || !is_array(DOMAIN_CONFIG)) {
            return null;
        }

        $language = trim((string) (DOMAIN_CONFIG['default_language'] ?? ''));
        return $language !== '' ? $language : null;
    }

    private static function cacheKey(string $uuid, string $lang): string
    {
        // Keep the existing key format so current invalidation code remains compatible.
        return "globals:{$uuid}_{$lang}";
    }


}
