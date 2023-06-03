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

namespace Markocupic\SacEventToolBundle\DataContainer;

use Contao\ArrayUtil;
use Contao\Backend;
use Contao\BackendUser;
use Contao\Calendar;
use Contao\CalendarEventsModel;
use Contao\CalendarModel;
use Contao\Config;
use Contao\Controller;
use Contao\CoreBundle\DataContainer\PaletteManipulator;
use Contao\CoreBundle\DependencyInjection\Attribute\AsCallback;
use Contao\CoreBundle\Framework\Adapter;
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\Database;
use Contao\DataContainer;
use Contao\Date;
use Contao\DcaExtractor;
use Contao\FilesModel;
use Contao\Idna;
use Contao\Image;
use Contao\Message;
use Contao\StringUtil;
use Contao\System;
use Contao\User;
use Contao\UserGroupModel;
use Contao\UserModel;
use Contao\Versions;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;
use League\Csv\CannotInsertRecord;
use League\Csv\CharsetConverter;
use League\Csv\InvalidArgument;
use League\Csv\Writer;
use Markocupic\SacEventToolBundle\CalendarEventsHelper;
use Markocupic\SacEventToolBundle\Config\EventState;
use Markocupic\SacEventToolBundle\Config\EventType;
use Markocupic\SacEventToolBundle\Model\CalendarEventsJourneyModel;
use Markocupic\SacEventToolBundle\Model\EventReleaseLevelPolicyModel;
use Markocupic\SacEventToolBundle\Model\EventReleaseLevelPolicyPackageModel;
use Markocupic\SacEventToolBundle\Model\EventTypeModel;
use Markocupic\SacEventToolBundle\Model\TourDifficultyCategoryModel;
use Markocupic\SacEventToolBundle\Security\Voter\CalendarEventsVoter;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\PasswordHasher\Hasher\PasswordHasherFactoryInterface;
use Symfony\Component\Security\Core\Security;

class CalendarEvents
{
    // Adapters
    private Adapter $arrayUtil;
    private Adapter $backend;
    private Adapter $calendarEventsHelper;
    private Adapter $calendarEventsJourneyModel;
    private Adapter $calendarEventsModel;
    private Adapter $calendarModel;
    private Adapter $config;
    private Adapter $controller;
    private Adapter $date;
    private Adapter $dcaExtractor;
    private Adapter $filesModel;
    private Adapter $idna;
    private Adapter $message;
    private Adapter $stringUtil;
    private Adapter $system;
    private Adapter $userModel;

    public function __construct(
        private readonly ContaoFramework $framework,
        private readonly RequestStack $requestStack,
        private readonly Connection $connection,
        private readonly Util $util,
        private readonly Security $security,
        private readonly PasswordHasherFactoryInterface $passwordHasherFactory,
        private readonly LoggerInterface|null $contaoGeneralLogger = null,
    ) {
        // Adapters
        $this->arrayUtil = $this->framework->getAdapter(ArrayUtil::class);
        $this->backend = $this->framework->getAdapter(Backend::class);
        $this->calendarEventsHelper = $this->framework->getAdapter(CalendarEventsHelper::class);
        $this->calendarEventsJourneyModel = $this->framework->getAdapter(CalendarEventsJourneyModel::class);
        $this->calendarEventsModel = $this->framework->getAdapter(CalendarEventsModel::class);
        $this->calendarModel = $this->framework->getAdapter(CalendarModel::class);
        $this->config = $this->framework->getAdapter(Config::class);
        $this->controller = $this->framework->getAdapter(Controller::class);
        $this->date = $this->framework->getAdapter(Date::class);
        $this->dcaExtractor = $this->framework->getAdapter(DcaExtractor::class);
        $this->filesModel = $this->framework->getAdapter(FilesModel::class);
        $this->idna = $this->framework->getAdapter(Idna::class);
        $this->message = $this->framework->getAdapter(Message::class);
        $this->stringUtil = $this->framework->getAdapter(StringUtil::class);
        $this->system = $this->framework->getAdapter(System::class);
        $this->userModel = $this->framework->getAdapter(UserModel::class);
    }

    /**
     * Set the correct referer.
     */
    #[AsCallback(table: 'tl_calendar_events', target: 'config.onload', priority: 100)]
    public function setCorrectReferer(): void
    {
        $this->util->setCorrectReferer();
    }

    /**
     * Set the "on create new" palette.
     */
    #[AsCallback(table: 'tl_calendar_events', target: 'config.onload', priority: 90)]
    public function setPaletteWhenCreatingNew(DataContainer $dc): void
    {
        $request = $this->requestStack->getCurrentRequest();

        if ('edit' === $request->query->get('act')) {
            $objCalendarEventsModel = $this->calendarEventsModel->findByPk($dc->id);

            if (null !== $objCalendarEventsModel) {
                if (0 === (int) $objCalendarEventsModel->tstamp && empty($objCalendarEventsModel->eventType)) {
                    $GLOBALS['TL_DCA']['tl_calendar_events']['palettes']['default'] = 'eventType';
                }
            }

            // If event has been deferred
            if (EventState::STATE_DEFERRED === $objCalendarEventsModel->eventState) {
                PaletteManipulator::create()
                    ->applyToPalette('default', 'tl_calendar_events')
                    ->applyToPalette(EventType::TOUR, 'tl_calendar_events')
                    ->applyToPalette(EventType::LAST_MINUTE_TOUR, 'tl_calendar_events')
                    ->applyToPalette(EventType::GENERAL_EVENT, 'tl_calendar_events')
                    ->applyToPalette(EventType::COURSE, 'tl_calendar_events')
                ;
            }
        }
    }

    /**
     * Reduce filter fields for tour guides and course instructors
     * and
     * Adjust filters depending on event type.
     */
    #[AsCallback(table: 'tl_calendar_events', target: 'config.onload', priority: 80)]
    public function adjustFilterSearchAndSortingBoard(DataContainer $dc): void
    {
        // Reduce filter fields to tour guides and course instructors
        if (!$this->security->isGranted('ROLE_ADMIN')) {
            $allowedFilters = [
                'mountainguide',
                'author',
                'organizers',
                'tourType',
                'journey',
                'eventReleaseLevel',
                'mainInstructor',
                'courseTypeLevel0',
                'startTime',
            ];

            // Reduce filter fields for tour guides and course instructors
            foreach (array_keys($GLOBALS['TL_DCA']['tl_calendar_events']['fields']) as $field) {
                if (\in_array($field, $allowedFilters, true)) {
                    continue;
                }

                $GLOBALS['TL_DCA']['tl_calendar_events']['fields'][$field]['filter'] = null;
            }
        }

        // Adjust filters depending on event type
        if (\defined('CURRENT_ID') && CURRENT_ID > 0) {
            $objCalendar = $this->calendarModel->findByPk(CURRENT_ID);

            if (null !== $objCalendar) {
                $arrAllowedEventTypes = $this->stringUtil->deserialize($objCalendar->allowedEventTypes, true);

                if (!\in_array(EventType::TOUR, $arrAllowedEventTypes, true) && !\in_array(EventType::LAST_MINUTE_TOUR, $arrAllowedEventTypes, true)) {
                    $GLOBALS['TL_DCA']['tl_calendar_events']['fields']['tourType']['filter'] = false;
                    $GLOBALS['TL_DCA']['tl_calendar_events']['fields']['tourType']['search'] = false;
                    $GLOBALS['TL_DCA']['tl_calendar_events']['fields']['tourType']['sorting'] = false;
                }

                if (!\in_array(EventType::COURSE, $arrAllowedEventTypes, true)) {
                    $GLOBALS['TL_DCA']['tl_calendar_events']['fields']['courseTypeLevel0']['filter'] = false;
                    $GLOBALS['TL_DCA']['tl_calendar_events']['fields']['courseTypeLevel0']['search'] = false;
                    $GLOBALS['TL_DCA']['tl_calendar_events']['fields']['courseTypeLevel0']['sorting'] = false;

                    $GLOBALS['TL_DCA']['tl_calendar_events']['fields']['courseTypeLevel1']['filter'] = false;
                    $GLOBALS['TL_DCA']['tl_calendar_events']['fields']['courseTypeLevel1']['search'] = false;
                    $GLOBALS['TL_DCA']['tl_calendar_events']['fields']['courseTypeLevel1']['sorting'] = false;

                    $GLOBALS['TL_DCA']['tl_calendar_events']['fields']['courseLevel']['filter'] = false;
                    $GLOBALS['TL_DCA']['tl_calendar_events']['fields']['courseLevel']['search'] = false;
                    $GLOBALS['TL_DCA']['tl_calendar_events']['fields']['courseLevel']['sorting'] = false;
                }
            }
        }
    }

    /**
     * @throws Exception
     */
    #[AsCallback(table: 'tl_calendar_events', target: 'config.onload', priority: 70)]
    public function onloadCallbackDeleteInvalidEvents(DataContainer $dc): void
    {
        $this->connection->executeStatement(
            'DELETE FROM tl_calendar_events WHERE tstamp < ? AND tstamp > ? AND title = ?',
            [time() - 86400, 0, ''],
        );
    }

