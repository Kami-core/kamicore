<?php

/**
 * KamiCore
 *
 * SPDX-License-Identifier: Apache-2.0
 *
 * @see https://kamicore.org
 */

namespace Core;

if(!IN_KAMI) die();

class Response {
    private static array $headers = [];
    private static array $cookies = [];

    public static function addHeader(string $header, bool $replace = true, int $code = 0): void {
        self::$headers[] = compact('header','replace','code');
    }

    public static function addCookie(
        string $name,
        string $value = "",
        int $expires = 0,
        bool $httponly = false,
        string $sameSite = 'Lax'
    ): void {
        $sameSite = ucfirst(strtolower($sameSite));
        if (!in_array($sameSite, ['Lax', 'Strict', 'None'], true)) {
            throw new \InvalidArgumentException('Invalid SameSite cookie policy.');
        }

        $cookieName = Request::cookieName($name);
        self::$cookies[$cookieName] = [
            'name' => $cookieName,
            'value' => $value,
            'options' => [
                'expires' => $expires,
                'path' => '/',
                'secure' => true,
                'httponly' => $httponly,
                'samesite' => $sameSite,
            ],
        ];
    }

    public static function send(string $content): void {
        foreach (self::$headers as $h) {
            if ($h['code'] !== null) {
                header($h['header'], $h['replace'], $h['code']);
            } else {
                header($h['header'], $h['replace']);
            }
        }

        foreach (self::$cookies as $c) {
            setcookie(
                $c['name'],
                $c['value'],
                $c['options']
            );
        }

        echo $content;
    }

    public static function json(array $data, int $status = 200, int $options = JSON_UNESCAPED_UNICODE): void {
        self::addHeader("Content-Type: application/json; charset=utf-8", true, $status);
        self::send(json_encode($data, $options));
    }
}
