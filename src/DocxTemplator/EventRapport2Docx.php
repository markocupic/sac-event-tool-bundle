<?php

declare(strict_types=1);

/*
 * This file is part of SAC Event Tool Bundle.
 *
 * (c) Marko Cupic 2022 <m.cupic@gmx.ch>
 * @license GPL-3.0-or-later
 * For the full copyright and license information,
 * please view the LICENSE file that was distributed with this source code.
 * @link https://github.com/markocupic/sac-event-tool-bundle
 */

namespace Markocupic\SacEventToolBundle\DocxTemplator;

use Contao\CalendarEventsInstructorInvoiceModel;
use Contao\CalendarEventsModel;
use Contao\Controller;
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\Message;
use Contao\System;
use Contao\UserModel;
use Markocupic\CloudconvertBundle\Conversion\ConvertFile;
use Markocupic\PhpOffice\PhpWord\MsWordTemplateProcessor;
use Markocupic\SacEventToolBundle\Config\EventState;
use Markocupic\SacEventToolBundle\DocxTemplator\Helper\Event;
use Markocupic\SacEventToolBundle\DocxTemplator\Helper\EventMember;
use PhpOffice\PhpWord\Exception\CopyFileException;
use PhpOffice\PhpWord\Exception\CreateTemporaryFileException;

class EventRapport2Docx
{
    private ContaoFramework $framework;
    private ConvertFile $convertFile;
    private string $projectDir;
    private string $tempDir;

    /**
     * EventRapport constructor.
     */
    public function __construct(ContaoFramework $framework, ConvertFile $convertFile, string $projectDir, string $tempDir)
    {
        $this->framework = $framework;
        $this->convertFile = $convertFile;
        $this->projectDir = $projectDir;
        $this->tempDir = $tempDir;

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
        /** @var CalendarEventsModel CalendarEventsModel $calendarEventsModelAdapter */
        $calendarEventsModelAdapter = $this->framework->getAdapter(CalendarEventsModel::class);
        /** @var UserModel $userModelAdapter */
        $userModelAdapter = $this->framework->getAdapter(UserModel::class);
        /** @var Message $messageAdapter */
        $messageAdapter = $this->framework->getAdapter(Message::class);
        /** @var Controller $controllerAdapter */
        $controllerAdapter = $this->framework->getAdapter(Controller::class);

        /** @var Event $objEventHelper */
        $objEventHelper = System::getContainer()->get('Markocupic\SacEventToolBundle\DocxTemplator\Helper\Event');

        /** @var EventMember $objEventMemberHelper */
        $objEventMemberHelper = System::getContainer()->get('Markocupic\SacEventToolBundle\DocxTemplator\Helper\EventMember');

        /** @var CalendarEventsModel $objEvent */
        $objEvent = $calendarEventsModelAdapter->findByPk($objEventInvoice->pid);

        if (!$objEventHelper->checkEventRapportHasFilledInCorrectly($objEventInvoice)) {
            $messageAdapter->addError('Bitte füllen Sie den Tourenrapport vollständig aus, bevor Sie das Vergütungsformular herunterladen.');
            $controllerAdapter->redirect(System::getReferer());
        }

        if (EventState::STATE_CANCELED !== $objEvent->eventState && null === $objEventMemberHelper->getParticipatedEventMembers($objEvent)) {
            // Send error message if there are no members assigned to the event
            $messageAdapter->addError('Bitte überprüfe die Teilnehmerliste. Es wurdem keine Teilnehmer gefunden, die am Event teilgenommen haben. Falls du den Event abgesagt hast, musst du dies unter Event Status beim Event selber vermerken.');
            $controllerAdapter->redirect(System::getReferer());
        }

        // $objBiller "Der Rechnungssteller"
        $objBiller = $userModelAdapter->findByPk($objEventInvoice->userPid);

        if (null !== $objEvent && null !== $objBiller) {
            $filenamePattern = str_replace('%%s', '%s', $strFilenamePattern);
            $destFilename = $this->tempDir.'/'.sprintf($filenamePattern, time(), 'docx');

            $objPhpWord = new MsWordTemplateProcessor($templateSRC, $destFilename);

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

            if ('pdf' === $outputType) {
                // Generate Docx file from template;
                $objPhpWord->generateUncached(true)
                    ->sendToBrowser(false)
                    ->generate()
                ;

                // Generate pdf
                $this->convertFile
                    ->file($this->projectDir.'/'.$destFilename)
                    ->uncached(true)
                    ->sendToBrowser(true, true)
                    ->convertTo('pdf')
                    ;
            }

            if ('docx' === $outputType) {
                // Generate Docx file from template;
                $objPhpWord->generateUncached(true)
                    ->sendToBrowser(true, false)
                    ->generate()
                ;
            }

            throw new \Exception(sprintf('Invalid output Type "%s"', $outputType));
        }
    }
}
