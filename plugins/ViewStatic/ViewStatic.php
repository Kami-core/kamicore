<?php

/**
 * KamiCore
 *
 * SPDX-License-Identifier: Apache-2.0
 *
 * @see https://kamicore.org
 */

namespace Plugins\ViewStatic;

if(!IN_KAMI) die();

class ViewStatic extends \Core\BasePlugin {
	public function view($instance_params) {
		$content = "";

		if ($instance_params['item_id']) {
			$id = $instance_params['item_id'];
		} else {
			return $this->render('error', ['message' => 'Incorrect viewer call']);
		}

		$values = [];
		$item = \Core\Content::getItem($id);

		if(isset($item['data']['content_items_list'])) {
			foreach($item['data']['content_items_list'] as $k => $cur_id) {
				$cur_item = \Core\Content::getItem($cur_id);

				$template_name = $cur_item['data']['static_item_template'] ?? 'default';
				$cur_data['content'] = $cur_item['data']['static_content_body'];

				$content .= $this->render($template_name, $cur_data);
			}

			$main_template_name = $item['data']['block_template'] ?? 'default';
			$common_title = ($item['data']['display_title']) ? $this->render('block_title', ['content' => $item['data']['display_title']]) : "";
		} else {
			$content = $item['data']['static_content_body'];
			$main_template_name = $item['data']['static_item_template'] ?? 'default';
			$common_title = "";
		}

		$render_data = $item['data'];
		$render_data['title'] = $common_title;
		$render_data['content'] = $content;

		return $this->render($main_template_name, $render_data);
	}

	public  function itemDelete():string {
		$data = \Core\Request::all();

		return print_r($data, true);
	}
}

