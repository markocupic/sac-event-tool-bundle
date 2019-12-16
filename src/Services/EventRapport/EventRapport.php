<?php

/**
 * SAC Event Tool Web Plugin for Contao
 * Copyright (c) 2008-2020 Marko Cupic
 * @package sac-event-tool-bundle
 * @author Marko Cupic m.cupic@gmx.ch, 2017-2020
 * @link https://github.com/markocupic/sac-event-tool-bundle
 */

namespace Markocupic\SacEventToolBundle\Services\EventRapport;

use Contao\CalendarEventsInstructorInvoiceModel;
use Contao\CalendarEventsJourneyModel;
use Contao\CalendarEventsModel;
use Markocupic\SacEventToolBundle\CalendarEventsHelper;
use Contao\Config;
use Contao\Controller;
use Contao\Database;
use Contao\Date;
use Contao\Dbafs;
use Contao\EventOrganizerModel;
use Contao\File;
use Contao\Folder;
use Contao\MemberModel;
use Contao\Message;
use Contao\StringUtil;
use Contao\System;
use Contao\UserModel;
use Markocupic\PhpOffice\PhpWord\MsWordTemplateProcessor;
use Markocupic\CloudconvertBundle\Services\DocxToPdfConversion;

/**
 * Class EventRapport
 * @package Markocupic\SacEventToolBundle\Services\EventRapport
 */
class EventRapport
{
    /**
     * @var ContaoFramework
     */
    private $framework;

    /**
     * EventRapport constructor.
     */
    public function __construct()
    {
        $this->framework = System::getContainer()->get('contao.framework');
        $this->framework->initialize();
    }

    /**
     * @param $invoiceId
     * @param string $outputType
     * @throws \Exception
     */
    public function generateInvoice($invoiceId, $outputType = 'docx')
    {
        // Set adapters
        /** @var  Config $configAdapter */
        $configAdapter = $this->framework->getAdapter(Config::class);
        /** @var  CalendarEventsModel CalendarEventsModel $calendarEventsModelAdapter */
        $calendarEventsModelAdapter = $this->framework->getAdapter(CalendarEventsModel::class);
        /** @var  UserModel $userModelAdapter */
        $userModelAdapter = $this->framework->getAdapter(UserModel::class);
        /** @var  Message $messageAdapter */
        $messageAdapter = $this->framework->getAdapter(Message::class);
        /** @var  Controller $controllerAdapter */
        $controllerAdapter = $this->framework->getAdapter(Controller::class);
        /** @var  Dbafs $dbafsAdapter */
        $dbafsAdapter = $this->framework->getAdapter(Dbafs::class);
        /** @var  Database $databaseAdapter */
        $databaseAdapter = $this->framework->getAdapter(Database::class);
        /** @var  CalendarEventsInstructorInvoiceModel $calendarEventsInstructorInvoiceModelAdapter */
        $calendarEventsInstructorInvoiceModelAdapter = $this->framework->getAdapter(CalendarEventsInstructorInvoiceModel::class);

        $objEventInvoice = $calendarEventsInstructorInvoiceModelAdapter->findByPk($invoiceId);
        if ($objEventInvoice !== null)
        {
            // Delete tmp files older the 1 week
            // Get root dir
            $rootDir = System::getContainer()->getParameter('kernel.project_dir');
            $arrScan = scan($rootDir . '/' . $configAdapter->get('SAC_EVT_TEMP_PATH'));
            foreach ($arrScan as $file)
            {
                if (is_file($rootDir . '/' . $configAdapter->get('SAC_EVT_TEMP_PATH') . '/' . $file))
                {
                    $objFile = new File($configAdapter->get('SAC_EVT_TEMP_PATH') . '/' . $file);
                    if ($objFile !== null)
                    {
                        if ((int)$objFile->mtime + 60 * 60 * 24 * 7 < time())
                        {
                            $objFile->delete();
                        }
                    }
                }
            }

            $objEvent = $calendarEventsModelAdapter->findByPk($objEventInvoice->pid);
            // $objBiller "Der Rechnungssteller"
            $objBiller = $userModelAdapter->findByPk($objEventInvoice->userPid);
            if ($objEvent !== null && $objBiller !== null)
            {
                // Check if tour report has filled in
                if (!$objEvent->filledInEventReportForm || $objEvent->tourAvalancheConditions === '')
                {
                    $messageAdapter->addError('Bitte f&uuml;llen Sie den Touren-Rapport vollst&auml;ndig aus, bevor Sie das Verg&uuml;tungsformular herunterladen.');
                    $controllerAdapter->redirect(System::getReferer());
                }

                $objEventMember = $databaseAdapter->getInstance()->prepare('SELECT * FROM tl_calendar_events_member WHERE eventId=? AND hasParticipated=?')->execute($objEvent->id, '1');
                if (!$objEventMember->numRows)
                {
                    // Send error message if there are no members assigned to the event
                    $messageAdapter->addError('Bitte &uuml;berpr&uuml;fe die Teilnehmerliste. Es wurdem keine Teilnehmer gefunden, die am Event teilgenommen haben.');
                    $controllerAdapter->redirect(System::getReferer());
                }

                $filenamePattern = str_replace('%%s', '%s', $configAdapter->get('SAC_EVT_EVENT_TOUR_INVOICE_FILE_NAME_PATTERN'));
                $destFilename = $configAdapter->get('SAC_EVT_TEMP_PATH') . '/' . sprintf($filenamePattern, time(), 'docx');
                $objPhpWord = new MsWordTemplateProcessor($configAdapter->get('SAC_EVT_EVENT_TOUR_INVOICE_TEMPLATE_SRC'), $destFilename);

                // Page #1
                // Tour rapport
                $this->getTourRapportData($objPhpWord, $objEvent, $objEventMember, $objEventInvoice, $objBiller);

                // Page #1 + #2
                // Get event data
                $this->getEventData($objPhpWord, $objEvent);

                // Page #2
                // Member list
                $this->getEventMemberData($objPhpWord, $objEvent, $objEventMember);

                // Create temporary folder, if it not exists.
                new Folder($configAdapter->get('SAC_EVT_TEMP_PATH'));
                $dbafsAdapter->addResource($configAdapter->get('SAC_EVT_TEMP_PATH'));

                if ($outputType === 'pdf')
                {
                    // Generate Docx file from template;
                    $objPhpWord->generateUncached(true)
                        ->sendToBrowser(false)
                        ->generate();

                    // Generate pdf
                    $objConversion = new DocxToPdfConversion($destFilename, $configAdapter->get('cloudconvertApiKey'));
                    $objConversion->sendToBrowser(true)->createUncached(true)->convert();
                }

                if ($outputType === 'docx')
                {
                    // Generate Docx file from template;
                    $objPhpWord->generateUncached(true)
                        ->sendToBrowser(true)
                        ->generate();
                }

                exit();
            }
        }
    }

