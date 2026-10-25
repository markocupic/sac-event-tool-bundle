<?php

declare(strict_types=1);

/*
 * This file is part of SAC Event Tool Bundle.
 *
 * (c) Marko Cupic <m.cupic@gmx.ch>
 * @license GPL-3.0-or-later
 * For the full copyright and license information,
 * please view the LICENSE file that was distributed with this source code.
 * @link https://github.com/markocupic/sac-event-tool-bundle
 */

namespace Markocupic\SacEventToolBundle\EventSubscriber;

use Contao\CoreBundle\Routing\ScopeMatcher;
use Symfony\Component\Asset\Packages;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Twig\Environment;

class BackendAssetsSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly Packages $packages,
        private readonly ScopeMatcher $scopeMatcher,
        private readonly Environment $twig,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [KernelEvents::REQUEST => 'registerBackendAssets'];
    }

    public function registerBackendAssets(RequestEvent $e): void
    {
        $request = $e->getRequest();

        if ($this->scopeMatcher->isBackendRequest($request)) {
            // Add backend CSS
            $GLOBALS['TL_CSS'][] = $this->packages->getUrl('css/be_stylesheet.css', 'markocupic_sac_event_tool');

            // Stimulus
            $GLOBALS['TL_JAVASCRIPT'][] = $this->packages->getUrl('stimulus_backend.js', 'markocupic_sac_event_tool');

            // MultiColumnWizard (menatwork/contao-multicolumnwizard-bundle): the bundle adds its assets
            // in the "initializeSystem" hook. Under Contao 5.7 these entries can get lost (TL_JAVASCRIPT
            // and TL_CSS are backed up at the end of kernel.request by the LegacyGlobalsBackupListener and
            // restored later), which results in "MultiColumnWizard is not defined". This listener runs
            // between routing and the backup, so the assets are always there. Same keys as the bundle,
            // therefore nothing is loaded twice.
            $GLOBALS['TL_JAVASCRIPT']['multicolumnwizard'] = 'bundles/multicolumnwizard/js/multicolumnwizard_be.js';
            $GLOBALS['TL_CSS']['multicolumnwizard'] = 'bundles/multicolumnwizard/css/multicolumnwizard.css';

            // Field selection helper (Stimulus controller "sacevt--backend--edit-all-navbar-helper")
            // on the field selection page of the "editAll" and "overrideAll" mode.
            // TL_MOOTOOLS is rendered inside <body> (Contao 5.3, 5.7 and 6), so Turbo
            // removes the markup again when navigating away.
            if ($e->isMainRequest() && $this->isEditAllFieldSelection($request)) {
                $GLOBALS['TL_MOOTOOLS'][] = $this->twig->render('@Contao/be_edit_all_navbar_helper.html.twig');
            }

            // Load Font Awesome Free GLOBALS['TL_JAVASCRIPT'][] =
            // $this->packages->getUrl('fontawesomefree/js/all.js',
            // 'markocupic/contao-component-fontawesome-free');
        }

        if ($this->scopeMatcher->isFrontendRequest($request)) {
            // Stimulus
            $GLOBALS['TL_JAVASCRIPT'][] = $this->packages->getUrl('stimulus_backend.js', 'markocupic_sac_event_tool');
        }
    }

    private function isEditAllFieldSelection(Request $request): bool
    {
        if ($request->isXmlHttpRequest() || !$request->query->has('do') || $request->query->has('fields')) {
            return false;
        }

        return \in_array($request->query->get('act'), ['editAll', 'overrideAll'], true);
    }
}
