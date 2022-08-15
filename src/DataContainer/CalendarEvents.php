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

namespace Markocupic\SacEventToolBundle\DataContainer;

use Contao\ArrayUtil;
use Contao\Backend;
use Contao\BackendUser;
use Contao\Calendar;
use Contao\CalendarEventsJourneyModel;
use Contao\CalendarEventsModel;
use Contao\CalendarModel;
use Contao\Config;
use Contao\Controller;
use Contao\CoreBundle\DataContainer\PaletteManipulator;
use Contao\CoreBundle\Framework\Adapter;
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\CoreBundle\ServiceAnnotation\Callback;
use Contao\Database;
use Contao\DataContainer;
use Contao\Date;
use Contao\DcaExtractor;
use Contao\EventReleaseLevelPolicyModel;
use Contao\EventReleaseLevelPolicyPackageModel;
use Contao\EventTypeModel;
use Contao\FilesModel;
use Contao\Idna;
use Contao\Image;
use Contao\Message;
use Contao\StringUtil;
use Contao\System;
use Contao\TourDifficultyCategoryModel;
use Contao\User;
use Contao\UserGroupModel;
use Contao\UserModel;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;
use League\Csv\CannotInsertRecord;
use League\Csv\CharsetConverter;
use League\Csv\InvalidArgument;
use League\Csv\Writer;
use Markocupic\SacEventToolBundle\CalendarEventsHelper;
use Markocupic\SacEventToolBundle\Config\EventState;
use Markocupic\SacEventToolBundle\Security\Voter\CalendarEventsVoter;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\PasswordHasher\Hasher\PasswordHasherFactoryInterface;
use Symfony\Component\Security\Core\Security;

class CalendarEvents
{
    private ContaoFramework $framework;
    private RequestStack $requestStack;
    private Connection $connection;
    private Util $util;
    private Security $security;
    private PasswordHasherFactoryInterface $passwordHasherFactory;

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

