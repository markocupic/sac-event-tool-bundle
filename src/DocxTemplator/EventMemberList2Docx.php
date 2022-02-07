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
use Contao\Config;
use Contao\Controller;
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

/**
 * Class EventMemberList2Docx.
 */
class EventMemberList2Docx
{
    private ContaoFramework $framework;
    private ConvertFile $convertFile;
    private string $tempDir;
    private string $projectDir;

    public function __construct(ContaoFramework $framework, ConvertFile $convertFile, string $tempDir, string $projectDir)
    {
        $this->framework = $framework;
        $this->convertFile = $convertFile;
        $this->tempDir = $tempDir;
        $this->projectDir = $projectDir;

        // Initialize contao framework
        $this->framework->initialize();
    }

    /**
     * @throws CopyFileException
     * @throws CreateTemporaryFileException
     */
    public function generate(CalendarEventsModel $objEvent, string $outputType = 'docx'): void
    {
        /** @var Config $configAdapter */
        $configAdapter = $this->framework->getAdapter(Config::class);

        /** @var Message $messageAdapter */
        $messageAdapter = $this->framework->getAdapter(Message::class);

        /** @var Controller $controllerAdapter */
        $controllerAdapter = $this->framework->getAdapter(Controller::class);

        /** @var CalendarEventsMemberModel $calendarEventsMemberModelAdapter */
        $calendarEventsMemberModelAdapter = $this->framework->getAdapter(CalendarEventsMemberModel::class);

        $objEventMember = $calendarEventsMemberModelAdapter->findBy(
            [
                'tl_calendar_events_member.eventId=?',
                'tl_calendar_events_member.stateOfSubscription=?',
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
            $messageAdapter->addError('Bitte überprüfe die Teilnehmerliste. Es wurdem keine Teilnehmer gefunden, deren Teilname best&auml;tigt ist.');
            $controllerAdapter->redirect(System::getReferer());
        }

        // Create phpWord instance
        $filenamePattern = str_replace('%%s', '%s', $configAdapter->get('SAC_EVT_EVENT_MEMBER_LIST_FILE_NAME_PATTERN'));
        $destFile = $this->tempDir.'/'.sprintf($filenamePattern, time(), 'docx');
        $objPhpWord = new MsWordTemplateProcessor((string) $configAdapter->get('SAC_EVT_EVENT_MEMBER_LIST_TEMPLATE_SRC'), $destFile);

        // Get event data
        /** @var Event $objEventHelper */
        $objEventHelper = System::getContainer()->get('Markocupic\SacEventToolBundle\DocxTemplator\Helper\Event');
        $objEventHelper->setEventData($objPhpWord, $objEvent);

        // Member list
        /** @var EventMember $objEventMemberHelper */
        $objEventMemberHelper = System::getContainer()->get('Markocupic\SacEventToolBundle\DocxTemplator\Helper\EventMember');
        $objEventMemberHelper->setEventMemberData($objPhpWord, $objEvent, $objEventMember);

        if ('pdf' === $outputType) {
            // Generate Docx file from template;
            $objPhpWord->generateUncached(true)
                ->sendToBrowser(false, true)
                ->generate()
            ;

            // Generate pdf
            $this->convertFile
                ->file($this->projectDir.'/'.$destFile)
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

        exit();
    }
}
