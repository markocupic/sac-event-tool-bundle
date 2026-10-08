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

namespace Markocupic\SacEventToolBundle\Feature\ParticipantEventHistory\EventListener;

use Contao\CalendarEventsModel;
use Contao\Config;
use Contao\CoreBundle\DataContainer\DataContainerOperation;
use Contao\CoreBundle\DependencyInjection\Attribute\AsCallback;
use Contao\CoreBundle\Framework\Adapter;
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\Date;
use Contao\Image;
use Contao\StringUtil;
use Markocupic\SacEventToolBundle\Feature\ParticipantEventHistory\Controller\ParticipantEventHistoryController;
use Markocupic\SacEventToolBundle\Feature\ParticipantEventHistory\ParticipantEventHistoryAccessPeriod;
use Markocupic\SacEventToolBundle\Feature\ParticipantEventHistory\Security\ParticipantEventHistoryVoter;
use Markocupic\SacEventToolBundle\Security\Voter\CalendarEventsVoter;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Operation "participantEventHistory" in the registration list (tl_calendar_events_member):
 * links to the history page, or is disabled with the reason as title.
 */
class ParticipantEventHistoryOperationListener
{
    private Adapter $calendarEventsModel;

    private Adapter $config;

    private Adapter $date;

    private Adapter $image;

    private Adapter $stringUtil;

    public function __construct(
        private readonly ContaoFramework $framework,
        private readonly RouterInterface $router,
        private readonly Security $security,
        private readonly TranslatorInterface $translator,
    ) {
        $this->calendarEventsModel = $this->framework->getAdapter(CalendarEventsModel::class);
        $this->config = $this->framework->getAdapter(Config::class);
        $this->date = $this->framework->getAdapter(Date::class);
        $this->image = $this->framework->getAdapter(Image::class);
        $this->stringUtil = $this->framework->getAdapter(StringUtil::class);
    }

    #[AsCallback(table: 'tl_calendar_events_member', target: 'list.operations.participantEventHistory.button')]
    public function __invoke(DataContainerOperation $operation): void
    {
        $registration = $operation->getRecord();
        $registrationId = (int) $registration['id'];

        if ($this->security->isGranted(ParticipantEventHistoryVoter::CAN_VIEW_PARTICIPANT_EVENT_HISTORY_OF_REGISTRATION, $registrationId)) {
            $operation->setUrl($this->router->generate(ParticipantEventHistoryController::class, ['registrationId' => $registrationId]));

            return;
        }

        $operation->disable();

        // Same markup as a disabled operation in DataContainer::generateButtons(), plus the reason as title
        $operation->setHtml($this->image->getHtml($operation['icon'], $operation['label'], 'title="'.$this->stringUtil->specialchars($this->getDeniedReason($registration)).'"').' ');
    }

    /**
     * @param array<string, mixed> $registration
     */
    private function getDeniedReason(array $registration): string
    {
        if ((int) ($registration['sacMemberId'] ?? 0) < 1) {
            return $this->translator->trans('MSC.participantEventHistoryNoSacMemberId', [], 'contao_default');
        }

        $event = $this->calendarEventsModel->findById((int) $registration['eventId']);

        // The user has the permission, but the access period has expired
        if (null !== $event && $this->security->isGranted(CalendarEventsVoter::CAN_VIEW_PARTICIPANT_EVENT_HISTORY, (int) $event->id)) {
            $accessEnd = ParticipantEventHistoryAccessPeriod::getAccessEndOfEvent($event);

            if (null !== $accessEnd) {
                $date = $this->date->parse((string) $this->config->get('dateFormat'), $accessEnd);

                return $this->translator->trans('MSC.participantEventHistoryAccessExpired', [$date], 'contao_default');
            }
        }

        return $this->translator->trans('MSC.participantEventHistoryNoPermission', [], 'contao_default');
    }
}
