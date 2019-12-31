<?php

declare(strict_types=1);

/**
 * SAC Event Tool Web Plugin for Contao
 * Copyright (c) 2008-2020 Marko Cupic
 * @package sac-event-tool-bundle
 * @author Marko Cupic m.cupic@gmx.ch, 2017-2020
 * @link https://github.com/markocupic/sac-event-tool-bundle
 */

namespace Markocupic\SacEventToolBundle\FrontendCache;

/**
 * Interface SessionInterface
 * @package Markocupic\SacEventToolBundle\FrontendCache
 */
interface SessionInterface
{
    public function set(string $strKey, $value, int $expirationTimeout = 0);

    public function get(string $strKey);

    public function has(string $strKey);

}