    /**
     * @throws Exception
     */
    #[AsCallback(table: 'tl_calendar_events', target: 'config.onload', priority: 60)]
    public function setPermissions(DataContainer $dc): void
    {
        // Skip here if the user is an admin
        if ($this->security->isGranted('ROLE_ADMIN')) {
            return;
        }

        $request = $this->requestStack->getCurrentRequest();

        /** @var BackendUser $user */
        $user = $this->security->getUser();

        // Minimize header fields for default users
        $GLOBALS['TL_DCA']['tl_calendar_events']['list']['sorting']['headerFields'] = ['title'];

        // Do not allow some specific operations for default users
        unset(
            $GLOBALS['TL_DCA']['tl_calendar_events']['list']['operations']['show'],
            $GLOBALS['TL_DCA']['tl_calendar_events']['list']['global_operations']['plus1year'],
            $GLOBALS['TL_DCA']['tl_calendar_events']['list']['global_operations']['minus1year'],
            $GLOBALS['TL_DCA']['tl_calendar_events']['list']['operations']['edit'],
        );

        // Prevent unauthorized deletion
        if ('delete' === $request->query->get('act')) {
            $eventId = $this->connection->fetchOne('SELECT id FROM tl_calendar_events WHERE id = ?', [$dc->id]);

            if ($eventId) {
                if (!$this->security->isGranted(CalendarEventsVoter::CAN_DELETE_EVENT, $eventId)) {
                    $this->message->addError(sprintf($GLOBALS['TL_LANG']['MSC']['missingPermissionsToDeleteEvent'], $eventId));
                    $this->controller->redirect($this->system->getReferer());
                }
            }
        }

        // Prevent unauthorized publishing
        if ($request->query->has('tid')) {
            $tid = $request->query->get('tid');
            $eventId = $this->connection->fetchOne('SELECT id FROM tl_calendar_events WHERE id = ?', [$tid]);

            if ($eventId && !$this->security->isGranted(CalendarEventsVoter::CAN_WRITE_EVENT, $eventId)) {
                $this->message->addError(sprintf($GLOBALS['TL_LANG']['MSC']['missingPermissionsToPublishOrUnpublishEvent'], $eventId));
                $this->controller->redirect($this->system->getReferer());
            }
        }

        // Prevent unauthorized editing
        if ('edit' === $request->query->get('act')) {
            $objEventsModel = $this->calendarEventsModel->findByPk($request->query->get('id'));

            if (null !== $objEventsModel) {
                if (null !== EventReleaseLevelPolicyModel::findByPk($objEventsModel->eventReleaseLevel)) {
                    if (false === $this->security->isGranted(CalendarEventsVoter::CAN_WRITE_EVENT, $objEventsModel->id) && $user->id !== $objEventsModel->registrationGoesTo) {
                        // User has no write access to the data record, that's why we display field values without a form input
                        foreach (array_keys($GLOBALS['TL_DCA']['tl_calendar_events']['fields']) as $field) {
                            $GLOBALS['TL_DCA']['tl_calendar_events']['fields'][$field]['input_field_callback'] = [self::class, 'showFieldValue'];
                        }

                        if ('tl_calendar_events' === $request->request->get('FORM_SUBMIT')) {
                            $this->message->addError(sprintf($GLOBALS['TL_LANG']['MSC']['missingPermissionsToEditEvent'], $objEventsModel->id));
                            $this->controller->redirect($this->system->getReferer());
                        }
                    } else {
                        // Protect fields with $GLOBALS['TL_DCA']['tl_calendar_events']['fields'][$fieldname]['allowEditingOnFirstReleaseLevelOnly'] === true,
                        // if the event is on the first release level
                        $objEventReleaseLevelPolicyPackageModel = EventReleaseLevelPolicyPackageModel::findReleaseLevelPolicyPackageModelByEventId($objEventsModel->id);

                        if (null !== $objEventReleaseLevelPolicyPackageModel) {
                            if ($objEventsModel->eventReleaseLevel > 0) {
                                $objEventReleaseLevelPolicyModel = EventReleaseLevelPolicyModel::findFirstLevelByEventId($objEventsModel->id);

                                if (null !== $objEventReleaseLevelPolicyModel) {
                                    if ($objEventReleaseLevelPolicyModel->id !== $objEventsModel->eventReleaseLevel) {
                                        foreach (array_keys($GLOBALS['TL_DCA']['tl_calendar_events']['fields']) as $fieldname) {
                                            if (true === ($GLOBALS['TL_DCA']['tl_calendar_events']['fields'][$fieldname]['allowEditingOnFirstReleaseLevelOnly'] ?? false) && isset($GLOBALS['TL_DCA']['tl_calendar_events']['fields'][$fieldname]['inputType'])) {
                                                $GLOBALS['TL_DCA']['tl_calendar_events']['fields'][$fieldname]['input_field_callback'] = [self::class, 'showFieldValue'];
                                            }
                                        }
                                    }
                                }
                            }
                        }
                    }
                }
            }
        }

        // Allow select mode only, if an eventReleaseLevel filter is set
        if ('select' === $request->query->get('act')) {
            $objSessionBag = $request->getSession()->getBag('contao_backend');

            $session = $objSessionBag->all();

            $filter = DataContainer::MODE_PARENT === $GLOBALS['TL_DCA']['tl_calendar_events']['list']['sorting']['mode'] ? 'tl_calendar_events_'.CURRENT_ID : 'tl_calendar_events';

            if (!isset($session['filter'][$filter]['eventReleaseLevel'])) {
                $this->message->addInfo('"Mehrere bearbeiten" nur möglich, wenn ein Freigabestufen-Filter gesetzt wurde."');
                $this->controller->redirect($this->system->getReferer());
            }
        }

        // Only list record if the currently logged-in backend user has write-permissions.
        if ('select' === $request->query->get('act') || 'editAll' === $request->query->get('act')) {
            $arrIDS = [0];

            $ids = $this->connection->fetchFirstColumn('SELECT id FROM tl_calendar_events WHERE pid = ?', [CURRENT_ID]);

            foreach ($ids as $id) {
                if ($this->security->isGranted(CalendarEventsVoter::CAN_WRITE_EVENT, $id)) {
                    $arrIDS[] = $id;
                }
            }

            $GLOBALS['TL_DCA']['tl_calendar_events']['list']['sorting']['root'] = $arrIDS;
        }

        // Do not allow editing write-protected fields in editAll mode
        // Use input_field_callback to only display the field values without the form input field
        if ('editAll' === $request->query->get('act') || 'overrideAll' === $request->query->get('act')) {
            $session = $request->getSession()->get('CURRENT');
            $arrIDS = $session['IDS'];

            if (!empty($arrIDS) && \is_array($arrIDS)) {
                $objEventsModel = $this->calendarEventsModel->findByPk($arrIDS[0]);

                if (null !== $objEventsModel) {
                    if ($objEventsModel->eventReleaseLevel > 0) {
                        $objEventReleaseLevelPolicyModel = EventReleaseLevelPolicyModel::findFirstLevelByEventId($objEventsModel->id);

                        if (null !== $objEventReleaseLevelPolicyModel) {
                            if ($objEventReleaseLevelPolicyModel->id !== $objEventsModel->eventReleaseLevel) {
                                foreach (array_keys($GLOBALS['TL_DCA']['tl_calendar_events']['fields']) as $fieldName) {
                                    if (true === ($GLOBALS['TL_DCA']['tl_calendar_events']['fields'][$fieldName]['allowEditingOnFirstReleaseLevelOnly'] ?? false)) {
                                        if ('editAll' === $request->query->get('act')) {
                                            $GLOBALS['TL_DCA']['tl_calendar_events']['fields'][$fieldName]['input_field_callback'] = [self::class, 'showFieldValue'];
                                        } else {
                                            unset($GLOBALS['TL_DCA']['tl_calendar_events']['fields'][$fieldName]);
                                        }
                                    }
                                }
                            }
                        }
                    }
                }
            }
        }
    }

    /**
     * Set palette for course, tour, tour_report, etc.
     */
    #[AsCallback(table: 'tl_calendar_events', target: 'config.onload', priority: 50)]
    public function setPalettes(DataContainer $dc): void
    {
        $request = $this->requestStack->getCurrentRequest();

        if ('editAll' === $request->query->get('act') || 'overrideAll' === $request->query->get('act')) {
            return;
        }

        if ($dc->id > 0) {
            if ('writeTourReport' === $request->query->get('call')) {
                $GLOBALS['TL_DCA']['tl_calendar_events']['palettes']['default'] = $GLOBALS['TL_DCA']['tl_calendar_events']['palettes']['tour_report'];

                return;
            }

            // Set palette for tour and course
            $objCalendarEventsModel = $this->calendarEventsModel->findByPk($dc->id);

            if (null !== $objCalendarEventsModel) {
                if (isset($GLOBALS['TL_DCA']['tl_calendar_events']['palettes'][$objCalendarEventsModel->eventType])) {
                    $GLOBALS['TL_DCA']['tl_calendar_events']['palettes']['default'] = $GLOBALS['TL_DCA']['tl_calendar_events']['palettes'][$objCalendarEventsModel->eventType];
                }
            }
        }
    }

