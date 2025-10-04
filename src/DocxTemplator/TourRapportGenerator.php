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

namespace Markocupic\SacEventToolBundle\DocxTemplator;

use Contao\CalendarEventsModel;
use Contao\CoreBundle\Framework\Adapter;
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\UserModel;
use Markocupic\CloudconvertBundle\Conversion\ConvertFile;
use Markocupic\PhpOffice\PhpWord\MsWordTemplateProcessor;
use Markocupic\SacEventToolBundle\DocxTemplator\Helper\Event;
use Markocupic\SacEventToolBundle\DocxTemplator\Helper\EventMember;
use Markocupic\SacEventToolBundle\DocxTemplator\Validator\TourRapportValidator;
use Markocupic\SacEventToolBundle\Download\BinaryFileDownload;
use Markocupic\SacEventToolBundle\Model\CalendarEventsInstructorInvoiceModel;
use Symfony\Component\Filesystem\Path;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

readonly class TourRapportGenerator
{
    private Adapter $calendarEventsModel;

    private Adapter $userModel;

    public function __construct(
        private ContaoFramework $framework,
        private BinaryFileDownload $binaryFileDownload,
        private ConvertFile $convertFile,
        private Event $docxEventHelper,
        private EventMember $docxEventMemberHelper,
        private TourRapportValidator $tourRapportValidator,
        private string $projectDir,
        private string $sacevtTempDir,
    ) {
        $this->framework->initialize();

        // Adapters
        $this->calendarEventsModel = $this->framework->getAdapter(CalendarEventsModel::class);
        $this->userModel = $this->framework->getAdapter(UserModel::class);
    }

    /**
     * This method will generate either the event report or the invoice/reimbursement
     * form as a file on the file system.
     */
    public function generate(DocumentType $documentType, CalendarEventsInstructorInvoiceModel $eventInvoice, OutputType $outputType, string $docxTemplateSrc, string $strFilenamePattern): \SplFileObject
    {
        // Will throw an exception if validation fails
        $event = $this->validateAndGetEvent($eventInvoice);
        $beneficiary = $this->userModel->findById($eventInvoice->userPid);

        $config = $this->prepareDocumentConfiguration($event, $eventInvoice, $docxTemplateSrc, $strFilenamePattern);
        $processor = $this->createDocumentProcessor($config, $documentType, $event, $eventInvoice, $beneficiary);

        return $this->generateOutput($processor, $outputType, $config->targetPathPdf, $config->docxTemplateSrc);
    }

    public function download(DocumentType $documentType, CalendarEventsInstructorInvoiceModel $eventInvoice, OutputType $outputType, string $docxTemplateSrc, string $strFilenamePattern): BinaryFileResponse
    {
        $splFileObject = $this->generate($documentType, $eventInvoice, $outputType, $docxTemplateSrc, $strFilenamePattern);

        return $this->binaryFileDownload->sendFileToBrowser($splFileObject->getRealPath(), '', false, true);
    }

    public function validate(CalendarEventsInstructorInvoiceModel $eventInvoice): void
    {
        $event = $this->calendarEventsModel->findById($eventInvoice->pid);

        $this->tourRapportValidator->checkEventExists($event);
        $this->tourRapportValidator->checkEventRapportHasFilledOutFully($eventInvoice);
        $this->tourRapportValidator->checkEventHasConfirmedParticipatedMembers($event);
        $this->tourRapportValidator->checkBeneficiaryExists($eventInvoice);
    }

    public function validateAndGetEvent(CalendarEventsInstructorInvoiceModel $eventInvoice): CalendarEventsModel
    {
        $event = $this->calendarEventsModel->findById($eventInvoice->pid);

        // Will throw an exception if validation fails
        $this->validate($eventInvoice);

        return $event;
    }

    private function prepareDocumentConfiguration(CalendarEventsModel $event, CalendarEventsInstructorInvoiceModel $eventInvoice, string $docxTemplateSrc, string $strFilenamePattern): object
    {
        $docxTemplateSrc = Path::makeAbsolute($docxTemplateSrc, $this->projectDir);
        $fileName = \sprintf($strFilenamePattern, $event->id.'_'.$eventInvoice->userPid, 'docx');
        $targetPathDocx = Path::makeAbsolute(Path::join($this->sacevtTempDir, $fileName), $this->projectDir);
        $targetPathPdf = str_replace('.docx', '.pdf', $targetPathDocx);

        return (object) [
            'docxTemplateSrc' => $docxTemplateSrc,
            'targetPathDocx' => $targetPathDocx,
            'targetPathPdf' => $targetPathPdf,
        ];
    }

    private function createDocumentProcessor(object $config, DocumentType $documentType, CalendarEventsModel $event, CalendarEventsInstructorInvoiceModel $eventInvoice, UserModel $beneficiary): MsWordTemplateProcessor
    {
        $processor = new MsWordTemplateProcessor($config->docxTemplateSrc, $config->targetPathDocx);

        // Page #1 Tour rapport
        $this->docxEventHelper->setTourRapportData($processor, $event, $eventInvoice, $beneficiary);

        // Page #1 + #2 Event data
        $this->docxEventHelper->setEventData($processor, $event);

        // Page #2 Member list
        if (DocumentType::RAPPORT === $documentType) {
            $participatedMembers = $this->docxEventMemberHelper->getParticipatedEventMembers($event);
            $this->docxEventMemberHelper->setEventMemberData($processor, $event, $participatedMembers);
        }

        return $processor;
    }

    private function generateOutput(MsWordTemplateProcessor $processor, OutputType $outputType, string $targetPathPdf, string $docxTemplateSrc): \SplFileObject
    {
        if (OutputType::PDF === $outputType) {
            return $this->generatePdfOutput($processor, $targetPathPdf, $docxTemplateSrc);
        }

        return $processor->generate();
    }

    private function generatePdfOutput(MsWordTemplateProcessor $processor, string $targetPathPdf, string $docxTemplateSrc): \SplFileObject
    {
        // Use the cached version of the PDF file if...
        // - data has not been changed and
        // - no changes have been made to the template
        $hashCode = hash('md5', json_encode($processor->getData()).hash_file('md5', $docxTemplateSrc));

        // Generate a DOCX file from the template
        $docxFile = $processor->generate();

        // Generate the PDF document
        return $this->convertFile
            ->file($docxFile->getRealPath())
            ->uncached(false)
            ->setCacheHashCode($hashCode)
            ->convertTo(OutputType::PDF->value, $targetPathPdf)
        ;
    }
}
