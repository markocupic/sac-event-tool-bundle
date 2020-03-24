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

use Contao\CalendarEventsInstructorInvoiceModel;
use Contao\CalendarEventsModel;
use Contao\Config;
use Contao\Controller;
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\Dbafs;
use Contao\File;
use Contao\Folder;
use Contao\Message;
use Contao\System;
use Contao\UserModel;
use Markocupic\CloudconvertBundle\Services\DocxToPdfConversion;
use Markocupic\PhpOffice\PhpWord\MsWordTemplateProcessor;
use Markocupic\SacEventToolBundle\DocxTemplator\Helper\Event;
use Markocupic\SacEventToolBundle\DocxTemplator\Helper\EventMember;

/**
 * Class EventRapport2Docx
 * @package Markocupic\SacEventToolBundle\DocxTemplator
 */
class EventRapport2Docx
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
     * @param string $type
     * @param CalendarEventsInstructorInvoiceModel $objEventInvoice
     * @param string $outputType
     * @param string $templateSRC
     * @param string $strFilenamePattern
     * @throws \PhpOffice\PhpWord\Exception\CopyFileException
     * @throws \PhpOffice\PhpWord\Exception\CreateTemporaryFileException
     */
    public function generate(string $type, CalendarEventsInstructorInvoiceModel $objEventInvoice, string $outputType = 'docx', string $templateSRC, string $strFilenamePattern): void
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

        /** @var Event $objEventHelper */
        $objEventHelper = System::getContainer()->get('Markocupic\SacEventToolBundle\DocxTemplator\Helper\Event');

        /** @var EventMember $objEventMemberHelper */
        $objEventMemberHelper = System::getContainer()->get('Markocupic\SacEventToolBundle\DocxTemplator\Helper\EventMember');

        /** @var CalendarEventsModel $objEvent */
        $objEvent = $calendarEventsModelAdapter->findByPk($objEventInvoice->pid);

        // Delete old tmp files
        $this->deleteOldTempFiles();

        if (!$objEventHelper->checkEventRapportHasFilledInCorrectly($objEventInvoice))
        {
            $messageAdapter->addError('Bitte f&uuml;llen Sie den Touren-Rapport vollst&auml;ndig aus, bevor Sie das Verg&uuml;tungsformular herunterladen.');
            $controllerAdapter->redirect(System::getReferer());
        }

        if ($objEventMemberHelper->getParticipatedEventMembers($objEvent) === null)
        {
            // Send error message if there are no members assigned to the event
            $messageAdapter->addError('Bitte &uuml;berpr&uuml;fe die Teilnehmerliste. Es wurdem keine Teilnehmer gefunden, die am Event teilgenommen haben.');
            $controllerAdapter->redirect(System::getReferer());
        }

        // $objBiller "Der Rechnungssteller"
        $objBiller = $userModelAdapter->findByPk($objEventInvoice->userPid);
        if ($objEvent !== null && $objBiller !== null)
        {
            $filenamePattern = str_replace('%%s', '%s', $strFilenamePattern);
            $destFilename = $configAdapter->get('SAC_EVT_TEMP_PATH') . '/' . sprintf($filenamePattern, time(), 'docx');
            $strTemplateSrc = (string) $templateSRC;
            $objPhpWord = new MsWordTemplateProcessor($strTemplateSrc, $destFilename);

            // Page #1
            // Tour rapport
            $objEventHelper->setTourRapportData($objPhpWord, $objEvent, $objEventInvoice, $objBiller);

            // Page #1 + #2
            // Get event data
            $objEventHelper->setEventData($objPhpWord, $objEvent);

            // Page #2
            // Member list
            if ($type === 'rapport')
            {
                $objEventMemberHelper->setEventMemberData($objPhpWord, $objEvent, $objEventMemberHelper->getParticipatedEventMembers($objEvent));
            }

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
                $objConversion = new DocxToPdfConversion($destFilename, (string) $configAdapter->get('cloudconvertApiKey'));
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