    /**
     * Make a CSV-export of every event of a certain calendar.
     *
     * @throws CannotInsertRecord
     * @throws InvalidArgument
     */
    #[AsCallback(table: 'tl_calendar_events', target: 'config.onload', priority: 40)]
    public function exportCalendar(DataContainer $dc): void
    {
        $request = $this->requestStack->getCurrentRequest();

        if ('onloadCallbackExportCalendar' === $request->query->get('action') && $request->query->get('id') > 0) {
            // Create empty document
            $csv = Writer::createFromString();

            // Set encoding from utf-8 to is0-8859-15 (windows)
            $encoder = (new CharsetConverter())
                ->outputEncoding('iso-8859-15')
            ;

            $csv->addFormatter($encoder);

            // Set delimiter
            $csv->setDelimiter(';');

            // Selected fields
            $arrFields = ['id', 'title', 'eventDates', 'organizers', 'mainInstructor', 'instructor', 'executionState', 'eventState', 'eventType', 'courseLevel', 'courseTypeLevel0', 'courseTypeLevel1', 'tourType', 'tourTechDifficulty', 'eventReleaseLevel', 'journey'];

            // Insert headline first
            $this->controller->loadLanguageFile('tl_calendar_events');

            $arrHeadline = array_map(
                static fn ($field) => $GLOBALS['TL_LANG']['tl_calendar_events'][$field][0] ?? $field,
                $arrFields
            );

            $csv->insertOne($arrHeadline);

            $objEvent = $this->calendarEventsModel->findBy(
                ['tl_calendar_events.pid = ?'],
                [$request->query->get('id')],
                ['order' => 'tl_calendar_events.startDate ASC']
            );

            if (null !== $objEvent) {
                while ($objEvent->next()) {
                    $arrRow = [];

                    foreach ($arrFields as $field) {
                        if ('mainInstructor' === $field) {
                            $objUser = $this->userModel->findByPk($objEvent->{$field});
                            $arrRow[] = null !== $objUser ? html_entity_decode($objUser->lastname.' '.$objUser->firstname) : '';
                        } elseif ('tourTechDifficulty' === $field) {
                            $arrDiff = $this->calendarEventsHelper->getTourTechDifficultiesAsArray($objEvent->current(), false, false);
                            $arrRow[] = implode(' und ', $arrDiff);
                        } elseif ('eventDates' === $field) {
                            $arrTimestamps = $this->calendarEventsHelper->getEventTimestamps($objEvent->current());
                            $arrDates = array_map(
                                static fn ($tstamp) => Date::parse(Config::get('dateFormat'), $tstamp),
                                $arrTimestamps
                            );
                            $arrRow[] = implode(',', $arrDates);
                        } elseif ('organizers' === $field) {
                            $arrOrganizers = $this->calendarEventsHelper->getEventOrganizersAsArray($objEvent->current(), 'title');
                            $arrRow[] = html_entity_decode(implode(',', $arrOrganizers));
                        } elseif ('instructor' === $field) {
                            $arrInstructors = $this->calendarEventsHelper->getInstructorNamesAsArray($objEvent->current(), false, false);
                            $arrRow[] = html_entity_decode(implode(',', $arrInstructors));
                        } elseif ('tourType' === $field) {
                            $arrTourTypes = $this->calendarEventsHelper->getTourTypesAsArray($objEvent->current(), 'title');
                            $arrRow[] = html_entity_decode(implode(',', $arrTourTypes));
                        } elseif ('eventReleaseLevel' === $field) {
                            $objFS = EventReleaseLevelPolicyModel::findByPk($objEvent->{$field});
                            $arrRow[] = null !== $objFS ? $objFS->level : '';
                        } elseif ('journey' === $field) {
                            $objJourney = $this->calendarEventsJourneyModel->findByPk($objEvent->{$field});
                            $arrRow[] = null !== $objJourney ? $objJourney->title : $objEvent->{$field};
                        } elseif ('courseTypeLevel0' === $field) {
                            $arrRow[] = empty($objEvent->{$field}) ? '' : (string) $this->connection->fetchOne('SELECT name FROM tl_course_main_type WHERE id = ?', [$objEvent->{$field}]);
                        } elseif ('courseTypeLevel1' === $field) {
                            $arrRow[] = empty($objEvent->{$field}) ? '' : (string) $this->connection->fetchOne('SELECT name FROM tl_course_sub_type WHERE id = ?', [$objEvent->{$field}]);
                        } elseif ('executionState' === $field) {
                            $arrRow[] = empty($objEvent->{$field}) ? '' : $GLOBALS['TL_LANG']['tl_calendar_events'][$objEvent->{$field}][0] ?? $objEvent->{$field};
                        } elseif ('eventState' === $field) {
                            $arrRow[] = empty($objEvent->{$field}) ? '' : $GLOBALS['TL_LANG']['tl_calendar_events'][$objEvent->{$field}][0] ?? $objEvent->{$field};
                        } else {
                            $arrRow[] = $objEvent->{$field};
                        }
                    }

                    $csv->insertOne($arrRow);
                }
            }

            $objCalendar = $this->calendarModel->findByPk($request->query->get('id'));
            $csv->output($objCalendar->title.'.csv');
            exit;
        }
    }

    /**
     * Shift all event dates of a certain calendar by +/- 1 year
     * contao?do=sac_calendar_events_tool&table=tl_calendar_events&id=21&transformDate=+52weeks.
     *
     * @throws Exception
     */
    #[AsCallback(table: 'tl_calendar_events', target: 'config.onload', priority: 30)]
    public function shiftEventDates(DataContainer $dc): void
    {
        $request = $this->requestStack->getCurrentRequest();

        if ($request->query->get('transformDates')) {
            // $mode may be "+52weeks" or "+1year"
            $mode = $request->query->get('transformDates');

            if (false !== strtotime($mode)) {
                $calendarId = $request->query->get('id');

                $stmt = $this->connection->executeQuery('SELECT * FROM tl_calendar_events WHERE pid = ?', [$calendarId]);

                while (false !== ($row = $stmt->fetchAssociative())) {
                    $set['startTime'] = strtotime($mode, (int) $row['startTime']);
                    $set['endTime'] = strtotime($mode, (int) $row['endTime']);
                    $set['startDate'] = strtotime($mode, (int) $row['startDate']);
                    $set['endDate'] = strtotime($mode, (int) $row['endDate']);

                    if ($row['registrationStartDate'] > 0) {
                        $set['registrationStartDate'] = strtotime($mode, (int) $row['registrationStartDate']);
                    }

                    if ($row['registrationEndDate'] > 0) {
                        $set['registrationEndDate'] = strtotime($mode, (int) $row['registrationEndDate']);
                    }

                    $arrRepeats = $this->stringUtil->deserialize($row['eventDates'], true);
                    $newArrRepeats = [];

                    if (\count($arrRepeats) > 0) {
                        foreach ($arrRepeats as $repeat) {
                            $repeat['new_repeat'] = strtotime($mode, (int) $repeat['new_repeat']);
                            $newArrRepeats[] = $repeat;
                        }

                        $set['eventDates'] = serialize($newArrRepeats);
                    }

                    $this->connection->update('tl_calendar_events', $set, ['id' => $row['id']]);
                }
            }

            // Redirect
            $this->controller->redirect($this->system->getReferer());
        }
    }

    /**
     * Set defaults.
     *
     * @param string        $strTable
     * @param int           $insertId
     * @param array         $set
     * @param DataContainer $dc
     */
    #[AsCallback(table: 'tl_calendar_events', target: 'config.oncreate', priority: 100)]
    public function onCreate(string $strTable, int $insertId, array $set, DataContainer $dc): void
    {
        $user = $this->security->getUser();

        // Set source, add author, set first release level and & set customEventRegistrationConfirmationEmailText on creating new events
        $objEventsModel = $this->calendarEventsModel->findByPk($insertId);

        if (null !== $objEventsModel) {
            // Set source always to "default"
            $objEventsModel->source = 'default';

            // Set logged-in User as author
            $objEventsModel->author = $user->id;
            $objEventsModel->mainInstructor = $user->id;
            $objEventsModel->instructor = serialize([['instructorId' => $user->id]]);

            // Set customEventRegistrationConfirmationEmailText
            $objEventsModel->customEventRegistrationConfirmationEmailText = str_replace('{{br}}', "\n", $this->system->getContainer()->getParameter('sacevt.event.accept_registration_email_body'));

            $objEventsModel->save();
        }
    }

    /**
     * @throws \Exception
     */
    #[AsCallback(table: 'tl_calendar_events', target: 'config.oncopy', priority: 100)]
    public function onCopy(int $insertId, DataContainer $dc): void
    {
        $user = $this->security->getUser();

        // Add author and set first release level on creating new events
        $objEventsModel = $this->calendarEventsModel->findByPk($insertId);

        if (null !== $objEventsModel) {
            // Set logged-in user as author
            $objEventsModel->author = $user->id;
            $objEventsModel->save();

            // Set eventReleaseLevel
            if ('' !== $objEventsModel->eventType) {
                $objEventReleaseLevelPolicyModel = EventReleaseLevelPolicyModel::findFirstLevelByEventId($objEventsModel->id);

                if (null !== $objEventReleaseLevelPolicyModel) {
                    $objEventsModel->eventReleaseLevel = $objEventReleaseLevelPolicyModel->id;
                    $objEventsModel->save();
                }
            }
        }
    }

    /**
     * Do not allow to non-admins deleting records,
     * if there are registrations on the current event.
     *
     * @throws Exception
     */
    #[AsCallback(table: 'tl_calendar_events', target: 'config.ondelete', priority: 100)]
    public function onDelete(DataContainer $dc): void
    {
        // Return if there is no ID
        if (!$dc->activeRecord) {
            return;
        }

        $registrationId = $this->connection->fetchOne('SELECT id FROM tl_calendar_events_member WHERE eventId = ?', [$dc->activeRecord->id]);

        if ($registrationId) {
            $this->message->addError(sprintf($GLOBALS['TL_LANG']['MSC']['deleteEventMembersBeforeDeleteEvent'], $dc->activeRecord->id));
            $this->controller->redirect($this->system->getReferer());
        }
    }

    /**
     * Add a priority of -100
     * This way this callback will be executed after! the legacy callback tl_calendar_events.adjustTime()
     * but before self::adjustRegistrationPeriod.
     *
     * @throws Exception
     */
    #[AsCallback(table: 'tl_calendar_events', target: 'config.onsubmit', priority: -100)]
    public function adjustStartAndEndDate(DataContainer $dc): void
    {
        // Return if there is no active record (override all)
        if (!$dc->activeRecord) {
            return;
        }

        $arrDates = $this->stringUtil->deserialize($dc->activeRecord->eventDates);

        if (!\is_array($arrDates) || empty($arrDates)) {
            return;
        }

        $aNew = [];

        foreach ($arrDates as $v) {
            $objDate = new Date($v['new_repeat']);
            $aNew[$objDate->timestamp] = $objDate->timestamp;
        }

        ksort($aNew);

        $arrDates = [];

        foreach ($aNew as $v) {
            // Save as a timestamp
            $arrDates[] = ['new_repeat' => $v];
        }

        $set = [];
        $startTime = !empty($arrDates[0]['new_repeat']) ? $arrDates[0]['new_repeat'] : 0;
        $endTime = !empty($arrDates[\count($arrDates) - 1]['new_repeat']) ? $arrDates[\count($arrDates) - 1]['new_repeat'] : 0;

        $set['startDate'] = $set['startTime'] = $dc->activeRecord->startDate = $dc->activeRecord->startTime = $startTime;
        $set['endDate'] = $set['endTime'] = $dc->activeRecord->endDate = $dc->activeRecord->endTime = $endTime;

        $this->connection->update('tl_calendar_events', $set, ['id' => $dc->activeRecord->id]);
    }

