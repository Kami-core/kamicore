<?php

/**
 * KamiCore
 *
 * SPDX-License-Identifier: Apache-2.0
 *
 * @see https://kamicore.org
 */

if (!defined('IN_KAMI')) die();

/**
 * Normalize common scalar representations to a PHP boolean.
 */
function boolValue(mixed $value): bool
{
    if (is_bool($value)) {
        return $value;
    }

    if (is_int($value)) {
        return $value !== 0;
    }

    $value = strtolower(trim((string) $value));

    return !in_array($value, ['', '0', 'false', 'off', 'no'], true);
}

function createSlug(string $string): string
{
    $string = trim($string);
    if ($string === '') {
        return 'n-a';
    }

    if (class_exists(\Normalizer::class)) {
        $string = \Normalizer::normalize($string, \Normalizer::FORM_KC) ?? $string;
    }

    $string = transliterate($string);
    $string = strtolower($string);
    $string = preg_replace('~[^a-z0-9]+~', '-', $string) ?? '';
    $string = trim($string, '-');
    $string = preg_replace('~-{2,}~', '-', $string) ?? $string;

    return $string !== '' ? $string : 'n-a';
}

/**
 * Transliterate Ukrainian/Russian Cyrillic and common variants to Latin.
 * The result is not guaranteed to be ASCII-clean; createSlug() sanitizes it.
 */
function transliterate(string $string): string
{
    $string = str_replace(
        ["’", "ʼ", "`", "´", "ʹ", "ʾ", "“", "”", "«", "»"],
        ["'", "'", "'", "'", "'", "'", '"', '"', '"', '"'],
        $string
    );

    $string = str_replace(["'", "’", "ʼ", "ь", "Ь", "ъ", "Ъ"], '', $string);

    static $map = [
        'А'=>'A','а'=>'a','Б'=>'B','б'=>'b','В'=>'V','в'=>'v','Г'=>'H','г'=>'h','Ґ'=>'G','ґ'=>'g',
        'Д'=>'D','д'=>'d','Е'=>'E','е'=>'e','Є'=>'Ye','є'=>'ie','Ж'=>'Zh','ж'=>'zh','З'=>'Z','з'=>'z',
        'И'=>'Y','и'=>'y','І'=>'I','і'=>'i','Ї'=>'Yi','ї'=>'i','Й'=>'Y','й'=>'y','К'=>'K','к'=>'k',
        'Л'=>'L','л'=>'l','М'=>'M','м'=>'m','Н'=>'N','н'=>'n','О'=>'O','о'=>'o','П'=>'P','п'=>'p',
        'Р'=>'R','р'=>'r','С'=>'S','с'=>'s','Т'=>'T','т'=>'t','У'=>'U','у'=>'u','Ф'=>'F','ф'=>'f',
        'Х'=>'Kh','х'=>'kh','Ц'=>'Ts','ц'=>'ts','Ч'=>'Ch','ч'=>'ch','Ш'=>'Sh','ш'=>'sh','Щ'=>'Shch','щ'=>'shch',
        'Ю'=>'Yu','ю'=>'yu','Я'=>'Ya','я'=>'ya',
        'Ё'=>'Yo','ё'=>'yo','Э'=>'E','э'=>'e','Ы'=>'Y','ы'=>'y',
    ];

    $string = strtr($string, $map);

    if (function_exists('iconv')) {
        $converted = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $string);
        if (is_string($converted) && $converted !== '') {
            $string = $converted;
        }
    }

    return $string;
}
