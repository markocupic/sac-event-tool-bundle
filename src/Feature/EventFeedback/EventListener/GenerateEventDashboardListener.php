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

namespace Markocupic\SacEventToolBundle\Feature\EventFeedback\EventListener;

use Contao\BackendUser;
use Markocupic\SacEventToolBundle\Event\GenerateEventDashboardEvent;
use Markocupic\SacEventToolBundle\Model\EventFeedbackModel;
use Markocupic\SacEventToolBundle\Security\Voter\CalendarEventsVoter;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\Routing\RouterInterface;

/**
 * Adds the button "Event Auswertungen" to the event dashboard if the event has feedbacks.
 * Same permissions as the registration list: write access to the event or registration coordinator.
 */
#[AsEventListener]
readonly class GenerateEventDashboardListener
{
    public function __construct(
        private Security $security,
        private RouterInterface $router,
    ) {
    }

    public function __invoke(GenerateEventDashboardEvent $event): void
    {
        $calEvent = $event->getCalendarEvent();

        if (null === EventFeedbackModel::findByPid($calEvent->id)) {
            return;
        }

        $user = $this->security->getUser();

        if (!$user instanceof BackendUser) {
            return;
        }

        if (!$this->security->isGranted(CalendarEventsVoter::CAN_WRITE_EVENT, $calEvent->id) && (int) $calEvent->registrationGoesTo !== (int) $user->id) {
            return;
        }

        $href = $this->router->generate('contao_backend', [
            'do' => 'calendar',
            'key' => 'showEventFeedbacks',
            'id' => $calEvent->id,
            'ref' => $event->getRequest()->attributes->get('_contao_referer_id'),
        ]);

        $event->getMenuItem()
            ->addChild('Event Auswertungen', ['uri' => $href])
            ->setLinkAttribute('role', 'button')
            ->setLinkAttribute('class', 'tl_submit')
            ->setLinkAttribute('target', '_blank')
            ->setLinkAttribute('rel', 'noopener')
            ->setLinkAttribute('title', 'Event Auswertungen herunterladen')
        ;
    }
}