    /**
     * Add a priority of -110
     * This way this callback will be executed after! the legacy callback tl_calendar_events.adjustTime()
     * but before self::adjustStartAndEndDate.
     *
     * @throws Exception
     */
    #[AsCallback(table: 'tl_calendar_events', target: 'config.onsubmit', priority: -110)]
    public function adjustRegistrationPeriod(DataContainer $dc): void
    {
        // Return if there is no active record (override all)
        if (!$dc->activeRecord) {
            return;
        }

        $row = $this->connection->fetchAssociative('SELECT * FROM tl_calendar_events WHERE id = ?', [$dc->activeRecord->id]);

        if ($row) {
            if ($row['setRegistrationPeriod']) {
                $regEndDate = $row['registrationEndDate'];
                $regStartDate = $row['registrationStartDate'];

                if ($regEndDate > $row['startDate']) {
                    $regEndDate = strtotime(date('Y-m-d', (int) $row['startDate']).' +1 day') - 1;
                    $this->message->addInfo($GLOBALS['TL_LANG']['MSC']['patchedEndDatePleaseCheck']);
                }

                if ($regStartDate > $regEndDate) {
                    $regStartDate = $regEndDate - 86400;
                    $this->message->addInfo($GLOBALS['TL_LANG']['MSC']['patchedStartDatePleaseCheck']);
                }

                $set = [
                    'registrationStartDate' => $regStartDate,
                    'registrationEndDate' => $regEndDate,
                ];

                $dc->activeRecord->registrationStartDate = $regStartDate;
                $dc->activeRecord->registrationEndDate = $regEndDate;

                $this->connection->update('tl_calendar_events', $set, ['id' => $row['id']]);
            }
        }
    }

    /**
     * @throws Exception
     */
    #[AsCallback(table: 'tl_calendar_events', target: 'config.onsubmit', priority: 80)]
    public function adjustEventReleaseLevel(DataContainer $dc): void
    {
        // Return if there is no active record (override all)
        if (!$dc->activeRecord) {
            return;
        }

        if ($dc->activeRecord->eventReleaseLevel) {
            return;
        }

        // Set releaseLevel to level 1
        $eventReleaseLevelModel = EventReleaseLevelPolicyModel::findFirstLevelByEventId($dc->activeRecord->id);

        if (null !== $eventReleaseLevelModel) {
            $set = ['eventReleaseLevel' => $eventReleaseLevelModel->id];
            $dc->activeRecord->eventReleaseLevel = $eventReleaseLevelModel->id;
            $this->connection->update('tl_calendar_events', $set, ['id' => $dc->activeRecord->id]);
        }
    }

    /**
     * @throws Exception
     */
    #[AsCallback(table: 'tl_calendar_events', target: 'config.onsubmit', priority: 60)]
    public function adjustDurationInfo(DataContainer $dc): void
    {
        // Return if there is no active record (override all)
        if (!$dc->activeRecord) {
            return;
        }

        $objEvent = $this->calendarEventsModel->findByPk($dc->activeRecord->id);

        if (null !== $objEvent) {
            $arrTimestamps = $this->calendarEventsHelper->getEventTimestamps($objEvent);

            if ('' !== $objEvent->durationInfo && !empty($arrTimestamps)) {
                $countTimestamps = \count($arrTimestamps);

                if (isset($GLOBALS['TL_CONFIG']['SAC-EVENT-TOOL-CONFIG']['durationInfo'][$objEvent->durationInfo])) {
                    $arrDuration = $GLOBALS['TL_CONFIG']['SAC-EVENT-TOOL-CONFIG']['durationInfo'][$objEvent->durationInfo];

                    if (!empty($arrDuration) && \is_array($arrDuration)) {
                        $duration = $arrDuration['dateRows'];

                        if ($duration !== $countTimestamps) {
                            $set = ['durationInfo' => ''];
                            $dc->activeRecord->durationInfo = '';
                            $this->connection->update('tl_calendar_events', $set, ['id' => $objEvent->id]);

                            $this->message->addError(sprintf('Die Event-Dauer in "%s" [ID:%s] stimmt nicht mit der Anzahl Event-Daten überein. Setzen Sie für jeden Event-Tag eine Datumszeile!', $objEvent->title, $objEvent->id));
                        }
                    }
                }
            }
        }
    }

    /**
     * @param DataContainer $dc
     *
     * @throws Exception
     */
    #[AsCallback(table: 'tl_calendar_events', target: 'config.onsubmit', priority: 40)]
    public function setTheFilledInReportFormAsDone(DataContainer $dc): void
    {
        $request = $this->requestStack->getCurrentRequest();

        // Set filledInEventReportForm, now the invoice form can be printed in tl_calendar_events_instructor_invoice
        if ('writeTourReport' === $request->query->get('call')) {
            $set = [
                'filledInEventReportForm' => '1',
            ];

            $this->connection->update('tl_calendar_events', $set, ['id' => $dc->activeRecord->id]);
        }
    }

    /**
     * @param DataContainer $dc
     *
     * @throws Exception
     */
    #[AsCallback(table: 'tl_calendar_events', target: 'config.onsubmit', priority: 30)]
    public function setAlias(DataContainer $dc): void
    {
        $set = [
            'alias' => 'event-'.$dc->id,
        ];

        $this->connection->update('tl_calendar_events', $set, ['id' => $dc->activeRecord->id]);
    }

    /**
     * @param DataContainer $dc
     *
     * @throws Exception
     */
    #[AsCallback(table: 'tl_calendar_events', target: 'config.onsubmit', priority: 20)]
    public function setValidEventReleaseLevel(DataContainer $dc): void
    {
        // Set correct eventReleaseLevel
        $objEvent = $this->calendarEventsModel->findByPk($dc->activeRecord->id);

        if (null !== $objEvent) {
            if ('' !== $objEvent->eventType) {
                if ($objEvent->eventReleaseLevel > 0) {
                    $objEventReleaseLevel = EventReleaseLevelPolicyModel::findByPk($objEvent->eventReleaseLevel);

                    if (null !== $objEventReleaseLevel) {
                        $objEventReleaseLevelPackage = EventReleaseLevelPolicyPackageModel::findReleaseLevelPolicyPackageModelByEventId($objEvent->id);
                        // Change eventReleaseLevel when changing eventType...
                        if ($objEventReleaseLevel->pid !== $objEventReleaseLevelPackage->id) {
                            $oEventReleaseLevelModel = EventReleaseLevelPolicyModel::findFirstLevelByEventId($objEvent->id);

                            if (null !== $oEventReleaseLevelModel) {
                                $set = [
                                    'eventReleaseLevel' => $oEventReleaseLevelModel->id,
                                ];

                                $this->connection->update('tl_calendar_events', $set, ['id' => $objEvent->id]);
                            }
                        }
                    }
                } else {
                    // Add eventReleaseLevel when creating a new event...
                    $oEventReleaseLevelModel = EventReleaseLevelPolicyModel::findFirstLevelByEventId($objEvent->id);

                    $set = ['eventReleaseLevel' => $oEventReleaseLevelModel->id];
                    $dc->activeRecord->eventReleaseLevel = $oEventReleaseLevelModel->id;

                    $this->connection->update('tl_calendar_events', $set, [$objEvent->id]);
                }
            }
        }
    }