    public function __construct(ContaoFramework $framework, RequestStack $requestStack, Connection $connection, Util $util, Security $security, PasswordHasherFactoryInterface $passwordHasherFactory)
    {
        $this->framework = $framework;
        $this->requestStack = $requestStack;
        $this->connection = $connection;
        $this->util = $util;
        $this->security = $security;
        $this->passwordHasherFactory = $passwordHasherFactory;

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
     * Set correct referer.
     *
     * @Callback(table="tl_calendar_events", target="config.onload", priority=100)
     */
    public function setCorrectReferer(): void
    {
        $this->util->setCorrectReferer();
    }

    /**
     * Set palette on creating new.
     *
     * @Callback(table="tl_calendar_events", target="config.onload", priority=90)
     */
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

            /** @todo Continue allowing members to register even after registration deadline has expired */

            // If event has been deferred
            if (EventState::STATE_DEFERRED === $objCalendarEventsModel->eventState) {
                PaletteManipulator::create()
                    ->applyToPalette('default', 'tl_calendar_events')
                    ->applyToPalette('tour', 'tl_calendar_events')
                    ->applyToPalette('lastMinuteTour', 'tl_calendar_events')
                    ->applyToPalette('generalEvent', 'tl_calendar_events')
                    ->applyToPalette('course', 'tl_calendar_events')
                ;
            }
        }
    }

    /**
     * Limitize filter fields to tour guides and course instructors
     * and
     * adjust filters depending on event type.
     *
     * @Callback(table="tl_calendar_events", target="config.onload", priority=80)
     */
    public function setFilterSearchAndSortingBoard(DataContainer $dc): void
    {
        $user = $this->security->getUser();

        if (!$user->admin) {
            // Limitize filter fields tour guides and course instructors
            foreach (array_keys($GLOBALS['TL_DCA']['tl_calendar_events']['fields']) as $k) {
                if ('mountainguide' === $k || 'author' === $k || 'organizers' === $k || 'tourType' === $k || 'journey' === $k || 'eventReleaseLevel' === $k || 'mainInstructor' === $k || 'courseTypeLevel0' === $k || 'startTime' === $k) {
                    continue;
                }

                $GLOBALS['TL_DCA']['tl_calendar_events']['fields'][$k]['filter'] = null;
            }
        }

        if (\defined('CURRENT_ID') && CURRENT_ID > 0) {
            $objCalendar = $this->calendarModel->findByPk(CURRENT_ID);

            if (null !== $objCalendar) {
                $arrAllowedEventTypes = $this->stringUtil->deserialize($objCalendar->allowedEventTypes, true);

                if (!\in_array('tour', $arrAllowedEventTypes, true) && !\in_array('lastMinuteTour', $arrAllowedEventTypes, true)) {
                    $GLOBALS['TL_DCA']['tl_calendar_events']['fields']['tourType']['filter'] = false;
                    $GLOBALS['TL_DCA']['tl_calendar_events']['fields']['tourType']['search'] = false;
                    $GLOBALS['TL_DCA']['tl_calendar_events']['fields']['tourType']['sorting'] = false;
                }

                if (!\in_array('course', $arrAllowedEventTypes, true)) {
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
     * @Callback(table="tl_calendar_events", target="config.onload", priority=70)
     *
     * @throws Exception
     */
    public function onloadCallbackDeleteInvalidEvents(DataContainer $dc): void
    {
        $this->connection->executeStatement(
            'DELETE FROM tl_calendar_events WHERE tstamp < ? AND tstamp > ? AND title = ?',
            [time() - 86400, 0, ''],
        );
    }

    /**
     * @Callback(table="tl_calendar_events", target="config.onload", priority=60)
     *
     * @throws Exception
     */
    public function onloadCallback(DataContainer $dc): void
    {
        $request = $this->requestStack->getCurrentRequest();

        $user = $this->security->getUser();

        // Minimize header fields for default users
        if (!$user->admin) {
            $GLOBALS['TL_DCA']['tl_calendar_events']['list']['sorting']['headerFields'] = ['title'];
        }

        // Minimize operations for default users
        if (!$user->admin) {
            unset($GLOBALS['TL_DCA']['tl_calendar_events']['list']['operations']['show']);
        }

        // Do not allow some specific global operations to default users
        if (!$user->admin) {
            unset($GLOBALS['TL_DCA']['tl_calendar_events']['list']['global_operations']['plus1year'], $GLOBALS['TL_DCA']['tl_calendar_events']['list']['global_operations']['minus1year']);
        }

        // Special treatment for tl_calendar_events.eventReleaseLevel
        // Do not allow multi edit on tl_calendar_events.eventReleaseLevel, if user doesn't have write-permissions on all levels.
        if ('editAll' === $request->query->get('act') || 'overrideAll' === $request->query->get('act')) {
            $allow = true;
            $session = $request->getSession()->get('CURRENT');
            $arrIDS = $session['IDS'];

            foreach ($arrIDS as $eventId) {
                $objEvent = $this->calendarEventsModel->findByPk($eventId);

                if (null !== $objEvent) {
                    $objEventReleaseLevelPolicyPackageModel = EventReleaseLevelPolicyPackageModel::findReleaseLevelPolicyPackageModelByEventId($eventId);

                    if (null !== $objEventReleaseLevelPolicyPackageModel) {
                        $objReleaseLevelModel = EventReleaseLevelPolicyModel::findByPid($objEventReleaseLevelPolicyPackageModel->id);

                        if (null !== $objReleaseLevelModel) {
                            while ($objReleaseLevelModel->next()) {
                                $allow = false;
                                $arrGroupsUserBelongsTo = $this->stringUtil->deserialize($user->groups, true);
                                $arrGroups = $this->stringUtil->deserialize($objReleaseLevelModel->groupReleaseLevelRights, true);

                                foreach ($arrGroups as $v) {
                                    if (\in_array($v['group'], $arrGroupsUserBelongsTo, false)) {
                                        if ('upAndDown' === $v['releaseLevelRights']) {
                                            $allow = true;
                                            continue;
                                        }
                                    }
                                }
                            }
                        }
                    }
                }
            }

            if ($user->admin || true === $allow) {
                PaletteManipulator::create()
                    ->addField(['eventReleaseLevel'], 'title_legend', PaletteManipulator::POSITION_APPEND)
                    ->applyToPalette('default', 'tl_calendar_events')
                ;
            }
        }

        // Prevent unauthorized deletion
        if ('delete' === $request->query->get('act')) {
            $eventId = $this->connection->fetchOne('SELECT id FROM tl_calendar_events WHERE id = ?', [$dc->id]);

            if ($eventId) {
                if (false === $this->security->isGranted(CalendarEventsVoter::CAN_DELETE_EVENT, $eventId)) {
                    $this->message->addError(sprintf($GLOBALS['TL_LANG']['MSC']['missingPermissionsToDeleteEvent'], $eventId));
                    $this->controller->redirect($this->system->getReferer());
                }
            }
        }

        // Skip here if the user is an admin
        if ($user->admin) {
            return;
        }

        // Do not allow cutting and editing to default users
        $GLOBALS['TL_DCA']['tl_calendar_events']['list']['operations']['edit'] = null;

        // Prevent unauthorized publishing
        if ($request->query->has('tid')) {
            $tid = $request->query->get('tid');
            $eventId = $this->connection->fetchOne('SELECT id FROM tl_calendar_events WHERE id = ?', [$tid]);

            if ($eventId && false === $this->security->isGranted(CalendarEventsVoter::CAN_WRITE_EVENT, $eventId)) {
                $this->message->addError(sprintf($GLOBALS['TL_LANG']['MSC']['missingPermissionsToPublishOrUnpublishEvent'], $eventId));
                $this->controller->redirect($this->system->getReferer());
            }
        }

        // Prevent unauthorized deletion
        if ('delete' === $request->query->get('act')) {
            $eventId = $this->connection->fetchOne('SELECT id FROM tl_calendar_events WHERE id = ?', [$dc->id]);

            if ($eventId) {
                if (false === $this->security->isGranted(CalendarEventsVoter::CAN_DELETE_EVENT, $eventId)) {
                    $this->message->addError(sprintf($GLOBALS['TL_LANG']['MSC']['missingPermissionsToDeleteEvent'], $eventId));
                    $this->controller->redirect($this->system->getReferer());
                }
            }
        }

        // Prevent unauthorized editing
        if ('edit' === $request->query->get('act')) {
            $objEventsModel = $this->calendarEventsModel->findByPk($request->query->get('id'));

            if (null !== $objEventsModel) {
                if (null !== EventReleaseLevelPolicyModel::findByPk($objEventsModel->eventReleaseLevel)) {
                    if (false === $this->security->isGranted(CalendarEventsVoter::CAN_WRITE_EVENT, $objEventsModel->id) && $user->id !== $objEventsModel->registrationGoesTo) {
                        // User has no write access to the datarecord, that's why we display field values without a form input
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

        // Only list record if the logged-in user has write-permissions.
        if ('select' === $request->query->get('act') || 'editAll' === $request->query->get('act')) {
            $arrIDS = [0];

            $ids = $this->connection->fetchFirstColumn('SELECT id FROM tl_calendar_events WHERE pid = ?', [CURRENT_ID]);

            foreach ($ids as $id) {
                if (true === $this->security->isGranted(CalendarEventsVoter::CAN_WRITE_EVENT, $id)) {
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
                $objEventsModel = $this->calendarEventsModel->findByPk($arrIDS[1]);

                if (null !== $objEventsModel) {
                    if ($objEventsModel->eventReleaseLevel > 0) {
                        $objEventReleaseLevelPolicyModel = EventReleaseLevelPolicyModel::findFirstLevelByEventId($objEventsModel->id);

                        if (null !== $objEventReleaseLevelPolicyModel) {
                            if ($objEventReleaseLevelPolicyModel->id !== $objEventsModel->eventReleaseLevel) {
                                foreach (array_keys($GLOBALS['TL_DCA']['tl_calendar_events']['fields']) as $fieldname) {
                                    if (true === $GLOBALS['TL_DCA']['tl_calendar_events']['fields'][$fieldname]['allowEditingOnFirstReleaseLevelOnly']) {
                                        if ('editAll' === $request->query->get('act')) {
                                            $GLOBALS['TL_DCA']['tl_calendar_events']['fields'][$fieldname]['input_field_callback'] = [self::class, 'showFieldValue'];
                                        } else {
                                            unset($GLOBALS['TL_DCA']['tl_calendar_events']['fields'][$fieldname]);
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
     *
     * @Callback(table="tl_calendar_events", target="config.onload", priority=50)
     */
    public function onloadCallbackSetPalettes(DataContainer $dc): void
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
     * CSV-export of all events of a calendar.
     *
     * @Callback(table="tl_calendar_events", target="config.onload", priority=40)
     *
     * @throws CannotInsertRecord
     * @throws InvalidArgument
     */
    public function onloadCallbackExportCalendar(DataContainer $dc): void
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
            $arrFields = ['id', 'title', 'eventDates', 'organizers', 'mainInstructor', 'instructor', 'eventType', 'tourType', 'tourTechDifficulty', 'eventReleaseLevel', 'journey'];

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
                            $arrDiff = $this->calendarEventsHelper->getTourTechDifficultiesAsArray($objEvent->current(), false);
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
     * https://somehost/contao?do=sac_calendar_events_tool&table=tl_calendar_events&id=21&transformDate=+52weeks&rt=hUFF18TV1YCLddb-Cyb48dRH8y_9iI-BgM-Nc1rB8o8&ref=2sjHl6mB.
     *
     * @Callback(table="tl_calendar_events", target="config.onload", priority=30)
     *
     * @throws Exception
     */
    public function onloadCallbackShiftEventDates(DataContainer $dc): void
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
     * @Callback(table="tl_calendar_events", target="config.oncreate", priority=100)
     */
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
     * @Callback(table="tl_calendar_events", target="config.oncopy", priority=100)
     *
     * @throws \Exception
     */
    public function onCopy(int $insertId, DataContainer $dc): void
    {
        $user = $this->security->getUser();

        // Add author and set first release level on creating new events
        $objEventsModel = $this->calendarEventsModel->findByPk($insertId);

        if (null !== $objEventsModel) {
            // Set logged-in user as author
            $objEventsModel->author = $user->id;
            $objEventsModel->eventToken = $this->generateEventToken($insertId);
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
     * @throws \Exception
     */
    private function generateEventToken(int $eventId): string
    {
        return md5((string) random_int(100000000, 999999999)).'-'.$eventId;
    }

    /**
     * Do not allow to non-admins deleting records if there are child records (event registrations) in tl_calendar_events_member.
     *
     * @Callback(table="tl_calendar_events", target="config.ondelete", priority=100)
     *
     * @throws Exception
     */
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
     * Add a priority of -1
     * In this way this callback will be executed after! the legacy callback tl_calendar_events.adjustTime().
     *
     * @Callback(table="tl_calendar_events", target="config.onsubmit", priority=-1)
     *
     * @throws Exception
     */
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
     * @Callback(table="tl_calendar_events", target="config.onsubmit", priority=80)
     *
     * @throws Exception
     */
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
     * @Callback(table="tl_calendar_events", target="config.onsubmit", priority=70)
     *
     * @throws Exception
     * @throws \Exception
     */
    public function setEventToken(DataContainer $dc): void
    {
        // Return if there is no active record (override all)
        if (!$dc->activeRecord) {
            return;
        }

        $objEvent = $this->calendarEventsModel->findByPk($dc->activeRecord->id);

        if (null !== $objEvent) {
            if (false === strpos($objEvent->eventToken, '-'.$dc->activeRecord->id)) {
                $objEvent->eventToken = $this->generateEventToken((int) $dc->activeRecord->id);
                $objEvent->save();
            }
        }

        $strEventToken = $this->generateEventToken((int) $dc->activeRecord->id);
        $set = ['eventToken' => $strEventToken];
        $dc->activeRecord->eventToken = $strEventToken;

        $this->connection->update('tl_calendar_events', $set, ['id' => $dc->activeRecord->id, 'eventToken' => '']);
    }

    /**
     * @Callback(table="tl_calendar_events", target="config.onsubmit", priority=60)
     *
     * @throws Exception
     */
    public function adjustDurationInfo(DataContainer $dc): void
    {
        // Return if there is no active record (override all)
        if (!$dc->activeRecord) {
            return;
        }

        $objEvent = $this->calendarEventsModel->findByPk($dc->activeRecord->id);

        if (null !== $objEvent) {
            $arrTimestamps = $this->calendarEventsHelper->getEventTimestamps($objEvent);

            if ('' !== $objEvent->durationInfo && !empty($arrTimestamps) && \is_array($arrTimestamps)) {
                $countTimestamps = \count($arrTimestamps);

                if (isset($GLOBALS['TL_CONFIG']['SAC-EVENT-TOOL-CONFIG']['durationInfo'][$objEvent->durationInfo])) {
                    $arrDuration = $GLOBALS['TL_CONFIG']['SAC-EVENT-TOOL-CONFIG']['durationInfo'][$objEvent->durationInfo];

                    if (!empty($arrDuration) && \is_array($arrDuration)) {
                        $duration = $arrDuration['dateRows'];

                        if ($duration !== $countTimestamps) {
                            $set = ['durationInfo' => ''];
                            $dc->activeRecord->durationInfo = '';
                            $this->connection->update('tl_calendar_events', $set, ['id' => $objEvent->id]);

                            $this->message->addError(sprintf('Die Event-Dauer in "%s" [ID:%s] stimmt nicht mit der Anzahl Event-Daten überein. Setzen Sie für jeden Event-Tag eine Datumszeile!', $objEvent->title, $objEvent->id), TL_MODE);
                        }
                    }
                }
            }
        }
    }

    /**
     * @Callback(table="tl_calendar_events", target="config.onsubmit", priority=50)
     *
     * @throws Exception
     */
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
                    $regEndDate = $row['startDate'];
                    $this->message->addInfo($GLOBALS['TL_LANG']['MSC']['patchedEndDatePleaseCheck'], TL_MODE);
                }

                if ($regStartDate > $regEndDate) {
                    $regStartDate = $regEndDate - 86400;
                    $this->message->addInfo($GLOBALS['TL_LANG']['MSC']['patchedStartDatePleaseCheck'], TL_MODE);
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
     * @Callback(table="tl_calendar_events", target="config.onsubmit", priority=40)
     *
     * @throws Exception
     */
    public function setAlias(DataContainer $dc): void
    {
        $request = $this->requestStack->getCurrentRequest();

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
        // End set correct eventReleaseLevel

        // Set filledInEventReportForm, now the invoice form can be printed in tl_calendar_events_instructor_invoice
        if ('writeTourReport' === $request->query->get('call')) {
            $set = [
                'filledInEventReportForm' => '1',
            ];

            $this->connection->update('tl_calendar_events', $set, ['id' => $dc->activeRecord->id]);
        }

        $set = [
            'alias' => 'event-'.$dc->id,
        ];

        $this->connection->update('tl_calendar_events', $set, ['id' => $dc->activeRecord->id]);
    }

    /**
     * @Callback(table="tl_calendar_events", target="fields.alias.input_field", priority=100)
     *
     * @throws Exception
     */
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
                $passwordHasher = $this->passwordHasherFactory
                    ->getPasswordHasher(User::class)
                ;
                $value = $passwordHasher->hash($value);
            }

            // Default value
            $row[$i] = '';

            // Get the field value
            if ('eventType' === $i) {
                $row[$i] = $value;
            } elseif ('eventState' === $i) {
                $row[$i] = '' === $value ? '---' : $value;
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
                $label = \is_array($GLOBALS['TL_DCA'][$strTable]['fields'][$i]['label']) ? $GLOBALS['TL_DCA'][$strTable]['fields'][$i]['label'][0] : $GLOBALS['TL_DCA'][$strTable]['fields'][$i]['label'];
                $help = \is_array($GLOBALS['TL_DCA'][$strTable]['fields'][$i]['label']) ? $GLOBALS['TL_DCA'][$strTable]['fields'][$i]['label'][1] : $GLOBALS['TL_DCA'][$strTable]['fields'][$i]['label'];
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

        // Return Html
        return $return;
    }

    /**
     * @Callback(table="tl_calendar_events", target="fields.eventDates.load", priority=100)
     */
    public function loadCallbackEventDates(string|null $varValue, DataContainer $dc): array
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

    /**
     * @Callback(table="tl_calendar_events", target="edit.buttons", priority=100)
     */
    public function editButtons($arrButtons, $dc)
    {
        $request = $this->requestStack->getCurrentRequest();

        if ('writeTourReport' === $request->query->get('call')) {
            unset($arrButtons['saveNcreate'], $arrButtons['saveNduplicate'], $arrButtons['saveNedit']);
        }

        return $arrButtons;
    }

    /**
     * @Callback(table="tl_calendar_events", target="fields.durationInfo.options", priority=100)
     */
    public function optionsCallbackGetEventDuration(): array
    {
        if (!empty($GLOBALS['TL_CONFIG']['SAC-EVENT-TOOL-CONFIG']['durationInfo']) && \is_array($GLOBALS['TL_CONFIG']['SAC-EVENT-TOOL-CONFIG']['durationInfo'])) {
            $opt = $GLOBALS['TL_CONFIG']['SAC-EVENT-TOOL-CONFIG']['durationInfo'];
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
     * @Callback(table="tl_calendar_events", target="fields.organizers.options", priority=90)
     *
     * @throws Exception
     */
    public function optionsCallbackGetOrganizers(): array
    {
        return $this->connection
            ->fetchAllKeyValue('SELECT id,title FROM tl_event_organizer ORDER BY sorting')
        ;
    }

    /**
     * @Callback(table="tl_calendar_events", target="fields.courseTypeLevel0.options", priority=80)
     *
     * @throws Exception
     */
    public function optionsCallbackCourseTypeLevel0(): array
    {
        return $this->connection
            ->fetchAllKeyValue('SELECT id,name FROM tl_course_main_type ORDER BY code')
        ;
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
     * @Callback(table="tl_calendar_events", target="fields.eventType.options", priority=70)
     *
     * @throws \Exception
     */
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
     * @Callback(table="tl_calendar_events", target="fields.courseTypeLevel1.options", priority=60)
     *
     * @throws Exception
     */
    public function getCourseSubType(DataContainer $dc): array
    {
        $options = [];

        $eventId = $this->connection
            ->fetchOne('SELECT courseTypeLevel0 FROM tl_calendar_events WHERE id = ?', [$dc->id])
        ;

        if ($eventId) {
            $stmt = $this->connection
                ->executeQuery('SELECT * FROM tl_course_sub_type WHERE pid = ? ORDER BY pid, code', [$eventId])
            ;

            while (false !== ($row = $stmt->fetchAssociative())) {
                $options[$row['id']] = $row['code'].' '.$row['name'];
            }
        }

        return $options;
    }

    /**
     * @Callback(table="tl_calendar_events", target="fields.eventReleaseLevel.options", priority=50)
     *
     * @throws Exception
     * @throws \Exception
     */
    public function getReleaseLevels(DataContainer $dc): array
    {
        $options = [];

        $objUser = BackendUser::getInstance();
        $arrAllowedEventTypes = [];

        if (null !== $objUser) {
            if (!$objUser->admin) {
                $arrGroups = $this->stringUtil->deserialize($objUser->groups, true);

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

                            // Get the referring method, because nested filter won't work in the filter panel
                            $trace = debug_backtrace();
                            $referringMethod = $trace[2]['function'];

                            while (false !== ($rowEventReleaseLevels = $stmt->fetchAssociative())) {
                                if ('panel' === $referringMethod) {
                                    // Nested options won't work in the filter panel
                                    $options[$rowEventReleaseLevels['id']] = $rowEventReleaseLevels['title'];
                                } else {
                                    $options[EventReleaseLevelPolicyModel::findByPk($rowEventReleaseLevels['id'])->getRelated('pid')->title][$rowEventReleaseLevels['id']] = $rowEventReleaseLevels['title'];
                                }
                            }
                        }
                    }
                }
            } else {
                $stmt = $this->connection->executeQuery('SELECT * FROM tl_event_release_level_policy ORDER BY pid,level');

                // Get the referring method, because nested filter won't work in the filter panel
                $trace = debug_backtrace();
                $referringMethod = $trace[2]['function'];

                while (false !== ($rowEventReleaseLevels = $stmt->fetchAssociative())) {
                    if ('panel' === $referringMethod) {
                        // Nested options won't work in the filter panel
                        $options[$rowEventReleaseLevels['id']] = $rowEventReleaseLevels['title'];
                    } else {
                        $options[EventReleaseLevelPolicyModel::findByPk($rowEventReleaseLevels['id'])->getRelated('pid')->title][$rowEventReleaseLevels['id']] = $rowEventReleaseLevels['title'];
                    }
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

    /**
     * @Callback(table="tl_calendar_events", target="list.sorting.child_record", priority=100)
     */
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
            $strLevel = sprintf('<span class="release-level-%s" title="Freigabestufe: %s">FS: %s</span> ', $eventReleaseLevelModel->level, $eventReleaseLevelModel->title, $eventReleaseLevelModel->level);
        }

        return '<div class="tl_content_left">'.$icon.' '.$strLevel.$arrRow['title'].' <span style="color:#999;padding-left:3px">['.$date.']</span>'.$strAuthor.$strRegistrations.'</div>';
    }

    /**
     * Push event to next release level.
     *
     * @Callback(table="tl_calendar_events", target="list.operations.releaseLevelNext.button", priority=100)
     *
     * @throws \Exception
     */
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
                    $objReleaseLevelModel = EventReleaseLevelPolicyModel::findNextLevel($objEvent->eventReleaseLevel);

                    if (null !== $objReleaseLevelModel) {
                        $objEvent->eventReleaseLevel = $objReleaseLevelModel->id;
                        $objEvent->save();

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

        if (true === $this->security->isGranted(CalendarEventsVoter::CAN_UPGRADE_EVENT_RELEASE_LEVEL, $row['id']) && true === EventReleaseLevelPolicyModel::levelExists($row['id'], $nextReleaseLevel)) {
            $canPushToNextReleaseLevel = true;
        }

        if (false === $canPushToNextReleaseLevel) {
            return '';
        }

        return '<a href="'.$this->backend->addToUrl($href.'&amp;eventId='.$row['id']).'" title="'.$this->stringUtil->specialchars($title).'"'.$attributes.'>'.Image::getHtml($icon, $label).'</a> ';
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

                // HOOK: publishEvent, f.ex advice tourenchef by email
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

    /**
     * Update main instructor (the first instructor in the list is the main instructor).
     *
     * @throws Exception
     *
     * @Callback(table="tl_calendar_events", target="fields.instructor.save", priority=100)
     */
    public function saveCallbackSetMaininstructor(string|null $varValue, DataContainer $dc): string|null
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
     * Publish or unpublish events if eventReleaseLevel has reached the highest/last level.
     *
     * @throws \Exception
     *
     * @Callback(table="tl_calendar_events", target="fields.eventReleaseLevel.save", priority=90)
     */
    public function saveCallbackEventReleaseLevel(int $targetEventReleaseLevelId, DataContainer $dc): int
    {
        return $this->handleEventReleaseLevelAndPublishUnpublish((int) $dc->activeRecord->id, $targetEventReleaseLevelId);
    }

    /**
     * @throws \Exception
     *
     * @Callback(table="tl_calendar_events", target="fields.eventType.save", priority=80)
     */
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
     * @Callback(table="tl_calendar_events", target="list.operations.releaseLevelPrev.button", priority=90)
     *
     * @throws \Exception
     */
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
                    $objReleaseLevelModel = EventReleaseLevelPolicyModel::findPrevLevel($objEvent->eventReleaseLevel);

                    if (null !== $objReleaseLevelModel) {
                        $objEvent->eventReleaseLevel = $objReleaseLevelModel->id;
                        $objEvent->save();

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
            return '';
        }

        return '<a href="'.$this->backend->addToUrl($href.'&amp;eventId='.$row['id']).'" title="'.$this->stringUtil->specialchars($title).'"'.$attributes.'>'.Image::getHtml($icon, $label).'</a> ';
    }

    /**
     * @Callback(table="tl_calendar_events", target="list.operations.delete.button", priority=80)
     */
    public function deleteIcon(array $row, string|null $href, string $label, string $title, string|null $icon, string $attributes): string
    {
        $blnAllow = $this->security->isGranted(CalendarEventsVoter::CAN_DELETE_EVENT, $row['id']);

        if (!$blnAllow) {
            return '';
        }

        return '<a href="'.$this->backend->addToUrl($href.'&amp;id='.$row['id']).'" title="'.$this->stringUtil->specialchars($title).'"'.$attributes.'>'.Image::getHtml($icon, $label).'</a> ';
    }

    /**
     * @Callback(table="tl_calendar_events", target="list.operations.copy.button", priority=70)
     */
    public function copyIcon(array $row, string|null $href, string $label, string $title, string|null $icon, string $attributes): string
    {
        $blnAllow = $this->security->isGranted(CalendarEventsVoter::CAN_WRITE_EVENT, $row['id']);

        if (!$blnAllow) {
            return '';
        }

        return '<a href="'.$this->backend->addToUrl($href.'&amp;id='.$row['id']).'" title="'.$this->stringUtil->specialchars($title).'"'.$attributes.'>'.Image::getHtml($icon, $label).'</a> ';
    }
}
