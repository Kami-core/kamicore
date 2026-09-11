<?php

/**
 * KamiCore
 *
 * SPDX-License-Identifier: Apache-2.0
 *
 * @see https://kamicore.org
 */

const ROOT_PATH = './';

$requestPath = parse_url((string)($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH);
$requestPath = is_string($requestPath) && $requestPath !== '' ? $requestPath : '/';
$requestRoot = trim($requestPath, '/');
$requestRoot = $requestRoot !== '' ? explode('/', $requestRoot, 2)[0] : null;

if ($requestPath === '/install' || $requestPath === '/install/') {
    require_once ROOT_PATH . 'install/index.php';
    exit;
}

// Core-owned physical endpoints are optional web-server fallbacks.
if ($requestRoot === 'ajax') {
	require_once ROOT_PATH . 'ajax/index.php';
	exit;
}
if ($requestRoot === 'api') {
	require_once ROOT_PATH . 'api/index.php';
	exit;
}

const IN_KAMI = true;
const KAMI_FRONT = true;

require_once ROOT_PATH . 'core/init.php';

debug_step("INIT finished");

// Plugin-owned virtual endpoints bypass normal page, language, user and ACL routing.
// After dispatch, request handling and response output are entirely the plugin's responsibility.
if ($requestRoot !== null) {
	$pluginEndpoint = \Core\EndpointRegistry::resolve($requestRoot, DOMAIN_ID);
	if ($pluginEndpoint !== null) {
		if (!defined('KAMI_ENDPOINT')) {
			define('KAMI_ENDPOINT', true);
		}
		\Core\Request::init();
		\Core\ClientContext::init();
		\Core\EndpointRegistry::dispatch($pluginEndpoint, new \Core\Request());
		exit;
	}
}


// 1. Resolve the frontend URL to a page, optional item, and semantic path parameters.

// Domain pages list - only to identify current. Get from redis if possible.
$domain_pages = getDomainPages();

// Separate the path from the query string.
$parts = parse_url($_SERVER['REQUEST_URI']);
$path = trim($parts['path'], '/');
$segments = $path !== '' ? explode('/', $path) : [];

$page_id = 0;
$page_slug = '/';
$item_id = 0;
$uri_lang = null;
$route_error = null;
$path_params = [];

// Resolve the first content segment as a page or item slug.
if (!empty($segments)) {
	// Language segment comes first when present.

	$first = array_shift($segments);
	if(in_array($first, DOMAIN_CONFIG['languages'], true)) {
		$uri_lang = $first;
		$first = (count($segments)) ? array_shift($segments) : "/";
	}

	if(isset($domain_pages[$first])) {
		// The first segment is a page slug.
		$page_id = $domain_pages[$first];
		$page_slug = $first;
	} else {
		// Domain home: the first segment may be an item slug.
		$page_id = $domain_pages['/'];
		array_unshift($segments, $first);
	}

	if(count($segments)) {
		// Resolve an optional globally unique item slug.
		$first = array_shift($segments);
		$item_cached = true;
		$item = Cache::get('globals:item_'.$first);
		if($item) {
			$item_id = $item['item_id'];
		} else {
			$item_id = DB::getOne('select item_id from content_items where item_slug=$1', [$first]);
		}

		if(!$item_id) {
			// Not an item slug; restore it for semantic path parsing.
			array_unshift($segments, $first);
		}
	}

	// Semantic path parameters are parsed as key/value pairs.
	// Their ownership is validated later by actual plugin consumption.
	$params_count = count($segments);
	if ($params_count && $params_count % 2 === 0) {
		while (count($segments) >= 2) {
			$paramName = array_shift($segments);
			$value = array_shift($segments);

			if (str_ends_with($paramName, '[]')) {
				$paramName = substr($paramName, 0, -2);

				if (isset($path_params[$paramName]) && !is_array($path_params[$paramName])) {
					$route_error = 404;
					break;
				}

				$path_params[$paramName][] = $value;
			} else {
				if (array_key_exists($paramName, $path_params)) {
					$route_error = 404;
					break;
				}

				$path_params[$paramName] = $value;
			}
		}
	} elseif ($params_count) {
		$route_error = 404;
	}

} else {
	$page_id = $domain_pages["/"];
	$page_slug = "/";
}

// 3. Merge ordinary query-string parameters into GET input.
if (!empty($parts['query'])) {
	parse_str($parts['query'], $queryVars);
	$_GET = array_merge($_GET, $queryVars);
}

debug_step("URL parsing finished");

\Core\Request::setPathParams($path_params);
\Core\Request::setRoutedItemId($item_id > 0 ? (int)$item_id : null);

// Additional request pipes may be registered here.

$q = \Core\Request::init();
$data = \Core\Request::all();

debug_step("Request processed");

Core\Session::init();
debug_step('Session init');

// Detecting language: 1 - from cookies (if available), 2 - search available from browser, 3 - domain default.

if($uri_lang) {
	$lang = $uri_lang;
} else {
	$user_lang = Core\Request::cookie()['lang'] ?? null;

	if(!in_array($user_lang, DOMAIN_CONFIG['languages']) && !empty($_SERVER['HTTP_ACCEPT_LANGUAGE'])) {
		$user_lang = null;
		// Fall back to the browser language list when the cookie is missing or unsupported.
		$browser_langs = [];
		foreach (explode(',', $_SERVER['HTTP_ACCEPT_LANGUAGE']) as $part) {
			if (preg_match('/^\s*([a-zA-Z-]+)\s*(?:;\s*q\s*=\s*(\d*(?:\.\d+)?))?/', $part, $m)) {
				$code = strtolower($m[1]);
				$q    = isset($m[2]) && $m[2] !== '' ? (float)$m[2] : 1.0;
				$browser_langs[$code] = $q;
			}
		}

		arsort($browser_langs, SORT_NUMERIC);

		foreach ($browser_langs as $code => $q) {
			$base = explode('-', $code, 2)[0]; // drop country
			if (in_array($base, DOMAIN_CONFIG['languages'], true)) {
				$user_lang = $base;
				break;
			}
		}
	}

	if(!$user_lang) {
		$user_lang = DOMAIN_CONFIG['default_language'];
	}

	if($user_lang != DOMAIN_CONFIG['default_language'] && in_array($user_lang, DOMAIN_CONFIG['languages'], true)) {
		// Preserve request method and body for write requests. A 302 may turn
		// POST into GET in the client and silently discard submitted form data.
		$redirectStatus = in_array(
			Core\Request::method(),
			['POST', 'PUT', 'PATCH', 'DELETE'],
			true
		) ? 307 : 302;

		header("Location: /{$user_lang}{$_SERVER['REQUEST_URI']}", true, $redirectStatus);
		die();
	} else {
		$lang = DOMAIN_CONFIG['default_language'];
	}
}

define('LANG', $lang);
Core\Response::addCookie('lang', $lang);

$system_lang = getTranslation(\Core\Translation::SYSTEM_ENTITY_UUID) ?? [];
define('SYSTEM_DICTIONARY', $system_lang);

debug_step("Language ($lang) prepared");

Core\ClientContext::init();
debug_step('Client context init');

// User identity is initialized only after the language context exists.
Core\User::init();
debug_step('User init');
$userdata = Core\User::getUser();
define('USERDATA', $userdata);
debug_step('User processed');

// Route errors are rendered only after Request, language and user contexts exist.
if ($route_error !== null || $page_id < 1) {
	Core\Response::addHeader('HTTP/1.1 404 Not Found', true, 404);
	Core\Response::addHeader('X-Powered-By: Kami');
	Core\Response::send(Core\Renderer::finalize(Core\Renderer::renderError(404)));
	exit;
}

if (!Core\User::canPage($page_id)) {
	Core\Response::addHeader('HTTP/1.1 403 Forbidden', true, 403);
	Core\Response::addHeader('X-Powered-By: Kami');
	Core\Response::send(Core\Renderer::finalize(Core\Renderer::renderError(403)));
	exit;
}

define('PAGE_ID', $page_id);
define('PAGE_SLUG', $page_slug);

$content = "";

$page_data = Cache::get('d_'.DOMAIN_ID.':page_'.PAGE_ID) ?? [];

if(!$page_data) {
	$page_data = DB::getRow("select p.*, l.layout_filename
	from pages p
	left join theme_layouts l using(layout_id)
	where p.domain_id=".DOMAIN_ID." and p.page_id=".PAGE_ID);
	$page_data['settings'] = json_decode($page_data['page_settings'] ?? '{}', true);
	$page_data['plugins'] = json_decode($page_data['page_plugins'], true);

	$page_data['translation'] = Core\Translation::get($page_data['uuid']);

	Cache::set('d_'.DOMAIN_ID.':page_'.PAGE_ID, $page_data);

}


$plugins_in_use = [];
$wrappers = [];

$plugins = new \Core\PluginRegistry();
$wrappers = [];

$layout_params = [
	"language_code" => LANG,
	"page_title" => $page_data['translation']['title'],
	"base_href" => Core\Request::scheme()."://".DOMAIN_NAME
];

// Initialize plugins that participate in the page lifecycle without requiring a wrapper.
$lifecycle_plugin_ids = Core\Settings::get('lifecycle_plugins', []);
if (is_string($lifecycle_plugin_ids)) {
	$decoded = json_decode($lifecycle_plugin_ids, true);
	$lifecycle_plugin_ids = is_array($decoded) ? $decoded : [];
}
$lifecycle_plugin_ids = is_array($lifecycle_plugin_ids) ? $lifecycle_plugin_ids : [];
$lifecycle_plugin_ids = array_values(array_unique(array_filter(
	array_map('intval', $lifecycle_plugin_ids),
	static fn(int $plugin_id): bool => $plugin_id > 0
)));

$page_lifecycle = is_array($page_data['settings']['lifecycle_plugins'] ?? null)
	? $page_data['settings']['lifecycle_plugins']
	: [];
$lifecycle_enable = is_array($page_lifecycle['enable'] ?? null) ? $page_lifecycle['enable'] : [];
$lifecycle_disable = is_array($page_lifecycle['disable'] ?? null) ? $page_lifecycle['disable'] : [];
$lifecycle_enable = array_values(array_unique(array_filter(
	array_map('intval', $lifecycle_enable),
	static fn(int $plugin_id): bool => $plugin_id > 0
)));
$lifecycle_disable = array_values(array_unique(array_filter(
	array_map('intval', $lifecycle_disable),
	static fn(int $plugin_id): bool => $plugin_id > 0
)));

$lifecycle_plugin_ids = array_values(array_diff(
	array_values(array_unique(array_merge($lifecycle_plugin_ids, $lifecycle_enable))),
	$lifecycle_disable
));

if ($lifecycle_plugin_ids !== []) {
	$lifecycle_rows = DB::query(
		'SELECT p.plugin_id, p.system_name '
		. 'FROM plugins p '
		. 'JOIN plugin_domains pd USING(plugin_id) '
		. 'WHERE p.is_active=true AND pd.domain_id=$1 AND p.plugin_id=ANY($2::int[])',
		[DOMAIN_ID, '{' . implode(',', $lifecycle_plugin_ids) . '}']
	);
	$lifecycle_names = [];
	while ($lifecycle_plugin = DB::fetchRow($lifecycle_rows)) {
		$lifecycle_names[(int)$lifecycle_plugin['plugin_id']] = (string)$lifecycle_plugin['system_name'];
	}

	foreach ($lifecycle_plugin_ids as $plugin_id) {
		if (isset($lifecycle_names[$plugin_id])) {
			$plugins->get($lifecycle_names[$plugin_id]);
		}
	}
}

foreach ($page_data['plugins'] as $wrapper_name => $wrapper_plugins) {
    $wrappers[$wrapper_name] = '';

    foreach ($wrapper_plugins as $plugin) {
        $plugin_name = array_key_first($plugin);
        $instance_params = array_shift($plugin);

        $instance = $plugins->get($plugin_name);

        if ($instance) {
            $wrappers[$wrapper_name] .= $instance->handle($instance_params);

			$layout_params = array_replace(
				$layout_params,
				$instance->getLayoutParams()
			);
        }
    }
}

// Finalize every plugin instance created during this page request.
foreach ($plugins->instances() as $instance) {
	$instance->finalize($layout_params);
	$layout_params = array_replace(
		$layout_params,
		$instance->getLayoutParams()
	);
}

debug_step("plugins finalized");

$layout_params = array_replace(
    $layout_params,
    Core\Renderer::systemTemplateParams()
);

if (
	Core\Request::hasUnconsumedPathParams()
	|| Core\Request::hasUnclaimedRoutedItem()
) {
	Core\Response::addHeader('HTTP/1.1 404 Not Found', true, 404);
	Core\Response::addHeader('X-Powered-By: Kami');
	Core\Response::send(Core\Renderer::finalize(Core\Renderer::renderError(404)));
	exit;
}



$page = \Core\Renderer::finalize(
	\Core\Renderer::render(
		$page_data['layout_filename'],
		null,
		array_replace($wrappers, $layout_params)
	)
);

debug_step("Page rendered");

Core\Response::addHeader("X-Powered-By: Kami");
Core\Response::send($page);

debug_step("Done");