    /**
     * @param MsWordTemplateProcessor $objPhpWord
     * @param $objEvent
     * @param $objEventMember
     * @param $objEventInvoice
     * @param $objBiller
     */
    protected function getTourRapportData(MsWordTemplateProcessor $objPhpWord, $objEvent, $objEventMember, $objEventInvoice, $objBiller)
    {
        // Set adapters
        $controllerAdapter = $this->framework->getAdapter(Controller::class);
        $databaseAdapter = $this->framework->getAdapter(Database::class);
        $calendarEventsHelperAdapter = $this->framework->getAdapter(CalendarEventsHelper::class);
        $calendarEventsJourneyModel = $this->framework->getAdapter(CalendarEventsJourneyModel::class);

        $controllerAdapter->loadLanguageFile('tl_calendar_events');

        $countParticipants = $objEventMember->numRows;
        $arrInstructors = $calendarEventsHelperAdapter->getInstructorsAsArray($objEvent->id, false);
        $countInstructors = count($arrInstructors);
        $countParticipantsTotal = $countParticipants + $countInstructors;

        $transport = $calendarEventsJourneyModel->findByPk($objEvent->journey) !== null ? $calendarEventsJourneyModel->findByPk($objEvent->journey)->title : 'keine Angabe';
        $objPhpWord->replace('eventTransport', htmlspecialchars(html_entity_decode($transport)));
        $objPhpWord->replace('eventCanceled', ($objEvent->eventState === 'event_canceled' || $objEvent->executionState === 'event_canceled') ? 'Ja' : 'Nein');
        $objPhpWord->replace('eventHasExecuted', $objEvent->executionState === 'event_executed_like_predicted' ? 'Ja' : 'Nein');
        $substitutionText = $objEvent->eventSubstitutionText !== '' ? $objEvent->eventSubstitutionText : '---';
        $objPhpWord->replace('eventSubstitutionText', htmlspecialchars(html_entity_decode($substitutionText)));
        $objPhpWord->replace('eventDuration', htmlspecialchars(html_entity_decode($objEventInvoice->eventDuration)));

        // User
        $objPhpWord->replace('eventInstructorName', htmlspecialchars(html_entity_decode($objBiller->name)));
        $objPhpWord->replace('eventInstructorStreet', htmlspecialchars(html_entity_decode($objBiller->street)));
        $objPhpWord->replace('eventInstructorPostalCity', htmlspecialchars(html_entity_decode($objBiller->postal . ' ' . $objBiller->city)));
        $objPhpWord->replace('eventInstructorPhone', htmlspecialchars(html_entity_decode($objBiller->phone)));
        $objPhpWord->replace('countParticipants', htmlspecialchars(html_entity_decode($countParticipantsTotal)));

        $objPhpWord->replace('weatherConditions', htmlspecialchars(html_entity_decode($objEvent->tourWeatherConditions)));
        $objPhpWord->replace('avalancheConditions', htmlspecialchars(html_entity_decode($GLOBALS['TL_LANG']['tl_calendar_events'][$objEvent->tourAvalancheConditions][0])));
        $objPhpWord->replace('specialIncidents', htmlspecialchars(html_entity_decode($objEvent->tourSpecialIncidents)));

        $arrFields = array('sleepingTaxes', 'sleepingTaxesText', 'miscTaxes', 'miscTaxesText', 'railwTaxes', 'railwTaxesText', 'cabelCarTaxes', 'cabelCarTaxesText', 'roadTaxes', 'carTaxesKm', 'countCars', 'phoneTaxes');
        foreach ($arrFields as $field)
        {
            $objPhpWord->replace($field, htmlspecialchars(html_entity_decode($objEventInvoice->{$field})));
        }

        // Calculate car costs
        $carTaxes = 0;
        if ($objEventInvoice->countCars > 0 && $objEventInvoice->carTaxesKm > 0)
        {
            $objEventMember = $databaseAdapter->getInstance()->prepare('SELECT * FROM tl_calendar_events_member WHERE eventId=? AND hasParticipated=?')->execute($objEvent->id, '1');
            if ($objEventMember->numRows)
            {
                // ((CHF 0.60 x AnzKm + Park-/Strassen-/Tunnelgebühren) x AnzAutos) : AnzPersonen
                $carTaxes = ((0.6 * $objEventInvoice->carTaxesKm + $objEventInvoice->roadTaxes) * $objEventInvoice->countCars) / $countParticipantsTotal;
            }
        }

        $objPhpWord->replace('carTaxes', htmlspecialchars(html_entity_decode(round($carTaxes, 2))));
        $totalCosts = $objEventInvoice->sleepingTaxes + $objEventInvoice->miscTaxes + $objEventInvoice->railwTaxes + $objEventInvoice->cabelCarTaxes + $objEventInvoice->phoneTaxes + $carTaxes;
        $objPhpWord->replace('totalCosts', htmlspecialchars(html_entity_decode(round($totalCosts, 2))));

        // Notice
        $notice = $objEventInvoice->notice == '' ? '---' : $objEventInvoice->notice;
        $objPhpWord->replace('notice', htmlspecialchars(html_entity_decode($notice)), array('multiline' => true));

        // eventReportAdditionalNotices
        $eventReportAdditionalNotices = $objEvent->eventReportAdditionalNotices == '' ? '---' : $objEvent->eventReportAdditionalNotices;
        $objPhpWord->replace('eventReportAdditionalNotices', htmlspecialchars(html_entity_decode($eventReportAdditionalNotices)), array('multiline' => true));

        // Iban & account holder
        $objPhpWord->replace('iban', htmlspecialchars(html_entity_decode($objEventInvoice->iban)));
        $objPhpWord->replace('accountHolder', htmlspecialchars(html_entity_decode($objBiller->name)));
    }

