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

namespace Markocupic\SacEventToolBundle\ContaoMode;

use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\CoreBundle\Routing\ScopeMatcher;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Class ContaoMode.
 */
class ContaoMode
{
    private const FRONTEND_MODE = 'FE';
    private const BACKEND_MODE = 'BE';

    /**
     * @var ContaoFramework
     */
    private $framework;

    /**
     * @var RequestStack
     */
    private $requestStack;

    /**
     * @var ScopeMatcher
     */
    private $scopeMatcher;

    /**
     * @var string
     */
    private $contaoMode;

    /**
     * ContaoMode constructor.
     */
    public function __construct(ContaoFramework $framework, RequestStack $requestStack, ScopeMatcher $scopeMatcher)
    {
        $this->framework = $framework;
        $this->requestStack = $requestStack;
        $this->scopeMatcher = $scopeMatcher;

        // Set the contao mode
        if (null !== $framework) {
            if ($this->framework->isInitialized()) {
                $this->_setMode();
            }
        }
    }

    /**
     * Get contao mode.
     */
    public function getMode(): string
    {
        return $this->contaoMode;
    }

    /**
     * Identify the Contao scope (TL_MODE) of the current request.
     */
    public function isFrontend(): bool
    {
        if (null !== $this->framework) {
            if ($this->framework->isInitialized() && $this->requestStack->getMasterRequest() && !$this->isBackend()) {
                return true;
            }
        }

        return false;
    }

    /**
     * Identify the Contao scope (TL_MODE) of the current request.
     */
    public function isBackend(): bool
    {
        if (null !== $this->framework) {
            if ($this->framework->isInitialized()) {
                if (null !== $this->requestStack) {
                    if (null !== $this->requestStack->getMasterRequest()) {
                        return $this->scopeMatcher->isBackendRequest($this->requestStack->getMasterRequest());
                    }
                }
            }
        }

        return false;
    }

    /**
     * Set contao mode.
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
}
