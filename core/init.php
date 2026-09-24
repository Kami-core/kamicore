<?php

/**
 * KamiCore
 *
 * SPDX-License-Identifier: Apache-2.0
 *
 * @see https://kamicore.org
 */

if(!IN_KAMI) die();

require_once ROOT_PATH . 'core/autoload.php';

// Use a deterministic timezone until installation-wide settings are loaded.
define('CORE_VERSION', '0.7.2');

date_default_timezone_set('UTC');
define('TIME_NOW', time());

$configFile = ROOT_PATH . 'config/config.php';
if (!is_file($configFile)) {
    if (defined('KAMI_FRONT')) {
        header('Location: /install/', true, 302);
        exit;
    }

    http_response_code(503);
    exit('KamiCore is not installed.');
}
require_once $configFile;
if (file_exists(ROOT_PATH.'config/config.domain.php')) {
	require_once ROOT_PATH.'config/config.domain.php';
}

// Enable timing diagnostics for frontend requests in debug mode.
if (DEBUG_MODE && defined('KAMI_FRONT')) {
    require_once ROOT_PATH . 'core/debug.php';
} elseif(DEBUG_MODE) {
	error_reporting(E_ALL);
	function debug_step(string $name) {
        return false;
    }
} else {
	//error_reporting(E_NONE);
    function debug_step(string $name) {
        return false;
    }
}

require_once ROOT_PATH . 'core/functions.php';

require_once ROOT_PATH . 'core/classes/Pgsql.php';
DB::connect($db_config['host'], $db_config['user'], $db_config['password'], $db_config['name'], 'utf8', $db_config['port']);

	require_once ROOT_PATH . 'core/classes/cache/Cache.php';
	Cache::configure($redis_config);
	Cache::connect();

// Load installation-wide settings.
$global_settings = Cache::get("globals:settings");
if(!$global_settings) {
	$global_settings = [];
	$rows = DB::query("select * from global_settings");
	while ($row = DB::fetchRow($rows)) {
		$global_settings[$row['varname']] = $row['value'];
	}
	Cache::set("globals:settings", $global_settings);
}


$timezone = trim((string)($global_settings['default_timezone'] ?? 'UTC'));
if (!in_array($timezone, timezone_identifiers_list(), true)) {
    $timezone = 'UTC';
}
$global_settings['default_timezone'] = $timezone;

// Internal processing stays in UTC; keep the database session in UTC too.
DB::query("SELECT set_config('TimeZone', 'UTC', false)");

define('GLOBAL_SETTINGS', $global_settings);

// Resolve the current domain configuration.
$domain_id = $domain = null;
$domain_config = null;

$all_domains = Cache::get('globals:domains');

if($all_domains) {
	$domain_id = $all_domains[$_SERVER['HTTP_HOST']];
	$domain_config =  Cache::get('d_'.$domain_id.':config');
}

// Rebuild the domain cache when the current domain config is missing.
if(!$domain_config) {

	$domain_name_orig = $alias_name = $_SERVER['HTTP_HOST'];
	$is_alias = false;

	$domain = DB::getRow("select * from domains
	left join themes using(theme_id)
	where domain_name=$1", [$_SERVER['HTTP_HOST']]);

	if(!$domain) {
		$domain = DB::getRow("select * from domain_aliases
		LEFT JOIN domains using(domain_id)
		LEFT JOIN themes using(theme_id)
		where alias_name=$1", [$_SERVER['HTTP_HOST']]);
		$domain_name_orig = $domain['domain_name'];
		$alias_name = $_SERVER['HTTP_HOST'];
		$is_alias = true;
	}

	if(!$domain) {
		throw new Exception("Unknown host {$_SERVER['HTTP_HOST']}.");
	}

	$domain_id = $domain['domain_id'];
	$domain_config = \Core\Utils\JsonTool::decodeArray($domain['domain_config'] ?? null);

	$domain_config['name'] = $alias_name;
	$domain_config['name_orig'] = $domain_name_orig;
	$domain_config['is_alias'] = $is_alias;
	$domain_config['theme_id'] = $domain['theme_id'];
	$domain_config['theme_path'] = $domain['system_name'];
	$domain_config['theme_settings'] = \Core\Utils\JsonTool::decodeArray($domain['theme_settings'] ?? null);

	// renew aliases and current domain
	$all_domains = [];
	$domains = DB::query("select domain_id, domain_name from domains");
	while($d = DB::fetchRow($domains)) {
		$all_domains[$d['domain_name']] = $d['domain_id'];
	}

	$aliases = DB::query("select * from domain_aliases");
	while($a = DB::fetchRow($aliases)) {
		$all_domains[$a['alias_name']] = $a['domain_id'];
	}

	Cache::set('globals:domains', $all_domains);
	Cache::set('d_'.$domain_id.':config', $domain_config);

}

define('DOMAIN_ID', (int)$domain_id);
define('DOMAIN_NAME', $domain_config['name']);
define('DOMAIN_CONFIG', $domain_config);
