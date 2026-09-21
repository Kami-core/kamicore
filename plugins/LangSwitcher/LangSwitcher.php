<?php

/**
 * KamiCore
 *
 * SPDX-License-Identifier: Apache-2.0
 *
 * @see https://kamicore.org
 */

namespace Plugins\LangSwitcher;

if(!IN_KAMI) die();

class LangSwitcher extends \Core\BasePlugin {

	public function view($instance_params) {
		$content = "";
		$flag_path = "/plugins/{$this->name}/flags";

		$parts = parse_url($_SERVER['REQUEST_URI']);
		$path = trim($parts['path'], '/');
		$segments = $path ? array_filter(explode('/', $path)) : [];
		if(count($segments) && $segments[0]==LANG) array_shift($segments);

		$base_path = (!empty($parts['query'])) ? implode("/", $segments)."?".$parts['query'] : implode("/", $segments);

		$template = $instance_params['template'] ?? "simple";

		$cache_key = \Cache::key(
			domain: DOMAIN_ID,
			language: LANG,
			plugin: 'LangSwitcher',
			name: 'langs'
		);

		$langs = \Cache::get($cache_key);
		if(!$langs) {
			foreach(DOMAIN_CONFIG['languages'] as $lang_code) {
				$lang = \DB::getRow("select * from languages where lang_code=$1", [$lang_code]);
				$langs[$lang_code] = $lang;
			}

			\Cache::set($cache_key, $langs);
		};

		$lang_items = [];
		$active_lang_item = "";
		foreach(DOMAIN_CONFIG['languages'] as $lang_code) {
			$lang = $langs[$lang_code];
			$lang['flag_path'] = $flag_path;

			$lang['url'] = "/{$lang_code}/".$base_path;

			if($lang_code==LANG) {
				$lang['selected'] = "selected";
				$lang['active'] = "active";

				$item_template = "{$template}_active_item";

				$active_lang_item = [
					"template" => $item_template,
					"params" => $lang
				];


			} else {
				$lang['selected'] = "";
				$lang['active'] = "";
				$item_template = "{$template}_item";
			}

			$lang_items[] = [
				"template" => $item_template,
				"params" => $lang
			];
		}

		$content = $this->render($template, ['items' => $lang_items, 'active_item' => $active_lang_item, 'flag_path' => $flag_path]);

		return $content;
	}
}

