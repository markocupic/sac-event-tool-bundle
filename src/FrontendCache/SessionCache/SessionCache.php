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

namespace Markocupic\SacEventToolBundle\FrontendCache\SessionCache;

use Markocupic\SacEventToolBundle\FrontendCache\SessionInterface;

/**
 * Class SessionCache.
 */
class SessionCache implements SessionInterface
{
    private const SESSION_KEY = 'sac_evt_frontend_cache';

    /**
     * SessionCache constructor.
     */
    public function __construct()
    {
        if (!isset($_SESSION[static::SESSION_KEY])) {
            $_SESSION[static::SESSION_KEY] = [];
        }
    }

    /**
     * @param $value
     */
    public function set(string $strKey, $value, int $expirationTimeout = 0): void
    {
        if (0 === $expirationTimeout) {
            $expirationTimeout = time() + 3600 * 24;
        }

        if ($expirationTimeout < time()) {
            $expirationTimeout = time() + 15;
        }

        $_SESSION[static::SESSION_KEY][$strKey] = [
            'data' => $value,
            'tstamp' => time(),
            'expirationTimeout' => $expirationTimeout,
        ];
    }

    public function get(string $strKey)
    {
        if (isset($_SESSION[static::SESSION_KEY][$strKey])) {
            if ($_SESSION[static::SESSION_KEY][$strKey]['expirationTimeout'] > time()) {
                return $_SESSION[static::SESSION_KEY][$strKey]['data'];
            }

            unset($_SESSION[static::SESSION_KEY][$strKey]);
        }

        return null;
    }

    /**
     * @param $strKey
     * @param string $key
     */
    public function has(string $strKey): bool
    {
        if (null !== $this->get($strKey)) {
            return true;
        }

        return false;
    }
}
