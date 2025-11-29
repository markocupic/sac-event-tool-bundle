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

namespace Markocupic\SacEventToolBundle\DocxTemplator\Helper;

use Contao\CalendarEventsModel;
use Contao\Controller;
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\Date;
use Contao\Message;
use Contao\StringUtil;
use Contao\System;
use Contao\UserModel;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;
use Doctrine\DBAL\Types\Types;
use Markocupic\PhpOffice\PhpWord\MsWordTemplateProcessor;
use Markocupic\SacEventToolBundle\Config\EventExecutionState;
use Markocupic\SacEventToolBundle\Config\EventState;
use Markocupic\SacEventToolBundle\Config\EventType;
use Markocupic\SacEventToolBundle\Model\CalendarEventsInstructorInvoiceModel;
use Markocupic\SacEventToolBundle\Model\CalendarEventsJourneyModel;
use Markocupic\SacEventToolBundle\Util\CalendarEventsUtil;
use Symfony\Contracts\Translation\TranslatorInterface;

readonly class Event
{
    public function __construct(
        private CalendarEventsUtil $calendarEventsUtil,
        private ContaoFramework $framework,
        private TranslatorInterface $translator,
        private Connection $connection,
        private EventMember $eventMemberHelper,
    ) {
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

        // Event data
        $objPhpWord->replace('eventTitle', $this->prepareString($objEvent->title));
        $controllerAdapter->loadLanguageFile('tl_calendar_events');

        $minMembers = '---';
        $maxMembers = '---';

        if ($objEvent->addMinAndMaxMembers) {
            $minMembers = $objEvent->minMembers ?? '0';
            $maxMembers = $objEvent->maxMembers ?? '0';
        }

        $objPhpWord->replace('minMembers', $this->prepareString($minMembers));
        $objPhpWord->replace('maxMembers', $this->prepareString($maxMembers));

        if (EventType::COURSE === $objEvent->eventType) {
            $objPhpWord->replace('courseId', $this->prepareString('Kurs-Nr: '.$objEvent->courseId));
        } else {
            $objPhpWord->replace('courseId', '');
        }

        // Generate event duration string
        $arrEventDates = [];
        $eventTimestamps = $this->calendarEventsUtil->getEventTimestamps($objEvent);

        foreach ($eventTimestamps as $i => $v) {
            if (\count($eventTimestamps) - 1 === $i) {
                $strFormat = 'd.m.Y';
            } else {
                $strFormat = 'd.m.';
            }
            $arrEventDates[] = date($strFormat, (int) $v);
        }
        $strEventDuration = implode(', ', $arrEventDates);

        // Get tour profile
        $arrTourProfile = $this->calendarEventsUtil->getTourProfileAsArray($objEvent);
        $strTourProfile = implode("\r\n", $arrTourProfile);
        $strTourProfile = str_replace('Tag: ', 'Tag:'."\r\n", $strTourProfile);

        // Get emergency concept data
        $arrEmergencyConcept = [];
        $organizers = $stringUtilAdapter->deserialize($objEvent->organizers, true);

        if (!empty($organizers)) {
            $arrOrganizers = $this->connection->fetchAllAssociative(
                'SELECT id, title, emergencyConcept FROM tl_event_organizer WHERE id IN (?) ORDER BY emergencyConcept, title',
                [array_map('\intval', $organizers)],
                [ArrayParameterType::INTEGER],
            );

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
        $objPhpWord->replace('eventTechDifficulties', $this->prepareString(implode(', ', $this->calendarEventsUtil->getTourTechDifficultiesAsArray($objEvent, false, false))));
        $objPhpWord->replace('eventEquipment', $this->prepareString($objEvent->equipment), ['multiline' => true]);
        $objPhpWord->replace('eventTourProfile', $this->prepareString($strTourProfile), ['multiline' => true]);
        $objPhpWord->replace('emergencyConcept', $this->prepareString($strEmergencyConcept), ['multiline' => true]);
        $objPhpWord->replace('eventMiscellaneous', $this->prepareString($objEvent->miscellaneous), ['multiline' => true]);
    }

    /**
     * @throws Exception
     */
    public function setTourRapportData(MsWordTemplateProcessor $objPhpWord, CalendarEventsModel $objEvent, CalendarEventsInstructorInvoiceModel $objEventInvoice, UserModel $objBiller): void
    {
        // Set adapters
        /** @var Controller $controllerAdapter */
        $controllerAdapter = $this->framework->getAdapter(Controller::class);

        /** @var System $systemAdapter */
        $systemAdapter = $this->framework->getAdapter(System::class);

        /** @var CalendarEventsJourneyModel $calendarEventsJourneyModel */
        $calendarEventsJourneyModel = $this->framework->getAdapter(CalendarEventsJourneyModel::class);

        /** @var UserModel $userModel */
        $userModel = $this->framework->getAdapter(UserModel::class);

        /** @var Message $messageAdapter */
        $messageAdapter = $this->framework->getAdapter(Message::class);

        $controllerAdapter->loadLanguageFile('tl_calendar_events');

        $countFemale = 0;
        $countMale = 0;
        $countDivers = 0;

        // Count participants Member list
        $objEventMember = $this->eventMemberHelper->getParticipatedEventMembers($objEvent);

        if (null !== $objEventMember) {
            while ($objEventMember->next()) {
                if ('female' === $objEventMember->gender) {
                    ++$countFemale;
                } elseif ('male' === $objEventMember->gender) {
                    ++$countMale;
                } else {
                    ++$countDivers;
                }
            }
            // Reset Contao model collection
            $objEventMember->reset();
        }

        $countParticipants = $countFemale + $countMale + $countDivers;

        // Count instructors
        $arrInstructors = $this->calendarEventsUtil->getInstructorsAsArray($objEvent, ['includeDisabled' => true]);
        $countInstructors = \count($arrInstructors);
        $objUser = $userModel->findMultipleByIds($arrInstructors);

        if (null !== $objUser) {
            while ($objUser->next()) {
                if ('female' === $objUser->gender) {
                    ++$countFemale;
                } elseif ('male' === $objUser->gender) {
                    ++$countMale;
                } else {
                    ++$countDivers;
                }
            }
        }

        $countParticipantsTotal = $countInstructors + $countParticipants;

        if ($countParticipantsTotal < $objEventInvoice->privateArrival) {
            $messageAdapter->addError($this->translator->trans('ERR.invalidNumberOfPrivateArrivals', [$objEventInvoice->privateArrival, $countParticipantsTotal], 'contao_default'));
            $controllerAdapter->redirect($systemAdapter->getReferer());
        }

        $transport = $calendarEventsJourneyModel->findById($objEvent->journey)->title ?? 'keine Angabe';
        $objPhpWord->replace('eventTransport', $this->prepareString($transport));
        $objPhpWord->replace('eventCanceled', EventState::STATE_CANCELED === $objEvent->eventState ? 'Ja' : 'Nein');
        $objPhpWord->replace('eventHasExecutedLikePredicted', EventExecutionState::STATE_EXECUTED_LIKE_PREDICTED === $objEvent->executionState ? 'Ja' : 'Nein');
        $substitutionText = '' !== $objEvent->eventSubstitutionText ? $objEvent->eventSubstitutionText : '---';
        $objPhpWord->replace('eventSubstitutionText', $this->prepareString($substitutionText));
        $objPhpWord->replace('eventDuration', $this->prepareString($objEventInvoice->eventDuration));

        // User
        $objPhpWord->replace('eventInstructorName', $this->prepareString($objBiller->name));
        $objPhpWord->replace('eventInstructorStreet', $this->prepareString($objBiller->street));
        $objPhpWord->replace('eventInstructorPostalCity', $this->prepareString($objBiller->postal.' '.$objBiller->city));
        $strPhone = implode("\n", array_filter([$objBiller->mobile, $objBiller->phone]));
        $strPhone = !empty($strPhone) ? $strPhone : '---';
        $objPhpWord->replace('eventInstructorPhone', $this->prepareString($strPhone), ['multiline' => true]);
        $objPhpWord->replace('countParticipants', $this->prepareString($countParticipants + $countInstructors));
        $objPhpWord->replace('countMale', $this->prepareString($countMale));
        $objPhpWord->replace('countFemale', $this->prepareString($countFemale));
        $objPhpWord->replace('countDivers', $this->prepareString($countDivers));

        $objPhpWord->replace('weatherConditions', $this->prepareString($objEvent->tourWeatherConditions));
        $objPhpWord->replace('avalancheConditions', $this->prepareString($GLOBALS['TL_LANG']['tl_calendar_events'][$objEvent->tourAvalancheConditions][0]));
        $objPhpWord->replace('specialIncidents', $this->prepareString($objEvent->tourSpecialIncidents));

        $arrFields = ['sleepingTaxes', 'sleepingTaxesText', 'miscTaxes', 'miscTaxesText', 'privateArrival', 'railwTaxes', 'railwTaxesText', 'cableCarTaxes', 'cableCarTaxesText', 'roadTaxes', 'carTaxesKm', 'countCars', 'expenseReimbursement', 'organizationalFlatRate'];

        foreach ($arrFields as $field) {
            $objPhpWord->replace($field, $this->prepareString($objEventInvoice->{$field}));
        }

        // Calculate car costs
        $carTaxes = 0;

        if ($objEventInvoice->countCars > 0 && $objEventInvoice->carTaxesKm > 0) {
            $resEventMember = $this->connection->fetchOne('SELECT * FROM tl_calendar_events_member WHERE eventId = ? AND hasParticipated = ?', [$objEvent->id, 1], [Types::INTEGER, Types::INTEGER]);

            if (false !== $resEventMember) {
                // ((CHF 0.60 x AnzKm + Park-/Strassen-/Tunnelgebühren) x AnzAutos) : AnzPersonen
                $carTaxes = (0.6 * abs($objEventInvoice->carTaxesKm) + abs($objEventInvoice->roadTaxes)) * abs($objEventInvoice->countCars);

                if ($countParticipantsTotal - abs($objEventInvoice->privateArrival) > 0) {
                    $carTaxes = $carTaxes / ($countParticipantsTotal - abs($objEventInvoice->privateArrival));
                }
            }
        }

        $objPhpWord->replace('carTaxes', $this->prepareString(round($carTaxes, 2)));

        // Calculate total costs
        $totalCosts = array_sum([
            abs($objEventInvoice->sleepingTaxes),
            abs($objEventInvoice->miscTaxes),
            abs($objEventInvoice->railwTaxes),
            abs($objEventInvoice->cableCarTaxes),
            abs($objEventInvoice->expenseReimbursement),
            abs($objEventInvoice->organizationalFlatRate),
            $carTaxes,
        ]);
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

    protected function prepareString(mixed $string = ''): string
    {
        if (null === $string) {
            return '';
        }

        return htmlspecialchars(html_entity_decode((string) $string));
    }
}