    /**
     * @throws Exception
     */
    #[AsCallback(table: 'tl_calendar_events', target: 'fields.alias.input_field', priority: 100)]
    public function showFieldValue(DataContainer $dc): string
    {
        $field = $dc->field;

        $strTable = 'tl_calendar_events';

        if (!$dc->activeRecord->id) {
            return '';
        }

        $intId = $dc->activeRecord->id;

        $row = $this->connection->fetchAssociative('SELECT '.$field.' FROM tl_calendar_events WHERE id = ?', [$intId]);

        if (!$row) {
            return '';
        }

        $return = '';

        // Get the order fields
        $objDcaExtractor = $this->dcaExtractor->getInstance($strTable);
        $arrOrder = $objDcaExtractor->getOrderFields();

        // Get all fields
        $fields = array_keys($row);
        $allowedFields = ['id', 'pid', 'sorting', 'tstamp'];

        if (\is_array($GLOBALS['TL_DCA'][$strTable]['fields'])) {
            $allowedFields = array_unique(array_merge($allowedFields, array_keys($GLOBALS['TL_DCA'][$strTable]['fields'])));
        }

        // Use the field order of the DCA file
        $fields = array_intersect($allowedFields, $fields);

        // Show all allowed fields
        foreach ($fields as $i) {
            if (!\in_array($i, $allowedFields, true) || 'password' === $GLOBALS['TL_DCA'][$strTable]['fields'][$i]['inputType'] || (isset($GLOBALS['TL_DCA'][$strTable]['fields'][$i]['eval']['doNotShow']) && $GLOBALS['TL_DCA'][$strTable]['fields'][$i]['eval']['doNotShow']) || (isset($GLOBALS['TL_DCA'][$strTable]['fields'][$i]['eval']['hideInput']) && $GLOBALS['TL_DCA'][$strTable]['fields'][$i]['eval']['hideInput'])) {
                continue;
            }

            // Special treatment for table tl_undo
            if ('tl_undo' === $strTable && 'data' === $i) {
                continue;
            }

            $value = $this->stringUtil->deserialize($row[$i]);

            // Decrypt the value
            if ($GLOBALS['TL_DCA'][$strTable]['fields'][$i]['eval']['encrypt'] ?? null) {
                $passwordHasherFactory = $this->passwordHasherFactory
                    ->getPasswordHasher(User::class)
                ;
                $value = $passwordHasherFactory->hash($value);
            }

            // Default value
            $row[$i] = '';

            // Get the field value
            if ('eventType' === $i) {
                $row[$i] = $value;
            } elseif ('eventState' === $i) {
                $row[$i] = '' === $value ? '---' : $value;
            } elseif ('mountainguide' === $i) {
                $row[$i] = $GLOBALS['TL_LANG'][$strTable]['mountainguide_reference'][(int) $row[$i]];
            } elseif ('eventDates' === $i) {
                if (!empty($value) && \is_array($value)) {
                    $arrDate = [];

                    foreach ($value as $arrTstamp) {
                        $arrDate[] = $this->date->parse('D, d.m.Y', $arrTstamp['new_repeat']);
                    }
                    $row[$i] = implode('<br>', $arrDate);
                }
            } elseif ('tourProfile' === $i) {
                // Special treatment for tourProfile
                $arrProfile = [];
                $m = 0;

                if (!empty($value) && \is_array($value)) {
                    foreach ($value as $profile) {
                        ++$m;

                        if (\count($value) > 1) {
                            $pattern = $m.'. Tag &nbsp;&nbsp;&nbsp; Aufstieg: %s m/%s h &nbsp;&nbsp;&nbsp;Abstieg: %s m/%s h';
                        } else {
                            $pattern = 'Aufstieg: %s m/%s h &nbsp;&nbsp;&nbsp;Abstieg: %s m/%s h';
                        }

                        $arrProfile[] = sprintf($pattern, $profile['tourProfileAscentMeters'], $profile['tourProfileAscentTime'], $profile['tourProfileDescentMeters'], $profile['tourProfileDescentTime']);
                    }
                }

                if (!empty($arrProfile)) {
                    $row[$i] = implode('<br>', $arrProfile);
                }
            } elseif ('instructor' === $i) {
                // Special treatment for instructor
                $arrInstructors = [];

                foreach ($value as $arrInstructor) {
                    if ($arrInstructor['instructorId'] > 0) {
                        $objUser = $this->userModel->findByPk($arrInstructor['instructorId']);

                        if (null !== $objUser) {
                            $arrInstructors[] = $objUser->name;
                        }
                    }
                }

                if (!empty($arrInstructors)) {
                    $row[$i] = implode('<br>', $arrInstructors);
                }
            } elseif ('tourTechDifficulty' === $i) {
                // Special treatment for tourTechDifficulty
                $arrDiff = [];

                foreach ($value as $difficulty) {
                    $strDiff = '';

                    if (\strlen((string) $difficulty['tourTechDifficultyMin']) && \strlen($difficulty['tourTechDifficultyMax'])) {
                        $strMin = $this->connection->fetchOne('SELECT shortcut FROM tl_tour_difficulty WHERE id = ?', [$difficulty['tourTechDifficultyMin']]);

                        if ($strMin) {
                            $strDiff = $strMin;
                        }

                        $strMax = $this->connection->fetchOne('SELECT shortcut FROM tl_tour_difficulty WHERE id = ?', [$difficulty['tourTechDifficultyMax']]);

                        if ($strMax) {
                            $strDiff .= ' - '.$strMax;
                        }

                        $arrDiff[] = $strDiff;
                    } elseif (\strlen((string) $difficulty['tourTechDifficultyMin'])) {
                        $strMin = $this->connection->fetchOne('SELECT shortcut FROM tl_tour_difficulty WHERE id = ?', [$difficulty['tourTechDifficultyMin']]);

                        if ($strMin) {
                            $strDiff = $strMin;
                        }

                        $arrDiff[] = $strDiff;
                    }
                }

                if (!empty($arrDiff)) {
                    $row[$i] = implode(', ', $arrDiff);
                }
            } elseif (isset($GLOBALS['TL_DCA'][$strTable]['fields'][$i]['foreignKey'])) {
                $temp = [];
                $chunks = explode('.', $GLOBALS['TL_DCA'][$strTable]['fields'][$i]['foreignKey'], 2);

                foreach ((array) $value as $v) {
                    // Use \Contao\Database::quoteIdentifier instead of Doctrine\DBAL\Connection::quoteIdentifier
                    // because only Contao can handle chained foreign keys like this:
                    // 'foreignKey' => "tl_user.CONCAT(lastname, ' ', firstname, ', ', city)",
                    $keyValue = $this->connection->fetchOne('SELECT '.Database::quoteIdentifier($chunks[1]).' AS value FROM '.$chunks[0].' WHERE id = ?', [$v]);

                    if ($keyValue) {
                        $temp[] = $keyValue;
                    }
                }

                $row[$i] = implode(', ', $temp);
            } elseif (($GLOBALS['TL_DCA'][$strTable]['fields'][$i]['inputType'] ?? null) === 'fileTree' || \in_array($i, $arrOrder, true)) {
                if (\is_array($value)) {
                    foreach ($value as $kk => $vv) {
                        if (($objFile = $this->filesModel->findByUuid($vv)) instanceof FilesModel) {
                            $value[$kk] = $objFile->path.' ('.$this->stringUtil->binToUuid($vv).')';
                        } else {
                            $value[$kk] = '';
                        }
                    }

                    $row[$i] = implode(', ', $value);
                } elseif (($objFile = $this->filesModel->findByUuid($value)) instanceof FilesModel) {
                    $row[$i] = $objFile->path.' ('.$this->stringUtil->binToUuid($value).')';
                } else {
                    $row[$i] = '';
                }
            } elseif (\is_array($value)) {
                if (isset($value['value'], $value['unit']) && 2 === \count($value)) {
                    $row[$i] = trim($value['value'].', '.$value['unit']);
                } else {
                    foreach ($value as $kk => $vv) {
                        if (\is_array($vv)) {
                            $values = array_values($vv);
                            $value[$kk] = array_shift($values).' ('.implode(', ', array_filter($values)).')';
                        }
                    }

                    if ($this->arrayUtil->isAssoc($value)) {
                        foreach ($value as $kk => $vv) {
                            $value[$kk] = $kk.': '.$vv;
                        }
                    }

                    $row[$i] = implode(', ', $value);
                }
            } elseif (($GLOBALS['TL_DCA'][$strTable]['fields'][$i]['eval']['rgxp'] ?? null) === 'date') {
                $row[$i] = $value ? $this->date->parse($this->config->get('dateFormat'), $value) : '-';
            } elseif (($GLOBALS['TL_DCA'][$strTable]['fields'][$i]['eval']['rgxp'] ?? null) === 'time') {
                $row[$i] = $value ? $this->date->parse($this->config->get('timeFormat'), $value) : '-';
            } elseif ('tstamp' === $i || ($GLOBALS['TL_DCA'][$strTable]['fields'][$i]['eval']['rgxp'] ?? null) === 'datim' || \in_array($GLOBALS['TL_DCA'][$strTable]['fields'][$i]['flag'] ?? null, [DataContainer::SORT_DAY_ASC, DataContainer::SORT_DAY_DESC, DataContainer::SORT_MONTH_ASC, DataContainer::SORT_MONTH_DESC, DataContainer::SORT_YEAR_ASC, DataContainer::SORT_YEAR_DESC], true)) {
                $row[$i] = $value ? $this->date->parse($this->config->get('datimFormat'), $value) : '-';
            } elseif (($GLOBALS['TL_DCA'][$strTable]['fields'][$i]['eval']['isBoolean'] ?? null) || (($GLOBALS['TL_DCA'][$strTable]['fields'][$i]['inputType'] ?? null) === 'checkbox' && !($GLOBALS['TL_DCA'][$strTable]['fields'][$i]['eval']['multiple'] ?? null))) {
                $row[$i] = $value ? $GLOBALS['TL_LANG']['MSC']['yes'] : $GLOBALS['TL_LANG']['MSC']['no'];
            } elseif (($GLOBALS['TL_DCA'][$strTable]['fields'][$i]['eval']['rgxp'] ?? null) === 'email') {
                $row[$i] = $this->idna->decodeEmail($value);
            } elseif (($GLOBALS['TL_DCA'][$strTable]['fields'][$i]['inputType'] ?? null) === 'textarea' && (($GLOBALS['TL_DCA'][$strTable]['fields'][$i]['eval']['allowHtml'] ?? null) || ($GLOBALS['TL_DCA'][$strTable]['fields'][$i]['eval']['preserveTags'] ?? null))) {
                $row[$i] = $this->stringUtil->specialchars($value);
            } elseif (\is_array($GLOBALS['TL_DCA'][$strTable]['fields'][$i]['reference'] ?? null)) {
                $row[$i] = isset($GLOBALS['TL_DCA'][$strTable]['fields'][$i]['reference'][$row[$i]]) ? (\is_array($GLOBALS['TL_DCA'][$strTable]['fields'][$i]['reference'][$row[$i]]) ? $GLOBALS['TL_DCA'][$strTable]['fields'][$i]['reference'][$row[$i]][0] : $GLOBALS['TL_DCA'][$strTable]['fields'][$i]['reference'][$row[$i]]) : $row[$i];
            } elseif (($GLOBALS['TL_DCA'][$strTable]['fields'][$i]['eval']['isAssociative'] ?? null) || $this->arrayUtil->isAssoc($GLOBALS['TL_DCA'][$strTable]['fields'][$i]['options'] ?? null)) {
                $row[$i] = $GLOBALS['TL_DCA'][$strTable]['fields'][$i]['options'][$row[$i]] ?? null;
            } else {
                $row[$i] = $value;
            }

            // Label and help
            if (isset($GLOBALS['TL_DCA'][$strTable]['fields'][$i]['label'])) {
                $label = $GLOBALS['TL_DCA'][$strTable]['fields'][$i]['label'][0] ?? $i;
                $help = $GLOBALS['TL_DCA'][$strTable]['fields'][$i]['label'][1] ?? $i;
            } else {
                $label = isset($GLOBALS['TL_LANG']['MSC'][$i]) && \is_array($GLOBALS['TL_LANG']['MSC'][$i]) ? $GLOBALS['TL_LANG']['MSC'][$i][0] : $GLOBALS['TL_LANG']['MSC'][$i];
                $help = isset($GLOBALS['TL_LANG']['MSC'][$i]) && \is_array($GLOBALS['TL_LANG']['MSC'][$i]) ? $GLOBALS['TL_LANG']['MSC'][$i][1] : $GLOBALS['TL_LANG']['MSC'][$i];
            }

            if (empty($label)) {
                $label = $i;
            }

            if (!empty($help)) {
                $help = '<p class="tl_help tl_tip tl_full_height">'.$help.'</p>';
            }

            $return .= '
<div class="clr readonly">
    <h3><label for="ctrl_title">'.$label.'</label></h3>
    <div class="field-content-box">'.$row[$i].'</div>
'.$help.'
</div>';
        }

        // Return html
        return $return;
    }

