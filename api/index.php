<?php

/**
 * KamiCore
 *
 * SPDX-License-Identifier: Apache-2.0
 *
 * @see https://kamicore.org
 */

define('IN_KAMI', true);
define('KAMI_API', true);

// Supports web-server configs that route this endpoint directly.
if (!defined('ROOT_PATH')) define('ROOT_PATH', '../');

require_once ROOT_PATH . 'core/init.php';

$apiError = static function (int $status, string $error, array $headers = []): never {
    if ($status === 401) {
        $headers['WWW-Authenticate'] ??= 'Bearer';
    }

    foreach ($headers as $name => $value) {
        Core\Response::addHeader($name . ': ' . $value);
    }

    Core\Response::json(['error' => $error], $status);
    exit;
};

$requestFormat = static function (string $contentType): ?string {
    $contentType = strtolower(trim($contentType));
    $mediaType = trim(explode(';', $contentType, 2)[0]);

    return match ($mediaType) {
        'application/json' => 'json',
        default => null,
    };
};

$responseFormat = static function (string $header): ?string {
    $header = trim($header);
    if ($header === '') {
        return 'json';
    }

    foreach (explode(',', $header) as $entry) {
        $parts = array_map('trim', explode(';', $entry));
        $mediaType = strtolower((string)array_shift($parts));
        $quality = 1.0;

        foreach ($parts as $parameter) {
            if (preg_match('/^q\s*=\s*([0-9.]+)$/i', $parameter, $match)) {
                $quality = (float)$match[1];
                break;
            }
        }

        if ($quality <= 0) {
            continue;
        }

        if (in_array($mediaType, ['*/*', 'application/*', 'application/json'], true)) {
            return 'json';
        }
    }

    return null;
};

$parts = parse_url((string)($_SERVER['REQUEST_URI'] ?? ''));
if (!is_array($parts)) {
    $apiError(400, 'Bad Request');
}

$segments = explode('/', trim((string)($parts['path'] ?? ''), '/'));

if (count($segments) !== 3 || array_shift($segments) !== 'api') {
    $apiError(404, 'Not Found');
}

$className = (string)array_shift($segments);
$action = (string)array_shift($segments);

$domainPlugins = Core\PluginRegistry::forDomain();
if ($className === '' || $action === '' || !isset($domainPlugins[$className])) {
    $apiError(404, 'Not Found');
}

$requestMethod = strtoupper(trim((string)($_SERVER['REQUEST_METHOD'] ?? 'GET')));
$queryString = trim((string)($parts['query'] ?? ''));

$authorization = trim((string)(
    $_SERVER['HTTP_AUTHORIZATION']
    ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION']
    ?? ''
));

if (!preg_match('/^Bearer\s+(\S+)$/i', $authorization, $matches)) {
    $apiError(401, 'Unauthorized');
}

$tokenValue = (string)$matches[1];
$token = DB::getRow(
    'SELECT token_id, user_id, restrictions
     FROM api_tokens
     WHERE token_hash=$1
       AND is_enabled=true
       AND revoked_at IS NULL
       AND (expires_at IS NULL OR expires_at>CURRENT_TIMESTAMP)
     LIMIT 1',
    [Core\Crypto::tokenHash($tokenValue)]
);
unset($tokenValue, $matches);

if (!$token) {
    $apiError(401, 'Unauthorized');
}

$restrictions = Core\Utils\JsonTool::decodeArray($token['restrictions'] ?? null);
if (!Core\User::initApi((int)$token['user_id'], $restrictions)) {
    $apiError(401, 'Unauthorized');
}
unset($restrictions);

DB::query(
    'UPDATE api_tokens SET last_used_at=CURRENT_TIMESTAMP WHERE token_id=$1',
    [(int)$token['token_id']]
);

debug_step('API token processed');

$lang = DOMAIN_CONFIG['default_language'];
define('LANG', $lang);

$systemLang = Cache::get(Core\Translation::SYSTEM_ENTITY_UUID . '_' . LANG);
if (!$systemLang) {
    $systemLang = Core\Utils\JsonTool::decodeArray(
        DB::getOne(
            'SELECT translated_data FROM translations WHERE entity_uuid=$1 AND lang_code=$2',
            [Core\Translation::SYSTEM_ENTITY_UUID, LANG]
        ) ?? null
    );
}
define('SYSTEM_DICTIONARY', $systemLang);

$pluginClass = "Plugins\\{$className}\\{$className}";
$plugins = new Core\PluginRegistry();
$apiPlugin = new $pluginClass($plugins);

if (!$apiPlugin->isApiAction($action)) {
    $apiError(404, 'Not Found');
}

if (!Core\User::canApiAction($className, $action)) {
    $apiError(403, 'Forbidden');
}

$allowedMethod = $apiPlugin->apiActionMethod($action);
if ($allowedMethod === null) {
    $apiError(404, 'Not Found');
}

if ($requestMethod !== $allowedMethod) {
    $apiError(405, 'Method Not Allowed', ['Allow' => $allowedMethod]);
}

$responseType = $responseFormat((string)($_SERVER['HTTP_ACCEPT'] ?? ''));
if ($responseType === null) {
    $apiError(406, 'Not Acceptable');
}

$rawInput = file_get_contents('php://input');
if ($rawInput === false) {
    $apiError(400, 'Bad Request');
}

$rawInput = trim($rawInput);
$data = [];

if ($requestMethod === 'GET') {
    if ($rawInput !== '') {
        $apiError(400, 'GET requests must not contain a body');
    }

    if ($queryString !== '') {
        parse_str($queryString, $data);
    }
} else {
    if ($queryString !== '') {
        $apiError(400, 'Query parameters are not allowed for this method');
    }

    if ($rawInput !== '') {
        $requestType = $requestFormat((string)($_SERVER['CONTENT_TYPE'] ?? ''));
        if ($requestType === null) {
            $apiError(415, 'Unsupported Media Type');
        }

        try {
            $data = match ($requestType) {
                'json' => Core\Utils\JsonTool::decode($rawInput),
                default => throw new RuntimeException('Unsupported API request format.'),
            };
        } catch (RuntimeException) {
            $apiError(400, 'Invalid request body');
        }
    }
}

Core\Request::initApi($data, $rawInput !== '' ? $rawInput : null);
debug_step('API request processed');

try {
    $result = $apiPlugin->invokeAction($action, $data);
} catch (Core\PluginActionException $e) {
    $status = match ($e->getCode()) {
        Core\PluginActionException::BAD_REQUEST => 400,
        Core\PluginActionException::FORBIDDEN => 403,
        default => 404,
    };
    $error = match ($status) {
        400 => $e->getMessage() !== '' ? $e->getMessage() : 'Bad Request',
        403 => 'Forbidden',
        default => 'Not Found',
    };
    $apiError($status, $error);
}

if (!is_array($result)) {
    trigger_error(
        "API action {$className}.{$action} must return an array.",
        E_USER_WARNING
    );
    $apiError(500, 'Internal Server Error');
}

try {
    $content = match ($responseType) {
        'json' => Core\Utils\JsonTool::encode($result, false),
        default => throw new RuntimeException('Unsupported API response format.'),
    };
} catch (RuntimeException) {
    $apiError(500, 'Internal Server Error');
}

$contentType = match ($responseType) {
    'json' => 'application/json; charset=utf-8',
};

Core\Response::addHeader('Content-Type: ' . $contentType);
Core\Response::addHeader('X-Powered-By: Kami');
Core\Response::send($content);
