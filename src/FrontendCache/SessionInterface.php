<?php

declare(strict_types=1);

/*
 * This file is part of SAC Event Tool Bundle.
 *
 * (c) Marko Cupic 2021 <m.cupic@gmx.ch>
 * @license MIT
 * For the full copyright and license information,
 * please view the LICENSE file that was distributed with this source code.
 * @link https://github.com/markocupic/sac-event-tool-bundle
 */

namespace Markocupic\SacEventToolBundle\FrontendCache;

/**
 * Interface SessionInterface.
 */
interface SessionInterface
{
    public function set(string $strKey, $value, int $expirationTimeout = 0);

    public function get(string $strKey);

    public function has(string $strKey);
}