    #[AsCallback(table: 'tl_calendar_events', target: 'fields.eventDates.load', priority: 100)]
    public function transformTimestampsToDates(string|null $varValue, DataContainer $dc): array
    {
        $arrValues = $this->stringUtil->deserialize($varValue, true);

        if (isset($arrValues[0])) {
            if ($arrValues[0]['new_repeat'] <= 0) {
                // Replace invalid date with empty array
                $arrValues = [];
            }
        } else {
            $arrValues = [];
        }

        return $arrValues;
    }

    #[AsCallback(table: 'tl_calendar_events', target: 'edit.buttons', priority: 100)]
    public function editButtons($arrButtons, $dc)
    {
        $request = $this->requestStack->getCurrentRequest();

        if ('writeTourReport' === $request->query->get('call')) {
            unset($arrButtons['saveNcreate'], $arrButtons['saveNduplicate'], $arrButtons['saveNedit']);
        }

        return $arrButtons;
    }

    #[AsCallback(table: 'tl_calendar_events', target: 'fields.durationInfo.options', priority: 100)]
    public function getEventDuration(): array
    {
        $arrDuration = $GLOBALS['TL_CONFIG']['SAC-EVENT-TOOL-CONFIG']['durationInfo'];

        if (!empty($arrDuration) && \is_array($arrDuration)) {
            $opt = $arrDuration;
        } else {
            $opt = [];
        }

        $arrOpt = [];

        foreach (array_keys($opt) as $k) {
            $arrOpt[] = $k;
        }

        return $arrOpt;
    }

    /**
     * @throws Exception
     */
    #[AsCallback(table: 'tl_calendar_events', target: 'fields.organizers.options', priority: 90)]
    public function getEventOrganizers(): array
    {
        return $this->connection->fetchAllKeyValue('SELECT id,title FROM tl_event_organizer ORDER BY sorting');
    }

    /**
     * @throws Exception
     */
    #[AsCallback(table: 'tl_calendar_events', target: 'fields.courseTypeLevel0.options', priority: 80)]
    public function getCourseSuperCategory(): array
    {
        return $this->connection->fetchAllKeyValue('SELECT id,name FROM tl_course_main_type ORDER BY code');
    }

    /**
     * Options callback for Multi Column Wizard field tl_calendar_events.tourTechDifficulty.
     *
     * @throws Exception
     */
    public function getTourDifficulties(): array
    {
        $options = [];
        $stmt = $this->connection->executeQuery('SELECT * FROM tl_tour_difficulty ORDER BY pid, code');

        while (false !== ($row = $stmt->fetchAssociative())) {
            $objDiffCat = TourDifficultyCategoryModel::findByPk($row['pid']);

            if (null !== $objDiffCat) {
                if ('' !== $objDiffCat->title) {
                    if (!isset($options[$objDiffCat->title])) {
                        $options[$objDiffCat->title] = [];
                    }

                    $options[$objDiffCat->title][$row['id']] = $row['shortcut'];
                }
            }
        }

        return $options;
    }

    /**
     * @throws \Exception
     */
    #[AsCallback(table: 'tl_calendar_events', target: 'fields.eventType.options', priority: 70)]
    public function getEventTypes(DataContainer|null $dc): array
    {
        $options = [];
        $user = $this->security->getUser();

        if (!$dc) {
            return $options;
        }

        if (!$dc->id && CURRENT_ID > 0) {
            $objCalendar = $this->calendarModel->findByPk(CURRENT_ID);
        } elseif ($dc->id > 0) {
            $objCalendar = $this->calendarEventsModel->findByPk($dc->id)->getRelated('pid');
        }

        $arrAllowedEventTypes = [];

        if (isset($objCalendar) && null !== $user) {
            $arrGroups = $this->stringUtil->deserialize($user->groups, true);

            foreach ($arrGroups as $group) {
                $objGroup = UserGroupModel::findByPk($group);

                if (null !== $objGroup && !empty($objGroup->allowedEventTypes) && \is_array($objGroup->allowedEventTypes)) {
                    $arrAllowedEvtTypes = $this->stringUtil->deserialize($objGroup->allowedEventTypes, true);

                    foreach ($arrAllowedEvtTypes as $eventType) {
                        if (!\in_array($eventType, $arrAllowedEventTypes, false)) {
                            $arrAllowedEventTypes[] = $eventType;
                        }
                    }
                }
            }
        }

        if (isset($objCalendar)) {
            $options = $this->stringUtil->deserialize($objCalendar->allowedEventTypes, true);
        }

        return $options;
    }

    /**
     * @throws Exception
     */
    #[AsCallback(table: 'tl_calendar_events', target: 'fields.courseTypeLevel1.options', priority: 60)]
    public function getCourseSubCategory(DataContainer $dc): array
    {
        $options = [];

        $eventId = $this->connection->fetchOne(
            'SELECT courseTypeLevel0 FROM tl_calendar_events WHERE id = ?',
            [$dc->id],
        );

        if ($eventId) {
            $stmt = $this->connection->executeQuery(
                'SELECT * FROM tl_course_sub_type WHERE pid = ? ORDER BY pid, code',
                [$eventId],
            );

            while (false !== ($row = $stmt->fetchAssociative())) {
                $options[$row['id']] = $row['code'].' '.$row['name'];
            }
        }

        return $options;
    }

    /**
     * @throws Exception
     * @throws \Exception
     */
    #[AsCallback(table: 'tl_calendar_events', target: 'fields.eventReleaseLevel.options', priority: 50)]
    public function getReleaseLevels(DataContainer $dc): array
    {
        // Use $GLOBALS['TL_DCA']['tl_calendar_events']['fields']['eventReleaseLevel']['foreignKey']
        // for the filter panel instead of the options callback
        $referringMethod = debug_backtrace()[2]['function'];

        if ('panel' === $referringMethod) {
            return [];
        }

        $options = [];

        $user = $this->security->getUser();
        $arrAllowedEventTypes = [];

        if ($user instanceof BackendUser) {
            if (!$this->security->isGranted('ROLE_ADMIN')) {
                $arrGroups = $this->stringUtil->deserialize($user->groups, true);

                foreach ($arrGroups as $group) {
                    $objGroup = UserGroupModel::findByPk($group);

                    if (null !== $objGroup) {
                        $arrEventTypes = $this->stringUtil->deserialize($objGroup->allowedEventTypes, true);

                        foreach ($arrEventTypes as $eventType) {
                            if (!\in_array($eventType, $arrAllowedEventTypes, false)) {
                                $arrAllowedEventTypes[] = $eventType;
                            }
                        }
                    }
                }

                foreach ($arrAllowedEventTypes as $eventType) {
                    $objEventType = EventTypeModel::findByPk($eventType);

                    if (null !== $objEventType) {
                        $objEventReleasePackage = EventReleaseLevelPolicyPackageModel::findByPk($objEventType->levelAccessPermissionPackage);

                        if (null !== $objEventReleasePackage) {
                            $stmt = $this->connection->executeQuery('SELECT * FROM tl_event_release_level_policy WHERE pid = ? ORDER BY level', [$objEventReleasePackage->id]);

                            while (false !== ($rowEventReleaseLevels = $stmt->fetchAssociative())) {
                                $options[EventReleaseLevelPolicyModel::findByPk($rowEventReleaseLevels['id'])->getRelated('pid')->title][$rowEventReleaseLevels['id']] = $rowEventReleaseLevels['title'];
                            }
                        }
                    }
                }
            } else {
                $stmt = $this->connection->executeQuery('SELECT * FROM tl_event_release_level_policy ORDER BY pid,level');

                while (false !== ($rowEventReleaseLevels = $stmt->fetchAssociative())) {
                    $options[EventReleaseLevelPolicyModel::findByPk($rowEventReleaseLevels['id'])->getRelated('pid')->title][$rowEventReleaseLevels['id']] = $rowEventReleaseLevels['title'];
                }
            }
        }

        return $options;
    }

    /**
     * Multi Column Wizard columnsCallback listFixedDates().
     */
    public function listFixedDates(): array
    {
        return [
            'new_repeat' => [
                'label' => null,
                'exclude' => true,
                'inputType' => 'text',
                'default' => time(),
                'eval' => ['rgxp' => 'date', 'datepicker' => true, 'doNotCopy' => false, 'style' => 'width:100px', 'tl_class' => 'hidelabel wizard'],
            ],
        ];
    }

