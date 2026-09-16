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

    public static function redirect(string $url, int $status = 302): string
    {
        self::addHeader('Location: ' . $url, true, $status);
        return '';
    }

    public static function seeOther(string $url): string
    {
        return self::redirect($url, 303);
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

    public static function jsRedirect(
        string $url,
        ?string $message = null,
        string $class = 'uk-alert-primary'
    ): string {
        $urlJson = \Core\Utils\JsonTool::encodeForHtml($url);
        $messageJson = \Core\Utils\JsonTool::encodeForHtml($message ?? '');
        $classJson = \Core\Utils\JsonTool::encodeForHtml($class);
        $back = $url === 'back' ? 'true' : 'false';
        $delay = $message !== null && $message !== '' ? 3000 : 0;

        return <<<HTML
<script>
(function () {
    const message = {$messageJson};
    if (message !== '') {
        const div = document.createElement('div');
        div.className = 'uk-alert ' + {$classJson};
        div.setAttribute('uk-alert', '');
        const paragraph = document.createElement('p');
        paragraph.textContent = message;
        div.appendChild(paragraph);
        document.body.appendChild(div);
        setTimeout(() => div.remove(), 2500);
    }

    setTimeout(() => {
        if ({$back}) {
            history.back();
        } else {
            window.location.href = {$urlJson};
        }
    }, {$delay});
})();
</script>
HTML;
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
