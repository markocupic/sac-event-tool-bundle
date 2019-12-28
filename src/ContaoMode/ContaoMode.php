<?php

declare(strict_types=1);

/**
 * SAC Event Tool Web Plugin for Contao
 * Copyright (c) 2008-2020 Marko Cupic
 * @package sac-event-tool-bundle
 * @author Marko Cupic m.cupic@gmx.ch, 2017-2020
 * @link https://github.com/markocupic/sac-event-tool-bundle
 */

namespace Markocupic\SacEventToolBundle\ContaoMode;

use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\CoreBundle\Routing\ScopeMatcher;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Class ContaoMode
 * @package Markocupic\SacEventToolBundle\ContaoMode
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
     * @param ContaoFramework $framework
     * @param RequestStack $requestStack
     * @param ScopeMatcher $scopeMatcher
     */
    public function __construct(ContaoFramework $framework, RequestStack $requestStack, ScopeMatcher $scopeMatcher)
    {
        $this->framework = $framework;
        $this->requestStack = $requestStack;
        $this->scopeMatcher = $scopeMatcher;

        // Set the contao mode
        if ($framework !== null)
        {
            if ($this->framework->isInitialized())
            {
                $this->_setMode();
            }
        }
    }

    /**
     * Set contao mode
     */
    private function _setMode(): void
    {
        if ($this->isBackend())
        {
            $this->contaoMode = static::BACKEND_MODE;
        }
        elseif ($this->isFrontend())
        {
            $this->contaoMode = static::FRONTEND_MODE;
        }
        else
        {
            $this->contaoMode = '';
        }
    }

    /**
     * Get contao mode
     * @return string
     */
    public function getMode(): string
    {
        return $this->contaoMode;
    }

    /**
     * Identify the Contao scope (TL_MODE) of the current request
     * @return bool
     */
    public function isFrontend(): bool
    {
        if ($this->framework !== null)
        {
            if ($this->framework->isInitialized() && $this->requestStack->getMasterRequest() && !$this->isBackend())
            {
                return true;
            }
        }
        return false;
    }

    /**
     * Identify the Contao scope (TL_MODE) of the current request
     * @return bool
     */
    public function isBackend(): bool
    {
        if ($this->framework !== null)
        {
            if ($this->framework->isInitialized())
            {
                if ($this->requestStack !== null)
                {
                    if ($this->requestStack->getMasterRequest() !== null)
                    {
                        return $this->scopeMatcher->isBackendRequest($this->requestStack->getMasterRequest());
                    }
                }
            }
        }
        return false;
    }

}
