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
use Markocupic\CloudconvertBundle\Conversion\ConvertFile;
use Markocupic\PhpOffice\PhpWord\MsWordTemplateProcessor;
use Markocupic\SacEventToolBundle\DocxTemplator\Helper\Event;
use Markocupic\SacEventToolBundle\DocxTemplator\Helper\EventMember;
use PhpOffice\PhpWord\Exception\CopyFileException;
use PhpOffice\PhpWord\Exception\CreateTemporaryFileException;

/**
 * Class EventRapport2Docx.
 */
class EventRapport2Docx
{

    private ContaoFramework $framework;
    private ConvertFile $convertFile;
    private string $projectDir;

    /**
     * EventRapport constructor.
     */
    public function __construct(ContaoFramework $framework, ConvertFile $convertFile, string $projectDir)
    {
        $this->framework = $framework;
        $this->convertFile = $convertFile;
        $this->projectDir = $projectDir;

        // Initialize contao framework
        $this->framework->initialize();
    }

    /**
     * @throws CopyFileException
     * @throws CreateTemporaryFileException
     */
    public function generate(string $type, CalendarEventsInstructorInvoiceModel $objEventInvoice, string $outputType, string $templateSRC, string $strFilenamePattern): void
    {
        // Set adapters
        /** @var Config $configAdapter */
        $configAdapter = $this->framework->getAdapter(Config::class);
        /** @var CalendarEventsModel CalendarEventsModel $calendarEventsModelAdapter */
        $calendarEventsModelAdapter = $this->framework->getAdapter(CalendarEventsModel::class);
        /** @var UserModel $userModelAdapter */
        $userModelAdapter = $this->framework->getAdapter(UserModel::class);
        /** @var Message $messageAdapter */
        $messageAdapter = $this->framework->getAdapter(Message::class);
        /** @var Controller $controllerAdapter */
        $controllerAdapter = $this->framework->getAdapter(Controller::class);
        /** @var Dbafs $dbafsAdapter */
        $dbafsAdapter = $this->framework->getAdapter(Dbafs::class);

        /** @var Event $objEventHelper */
        $objEventHelper = System::getContainer()->get('Markocupic\SacEventToolBundle\DocxTemplator\Helper\Event');

        /** @var EventMember $objEventMemberHelper */
        $objEventMemberHelper = System::getContainer()->get('Markocupic\SacEventToolBundle\DocxTemplator\Helper\EventMember');

        /** @var CalendarEventsModel $objEvent */
        $objEvent = $calendarEventsModelAdapter->findByPk($objEventInvoice->pid);

        // Delete old tmp files
        $this->deleteOldTempFiles();

        if (!$objEventHelper->checkEventRapportHasFilledInCorrectly($objEventInvoice)) {
            $messageAdapter->addError('Bitte f&uuml;llen Sie den Touren-Rapport vollst&auml;ndig aus, bevor Sie das Verg&uuml;tungsformular herunterladen.');
            $controllerAdapter->redirect(System::getReferer());
        }

        if (null === $objEventMemberHelper->getParticipatedEventMembers($objEvent)) {
            // Send error message if there are no members assigned to the event
            $messageAdapter->addError('Bitte &uuml;berpr&uuml;fe die Teilnehmerliste. Es wurdem keine Teilnehmer gefunden, die am Event teilgenommen haben.');
            $controllerAdapter->redirect(System::getReferer());
        }

        // $objBiller "Der Rechnungssteller"
        $objBiller = $userModelAdapter->findByPk($objEventInvoice->userPid);

        if (null !== $objEvent && null !== $objBiller) {
            $filenamePattern = str_replace('%%s', '%s', $strFilenamePattern);
            $destFilename = $configAdapter->get('SAC_EVT_TEMP_PATH').'/'.sprintf($filenamePattern, time(), 'docx');
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
            if ('rapport' === $type) {
                $objEventMemberHelper->setEventMemberData($objPhpWord, $objEvent, $objEventMemberHelper->getParticipatedEventMembers($objEvent));
            }

            // Create temporary folder, if it not exists.
            new Folder($configAdapter->get('SAC_EVT_TEMP_PATH'));
            $dbafsAdapter->addResource($configAdapter->get('SAC_EVT_TEMP_PATH'));

            if ('pdf' === $outputType) {
                // Generate Docx file from template;
                $objPhpWord->generateUncached(true)
                    ->sendToBrowser(false)
                    ->generate()
                ;

                // Generate pdf
                $this->convertFile
                    ->file(new File($destFilename))
                    ->uncached(true)
                    ->sendToBrowser(true)
                    ->convertTo('pdf')
                    ;
            }

            if ('docx' === $outputType) {
                // Generate Docx file from template;
                $objPhpWord->generateUncached(true)
                    ->sendToBrowser(true)
                    ->generate()
                ;
            }

            exit();
        }
    }

    /**
     * @throws \Exception
     */
    protected function deleteOldTempFiles(): void
    {
        /** @var Config $configAdapter */
        $configAdapter = $this->framework->getAdapter(Config::class);

        // Delete tmp files older the 1 week
        $arrScan = scan($this->projectDir.'/'.$configAdapter->get('SAC_EVT_TEMP_PATH'));

        foreach ($arrScan as $file) {
            if (is_file($this->projectDir.'/'.$configAdapter->get('SAC_EVT_TEMP_PATH').'/'.$file)) {
                $objFile = new File($configAdapter->get('SAC_EVT_TEMP_PATH').'/'.$file);

                if (null !== $objFile) {
                    if ((int) $objFile->mtime + 60 * 60 * 24 * 7 < time()) {
                        $objFile->delete();
                    }
                }
            }
        }
    }
}
