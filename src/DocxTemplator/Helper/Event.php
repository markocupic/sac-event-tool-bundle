<?php

declare(strict_types=1);

/*
 * This file is part of SAC Event Tool Bundle.
 *
 * (c) Marko Cupic 2023 <m.cupic@gmx.ch>
 * @license GPL-3.0-or-later
 * For the full copyright and license information,
 * please view the LICENSE file that was distributed with this source code.
 * @link https://github.com/markocupic/sac-event-tool-bundle
 */

namespace Markocupic\SacEventToolBundle\DocxTemplator\Helper;

use Contao\CalendarEventsModel;
use Contao\Controller;
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\Date;
use Contao\Message;
use Contao\StringUtil;
use Contao\System;
use Contao\UserModel;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;
use Markocupic\PhpOffice\PhpWord\MsWordTemplateProcessor;
use Markocupic\SacEventToolBundle\CalendarEventsHelper;
use Markocupic\SacEventToolBundle\Config\EventExecutionState;
use Markocupic\SacEventToolBundle\Config\EventState;
use Markocupic\SacEventToolBundle\Model\CalendarEventsInstructorInvoiceModel;
use Markocupic\SacEventToolBundle\Model\CalendarEventsJourneyModel;
use Symfony\Contracts\Translation\TranslatorInterface;

class Event
{
    public function __construct(
        private readonly ContaoFramework $framework,
        private readonly TranslatorInterface $translator,
        private readonly Connection $connection,
    ) {
    }

    public function bold($string)
    {
        $string = str_replace('&lt;B&gt;', '</w:t></w:r><w:r><w:rPr><w:b/></w:rPr><w:t xml:space="preserve"> ', $string);

        return str_replace('&lt;/B&gt;', '</w:t></w:r><w:r><w:t xml:space="preserve">', $string);
    }

    /**
     * @throws Exception
     */
    public function setEventData(MsWordTemplateProcessor $objPhpWord, CalendarEventsModel $objEvent): void
    {
        // Set adapters
        /** @var Controller $controllerAdapter */
        $controllerAdapter = $this->framework->getAdapter(Controller::class);

        /** @var StringUtil $stringUtilAdapter */
        $stringUtilAdapter = $this->framework->getAdapter(StringUtil::class);

        /** @var Date $dateAdapter */
        $dateAdapter = $this->framework->getAdapter(Date::class);

        /** @var CalendarEventsHelper $calendarEventsHelperAdapter */
        $calendarEventsHelperAdapter = $this->framework->getAdapter(CalendarEventsHelper::class);

        // Event data
        $objPhpWord->replace('eventTitle', $this->prepareString($objEvent->title));
        $controllerAdapter->loadLanguageFile('tl_calendar_events');
        $arrEventTstamps = $calendarEventsHelperAdapter->getEventTimestamps($objEvent);

        if ('course' === $objEvent->eventType) {
            $objPhpWord->replace('courseId', $this->prepareString('Kurs-Nr: '.$objEvent->courseId));
        } else {
            $objPhpWord->replace('courseId', '');
        }

        // Generate event duration string
        $arrEventDates = [];

        foreach ($arrEventTstamps as $i => $v) {
            if (\count($arrEventTstamps) - 1 === $i) {
                $strFormat = 'd.m.Y';
            } else {
                $strFormat = 'd.m.';
            }
            $arrEventDates[] = $dateAdapter->parse($strFormat, $v);
        }
        $strEventDuration = implode(', ', $arrEventDates);

        // Get tour profile
        $arrTourProfile = $calendarEventsHelperAdapter->getTourProfileAsArray($objEvent);
        $strTourProfile = implode("\r\n", $arrTourProfile);
        $strTourProfile = str_replace('Tag: ', 'Tag:'."\r\n", $strTourProfile);

        // Get emergencyConcept data
        $arrEmergencyConcept = [];
        $organizers = $stringUtilAdapter->deserialize($objEvent->organizers, true);

        if (!empty($organizers)) {
            $arrOrganizers = $this->connection->fetchAllAssociative('SELECT id,title,emergencyConcept FROM tl_event_organizer WHERE id IN ('.implode(',', array_map('\intval', $organizers)).') ORDER BY emergencyConcept, title');

            foreach ($arrOrganizers as $i => $arrOrganizer) {
                // Do not print duplicate content
                if (isset($arrOrganizers[$i + 1]) && $arrOrganizers[$i + 1]['emergencyConcept'] === $arrOrganizer['emergencyConcept']) {
                    $arrEmergencyConcept[] = '<B>'.$arrOrganizer['title'].'</B>'.":\r\n";
                } else {
                    $arrEmergencyConcept[] = '<B>'.$arrOrganizer['title'].'</B>'.":\r\n".$arrOrganizer['emergencyConcept']."\r\n\r\n";
                }
            }
        }

        $strEmergencyConcept = rtrim(implode('', $arrEmergencyConcept));

        $objPhpWord->replace('eventDates', $this->prepareString($strEventDuration));
        $objPhpWord->replace('eventMeetingpoint', $this->prepareString($objEvent->meetingPoint));
        $objPhpWord->replace('eventTechDifficulties', $this->prepareString(implode(', ', $calendarEventsHelperAdapter->getTourTechDifficultiesAsArray($objEvent, false, false))));
        $objPhpWord->replace('eventEquipment', $this->prepareString($objEvent->equipment), ['multiline' => true]);
        $objPhpWord->replace('eventTourProfile', $this->prepareString($strTourProfile), ['multiline' => true]);
        $objPhpWord->replace('emergencyConcept', $this->prepareString($strEmergencyConcept), ['multiline' => true]);
        $objPhpWord->replace('eventMiscellaneous', $this->prepareString($objEvent->miscellaneous), ['multiline' => true]);
    }

