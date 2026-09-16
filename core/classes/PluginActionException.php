<?php

/**
 * KamiCore
 *
 * SPDX-License-Identifier: Apache-2.0
 *
 * @see https://kamicore.org
 */

namespace Core;

if (!IN_KAMI) die();

final class PluginActionException extends \RuntimeException
{
    public const BAD_REQUEST = 400;
    public const NOT_FOUND = 404;
    public const FORBIDDEN = 403;
}
