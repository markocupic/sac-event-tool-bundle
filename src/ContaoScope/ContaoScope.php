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

namespace Markocupic\SacEventToolBundle\ContaoScope;

use Contao\CoreBundle\ContaoCoreBundle;
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\CoreBundle\Routing\ScopeMatcher;
use Symfony\Component\HttpFoundation\RequestStack;

class ContaoScope
{
    private ContaoFramework $framework;
    private RequestStack $requestStack;
    private ScopeMatcher $scopeMatcher;
    private string $contaoScope = '';

    public function __construct(ContaoFramework $framework, RequestStack $requestStack, ScopeMatcher $scopeMatcher)
    {
        $this->framework = $framework;
        $this->requestStack = $requestStack;
        $this->scopeMatcher = $scopeMatcher;
    }

    /**
     * @throws \Exception
     */
    public function getScope(): string
    {
        $this->assertHasInitialized();

        $this->_setMode();

        return $this->contaoScope;
    }

    /**
     * @throws \Exception
     */
    public function isFrontend(): bool
    {
        $this->assertHasInitialized();

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
        $this->assertHasInitialized();

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
            $this->contaoScope = ContaoCoreBundle::SCOPE_BACKEND;
        } elseif ($this->isFrontend()) {
            $this->contaoScope = ContaoCoreBundle::SCOPE_FRONTEND;
        } else {
            $this->contaoScope = '';
        }
    }

    /**
     * @throws \Exception
     */
    private function assertHasInitialized(): void
    {
        if (!$this->framework->isInitialized()) {
            throw new \Exception('The Contao framework has not been initialized.');
        }
    }
}
