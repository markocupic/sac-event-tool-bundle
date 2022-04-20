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

use Contao\CalendarEventsMemberModel;
use Contao\CalendarEventsModel;
use Contao\Controller;
use Contao\CoreBundle\Framework\Adapter;
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\Message;
use Contao\System;
use Markocupic\CloudconvertBundle\Conversion\ConvertFile;
use Markocupic\PhpOffice\PhpWord\MsWordTemplateProcessor;
use Markocupic\SacEventToolBundle\Config\EventSubscriptionLevel;
use Markocupic\SacEventToolBundle\DocxTemplator\Helper\Event;
use Markocupic\SacEventToolBundle\DocxTemplator\Helper\EventMember;
use PhpOffice\PhpWord\Exception\CopyFileException;
use PhpOffice\PhpWord\Exception\CreateTemporaryFileException;

class EventMemberList2Docx
{
    private ContaoFramework $framework;
    private Event $eventHelper;
    private EventMember $eventMemberHelper;
    private ConvertFile $convertFile;
    private string $tempDir;
    private string $projectDir;
    private string $eventMemberListTemplate;
    private string $eventMemberListFileNamePattern;

    // Adapters
    private Adapter $calendarEventsMemberModelAdapter;
    private Adapter $controllerAdapter;
    private Adapter $messageAdapter;

    public function __construct(ContaoFramework $framework, Event $eventHelper, EventMember $eventMemberHelper, ConvertFile $convertFile, string $tempDir, string $projectDir, string $eventMemberListTemplate, string $eventMemberListFileNamePattern)
    {
        $this->framework = $framework;
        $this->eventHelper = $eventHelper;
        $this->eventMemberHelper = $eventMemberHelper;
        $this->convertFile = $convertFile;
        $this->tempDir = $tempDir;
        $this->projectDir = $projectDir;
        $this->eventMemberListTemplate = $eventMemberListTemplate;
        $this->eventMemberListFileNamePattern = $eventMemberListFileNamePattern;

        // Adapters
        $this->calendarEventsMemberModelAdapter = $this->framework->getAdapter(CalendarEventsMemberModel::class);
        $this->controllerAdapter = $this->framework->getAdapter(Controller::class);
        $this->messageAdapter = $this->framework->getAdapter(Message::class);

        // Initialize contao framework
        $this->framework->initialize();
    }

    /**
     * @throws CopyFileException
     * @throws CreateTemporaryFileException
     */
    public function generate(CalendarEventsModel $objEvent, string $outputType = 'docx'): void
    {
        $objEventMember = $this->calendarEventsMemberModelAdapter->findBy(
            [
                'tl_calendar_events_member.eventId = ?',
                'tl_calendar_events_member.stateOfSubscription = ?',
            ],
            [
                $objEvent->id,
                EventSubscriptionLevel::SUBSCRIPTION_ACCEPTED,
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

        // Create phpWord instance
        $targetFilePath = $this->tempDir.'/'.sprintf($this->eventMemberListFileNamePattern, time(), 'docx');
        $objPhpWord = new MsWordTemplateProcessor($this->eventMemberListTemplate, $targetFilePath);

        // Get event data
        $this->eventHelper->setEventData($objPhpWord, $objEvent);

        // Member list
        $this->eventMemberHelper->setEventMemberData($objPhpWord, $objEvent, $objEventMember);

        if ('pdf' === $outputType) {
            // Generate Docx file from template;
            $objPhpWord->generateUncached(true)
                ->sendToBrowser(false, true)
                ->generate()
            ;

            // Generate pdf
            $this->convertFile
                ->file($this->projectDir.'/'.$targetFilePath)
                ->sendToBrowser(true, true)
                ->uncached(true)
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

        throw new \Exception('No output type defined. The output type must be "docx" or "pdf".');
    }
}