    #[AsCallback(table: 'tl_calendar_events', target: 'list.sorting.child_record', priority: 100)]
    public function childRecordCallback(array $arrRow): string
    {
        $span = Calendar::calculateSpan($arrRow['startTime'], $arrRow['endTime']);
        $objEvent = $this->calendarEventsModel->findByPk($arrRow['id']);

        if ($span > 0) {
            $date = $this->date->parse($this->config->get(($arrRow['addTime'] ? 'datimFormat' : 'dateFormat')), $arrRow['startTime']).' – '.$this->date->parse($this->config->get(($arrRow['addTime'] ? 'datimFormat' : 'dateFormat')), $arrRow['endTime']);
        } elseif ((int) $arrRow['startTime'] === (int) $arrRow['endTime']) {
            $date = $this->date->parse($this->config->get('dateFormat'), $arrRow['startTime']).($arrRow['addTime'] ? ' '.$this->date->parse($this->config->get('timeFormat'), $arrRow['startTime']) : '');
        } else {
            $date = $this->date->parse($this->config->get('dateFormat'), $arrRow['startTime']).($arrRow['addTime'] ? ' '.$this->date->parse($this->config->get('timeFormat'), $arrRow['startTime']).' – '.$this->date->parse($this->config->get('timeFormat'), $arrRow['endTime']) : '');
        }

        // Add icon
        if ($arrRow['published']) {
            $icon = Image::getHtml('visible.svg', $GLOBALS['TL_LANG']['MSC']['published'], 'title="'.$GLOBALS['TL_LANG']['MSC']['published'].'"');
        } else {
            $icon = Image::getHtml('invisible.svg', $GLOBALS['TL_LANG']['MSC']['unpublished'], 'title="'.$GLOBALS['TL_LANG']['MSC']['unpublished'].'"');
        }

        // Add main instructor
        $strAuthor = '';
        $objUser = $this->userModel->findByPk($arrRow['mainInstructor']);

        if (null !== $objUser) {
            $strAuthor = ' <span style="color:#b3b3b3;padding-left:3px">[Hauptleiter: '.$objUser->name.']</span><br>';
        }

        $strRegistrations = $this->calendarEventsHelper->getEventStateOfSubscriptionBadgesString($objEvent);

        if ('' !== $strRegistrations) {
            $strRegistrations = '<br>'.$strRegistrations;
        }

        // Add event release level
        $strLevel = '';
        $eventReleaseLevelModel = EventReleaseLevelPolicyModel::findByPk($arrRow['eventReleaseLevel']);

        if (null !== $eventReleaseLevelModel) {
            $strLevel = sprintf(
                '<span class="release-level-%s text-decoration-underline" title="Freigabestufe: %s">FS: %s</span> ',
                $eventReleaseLevelModel->level,
                $eventReleaseLevelModel->title,
                $eventReleaseLevelModel->level,
            );
        }

        return sprintf(
            '<div class="tl_content_left">%s %s%s <span style="color:#999;padding-left:3px">[%s]</span>%s%s</div>',
            $icon,
            $strLevel,
            $arrRow['title'],
            $date,
            $strAuthor,
            $strRegistrations,
        );
    }

    /**
     * Push event to next release level.
     *
     * @throws \Exception
     */
    #[AsCallback(table: 'tl_calendar_events', target: 'list.operations.releaseLevelNext.button', priority: 100)]
    public function releaseLevelNext(array $row, string|null $href, string $label, string $title, string|null $icon, string $attributes): string
    {
        $request = $this->requestStack->getCurrentRequest();

        $strDirection = 'up';

        $canPushToNextReleaseLevel = false;
        $objReleaseLevelModel = EventReleaseLevelPolicyModel::findByPk($row['eventReleaseLevel']);
        $nextReleaseLevel = null;

        if (null !== $objReleaseLevelModel) {
            $nextReleaseLevel = $objReleaseLevelModel->level + 1;
        }

        // Save to database
        if ('releaseLevelNext' === $request->query->get('action') && (int) $request->query->get('eventId') === (int) $row['id']) {
            if (true === $this->security->isGranted(CalendarEventsVoter::CAN_UPGRADE_EVENT_RELEASE_LEVEL, $row['id']) && true === EventReleaseLevelPolicyModel::levelExists($row['id'], $nextReleaseLevel)) {
                $objEvent = $this->calendarEventsModel->findByPk($request->query->get('eventId'));

                if (null !== $objEvent) {
                    $objReleaseLevelModelCurrent = EventReleaseLevelPolicyModel::findByPk($objEvent->eventReleaseLevel);
                    $titleCurrent = $objReleaseLevelModelCurrent ? $objReleaseLevelModelCurrent->title : 'not defined';

                    $objReleaseLevelModelNew = EventReleaseLevelPolicyModel::findNextLevel($objEvent->eventReleaseLevel);
                    $titleNew = $objReleaseLevelModelNew ? $objReleaseLevelModelNew->title : 'not defined';

                    if (null !== $objReleaseLevelModelNew) {
                        $objEvent->eventReleaseLevel = $objReleaseLevelModelNew->id;
                        $objEvent->save();

                        // Create new version
                        $objVersions = new Versions('tl_calendar_events', $objEvent->id);
                        $objVersions->initialize();
                        $objVersions->create();

                        // System log
                        $this->contaoGeneralLogger?->info(
                            sprintf(
                                'Event release level for event with ID %d ["%s"] pushed %s from "%s" to "%s".',
                                $objEvent->id,
                                $objEvent->title,
                                $strDirection,
                                $titleCurrent,
                                $titleNew,
                            ),
                        );

                        $this->handleEventReleaseLevelAndPublishUnpublish((int) $objEvent->id, (int) $objEvent->eventReleaseLevel);

                        // HOOK: changeEventReleaseLevel, e.g. inform tourenchef via email
                        if (isset($GLOBALS['TL_HOOKS']['changeEventReleaseLevel']) && \is_array($GLOBALS['TL_HOOKS']['changeEventReleaseLevel'])) {
                            foreach ($GLOBALS['TL_HOOKS']['changeEventReleaseLevel'] as $callback) {
                                $this->system->importStatic($callback[0])->{$callback[1]}($objEvent, $strDirection);
                            }
                        }
                    }
                }
            }

            $this->controller->redirect($this->system->getReferer());
        }

        if (true === $this->security->isGranted(CalendarEventsVoter::CAN_UPGRADE_EVENT_RELEASE_LEVEL, $row['id']) && true === EventReleaseLevelPolicyModel::levelExists($row['id'], $nextReleaseLevel)) {
            $canPushToNextReleaseLevel = true;
        }

        if (false === $canPushToNextReleaseLevel) {
            return Image::getHtml(str_replace('default', 'brightened', $icon), $label).' ';
        }

        return '<a href="'.$this->backend->addToUrl($href.'&amp;eventId='.$row['id']).'" title="'.$this->stringUtil->specialchars($title).'"'.$attributes.'>'.Image::getHtml($icon, $label).'</a> ';
    }

    /**
     * Update main instructor (the first instructor in the list is the main instructor).
     *
     * @throws Exception
     */
    #[AsCallback(table: 'tl_calendar_events', target: 'fields.instructor.save', priority: 100)]
    public function setMainInstructor(string|null $varValue, DataContainer $dc): string|null
    {
        if ($dc->id > 0) {
            $arrInstructors = $this->stringUtil->deserialize($varValue, true);

            // Use a child table to store instructors
            // Delete instructor
            $this->connection->delete('tl_calendar_events_instructor', ['pid' => $dc->id]);

            $i = 0;

            foreach ($arrInstructors as $arrInstructor) {
                // Rebuild instructor table
                $set = [
                    'pid' => $dc->id,
                    'userId' => $arrInstructor['instructorId'],
                    'tstamp' => time(),
                    'isMainInstructor' => $i < 1 ? '1' : '',
                ];

                $this->connection->insert('tl_calendar_events_instructor', $set);

                ++$i;
            }
            // End child insert

            if (\count($arrInstructors) > 0) {
                $intInstructor = $arrInstructors[0]['instructorId'];

                if (null !== $this->userModel->findByPk($intInstructor)) {
                    $set = ['mainInstructor' => $intInstructor];

                    $this->connection->update('tl_calendar_events', $set, ['id' => $dc->id]);

                    return $varValue;
                }
            }

            $set = ['mainInstructor' => 0];

            $this->connection->update('tl_calendar_events', $set, ['id' => $dc->id]);
        }

        return $varValue;
    }

    /**
     * Publish or un-publish events if eventReleaseLevel has reached the highest/lowest level.
     *
     * @throws \Exception
     */
    #[AsCallback(table: 'tl_calendar_events', target: 'fields.eventReleaseLevel.save', priority: 90)]
    public function saveCallbackEventReleaseLevel(int $targetEventReleaseLevelId, DataContainer $dc): int
    {
        return $this->handleEventReleaseLevelAndPublishUnpublish((int) $dc->activeRecord->id, $targetEventReleaseLevelId);
    }

    /**
     * Don't allow the max value to be the same as the min value.
     *
     * @param $value
     * @param DataContainer $dc
     */
    #[AsCallback(table: 'tl_calendar_events', target: 'fields.tourTechDifficulty.save', priority: 90)]
    public function setCorrectTourTechDifficulty(string $value, DataContainer $dc): string
    {
        $arrValue = $this->stringUtil->deserialize($value, true);
        $hasUpdate = false;

        if (!empty($arrValue)) {
            foreach ($arrValue as $i => $tourTechDiff) {
                if (isset($tourTechDiff['tourTechDifficultyMin'],$tourTechDiff['tourTechDifficultyMax']) && $tourTechDiff['tourTechDifficultyMin'] === $tourTechDiff['tourTechDifficultyMax']) {
                    $arrValue[$i]['tourTechDifficultyMax'] = '';
                    $hasUpdate = true;
                }
            }

            if ($hasUpdate) {
                return serialize($arrValue);
            }
        }

        return $value;
    }

