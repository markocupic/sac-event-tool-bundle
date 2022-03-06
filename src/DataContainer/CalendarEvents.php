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

use Contao\Backend;
use Contao\BackendUser;
use Contao\Calendar;
use Contao\CalendarEventsModel;
use Contao\CalendarModel;
use Contao\Config;
use Contao\Controller;
use Contao\CoreBundle\DataContainer\PaletteManipulator;
use Contao\CoreBundle\Exception\NoContentResponseException;
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
use Contao\Input;
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
use MenAtWork\MultiColumnWizardBundle\Contao\Widgets\MultiColumnWizard;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Security\Core\Security;

class CalendarEvents
{
    private Util $util;
    private ContaoFramework $framework;
    private RequestStack $requestStack;
    private Security $security;
    private Connection $connection;

    public function __construct(Util $util, ContaoFramework $framework, RequestStack $requestStack, Security $security, Connection $connection)
    {
        $this->util = $util;
        $this->framework = $framework;
        $this->requestStack = $requestStack;
        $this->security = $security;
        $this->connection = $connection;

        // Adapters
        $this->backend = $framework->getAdapter(Backend::class);
        $this->controller = $framework->getAdapter(Controller::class);
        $this->system = $framework->getAdapter(System::class);
        $this->message = $framework->getAdapter(Message::class);
        $this->config = $framework->getAdapter(Config::class);
        $this->stringUtil = $framework->getAdapter(StringUtil::class);
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
     * Manipulate palette when creating a new data record.
     *
     * @Callback(table="tl_calendar_events", target="config.onload", priority=90)
     */
    public function setPaletteWhenCreatingNew(DataContainer $dc): void
    {
        $request = $this->requestStack->getCurrentRequest();

        if ('edit' === $request->query->get('act')) {
            $objCalendarEventsModel = CalendarEventsModel::findByPk($dc->id);

            if (null !== $objCalendarEventsModel) {
                if (0 === (int) $objCalendarEventsModel->tstamp && empty($objCalendarEventsModel->eventType)) {
                    $GLOBALS['TL_DCA']['tl_calendar_events']['palettes']['default'] = 'eventType';
                }
            }

            /** @todo Den Teilnehmern weiterhin ermöglichen, sich anzumelden, auch wenn das Enddatum abgelaufen ist */
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
     * Display filters depending on event type.
     *
     * @Callback(table="tl_calendar_events", target="config.onload", priority=120)
     */
    public function setFilterSearchAndSortingBoard(DataContainer $dc): void
    {
        if (\defined('CURRENT_ID') && CURRENT_ID > 0) {
            $objCalendar = CalendarModel::findByPk(CURRENT_ID);

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
     * @Callback(table="tl_calendar_events", target="config.onload", priority=110)
     */
    public function onloadCallbackDeleteInvalidEvents(DataContainer $dc): void
    {
        $this->connection->executeStatement(
            'DELETE FROM tl_calendar_events WHERE tstamp < ? AND tstamp > ? AND title = ?',
            [time() - 24 * 60 * 60, 0, '']
        );
    }

    /**
     * Onload callback.
     *
     * @Callback(table="tl_calendar_events", target="config.onload", priority=100)
     */
    public function onloadCallback(DataContainer $dc): void
    {
        $user = $this->security->getUser();

        $request = $this->requestStack->getCurrentRequest();

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
            $objSession = $this->requestStack->getCurrentRequest()->getSession();
            $session = $objSession->get('CURRENT');
            $arrIDS = $session['IDS'];

            foreach ($arrIDS as $eventId) {
                $objEvent = CalendarEventsModel::findByPk($eventId);

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
            $id = $this->connection->fetchOne('SELECT id FROM tl_calendar_events WHERE id = ?', [$request->query->get('tid')]);

            if ($id) {
                if (!EventReleaseLevelPolicyModel::hasWritePermission($user->id, $id)) {
                    $this->message->addError(sprintf($GLOBALS['TL_LANG']['MSC']['missingPermissionsToPublishOrUnpublishEvent'], $id));
                    $this->controller->redirect($this->system->getReferer());
                }
            }
        }

        // Prevent unauthorized deletion
        if ('delete' === $request->query->get('act')) {
            $id = $this->connection->fetchOne('SELECT id FROM tl_calendar_events WHERE id = ?', [$dc->id]);

            if ($id) {
                if (!EventReleaseLevelPolicyModel::canDeleteEvent($user->id, $id)) {
                    $this->message->addError(sprintf($GLOBALS['TL_LANG']['MSC']['missingPermissionsToDeleteEvent'], $id));
                    $this->controller->redirect($this->system->getReferer());
                }
            }
        }

        // Prevent unauthorized editing
        if ('edit' === $request->query->get('act')) {
            $id = $request->query->get('id');
            $objEventsModel = CalendarEventsModel::findOneById($id);

            if (null !== $objEventsModel) {
                if (null !== EventReleaseLevelPolicyModel::findByPk($objEventsModel->eventReleaseLevel)) {
                    if (!EventReleaseLevelPolicyModel::hasWritePermission($user->id, $objEventsModel->id) && $user->id !== $objEventsModel->registrationGoesTo) {
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
                        if (!$user->isAdmin) {
                            $objEventReleaseLevelPolicyPackageModel = EventReleaseLevelPolicyPackageModel::findReleaseLevelPolicyPackageModelByEventId($objEventsModel->id);

                            if (null !== $objEventReleaseLevelPolicyPackageModel) {
                                if ($objEventsModel->eventReleaseLevel > 0) {
                                    $objEventReleaseLevelPolicyModel = EventReleaseLevelPolicyModel::findFirstLevelByEventId($objEventsModel->id);

                                    if (null !== $objEventReleaseLevelPolicyModel) {
                                        if ($objEventReleaseLevelPolicyModel->id !== $objEventsModel->eventReleaseLevel) {
                                            foreach (array_keys($GLOBALS['TL_DCA']['tl_calendar_events']['fields']) as $fieldname) {
                                                if (true === $GLOBALS['TL_DCA']['tl_calendar_events']['fields'][$fieldname]['allowEditingOnFirstReleaseLevelOnly'] && '' !== $GLOBALS['TL_DCA']['tl_calendar_events']['fields'][$fieldname]['inputType']) {
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
            $bag = $objSession->getBag('contao_backend');

            $arrBag = $bag->all();

            $filter = 4 === (int) $GLOBALS['TL_DCA']['tl_calendar_events']['list']['sorting']['mode'] ? 'tl_calendar_events_'.CURRENT_ID : 'tl_calendar_events';

            if (!isset($arrBag['filter'][$filter]['eventReleaseLevel'])) {
                $this->message->addInfo('"Mehrere bearbeiten" nur möglich, wenn ein Freigabestufen-Filter gesetzt wurde."');
                $this->controller->redirect($this->system->getReferer());

                return;
            }
        }

        // Only list records where the logged in user has write permissions
        if ('select' === $request->query->get('act') || 'editAll' === $request->query->get('act')) {
            $arrIDS = [0];
            $ids = $this->connection->fetchFirstColumn('SELECT id FROM tl_calendar_events WHERE pid = ?', [CURRENT_ID]);

            if ($ids && !empty($ids)) {
                $arrIDS = array_merge($arrIDS, $ids);
            }

            $GLOBALS['TL_DCA']['tl_calendar_events']['list']['sorting']['root'] = $arrIDS;
        }

        // Do not allow editing write protected fields in editAll mode
        // Use input_field_callback to only display the field values without the form input field
        if ('editAll' === $request->query->get('act') || 'overrideAll' === $request->query->get('act')) {
            $session = $objSession->get('CURRENT');
            $arrIDS = $session['IDS'];

            if (!empty($arrIDS) && \is_array($arrIDS)) {
                $objEventsModel = CalendarEventsModel::findByPk($arrIDS[1]);

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
     * @Callback(table="tl_calendar_events", target="config.onload", priority=80)
     */
    public function onloadCallbackSetPalettes(DataContainer $dc): void
    {
        if (!$dc) {
            return;
        }

        $request = $this->requestStack->getCurrentRequest();

        if ('editAll' === $request->query->get('act') || 'overrideAll' === $request->query->get('act')) {
            return;
        }

        if ('writeTourReport' === $request->query->get('call')) {
            $GLOBALS['TL_DCA']['tl_calendar_events']['palettes']['default'] = $GLOBALS['TL_DCA']['tl_calendar_events']['palettes']['tour_report'];

            return;
        }

        // Set palette for tour and course
        $objCalendarEventsModel = CalendarEventsModel::findByPk($dc->id);

        if (null !== $objCalendarEventsModel) {
            if (isset($GLOBALS['TL_DCA']['tl_calendar_events']['palettes'][$objCalendarEventsModel->eventType])) {
                $GLOBALS['TL_DCA']['tl_calendar_events']['palettes']['default'] = $GLOBALS['TL_DCA']['tl_calendar_events']['palettes'][$objCalendarEventsModel->eventType];
            }
        }
    }

    /**
     * CSV export all events of a calendar.
     *
     * @Callback(table="tl_calendar_events", target="config.onload", priority=70)
     */
    public function onloadCallbackExportCalendar(DataContainer $dc): void
    {
        $request = $this->requestStack->getCurrentRequest();

        if ('onloadCallbackExportCalendar' === $request->query->get('action') && $request->query->has('id')) {
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

            $objEvent = CalendarEventsModel::findBy(
                ['tl_calendar_events.pid=?'],
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
                            $arrRow[] = html_entity_decode(implode(',', $arrOrganizers));
                        } elseif ('instructor' === $field) {
                            $arrInstructors = CalendarEventsHelper::getInstructorNamesAsArray($objEvent->current(), false, false);
                            $arrRow[] = html_entity_decode(implode(',', $arrInstructors));
                        } elseif ('tourType' === $field) {
                            $arrTourTypes = CalendarEventsHelper::getTourTypesAsArray($objEvent->current(), 'title');
                            $arrRow[] = html_entity_decode(implode(',', $arrTourTypes));
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

            $objCalendar = CalendarModel::findByPk($request->query->get('id'));

            $csv->output($objCalendar->title.'.csv');

            throw new NoContentResponseException();
        }
    }

    /**
     * Shift all event dates of a certain calendar by +/- 1 year
     * contao?do=sac_calendar_events_tool&table=tl_calendar_events&id=21&transformDate=+52weeks&rt=hUFF18TV1YCLddb-Cyb48dRH8y_9iI-BgM-Nc1rB8o8&ref=2sjHl6mB.
     *
     * @Callback(table="tl_calendar_events", target="config.onload", priority=60)
     */
    public function shiftEventDates(DataContainer $dc): void
    {
        $request = $this->requestStack->getCurrentRequest();

        if ($dc && $request->query->get('transformDates')) {
            // $mode may be "+52weeks" or "+1year"
            $mode = $request->query->get('transformDates');

            if (false !== strtotime($mode)) {
                $calendarId = $dc->id;

                $stmt = $this->connection->executeQuery(
                    'SELECT * FROM tl_calendar_events WHERE pid = ?',
                    [$calendarId]
                );

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
     * Oncreate callback.
     *
     * @Callback(table="tl_calendar_events", target="config.oncreate", priority=100)
     */
    public function setEmailTextOnCreatingNew(string $strTable, int $insertId, array $set, DataContainer $dc): void
    {
        $user = $this->security->getUser();

        // Set source, add author, set first release level and & set customEventRegistrationConfirmationEmailText on creating new events
        $objEventsModel = CalendarEventsModel::findByPk($insertId);

        if (null !== $objEventsModel) {
            // Set source always to "default"
            $objEventsModel->source = 'default';

            // Set logged in user as author
            $objEventsModel->author = $user->id;
            $objEventsModel->mainInstructor = $user->id;
            $objEventsModel->instructor = serialize([['instructorId' => $user->id]]);

            // Set customEventRegistrationConfirmationEmailText
            $objEventsModel->customEventRegistrationConfirmationEmailText = str_replace('{{br}}', "\n", System::getContainer()->getParameter('sacevt.event.accept_registration_email_body'));

            $objEventsModel->save();
        }
    }

    /**
     * @Callback(table="tl_calendar_events", target="config.oncopy", priority=100)
     */
    public function oncopyCallback(int $insertId, DataContainer $dc): void
    {
        $user = $this->security->getUser();

        // Add author and set first release level on creating new events
        $objEventsModel = CalendarEventsModel::findByPk($insertId);

        if (null !== $objEventsModel) {
            // Set logged in user as author
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
     * Do not allow non-admins deleting records
     * if there are child records (event registrations) in tl_calendar_events_member.
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
            $result = $this->connection->fetchOne('SELECT * FROM tl_calendar_events_member WHERE eventId = ?', [$dc->activeRecord->id]);

            if ($result) {
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

        $set['size'] = serialize(['', '', 11]);
        $this->connection->update('tl_calendar_events', $set, ['id' => $dc->activeRecord->id]);
    }

    /**
     * Adjust enddate.
     *
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
            // Save as timestamp
            $arrDates[] = ['new_repeat' => $v];
        }

        $set = [];
        $set['eventDates'] = serialize($arrDates);
        $startTime = !empty($arrDates[0]['new_repeat']) ? $arrDates[0]['new_repeat'] : 0;
        $endTime = !empty($arrDates[\count($arrDates) - 1]['new_repeat']) ? $arrDates[\count($arrDates) - 1]['new_repeat'] : 0;

        $set['endTime'] = $endTime;
        $set['endDate'] = $endTime;
        $set['startDate'] = $startTime;
        $set['startTime'] = $startTime;

        $this->connection->update('tl_calendar_events', $set, ['id' => $dc->activeRecord->id]);
    }

    /**
     * Adjust event release level.
     *
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
            $this->connection->update('tl_calendar_events', $set, ['id' => $dc->activeRecord->id]);
        }
    }

    /**
     * Set the event token.
     *
     * @Callback(table="tl_calendar_events", target="config.onsubmit", priority=70)
     */
    public function setEventToken(DataContainer $dc): void
    {
        // Return if there is no active record (override all)
        if (!$dc->activeRecord) {
            return;
        }

        $objEvent = CalendarEventsModel::findByPk($dc->activeRecord->id);

        if (null !== $objEvent) {
            if (false === strpos($objEvent->eventToken, '-'.$dc->activeRecord->id)) {
                $objEvent->eventToken = $this->generateEventToken($dc->activeRecord->id);
                $objEvent->save();
            }
        }

        $strToken = $this->generateEventToken($dc->activeRecord->id);

        $this->connection->executeStatement(
            'UPDATE tl_calendar_events SET eventToken = ? WHERE id = ? AND eventToken = ?',
            [$strToken, $dc->activeRecord->id, '']
        );
    }

    /**
     * Adjust duration info.
     *
     * @Callback(table="tl_calendar_events", target="config.onsubmit", priority=60)
     */
    public function adjustDurationInfo(DataContainer $dc): void
    {
        // Return if there is no active record (override all)
        if (!$dc->activeRecord) {
            return;
        }

        $objEvent = CalendarEventsModel::findByPk($dc->activeRecord->id);

        if (null !== $objEvent) {
            $arrTimestamps = CalendarEventsHelper::getEventTimestamps($objEvent);

            if ('' !== $objEvent->durationInfo && !empty($arrTimestamps) && \is_array($arrTimestamps)) {
                $countTimestamps = \count($arrTimestamps);

                if (isset($GLOBALS['TL_CONFIG']['SAC-EVENT-TOOL-CONFIG']['durationInfo'][$objEvent->durationInfo])) {
                    $arrDuration = $GLOBALS['TL_CONFIG']['SAC-EVENT-TOOL-CONFIG']['durationInfo'][$objEvent->durationInfo];

                    if (!empty($arrDuration) && \is_array($arrDuration)) {
                        $duration = $arrDuration['dateRows'];

                        if ($duration !== $countTimestamps) {
                            $set = [];
                            $set['durationInfo'] = '';

                            $this->connection->update('tl_calendar_events', $set, ['id' => $objEvent->id]);
                            $this->message->addError(sprintf('Die Event-Dauer in "%s" [ID:%s] stimmt nicht mit der Anzahl Event-Daten überein. Setzen SIe für jeden Event-Tag eine Datumszeile!', $objEvent->title, $objEvent->id), TL_MODE);
                        }
                    }
                }
            }
        }
    }

    /**
     * Adjust registration period.
     *
     * @Callback(table="tl_calendar_events", target="config.onsubmit", priority=50)
     */
    public function adjustRegistrationPeriod(DataContainer $dc): void
    {
        // Return if there is no active record (override all)
        if (!$dc->activeRecord) {
            return;
        }

        $arrRow = $this->connection->fetchAssociative('SELECT * FROM tl_calendar_events WHERE id = ?', [$dc->activeRecord->id]);

        if ($arrRow) {
            if ($arrRow['setRegistrationPeriod']) {
                $regEndDate = $arrRow['registrationEndDate'];
                $regStartDate = $arrRow['registrationStartDate'];

                if ($regEndDate > $arrRow['startDate']) {
                    $regEndDate = $arrRow['startDate'];
                    $this->message->addInfo($GLOBALS['TL_LANG']['MSC']['patchedEndDatePleaseCheck'], TL_MODE);
                }

                if ($regStartDate > $regEndDate) {
                    $regStartDate = $regEndDate - 86400;
                    $this->message->addInfo($GLOBALS['TL_LANG']['MSC']['patchedStartDatePleaseCheck'], TL_MODE);
                }

                $set = [];
                $set['registrationStartDate'] = $regStartDate;
                $set['registrationEndDate'] = $regEndDate;

                $this->connection->update('tl_calendar_events', $set, ['id' => $arrRow['id']]);
            }
        }
    }

    /**
     * Set correct event release level.
     *
     * @Callback(table="tl_calendar_events", target="config.onsubmit", priority=50)
     */
    public function setEventReleaseLevel(DataContainer $dc): void
    {
        $objEvent = CalendarEventsModel::findByPk($dc->activeRecord->id);

        if (null !== $objEvent) {
            if ('' !== $objEvent->eventType) {
                if ($objEvent->eventReleaseLevel > 0) {
                    $objEventReleaseLevel = EventReleaseLevelPolicyModel::findByPk($objEvent->eventReleaseLevel);

                    if (null !== $objEventReleaseLevel) {
                        $objEventReleaseLevelPackage = EventReleaseLevelPolicyPackageModel::findReleaseLevelPolicyPackageModelByEventId($objEvent->id);
                        // Adjust event release level when changing eventType...
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
                    // Add event release level when creating new
                    $oEventReleaseLevelModel = EventReleaseLevelPolicyModel::findFirstLevelByEventId($objEvent->id);

                    $set = [
                        'eventReleaseLevel' => $oEventReleaseLevelModel->id,
                    ];

                    $this->connection->update('tl_calendar_events', $set, ['id' => $objEvent->id]);
                }
            }
        }

        $request = $this->requestStack->getCurrentRequest();

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
     * input_field_callback showFieldValue.
     *
     * @Callback(table="tl_calendar_events", target="fields.alias.input_field")
     */
    public function showFieldValue(DataContainer $dc): string
    {
        $field = $dc->field;

        $strTable = 'tl_calendar_events';

        if (!\strlen((string) $dc->activeRecord->id)) {
            return '';
        }

        $intId = (int) $dc->activeRecord->id;

        $row = $this->connection->fetchAssociative('SELECT '.$field.' FROM tl_calendar_events WHERE id = ?', [$intId]);

        if (!$row) {
            return '';
        }

        $return = '';

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
                        $arrDate[] = Date::parse('D, d.m.Y', $arrTstamp['new_repeat']);
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
                        $diffMin = $this->connection->fetchOne('SELECT shortcut FROM tl_tour_difficulty WHERE id = ?', [(int) $difficulty['tourTechDifficultyMin']]);

                        if ($diffMin) {
                            $strDiff = $diffMin;
                        }

                        $diffMax = $this->connection->fetchOne('SELECT shortcut FROM tl_tour_difficulty WHERE id = ?', [(int) $difficulty['tourTechDifficultyMax']]);

                        if ($diffMax) {
                            $strDiff .= ' - '.$diffMax;
                        }

                        $arrDiff[] = $strDiff;
                    } elseif (\strlen((string) $difficulty['tourTechDifficultyMin'])) {
                        $diffMin = $this->connection->fetchOne('SELECT shortcut FROM tl_tour_difficulty WHERE id = ?', [(int) $difficulty['tourTechDifficultyMin']]);

                        if ($diffMin) {
                            $strDiff = $diffMin;
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
                    $varValue = $this->connection->fetchOne('SELECT '.$chunks[1].' AS value FROM '.$chunks[0].' WHERE id = ?', [$v]);

                    if ($varValue) {
                        $temp[] = $varValue;
                    }
                }

                $row[$i] = implode(', ', $temp);
            } elseif ('fileTree' === $GLOBALS['TL_DCA'][$strTable]['fields'][$i]['inputType'] || \in_array($i, $arrOrder, true)) {
                if (\is_array($value)) {
                    foreach ($value as $kk => $vv) {
                        if (($objFile = FilesModel::findByUuid($vv)) instanceof FilesModel) {
                            $value[$kk] = $objFile->path.' ('.$this->stringUtil->binToUuid($vv).')';
                        } else {
                            $value[$kk] = '';
                        }
                    }

                    $row[$i] = implode('<br>', $value);
                } else {
                    if (($objFile = FilesModel::findByUuid($value)) instanceof FilesModel) {
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
                            $vvalue = array_values($vv);
                            $value[$kk] = array_shift($vvalue).' ('.implode(', ', array_filter($vvalue)).')';
                        }
                    }

                    $row[$i] = implode('<br>', $value);
                }
            } elseif ('date' === $GLOBALS['TL_DCA'][$strTable]['fields'][$i]['eval']['rgxp']) {
                $row[$i] = $value ? Date::parse($this->config->get('dateFormat'), $value) : '-';
            } elseif ('time' === $GLOBALS['TL_DCA'][$strTable]['fields'][$i]['eval']['rgxp']) {
                $row[$i] = $value ? Date::parse($this->config->get('timeFormat'), $value) : '-';
            } elseif ('datim' === $GLOBALS['TL_DCA'][$strTable]['fields'][$i]['eval']['rgxp'] || (isset($GLOBALS['TL_DCA'][$strTable]['fields'][$i]['flag']) && \in_array($GLOBALS['TL_DCA'][$strTable]['fields'][$i]['flag'], [5, 6, 7, 8, 9, 10], false)) || 'tstamp' === $i) {
                $row[$i] = $value ? Date::parse($this->config->get('datimFormat'), $value) : '-';
            } elseif ('checkbox' === $GLOBALS['TL_DCA'][$strTable]['fields'][$i]['inputType'] && !$GLOBALS['TL_DCA'][$strTable]['fields'][$i]['eval']['multiple']) {
                $row[$i] = $value ? $GLOBALS['TL_LANG']['MSC']['yes'] : $GLOBALS['TL_LANG']['MSC']['no'];
            } elseif ('email' === $GLOBALS['TL_DCA'][$strTable]['fields'][$i]['eval']['rgxp']) {
                $row[$i] = Idna::decodeEmail($value);
            } elseif ('textarea' === $GLOBALS['TL_DCA'][$strTable]['fields'][$i]['inputType'] && ($GLOBALS['TL_DCA'][$strTable]['fields'][$i]['eval']['allowHtml'] || $GLOBALS['TL_DCA'][$strTable]['fields'][$i]['eval']['preserveTags'])) {
                $row[$i] = $this->stringUtil->specialchars($value);
            } elseif (isset($GLOBALS['TL_DCA'][$strTable]['fields'][$i]['reference']) && \is_array($GLOBALS['TL_DCA'][$strTable]['fields'][$i]['reference'])) {
                $row[$i] = isset($GLOBALS['TL_DCA'][$strTable]['fields'][$i]['reference'][$row[$i]]) ? (\is_array($GLOBALS['TL_DCA'][$strTable]['fields'][$i]['reference'][$row[$i]]) ? $GLOBALS['TL_DCA'][$strTable]['fields'][$i]['reference'][$row[$i]][0] : $GLOBALS['TL_DCA'][$strTable]['fields'][$i]['reference'][$row[$i]]) : $row[$i];
            } elseif ((isset($GLOBALS['TL_DCA'][$strTable]['fields'][$i]['eval']['isAssociative']) && $GLOBALS['TL_DCA'][$strTable]['fields'][$i]['eval']['isAssociative']) || (isset($GLOBALS['TL_DCA'][$strTable]['fields'][$i]['options']) && array_is_assoc($GLOBALS['TL_DCA'][$strTable]['fields'][$i]['options']))) {
                $row[$i] = $GLOBALS['TL_DCA'][$strTable]['fields'][$i]['options'][$row[$i]];
            } else {
                $row[$i] = $value;
            }

            // Label and help
            if (isset($GLOBALS['TL_DCA'][$strTable]['fields'][$i]['label'])) {
                $label = \is_array($GLOBALS['TL_DCA'][$strTable]['fields'][$i]['label']) ? $GLOBALS['TL_DCA'][$strTable]['fields'][$i]['label'][0] : $GLOBALS['TL_DCA'][$strTable]['fields'][$i]['label'];
                $help = \is_array($GLOBALS['TL_DCA'][$strTable]['fields'][$i]['label']) ? $GLOBALS['TL_DCA'][$strTable]['fields'][$i]['label'][1] : $GLOBALS['TL_DCA'][$strTable]['fields'][$i]['label'];
            } else {
                $label = \is_array($GLOBALS['TL_LANG']['MSC'][$i]) ? $GLOBALS['TL_LANG']['MSC'][$i][0] : $GLOBALS['TL_LANG']['MSC'][$i];
                $help = \is_array($GLOBALS['TL_LANG']['MSC'][$i]) ? $GLOBALS['TL_LANG']['MSC'][$i][1] : $GLOBALS['TL_LANG']['MSC'][$i];
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
     * @Callback(table="tl_calendar_events", target="fields.eventDates.load")
     */
    public function loadCallbackeventDates($arrValues, DataContainer $dc)
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
     * @Callback(table="tl_calendar_events", target="edit.buttons")
     */
    public function editButtonsCallback(array $arrButtons, $dc): array
    {
        if ('writeTourReport' === Input::get('call')) {
            unset($arrButtons['saveNcreate'], $arrButtons['saveNduplicate'], $arrButtons['saveNedit']);
        }

        return $arrButtons;
    }

    /**
     * @Callback(table="tl_calendar_events", target="fields.durationInfo.options")
     */
    public function getEventDuration(): array
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
     * @Callback(table="tl_calendar_events", target="fields.organizers.options")
     */
    public function optionsCallbackGetOrganizers(): array
    {
        $arrOptions = [];

        $stmt = $this->connection->executeQuery('SELECT * FROM tl_event_organizer ORDER BY sorting');

        while (false !== ($row = $stmt->fetchAssociative())) {
            $arrOptions[$row['id']] = $row['title'];
        }

        return $arrOptions;
    }

    /**
     * @Callback(table="tl_calendar_events", target="fields.courseTypeLevel0.options")
     */
    public function optionsCallbackCourseTypeLevel0(): array
    {
        $arrOpt = [];
        $objDatabase = Database::getInstance()->execute('SELECT * FROM tl_course_main_type ORDER BY code');

        while ($objDatabase->next()) {
            $arrOpt[$objDatabase->id] = $objDatabase->name;
        }

        return $arrOpt;
    }

    /**
     * Options callback for tl_calendar_events.tourTechDifficulty.
     */
    public function optionsCallbackTourDifficulties(MultiColumnWizard $dc): array
    {
        $options = [];
        $stmt = $this->connection->executeQuery('SELECT * FROM tl_tour_difficulty ORDER BY pid ASC, code ASC');

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
     * @Callback(table="tl_calendar_events", target="fields.eventType.options")
     */
    public function optionsCallbackEventType(DataContainer $dc): array
    {
        $user = $this->security->getUser();

        $arrEventTypes = [];

        if (!$dc->id && CURRENT_ID > 0) {
            $objCalendar = CalendarModel::findByPk(CURRENT_ID);
        } elseif ($dc->id > 0) {
            $objCalendar = CalendarEventsModel::findByPk($dc->id)->getRelated('pid');
        }

        $arrAllowedEventTypes = [];

        $arrGroups = $this->stringUtil->deserialize($user->groups, true);

        foreach ($arrGroups as $group) {
            $objGroup = UserGroupModel::findByPk($group);

            if (!empty($objGroup->allowedEventTypes) && \is_array($objGroup->allowedEventTypes)) {
                $arrAllowedEvtTypes = $this->stringUtil->deserialize($objGroup->allowedEventTypes, true);

                foreach ($arrAllowedEvtTypes as $eventType) {
                    if (!\in_array($eventType, $arrAllowedEventTypes, false)) {
                        $arrAllowedEventTypes[] = $eventType;
                    }
                }
            }
        }

        if (null !== $objCalendar) {
            $arrEventTypes = $this->stringUtil->deserialize($objCalendar->allowedEventTypes, true);
        }

        return $arrEventTypes;
    }

    /**
     * @Callback(table="tl_calendar_events", target="fields.courseTypeLevel1.options")
     */
    public function optionsCallbackCourseSubType(): array
    {
        $options = [];

        if ('edit' === Input::get('act')) {
            $objEvent = CalendarEventsModel::findByPk(Input::get('id'));
            $sql = "SELECT * FROM tl_course_sub_type WHERE pid='".$objEvent->courseTypeLevel0."' ORDER BY pid, code";
        } else {
            $sql = 'SELECT * FROM tl_course_sub_type ORDER BY pid, code';
        }

        $stmt = $this->connection->executeQuery($sql);

        while (false !== ($row = $stmt->fetchAssociative())) {
            $options[$row['id']] = $row['code'].' '.$row['name'];
        }

        return $options;
    }

    /**
     * @Callback(table="tl_calendar_events", target="fields.eventReleaseLevel.options")
     */
    public function optionsCallbackListReleaseLevels(DataContainer $dc): array
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
                            $stmt = $this->connection->executeQuery('SELECT * FROM tl_event_release_level_policy WHERE pid = ? ORDER BY level ASC', [$objEventReleasePackage->id]);

                            while (false !== ($row = $stmt->fetchAssociative())) {
                                $options[EventReleaseLevelPolicyModel::findByPk($row['id'])->getRelated('pid')->title][$row['id']] = $row['title'];
                            }
                        }
                    }
                }
            } else {
                $stmt = $this->connection->executeQuery('SELECT * FROM tl_event_release_level_policy ORDER BY pid,level ASC');

                while (false !== ($row = $stmt->fetchAssociative())) {
                    $options[EventReleaseLevelPolicyModel::findByPk($row['id'])->getRelated('pid')->title][$row['id']] = $row['title'];
                }
            }
        }

        return $options;
    }

    /**
     * multicolumnwizard columnsCallback listFixedDates().
     */
    public function listFixedDates(): array
    {
        return [
            'new_repeat' => [
                'label' => &$GLOBALS['TL_LANG']['tl_calendar_events']['kurstage'],
                'exclude' => true,
                'inputType' => 'text',
                'default' => time(),
                'eval' => ['rgxp' => 'date', 'datepicker' => true, 'doNotCopy' => false, 'style' => 'width:100px', 'tl_class' => 'wizard'],
            ],
        ];
    }

    /**
     * @param $eventId
     *
     * @return string
     */
    public function generateEventToken($eventId)
    {
        return md5((string) random_int(100000000, 999999999)).'-'.$eventId;
    }

    /**
     * @Callback(table="tl_calendar_events", target="list.sorting.child_record")
     */
    public function listEvents($arrRow): string
    {
        $span = Calendar::calculateSpan($arrRow['startTime'], $arrRow['endTime']);
        $objEvent = CalendarEventsModel::findByPk($arrRow['id']);

        if ($span > 0) {
            $date = Date::parse($this->config->get(($arrRow['addTime'] ? 'datimFormat' : 'dateFormat')), $arrRow['startTime']).' – '.Date::parse($this->config->get(($arrRow['addTime'] ? 'datimFormat' : 'dateFormat')), $arrRow['endTime']);
        } elseif ((int) $arrRow['startTime'] === (int) $arrRow['endTime']) {
            $date = Date::parse($this->config->get('dateFormat'), $arrRow['startTime']).($arrRow['addTime'] ? ' '.Date::parse($this->config->get('timeFormat'), $arrRow['startTime']) : '');
        } else {
            $date = Date::parse($this->config->get('dateFormat'), $arrRow['startTime']).($arrRow['addTime'] ? ' '.Date::parse($this->config->get('timeFormat'), $arrRow['startTime']).' – '.Date::parse($this->config->get('timeFormat'), $arrRow['endTime']) : '');
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
     * @param $row
     * @param $href
     * @param $label
     * @param $title
     * @param $icon
     * @param $attributes
     *
     * @Callback(table="tl_calendar_events", target="list.operations.releaseLevelNext.button")
     */
    public function releaseLevelNext($row, $href, $label, $title, $icon, $attributes): string
    {
        $user = $this->security->getUser();

        $strDirection = 'up';

        $canSendToNextReleaseLevel = false;
        $objReleaseLevelModel = EventReleaseLevelPolicyModel::findByPk($row['eventReleaseLevel']);
        $nextReleaseLevel = null;

        if (null !== $objReleaseLevelModel) {
            $nextReleaseLevel = $objReleaseLevelModel->level + 1;
        }

        // Save to database
        if ('releaseLevelNext' === Input::get('action') && (int) Input::get('eventId') === (int) $row['id']) {
            if (true === EventReleaseLevelPolicyModel::allowSwitchingEventReleaseLevel($user->id, $row['id'], 'up') && true === EventReleaseLevelPolicyModel::levelExists($row['id'], $nextReleaseLevel)) {
                $objEvent = CalendarEventsModel::findByPk(Input::get('eventId'));

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
            $canSendToNextReleaseLevel = true;
        }

        if (false === $canSendToNextReleaseLevel) {
            return '';
        }

        return '<a href="'.$this->backend->addToUrl($href.'&amp;eventId='.$row['id']).'" title="'.$this->stringUtil->specialchars($title).'"'.$attributes.'>'.Image::getHtml($icon, $label).'</a> ';
    }

    /**
     * Save the main instructor in a separate table.
     *
     * @param $varValue
     *
     * @return mixed
     *
     * @Callback(table="tl_calendar_events", target="fields.instructor.save")
     */
    public function saveCallbackSetMainInstructor($varValue, DataContainer $dc)
    {
        if ($dc) {
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

            if (!empty($arrInstructors)) {
                $intInstructor = $arrInstructors[0]['instructorId'];

                if (null !== UserModel::findByPk($intInstructor)) {
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
     * save_callback saveCallbackEventReleaseLevel()
     * Publish or unpublish events if eventReleaseLevel has reached the highest/last level.
     *
     * @param $newEventReleaseLevelId
     * @param DataContainer $dc
     * @param null          $eventId
     *
     * @return mixed
     *
     * @Callback(table="tl_calendar_events", target="fields.eventReleaseLevel.save")
     */
    public function saveCallbackEventReleaseLevel($newEventReleaseLevelId, DataContainer $dc = null, $eventId = null)
    {
        $hasError = false;
        // Get event id
        if ($dc->activeRecord->id > 0) {
            $objEvent = CalendarEventsModel::findByPk($dc->activeRecord->id);
        } elseif ($eventId > 0) {
            $objEvent = CalendarEventsModel::findByPk($eventId);
        }

        if (null !== $objEvent) {
            $lastEventReleaseModel = EventReleaseLevelPolicyModel::findLastLevelByEventId($objEvent->id);

            if (null !== $lastEventReleaseModel) {
                // Display message in the backend if event is published or unpublished now
                if ((int) $lastEventReleaseModel->id === (int) $newEventReleaseLevelId) {
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
                    $eventReleaseModel = EventReleaseLevelPolicyModel::findByPk($newEventReleaseLevelId);
                    $firstEventReleaseModel = EventReleaseLevelPolicyModel::findFirstLevelByEventId($objEvent->id);

                    if (null !== $eventReleaseModel) {
                        if ($eventReleaseModel->pid !== $firstEventReleaseModel->pid) {
                            $hasError = true;

                            if ($objEvent->eventReleaseLevel > 0) {
                                $newEventReleaseLevelId = $objEvent->eventReleaseLevel;
                                $this->message->addError(sprintf('Die Freigabestufe für Event "%s (ID: %s)" konnte nicht auf "%s" geändert werden, weil diese Freigabestufe zum Event-Typ ungültig ist. ', $objEvent->title, $objEvent->id, $eventReleaseModel->title));
                            } else {
                                $newEventReleaseLevelId = $firstEventReleaseModel->id;
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
                    $this->message->addInfo(sprintf($GLOBALS['TL_LANG']['MSC']['setEventReleaseLevelTo'], $objEvent->id, EventReleaseLevelPolicyModel::findByPk($newEventReleaseLevelId)->level));
                }
            }
        }

        return $newEventReleaseLevelId;
    }

    /**
     * @Callback(table="tl_calendar_events", target="fields.eventType.save")
     */
    public function saveCallbackEventType($strEventType, DataContainer $dc = null, $eventId = null)
    {
        if ('' !== $strEventType) {
            // Get event id
            if ($dc->activeRecord->id > 0) {
                $objEvent = CalendarEventsModel::findByPk($dc->activeRecord->id);
            } elseif ($eventId > 0) {
                $objEvent = CalendarEventsModel::findByPk($eventId);
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
     * @param $row
     * @param $href
     * @param $label
     * @param $title
     * @param $icon
     * @param $attributes
     *
     * @return string
     *
     * @Callback(table="tl_calendar_events", target="list.operations.releaseLevelPrev.button")
     */
    public function releaseLevelPrev($row, $href, $label, $title, $icon, $attributes)
    {
        $user = $this->security->getUser();

        $strDirection = 'down';

        $canSendToNextReleaseLevel = false;
        $prevReleaseLevel = null;
        $objReleaseLevelModel = EventReleaseLevelPolicyModel::findByPk($row['eventReleaseLevel']);

        if (null !== $objReleaseLevelModel) {
            $prevReleaseLevel = $objReleaseLevelModel->level - 1;
        }

        // Save to database
        if ('releaseLevelPrev' === Input::get('action') && (int) Input::get('eventId') === (int) $row['id']) {
            if (true === EventReleaseLevelPolicyModel::allowSwitchingEventReleaseLevel($user->id, $row['id'], 'down') && true === EventReleaseLevelPolicyModel::levelExists($row['id'], $prevReleaseLevel)) {
                $objEvent = CalendarEventsModel::findByPk(Input::get('eventId'));

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
            $canSendToNextReleaseLevel = true;
        }

        if (false === $canSendToNextReleaseLevel) {
            return '';
        }

        return '<a href="'.$this->backend->addToUrl($href.'&amp;eventId='.$row['id']).'" title="'.$this->stringUtil->specialchars($title).'"'.$attributes.'>'.Image::getHtml($icon, $label).'</a> ';
    }

    /**
     * Return the delete icon.
     *
     * @param array  $row
     * @param string $href
     * @param string $label
     * @param string $title
     * @param string $icon
     * @param string $attributes
     *
     * @Callback(table="tl_calendar_events", target="list.operations.delete.button")
     */
    public function deleteIcon($row, $href, $label, $title, $icon, $attributes): string
    {
        $user = $this->security->getUser();

        $blnAllow = EventReleaseLevelPolicyModel::canDeleteEvent($user->id, $row['id']);

        if (!$blnAllow) {
            return '';
        }

        return '<a href="'.$this->backend->addToUrl($href.'&amp;id='.$row['id']).'" title="'.$this->stringUtil->specialchars($title).'"'.$attributes.'>'.Image::getHtml($icon, $label).'</a> ';
    }

    /**
     * @param $row
     * @param $href
     * @param $label
     * @param $title
     * @param $icon
     * @param $attributes
     *
     * @Callback(table="tl_calendar_events", target="list.operations.copy.button")
     */
    public function copyIcon($row, $href, $label, $title, $icon, $attributes): string
    {
        $user = $this->security->getUser();

        $blnAllow = EventReleaseLevelPolicyModel::hasWritePermission($user->id, $row['id']);

        if (!$blnAllow) {
            return '';
        }

        return '<a href="'.$this->backend->addToUrl($href.'&amp;id='.$row['id']).'" title="'.$this->stringUtil->specialchars($title).'"'.$attributes.'>'.Image::getHtml($icon, $label).'</a> ';
    }
}
