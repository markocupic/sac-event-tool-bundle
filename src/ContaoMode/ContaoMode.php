<?php

declare(strict_types=1);

/*
 * This file is part of SAC Event Tool Bundle.
 *
 * (c) Marko Cupic 2022 <m.cupic@gmx.ch>
 * @license GPL-3.0-or-later
 * For the full copyright and license information,
 * please view the LICENSE file that was distributed with this source code.
 * @link https://github.com/markocupic/sac-event-tool-bundle
 */

namespace Markocupic\SacEventToolBundle\ContaoMode;

use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\CoreBundle\Routing\ScopeMatcher;
use Symfony\Component\HttpFoundation\RequestStack;

class ContaoMode
{
    private const FRONTEND_MODE = 'FE';

    private const BACKEND_MODE = 'BE';

    private ContaoFramework $framework;

    private RequestStack $requestStack;

    private ScopeMatcher $scopeMatcher;

    private string $contaoMode = '';

    public function __construct(ContaoFramework $framework, RequestStack $requestStack, ScopeMatcher $scopeMatcher)
    {
        $this->framework = $framework;
        $this->requestStack = $requestStack;
        $this->scopeMatcher = $scopeMatcher;
    }

    /**
     * @throws \Exception
     */
    public function getMode(): string
    {
        $this->_checkInitialization();

        $this->_setMode();

        return $this->contaoMode;
    }

    /**
     * @throws \Exception
     */
    public function isFrontend(): bool
    {
        $this->_checkInitialization();

        if (null !== ($request = $this->requestStack->getCurrentRequest())) {
            return $this->scopeMatcher->isFrontendRequest($request);
        }

        return false;
    }

    /**
     * @throws \Exception
     */
    public function isBackend(): bool
    {
        $this->_checkInitialization();

        if (null !== ($request = $this->requestStack->getCurrentRequest())) {
            return $this->scopeMatcher->isBackendRequest($request);
        }

        return false;
    }

    /**
     * @throws \Exception
     */
    private function _setMode(): void
    {
        if ($this->isBackend()) {
            $this->contaoMode = static::BACKEND_MODE;
        } elseif ($this->isFrontend()) {
            $this->contaoMode = static::FRONTEND_MODE;
        } else {
            $this->contaoMode = '';
        }
    }

    /**
     * @throws \Exception
     */
    private function _checkInitialization(): void
    {
        if (!$this->framework->isInitialized()) {
            throw new \Exception('The Contao framework is not initialized.');
        }
    }
}
