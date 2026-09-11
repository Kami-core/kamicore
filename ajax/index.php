<?php

/**
 * KamiCore
 *
 * SPDX-License-Identifier: Apache-2.0
 *
 * @see https://kamicore.org
 */

define('IN_KAMI', true);
define('KAMI_AJAX', true);
// Supports web-server configs that route this endpoint directly.
if(!defined('ROOT_PATH')) define('ROOT_PATH', '../');

require_once ROOT_PATH . 'core/init.php';

// error_reporting(E_ALL);
// 	@ini_set('display_startup_errors', 1);
// 	@ini_set('display_errors', 1);
// 	@ini_set('log_errors', 1);


$parts = parse_url($_SERVER['REQUEST_URI']);
$path = trim($parts['path'], '/');
$segments = $path ? explode('/', $path) : [];

$zero = array_shift($segments);
if($zero!='ajax') die('bad request');

if (!empty($segments)) {
	$className = array_shift($segments);

	$plugins = getDomainPlugins();

	if(isset($plugins[$className])) {
		$path = "Plugins\\$className";
	} else {
		die();
	}

	if(count($segments)) {
		$method = array_shift($segments);
	} else {
		die('bad method');
	}

	// Remaining path segments are parsed as plain key/value pairs.
	$params_count = count($segments);
	if($params_count && $params_count % 2 === 0) {
		while (count($segments) >= 2) {
			$paramName = array_shift($segments);
			$value = array_shift($segments);

			$_GET[$paramName] = $value;
		}
	} elseif ($params_count) {
		die('bad params');
	}

} else {
	die('bad url...');
}

// Merge ordinary query-string parameters into GET input.
if (!empty($parts['query'])) {
	parse_str($parts['query'], $queryVars);
	$_GET = array_merge($_GET, $queryVars);
}

debug_step("URL parsed");

Core\Request::init();
$data = Core\Request::all();
debug_step("Request processed");
Core\ClientContext::init();
Core\Session::init();
Core\User::init();
$userdata = Core\User::getUser();

debug_step("User processed");

// Resolve language from the cookie, user preference, or domain default.

$lang = Core\Request::cookie()['lang'] ?? ($userdata['lang'] ?? null);

if(!$lang) {
	$lang = DOMAIN_CONFIG['default_language'];
}

Core\Response::addCookie("lang", $lang);

debug_step("Language ($lang)");
define('LANG', $lang);

$system_lang = Cache::get(\Core\Translation::SYSTEM_ENTITY_UUID."_".LANG);
if(!$system_lang) {
	$system_lang = json_decode(DB::getOne("select translated_data from translations where entity_uuid='".\Core\Translation::SYSTEM_ENTITY_UUID."' AND lang_code='".LANG."'") ?? "", true);
}
define('SYSTEM_DICTIONARY', $system_lang);

$content = "";

$pluginclass = "$path\\$className";

$plugins = new \Core\PluginRegistry();

$ajax_plugin = new $pluginclass($plugins);
$action = $method;

try {
    $content .= $ajax_plugin->invokeAction($action, $data);
} catch (Core\PluginActionException $e) {
    $status = $e->getCode() === Core\PluginActionException::FORBIDDEN ? 403 : 404;
    $statusText = $status === 403 ? 'Forbidden' : 'Not Found';
    Core\Response::addHeader("HTTP/1.1 {$status} {$statusText}", true, $status);
    Core\Response::send('');
    exit;
}

Core\Response::addHeader("X-Powered-By: Kami");

// Ajax actions may return either final HTML fragments or structured JSON.
// Only HTML-like responses belong to the Renderer finalization lifecycle.
if (!json_validate($content)) {
	$content = Core\Renderer::finalize($content);
}

Core\Response::send($content);
