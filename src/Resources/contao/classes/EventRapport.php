<?php

/**
 * SAC Event Tool Web Plugin for Contao
 * Copyright (c) 2008-2017 Marko Cupic
 * @package sac-event-tool-bundle
 * @author Marko Cupic m.cupic@gmx.ch, 2017-2018
 * @link https://sac-kurse.kletterkader.com
 */

namespace Markocupic\SacEventToolBundle;


use Contao\CalendarEventsInstructorInvoiceModel;
use Contao\CalendarEventsJourneyModel;
use Contao\CalendarEventsModel;
use Contao\Config;
use Contao\Controller;
use Contao\Database;
use Contao\Date;
use Contao\Dbafs;
use Contao\File;
use Contao\Folder;
use Contao\MemberModel;
use Contao\Message;
use Contao\StringUtil;
use Contao\System;
use Contao\UserModel;
use Markocupic\SacEventToolBundle\Services\Pdf\DocxToPdfConversion;
use PhpOffice\PhpWord\CreateDocxFromTemplate;


/**
 * Class EventRapport
 * @package Markocupic\SacEventToolBundle
 */
class EventRapport
{

    /**
     * @param $invoiceId
     * @param string $outputType
     * @throws \Exception
     */
    public function generateInvoice($invoiceId, $outputType = 'docx')
    {

        $objEventInvoice = CalendarEventsInstructorInvoiceModel::findByPk($invoiceId);
        if ($objEventInvoice !== null)
        {
            // Delete tmp files older the 1 week
            // Get root dir
            $rootDir = System::getContainer()->getParameter('kernel.project_dir');
            $arrScan = scan($rootDir . '/' . Config::get('SAC_EVT_TEMP_PATH'));
            foreach ($arrScan as $file)
            {
                if (is_file($rootDir . '/' . Config::get('SAC_EVT_TEMP_PATH') . '/' . $file))
                {
                    $objFile = new File(Config::get('SAC_EVT_TEMP_PATH') . '/' . $file);
                    if ($objFile !== null)
                    {
                        if ($objFile->mtime + 60 * 60 * 24 * 7 < time())
                        {
                            $objFile->delete();
                        }
                    }
                }
            }

            $objEvent = CalendarEventsModel::findByPk($objEventInvoice->pid);
            // $objBiller "Der Rechnungssteller"
            $objBiller = UserModel::findByPk($objEventInvoice->userPid);
            if ($objEvent !== null && $objBiller !== null)
            {
                // Check if tour report has filled in
                if (!$objEvent->filledInEventReportForm)
                {
                    Message::addError('Bitte f&uuml;llen Sie den Touren-Rapport vollst&auml;ndig aus, bevor Sie das Verg&uuml;tungsformular herunterladen.');
                    Controller::redirect(System::getReferer());
                }

                $objEventMember = Database::getInstance()->prepare('SELECT * FROM tl_calendar_events_member WHERE pid=? AND hasParticipated=?')->execute($objEvent->id, '1');
                if (!$objEventMember->numRows)
                {
                    // Send error message if there are no members assigned to the event
                    Message::addError('Bitte &uuml;berpr&uuml;fe die Teilnehmerliste. Es wurdem keine Teilnehmer gefunden, die am Event teilgenommen haben.');
                    Controller::redirect(System::getReferer());
                }


                $arrData = array();

                // Page #1
                // Tour rapport
                $arrData = $this->getTourRapportData($arrData, $objEvent, $objEventMember, $objEventInvoice, $objBiller);

                // Page #1 + #2
                // Get event data
                $arrData = $this->getEventData($arrData, $objEvent);

                // Page #2
                // Member list
                $arrData = $this->getEventMemberData($arrData, $objEvent, $objEventMember);


                // Generate filename
                $container = \Contao\System::getContainer();
                $filenamePattern = str_replace('%%s', '%s', $container->getParameter('SAC_EVT_EVENT_TOUR_INVOICE_FILE_NAME_PATTERN'));
                $targetFile = Config::get('SAC_EVT_TEMP_PATH') . '/' . sprintf($filenamePattern, time(), 'docx');

                // Create temporary folder, if it not exists.
                new Folder(Config::get('SAC_EVT_TEMP_PATH'));
                Dbafs::addResource(Config::get('SAC_EVT_TEMP_PATH'));

                if ($outputType === 'pdf')
                {
                    // Generate Docx file from template;
                    CreateDocxFromTemplate::create($arrData, Config::get('SAC_EVT_EVENT_TOUR_INVOICE_TEMPLATE_SRC'), $targetFile)
                        ->generateUncached(true)
                        ->sendToBrowser(false)
                        ->generate();

                    // Generate pdf
                    DocxToPdfConversion::create($targetFile, Config::get('SAC_EVT_CLOUDCONVERT_API_KEY'))
                        ->sendToBrowser(true)
                        ->createUncached(true)
                        ->convert();
                }

                if ($outputType === 'docx')
                {
                    // Generate Docx file from template;
                    CreateDocxFromTemplate::create($arrData, Config::get('SAC_EVT_EVENT_TOUR_INVOICE_TEMPLATE_SRC'), $targetFile)
                        ->generateUncached(true)
                        ->sendToBrowser(true)
                        ->generate();
                }


                exit();
            }
        }
    }


