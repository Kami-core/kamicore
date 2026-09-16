<?php

/**
 * KamiCore
 *
 * SPDX-License-Identifier: Apache-2.0
 *
 * @see https://kamicore.org
 */

declare(strict_types=1);

namespace Plugins\ViewMd;

if (!defined('IN_KAMI')) die();

final class ViewMd extends \Core\BasePlugin
{
    public function view(array $instanceParams = []): string
    {
        $itemId = (int)($instanceParams['item_id'] ?? 0);
        if ($itemId < 1) {
            return $this->render('error', ['message' => 'Incorrect viewer call']);
        }

        $item = \Core\Content::getItem($itemId);
        if ($item === []) {
            return $this->render('error', ['message' => 'Content item not found']);
        }

        $markdown = (string)($item['data']['markdown_body'] ?? '');

        return $this->render('markdown', [
            'content' => $this->renderMarkdown($markdown),
        ]);
    }

    public function renderMarkdown(string $markdown): string
    {
        if (!class_exists(\Parsedown::class)) {
            throw new \RuntimeException('Parsedown is not available.');
        }

        $parser = new \Parsedown();
        $parser->setSafeMode((bool)($this->settings['safe_mode'] ?? true));

        return $parser->text($markdown);
    }
}
