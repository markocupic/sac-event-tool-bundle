<?php

declare(strict_types=1);

/*
 * This file is part of SAC Event Tool Bundle.
 *
 * (c) Marko Cupic 2021 <m.cupic@gmx.ch>
 * @license MIT
 * For the full copyright and license information,
 * please view the LICENSE file that was distributed with this source code.
 * @link https://github.com/markocupic/sac-event-tool-bundle
 */

namespace Markocupic\SacEventToolBundle\DocxTemplator\Helper;

use Contao\CalendarEventsInstructorInvoiceModel;
use Contao\CalendarEventsJourneyModel;
use Contao\CalendarEventsModel;
use Contao\Controller;
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\Database;
use Contao\Date;
use Contao\EventOrganizerModel;
use Contao\StringUtil;
use Contao\System;
use Contao\UserModel;
use Markocupic\PhpOffice\PhpWord\MsWordTemplateProcessor;
use Markocupic\SacEventToolBundle\CalendarEventsHelper;

/**
 * Class Event.
 */
class Event
{
    /**
     * @var ContaoFramework
     */
    private $framework;

    /**
     * @var string
     */
    private $projectDir;

    /**
     * Event constructor.
     */
    public function __construct(ContaoFramework $framework, string $projectDir)
    {
        $this->framework = $framework;
        $this->projectDir = $projectDir;

        // Initialize contao framework
        $this->framework->initialize();
    }

    public function setEventData(MsWordTemplateProcessor $objPhpWord, CalendarEventsModel $objEvent): void
    {
        // Set adapters
        /** @var Controller $controllerAdapter */
        $controllerAdapter = $this->framework->getAdapter(Controller::class);
        /** @var StringUtil $stringUtilAdapter */
        $stringUtilAdapter = $this->framework->getAdapter(StringUtil::class);
        /** @var Date $dateAdapter */
        $dateAdapter = $this->framework->getAdapter(Date::class);
        /** @var EventOrganizerModel $eventOrganizerModelAdapter */
        $eventOrganizerModelAdapter = $this->framework->getAdapter(EventOrganizerModel::class);
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

        // emergencyConcept
        $arrEmergencyConcept = [];
        $arrOrganizers = $stringUtilAdapter->deserialize($objEvent->organizers, true);

        foreach ($arrOrganizers as $organizer) {
            $objOrganizer = $eventOrganizerModelAdapter->findByPk($organizer);
            $arrEmergencyConcept[] = $objOrganizer->title.":\r\n".$objOrganizer->emergencyConcept;
        }
        $strEmergencyConcept = implode("\r\n\r\n", $arrEmergencyConcept);

        $objPhpWord->replace('eventDates', $this->prepareString($strEventDuration));
        $objPhpWord->replace('eventMeetingpoint', $this->prepareString($objEvent->meetingPoint));
        $objPhpWord->replace('eventTechDifficulties', $this->prepareString(implode(', ', $calendarEventsHelperAdapter->getTourTechDifficultiesAsArray($objEvent, false))));
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
        /** @var Database $databaseAdapter */
        $databaseAdapter = $this->framework->getAdapter(Database::class);
        /** @var CalendarEventsHelper $calendarEventsHelperAdapter */
        $calendarEventsHelperAdapter = $this->framework->getAdapter(CalendarEventsHelper::class);
        /** @var CalendarEventsJourneyModel $calendarEventsJourneyModel */
        $calendarEventsJourneyModel = $this->framework->getAdapter(CalendarEventsJourneyModel::class);
        /** @var UserModel $userModel */
        $userModel = $this->framework->getAdapter(UserModel::class);

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

        $transport = null !== $calendarEventsJourneyModel->findByPk($objEvent->journey) ? $calendarEventsJourneyModel->findByPk($objEvent->journey)->title : 'keine Angabe';
        $objPhpWord->replace('eventTransport', $this->prepareString($transport));
        $objPhpWord->replace('eventCanceled', 'event_canceled' === $objEvent->eventState || 'event_canceled' === $objEvent->executionState ? 'Ja' : 'Nein');
        $objPhpWord->replace('eventHasExecuted', 'event_executed_like_predicted' === $objEvent->executionState ? 'Ja' : 'Nein');
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

        $arrFields = ['sleepingTaxes', 'sleepingTaxesText', 'miscTaxes', 'miscTaxesText', 'railwTaxes', 'railwTaxesText', 'cabelCarTaxes', 'cabelCarTaxesText', 'roadTaxes', 'carTaxesKm', 'countCars', 'phoneTaxes'];

        foreach ($arrFields as $field) {
            $objPhpWord->replace($field, $this->prepareString($objEventInvoice->{$field}));
        }

        // Calculate car costs
        $carTaxes = 0;

        if ($objEventInvoice->countCars > 0 && $objEventInvoice->carTaxesKm > 0) {
            $objEventMember = $databaseAdapter->getInstance()->prepare('SELECT * FROM tl_calendar_events_member WHERE eventId=? AND hasParticipated=?')->execute($objEvent->id, '1');

            if ($objEventMember->numRows) {
                // ((CHF 0.60 x AnzKm + Park-/Strassen-/Tunnelgebühren) x AnzAutos) : AnzPersonen
                $carTaxes = (0.6 * $objEventInvoice->carTaxesKm + $objEventInvoice->roadTaxes) * $objEventInvoice->countCars / $countParticipantsTotal;
            }
        }

        $objPhpWord->replace('carTaxes', $this->prepareString(round($carTaxes, 2)));
        $totalCosts = $objEventInvoice->sleepingTaxes + $objEventInvoice->miscTaxes + $objEventInvoice->railwTaxes + $objEventInvoice->cabelCarTaxes + $objEventInvoice->phoneTaxes + $carTaxes;
        $objPhpWord->replace('totalCosts', $this->prepareString(round($totalCosts, 2)));

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

    /**
     * @param string $string
     */
    protected function prepareString($string = ''): string
    {
        if (null === $string) {
            return '';
        }

        return htmlspecialchars(html_entity_decode((string) $string));
    }
}