    /**
     * @param MsWordTemplateProcessor $objPhpWord
     * @param $objEvent
     */
    protected function getEventData(MsWordTemplateProcessor $objPhpWord, $objEvent)
    {
        // Set adapters
        $controllerAdapter = $this->framework->getAdapter(Controller::class);
        $stringUtilAdapter = $this->framework->getAdapter(StringUtil::class);
        $dateAdapter = $this->framework->getAdapter(Date::class);
        $eventOrganizerModelAdapter = $this->framework->getAdapter(EventOrganizerModel::class);
        $calendarEventsHelperAdapter = $this->framework->getAdapter(CalendarEventsHelper::class);

        // Event data
        $objPhpWord->replace('eventTitle', htmlspecialchars(html_entity_decode($objEvent->title)));
        $controllerAdapter->loadLanguageFile('tl_calendar_events');
        $arrEventTstamps = $calendarEventsHelperAdapter->getEventTimestamps($objEvent->id);

        if ($objEvent->eventType === 'course')
        {
            $objPhpWord->replace('courseId', htmlspecialchars(html_entity_decode('Kurs-Nr: ' . $objEvent->courseId)));
        }
        else
        {
            $objPhpWord->replace('courseId', '');
        }

        // Generate event duration string
        $arrEventDates = array();
        foreach ($arrEventTstamps as $i => $v)
        {
            if ((count($arrEventTstamps) - 1) == $i)
            {
                $strFormat = 'd.m.Y';
            }
            else
            {
                $strFormat = 'd.m.';
            }
            $arrEventDates[] = $dateAdapter->parse($strFormat, $v);
        }
        $strEventDuration = implode(', ', $arrEventDates);

        // Get tour profile
        $arrTourProfile = $calendarEventsHelperAdapter->getTourProfileAsArray($objEvent->id);
        $strTourProfile = implode("\r\n", $arrTourProfile);
        $strTourProfile = str_replace('Tag: ', 'Tag:' . "\r\n", $strTourProfile);

        // emergencyConcept
        $arrEmergencyConcept = array();
        $arrOrganizers = $stringUtilAdapter->deserialize($objEvent->organizers, true);
        foreach ($arrOrganizers as $organizer)
        {
            $objOrganizer = $eventOrganizerModelAdapter->findByPk($organizer);
            $arrEmergencyConcept[] = $objOrganizer->title . ":\r\n" . $objOrganizer->emergencyConcept;
        }
        $strEmergencyConcept = implode("\r\n\r\n", $arrEmergencyConcept);

        $objPhpWord->replace('eventDates', htmlspecialchars(html_entity_decode($strEventDuration)));
        $objPhpWord->replace('eventMeetingpoint', htmlspecialchars(html_entity_decode($objEvent->meetingPoint)));
        $objPhpWord->replace('eventTechDifficulties', htmlspecialchars(html_entity_decode(implode(', ', $calendarEventsHelperAdapter->getTourTechDifficultiesAsArray($objEvent->id, false)))));
        $objPhpWord->replace('eventEquipment', htmlspecialchars(html_entity_decode($objEvent->equipment)), array('multiline' => true));
        $objPhpWord->replace('eventTourProfile', htmlspecialchars(html_entity_decode($strTourProfile)), array('multiline' => true));
        $objPhpWord->replace('emergencyConcept', htmlspecialchars(html_entity_decode($strEmergencyConcept)), array('multiline' => true));
        $objPhpWord->replace('eventMiscellaneous', htmlspecialchars(html_entity_decode($objEvent->miscellaneous)), array('multiline' => true));
    }

