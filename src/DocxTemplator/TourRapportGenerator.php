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
use Contao\CoreBundle\Framework\Adapter;
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\Message;
use Contao\System;
use Contao\UserModel;
use Markocupic\CloudconvertBundle\Conversion\ConvertFile;
use Markocupic\PhpOffice\PhpWord\MsWordTemplateProcessor;
use Markocupic\SacEventToolBundle\Config\EventState;
use Markocupic\SacEventToolBundle\DocxTemplator\Helper\Event;
use Markocupic\SacEventToolBundle\DocxTemplator\Helper\EventMember;
use Markocupic\SacEventToolBundle\Download\BinaryFileDownload;
use Markocupic\SacEventToolBundle\Model\CalendarEventsInstructorInvoiceModel;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Filesystem\Path;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class TourRapportGenerator
{
    public const OUTPUT_TYPE_PDF = 'pdf';
    public const OUTPUT_TYPE_DOCX = 'docx';
    public const DOCUMENT_TYPE_RAPPORT = 'rapport';
    public const DOCUMENT_TYPE_INVOICE = 'invoice';

    private Adapter $calendarEventsModel;
    private Adapter $userModel;
    private Adapter $message;

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

        // Adapters
        $this->calendarEventsModel = $this->framework->getAdapter(CalendarEventsModel::class);
        $this->userModel = $this->framework->getAdapter(UserModel::class);
        $this->message = $this->framework->getAdapter(Message::class);
    }

    /**
     * This method will generate either
     * the event report or the invoice/reimbursement form
     * as a file on the file system.
     */
    public function generate(string $documentType, CalendarEventsInstructorInvoiceModel $eventInvoice, string $outputType, string $templateSRC, string $strFilenamePattern): \SplFileObject
    {
        $event = $this->calendarEventsModel->findByPk($eventInvoice->pid);

        if (null === $event) {
            throw new \Exception(sprintf('Event with ID %d not found.', $eventInvoice->pid));
        }

        if (!$this->docxEventHelper->checkEventRapportHasFilledInCorrectly($eventInvoice)) {
            $this->message->addError('Bitte füllen Sie den Tourrapport vollständig aus, bevor Sie das Vergütungsformular herunterladen.');

            throw new RedirectResponseException(System::getReferer());
        }

        if (EventState::STATE_CANCELED !== $event->eventState && null === $this->docxEventMemberHelper->getParticipatedEventMembers($event)) {
            $this->message->addError('Bitte überprüfe die Teilnehmerliste. Es wurden keine Teilnehmer gefunden, die am Event teilgenommen haben. Falls du den Event abgesagt hast, musst du dies unter Event Status beim Event selber vermerken.');

            throw new RedirectResponseException(System::getReferer());
        }

        // "Zahlungsempfänger"
        $beneficiary = $this->userModel->findByPk($eventInvoice->userPid);

        if (null === $beneficiary) {
            throw new \Exception(sprintf('User with ID %d not found.', $eventInvoice->userPid));
        }

        $fileName = sprintf($strFilenamePattern, $event->id.'_'.$eventInvoice->userPid, 'docx');
        $targetPathDocx = $this->projectDir.'/'.$this->sacevtTempDir.'/'.$fileName;
        $targetPathPdf = str_replace('.docx', '.pdf', $targetPathDocx);

        $phpWord = new MsWordTemplateProcessor($templateSRC, Path::makeRelative($targetPathDocx, $this->projectDir));

        // Page #1
        // Tour rapport
        $this->docxEventHelper->setTourRapportData($phpWord, $event, $eventInvoice, $beneficiary);

        // Page #1 + #2
        // Event data
        $this->docxEventHelper->setEventData($phpWord, $event);

        // Page #2
        // Member list
        if (self::DOCUMENT_TYPE_RAPPORT === $documentType) {
            $this->docxEventMemberHelper->setEventMemberData($phpWord, $event, $this->docxEventMemberHelper->getParticipatedEventMembers($event));
        }

        if (self::OUTPUT_TYPE_PDF === $outputType) {
            // Use the cached version of the PDF file, if...
            // - data has not been changed and
            // - no changes have been made to the template
            $hash = hash('md5', json_encode($phpWord->getData()).hash_file('md5', $this->projectDir.'/'.$templateSRC));

            // Create cache dirs
            $pdfCacheDir = $this->projectDir.'/system/tmp/cloudconvert/pdf';
            $docxCacheDir = $this->projectDir.'/system/tmp/cloudconvert/docx';

            $fs = new Filesystem();
            $fs->mkdir([$pdfCacheDir, $docxCacheDir]);

            if (is_file($pdfCacheDir.'/'.$hash) && is_file($docxCacheDir.'/'.$hash)) {
                $fs->copy($pdfCacheDir.'/'.$hash, $targetPathPdf, true);

                // Return the cached version of the PDF file.
                return new \SplFileObject($targetPathPdf);
            }

            // Generate DOCX file from template;
            $phpWord->generateUncached(true)
                ->generate()
            ;

            // Copy the DOCX version of the file to the cache.
            $fs->copy($targetPathDocx, $docxCacheDir.'/'.$hash, true);

            // Generate the PDF document
            $this->convertFile
                ->sendToBrowser(false)
                ->file($targetPathDocx)
                ->uncached(true)
                ->convertTo(self::OUTPUT_TYPE_PDF)
                ;

            // Copy the PDF version of the file to the cache.
            $fs->copy($targetPathPdf, $pdfCacheDir.'/'.$hash, true);

            return new \SplFileObject($targetPathPdf);
        }

        if (self::OUTPUT_TYPE_DOCX === $outputType) {
            // Generate the DOCX version
            $phpWord->generateUncached(true)
                ->generate()
                ;

            return new \SplFileObject($targetPathDocx);
        }

        throw new \LogicException(sprintf('Invalid output Type "%s". Type must be "%s" or "%s".', self::OUTPUT_TYPE_DOCX, self::OUTPUT_TYPE_PDF, $outputType));
    }

    public function download(string $documentType, CalendarEventsInstructorInvoiceModel $eventInvoice, string $outputType, string $templateSRC, string $strFilenamePattern): BinaryFileResponse
    {
        $splFileObject = $this->generate($documentType, $eventInvoice, $outputType, $templateSRC, $strFilenamePattern);

        return $this->binaryFileDownload->sendFileToBrowser($splFileObject->getRealPath(), '', false, true);
    }
}
