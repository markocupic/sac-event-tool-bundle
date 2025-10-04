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

namespace Markocupic\SacEventToolBundle\DocxTemplator\Validator;

use Contao\CalendarEventsModel;
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\UserModel;
use Markocupic\SacEventToolBundle\Config\EventState;
use Markocupic\SacEventToolBundle\DocxTemplator\Exception\TourRapportGeneratorException;
use Markocupic\SacEventToolBundle\DocxTemplator\Helper\EventMember;
use Markocupic\SacEventToolBundle\Model\CalendarEventsInstructorInvoiceModel;
use Symfony\Contracts\Translation\TranslatorInterface;

readonly class TourRapportValidator
{
    public function __construct(
        private ContaoFramework $framework,
        private EventMember $docxEventMemberHelper,
        private TranslatorInterface $translator,
    ) {
    }

    public function checkEventExists(CalendarEventsModel $event): void
    {
        $eventAdapter = $this->framework->getAdapter(CalendarEventsModel::class);

        if (null === $eventAdapter->findById($event->id)) {
            $this->throwEventNotFoundException($event->id);
        }
    }

    public function checkEventRapportHasFilledOutFully(CalendarEventsInstructorInvoiceModel $eventInvoice): void
    {
        $event = $this->getEventFromInvoice($eventInvoice);
        $this->validateBiller($eventInvoice);
        $this->validateTourReportCompleteness($event);
    }

    public function checkEventHasConfirmedParticipatedMembers(CalendarEventsModel $event): void
    {
        if (EventState::STATE_CANCELED === $event->eventState) {
            return;
        }

        if (null === $this->docxEventMemberHelper->getParticipatedEventMembers($event)) {
            throw new TourRapportGeneratorException('The event has no members whose participation has been confirmed.', $this->translator->trans('ERR.evt_strn_eventHasNoMember', [], 'contao_default'));
        }
    }

    public function checkBeneficiaryExists(CalendarEventsInstructorInvoiceModel $eventInvoice): void
    {
        $this->validateBiller($eventInvoice);
    }

    private function getEventFromInvoice(CalendarEventsInstructorInvoiceModel $eventInvoice): CalendarEventsModel
    {
        $event = $eventInvoice->getRelated('pid');

        if (null === $event) {
            $this->throwEventNotFoundException($eventInvoice->pid);
        }

        return $event->current();
    }

    private function validateBiller(CalendarEventsInstructorInvoiceModel $eventInvoice): void
    {
        $userId = $eventInvoice->userPid;
        $userAdapter = $this->framework->getAdapter(UserModel::class);
        $biller = $userAdapter->findById($userId);

        if (null === $biller) {
            throw new TourRapportGeneratorException(\sprintf('User with ID %d not found.', $userId), $this->translator->trans('ERR.evt_strn_user_not_found', [$userId], 'contao_default'));
        }
    }

    private function validateTourReportCompleteness(CalendarEventsModel $event): void
    {
        if (!$event->filledInEventReportForm || '' === $event->tourAvalancheConditions) {
            throw new TourRapportGeneratorException(\sprintf('Tour report for calendar event ID %d has not been filled out fully.', $event->id), $this->translator->trans('ERR.evt_strn_eventRapportNotFilledOutCorrectlyDownloadNotPossible', [], 'contao_default'));
        }
    }

    private function throwEventNotFoundException(int|string $eventId): never
    {
        throw new TourRapportGeneratorException(\sprintf('Event with ID %s not found.', $eventId), $this->translator->trans('ERR.evt_strn_event_not_found', [$eventId], 'contao_default'));
    }
}