    public function setTourRapportData(MsWordTemplateProcessor $objPhpWord, CalendarEventsModel $objEvent, CalendarEventsInstructorInvoiceModel $objEventInvoice, UserModel $objBiller): void
    {
        // Set adapters
        /** @var Controller $controllerAdapter */
        $controllerAdapter = $this->framework->getAdapter(Controller::class);

        /** @var System $systemAdapter */
        $systemAdapter = $this->framework->getAdapter(System::class);

        /** @var CalendarEventsHelper $calendarEventsHelperAdapter */
        $calendarEventsHelperAdapter = $this->framework->getAdapter(CalendarEventsHelper::class);

        /** @var CalendarEventsJourneyModel $calendarEventsJourneyModel */
        $calendarEventsJourneyModel = $this->framework->getAdapter(CalendarEventsJourneyModel::class);

        /** @var UserModel $userModel */
        $userModel = $this->framework->getAdapter(UserModel::class);

        /** @var Message $messageAdapter */
        $messageAdapter = $this->framework->getAdapter(Message::class);

        $controllerAdapter->loadLanguageFile('tl_calendar_events');

        $countFemale = 0;
        $countMale = 0;

        // Count participants
        // Member list
        /** @var EventMember $objEventMemberHelper */
        $objEventMemberHelper = System::getContainer()->get('Markocupic\SacEventToolBundle\DocxTemplator\Helper\EventMember');

        $objEventMember = $objEventMemberHelper->getParticipatedEventMembers($objEvent);

        if (null !== $objEventMember) {
            while ($objEventMember->next()) {
                if ('female' === $objEventMember->gender) {
                    ++$countFemale;
                } else {
                    ++$countMale;
                }
            }
            // Reset Contao model collection
            $objEventMember->reset();
        }

        $countParticipants = $countFemale + $countMale;

        // Count instructors
        $arrInstructors = $calendarEventsHelperAdapter->getInstructorsAsArray($objEvent, false);
        $countInstructors = \count($arrInstructors);
        $objUser = $userModel->findMultipleByIds($arrInstructors);

        if (null !== $objUser) {
            while ($objUser->next()) {
                if ('female' === $objUser->gender) {
                    ++$countFemale;
                } else {
                    ++$countMale;
                }
            }
        }

        $countParticipantsTotal = $countInstructors + $countParticipants;

        if ($countParticipantsTotal < $objEventInvoice->privateArrival) {
            $messageAdapter->addError($this->translator->trans('ERR.invalidNumberOfPrivateArrivals', [$objEventInvoice->privateArrival, $countParticipantsTotal], 'contao_default'));
            $controllerAdapter->redirect($systemAdapter->getReferer());
        }

        $transport = null !== $calendarEventsJourneyModel->findByPk($objEvent->journey) ? $calendarEventsJourneyModel->findByPk($objEvent->journey)->title : 'keine Angabe';
        $objPhpWord->replace('eventTransport', $this->prepareString($transport));
        $objPhpWord->replace('eventCanceled', EventState::STATE_CANCELED === $objEvent->eventState || EventExecutionState::STATE_CANCELED === $objEvent->executionState ? 'Ja' : 'Nein');
        $objPhpWord->replace('eventHasExecuted', EventExecutionState::STATE_EXECUTED_LIKE_PREDICTED === $objEvent->executionState ? 'Ja' : 'Nein');
        $substitutionText = '' !== $objEvent->eventSubstitutionText ? $objEvent->eventSubstitutionText : '---';
        $objPhpWord->replace('eventSubstitutionText', $this->prepareString($substitutionText));
        $objPhpWord->replace('eventDuration', $this->prepareString($objEventInvoice->eventDuration));

        // User
        $objPhpWord->replace('eventInstructorName', $this->prepareString($objBiller->name));
        $objPhpWord->replace('eventInstructorStreet', $this->prepareString($objBiller->street));
        $objPhpWord->replace('eventInstructorPostalCity', $this->prepareString($objBiller->postal.' '.$objBiller->city));
        $objPhpWord->replace('eventInstructorPhone', $this->prepareString($objBiller->phone));
        $objPhpWord->replace('countParticipants', $this->prepareString($countParticipants + $countInstructors));
        $objPhpWord->replace('countMale', $this->prepareString($countMale));
        $objPhpWord->replace('countFemale', $this->prepareString($countFemale));

        $objPhpWord->replace('weatherConditions', $this->prepareString($objEvent->tourWeatherConditions));
        $objPhpWord->replace('avalancheConditions', $this->prepareString($GLOBALS['TL_LANG']['tl_calendar_events'][$objEvent->tourAvalancheConditions][0]));
        $objPhpWord->replace('specialIncidents', $this->prepareString($objEvent->tourSpecialIncidents));

        $arrFields = ['sleepingTaxes', 'sleepingTaxesText', 'miscTaxes', 'miscTaxesText', 'privateArrival', 'railwTaxes', 'railwTaxesText', 'cabelCarTaxes', 'cabelCarTaxesText', 'roadTaxes', 'carTaxesKm', 'countCars', 'phoneTaxes'];

        foreach ($arrFields as $field) {
            $objPhpWord->replace($field, $this->prepareString($objEventInvoice->{$field}));
        }

        // Calculate car costs
        $carTaxes = 0;

        if ($objEventInvoice->countCars > 0 && $objEventInvoice->carTaxesKm > 0) {
            $resEventMember = $this->connection->fetchOne('SELECT * FROM tl_calendar_events_member WHERE eventId = ? AND hasParticipated = ?', [$objEvent->id, '1']);

            if (false !== $resEventMember) {
                // ((CHF 0.60 x AnzKm + Park-/Strassen-/Tunnelgebühren) x AnzAutos) : AnzPersonen
                $carTaxes = (0.6 * $objEventInvoice->carTaxesKm + $objEventInvoice->roadTaxes) * $objEventInvoice->countCars;

                if ($countParticipantsTotal - $objEventInvoice->privateArrival > 0) {
                    $carTaxes = $carTaxes / ($countParticipantsTotal - $objEventInvoice->privateArrival);
                }
            }
        }

        $objPhpWord->replace('carTaxes', $this->prepareString(round($carTaxes, 2)));
        $totalCosts = $objEventInvoice->sleepingTaxes + $objEventInvoice->miscTaxes + $objEventInvoice->railwTaxes + $objEventInvoice->cabelCarTaxes + $objEventInvoice->phoneTaxes + $carTaxes;
        $objPhpWord->replace('totalCosts', $this->prepareString(ceil($totalCosts)));

        // Notice
        $notice = empty($objEventInvoice->notice) ? '---' : $objEventInvoice->notice;
        $objPhpWord->replace('notice', $this->prepareString($notice), ['multiline' => true]);

        // eventReportAdditionalNotices
        $eventReportAdditionalNotices = empty($objEvent->eventReportAdditionalNotices) ? '---' : $objEvent->eventReportAdditionalNotices;
        $objPhpWord->replace('eventReportAdditionalNotices', $this->prepareString($eventReportAdditionalNotices), ['multiline' => true]);

        // Iban & account holder
        $objPhpWord->replace('iban', $this->prepareString($objEventInvoice->iban));
        $objPhpWord->replace('accountHolder', $this->prepareString($objBiller->name));

        // Printing date
        $objPhpWord->replace('printingDate', Date::parse('d.m.Y'));
    }

    public function checkEventRapportHasFilledInCorrectly(CalendarEventsInstructorInvoiceModel $objEventInvoice): bool
    {
        /** @var CalendarEventsModel $calendarEventsModelAdapter */
        $calendarEventsModelAdapter = $this->framework->getAdapter(CalendarEventsModel::class);

        /** @var UserModel $userModelAdapter */
        $userModelAdapter = $this->framework->getAdapter(UserModel::class);

        $objEvent = $calendarEventsModelAdapter->findByPk($objEventInvoice->pid);

        // $objBiller "Der Rechnungssteller"
        $objBiller = $userModelAdapter->findByPk($objEventInvoice->userPid);

        if (null !== $objEvent && null !== $objBiller) {
            // Check if tour report has filled in
            if ($objEvent->filledInEventReportForm && '' !== $objEvent->tourAvalancheConditions) {
                return true;
            }
        }

        return false;
    }

    protected function prepareString(mixed $string = ''): string
    {
        if (null === $string) {
            return '';
        }

        return htmlspecialchars(html_entity_decode((string) $string));
    }
}