    /**
     * @param MsWordTemplateProcessor $objPhpWord
     * @param $objEvent
     * @param $objEventMember
     */
    protected function getEventMemberData(MsWordTemplateProcessor $objPhpWord, $objEvent, $objEventMember)
    {
        // Set adapters
        $userModelAdapter = $this->framework->getAdapter(UserModel::class);
        $memberModelAdapter = $this->framework->getAdapter(MemberModel::class);
        $dateAdapter = $this->framework->getAdapter(Date::class);
        $calendarEventsHelperAdapter = $this->framework->getAdapter(CalendarEventsHelper::class);

        $i = 0;

        // TL
        $arrInstructors = $calendarEventsHelperAdapter->getInstructorsAsArray($objEvent->id, false);
        if (!empty($arrInstructors) && is_array($arrInstructors))
        {
            foreach ($arrInstructors as $userId)
            {
                $objUserModel = $userModelAdapter->findByPk($userId);
                if ($objUserModel !== null)
                {
                    // Check club membership
                    $isMember = false;
                    $objMember = $memberModelAdapter->findBySacMemberId($objUserModel->sacMemberId);
                    if ($objMember !== null)
                    {
                        if ($objMember->isSacMember && !$objMember->disable)
                        {
                            $isMember = true;
                        }
                    }
                    // Keep this var empty
                    $transportInfo = '';

                    // Phone
                    $mobile = $objUserModel->mobile != '' ? $objUserModel->mobile : '----';

                    $i++;

                    // Clone row
                    $objPhpWord->createClone('i');

                    // Push data to clone
                    $objPhpWord->addToClone('i', 'i', $i, array('multiline' => false));
                    $objPhpWord->addToClone('i', 'role', 'TL', array('multiline' => false));
                    $objPhpWord->addToClone('i', 'firstname', htmlspecialchars(html_entity_decode($objUserModel->name)), array('multiline' => false));
                    $objPhpWord->addToClone('i', 'lastname', '', array('multiline' => false));
                    $objPhpWord->addToClone('i', 'sacMemberId', 'Mitgl. No. ' . $objUserModel->sacMemberId, array('multiline' => false));
                    $objPhpWord->addToClone('i', 'isNotSacMember', $isMember ? ' ' : '!inaktiv/kein Mitglied', array('multiline' => false));
                    $objPhpWord->addToClone('i', 'street', htmlspecialchars(html_entity_decode($objUserModel->street)), array('multiline' => false));
                    $objPhpWord->addToClone('i', 'postal', htmlspecialchars(html_entity_decode($objUserModel->postal)), array('multiline' => false));
                    $objPhpWord->addToClone('i', 'city', htmlspecialchars(html_entity_decode($objUserModel->city)), array('multiline' => false));
                    $objPhpWord->addToClone('i', 'emergencyPhone', htmlspecialchars(html_entity_decode($objUserModel->emergencyPhone)), array('multiline' => false));
                    $objPhpWord->addToClone('i', 'emergencyPhoneName', htmlspecialchars(html_entity_decode($objUserModel->emergencyPhoneName)), array('multiline' => false));
                    $objPhpWord->addToClone('i', 'mobile', htmlspecialchars(html_entity_decode($mobile)), array('multiline' => false));
                    $objPhpWord->addToClone('i', 'email', htmlspecialchars(html_entity_decode($objUserModel->email)), array('multiline' => false));
                    $objPhpWord->addToClone('i', 'transportInfo', htmlspecialchars(html_entity_decode($transportInfo)), array('multiline' => false));
                    $objPhpWord->addToClone('i', 'dateOfBirth', $objUserModel->dateOfBirth != '' ? $dateAdapter->parse('Y', $objUserModel->dateOfBirth) : '', array('multiline' => false));
                }
            }
        }

        // TN
        while ($objEventMember->next())
        {
            $i++;

            // Check club membership
            $strIsActiveMember = '!inaktiv/keinMitglied';
            if ($objEventMember->sacMemberId != '')
            {
                $objMemberModel = $memberModelAdapter->findBySacMemberId($objEventMember->sacMemberId);
                if ($objMemberModel !== null)
                {
                    if ($objMemberModel->isSacMember && !$objMemberModel->disable)
                    {
                        $strIsActiveMember = ' ';
                    }
                }
            }

            $transportInfo = '';
            if (strlen($objEventMember->carInfo))
            {
                if ((integer)$objEventMember->carInfo > 0)
                {
                    $transportInfo .= sprintf(' Auto mit %s Plätzen', $objEventMember->carInfo);
                }
            }

            // GA, Halbtax, Tageskarte
            if (strlen($objEventMember->ticketInfo))
            {
                $transportInfo .= sprintf(' Ticket: Mit %s', $objEventMember->ticketInfo);
            }

            // Phone
            $mobile = $objEventMember->mobile != '' ? $objEventMember->mobile : '----';
            // Clone row
            $objPhpWord->createClone('i');

            // Push data to clone
            $objPhpWord->addToClone('i', 'i', $i, array('multiline' => false));
            $objPhpWord->addToClone('i', 'role', 'TN', array('multiline' => false));
            $objPhpWord->addToClone('i', 'firstname', htmlspecialchars(html_entity_decode($objEventMember->firstname)), array('multiline' => false));
            $objPhpWord->addToClone('i', 'lastname', htmlspecialchars(html_entity_decode($objEventMember->lastname)), array('multiline' => false));
            $objPhpWord->addToClone('i', 'sacMemberId', 'Mitgl. No. ' . $objEventMember->sacMemberId, array('multiline' => false));
            $objPhpWord->addToClone('i', 'isNotSacMember', $strIsActiveMember, array('multiline' => false));
            $objPhpWord->addToClone('i', 'street', htmlspecialchars(html_entity_decode($objEventMember->street)), array('multiline' => false));
            $objPhpWord->addToClone('i', 'postal', htmlspecialchars(html_entity_decode($objEventMember->postal)), array('multiline' => false));
            $objPhpWord->addToClone('i', 'city', htmlspecialchars(html_entity_decode($objEventMember->city)), array('multiline' => false));
            $objPhpWord->addToClone('i', 'mobile', htmlspecialchars(html_entity_decode($mobile)), array('multiline' => false));
            $objPhpWord->addToClone('i', 'emergencyPhone', htmlspecialchars(html_entity_decode($objEventMember->emergencyPhone)), array('multiline' => false));
            $objPhpWord->addToClone('i', 'emergencyPhoneName', htmlspecialchars(html_entity_decode($objEventMember->emergencyPhoneName)), array('multiline' => false));
            $objPhpWord->addToClone('i', 'email', htmlspecialchars(html_entity_decode($objEventMember->email)), array('multiline' => false));
            $objPhpWord->addToClone('i', 'transportInfo', htmlspecialchars(html_entity_decode($transportInfo)), array('multiline' => false));
            $objPhpWord->addToClone('i', 'dateOfBirth', $objEventMember->dateOfBirth != '' ? $dateAdapter->parse('Y', $objEventMember->dateOfBirth) : '', array('multiline' => false));
        }

        // Event instructors
        $aInstructors = $calendarEventsHelperAdapter->getInstructorsAsArray($objEvent->id, false);

        $arrInstructors = array_map(function ($id) {
            $userModelAdapter = $this->framework->getAdapter(UserModel::class);

            $objUser = $userModelAdapter->findByPk($id);
            if ($objUser !== null)
            {
                return $objUser->name;
            }
        }, $aInstructors);
        $objPhpWord->replace('eventInstructors', htmlspecialchars(html_entity_decode(implode(', ', $arrInstructors))));

        // Event Id
        $objPhpWord->replace('eventId', $objEvent->id);
    }

