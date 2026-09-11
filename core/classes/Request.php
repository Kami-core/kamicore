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

interface RequestPipe {
    public function handle(array $data, ?string $raw, Request $request): array;
}

class Request
{
    public const COOKIE_PREFIX = '__Host-kami_';

    private static array $data = [];
    private static ?string $rawInput = null;
    private static string $method;
    private static string $contentType;
    private static bool $initialized = false;

    private static array $pathParams = [];
    private static array $consumedPathParams = [];
    private static ?int $routedItemId = null;
    private static array $routedItemClaims = [];

    /** @var RequestPipe[] */
    private static array $pipes = [];

    public static function init(): void
    {
        if (self::$initialized) {
            return;
        }

        self::$method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
        self::$contentType = $_SERVER['CONTENT_TYPE'] ?? '';

        $get    = $_GET ?? [];
        $post   = $_POST ?? [];
        $cookie = self::normalizeCookies($_COOKIE ?? []);
        $files  = $_FILES ?? [];

        $merged = [];

        switch (self::$method) {
            case 'GET':
                $merged = $get;
                break;

            case 'POST':
                $duplicates = array_intersect_key($post, $get);
                if ($duplicates) {
                    throw new \RuntimeException(
                        "Duplicate keys in GET and POST: " . implode(', ', array_keys($duplicates))
                    );
                }
                $merged = array_merge($post, $get);
                break;

            case 'PUT':
            case 'PATCH':
            case 'DELETE':
                if (self::$rawInput === null) {
                    self::$rawInput = file_get_contents('php://input');
                }
                $merged = $get; // Keep the body available only as raw input for these methods.
                break;

            default:
                $merged = $get;
                break;
        }

        self::$data = [
            'method'      => self::$method,
            'contentType' => self::$contentType,
            'get'         => $get,
            'post'        => $post,
            'cookie'      => $cookie,
            'files'       => $files,
            'merged'      => $merged,
            'raw'         => self::$rawInput,
        ];

        // Merge a valid JSON object into request input.
        self::parseJsonIfApplicable();

        foreach (self::$pipes as $pipe) {
            self::$data['merged'] = $pipe->handle(
                self::$data['merged'],
                self::$data['raw'],
                new self
            );
        }

        self::$initialized = true;
    }

