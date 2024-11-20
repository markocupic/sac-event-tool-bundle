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
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

class BackendAssetsSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly Packages $packages,
        private readonly ScopeMatcher $scopeMatcher,
    ) {
    }

    public static function getSubscribedEvents()
    {
        return [KernelEvents::REQUEST => 'registerBackendAssets'];
    }

    public function registerBackendAssets(RequestEvent $e): void
    {
        $request = $e->getRequest();

        if ($this->scopeMatcher->isBackendRequest($request)) {
            // Add backend CSS
            $GLOBALS['TL_CSS'][] = $this->packages->getUrl('css/be_stylesheet.css', 'markocupic_sac_event_tool');

            // Add backend javascript
            $GLOBALS['TL_JAVASCRIPT'][] = $this->packages->getUrl('js/backend_edit_all_navbar_helper.js', 'markocupic_sac_event_tool');

            // Load Font Awesome Free
            $GLOBALS['TL_HEAD'][] = '<script src="'.$this->packages->getUrl('fontawesomefree/js/all.js', 'markocupic/contao-component-fontawesome-free').'"></script>';
        }
    }
}
