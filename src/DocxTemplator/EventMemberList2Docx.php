<?php

declare(strict_types=1);

/**
 * SAC Event Tool Web Plugin for Contao
 * Copyright (c) 2008-2020 Marko Cupic
 * @package sac-event-tool-bundle
 * @author Marko Cupic m.cupic@gmx.ch, 2017-2020
 * @link https://github.com/markocupic/sac-event-tool-bundle
 */

namespace Markocupic\SacEventToolBundle\DocxTemplator;

use Contao\CalendarEventsMemberModel;
use Contao\CalendarEventsModel;
use Contao\Config;
use Contao\Controller;
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\Dbafs;
use Contao\File;
use Contao\Folder;
use Contao\Message;
use Contao\System;
use Markocupic\CloudconvertBundle\Services\DocxToPdfConversion;
use Markocupic\PhpOffice\PhpWord\MsWordTemplateProcessor;
use Markocupic\SacEventToolBundle\DocxTemplator\Helper\Event;
use Markocupic\SacEventToolBundle\DocxTemplator\Helper\EventMember;

/**
 * Class EventMemberList2Docx
 * @package Markocupic\SacEventToolBundle\DocxTemplator
 */
class EventMemberList2Docx
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
     * EventRapport constructor.
     * @param ContaoFramework $framework
     * @param string $projectDir
     */
    public function __construct(ContaoFramework $framework, string $projectDir)
    {
        $this->framework = $framework;
        $this->projectDir = $projectDir;

        // Initialize contao framework
        $this->framework->initialize();
    }

    /**
     * @param CalendarEventsModel $objEvent
     * @param string $outputType
     * @throws \PhpOffice\PhpWord\Exception\CopyFileException
     * @throws \PhpOffice\PhpWord\Exception\CreateTemporaryFileException
     */
    public function generate(CalendarEventsModel $objEvent, string $outputType = 'docx'): void
    {
        // Set adapters
        /** @var  Config $configAdapter */
        $configAdapter = $this->framework->getAdapter(Config::class);
        /** @var  Message $messageAdapter */
        $messageAdapter = $this->framework->getAdapter(Message::class);
        /** @var  Controller $controllerAdapter */
        $controllerAdapter = $this->framework->getAdapter(Controller::class);
        /** @var  Dbafs $dbafsAdapter */
        $dbafsAdapter = $this->framework->getAdapter(Dbafs::class);
        /** @var  CalendarEventsMemberModel $calendarEventsMemberModelAdapter */
        $calendarEventsMemberModelAdapter = $this->framework->getAdapter(CalendarEventsMemberModel::class);

        // Delete old tmp files
        $this->deleteOldTempFiles();

        $objEventMember = $calendarEventsMemberModelAdapter->findBy(
            [
                'tl_calendar_events_member.eventId=?',
                'tl_calendar_events_member.stateOfSubscription=?'
            ],
            [
                $objEvent->id,
                'subscription-accepted'
            ],
            [
                'order' => 'tl_calendar_events_member.lastname, tl_calendar_events_member.firstname'
            ]
        );

        if ($objEventMember === null)
        {
            // Send error message if there are no members assigned to the event
            $messageAdapter->addError('Bitte &uuml;berpr&uuml;fe die Teilnehmerliste. Es wurdem keine Teilnehmer gefunden, deren Teilname best&auml;tigt ist.');
            $controllerAdapter->redirect(System::getReferer());
        }

        // Create phpWord instance
        $filenamePattern = str_replace('%%s', '%s', $configAdapter->get('SAC_EVT_EVENT_MEMBER_LIST_FILE_NAME_PATTERN'));
        $destFile = $configAdapter->get('SAC_EVT_TEMP_PATH') . '/' . sprintf($filenamePattern, time(), 'docx');
        $objPhpWord = new MsWordTemplateProcessor((string) $configAdapter->get('SAC_EVT_EVENT_MEMBER_LIST_TEMPLATE_SRC'), $destFile);

        // Get event data
        /** @var Event $objEventHelper */
        $objEventHelper = System::getContainer()->get('Markocupic\SacEventToolBundle\DocxTemplator\Helper\Event');
        $objEventHelper->setEventData($objPhpWord, $objEvent);

        // Member list
        /** @var EventMember $objEventMemberHelper */
        $objEventMemberHelper = System::getContainer()->get('Markocupic\SacEventToolBundle\DocxTemplator\Helper\EventMember');
        $objEventMemberHelper->setEventMemberData($objPhpWord, $objEvent, $objEventMember);

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
            $objConversion = new DocxToPdfConversion($destFile, (string) $configAdapter->get('cloudconvertApiKey'));
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

    /**
     * @throws \Exception
     */
    protected function deleteOldTempFiles(): void
    {
        /** @var  Config $configAdapter */
        $configAdapter = $this->framework->getAdapter(Config::class);

        // Delete tmp files older the 1 week
        $arrScan = scan($this->projectDir . '/' . $configAdapter->get('SAC_EVT_TEMP_PATH'));
        foreach ($arrScan as $file)
        {
            if (is_file($this->projectDir . '/' . $configAdapter->get('SAC_EVT_TEMP_PATH') . '/' . $file))
            {
                $objFile = new File($configAdapter->get('SAC_EVT_TEMP_PATH') . '/' . $file);
                if ($objFile !== null)
                {
                    if ((int) $objFile->mtime + 60 * 60 * 24 * 7 < time())
                    {
                        $objFile->delete();
                    }
                }
            }
        }
    }

}
