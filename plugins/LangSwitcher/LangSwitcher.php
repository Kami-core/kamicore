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

	public function view(array $instance_params = []): string {
		$flag_path = "/plugins/{$this->name}/flags";
		$base_url = \Core\Url::withLanguage(
			(string)($_SERVER['REQUEST_URI'] ?? '/'),
			(string)(DOMAIN_CONFIG['default_language'] ?? LANG)
		);

		$template = $instance_params['template'] ?? "simple";

		$cache_key = \Cache::key(
			domain: DOMAIN_ID,
			language: LANG,
			plugin: 'LangSwitcher',
			name: 'langs'
		);

		$langs = \Cache::get($cache_key);
		if(!$langs) {
			$langs = [];
			foreach(DOMAIN_CONFIG['languages'] as $lang_code) {
				$lang = \DB::getRow("select * from languages where lang_code=$1", [$lang_code]);
				if ($lang) {
					$langs[$lang_code] = $lang;
				}
			}

			\Cache::set($cache_key, $langs);
		}

		$lang_items = [];
		$active_lang_item = "";
		foreach(DOMAIN_CONFIG['languages'] as $lang_code) {
			if (!isset($langs[$lang_code])) {
				continue;
			}

			$lang = $langs[$lang_code];
			$lang['flag_path'] = $flag_path;
			$lang['url'] = \Core\Html::escape(
				'/lang/' . rawurlencode((string)$lang_code)
				. '?' . http_build_query(['return' => $base_url], '', '&', PHP_QUERY_RFC3986)
			);

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

		return $this->render($template, [
			'items' => $lang_items,
			'active_item' => $active_lang_item,
			'flag_path' => $flag_path
		]);
	}

	public function routeLanguage(\Core\Request $request): void {
		if (!in_array($request::method(), ['GET', 'HEAD'], true)) {
			\Core\Response::addHeader('Allow: GET, HEAD');
			\Core\Response::addHeader('HTTP/1.1 405 Method Not Allowed', true, 405);
			\Core\Response::send('');
			return;
		}

		$segments = $request::segments();
		if (count($segments) !== 2 || ($segments[0] ?? null) !== 'lang') {
			\Core\Response::addHeader('HTTP/1.1 404 Not Found', true, 404);
			\Core\Response::send('');
			return;
		}

		$language = strtolower(trim((string)$segments[1]));
		$languages = array_map(
			static fn(mixed $value): string => strtolower(trim((string)$value)),
			is_array(DOMAIN_CONFIG['languages'] ?? null) ? DOMAIN_CONFIG['languages'] : []
		);
		if (!in_array($language, $languages, true)) {
			\Core\Response::addHeader('HTTP/1.1 404 Not Found', true, 404);
			\Core\Response::send('');
			return;
		}

		\Core\ClientContext::setLanguage(DOMAIN_ID, $language);

		$returnUrl = $this->normalizeReturnUrl(
			(string)\Core\Request::input('return', '/')
		);
		$target = \Core\Url::withLanguage($returnUrl, $language);

		\Core\Response::addHeader('Cache-Control: private, no-store');
		\Core\Response::seeOther($target);
		\Core\Response::send('');
	}

	private function normalizeReturnUrl(string $url): string {
		$url = trim($url);
		if (
			$url === ''
			|| !str_starts_with($url, '/')
			|| str_starts_with($url, '//')
			|| str_contains($url, '\\')
			|| preg_match('/[\x00-\x1F\x7F]/', $url)
		) {
			return '/';
		}

		$parts = parse_url($url);
		if (
			$parts === false
			|| isset($parts['scheme'])
			|| isset($parts['host'])
			|| isset($parts['user'])
			|| isset($parts['pass'])
		) {
			return '/';
		}

		return $url;
	}
}
