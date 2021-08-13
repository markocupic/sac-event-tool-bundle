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

namespace Markocupic\SacEventToolBundle\EventRapport;

use Contao\CalendarEventsInstructorInvoiceModel;
use Contao\CalendarEventsJourneyModel;
use Contao\CalendarEventsMemberModel;
use Contao\CalendarEventsModel;
use Contao\Config;
use Contao\Controller;
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\Database;
use Contao\Date;
use Contao\Dbafs;
use Contao\EventOrganizerModel;
use Contao\File;
use Contao\Folder;
use Contao\MemberModel;
use Contao\Message;
use Contao\Model\Collection;
use Contao\StringUtil;
use Contao\System;
use Contao\UserModel;
use Markocupic\CloudconvertBundle\Services\DocxToPdfConversion;
use Markocupic\PhpOffice\PhpWord\MsWordTemplateProcessor;
use Markocupic\SacEventToolBundle\CalendarEventsHelper;
use Markocupic\SacEventToolBundle\Config\EventSubscriptionLevel;
use PhpOffice\PhpWord\Exception\CopyFileException;
use PhpOffice\PhpWord\Exception\CreateTemporaryFileException;

/**
 * Class EventRapport.
 */
class EventRapport
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
     */
    public function __construct(ContaoFramework $framework, string $projectDir)
    {
        $this->framework = $framework;
        $this->projectDir = $projectDir;

        // Initialize contao framework
        $this->framework->initialize();
    }

    /**
     * @param string $outputType
     *
     * @throws CopyFileException
     * @throws CreateTemporaryFileException
     */
    public function runExport(string $type, CalendarEventsInstructorInvoiceModel $objEventInvoice, $outputType, string $templateSRC, string $strFilenamePattern): void
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

        $objEvent = $calendarEventsModelAdapter->findByPk($objEventInvoice->pid);

        // Delete old tmp files
        $this->deleteOldTempFiles();

        if (!$this->checkEventRapportHasFillesInCorrectly($objEventInvoice)) {
            $messageAdapter->addError('Bitte f&uuml;llen Sie den Touren-Rapport vollst&auml;ndig aus, bevor Sie das Verg&uuml;tungsformular herunterladen.');
            $controllerAdapter->redirect(System::getReferer());
        }

        if (null === $this->getParticipatedEventMembers($objEvent)) {
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
            $this->getTourRapportData($objPhpWord, $objEvent, $objEventInvoice, $objBiller);

            // Page #1 + #2
            // Get event data
            $this->getEventData($objPhpWord, $objEvent);

            // Page #2
            // Member list
            if ('rapport' === $type) {
                $this->getEventMemberData($objPhpWord, $objEvent, $this->getParticipatedEventMembers($objEvent));
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
                $objConversion = new DocxToPdfConversion($destFilename, (string) $configAdapter->get('cloudconvertApiKey'));
                $objConversion->sendToBrowser(true)->createUncached(true)->convert();
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
     * Generate event memberlist.
     *
     * @param $eventId
     * @param string $outputType
     *
     * @throws \Exception
     */
    public function generateMemberList($eventId, $outputType = 'docx'): void
    {
        // Set adapters
        /** @var Config $configAdapter */
        $configAdapter = $this->framework->getAdapter(Config::class);
        /** @var CalendarEventsModel $calendarEventsModelAdapter */
        $calendarEventsModelAdapter = $this->framework->getAdapter(CalendarEventsModel::class);
        /** @var Message $messageAdapter */
        $messageAdapter = $this->framework->getAdapter(Message::class);
        /** @var Controller $controllerAdapter */
        $controllerAdapter = $this->framework->getAdapter(Controller::class);
        /** @var Dbafs $dbafsAdapter */
        $dbafsAdapter = $this->framework->getAdapter(Dbafs::class);
        /** @var CalendarEventsMemberModel $calendarEventsMemberModelAdapter */
        $calendarEventsMemberModelAdapter = $this->framework->getAdapter(CalendarEventsMemberModel::class);

        $objEvent = $calendarEventsModelAdapter->findByPk($eventId);

        if (null !== $objEvent) {
            $objEventMember = $calendarEventsMemberModelAdapter->findBy(
                ['tl_calendar_events_member.eventId=?', 'tl_calendar_events_member.stateOfSubscription=?'],
                [$objEvent->id, EventSubscriptionLevel::SUBSCRIPTION_ACCEPTED],
                ['order' => 'tl_calendar_events_member.lastname , tl_calendar_events_member.firstname']
            );

            if (null === $objEventMember) {
                // Send error message if there are no members assigned to the event
                $messageAdapter->addError('Bitte &uuml;berpr&uuml;fe die Teilnehmerliste. Es wurdem keine Teilnehmer gefunden, deren Teilname best&auml;tigt ist.');
                $controllerAdapter->redirect(System::getReferer());
            }

            // Create phpWord instance
            $filenamePattern = str_replace('%%s', '%s', $configAdapter->get('SAC_EVT_EVENT_MEMBER_LIST_FILE_NAME_PATTERN'));
            $destFile = $configAdapter->get('SAC_EVT_TEMP_PATH').'/'.sprintf($filenamePattern, time(), 'docx');
            $objPhpWord = new MsWordTemplateProcessor((string) $configAdapter->get('SAC_EVT_EVENT_MEMBER_LIST_TEMPLATE_SRC'), $destFile);

            // Get event data
            $this->getEventData($objPhpWord, $objEvent);

            // Member list
            $this->getEventMemberData($objPhpWord, $objEvent, $objEventMember);

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
                $objConversion = new DocxToPdfConversion($destFile, (string) $configAdapter->get('cloudconvertApiKey'));
                $objConversion->sendToBrowser(true)->createUncached(true)->convert();
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
     * @param $objPhpWord
     * @param $objEventInvoice
     * @param $objBiller
     */
    protected function getTourRapportData(MsWordTemplateProcessor $objPhpWord, CalendarEventsModel $objEvent, $objEventInvoice, $objBiller): void
    {
        // Set adapters
        /** @var Controller $controllerAdapter */
        $controllerAdapter = $this->framework->getAdapter(Controller::class);
        /** @var Database $databaseAdapter */
        $databaseAdapter = $this->framework->getAdapter(Database::class);
        /** @var CalendarEventsHelper $calendarEventsHelperAdapter */
        $calendarEventsHelperAdapter = $this->framework->getAdapter(CalendarEventsHelper::class);
        /** @var CalendarEventsJourneyModel $calendarEventsJourneyModel */
        $calendarEventsJourneyModel = $this->framework->getAdapter(CalendarEventsJourneyModel::class);
        /** @var UserModel $userModel */
        $userModel = $this->framework->getAdapter(UserModel::class);

        $controllerAdapter->loadLanguageFile('tl_calendar_events');

        $countFemale = 0;
        $countMale = 0;

        // Count participants
        $objEventMember = $this->getParticipatedEventMembers($objEvent);

        if (null !== $objEventMember) {
            while ($objEventMember->next()) {
                if ('female' === $objEventMember->gender) {
                    ++$countFemale;
                } else {
                    ++$countMale;
                }
            }
            // Reset Contao model collection
            $objEventMember->reset();
        }

        $countParticipants = $countFemale + $countMale;

        // Count instructors
        $arrInstructors = $calendarEventsHelperAdapter->getInstructorsAsArray($objEvent, false);
        $countInstructors = \count($arrInstructors);
        $objUser = $userModel->findMultipleByIds($arrInstructors);

        if (null !== $objUser) {
            while ($objUser->next()) {
                if ('female' === $objUser->gender) {
                    ++$countFemale;
                } else {
                    ++$countMale;
                }
            }
        }

        $countParticipantsTotal = $countInstructors + $countParticipants;

        $transport = null !== $calendarEventsJourneyModel->findByPk($objEvent->journey) ? $calendarEventsJourneyModel->findByPk($objEvent->journey)->title : 'keine Angabe';
        $objPhpWord->replace('eventTransport', $this->prepareString($transport));
        $objPhpWord->replace('eventCanceled', 'event_canceled' === $objEvent->eventState || 'event_canceled' === $objEvent->executionState ? 'Ja' : 'Nein');
        $objPhpWord->replace('eventHasExecuted', 'event_executed_like_predicted' === $objEvent->executionState ? 'Ja' : 'Nein');
        $substitutionText = '' !== $objEvent->eventSubstitutionText ? $objEvent->eventSubstitutionText : '---';
        $objPhpWord->replace('eventSubstitutionText', $this->prepareString($substitutionText));
        $objPhpWord->replace('eventDuration', $this->prepareString($objEventInvoice->eventDuration));

        // User
        $objPhpWord->replace('eventInstructorName', $this->prepareString($objBiller->name));
        $objPhpWord->replace('eventInstructorStreet', $this->prepareString($objBiller->street));
        $objPhpWord->replace('eventInstructorPostalCity', $this->prepareString($objBiller->postal.' '.$objBiller->city));
        $objPhpWord->replace('eventInstructorPhone', $this->prepareString($objBiller->phone));
        $objPhpWord->replace('countParticipants', $this->prepareString($countParticipants + $countInstructors));
        $objPhpWord->replace('countMale', $this->prepareString($countMale));
        $objPhpWord->replace('countFemale', $this->prepareString($countFemale));

        $objPhpWord->replace('weatherConditions', $this->prepareString($objEvent->tourWeatherConditions));
        $objPhpWord->replace('avalancheConditions', $this->prepareString($GLOBALS['TL_LANG']['tl_calendar_events'][$objEvent->tourAvalancheConditions][0]));
        $objPhpWord->replace('specialIncidents', $this->prepareString($objEvent->tourSpecialIncidents));

        $arrFields = ['sleepingTaxes', 'sleepingTaxesText', 'miscTaxes', 'miscTaxesText', 'railwTaxes', 'railwTaxesText', 'cabelCarTaxes', 'cabelCarTaxesText', 'roadTaxes', 'carTaxesKm', 'countCars', 'phoneTaxes'];

        foreach ($arrFields as $field) {
            $objPhpWord->replace($field, $this->prepareString($objEventInvoice->{$field}));
        }

        // Calculate car costs
        $carTaxes = 0;

        if ($objEventInvoice->countCars > 0 && $objEventInvoice->carTaxesKm > 0) {
            $objEventMember = $databaseAdapter->getInstance()->prepare('SELECT * FROM tl_calendar_events_member WHERE eventId=? AND hasParticipated=?')->execute($objEvent->id, '1');

            if ($objEventMember->numRows) {
                // ((CHF 0.60 x AnzKm + Park-/Strassen-/Tunnelgebühren) x AnzAutos) : AnzPersonen
                $carTaxes = (0.6 * $objEventInvoice->carTaxesKm + $objEventInvoice->roadTaxes) * $objEventInvoice->countCars / $countParticipantsTotal;
            }
        }

        $objPhpWord->replace('carTaxes', $this->prepareString(round($carTaxes, 2)));
        $totalCosts = $objEventInvoice->sleepingTaxes + $objEventInvoice->miscTaxes + $objEventInvoice->railwTaxes + $objEventInvoice->cabelCarTaxes + $objEventInvoice->phoneTaxes + $carTaxes;
        $objPhpWord->replace('totalCosts', $this->prepareString(round($totalCosts, 2)));

        // Notice
        $notice = empty($objEventInvoice->notice) ? '---' : $objEventInvoice->notice;
        $objPhpWord->replace('notice', $this->prepareString($notice), ['multiline' => true]);

        // eventReportAdditionalNotices
        $eventReportAdditionalNotices = empty($objEvent->eventReportAdditionalNotices) ? '---' : $objEvent->eventReportAdditionalNotices;
        $objPhpWord->replace('eventReportAdditionalNotices', $this->prepareString($eventReportAdditionalNotices), ['multiline' => true]);

        // Iban & account holder
        $objPhpWord->replace('iban', $this->prepareString($objEventInvoice->iban));
        $objPhpWord->replace('accountHolder', $this->prepareString($objBiller->name));

        // Printing date
        $objPhpWord->replace('printingDate', Date::parse('d.m.Y'));
    }

    /**
     * @param string $string
     */
    protected function prepareString($string = ''): string
    {
        if (null === $string) {
            return '';
        }

        return htmlspecialchars(html_entity_decode((string) $string));
    }

    protected function getEventData(MsWordTemplateProcessor $objPhpWord, CalendarEventsModel $objEvent): void
    {
        // Set adapters
        /** @var Controller $controllerAdapter */
        $controllerAdapter = $this->framework->getAdapter(Controller::class);
        /** @var StringUtil $stringUtilAdapter */
        $stringUtilAdapter = $this->framework->getAdapter(StringUtil::class);
        /** @var Date $dateAdapter */
        $dateAdapter = $this->framework->getAdapter(Date::class);
        /** @var EventOrganizerModel $eventOrganizerModelAdapter */
        $eventOrganizerModelAdapter = $this->framework->getAdapter(EventOrganizerModel::class);
        /** @var CalendarEventsHelper $calendarEventsHelperAdapter */
        $calendarEventsHelperAdapter = $this->framework->getAdapter(CalendarEventsHelper::class);

        // Event data
        $objPhpWord->replace('eventTitle', $this->prepareString($objEvent->title));
        $controllerAdapter->loadLanguageFile('tl_calendar_events');
        $arrEventTstamps = $calendarEventsHelperAdapter->getEventTimestamps($objEvent);

        if ('course' === $objEvent->eventType) {
            $objPhpWord->replace('courseId', $this->prepareString('Kurs-Nr: '.$objEvent->courseId));
        } else {
            $objPhpWord->replace('courseId', '');
        }

        // Generate event duration string
        $arrEventDates = [];

        foreach ($arrEventTstamps as $i => $v) {
            if (\count($arrEventTstamps) - 1 === $i) {
                $strFormat = 'd.m.Y';
            } else {
                $strFormat = 'd.m.';
            }
            $arrEventDates[] = $dateAdapter->parse($strFormat, $v);
        }
        $strEventDuration = implode(', ', $arrEventDates);

        // Get tour profile
        $arrTourProfile = $calendarEventsHelperAdapter->getTourProfileAsArray($objEvent);
        $strTourProfile = implode("\r\n", $arrTourProfile);
        $strTourProfile = str_replace('Tag: ', 'Tag:'."\r\n", $strTourProfile);

        // emergencyConcept
        $arrEmergencyConcept = [];
        $arrOrganizers = $stringUtilAdapter->deserialize($objEvent->organizers, true);

        foreach ($arrOrganizers as $organizer) {
            $objOrganizer = $eventOrganizerModelAdapter->findByPk($organizer);
            $arrEmergencyConcept[] = $objOrganizer->title.":\r\n".$objOrganizer->emergencyConcept;
        }
        $strEmergencyConcept = implode("\r\n\r\n", $arrEmergencyConcept);

        $objPhpWord->replace('eventDates', $this->prepareString($strEventDuration));
        $objPhpWord->replace('eventMeetingpoint', $this->prepareString($objEvent->meetingPoint));
        $objPhpWord->replace('eventTechDifficulties', $this->prepareString(implode(', ', $calendarEventsHelperAdapter->getTourTechDifficultiesAsArray($objEvent, false))));
        $objPhpWord->replace('eventEquipment', $this->prepareString($objEvent->equipment), ['multiline' => true]);
        $objPhpWord->replace('eventTourProfile', $this->prepareString($strTourProfile), ['multiline' => true]);
        $objPhpWord->replace('emergencyConcept', $this->prepareString($strEmergencyConcept), ['multiline' => true]);
        $objPhpWord->replace('eventMiscellaneous', $this->prepareString($objEvent->miscellaneous), ['multiline' => true]);
    }

    protected function getEventMemberData(MsWordTemplateProcessor $objPhpWord, CalendarEventsModel $objEvent, Collection $objEventMember): void
    {
        // Set adapters
        /** @var UserModel $userModelAdapter */
        $userModelAdapter = $this->framework->getAdapter(UserModel::class);
        /** @var MemberModel $memberModelAdapter */
        $memberModelAdapter = $this->framework->getAdapter(MemberModel::class);
        /** @var $dateAdapter */
        $dateAdapter = $this->framework->getAdapter(Date::class);
        /** @var CalendarEventsHelper $calendarEventsHelperAdapter */
        $calendarEventsHelperAdapter = $this->framework->getAdapter(CalendarEventsHelper::class);

        $i = 0;

        // TL
        $arrInstructors = $calendarEventsHelperAdapter->getInstructorsAsArray($objEvent, false);

        if (!empty($arrInstructors) && \is_array($arrInstructors)) {
            foreach ($arrInstructors as $userId) {
                $objUserModel = $userModelAdapter->findByPk($userId);

                if (null !== $objUserModel) {
                    // Check club membership
                    $isMember = false;
                    $objMember = $memberModelAdapter->findOneBySacMemberId($objUserModel->sacMemberId);

                    if (null !== $objMember) {
                        if ($objMember->isSacMember && !$objMember->disable) {
                            $isMember = true;
                        }
                    }
                    // Keep this var empty
                    $transportInfo = '';

                    // Phone
                    $mobile = '' !== $objUserModel->mobile ? $objUserModel->mobile : '----';

                    ++$i;

                    // Clone row
                    $objPhpWord->createClone('i');

                    // Push data to clone
                    $objPhpWord->addToClone('i', 'i', $i, ['multiline' => false]);
                    $objPhpWord->addToClone('i', 'role', 'TL', ['multiline' => false]);
                    $objPhpWord->addToClone('i', 'firstname', $this->prepareString($objUserModel->firstname), ['multiline' => false]);
                    $objPhpWord->addToClone('i', 'lastname', $this->prepareString($objUserModel->lastname), ['multiline' => false]);
                    $objPhpWord->addToClone('i', 'sacMemberId', 'Mitgl. No. '.$objUserModel->sacMemberId, ['multiline' => false]);
                    $objPhpWord->addToClone('i', 'isNotSacMember', $isMember ? ' ' : '!inaktiv/kein Mitglied', ['multiline' => false]);
                    $objPhpWord->addToClone('i', 'street', $this->prepareString($objUserModel->street), ['multiline' => false]);
                    $objPhpWord->addToClone('i', 'postal', $this->prepareString($objUserModel->postal), ['multiline' => false]);
                    $objPhpWord->addToClone('i', 'city', $this->prepareString($objUserModel->city), ['multiline' => false]);
                    $objPhpWord->addToClone('i', 'emergencyPhone', $this->prepareString($objUserModel->emergencyPhone), ['multiline' => false]);
                    $objPhpWord->addToClone('i', 'emergencyPhoneName', $this->prepareString($objUserModel->emergencyPhoneName), ['multiline' => false]);
                    $objPhpWord->addToClone('i', 'mobile', $this->prepareString($mobile), ['multiline' => false]);
                    $objPhpWord->addToClone('i', 'email', $this->prepareString($objUserModel->email), ['multiline' => false]);
                    $objPhpWord->addToClone('i', 'transportInfo', $this->prepareString($transportInfo), ['multiline' => false]);
                    $objPhpWord->addToClone('i', 'dateOfBirth', '' !== $objUserModel->dateOfBirth ? $dateAdapter->parse('Y', $objUserModel->dateOfBirth) : '', ['multiline' => false]);
                }
            }
        }

        // TN
        if (null !== $objEventMember) {
            while ($objEventMember->next()) {
                ++$i;

                // Check club membership
                $strIsActiveMember = '!inaktiv/keinMitglied';

                if ('' !== $objEventMember->sacMemberId) {
                    $objMemberModel = $memberModelAdapter->findOneBySacMemberId($objEventMember->sacMemberId);

                    if (null !== $objMemberModel) {
                        if ($objMemberModel->isSacMember && !$objMemberModel->disable) {
                            $strIsActiveMember = ' ';
                        }
                    }
                }

                $transportInfo = '';

                if (\strlen($objEventMember->carInfo)) {
                    if ((int) $objEventMember->carInfo > 0) {
                        $transportInfo .= sprintf(' Auto mit %s Plätzen', $objEventMember->carInfo);
                    }
                }

                // GA, Halbtax, Tageskarte
                if (\strlen($objEventMember->ticketInfo)) {
                    $transportInfo .= sprintf(' Ticket: Mit %s', $objEventMember->ticketInfo);
                }

                // Phone
                $mobile = '' !== $objEventMember->mobile ? $objEventMember->mobile : '----';
                // Clone row
                $objPhpWord->createClone('i');

                // Push data to clone
                $objPhpWord->addToClone('i', 'i', $i, ['multiline' => false]);
                $objPhpWord->addToClone('i', 'role', 'TN', ['multiline' => false]);
                $objPhpWord->addToClone('i', 'firstname', $this->prepareString($objEventMember->firstname), ['multiline' => false]);
                $objPhpWord->addToClone('i', 'lastname', $this->prepareString($objEventMember->lastname), ['multiline' => false]);
                $objPhpWord->addToClone('i', 'sacMemberId', 'Mitgl. No. '.$objEventMember->sacMemberId, ['multiline' => false]);
                $objPhpWord->addToClone('i', 'isNotSacMember', $strIsActiveMember, ['multiline' => false]);
                $objPhpWord->addToClone('i', 'street', $this->prepareString($objEventMember->street), ['multiline' => false]);
                $objPhpWord->addToClone('i', 'postal', $this->prepareString($objEventMember->postal), ['multiline' => false]);
                $objPhpWord->addToClone('i', 'city', $this->prepareString($objEventMember->city), ['multiline' => false]);
                $objPhpWord->addToClone('i', 'mobile', $this->prepareString($mobile), ['multiline' => false]);
                $objPhpWord->addToClone('i', 'emergencyPhone', $this->prepareString($objEventMember->emergencyPhone), ['multiline' => false]);
                $objPhpWord->addToClone('i', 'emergencyPhoneName', $this->prepareString($objEventMember->emergencyPhoneName), ['multiline' => false]);
                $objPhpWord->addToClone('i', 'email', $this->prepareString($objEventMember->email), ['multiline' => false]);
                $objPhpWord->addToClone('i', 'transportInfo', $this->prepareString($transportInfo), ['multiline' => false]);
                $objPhpWord->addToClone('i', 'dateOfBirth', '' !== $objEventMember->dateOfBirth ? $dateAdapter->parse('Y', $objEventMember->dateOfBirth) : '', ['multiline' => false]);
            }
        }

        // Event instructors
        $aInstructors = $calendarEventsHelperAdapter->getInstructorsAsArray($objEvent, false);

        $arrInstructors = array_map(
            function ($id) {
                $userModelAdapter = $this->framework->getAdapter(UserModel::class);

                $objUser = $userModelAdapter->findByPk($id);

                if (null !== $objUser) {
                    return $objUser->name;
                }
            },
            $aInstructors
        );
        $objPhpWord->replace('eventInstructors', $this->prepareString(implode(', ', $arrInstructors)));

        // Event Id
        $objPhpWord->replace('eventId', $objEvent->id);
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

    protected function checkEventRapportHasFillesInCorrectly(CalendarEventsInstructorInvoiceModel $objEventInvoice): bool
    {
        /** @var CalendarEventsModel $calendarEventsModelAdapter */
        $calendarEventsModelAdapter = $this->framework->getAdapter(CalendarEventsModel::class);

        /** @var UserModel $userModelAdapter */
        $userModelAdapter = $this->framework->getAdapter(UserModel::class);

        $objEvent = $calendarEventsModelAdapter->findByPk($objEventInvoice->pid);

        // $objBiller "Der Rechnungssteller"
        $objBiller = $userModelAdapter->findByPk($objEventInvoice->userPid);

        if (null !== $objEvent && null !== $objBiller) {
            // Check if tour report has filled in
            if ($objEvent->filledInEventReportForm && '' !== $objEvent->tourAvalancheConditions) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param $objEvent
     *
     * @return mixed
     */
    protected function getParticipatedEventMembers($objEvent)
    {
        /** @var CalendarEventsMemberModel $calendarEventsMemberModelAdapter */
        $calendarEventsMemberModelAdapter = $this->framework->getAdapter(CalendarEventsMemberModel::class);

        return $calendarEventsMemberModelAdapter->findBy(
            ['tl_calendar_events_member.eventId=?', 'tl_calendar_events_member.hasParticipated=?'],
            [$objEvent->id, '1']
        );
    }
}
