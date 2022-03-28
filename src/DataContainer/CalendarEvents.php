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
use Contao\Encryption;
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
use Contao\UserGroupModel;
use Contao\UserModel;
use Doctrine\DBAL\Connection;
use League\Csv\CharsetConverter;
use League\Csv\Writer;
use Markocupic\SacEventToolBundle\CalendarEventsHelper;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Security\Core\Security;

class CalendarEvents extends Backend
{
    private ContaoFramework $framework;
    private RequestStack $requestStack;
    private Connection $connection;
    private Util $util;
    private Security $security;

    private Adapter $backend;
    private Adapter $calendarModel;
    private Adapter $calendarEventsModel;
    private Adapter $config;
    private Adapter $controller;
    private Adapter $date;
    private Adapter $filesModel;
    private Adapter $message;
    private Adapter $stringUtil;
    private Adapter $system;

    public function __construct(ContaoFramework $framework, RequestStack $requestStack, Connection $connection, Util $util, Security $security)
    {
        $this->framework = $framework;
        $this->requestStack = $requestStack;
        $this->connection = $connection;
        $this->util = $util;
        $this->security = $security;

        $this->backend = $this->framework->getAdapter(Backend::class);
        $this->calendarModel = $this->framework->getAdapter(CalendarModel::class);
        $this->calendarEventsModel = $this->framework->getAdapter(CalendarEventsModel::class);
        $this->config = $this->framework->getAdapter(Config::class);
        $this->controller = $this->framework->getAdapter(Controller::class);
        $this->date = $this->framework->getAdapter(Date::class);
        $this->filesModel = $this->framework->getAdapter(FilesModel::class);
        $this->message = $this->framework->getAdapter(Message::class);
        $this->stringUtil = $this->framework->getAdapter(StringUtil::class);
        $this->system = $this->framework->getAdapter(System::class);
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
            if ('event_deferred' === $objCalendarEventsModel->eventState) {
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
     * Adjust filters depending on event type.
     *
     * @Callback(table="tl_calendar_events", target="config.onload", priority=80)
     */
    public function setFilterSearchAndSortingBoard(DataContainer $dc): void
    {
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
     * @Callback(table="tl_calendar_events", target="config.onload", priority=80)
     */
    public function onloadCallbackDeleteInvalidEvents(DataContainer $dc): void
    {
        $this->connection->executeStatement(
            'DELETE FROM tl_calendar_events WHERE tstamp < ? AND tstamp > ? AND title = ?',
            [time() - 86400, 0, ''],
        );
    }

    /**
     * @Callback(table="tl_calendar_events", target="config.onload", priority=70)
     */
    public function onloadCallback(DataContainer $dc): void
    {
        $request = $this->requestStack->getCurrentRequest();

        $user = $this->security->getUser();

        // Minimize header fields for default users
        if (!$user->isAdmin) {
            $GLOBALS['TL_DCA']['tl_calendar_events']['list']['sorting']['headerFields'] = ['title'];
        }

        // Minimize operations for default users
        if (!$user->isAdmin) {
            unset($GLOBALS['TL_DCA']['tl_calendar_events']['list']['operations']['show']);
        }

        // Do not allow some specific global operations to default users
        if (!$user->isAdmin) {
            unset($GLOBALS['TL_DCA']['tl_calendar_events']['list']['global_operations']['plus1year'], $GLOBALS['TL_DCA']['tl_calendar_events']['list']['global_operations']['minus1year']);
        }

        // Special treatment for tl_calendar_events.eventReleaseLevel
        // Do not allow multi edit on tl_calendar_events.eventReleaseLevel, if user does not habe write permissions on all levels
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

                                foreach ($arrGroups as $k => $v) {
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

            if ($user->isAdmin || true === $allow) {
                PaletteManipulator::create()
                    ->addField(['eventReleaseLevel'], 'title_legend', PaletteManipulator::POSITION_APPEND)
                    ->applyToPalette('default', 'tl_calendar_events')
                ;
            }
        }

        // Skip here if the user is an admin
        if ($user->isAdmin) {
            return;
        }

        // Do not allow cutting an editing to default users
        $GLOBALS['TL_DCA']['tl_calendar_events']['list']['operations']['edit'] = null;

        // Limitize filter fields
        foreach (array_keys($GLOBALS['TL_DCA']['tl_calendar_events']['fields']) as $k) {
            if ('mountainguide' === $k || 'author' === $k || 'organizers' === $k || 'tourType' === $k || 'eventReleaseLevel' === $k || 'mainInstructor' === $k || 'courseTypeLevel0' === $k || 'startTime' === $k) {
                continue;
            }

            $GLOBALS['TL_DCA']['tl_calendar_events']['fields'][$k]['filter'] = null;
        }

        // Prevent unauthorized publishing
        if ($request->query->has('tid')) {
            $objDb = Database::getInstance()->prepare('SELECT * FROM tl_calendar_events WHERE id = ?')->execute($request->query->get('tid'));

            if ($objDb->next()) {
                if (!EventReleaseLevelPolicyModel::hasWritePermission($user->id, $objDb->id)) {
                    $this->message->addError(sprintf($GLOBALS['TL_LANG']['MSC']['missingPermissionsToPublishOrUnpublishEvent'], $objDb->id));
                    $this->controller->redirect($this->system->getReferer());
                }
            }
        }

        // Prevent unauthorized deletion
        if ('delete' === $request->query->get('act')) {
            $objDb = Database::getInstance()->prepare('SELECT * FROM tl_calendar_events WHERE id = ?')->limit(1)->execute($dc->id);

            if ($objDb->numRows) {
                if (!EventReleaseLevelPolicyModel::canDeleteEvent($user->id, $objDb->id)) {
                    $this->message->addError(sprintf($GLOBALS['TL_LANG']['MSC']['missingPermissionsToDeleteEvent'], $objDb->id));
                    $this->controller->redirect($this->system->getReferer());
                }
            }
        }

        // Prevent unauthorized editing
        if ('edit' === $request->query->get('act')) {
            $objEventsModel = $this->calendarEventsModel->findOneById($request->query->get('id'));

            if (null !== $objEventsModel) {
                if (null !== EventReleaseLevelPolicyModel::findByPk($objEventsModel->eventReleaseLevel)) {
                    if (!EventReleaseLevelPolicyModel::hasWritePermission($user->id, $objEventsModel->id) && $user->id !== $objEventsModel->registrationGoesTo) {
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
                        if (!$user->isAdmin) {
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
        }

        // Allow select mode only, if an eventReleaseLevel filter is set
        if ('select' === $request->query->get('act')) {
            /** @var AttributeBagInterface $objSessionBag */
            $objSessionBag = $request->getSession()->getBag('contao_backend');

            $session = $objSessionBag->all();

            $filter = 4 === (int) $GLOBALS['TL_DCA']['tl_calendar_events']['list']['sorting']['mode'] ? 'tl_calendar_events_'.CURRENT_ID : 'tl_calendar_events';

            if (!isset($session['filter'][$filter]['eventReleaseLevel'])) {
                $this->message->addInfo('"Mehrere bearbeiten" nur möglich, wenn ein Freigabestufen-Filter gesetzt wurde."');
                $this->controller->redirect($this->system->getReferer());
            }
        }

        // Only list record if the logged in user has write permissions
        if ('select' === $request->query->get('act') || 'editAll' === $request->query->get('act')) {
            $arrIDS = [0];

            $objDb = Database::getInstance()->prepare('SELECT * FROM tl_calendar_events WHERE pid = ?')->execute(CURRENT_ID);

            while ($objDb->next()) {
                if (EventReleaseLevelPolicyModel::hasWritePermission($user->id, $objDb->id)) {
                    $arrIDS[] = $objDb->id;
                }
            }
            $GLOBALS['TL_DCA']['tl_calendar_events']['list']['sorting']['root'] = $arrIDS;
        }

        // Do not allow editing write protected fields in editAll mode
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
     * @Callback(table="tl_calendar_events", target="config.onload", priority=60)
     */
    public function onloadCallbackSetPalettes(DataContainer $dc): void
    {
        $request = $this->requestStack->getCurrentRequest();

        if (!$dc || 'editAll' === $request->query->get('act') || 'overrideAll' === $request->query->get('act')) {
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
     * @Callback(table="tl_calendar_events", target="config.onload", priority=50)
     */
    public function onloadCallbackExportCalendar(DataContainer $dc): void
    {
        $request = $this->requestStack->getCurrentRequest();

        if ('onloadCallbackExportCalendar' === $request->query->get('action') && $request->query->get('id') > 0) {
            // Create empty document
            $csv = Writer::createFromString('');

            // Set encoding from utf-8 to is0-8859-15 (windows)
            $encoder = (new CharsetConverter())
                ->outputEncoding('iso-8859-15')
            ;

            $csv->addFormatter($encoder);

            // Set delimiter
            $csv->setDelimiter(';');

            // Selected fields
            $arrFields = ['id', 'title', 'eventDates', 'organizers', 'mainInstructor', 'instructor', 'eventType', 'tourType', 'tourTechDifficulty', 'eventReleaseLevel'];

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
                            $objUser = UserModel::findByPk($objEvent->{$field});
                            $arrRow[] = null !== $objUser ? html_entity_decode($objUser->lastname.' '.$objUser->firstname) : '';
                        } elseif ('tourTechDifficulty' === $field) {
                            $arrDiff = CalendarEventsHelper::getTourTechDifficultiesAsArray($objEvent->current(), false);
                            $arrRow[] = implode(' und ', $arrDiff);
                        } elseif ('eventDates' === $field) {
                            $arrTimestamps = CalendarEventsHelper::getEventTimestamps($objEvent->current());
                            $arrDates = array_map(
                                static fn ($tstamp) => Date::parse(Config::get('dateFormat'), $tstamp),
                                $arrTimestamps
                            );
                            $arrRow[] = implode(',', $arrDates);
                        } elseif ('organizers' === $field) {
                            $arrOrganizers = CalendarEventsHelper::getEventOrganizersAsArray($objEvent->current(), 'title');
                            $arrRow[] = html_entity_decode((string) implode(',', $arrOrganizers));
                        } elseif ('instructor' === $field) {
                            $arrInstructors = CalendarEventsHelper::getInstructorNamesAsArray($objEvent->current(), false, false);
                            $arrRow[] = html_entity_decode((string) implode(',', $arrInstructors));
                        } elseif ('tourType' === $field) {
                            $arrTourTypes = CalendarEventsHelper::getTourTypesAsArray($objEvent->current(), 'title');
                            $arrRow[] = html_entity_decode((string) implode(',', $arrTourTypes));
                        } elseif ('eventReleaseLevel' === $field) {
                            $objFS = EventReleaseLevelPolicyModel::findByPk($objEvent->{$field});
                            $arrRow[] = null !== $objFS ? $objFS->level : '';
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
     * @Callback(table="tl_calendar_events", target="config.onload", priority=40)
     */
    public function onloadCallbackShiftEventDates(DataContainer $dc): void
    {
        $request = $this->requestStack->getCurrentRequest();

        if ($request->query->get('transformDates')) {

            // $mode may be "+52weeks" or "+1year"
            $mode = $request->query->get('transformDates');

            if (false !== strtotime($mode)) {
                $calendarId = $request->query->get('id');

                $objEvent = Database::getInstance()->prepare('SELECT * FROM tl_calendar_events WHERE pid = ?')->execute($calendarId);

                while ($objEvent->next()) {
                    $set['startTime'] = strtotime($mode, (int) $objEvent->startTime);
                    $set['endTime'] = strtotime($mode, (int) $objEvent->endTime);
                    $set['startDate'] = strtotime($mode, (int) $objEvent->startDate);
                    $set['endDate'] = strtotime($mode, (int) $objEvent->endDate);

                    if ($objEvent->registrationStartDate > 0) {
                        $set['registrationStartDate'] = strtotime($mode, (int) $objEvent->registrationStartDate);
                    }

                    if ($objEvent->registrationEndDate > 0) {
                        $set['registrationEndDate'] = strtotime($mode, (int) $objEvent->registrationEndDate);
                    }

                    $arrRepeats = $this->stringUtil->deserialize($objEvent->eventDates, true);
                    $newArrRepeats = [];

                    if (\count($arrRepeats) > 0) {
                        foreach ($arrRepeats as $repeat) {
                            $repeat['new_repeat'] = strtotime($mode, (int) $repeat['new_repeat']);
                            $newArrRepeats[] = $repeat;
                        }
                        $set['eventDates'] = serialize($newArrRepeats);
                    }

                    Database::getInstance()->prepare('UPDATE tl_calendar_events %s WHERE id = ?')->set($set)->execute($objEvent->id);
                }
            }

            // Redirect
            $this->controller->redirect($this->system->getReferer());
        }
    }

    /**
     * @Callback(table="tl_calendar_events", target="config.oncreate", priority=100)
     */
    public function oncreateNew($strTable, $insertId, $set, DataContainer $dc): void
    {
        $user = $this->security->getUser();

        // Set source, add author, set first release level and & set customEventRegistrationConfirmationEmailText on creating new events
        $objEventsModel = $this->calendarEventsModel->findByPk($insertId);

        if (null !== $objEventsModel) {
            // Set source always to "default"
            $objEventsModel->source = 'default';

            // Set logged in User as author
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
     * @param $insertId
     */
    public function oncopy($insertId, DataContainer $dc): void
    {
        $user = $this->security->getUser();

        // Add author and set first release level on creating new events
        $objEventsModel = $this->calendarEventsModel->findByPk($insertId);

        if (null !== $objEventsModel) {

            // Set logged in user as author
            $objEventsModel->author = $user->id;
            $objEventsModel->eventToken = $this->generateEventToken((int) $insertId);
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
     * ondelete_callback ondeleteCallback
     * Do not allow to non-admins deleting records if there are child records (event registrations) in tl_calendar_events_member.
     *
     * @Callback(table="tl_calendar_events", target="config.ondelete", priority=100)
     */
    public function ondeleteCallback(DataContainer $dc): void
    {
        $user = $this->security->getUser();

        // Return if there is no ID
        if (!$dc->activeRecord) {
            return;
        }

        if (!$user->admin) {
            $objDb = Database::getInstance()->prepare('SELECT * FROM tl_calendar_events_member WHERE eventId = ?')->execute($dc->activeRecord->id);

            if ($objDb->numRows) {
                $this->message->addError(sprintf($GLOBALS['TL_LANG']['MSC']['deleteEventMembersBeforeDeleteEvent'], $dc->activeRecord->id));
                $this->controller->redirect($this->system->getReferer());
            }
        }
    }

    /**
     * @Callback(table="tl_calendar_events", target="config.onsubmit", priority=100)
     */
    public function adjustImageSize(DataContainer $dc): void
    {
        // Return if there is no active record (override all)
        if (!$dc->activeRecord) {
            return;
        }

        $arrSet['size'] = serialize(['', '', 11]);

        Database::getInstance()
            ->prepare('UPDATE tl_calendar_events %s WHERE id = ?')
            ->set($arrSet)
            ->execute($dc->activeRecord->id)
        ;
    }

    /**
     * @Callback(table="tl_calendar_events", target="config.onsubmit", priority=90)
     */
    public function adjustEndDate(DataContainer $dc): void
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
        $arrSet = [];
        $arrSet['eventDates'] = serialize($arrDates);
        $startTime = !empty($arrDates[0]['new_repeat']) ? $arrDates[0]['new_repeat'] : 0;
        $endTime = !empty($arrDates[\count($arrDates) - 1]['new_repeat']) ? $arrDates[\count($arrDates) - 1]['new_repeat'] : 0;

        $arrSet['endTime'] = $endTime;
        $arrSet['endDate'] = $endTime;
        $arrSet['startDate'] = $startTime;
        $arrSet['startTime'] = $startTime;

        Database::getInstance()
            ->prepare('UPDATE tl_calendar_events %s WHERE id = ?')
            ->set($arrSet)
            ->execute($dc->activeRecord->id)
        ;
    }

    /**
     * @Callback(table="tl_calendar_events", target="config.onsubmit", priority=80)
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

            Database::getInstance()
                ->prepare('UPDATE tl_calendar_events %s WHERE id = ?')
                ->set($set)
                ->execute($dc->activeRecord->id)
            ;
        }
    }

    /**
     * @Callback(table="tl_calendar_events", target="config.onsubmit", priority=70)
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

        $strToken = $this->generateEventToken((int) $dc->activeRecord->id);
        Database::getInstance()
            ->prepare('UPDATE tl_calendar_events SET eventToken = ? WHERE id = ? AND eventToken = ?')
            ->execute($strToken, $dc->activeRecord->id, '')
        ;
    }

    /**
     * @Callback(table="tl_calendar_events", target="config.onsubmit", priority=60)
     */
    public function adjustDurationInfo(DataContainer $dc): void
    {
        // Return if there is no active record (override all)
        if (!$dc->activeRecord) {
            return;
        }

        $objEvent = $this->calendarEventsModel->findByPk($dc->activeRecord->id);

        if (null !== $objEvent) {
            $arrTimestamps = CalendarEventsHelper::getEventTimestamps($objEvent);

            if ('' !== $objEvent->durationInfo && !empty($arrTimestamps) && \is_array($arrTimestamps)) {
                $countTimestamps = \count($arrTimestamps);

                if (isset($GLOBALS['TL_CONFIG']['SAC-EVENT-TOOL-CONFIG']['durationInfo'][$objEvent->durationInfo])) {
                    $arrDuration = $GLOBALS['TL_CONFIG']['SAC-EVENT-TOOL-CONFIG']['durationInfo'][$objEvent->durationInfo];

                    if (!empty($arrDuration) && \is_array($arrDuration)) {
                        $duration = $arrDuration['dateRows'];

                        if ($duration !== $countTimestamps) {
                            $arrSet = [];
                            $arrSet['durationInfo'] = '';

                            Database::getInstance()
                                ->prepare('UPDATE tl_calendar_events %s WHERE id = ?')
                                ->set($arrSet)->execute($objEvent->id)
                            ;

                            $this->message->addError(sprintf('Die Event-Dauer in "%s" [ID:%s] stimmt nicht mit der Anzahl Event-Daten überein. Setzen SIe für jeden Event-Tag eine Datumszeile!', $objEvent->title, $objEvent->id), TL_MODE);
                        }
                    }
                }
            }
        }
    }

    /**
     * @Callback(table="tl_calendar_events", target="config.onsubmit", priority=50)
     */
    public function adjustRegistrationPeriod(DataContainer $dc): void
    {
        // Return if there is no active record (override all)
        if (!$dc->activeRecord) {
            return;
        }

        $objDb = Database::getInstance()
            ->prepare('SELECT * FROM tl_calendar_events WHERE id = ?')
            ->limit(1)
            ->execute($dc->activeRecord->id)
        ;

        if ($objDb->numRows > 0) {
            if ($objDb->setRegistrationPeriod) {
                $regEndDate = $objDb->registrationEndDate;
                $regStartDate = $objDb->registrationStartDate;

                if ($regEndDate > $objDb->startDate) {
                    $regEndDate = $objDb->startDate;
                    $this->message->addInfo($GLOBALS['TL_LANG']['MSC']['patchedEndDatePleaseCheck'], TL_MODE);
                }

                if ($regStartDate > $regEndDate) {
                    $regStartDate = $regEndDate - 86400;
                    $this->message->addInfo($GLOBALS['TL_LANG']['MSC']['patchedStartDatePleaseCheck'], TL_MODE);
                }
                $arrSet['registrationStartDate'] = $regStartDate;
                $arrSet['registrationEndDate'] = $regEndDate;

                Database::getInstance()
                    ->prepare('UPDATE tl_calendar_events %s WHERE id = ?')
                    ->set($arrSet)->execute($objDb->id)
                ;
            }
        }
    }

    /**
     * @Callback(table="tl_calendar_events", target="config.onsubmit", priority=40)
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
                                $set = ['eventReleaseLevel' => $oEventReleaseLevelModel->id];

                                Database::getInstance()
                                    ->prepare('UPDATE tl_calendar_events %s WHERE id = ?')
                                    ->set($set)->execute($objEvent->id)
                                ;
                            }
                        }
                    }
                } else {
                    // Add eventReleaseLevel when creating a new event...
                    $oEventReleaseLevelModel = EventReleaseLevelPolicyModel::findFirstLevelByEventId($objEvent->id);

                    $set = ['eventReleaseLevel' => $oEventReleaseLevelModel->id];

                    Database::getInstance()
                        ->prepare('UPDATE tl_calendar_events %s WHERE id = ?')
                        ->set($set)
                        ->execute($objEvent->id)
                    ;
                }
            }
        }
        // End set correct eventReleaseLevel

        // Set filledInEventReportForm, now the invoice form can be printed in tl_calendar_events_instructor_invoice
        if ('writeTourReport' === $request->query->get('call')) {

            $set = ['filledInEventReportForm' => '1'];

            Database::getInstance()
                ->prepare('UPDATE tl_calendar_events %s WHERE id = ?')
                ->set($set)
                ->execute($dc->activeRecord->id)
            ;
        }

        $set = ['alias' => 'event-'.$dc->id];

        Database::getInstance()
            ->prepare('UPDATE tl_calendar_events %s WHERE id = ?')
            ->set($set)
            ->execute($dc->activeRecord->id)
        ;
    }

    /**
     * @Callback(table="tl_calendar_events", target="fields.alias.input_field", priority=100)
     */
    public function showFieldValue(DataContainer $dc): string
    {
        $field = $dc->field;

        $strTable = 'tl_calendar_events';

        if (!\strlen((string) $dc->activeRecord->id)) {
            return '';
        }
        $intId = $dc->activeRecord->id;

        $objRow = Database::getInstance()
            ->prepare('SELECT '.$field.' FROM tl_calendar_events WHERE id = ?')
            ->limit(1)
            ->execute($intId)
        ;

        if ($objRow->numRows < 1) {
            return '';
        }

        $return = '';
        $row = $objRow->row();

        // Get the order fields
        $objDcaExtractor = DcaExtractor::getInstance($strTable);
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
            if (isset($GLOBALS['TL_DCA'][$strTable]['fields'][$i]['eval']['encrypt']) && true === $GLOBALS['TL_DCA'][$strTable]['fields'][$i]['eval']['encrypt']) {
                $value = Encryption::decrypt($value);
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
                        $objUser = UserModel::findByPk($arrInstructor['instructorId']);

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
                        $objDiff = Database::getInstance()
                            ->prepare('SELECT * FROM tl_tour_difficulty WHERE id = ?')
                            ->limit(1)
                            ->execute((int) ($difficulty['tourTechDifficultyMin']))
                        ;

                        if ($objDiff->numRows) {
                            $strDiff = $objDiff->shortcut;
                        }
                        $objDiff = Database::getInstance()
                            ->prepare('SELECT * FROM tl_tour_difficulty WHERE id = ?')
                            ->limit(1)
                            ->execute((int) $difficulty['tourTechDifficultyMax'])
                        ;

                        if ($objDiff->numRows) {
                            $max = $objDiff->shortcut;
                            $strDiff .= ' - '.$max;
                        }

                        $arrDiff[] = $strDiff;
                    } elseif (\strlen((string) $difficulty['tourTechDifficultyMin'])) {

                        $objDiff = Database::getInstance()
                            ->prepare('SELECT * FROM tl_tour_difficulty WHERE id = ?')
                            ->limit(1)
                            ->execute((int) $difficulty['tourTechDifficultyMin'])
                        ;

                        if ($objDiff->numRows) {
                            $strDiff = $objDiff->shortcut;
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
                    $objKey = Database::getInstance()
                        ->prepare('SELECT '.$chunks[1].' AS value FROM '.$chunks[0].' WHERE id = ?')
                        ->limit(1)
                        ->execute($v)
                    ;

                    if ($objKey->numRows) {
                        $temp[] = $objKey->value;
                    }
                }

                $row[$i] = implode(', ', $temp);
            } elseif ('fileTree' === $GLOBALS['TL_DCA'][$strTable]['fields'][$i]['inputType'] || \in_array($i, $arrOrder, true)) {
                if (\is_array($value)) {
                    foreach ($value as $kk => $vv) {
                        if (($objFile = $this->filesModel->findByUuid($vv)) instanceof FilesModel) {
                            $value[$kk] = $objFile->path.' ('.$this->stringUtil->binToUuid($vv).')';
                        } else {
                            $value[$kk] = '';
                        }
                    }

                    $row[$i] = implode('<br>', $value);
                } else {
                    if (($objFile = $this->filesModel->findByUuid($value)) instanceof FilesModel) {
                        $row[$i] = $objFile->path.' ('.$this->stringUtil->binToUuid($value).')';
                    } else {
                        $row[$i] = '';
                    }
                }
            } elseif (\is_array($value)) {
                if (2 === \count($value) && isset($value['value'], $value['unit'])) {
                    $row[$i] = trim($value['value'].$value['unit']);
                } else {
                    foreach ($value as $kk => $vv) {
                        if (\is_array($vv)) {
                            $vals = array_values($vv);
                            $value[$kk] = array_shift($vals).' ('.implode(', ', array_filter($vals)).')';
                        }
                    }

                    $row[$i] = implode('<br>', $value);
                }
            } elseif ('date' === ($GLOBALS['TL_DCA'][$strTable]['fields'][$i]['eval']['rgxp'] ?? false)) {
                $row[$i] = $value ? $this->date->parse($this->config->get('dateFormat'), $value) : '-';
            } elseif ('time' === ($GLOBALS['TL_DCA'][$strTable]['fields'][$i]['eval']['rgxp'] ?? false)) {
                $row[$i] = $value ? $this->date->parse($this->config->get('timeFormat'), $value) : '-';
            } elseif ('datim' === ($GLOBALS['TL_DCA'][$strTable]['fields'][$i]['eval']['rgxp'] ?? false) || (isset($GLOBALS['TL_DCA'][$strTable]['fields'][$i]['flag']) && \in_array($GLOBALS['TL_DCA'][$strTable]['fields'][$i]['flag'], [5, 6, 7, 8, 9, 10], false)) || 'tstamp' === $i) {
                $row[$i] = $value ? $this->date->parse($this->config->get('datimFormat'), $value) : '-';
            } elseif ('checkbox' === ($GLOBALS['TL_DCA'][$strTable]['fields'][$i]['inputType'] ?? false) && true !== ($GLOBALS['TL_DCA'][$strTable]['fields'][$i]['eval']['multiple'] ?? false)) {
                $row[$i] = $value ? $GLOBALS['TL_LANG']['MSC']['yes'] : $GLOBALS['TL_LANG']['MSC']['no'];
            } elseif ('email' === ($GLOBALS['TL_DCA'][$strTable]['fields'][$i]['eval']['rgxp'] ?? false)) {
                $row[$i] = Idna::decodeEmail($value);
            } elseif ('textarea' === ($GLOBALS['TL_DCA'][$strTable]['fields'][$i]['inputType'] ?? false) && (true === ($GLOBALS['TL_DCA'][$strTable]['fields'][$i]['eval']['allowHtml'] ?? false) || true === ($GLOBALS['TL_DCA'][$strTable]['fields'][$i]['eval']['preserveTags'] ?? false))) {
                $row[$i] = $this->stringUtil->specialchars($value);
            } elseif (isset($GLOBALS['TL_DCA'][$strTable]['fields'][$i]['reference']) && \is_array($GLOBALS['TL_DCA'][$strTable]['fields'][$i]['reference'])) {
                $row[$i] = isset($GLOBALS['TL_DCA'][$strTable]['fields'][$i]['reference'][$row[$i]]) ? (\is_array($GLOBALS['TL_DCA'][$strTable]['fields'][$i]['reference'][$row[$i]]) ? $GLOBALS['TL_DCA'][$strTable]['fields'][$i]['reference'][$row[$i]][0] : $GLOBALS['TL_DCA'][$strTable]['fields'][$i]['reference'][$row[$i]]) : $row[$i];
            } elseif (true === ($GLOBALS['TL_DCA'][$strTable]['fields'][$i]['eval']['isAssociative'] ?? false) || (isset($GLOBALS['TL_DCA'][$strTable]['fields'][$i]['options']) && ArrayUtil::isAssoc($GLOBALS['TL_DCA'][$strTable]['fields'][$i]['options']))) {
                $row[$i] = $GLOBALS['TL_DCA'][$strTable]['fields'][$i]['options'][$row[$i]];
            } else {
                $row[$i] = $value;
            }

            // Label and help
            if (isset($GLOBALS['TL_DCA'][$strTable]['fields'][$i]['label'])) {
                $label = \is_array($GLOBALS['TL_DCA'][$strTable]['fields'][$i]['label']) ? $GLOBALS['TL_DCA'][$strTable]['fields'][$i]['label'][0] : $GLOBALS['TL_DCA'][$strTable]['fields'][$i]['label'];
                $help = \is_array($GLOBALS['TL_DCA'][$strTable]['fields'][$i]['label']) ? $GLOBALS['TL_DCA'][$strTable]['fields'][$i]['label'][1] : $GLOBALS['TL_DCA'][$strTable]['fields'][$i]['label'];
            } else {
                $label = (isset($GLOBALS['TL_LANG']['MSC'][$i]) && \is_array($GLOBALS['TL_LANG']['MSC'][$i])) ? $GLOBALS['TL_LANG']['MSC'][$i][0] : $GLOBALS['TL_LANG']['MSC'][$i];
                $help = (isset($GLOBALS['TL_LANG']['MSC'][$i]) && \is_array($GLOBALS['TL_LANG']['MSC'][$i])) ? $GLOBALS['TL_LANG']['MSC'][$i][1] : $GLOBALS['TL_LANG']['MSC'][$i];
            }

            if (empty($label)) {
                $label = $i;
            }

            if (!empty($help)) {
                $help = '<p class="tl_help tl_tip">'.$help.'</p>';
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
    public function loadCallbackEventDates(?string $arrValues, DataContainer $dc): array
    {
        if ('' !== $arrValues) {
            $arrValues = $this->stringUtil->deserialize($arrValues, true);

            if (isset($arrValues[0])) {
                if ($arrValues[0]['new_repeat'] <= 0) {
                    // Replace invalid date with empty string
                    $arrValues = '';
                }
            }
        }

        return $arrValues;
    }

    /**
     * buttons_callback buttonsCallback.
     *
     * @Callback(table="tl_calendar_events", target="edit.buttons", priority=100)
     */
    public function buttonsCallback($arrButtons, $dc)
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
     * @Callback(table="tl_calendar_events", target="fields.organizers.options", priority=100)
     */
    public function optionsCallbackGetOrganizers(): array
    {
        $arrOptions = [];
        $objOrganizer = Database::getInstance()
            ->prepare('SELECT * FROM tl_event_organizer ORDER BY sorting')
            ->execute()
        ;

        while ($objOrganizer->next()) {
            $arrOptions[$objOrganizer->id] = $objOrganizer->title;
        }

        return $arrOptions;
    }

    /**
     * @Callback(table="tl_calendar_events", target="fields.courseTypeLevel0.options", priority=100)
     */
    public function optionsCallbackCourseTypeLevel0(): array
    {
        $arrOpt = [];
        $objDatabase = Database::getInstance()
            ->execute('SELECT * FROM tl_course_main_type ORDER BY code')
        ;

        while ($objDatabase->next()) {
            $arrOpt[$objDatabase->id] = $objDatabase->name;
        }

        return $arrOpt;
    }

    public function getTourDifficulties(): array
    {
        $options = [];
        $objDb = Database::getInstance()
            ->execute('SELECT * FROM tl_tour_difficulty ORDER BY pid ASC, code ASC')
        ;

        while ($objDb->next()) {
            $objDiffCat = TourDifficultyCategoryModel::findByPk($objDb->pid);

            if (null !== $objDiffCat) {
                if ('' !== $objDiffCat->title) {
                    if (!isset($options[$objDiffCat->title])) {
                        $options[$objDiffCat->title] = [];
                    }

                    $options[$objDiffCat->title][$objDb->id] = $objDb->shortcut;
                }
            }
        }

        return $options;
    }

    /**
     * @Callback(table="tl_calendar_events", target="fields.eventType.options", priority=100)
     */
    public function getEventTypes(?DataContainer $dc): array
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

        if (null !== $objCalendar && null !== $user) {
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

        if (null !== $objCalendar) {
            $options = $this->stringUtil->deserialize($objCalendar->allowedEventTypes, true);
        }

        return $options;
    }

    /**
     * @Callback(table="tl_calendar_events", target="fields.courseTypeLevel1.options", priority=100)
     */
    public function getCourseSubType(?DataContainer $dc): array
    {
        $options = [];

        if (!$dc) {
            return $options;
        }

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
     * @Callback(table="tl_calendar_events", target="fields.eventReleaseLevel.options", priority=100)
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
                            $objEventReleaseLevels = Database::getInstance()
                                ->prepare('SELECT * FROM tl_event_release_level_policy WHERE pid = ? ORDER BY level ASC')
                                ->execute($objEventReleasePackage->id)
                            ;

                            while ($objEventReleaseLevels->next()) {
                                $options[EventReleaseLevelPolicyModel::findByPk($objEventReleaseLevels->id)->getRelated('pid')->title][$objEventReleaseLevels->id] = $objEventReleaseLevels->title;
                            }
                        }
                    }
                }
            } else {
                $objEventReleaseLevels = Database::getInstance()
                    ->prepare('SELECT * FROM tl_event_release_level_policy ORDER BY pid,level ASC')
                    ->execute()
                ;

                while ($objEventReleaseLevels->next()) {
                    $options[EventReleaseLevelPolicyModel::findByPk($objEventReleaseLevels->id)->getRelated('pid')->title][$objEventReleaseLevels->id] = $objEventReleaseLevels->title;
                }
            }
        }

        return $options;
    }

    /**
     * multicolumnwizard columnsCallback listFixedDates().
     *
     * @return array|null
     */
    public function listFixedDates()
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

    private function generateEventToken(int $eventId): string
    {
        return md5((string) random_int(100000000, 999999999)).'-'.$eventId;
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
        $objUser = UserModel::findByPk($arrRow['mainInstructor']);

        if (null !== $objUser) {
            $strAuthor = ' <span style="color:#b3b3b3;padding-left:3px">[Hauptleiter: '.$objUser->name.']</span><br>';
        }

        $strRegistrations = CalendarEventsHelper::getEventStateOfSubscriptionBadgesString($objEvent);

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
     */
    public function releaseLevelNext(array $row, ?string $href, string $label, string $title, ?string $icon, string $attributes): string
    {
        $request = $this->requestStack->getCurrentRequest();
        $user = $this->security->getUser();

        $strDirection = 'up';

        $canPushToNextReleaseLevel = false;
        $objReleaseLevelModel = EventReleaseLevelPolicyModel::findByPk($row['eventReleaseLevel']);
        $nextReleaseLevel = null;

        if (null !== $objReleaseLevelModel) {
            $nextReleaseLevel = $objReleaseLevelModel->level + 1;
        }

        // Save to database
        if ('releaseLevelNext' === $request->query->get('action') && (int) $request->query->get('eventId') === (int) $row['id']) {
            if (true === EventReleaseLevelPolicyModel::allowSwitchingEventReleaseLevel($user->id, $row['id'], 'up') && true === EventReleaseLevelPolicyModel::levelExists($row['id'], $nextReleaseLevel)) {
                $objEvent = $this->calendarEventsModel->findByPk($request->query->get('eventId'));

                if (null !== $objEvent) {
                    $objReleaseLevelModel = EventReleaseLevelPolicyModel::findNextLevel($objEvent->eventReleaseLevel);

                    if (null !== $objReleaseLevelModel) {
                        $objEvent->eventReleaseLevel = $objReleaseLevelModel->id;
                        $objEvent->save();
                        $this->saveCallbackEventReleaseLevel($objEvent->eventReleaseLevel, null, $objEvent->id);

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

        if (true === EventReleaseLevelPolicyModel::allowSwitchingEventReleaseLevel($user->id, $row['id'], $strDirection) && true === EventReleaseLevelPolicyModel::levelExists($row['id'], $nextReleaseLevel)) {
            $canPushToNextReleaseLevel = true;
        }

        if (false === $canPushToNextReleaseLevel) {
            return '';
        }

        return '<a href="'.$this->backend->addToUrl($href.'&amp;eventId='.$row['id']).'" title="'.$this->stringUtil->specialchars($title).'"'.$attributes.'>'.Image::getHtml($icon, $label).'</a> ';
    }

    /**
     * Update main instructor (first instructor in the list is the main instructor).
     *
     * @param mixed $varValue
     *
     * @return mixed
     * @Callback(table="tl_calendar_events", target="fields.instructor.save", priority=100)
     */
    public function saveCallbackSetMaininstructor($varValue, DataContainer $dc)
    {
        if (isset($dc) && $dc->id > 0) {
            $arrInstructors = $this->stringUtil->deserialize($varValue, true);

            // Use a child table to store instructors
            // Delete instructor
            Database::getInstance()
                ->prepare('DELETE FROM tl_calendar_events_instructor WHERE pid = ?')
                ->execute($dc->id)
            ;

            $i = 0;

            foreach ($arrInstructors as $arrInstructor) {
                // Rebuild instructor table
                $set = [
                    'pid' => $dc->id,
                    'userId' => $arrInstructor['instructorId'],
                    'tstamp' => time(),
                    'isMainInstructor' => $i < 1 ? '1' : '',
                ];

                Database::getInstance()->prepare('INSERT INTO tl_calendar_events_instructor %s')
                    ->set($set)
                    ->execute()
                ;

                ++$i;
            }
            // End child insert

            if (\count($arrInstructors) > 0) {
                $intInstructor = $arrInstructors[0]['instructorId'];

                if (null !== UserModel::findByPk($intInstructor)) {

                    $set = ['mainInstructor' => $intInstructor];

                    Database::getInstance()
                        ->prepare('UPDATE tl_calendar_events %s WHERE id = ?')
                        ->set($set)
                        ->execute($dc->id)
                    ;

                    return $varValue;
                }
            }

            $set = ['mainInstructor' => 0];

            Database::getInstance()
                ->prepare('UPDATE tl_calendar_events %s WHERE id = ?')
                ->set($set)
                ->execute($dc->id)
            ;
        }

        return $varValue;
    }

    /**
     * Publish or unpublish events if eventReleaseLevel has reached the highest/last level.
     *
     * @param $targetEventReleaseLevelId
     * @param DataContainer $dc
     * @param null          $eventId
     *
     * @return mixed
     *
     * @Callback(table="tl_calendar_events", target="fields.eventReleaseLevel.save", priority=100)
     */
    public function saveCallbackEventReleaseLevel($targetEventReleaseLevelId, DataContainer $dc = null, $eventId = null)
    {
        $hasError = false;
        // Get event id
        if ($dc && $dc->activeRecord->id > 0) {
            $objEvent = $this->calendarEventsModel->findByPk($dc->activeRecord->id);
        } elseif ($eventId > 0) {
            $objEvent = $this->calendarEventsModel->findByPk($eventId);
        }

        if (null !== $objEvent) {
            $lastEventReleaseModel = EventReleaseLevelPolicyModel::findLastLevelByEventId($objEvent->id);

            if (null !== $lastEventReleaseModel) {
                // Display message in the backend if event is published or unpublished now
                if ((int) $lastEventReleaseModel->id === (int) $targetEventReleaseLevelId) {
                    if (!$objEvent->published) {
                        $this->message->addInfo(sprintf($GLOBALS['TL_LANG']['MSC']['publishedEvent'], $objEvent->id));
                    }
                    $objEvent->published = '1';

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
                        if ($eventReleaseModel->pid !== $firstEventReleaseModel->pid) {
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
                }

                $objEvent->save();

                if (!$hasError) {
                    // Display message in the backend
                    $this->message->addInfo(sprintf($GLOBALS['TL_LANG']['MSC']['setEventReleaseLevelTo'], $objEvent->id, EventReleaseLevelPolicyModel::findByPk($targetEventReleaseLevelId)->level));
                }
            }
        }

        return $targetEventReleaseLevelId;
    }

    /**
     * @param int|null $eventId
     *
     * @return mixed
     *
     * @Callback(table="tl_calendar_events", target="fields.eventType.save", priority=100)
     */
    public function saveCallbackEventType(string $strEventType, ?DataContainer $dc, $eventId = null)
    {
        if ('' !== $strEventType) {
            // Get event id
            if ($dc->activeRecord->id > 0) {
                $objEvent = $this->calendarEventsModel->findByPk($dc->activeRecord->id);
            } elseif ($eventId > 0) {
                $objEvent = $this->calendarEventsModel->findByPk($eventId);
            }
            // !important, because if eventType is not saved, then no eventReleaseLevel can be assigned
            $objEvent->eventType = $strEventType;
            $objEvent->save();

            if (null !== $objEvent) {
                if (null === EventReleaseLevelPolicyModel::findByPk($objEvent->eventReleaseLevel)) {
                    $objEventReleaseModel = EventReleaseLevelPolicyModel::findFirstLevelByEventId($objEvent->id);

                    if (null !== $objEventReleaseModel) {
                        $objEvent->eventReleaseLevel = $objEventReleaseModel->id;
                        $objEvent->save();
                    }
                }
            }
        }

        return $strEventType;
    }

    /**
     * Downgrade event to the previous release level.
     *
     * @Callback(table="tl_calendar_events", target="list.operations.releaseLevelPrev.button", priority=100)
     */
    public function releaseLevelPrev(array $row, ?string $href, string $label, string $title, ?string $icon, string $attributes): string
    {
        $request = $this->requestStack->getCurrentRequest();
        $user = $this->security->getUser();

        $strDirection = 'down';

        $canPushToNextReleaseLevel = false;
        $prevReleaseLevel = null;
        $objReleaseLevelModel = EventReleaseLevelPolicyModel::findByPk($row['eventReleaseLevel']);

        if (null !== $objReleaseLevelModel) {
            $prevReleaseLevel = $objReleaseLevelModel->level - 1;
        }

        // Save to database
        if ('releaseLevelPrev' === $request->query->get('action') && (int) $request->query->get('eventId') === (int) $row['id']) {
            if (true === EventReleaseLevelPolicyModel::allowSwitchingEventReleaseLevel($user->id, $row['id'], 'down') && true === EventReleaseLevelPolicyModel::levelExists($row['id'], $prevReleaseLevel)) {
                $objEvent = $this->calendarEventsModel->findByPk($request->query->get('eventId'));

                if (null !== $objEvent) {
                    $objReleaseLevelModel = EventReleaseLevelPolicyModel::findPrevLevel($objEvent->eventReleaseLevel);

                    if (null !== $objReleaseLevelModel) {
                        $objEvent->eventReleaseLevel = $objReleaseLevelModel->id;
                        $objEvent->save();
                        $this->saveCallbackEventReleaseLevel($objEvent->eventReleaseLevel, null, $objEvent->id);

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

        if (true === EventReleaseLevelPolicyModel::allowSwitchingEventReleaseLevel($user->id, $row['id'], $strDirection) && true === EventReleaseLevelPolicyModel::levelExists($row['id'], $prevReleaseLevel)) {
            $canPushToNextReleaseLevel = true;
        }

        if (false === $canPushToNextReleaseLevel) {
            return '';
        }

        return '<a href="'.$this->backend->addToUrl($href.'&amp;eventId='.$row['id']).'" title="'.$this->stringUtil->specialchars($title).'"'.$attributes.'>'.Image::getHtml($icon, $label).'</a> ';
    }

    /**
     * @Callback(table="tl_calendar_events", target="list.operations.delete.button", priority=100)
     */
    public function deleteIcon(array $row, ?string $href, string $label, string $title, ?string $icon, string $attributes): string
    {
        $user = $this->security->getUser();

        $blnAllow = EventReleaseLevelPolicyModel::canDeleteEvent($user->id, $row['id']);

        if (!$blnAllow) {
            return '';
        }

        return '<a href="'.$this->backend->addToUrl($href.'&amp;id='.$row['id']).'" title="'.$this->stringUtil->specialchars($title).'"'.$attributes.'>'.Image::getHtml($icon, $label).'</a> ';
    }

    /**
     * @Callback(table="tl_calendar_events", target="list.operations.copy.button", priority=100)
     */
    public function copyIcon(array $row, ?string $href, string $label, string $title, ?string $icon, string $attributes): string
    {
        $user = $this->security->getUser();

        $blnAllow = EventReleaseLevelPolicyModel::hasWritePermission($user->id, $row['id']);

        if (!$blnAllow) {
            return '';
        }

        return '<a href="'.$this->backend->addToUrl($href.'&amp;id='.$row['id']).'" title="'.$this->stringUtil->specialchars($title).'"'.$attributes.'>'.Image::getHtml($icon, $label).'</a> ';
    }
}