    /**
     * @throws \Exception
     */
    #[AsCallback(table: 'tl_calendar_events', target: 'fields.eventType.save', priority: 80)]
    public function saveCallbackEventType(string $strEventType, DataContainer $dc, int $intId = null): string
    {
        if ('' !== $strEventType) {
            if ($dc->activeRecord->id > 0) {
                $objEvent = $this->calendarEventsModel->findByPk($dc->activeRecord->id);
            } else {
                $objEvent = $this->calendarEventsModel->findByPk($intId);
            }

            if (null === $objEvent) {
                throw new \Exception('Event not found.');
            }

            // !important, because if the eventType is not saved, no eventReleaseLevel can be assigned
            $objEvent->eventType = $strEventType;
            $objEvent->save();

            if (null === EventReleaseLevelPolicyModel::findByPk($objEvent->eventReleaseLevel)) {
                $objEventReleaseModel = EventReleaseLevelPolicyModel::findFirstLevelByEventId($objEvent->id);

                if (null !== $objEventReleaseModel) {
                    $objEvent->eventReleaseLevel = $objEventReleaseModel->id;
                    $objEvent->save();
                }
            }
        }

        return $strEventType;
    }

    /**
     * Downgrade event to the previous release level.
     *
     * @throws \Exception
     */
    #[AsCallback(table: 'tl_calendar_events', target: 'list.operations.releaseLevelPrev.button', priority: 90)]
    public function releaseLevelPrev(array $row, string|null $href, string $label, string $title, string|null $icon, string $attributes): string
    {
        $request = $this->requestStack->getCurrentRequest();

        $strDirection = 'down';

        $canPushToNextReleaseLevel = false;
        $prevReleaseLevel = null;
        $objReleaseLevelModel = EventReleaseLevelPolicyModel::findByPk($row['eventReleaseLevel']);

        if (null !== $objReleaseLevelModel) {
            $prevReleaseLevel = $objReleaseLevelModel->level - 1;
        }

        // Save to database
        if ('releaseLevelPrev' === $request->query->get('action') && (int) $request->query->get('eventId') === (int) $row['id']) {
            if (true === $this->security->isGranted(CalendarEventsVoter::CAN_DOWNGRADE_EVENT_RELEASE_LEVEL, $row['id']) && true === EventReleaseLevelPolicyModel::levelExists($row['id'], $prevReleaseLevel)) {
                $objEvent = $this->calendarEventsModel->findByPk($request->query->get('eventId'));

                if (null !== $objEvent) {
                    $objReleaseLevelModelCurrent = EventReleaseLevelPolicyModel::findByPk($objEvent->eventReleaseLevel);
                    $titleCurrent = $objReleaseLevelModelCurrent ? $objReleaseLevelModelCurrent->title : 'not defined';

                    $objReleaseLevelModelNew = EventReleaseLevelPolicyModel::findPrevLevel($objEvent->eventReleaseLevel);
                    $titleNew = $objReleaseLevelModelNew ? $objReleaseLevelModelNew->title : 'not defined';

                    if (null !== $objReleaseLevelModelNew) {
                        $objEvent->eventReleaseLevel = $objReleaseLevelModelNew->id;
                        $objEvent->save();

                        // Create new version
                        $objVersions = new Versions('tl_calendar_events', $objEvent->id);
                        $objVersions->initialize();
                        $objVersions->create();

                        // System log
                        $this->contaoGeneralLogger?->info(
                            sprintf(
                                'Event release level for event with ID %d ["%s"] pushed %s from "%s" to "%s".',
                                $objEvent->id,
                                $objEvent->title,
                                $strDirection,
                                $titleCurrent,
                                $titleNew,
                            ),
                        );

                        $this->handleEventReleaseLevelAndPublishUnpublish((int) $objEvent->id, (int) $objEvent->eventReleaseLevel);

                        // HOOK: changeEventReleaseLevel, f.ex inform tourenchef via email
                        if (isset($GLOBALS['TL_HOOKS']['changeEventReleaseLevel']) && \is_array($GLOBALS['TL_HOOKS']['changeEventReleaseLevel'])) {
                            foreach ($GLOBALS['TL_HOOKS']['changeEventReleaseLevel'] as $callback) {
                                $this->system->importStatic($callback[0])->{$callback[1]}($objEvent, $strDirection);
                            }
                        }
                    }
                }
            }

            $this->controller->redirect($this->system->getReferer());
        }

        if (true === $this->security->isGranted(CalendarEventsVoter::CAN_DOWNGRADE_EVENT_RELEASE_LEVEL, $row['id']) && true === EventReleaseLevelPolicyModel::levelExists($row['id'], $prevReleaseLevel)) {
            $canPushToNextReleaseLevel = true;
        }

        if (false === $canPushToNextReleaseLevel) {
            return Image::getHtml(str_replace('default', 'brightened', $icon), $label).' ';
        }

        return '<a href="'.$this->backend->addToUrl($href.'&amp;eventId='.$row['id']).'" title="'.$this->stringUtil->specialchars($title).'"'.$attributes.'>'.Image::getHtml($icon, $label).'</a> ';
    }

    #[AsCallback(table: 'tl_calendar_events', target: 'list.operations.delete.button', priority: 80)]
    public function deleteIcon(array $row, string|null $href, string $label, string $title, string|null $icon, string $attributes): string
    {
        $blnAllow = $this->security->isGranted(CalendarEventsVoter::CAN_DELETE_EVENT, $row['id']);

        if (!$blnAllow) {
            return Image::getHtml($icon, $label).' ';
        }

        return '<a href="'.$this->backend->addToUrl($href.'&amp;id='.$row['id']).'" title="'.$this->stringUtil->specialchars($title).'"'.$attributes.'>'.Image::getHtml($icon, $label).'</a> ';
    }

    #[AsCallback(table: 'tl_calendar_events', target: 'list.operations.copy.button', priority: 70)]
    public function copyIcon(array $row, string|null $href, string $label, string $title, string|null $icon, string $attributes): string
    {
        $blnAllow = $this->security->isGranted(CalendarEventsVoter::CAN_WRITE_EVENT, $row['id']);

        if (!$blnAllow) {
            return '';
        }

        return '<a href="'.$this->backend->addToUrl($href.'&amp;id='.$row['id']).'" title="'.$this->stringUtil->specialchars($title).'"'.$attributes.'>'.Image::getHtml($icon, $label).'</a> ';
    }

    /**
     * @throws \Exception
     */
    private function handleEventReleaseLevelAndPublishUnpublish(int $eventId, int $targetEventReleaseLevelId): int
    {
        $hasError = false;

        $objEvent = $this->calendarEventsModel->findByPk($eventId);

        if (null === $objEvent) {
            throw new \Exception('Event not found.');
        }

        $lastEventReleaseModel = EventReleaseLevelPolicyModel::findLastLevelByEventId($objEvent->id);

        if (null !== $lastEventReleaseModel) {
            // Display a message in the backend if the event has been published or unpublished.
            // @todo For some reason this the comparison operator will not work without type casting the id.
            if ((int) $lastEventReleaseModel->id === $targetEventReleaseLevelId) {
                if (!$objEvent->published) {
                    $this->message->addInfo(sprintf($GLOBALS['TL_LANG']['MSC']['publishedEvent'], $objEvent->id));
                }

                $objEvent->published = '1';
                $objEvent->save();

                // HOOK: publishEvent, f.ex notify the tour guide
                if (isset($GLOBALS['TL_HOOKS']['publishEvent']) && \is_array($GLOBALS['TL_HOOKS']['publishEvent'])) {
                    foreach ($GLOBALS['TL_HOOKS']['publishEvent'] as $callback) {
                        $this->system->importStatic($callback[0])->{$callback[1]}($objEvent);
                    }
                }
            } else {
                $eventReleaseModel = EventReleaseLevelPolicyModel::findByPk($targetEventReleaseLevelId);
                $firstEventReleaseModel = EventReleaseLevelPolicyModel::findFirstLevelByEventId($objEvent->id);

                if (null !== $eventReleaseModel) {
                    if ((int) $eventReleaseModel->pid !== (int) $firstEventReleaseModel->pid) {
                        $hasError = true;

                        if ($objEvent->eventReleaseLevel > 0) {
                            $targetEventReleaseLevelId = $objEvent->eventReleaseLevel;
                            $this->message->addError(sprintf('Die Freigabestufe für Event "%s (ID: %s)" konnte nicht auf "%s" geändert werden, weil diese Freigabestufe zum Event-Typ ungültig ist. ', $objEvent->title, $objEvent->id, $eventReleaseModel->title));
                        } else {
                            $targetEventReleaseLevelId = $firstEventReleaseModel->id;
                            $this->message->addError(sprintf('Die Freigabestufe für Event "%s (ID: %s)" musste auf "%s" korrigiert werden, weil eine zum Event-Typ ungültige Freigabestufe gewählt wurde. ', $objEvent->title, $objEvent->id, $firstEventReleaseModel->title));
                        }
                    }
                }

                if ($objEvent->published) {
                    $this->message->addInfo(sprintf($GLOBALS['TL_LANG']['MSC']['unpublishedEvent'], $objEvent->id));
                }

                $objEvent->published = '';
                $objEvent->save();
            }

            if (!$hasError) {
                // Display a message in the backend.
                $this->message->addInfo(sprintf($GLOBALS['TL_LANG']['MSC']['setEventReleaseLevelTo'], $objEvent->id, EventReleaseLevelPolicyModel::findByPk($targetEventReleaseLevelId)->level));
            }
        }

        return $targetEventReleaseLevelId;
    }
}
