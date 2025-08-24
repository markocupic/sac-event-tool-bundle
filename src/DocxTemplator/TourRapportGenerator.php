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
use Markocupic\SacEventToolBundle\Config\EventState;
use Markocupic\SacEventToolBundle\DocxTemplator\Exception\TourRapportGeneratorException;
use Markocupic\SacEventToolBundle\DocxTemplator\Helper\Event;
use Markocupic\SacEventToolBundle\DocxTemplator\Helper\EventMember;
use Markocupic\SacEventToolBundle\Download\BinaryFileDownload;
use Markocupic\SacEventToolBundle\Model\CalendarEventsInstructorInvoiceModel;
use Symfony\Component\Filesystem\Path;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Contracts\Translation\TranslatorInterface;

class TourRapportGenerator
{
    private Adapter $calendarEventsModel;

    private Adapter $userModel;

    public function __construct(
        private readonly ContaoFramework $framework,
        private readonly BinaryFileDownload $binaryFileDownload,
        private readonly ConvertFile $convertFile,
        private readonly Event $docxEventHelper,
        private readonly EventMember $docxEventMemberHelper,
        private readonly TranslatorInterface $translator,
        private readonly string $projectDir,
        private readonly string $sacevtTempDir,
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
        $event = $this->calendarEventsModel->findById($eventInvoice->pid);

        if (null === $event) {
            throw new TourRapportGeneratorException(\sprintf('Event with ID %d not found.', $eventInvoice->pid), $this->translator->trans('ERR.evt_strn_event_not_found', [$eventInvoice->pid], 'contao_default'));
        }

        if (!$this->docxEventHelper->checkEventRapportHasFilledInCorrectly($eventInvoice)) {
            throw new TourRapportGeneratorException('The tour rapport has not been filled out correctly.', $this->translator->trans('ERR.evt_strn_eventRapportNotFilledOutCorrectly', [], 'contao_default'));
        }

        if (EventState::STATE_CANCELED !== $event->eventState && null === $this->docxEventMemberHelper->getParticipatedEventMembers($event)) {
            throw new TourRapportGeneratorException('The event has no members whose participation has been confirmed.', $this->translator->trans('ERR.evt_strn_eventHasNoMember', [], 'contao_default'));
        }

        // "Zahlungsempfänger"
        $beneficiary = $this->userModel->findById($eventInvoice->userPid);

        if (null === $beneficiary) {
            throw new TourRapportGeneratorException(\sprintf('User with ID %d not found.', $eventInvoice->userPid), $this->translator->trans('ERR.evt_strn_user_not_found', [$eventInvoice->userPid], 'contao_default'));
        }

        $docxTemplateSrc = Path::makeAbsolute($docxTemplateSrc, $this->projectDir);

        $fileName = \sprintf($strFilenamePattern, $event->id.'_'.$eventInvoice->userPid, 'docx');
        $targetPathDocx = Path::makeAbsolute(Path::join($this->sacevtTempDir, $fileName), $this->projectDir);
        $targetPathPdf = str_replace('.docx', '.pdf', $targetPathDocx);

        $objPhpWord = new MsWordTemplateProcessor($docxTemplateSrc, $targetPathDocx);

        // Page #1 Tour rapport
        $this->docxEventHelper->setTourRapportData($objPhpWord, $event, $eventInvoice, $beneficiary);

        // Page #1 + #2 Event data
        $this->docxEventHelper->setEventData($objPhpWord, $event);

        // Page #2 Member list
        if (DocumentType::RAPPORT === $documentType) {
            $this->docxEventMemberHelper->setEventMemberData($objPhpWord, $event, $this->docxEventMemberHelper->getParticipatedEventMembers($event));
        }

        if (OutputType::PDF === $outputType) {
            // Use the cached version of the PDF file, if...
            // - data has not been changed and
            // - no changes have been made to the template
            $hashCode = hash('md5', json_encode($objPhpWord->getData()).hash_file('md5', $docxTemplateSrc));

            // Generate DOCX file from template;
            $objSplFileDocx = $objPhpWord->generate();

            // Generate the PDF document
            return $this->convertFile
                ->file($objSplFileDocx->getRealPath())
                ->uncached(false)
                ->setCacheHashCode($hashCode)
                ->convertTo($outputType->value, $targetPathPdf)
            ;
        }

        // OutputType::DOCX === $outputType
        return $objPhpWord->generate();
    }

    public function download(DocumentType $documentType, CalendarEventsInstructorInvoiceModel $eventInvoice, OutputType $outputType, string $docxTemplateSrc, string $strFilenamePattern): BinaryFileResponse
    {
        $splFileObject = $this->generate($documentType, $eventInvoice, $outputType, $docxTemplateSrc, $strFilenamePattern);

        return $this->binaryFileDownload->sendFileToBrowser($splFileObject->getRealPath(), '', false, true);
    }
}
