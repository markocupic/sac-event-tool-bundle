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

namespace Markocupic\SacEventToolBundle\DocxTemplator;

use Contao\CalendarEventsModel;
use Contao\CoreBundle\Exception\RedirectResponseException;
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\Message;
use Contao\System;
use Contao\UserModel;
use Doctrine\DBAL\Exception;
use Markocupic\CloudconvertBundle\Conversion\ConvertFile;
use Markocupic\PhpOffice\PhpWord\MsWordTemplateProcessor;
use Markocupic\SacEventToolBundle\Config\EventState;
use Markocupic\SacEventToolBundle\DocxTemplator\Helper\Event;
use Markocupic\SacEventToolBundle\DocxTemplator\Helper\EventMember;
use Markocupic\SacEventToolBundle\Download\BinaryFileDownload;
use Markocupic\SacEventToolBundle\Model\CalendarEventsInstructorInvoiceModel;
use PhpOffice\PhpWord\Exception\CopyFileException;
use PhpOffice\PhpWord\Exception\CreateTemporaryFileException;
use Symfony\Component\HttpFoundation\Response;

class EventRapport2Docx
{
    public const OUTPUT_TYPE_PDF = 'pdf';
    public const OUTPUT_TYPE_DOCX = 'docx';

    public const DOCUMENT_TYPE_RAPPORT = 'rapport';
    public const DOCUMENT_TYPE_INVOICE = 'invoice';

    public function __construct(
        private readonly ContaoFramework $framework,
        private readonly BinaryFileDownload $binaryFileDownload,
        private readonly ConvertFile $convertFile,
        private readonly Event $docxEventHelper,
        private readonly EventMember $docxEventMemberHelper,
        private readonly string $projectDir,
        private readonly string $sacevtTempDir,
    ) {
        $this->framework->initialize();
    }

    /**
     * This method will generate either
     * the event report or the invoice/reimbursement form
     * as a file on the file system.
     *
     * @throws CopyFileException
     * @throws CreateTemporaryFileException
     * @throws Exception
     */
    public function generateDocument(string $documentType, CalendarEventsInstructorInvoiceModel $objEventInvoice, string $outputType, string $templateSRC, string $strFilenamePattern): \SplFileObject
    {
        // Set adapters
        /** @var CalendarEventsModel $calendarEventsModelAdapter */
        $calendarEventsModelAdapter = $this->framework->getAdapter(CalendarEventsModel::class);

        /** @var UserModel $userModelAdapter */
        $userModelAdapter = $this->framework->getAdapter(UserModel::class);

        /** @var Message $messageAdapter */
        $messageAdapter = $this->framework->getAdapter(Message::class);

        /** @var CalendarEventsModel $objEvent */
        $objEvent = $calendarEventsModelAdapter->findByPk($objEventInvoice->pid);

        if (null === $objEvent) {
            throw new \Exception(sprintf('Event with ID %d not found.', $objEventInvoice->pid));
        }

        if (!$this->docxEventHelper->checkEventRapportHasFilledInCorrectly($objEventInvoice)) {
            $messageAdapter->addError('Bitte füllen Sie den Tourrapport vollständig aus, bevor Sie das Vergütungsformular herunterladen.');

            throw new RedirectResponseException(System::getReferer());
        }

        if (EventState::STATE_CANCELED !== $objEvent->eventState && null === $this->docxEventMemberHelper->getParticipatedEventMembers($objEvent)) {
            // Send error message if there are no members assigned to the event
            $messageAdapter->addError('Bitte überprüfe die Teilnehmerliste. Es wurden keine Teilnehmer gefunden, die am Event teilgenommen haben. Falls du den Event abgesagt hast, musst du dies unter Event Status beim Event selber vermerken.');

            throw new RedirectResponseException(System::getReferer());
        }

        // "Zahlungsempfänger"
        $objPaymentRecipient = $userModelAdapter->findByPk($objEventInvoice->userPid);

        if (null === $objPaymentRecipient) {
            throw new \Exception(sprintf('User with ID %d not found.', $objEventInvoice->userPid));
        }

        $filenamePattern = str_replace('%%s', '%s', $strFilenamePattern);
        $destFilename = $this->sacevtTempDir.'/'.sprintf($filenamePattern, time(), 'docx');

        $objPhpWord = new MsWordTemplateProcessor($templateSRC, $destFilename);

        // Page #1
        // Tour rapport
        $this->docxEventHelper->setTourRapportData($objPhpWord, $objEvent, $objEventInvoice, $objPaymentRecipient);

        // Page #1 + #2
        // Get event data
        $this->docxEventHelper->setEventData($objPhpWord, $objEvent);

        // Page #2
        // Member list
        if (self::DOCUMENT_TYPE_RAPPORT === $documentType) {
            $this->docxEventMemberHelper->setEventMemberData($objPhpWord, $objEvent, $this->docxEventMemberHelper->getParticipatedEventMembers($objEvent));
        }

        if (self::OUTPUT_TYPE_PDF === $outputType) {
            // Generate Docx file from template;
            $objPhpWord->generateUncached(true)
                ->generate()
            ;

            // Generate pdf document and send it to the browser
            $this->convertFile
                ->file($this->projectDir.'/'.$destFilename)
                ->uncached(true)
                ->convertTo('pdf')
                ;

            $destFilename = str_replace('.docx', '.pdf', $destFilename);

            return new \SplFileObject($this->projectDir.'/'.$destFilename);
        }

        if (self::OUTPUT_TYPE_DOCX === $outputType) {
            // Generate docx document from template and send it to the browser;
            $objPhpWord->generateUncached(true)
                ->generate()
                ;

            return new \SplFileObject($this->projectDir.'/'.$destFilename);
        }

        throw new \LogicException(sprintf('Invalid output Type "%s". Type must be "%s" or "%s".', self::OUTPUT_TYPE_DOCX, self::OUTPUT_TYPE_PDF, $outputType));
    }

    /**
     * This method will generate either
     * the event report or the invoice/reimbursement form
     * as a file on the file system
     * and finally send this file to the browser.
     *
     * @throws CopyFileException
     * @throws CreateTemporaryFileException
     * @throws Exception
     */
    public function downloadDocument(string $documentType, CalendarEventsInstructorInvoiceModel $objEventInvoice, string $outputType, string $templateSRC, string $strFilenamePattern): Response
    {
        $objFile = $this->generateDocument($documentType, $objEventInvoice, $outputType, $templateSRC, $strFilenamePattern);

        return $this->binaryFileDownload->sendFileToBrowser($objFile->getRealPath(), '', false);
    }
}
