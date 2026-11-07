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

namespace Markocupic\SacEventToolBundle\Feature\EventEnclosureProtection\EventListener;

use Contao\CoreBundle\Exception\AccessDeniedException;
use Contao\CoreBundle\Routing\ScopeMatcher;
use Contao\CoreBundle\Security\Authentication\Token\TokenChecker;
use Contao\FrontendUser;
use Markocupic\SacEventToolBundle\Feature\EventEnclosureProtection\EventEnclosureFinder;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * The enclosures (downloads) of events are only for logged-in members.
 *
 * Contao sends an enclosure when the page is called with "?file=<path>" (event reader). This
 * listener stops the download for visitors who are not logged in, if the file is an enclosure
 * of an event. Contao then shows the 403 page. Backend users (event preview) may download.
 *
 * See docs/features/event-enclosure-protection.md
 */
#[AsEventListener(event: KernelEvents::REQUEST)]
class EventEnclosureDownloadListener
{
    public function __construct(
        private readonly ScopeMatcher $scopeMatcher,
        private readonly Security $security,
        private readonly TokenChecker $tokenChecker,
        private readonly EventEnclosureFinder $enclosureFinder,
    ) {
    }

    public function __invoke(RequestEvent $event): void
    {
        $request = $event->getRequest();

        if (!$event->isMainRequest() || !$this->scopeMatcher->isFrontendRequest($request)) {
            return;
        }

        $file = (string) $request->query->get('file', '');

        if ('' === $file) {
            return;
        }

        if ($this->security->getUser() instanceof FrontendUser || $this->tokenChecker->hasBackendUser()) {
            return;
        }

        if ($this->enclosureFinder->isEventEnclosure($file)) {
            throw new AccessDeniedException('The downloads of events are only available to logged-in members.');
        }
    }
}