    /**
     * @param $arrData
     * @param $objEvent
     * @param $objEventMember
     * @return array
     */
    protected function getTourRapportData($arrData, $objEvent, $objEventMember, $objEventInvoice, $objBiller)
    {

        $countParticipants = $objEventMember->numRows;
        $countInstructors = count(StringUtil::deserialize($objEvent->instructor, true));
        $countParticipantsTotal = $countParticipants + $countInstructors;


        $transport = CalendarEventsJourneyModel::findByPk($objEvent->journey) !== null ? CalendarEventsJourneyModel::findByPk($objEvent->journey)->title : 'keine Angabe';
        $arrData[] = array('key' => 'eventTransport', 'value' => htmlspecialchars(html_entity_decode($transport)));
        $arrData[] = array('key' => 'eventCanceled', 'value' => $objEvent->eventCanceled ? 'Ja' : 'Nein');
        $arrData[] = array('key' => 'eventHasExecuted', 'value' => $objEvent->tourHasExecutedLikePredicted ? 'Ja' : 'Nein');
        $substitutionText = $objEvent->tourSubstitutionText != '' ? $objEvent->tourSubstitutionText : '---';
        $arrData[] = array('key' => 'eventSubstitutionText', 'value' => htmlspecialchars(html_entity_decode($substitutionText)));
        $arrData[] = array('key' => 'eventDuration', 'value' => htmlspecialchars(html_entity_decode($objEventInvoice->eventDuration)));

        // User
        $arrData[] = array('key' => 'eventInstructorName', 'value' => htmlspecialchars(html_entity_decode($objBiller->name)));
        $arrData[] = array('key' => 'eventInstructorStreet', 'value' => htmlspecialchars(html_entity_decode($objBiller->street)));
        $arrData[] = array('key' => 'eventInstructorPostalCity', 'value' => htmlspecialchars(html_entity_decode($objBiller->postal . ' ' . $objBiller->city)));
        $arrData[] = array('key' => 'eventInstructorPhone', 'value' => htmlspecialchars(html_entity_decode($objBiller->phone)));
        $arrData[] = array('key' => 'countParticipants', 'value' => htmlspecialchars(html_entity_decode($countParticipantsTotal)));


        $arrData[] = array('key' => 'weatherConditions', 'value' => htmlspecialchars(html_entity_decode($objEvent->tourWeatherConditions)));
        $arrData[] = array('key' => 'avalancheConditions', 'value' => htmlspecialchars(html_entity_decode($objEvent->tourAvalancheConditions)));
        $arrData[] = array('key' => 'specialIncidents', 'value' => htmlspecialchars(html_entity_decode($objEvent->tourSpecialIncidents)));


        $arrFields = array('sleepingTaxes', 'sleepingTaxesText', 'miscTaxes', 'miscTaxesText', 'railwTaxes', 'railwTaxesText', 'cabelCarTaxes', 'cabelCarTaxesText', 'roadTaxes', 'carTaxesKm', 'countCars', 'phoneTaxes');
        foreach ($arrFields as $field)
        {
            $arrData[] = array('key' => $field, 'value' => htmlspecialchars(html_entity_decode($objEventInvoice->{$field})));
        }
        // Calculate car costs
        $carTaxes = 0;
        if ($objEventInvoice->countCars > 0 && $objEventInvoice->carTaxesKm > 0)
        {
            $objEventMember = Database::getInstance()->prepare('SELECT * FROM tl_calendar_events_member WHERE pid=? AND hasParticipated=?')->execute($objEvent->id, '1');
            if ($objEventMember->numRows)
            {
                $carTaxes = $objEventInvoice->countCars * 0.6 / $countParticipantsTotal * $objEventInvoice->carTaxesKm;
            }
        }

        $arrData[] = array('key' => 'carTaxes', 'value' => htmlspecialchars(html_entity_decode(round($carTaxes))));
        $totalCosts = $objEventInvoice->sleepingTaxes + $objEventInvoice->miscTaxes + $objEventInvoice->railwTaxes + $objEventInvoice->cabelCarTaxes + $objEventInvoice->roadTaxes + $objEventInvoice->phoneTaxes + $carTaxes;
        $arrData[] = array('key' => 'totalCosts', 'value' => htmlspecialchars(html_entity_decode(round($totalCosts))));

        // Notice
        $notice = $objEventInvoice->notice == '' ? '---' : $objEventInvoice->notice;
        $arrData[] = array('key' => 'notice', 'value' => htmlspecialchars(html_entity_decode($notice)));

        // Iban & account holder
        $arrData[] = array('key' => 'iban', 'value' => htmlspecialchars(html_entity_decode($objEventInvoice->iban)));
        $arrData[] = array('key' => 'accountHolder', 'value' => htmlspecialchars(html_entity_decode($objBiller->name)));

        return $arrData;

    }

