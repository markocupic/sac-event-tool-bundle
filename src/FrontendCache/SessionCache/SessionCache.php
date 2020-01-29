<?php

declare(strict_types=1);

/**
 * SAC Event Tool Web Plugin for Contao
 * Copyright (c) 2008-2020 Marko Cupic
 * @package sac-event-tool-bundle
 * @author Marko Cupic m.cupic@gmx.ch, 2017-2020
 * @link https://github.com/markocupic/sac-event-tool-bundle
 */

namespace Markocupic\SacEventToolBundle\FrontendCache\SessionCache;

use Markocupic\SacEventToolBundle\FrontendCache\SessionInterface;

/**
 * Class SessionCache
 * @package Markocupic\SacEventToolBundle\FrontendCache\SessionCache
 */
class SessionCache implements SessionInterface
{
    private const SESSION_KEY = 'sac_evt_frontend_cache';

    /**
     * SessionCache constructor.
     */
    public function __construct()
    {
        if (!isset($_SESSION[static::SESSION_KEY]))
        {
            $_SESSION[static::SESSION_KEY] = array();
        }
    }

    /**
     * @param string $strKey
     * @param $value
     * @param int $expirationTimeout
     */
    public function set(string $strKey, $value, int $expirationTimeout = 0): void
    {
        if ($expirationTimeout === 0)
        {
            $expirationTimeout = time() + 3600 * 24;
        }

        if ($expirationTimeout < time())
        {
            $expirationTimeout = time() + 15;
        }

        $_SESSION[static::SESSION_KEY][$strKey] = [
            'data'              => $value,
            'tstamp'            => time(),
            'expirationTimeout' => $expirationTimeout,
        ];
    }

    /**
     * @param string $strKey
     * @return null
     */
    public function get(string $strKey)
    {
        if (isset($_SESSION[static::SESSION_KEY][$strKey]))
        {
            if ($_SESSION[static::SESSION_KEY][$strKey]['expirationTimeout'] > time())
            {
                return $_SESSION[static::SESSION_KEY][$strKey]['data'];
            }
            else
            {
                unset($_SESSION[static::SESSION_KEY][$strKey]);
            }
        }

        return null;
    }

    /**
     * @param $eventId
     * @param string $key
     * @return bool
     */
    public function has(string $strKey): bool
    {
        if (null !== $this->get($strKey))
        {
            return true;
        }

        return false;
    }

}
