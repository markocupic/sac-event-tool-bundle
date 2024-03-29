<?php

declare(strict_types=1);

/*
 * This file is part of SAC Event Tool Bundle.
 *
 * (c) Marko Cupic 2024 <m.cupic@gmx.ch>
 * @license GPL-3.0-or-later
 * For the full copyright and license information,
 * please view the LICENSE file that was distributed with this source code.
 * @link https://github.com/markocupic/sac-event-tool-bundle
 */

namespace Markocupic\SacEventToolBundle\DocxTemplator;

use Contao\CalendarEventsModel;
use Contao\Controller;
use Contao\CoreBundle\Framework\Adapter;
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\Message;
use Contao\System;
use Markocupic\CloudconvertBundle\Conversion\ConvertFile;
use Markocupic\PhpOffice\PhpWord\MsWordTemplateProcessor;
use Markocupic\SacEventToolBundle\Config\EventSubscriptionState;
use Markocupic\SacEventToolBundle\DocxTemplator\Helper\Event;
use Markocupic\SacEventToolBundle\DocxTemplator\Helper\EventMember;
use Markocupic\SacEventToolBundle\Download\BinaryFileDownload;
use Markocupic\SacEventToolBundle\Model\CalendarEventsMemberModel;
use Symfony\Component\Filesystem\Path;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class EventRegistrationListGeneratorDocx
{
    // Adapters
    private Adapter $calendarEventsMemberModelAdapter;
    private Adapter $controllerAdapter;
    private Adapter $messageAdapter;

    public function __construct(
        private readonly ContaoFramework $framework,
        private readonly BinaryFileDownload $binaryFileDownload,
        private readonly Event $eventHelper,
        private readonly EventMember $eventMemberHelper,
        private readonly ConvertFile $convertFile,
        private readonly string $sacevtTempDir,
        private readonly string $projectDir,
        private readonly string $sacevtEventTemplateMemberList,
        private readonly string $sacevtEventMemberListFileNamePattern,
    ) {
        // Adapters
        $this->calendarEventsMemberModelAdapter = $this->framework->getAdapter(CalendarEventsMemberModel::class);
        $this->controllerAdapter = $this->framework->getAdapter(Controller::class);
        $this->messageAdapter = $this->framework->getAdapter(Message::class);

        // Initialize contao framework
        $this->framework->initialize();
    }

    public function generate(CalendarEventsModel $objEvent, string $outputType = 'docx'): BinaryFileResponse
    {
        $objEventMember = $this->calendarEventsMemberModelAdapter->findBy(
            [
                'tl_calendar_events_member.eventId = ?',
                'tl_calendar_events_member.stateOfSubscription = ?',
            ],
            [
                $objEvent->id,
                EventSubscriptionState::SUBSCRIPTION_ACCEPTED,
            ],
            [
                'order' => 'tl_calendar_events_member.lastname, tl_calendar_events_member.firstname',
            ]
        );

        if (null === $objEventMember) {
            // Send error message if there are no members assigned to the event
            $this->messageAdapter->addError('Bitte überprüfe die Teilnehmerliste. Es wurdem keine Teilnehmer gefunden, deren Teilname bestätigt ist.');
            $this->controllerAdapter->redirect(System::getReferer());
        }

        $docxTemplateSrc = Path::makeAbsolute($this->sacevtEventTemplateMemberList, $this->projectDir);

        $targetFilePath = $this->sacevtTempDir.'/'.sprintf($this->sacevtEventMemberListFileNamePattern, time(), 'docx');
        $targetFilePath = Path::makeAbsolute($targetFilePath, $this->projectDir);

        // Create PhpWord instance
        $objPhpWord = new MsWordTemplateProcessor($docxTemplateSrc, $targetFilePath);

        // Get event data
        $this->eventHelper->setEventData($objPhpWord, $objEvent);

        // Member list
        $this->eventMemberHelper->setEventMemberData($objPhpWord, $objEvent, $objEventMember);

        if ('pdf' === $outputType) {
            // Generate Docx file from template;
            $objSplFileDocx = $objPhpWord->generate();

            // Generate pdf
            $objSplFilePdf = $this->convertFile
                ->file($objSplFileDocx->getRealPath())
                ->uncached(true)
                ->convertTo('pdf')
            ;

            return $this->binaryFileDownload->sendFileToBrowser($objSplFilePdf->getRealPath(), '', true, true);
        }

        if ('docx' === $outputType) {
            // Generate Docx file from template;
            $objSplFileDocx = $objPhpWord->generate();

            return $this->binaryFileDownload->sendFileToBrowser($objSplFileDocx->getRealPath(), '', true, true);
        }

        throw new \LogicException('No output type defined. Please define the output type either "docx" or "pdf".');
    }
}
