<?php

declare(strict_types=1);

/*
 * This file is part of SAC Event Tool Bundle.
 *
 * (c) Marko Cupic 2024 <m.cupic@gmx.ch>
 * @license GPL-3.0-or-later
 * For the full copyright and license information,
 * please view the LICENSE file that was distributed with this source code.
 * @link https://github.com/markocupic/sac-event-tool-bundle
 */

namespace Markocupic\SacEventToolBundle\EventSubscriber;

use Contao\CoreBundle\Routing\ScopeMatcher;
use Markocupic\SacEventToolBundle\Config\Bundle;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

class BackendAssetsSubscriber implements EventSubscriberInterface
{
    public function __construct(
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
            // Add Backend CSS
            $GLOBALS['TL_CSS'][] = Bundle::ASSET_DIR . '/css/be_stylesheet.css|static';

            // Add Backend javascript
            $GLOBALS['TL_JAVASCRIPT'][] = Bundle::ASSET_DIR . '/js/backend_edit_all_navbar_helper.js';

            // Load Font Awesome key from configuration
            $GLOBALS['TL_HEAD'][] = '<script src="assets/contaocomponent-fontawesome-free/fontawesomefree/js/all.js"></script>';
        }
    }
}