    private static function parseJsonIfApplicable(): void
    {
        if (in_array(self::$method, ['POST', 'PUT', 'PATCH', 'DELETE'], true)) {
            if (stripos(self::$contentType, 'application/json') === 0) {
                if (self::$rawInput === null) {
                    self::$rawInput = file_get_contents('php://input');
                }

                $decoded = json_decode(self::$rawInput, true);
                if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                    // Reject ambiguous keys already present in query/form input.
                    $duplicates = array_intersect_key($decoded, self::$data['merged']);
                    if ($duplicates) {
                        throw new \RuntimeException(
                            "Duplicate keys in merged and JSON: " . implode(', ', array_keys($duplicates))
                        );
                    }
                    self::$data['merged'] = array_merge(self::$data['merged'], $decoded);
                }
                // Invalid or non-object JSON leaves the merged input unchanged.
            }
        }
    }

    public static function pipe(RequestPipe $pipe): void
    {
        self::$pipes[] = $pipe;
    }

    /**
     * Register semantic path parameters parsed by the front router.
     * Path parameters stay separate from query/form input so their usage can be tracked.
     */
    public static function setPathParams(array $params): void
    {
        if (self::$initialized) {
            throw new \LogicException('Path parameters must be registered before Request::init().');
        }

        self::$pathParams = $params;
        self::$consumedPathParams = [];
    }

    /**
     * Register the content item resolved from the frontend route.
     * The item is accepted only when at least one page plugin claims it.
     */
    public static function setRoutedItemId(?int $itemId): void
    {
        if (self::$initialized) {
            throw new \LogicException('Routed item must be registered before Request::init().');
        }
        if ($itemId !== null && $itemId < 1) {
            throw new \InvalidArgumentException('Routed item ID must be a positive integer or null.');
        }

        self::$routedItemId = $itemId;
        self::$routedItemClaims = [];
    }

    public static function routedItemId(): ?int
    {
        return self::$routedItemId;
    }

    /**
     * Accept the routed item for one concrete plugin invocation.
     */
    public static function claimRoutedItem(string $claimId): void
    {
        if (self::$routedItemId === null) {
            return;
        }

        $claimId = trim($claimId);
        if ($claimId === '') {
            throw new \InvalidArgumentException('Routed item claim ID cannot be empty.');
        }

        self::$routedItemClaims[$claimId] = true;
    }

    /**
     * Withdraw only one plugin invocation's previous routed-item claim.
     */
    public static function declineRoutedItem(string $claimId): void
    {
        unset(self::$routedItemClaims[$claimId]);
    }

    public static function hasUnclaimedRoutedItem(): bool
    {
        return self::$routedItemId !== null && self::$routedItemClaims === [];
    }

    public static function method(): string
    {
        return self::$method;
    }

    public static function contentType(): string
    {
        return self::$contentType;
    }

    /**
     * Return the externally visible request scheme.
     *
     * Proxy headers are accepted only when REMOTE_ADDR matches TRUSTED_PROXIES.
     */
    public static function scheme(): string
    {
        $forwardedScheme = self::forwardedScheme();
        if ($forwardedScheme !== null) {
            return $forwardedScheme;
        }

        $https = strtolower(trim((string)($_SERVER['HTTPS'] ?? '')));
        if ($https !== '' && $https !== 'off' && $https !== '0') {
            return 'https';
        }

        $requestScheme = strtolower(trim((string)($_SERVER['REQUEST_SCHEME'] ?? '')));
        if ($requestScheme === 'http' || $requestScheme === 'https') {
            return $requestScheme;
        }

        if ((int)($_SERVER['SERVER_PORT'] ?? 0) === 443) {
            return 'https';
        }

        return 'http';
    }

    public static function isHttps(): bool
    {
        return self::scheme() === 'https';
    }

    public static function path(): string
    {
        $path = parse_url((string)($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH);
        return is_string($path) && $path !== '' ? $path : '/';
    }

    /**
     * Return URL path segments without dropping values such as "0".
     *
     * @return list<string>
     */
    public static function segments(): array
    {
        $path = trim(self::path(), '/');
        return $path === '' ? [] : explode('/', $path);
    }

    private static function forwardedScheme(): ?string
    {
        if (!self::isTrustedProxy()) {
            return null;
        }

        // RFC 7239 Forwarded header, e.g. "for=...;proto=https;host=...".
        $forwarded = trim((string)($_SERVER['HTTP_FORWARDED'] ?? ''));
        if ($forwarded !== '') {
            $firstHop = trim(explode(',', $forwarded, 2)[0]);
            if (preg_match('/(?:^|;)\\s*proto\\s*=\\s*"?([^;",\\s]+)"?/i', $firstHop, $match)) {
                $scheme = self::normalizeScheme($match[1]);
                if ($scheme !== null) {
                    return $scheme;
                }
            }
        }

        // Common reverse proxy/CDN header. The first value is the original hop.
        $forwardedProto = trim((string)($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? ''));
        if ($forwardedProto !== '') {
            $scheme = self::normalizeScheme(explode(',', $forwardedProto, 2)[0]);
            if ($scheme !== null) {
                return $scheme;
            }
        }

        // Cloudflare normally sends X-Forwarded-Proto, but CF-Visitor is a safe fallback
        // once the connecting proxy itself has already been trusted.
        $cfVisitor = trim((string)($_SERVER['HTTP_CF_VISITOR'] ?? ''));
        if ($cfVisitor !== '') {
            $visitor = json_decode($cfVisitor, true);
            if (is_array($visitor) && isset($visitor['scheme'])) {
                $scheme = self::normalizeScheme((string)$visitor['scheme']);
                if ($scheme !== null) {
                    return $scheme;
                }
            }
        }

        // Compatibility fallbacks used by some proxies/load balancers.
        foreach (['HTTP_X_FORWARDED_SSL', 'HTTP_FRONT_END_HTTPS'] as $key) {
            $value = strtolower(trim((string)($_SERVER[$key] ?? '')));
            if ($value === 'on' || $value === '1' || $value === 'https') {
                return 'https';
            }
            if ($value === 'off' || $value === '0' || $value === 'http') {
                return 'http';
            }
        }

        return null;
    }

    private static function normalizeScheme(string $scheme): ?string
    {
        $scheme = strtolower(trim($scheme, " \t\n\r\0\x0B\"'"));
        return ($scheme === 'http' || $scheme === 'https') ? $scheme : null;
    }

    private static function isTrustedProxy(): bool
    {
        if (!defined('TRUSTED_PROXIES') || !is_array(TRUSTED_PROXIES)) {
            return false;
        }

        $remoteAddress = trim((string)($_SERVER['REMOTE_ADDR'] ?? ''));
        if ($remoteAddress === '' || @inet_pton($remoteAddress) === false) {
            return false;
        }

        foreach (TRUSTED_PROXIES as $network) {
            if (is_string($network) && self::ipMatchesNetwork($remoteAddress, $network)) {
                return true;
            }
        }

        return false;
    }

    private static function ipMatchesNetwork(string $ip, string $network): bool
    {
        $network = trim($network);
        if ($network === '') {
            return false;
        }

        if (!str_contains($network, '/')) {
            $ipBinary = @inet_pton($ip);
            $networkBinary = @inet_pton($network);
            return $ipBinary !== false
                && $networkBinary !== false
                && hash_equals($networkBinary, $ipBinary);
        }

        [$subnet, $prefix] = explode('/', $network, 2);
        $subnet = trim($subnet);
        $prefix = trim($prefix);

        if ($prefix === '' || !ctype_digit($prefix)) {
            return false;
        }

        $ipBinary = @inet_pton($ip);
        $subnetBinary = @inet_pton($subnet);
        if ($ipBinary === false || $subnetBinary === false || strlen($ipBinary) !== strlen($subnetBinary)) {
            return false;
        }

        $prefixLength = (int)$prefix;
        $maxBits = strlen($ipBinary) * 8;
        if ($prefixLength < 0 || $prefixLength > $maxBits) {
            return false;
        }

        $fullBytes = intdiv($prefixLength, 8);
        $remainingBits = $prefixLength % 8;

        if ($fullBytes > 0 && substr($ipBinary, 0, $fullBytes) !== substr($subnetBinary, 0, $fullBytes)) {
            return false;
        }

        if ($remainingBits === 0) {
            return true;
        }

        $mask = (0xFF << (8 - $remainingBits)) & 0xFF;
        return (ord($ipBinary[$fullBytes]) & $mask) === (ord($subnetBinary[$fullBytes]) & $mask);
    }

    public static function all(): array
    {
        return self::$data['merged'] ?? [];
    }

    public static function cookie(): array
    {
        return self::$data['cookie'] ?? [];
    }

    public static function files(): array
    {
        return self::$data['files'] ?? [];
    }

    public static function file(string $key): ?array
    {
        $file = self::$data['files'][$key] ?? null;
        return is_array($file) ? $file : null;
    }

    public static function cookieName(string $name): string
    {
        $name = trim($name);

        if ($name === '' || !preg_match('/^[A-Za-z0-9_-]+$/', $name)) {
            throw new \InvalidArgumentException('Invalid cookie name.');
        }

        if (str_starts_with($name, self::COOKIE_PREFIX)) {
            throw new \InvalidArgumentException(
                'Cookie names must be provided without the internal prefix.'
            );
        }

        return self::COOKIE_PREFIX . $name;
    }

    private static function normalizeCookies(array $cookies): array
    {
        $normalized = [];

        foreach ($cookies as $name => $value) {
            if (!is_string($name) || !str_starts_with($name, self::COOKIE_PREFIX)) {
                continue;
            }

            $logicalName = substr($name, strlen(self::COOKIE_PREFIX));
            if ($logicalName === '') {
                continue;
            }

            $normalized[$logicalName] = $value;
        }

        return $normalized;
    }

    public static function input(string $key, $default = null)
    {
        return self::$data['merged'][$key] ?? $default;
    }

    /**
     * Resolve a plugin parameter from the semantic path first, then from GET.
     * Reading a path parameter marks it as consumed; GET parameters are never tracked.
     */
    public static function param(
        string $key,
        ?string $prefix = null,
        mixed $default = null,
        bool $allowUnprefixedGet = true
    ): mixed
    {
        $fullKey = self::buildKey($key, $prefix);
        if ($fullKey === '') {
            return $default;
        }

        if (array_key_exists($fullKey, self::$pathParams)) {
            self::$consumedPathParams[$fullKey] = true;
            return self::$pathParams[$fullKey];
        }

        $get = self::$data['get'] ?? [];
        if (array_key_exists($fullKey, $get)) {
            return $get[$fullKey];
        }
        if (
            $allowUnprefixedGet
            && $prefix !== null
            && $prefix !== ''
            && array_key_exists($key, $get)
        ) {
            return $get[$key];
        }

        return $default;
    }

    /**
     * Resolve a plugin parameter without consuming a matching path parameter.
     */
    public static function peekParam(
        string $key,
        ?string $prefix = null,
        mixed $default = null,
        bool $allowUnprefixedGet = true
    ): mixed
    {
        $fullKey = self::buildKey($key, $prefix);
        if ($fullKey === '') {
            return $default;
        }

        if (array_key_exists($fullKey, self::$pathParams)) {
            return self::$pathParams[$fullKey];
        }

        $get = self::$data['get'] ?? [];
        if (array_key_exists($fullKey, $get)) {
            return $get[$fullKey];
        }
        if (
            $allowUnprefixedGet
            && $prefix !== null
            && $prefix !== ''
            && array_key_exists($key, $get)
        ) {
            return $get[$key];
        }

        return $default;
    }

    /**
     * Mark one semantic path parameter as consumed without changing its value.
     */
    public static function consumePathParam(string $key, ?string $prefix = null): void
    {
        $fullKey = self::buildKey($key, $prefix);
        if ($fullKey !== '' && array_key_exists($fullKey, self::$pathParams)) {
            self::$consumedPathParams[$fullKey] = true;
        }
    }

    public static function hasUnconsumedPathParams(): bool
    {
        foreach (self::$pathParams as $key => $_value) {
            if (empty(self::$consumedPathParams[$key])) {
                return true;
            }
        }
        return false;
    }

    public static function unconsumedPathParams(): array
    {
        return array_filter(
            self::$pathParams,
            static fn(mixed $_value, string|int $key): bool => empty(self::$consumedPathParams[$key]),
            ARRAY_FILTER_USE_BOTH
        );
    }

    /**
     * Build a full param key: {prefix}-{key} or just {key}.
     *
     * Note: no normalization is applied on purpose. URL parsing/routing layer
     * should provide already-normalized keys.
     */
    public static function buildKey(string $key, ?string $prefix = null): string
    {
        $key = trim($key);
        if ($key === '') {
            return '';
        }

        $prefix = $prefix !== null ? trim($prefix) : null;
        if ($prefix === null || $prefix === '') {
            return $key;
        }

        return $prefix . '-' . $key;
    }

    public static function hasParam(string $key, ?string $prefix = null): bool
    {
        $fullKey = self::buildKey($key, $prefix);
        if ($fullKey === '') {
            return false;
        }

        return array_key_exists($fullKey, self::$data['merged'] ?? []);
    }

    /**
     * Get a page number for pagination. Supports per-plugin pagination keys,
     * e.g. "nav-page", "content-page".
     */
    public static function page(?string $prefix = null, string $key = 'page', int $default = 1, int $min = 1, int $max = 1000000): int
    {
        $fullKey = self::buildKey($key, $prefix);
        if ($fullKey === '') {
            return $default;
        }

        $raw = self::param($key, $prefix, $default);
        if ($raw === null) {
            return $default;
        }

        if (is_string($raw)) {
            $raw = trim($raw);
        }

        if (!is_numeric($raw)) {
            return $default;
        }

        $page = (int)$raw;
        if ($page < $min) $page = $min;
        if ($page > $max) $page = $max;

        return $page;
    }

    /**
     * Return only non-path request params that belong to a specific plugin prefix.
     * Semantic path params must be read explicitly through param() so unknown keys remain detectable.
     *
     * Example:
     *  merged contains: ['nav-page' => 2, 'nav-sort' => 'date', 'q' => 'abc']
     *  getPrefixedParams('nav') => ['page' => 2, 'sort' => 'date']
     */
    public static function getPrefixedParams(string $prefix): array
    {
        $prefix = trim($prefix);
        if ($prefix === '') {
            return [];
        }

        $merged = self::$data['merged'] ?? [];

        if (!$merged) {
            return [];
        }

        $out = [];
        $needle = $prefix . '-';
        $nlen = strlen($needle);

        foreach ($merged as $k => $v) {
            if (!is_string($k)) {
                continue;
            }
            if (strncmp($k, $needle, $nlen) !== 0) {
                continue;
            }

            $shortKey = substr($k, $nlen);
            if ($shortKey === '') {
                continue;
            }

            $out[$shortKey] = $v;
        }

        return $out;
    }

    public static function raw(): ?string
    {
        return self::$data['raw'];
    }
}