    /**
     * @param $arrData
     * @param $objEvent
     * @param $objEventMember
     * @return array
     */
    protected function getEventData($arrData, $objEvent)
    {


        // Event data
        $arrData[] = array('key' => 'eventTitle', 'value' => htmlspecialchars(html_entity_decode($objEvent->title)));
        Controller::loadLanguageFile('tl_calendar_events');
        $arrEventTstamps = CalendarSacEvents::getEventTimestamps($objEvent->id);

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
            $arrEventDates[] = Date::parse($strFormat, $v);
        }
        $strEventDuration = implode(', ', $arrEventDates);


        $arrData[] = array('key' => 'eventDates', 'value' => htmlspecialchars(html_entity_decode($strEventDuration)));
        $arrData[] = array('key' => 'eventMeetingpoint', 'value' => htmlspecialchars(html_entity_decode($objEvent->meetingPoint)));
        $arrData[] = array('key' => 'eventTechDifficulties', 'value' => htmlspecialchars(html_entity_decode(implode(', ', CalendarSacEvents::getTourTechDifficultiesAsArray($objEvent->id, false)))));

        return $arrData;

    }

    /**
     * @param $arrData
     * @param $objEvent
     * @param $objEventMember
     * @return array
     */
    protected function getEventMemberData($arrData, $objEvent, $objEventMember)
    {
        $i = 0;
        $rows = array();

        // TL
        $arrInstructors = StringUtil::deserialize($objEvent->instructor, true);
        if (!empty($arrInstructors) && is_array($arrInstructors))
        {
            foreach ($arrInstructors as $userId)
            {
                $objUserModel = UserModel::findByPk($userId);
                if ($objUserModel !== null)
                {
                    $i++;
                    $rows[] = array(
                        array('key' => 'i', 'value' => $i, 'options' => array('multiline' => false)),
                        array('key' => 'role', 'value' => 'TL', 'options' => array('multiline' => false)),
                        array('key' => 'firstname', 'value' => $objUserModel->name, 'options' => array('multiline' => false)),
                        array('key' => 'lastname', 'value' => '', 'options' => array('multiline' => false)),
                        array('key' => 'sacMemberId', 'value' => 'Mitgl. No. ' . $objUserModel->sacMemberId, 'options' => array('multiline' => false)),
                        array('key' => 'isNotSacMember', 'value' => $objUserModel->isSacMember ? ' ' : '!inaktiv/kein Mitglied', 'options' => array('multiline' => false)),
                        array('key' => 'street', 'value' => $objUserModel->street, 'options' => array('multiline' => false)),
                        array('key' => 'postal', 'value' => $objUserModel->postal, 'options' => array('multiline' => false)),
                        array('key' => 'city', 'value' => $objUserModel->city, 'options' => array('multiline' => false)),
                        array('key' => 'emergencyPhone', 'value' => $objUserModel->emergencyPhone, 'options' => array('multiline' => false)),
                        array('key' => 'emergencyPhoneName', 'value' => $objUserModel->emergencyPhoneName, 'options' => array('multiline' => false)),
                        array('key' => 'phone', 'value' => $objUserModel->phone, 'options' => array('multiline' => false)),
                        array('key' => 'email', 'value' => $objUserModel->email, 'options' => array('multiline' => false)),
                        array('key' => 'dateOfBirth', 'value' => $objUserModel->dateOfBirth ? Date::parse('d.m.Y', $objUserModel->dateOfBirth) : '', 'options' => array('multiline' => false)),
                    );
                }
            }
        }

        // TN
        while ($objEventMember->next())
        {
            $i++;
            $strIsActiveMember = '!inaktiv/keinMitglied';
            if ($objEventMember->sacMemberId != '')
            {
                $objMemberModel = MemberModel::findBySacMemberId($objEventMember->sacMemberId);
                if ($objMemberModel !== null)
                {
                    if ($objMemberModel->isSacMember)
                    {
                        $strIsActiveMember = ' ';
                    }
                }
            }
            $rows[] = array(
                array('key' => 'i', 'value' => $i, 'options' => array('multiline' => false)),
                array('key' => 'role', 'value' => 'TN', 'options' => array('multiline' => false)),
                array('key' => 'firstname', 'value' => $objEventMember->firstname, 'options' => array('multiline' => false)),
                array('key' => 'lastname', 'value' => $objEventMember->lastname, 'options' => array('multiline' => false)),
                array('key' => 'sacMemberId', 'value' => 'Mitgl. No. ' . $objEventMember->sacMemberId, 'options' => array('multiline' => false)),
                array('key' => 'isNotSacMember', 'value' => $strIsActiveMember, 'options' => array('multiline' => false)),
                array('key' => 'street', 'value' => $objEventMember->street, 'options' => array('multiline' => false)),
                array('key' => 'postal', 'value' => $objEventMember->postal, 'options' => array('multiline' => false)),
                array('key' => 'city', 'value' => $objEventMember->city, 'options' => array('multiline' => false)),
                array('key' => 'phone', 'value' => $objEventMember->phone, 'options' => array('multiline' => false)),
                array('key' => 'emergencyPhone', 'value' => $objEventMember->emergencyPhone, 'options' => array('multiline' => false)),
                array('key' => 'emergencyPhoneName', 'value' => $objEventMember->emergencyPhoneName, 'options' => array('multiline' => false)),
                array('key' => 'email', 'value' => $objEventMember->email, 'options' => array('multiline' => false)),
                array('key' => 'dateOfBirth', 'value' => $objUserModel->dateOfBirth ? Date::parse('d.m.Y', $objUserModel->dateOfBirth) : '', 'options' => array('multiline' => false)),
            );
        }

        // Clone rows
        $arrData[] = array(
            'clone' => 'i',
            'rows'  => $rows,
        );

        // Event instructors
        $arrInstructors = array_map(function ($id) {
            $objUser = \UserModel::findByPk($id);
            if ($objUser !== null)
            {
                return $objUser->name;
            }
        }, StringUtil::deserialize($objEvent->instructor, true));
        $arrData[] = array('key' => 'eventInstructors', 'value' => htmlspecialchars(html_entity_decode(implode(', ', $arrInstructors))));

        // Event Id
        $arrData[] = array('key' => 'eventId', 'value' => $objEvent->id);

        return $arrData;
    }

    /**
     * Generate event memberlist
     * @param $eventId
     * @param string $outputType
     * @throws \Exception
     */
    public function generateMemberList($eventId, $outputType = 'docx')
    {

        $objEvent = CalendarEventsModel::findByPk($eventId);

        if ($objEvent !== null)
        {
            $objEventMember = Database::getInstance()->prepare('SELECT * FROM tl_calendar_events_member WHERE pid=? AND stateOfSubscription=?')->execute($objEvent->id, 'subscription-accepted');
            if (!$objEventMember->numRows)
            {
                // Send error message if there are no members assigned to the event
                Message::addError('Bitte &uuml;berpr&uuml;fe die Teilnehmerliste. Es wurdem keine Teilnehmer gefunden, deren Teilname best&auml;tigt ist.');
                Controller::redirect(System::getReferer());
            }

            $arrData = array();

            // Get event data
            $arrData = $this->getEventData($arrData, $objEvent);

            // Member list
            $arrData = $this->getEventMemberData($arrData, $objEvent, $objEventMember);

            // Generate filename
            $container = \Contao\System::getContainer();
            $filenamePattern = str_replace('%%s', '%s', $container->getParameter('SAC_EVT_EVENT_MEMBER_LIST_FILE_NAME_PATTERN'));
            $targetFile = Config::get('SAC_EVT_TEMP_PATH') . '/' . sprintf($filenamePattern, time(), 'docx');

            // Create temporary folder, if it not exists.
            new Folder(Config::get('SAC_EVT_TEMP_PATH'));
            Dbafs::addResource(Config::get('SAC_EVT_TEMP_PATH'));

            if ($outputType === 'pdf')
            {
                // Generate Docx file from template;
                CreateDocxFromTemplate::create($arrData, Config::get('SAC_EVT_EVENT_MEMBER_LIST_TEMPLATE_SRC'), $targetFile)
                    ->generateUncached(true)
                    ->sendToBrowser(false)
                    ->generate();

                // Generate pdf
                DocxToPdfConversion::create($targetFile, Config::get('SAC_EVT_CLOUDCONVERT_API_KEY'))
                    ->sendToBrowser(true)
                    ->createUncached(true)
                    ->convert();
            }

            if ($outputType === 'docx')
            {
                // Generate Docx file from template;
                CreateDocxFromTemplate::create($arrData, Config::get('SAC_EVT_EVENT_MEMBER_LIST_TEMPLATE_SRC'), $targetFile)
                    ->generateUncached(true)
                    ->sendToBrowser(true)
                    ->generate();
            }


            exit();
        }
    }


}