<?php

/**
 * KamiCore
 *
 * SPDX-License-Identifier: Apache-2.0
 *
 * @see https://kamicore.org
 */

declare(strict_types=1);

if (!defined('IN_KAMI')) die();

return [
    'psr4' => [
        'PHPMailer\\PHPMailer\\' => ROOT_PATH . 'third-party/PHPMailer/src/',
    ],
    'classmap' => [
        'Parsedown' => ROOT_PATH . 'third-party/parsedown/Parsedown.php',
    ],
];