    /**
     * Generate event memberlist
     * @param $eventId
     * @param string $outputType
     * @throws \Exception
     */
    public function generateMemberList($eventId, $outputType = 'docx')
    {
        // Set adapters
        $configAdapter = $this->framework->getAdapter(Config::class);
        $calendarEventsModelAdapter = $this->framework->getAdapter(CalendarEventsModel::class);
        $messageAdapter = $this->framework->getAdapter(Message::class);
        $controllerAdapter = $this->framework->getAdapter(Controller::class);
        $dbafsAdapter = $this->framework->getAdapter(Dbafs::class);
        $databaseAdapter = $this->framework->getAdapter(Database::class);

        $objEvent = $calendarEventsModelAdapter->findByPk($eventId);

        if ($objEvent !== null)
        {
            $objEventMember = $databaseAdapter->getInstance()->prepare('SELECT * FROM tl_calendar_events_member WHERE eventId=? AND stateOfSubscription=?')->execute($objEvent->id, 'subscription-accepted');
            if (!$objEventMember->numRows)
            {
                // Send error message if there are no members assigned to the event
                $messageAdapter->addError('Bitte &uuml;berpr&uuml;fe die Teilnehmerliste. Es wurdem keine Teilnehmer gefunden, deren Teilname best&auml;tigt ist.');
                $controllerAdapter->redirect(System::getReferer());
            }

            // Create phpWord instance
            $filenamePattern = str_replace('%%s', '%s', $configAdapter->get('SAC_EVT_EVENT_MEMBER_LIST_FILE_NAME_PATTERN'));
            $destFile = $configAdapter->get('SAC_EVT_TEMP_PATH') . '/' . sprintf($filenamePattern, time(), 'docx');
            $objPhpWord = new MsWordTemplateProcessor($configAdapter->get('SAC_EVT_EVENT_MEMBER_LIST_TEMPLATE_SRC'), $destFile);

            // Get event data
            $this->getEventData($objPhpWord, $objEvent);

            // Member list
            $this->getEventMemberData($objPhpWord, $objEvent, $objEventMember);

            // Create temporary folder, if it not exists.
            new Folder($configAdapter->get('SAC_EVT_TEMP_PATH'));
            $dbafsAdapter->addResource($configAdapter->get('SAC_EVT_TEMP_PATH'));

            if ($outputType === 'pdf')
            {
                // Generate Docx file from template;
                $objPhpWord->generateUncached(true)
                    ->sendToBrowser(false)
                    ->generate();

                // Generate pdf
                $objConversion = new DocxToPdfConversion($destFile, $configAdapter->get('cloudconvertApiKey'));
                $objConversion->sendToBrowser(true)->createUncached(true)->convert();
            }

            if ($outputType === 'docx')
            {
                // Generate Docx file from template;
                $objPhpWord->generateUncached(true)
                    ->sendToBrowser(true)
                    ->generate();
            }

            exit();
        }
    }

}
