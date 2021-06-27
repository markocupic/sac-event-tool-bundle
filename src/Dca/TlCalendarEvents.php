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

namespace Markocupic\SacEventToolBundle\Dca;

use Contao\Backend;
use Contao\BackendUser;
use Contao\Calendar;
use Contao\CalendarEventsModel;
use Contao\CalendarModel;
use Contao\Config;
use Contao\Controller;
use Contao\CoreBundle\DataContainer\PaletteManipulator;
use Contao\CoreBundle\Exception\AccessDeniedException;
use Contao\Database;
use Contao\DataContainer;
use Contao\Date;
use Contao\DcaExtractor;
use Contao\Encryption;
use Contao\EventReleaseLevelPolicyModel;
use Contao\EventReleaseLevelPolicyPackageModel;
use Contao\Events;
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
use Contao\Versions;
use Haste\Form;
use League\Csv\CharsetConverter;
use League\Csv\Writer;
use Markocupic\SacEventToolBundle\CalendarEventsHelper;
use MultiColumnWizard;

/**
 * Class TlCalendarEvents.
 */
class TlCalendarEvents extends \tl_calendar_events
{
    /**
     * Import the back end user object.
     */
    public function __construct()
    {
        // Set correct referer
        if ('sac_calendar_events_tool' === Input::get('do') && '' !== Input::get('ref')) {
            $objSession = static::getContainer()->get('session');
            $ref = Input::get('ref');
            $session = $objSession->get('referer');

            if (isset($session[$ref]['tl_calendar_container'])) {
                $session[$ref]['tl_calendar_container'] = str_replace('do=calendar', 'do=sac_calendar_events_tool', $session[$ref]['tl_calendar_container']);
                $objSession->set('referer', $session);
            }

            if (isset($session[$ref]['tl_calendar'])) {
                $session[$ref]['tl_calendar'] = str_replace('do=calendar', 'do=sac_calendar_events_tool', $session[$ref]['tl_calendar']);
                $objSession->set('referer', $session);
            }

            if (isset($session[$ref]['tl_calendar_events'])) {
                $session[$ref]['tl_calendar_events'] = str_replace('do=calendar', 'do=sac_calendar_events_tool', $session[$ref]['tl_calendar_events']);
                $objSession->set('referer', $session);
            }

            if (isset($session[$ref]['tl_calendar_events_instructor_invoice'])) {
                $session[$ref]['tl_calendar_events_instructor_invoice'] = str_replace('do=calendar', 'do=sac_calendar_events_tool', $session[$ref]['tl_calendar_events_instructor_invoice']);
                $objSession->set('referer', $session);
            }
        }

        $this->import('BackendUser', 'User');

        return parent::__construct();
    }

    /**
     * Manipulate palette when creating a new datarecord.
     */
    public function setPaletteWhenCreatingNew(DataContainer $dc): void
    {
        if ('edit' === Input::get('act')) {
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
     * Display differentfilters for each event types.
     */
    public function setFilterSearchAndSortingBoard(DataContainer $dc): void
    {
        if (CURRENT_ID > 0) {
            $objCalendar = CalendarModel::findByPk(CURRENT_ID);

            if (null !== $objCalendar) {
                $arrAllowedEventTypes = StringUtil::deserialize($objCalendar->allowedEventTypes, true);

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
     * onload_callback onloadCallbackDeleteInvalidEvents.
     */
    public function onloadCallbackDeleteInvalidEvents(DataContainer $dc): void
    {
        $this->Database->prepare('DELETE FROM tl_calendar_events WHERE tstamp<? AND tstamp>? AND title=?')->execute(time() - 24 * 60 * 60, 0, '');
    }

    /**
     * onload_callback onloadCallback.
     */
    public function onloadCallback(DataContainer $dc): void
    {
        // Minimize header fields for default users
        if (!$this->User->isAdmin) {
            $GLOBALS['TL_DCA']['tl_calendar_events']['list']['sorting']['headerFields'] = ['title'];
        }

        // Minimize operations for default users
        if (!$this->User->isAdmin) {
            unset($GLOBALS['TL_DCA']['tl_calendar_events']['list']['operations']['show']);
        }

        // Do not allow some specific global operations to default users
        if (!$this->User->isAdmin) {
            unset($GLOBALS['TL_DCA']['tl_calendar_events']['list']['global_operations']['plus1year'], $GLOBALS['TL_DCA']['tl_calendar_events']['list']['global_operations']['minus1year']);
        }

        // Special treatment for tl_calendar_events.eventReleaseLevel
        // Do not allow multi edit on tl_calendar_events.eventReleaseLevel, if user does not habe write permissions on all levels
        if ('editAll' === Input::get('act') || 'overrideAll' === Input::get('act')) {
            $allow = true;
            $objSession = System::getContainer()->get('session');
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
                                $arrGroupsUserBelongsTo = StringUtil::deserialize($this->User->groups, true);
                                $arrGroups = StringUtil::deserialize($objReleaseLevelModel->groupReleaseLevelRights, true);

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

            if ($this->User->isAdmin || true === $allow) {
                PaletteManipulator::create()
                    ->addField(['eventReleaseLevel'], 'title_legend', PaletteManipulator::POSITION_APPEND)
                    ->applyToPalette('default', 'tl_calendar_events')
                ;
            }
        }

        // Skip here if user is admin
        if ($this->User->isAdmin) {
            return;
        }

        // Do not allow cutting an editing to default users
        $GLOBALS['TL_DCA']['tl_calendar_events']['list']['operations']['edit'] = null;

        // Limitize filter fields
        foreach (array_keys($GLOBALS['TL_DCA']['tl_calendar_events']['fields']) as $k) {
            if ('author' === $k || 'organizers' === $k || 'tourType' === $k || 'eventReleaseLevel' === $k || 'mainInstructor' === $k || 'courseTypeLevel0' === $k || 'startTime' === $k) {
                continue;
            }

            $GLOBALS['TL_DCA']['tl_calendar_events']['fields'][$k]['filter'] = null;
        }

        // Prevent unauthorized publishing
        if (Input::get('tid')) {
            $objDb = $this->Database->prepare('SELECT * FROM tl_calendar_events WHERE id=?')->execute(Input::get('tid'));

            if ($objDb->next()) {
                if (!EventReleaseLevelPolicyModel::hasWritePermission($this->User->id, $objDb->id)) {
                    Message::addError(sprintf($GLOBALS['TL_LANG']['MSC']['missingPermissionsToPublishOrUnpublishEvent'], $objDb->id));
                    $this->redirect($this->getReferer());
                }
            }
        }

        // Prevent unauthorized deletion
        if ('delete' === Input::get('act')) {
            $objDb = $this->Database->prepare('SELECT * FROM tl_calendar_events WHERE id=?')->limit(1)->execute($dc->id);

            if ($objDb->numRows) {
                if (!EventReleaseLevelPolicyModel::canDeleteEvent($this->User->id, $objDb->id)) {
                    Message::addError(sprintf($GLOBALS['TL_LANG']['MSC']['missingPermissionsToDeleteEvent'], $objDb->id));
                    $this->redirect($this->getReferer());
                }
            }
        }

        // Prevent unauthorized editing
        if ('edit' === Input::get('act')) {
            $objEventsModel = CalendarEventsModel::findOneById(Input::get('id'));

            if (null !== $objEventsModel) {
                if (null !== EventReleaseLevelPolicyModel::findByPk($objEventsModel->eventReleaseLevel)) {
                    if (!EventReleaseLevelPolicyModel::hasWritePermission($this->User->id, $objEventsModel->id) && $this->User->id !== $objEventsModel->registrationGoesTo) {
                        // User has no write access to the datarecord, that's why we display field values without a form input
                        foreach (array_keys($GLOBALS['TL_DCA']['tl_calendar_events']['fields']) as $field) {
                            $GLOBALS['TL_DCA']['tl_calendar_events']['fields'][$field]['input_field_callback'] = [self::class, 'showFieldValue'];
                        }

                        if ('tl_calendar_events' === Input::post('FORM_SUBMIT')) {
                            Message::addError(sprintf($GLOBALS['TL_LANG']['MSC']['missingPermissionsToEditEvent'], $objEventsModel->id));
                            $this->redirect($this->getReferer());
                        }
                    } else {
                        // Protect fields with $GLOBALS['TL_DCA']['tl_calendar_events']['fields'][$fieldname]['allowEdititingOnFirstReleaseLevelOnly'] === true,
                        // if the event is on the first release level
                        if (!$this->User->isAdmin) {
                            $objEventReleaseLevelPolicyPackageModel = EventReleaseLevelPolicyPackageModel::findReleaseLevelPolicyPackageModelByEventId($objEventsModel->id);

                            if (null !== $objEventReleaseLevelPolicyPackageModel) {
                                if ($objEventsModel->eventReleaseLevel > 0) {
                                    $objEventReleaseLevelPolicyModel = EventReleaseLevelPolicyModel::findFirstLevelByEventId($objEventsModel->id);

                                    if (null !== $objEventReleaseLevelPolicyModel) {
                                        if ($objEventReleaseLevelPolicyModel->id !== $objEventsModel->eventReleaseLevel) {
                                            foreach (array_keys($GLOBALS['TL_DCA']['tl_calendar_events']['fields']) as $fieldname) {
                                                if (true === $GLOBALS['TL_DCA']['tl_calendar_events']['fields'][$fieldname]['allowEdititingOnFirstReleaseLevelOnly'] && '' !== $GLOBALS['TL_DCA']['tl_calendar_events']['fields'][$fieldname]['inputType']) {
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
        if ('select' === Input::get('act')) {
            /** @var AttributeBagInterface $objSessionBag */
            $objSessionBag = System::getContainer()->get('session')->getBag('contao_backend');
            $session = $objSessionBag->all();
            $filter = 4 === (int) $GLOBALS['TL_DCA']['tl_calendar_events']['list']['sorting']['mode'] ? 'tl_calendar_events_'.CURRENT_ID : 'tl_calendar_events';

            if (!isset($session['filter'][$filter]['eventReleaseLevel'])) {
                Message::addInfo('"Mehrere bearbeiten" nur möglich, wenn ein Freigabestufen-Filter gesetzt wurde."');
                $this->redirect($this->getReferer());

                return;
            }
        }

        // Only list record where the logged in user has write permissions
        if ('select' === Input::get('act') || 'editAll' === Input::get('act')) {
            $arrIDS = [0];
            $objDb = $this->Database->prepare('SELECT * FROM tl_calendar_events WHERE pid=?')->execute(CURRENT_ID);

            while ($objDb->next()) {
                if (EventReleaseLevelPolicyModel::hasWritePermission($this->User->id, $objDb->id)) {
                    $arrIDS[] = $objDb->id;
                }
            }
            $GLOBALS['TL_DCA']['tl_calendar_events']['list']['sorting']['root'] = $arrIDS;
        }

        // Do not allow editing write protected fields in editAll mode
        // Use input_field_callback to only display the field values without the form input field
        if ('editAll' === Input::get('act') || 'overrideAll' === Input::get('act')) {
            $objSession = System::getContainer()->get('session');
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
                                    if (true === $GLOBALS['TL_DCA']['tl_calendar_events']['fields'][$fieldname]['allowEdititingOnFirstReleaseLevelOnly']) {
                                        if ('editAll' === Input::get('act')) {
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
     * onload_callback onloadCallbackSetPalettes
     * Set palette for course, tour, tour_report, etc.
     */
    public function onloadCallbackSetPalettes(DataContainer $dc): void
    {
        if ('editAll' === Input::get('act') || 'overrideAll' === Input::get('act')) {
            return;
        }

        if ($dc->id > 0) {
            if ('writeTourReport' === Input::get('call')) {
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
    }

    /**
     * onload_callback onloadCallbackExportCalendar
     * CSV-export of all events of a calendar.
     */
    public function onloadCallbackExportCalendar(DataContainer $dc): void
    {
        if ('onloadCallbackExportCalendar' === Input::get('action') && Input::get('id') > 0) {
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
            Controller::loadLanguageFile('tl_calendar_events');
            $arrHeadline = array_map(
                static function ($field) {
                    return $GLOBALS['TL_LANG']['tl_calendar_events'][$field][0] ?? $field;
                },
                $arrFields
            );
            $csv->insertOne($arrHeadline);

            $objEvent = CalendarEventsModel::findBy(
                ['tl_calendar_events.pid=?'],
                [Input::get('id')],
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
                                static function ($tstamp) {
                                    return Date::parse(Config::get('dateFormat'), $tstamp);
                                },
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

            $objCalendar = CalendarModel::findByPk(Input::get('id'));
            $csv->output($objCalendar->title.'.csv');
            exit;
        }
    }

    /**
     * onload_callback onloadCallbackShiftEventDates
     * Shift all event dates of a certain calendar by +/- 1 year
     * https://somehost/contao?do=sac_calendar_events_tool&table=tl_calendar_events&id=21&transformDate=+52weeks&rt=hUFF18TV1YCLddb-Cyb48dRH8y_9iI-BgM-Nc1rB8o8&ref=2sjHl6mB.
     */
    public function onloadCallbackShiftEventDates(DataContainer $dc): void
    {
        if (Input::get('transformDates')) {
            // $mode may be "+52weeks" or "+1year"
            $mode = Input::get('transformDates');

            if (false !== strtotime($mode)) {
                $calendarId = Input::get('id');

                $objEvent = $this->Database->prepare('SELECT * FROM tl_calendar_events WHERE pid=?')->execute($calendarId);

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

                    $arrRepeats = StringUtil::deserialize($objEvent->eventDates, true);
                    $newArrRepeats = [];

                    if (\count($arrRepeats) > 0) {
                        foreach ($arrRepeats as $repeat) {
                            $repeat['new_repeat'] = strtotime($mode, (int) $repeat['new_repeat']);
                            $newArrRepeats[] = $repeat;
                        }
                        $set['eventDates'] = serialize($newArrRepeats);
                    }
                    $this->Database->prepare('UPDATE tl_calendar_events %s WHERE id=?')->set($set)->execute($objEvent->id);
                }
            }
            // Redirect
            $this->redirect($this->getReferer());
        }
    }

    /**
     * oncreate_callback oncreateCallback.
     */
    public function oncreateCallback($strTable, $insertId, $set, DataContainer $dc): void
    {
        // Set source, add author, set first release level and & set customEventRegistrationConfirmationEmailText on creating new events
        $objEventsModel = CalendarEventsModel::findByPk($insertId);

        if (null !== $objEventsModel) {
            // Set source always to "default"
            $objEventsModel->source = 'default';

            // Set logged in User as author
            $objEventsModel->author = $this->User->id;
            $objEventsModel->mainInstructor = $this->User->id;
            $objEventsModel->instructor = serialize([['instructorId' => $this->User->id]]);

            // Set customEventRegistrationConfirmationEmailText
            $objEventsModel->customEventRegistrationConfirmationEmailText = str_replace('{{br}}', "\n", Config::get('SAC_EVT_ACCEPT_REGISTRATION_EMAIL_TEXT'));

            $objEventsModel->save();
        }
    }

    /**
     * oncopy_callback oncopyCallback.
     *
     * @param $insertId
     */
    public function oncopyCallback($insertId, DataContainer $dc): void
    {
        // Add author and set first release level on creating new events
        $objEventsModel = CalendarEventsModel::findByPk($insertId);

        if (null !== $objEventsModel) {
            // Set logged in User as author
            $objEventsModel->author = $this->User->id;
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
     * ondelete_callback ondeleteCallback
     * Do not allow to non-admins deleting records if there are child records (event registrations) in tl_calendar_events_member.
     */
    public function ondeleteCallback(DataContainer $dc): void
    {
        // Return if there is no ID
        if (!$dc->activeRecord) {
            return;
        }

        if (!$this->User->admin) {
            $objDb = $this->Database->prepare('SELECT * FROM tl_calendar_events_member WHERE eventId=?')->execute($dc->activeRecord->id);

            if ($objDb->numRows) {
                Message::addError(sprintf($GLOBALS['TL_LANG']['MSC']['deleteEventMembersBeforeDeleteEvent'], $dc->activeRecord->id));
                $this->redirect($this->getReferer());
            }
        }
    }

    /**
     * onsubmit_callback adjustImageSize().
     */
    public function adjustImageSize(DataContainer $dc): void
    {
        // Return if there is no active record (override all)
        if (!$dc->activeRecord) {
            return;
        }

        $arrSet['size'] = serialize(['', '', 11]);
        $this->Database->prepare('UPDATE tl_calendar_events %s WHERE id=?')->set($arrSet)->execute($dc->activeRecord->id);
    }

    /**
     * onsubmit_callback adjustEndDate().
     */
    public function adjustEndDate(DataContainer $dc): void
    {
        // Return if there is no active record (override all)
        if (!$dc->activeRecord) {
            return;
        }

        $arrDates = StringUtil::deserialize($dc->activeRecord->eventDates);

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
        $arrSet = [];
        $arrSet['eventDates'] = serialize($arrDates);
        $startTime = !empty($arrDates[0]['new_repeat']) ? $arrDates[0]['new_repeat'] : 0;
        $endTime = !empty($arrDates[\count($arrDates) - 1]['new_repeat']) ? $arrDates[\count($arrDates) - 1]['new_repeat'] : 0;

        $arrSet['endTime'] = $endTime;
        $arrSet['endDate'] = $endTime;
        $arrSet['startDate'] = $startTime;
        $arrSet['startTime'] = $startTime;
        $this->Database->prepare('UPDATE tl_calendar_events %s WHERE id=?')->set($arrSet)->execute($dc->activeRecord->id);
    }

    /**
     * onsubmit_callback adjustEventReleaseLevel().
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
            $this->Database->prepare('UPDATE tl_calendar_events %s WHERE id=?')->set($set)->execute($dc->activeRecord->id);
        }
    }

    /**
     * onsubmit_callback setEventToken().
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
        $this->Database->prepare('UPDATE tl_calendar_events SET eventToken=? WHERE id=? AND eventToken=?')->execute($strToken, $dc->activeRecord->id, '');
    }

    /**
     * onsubmit_callback adjustDurationInfo().
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
                            $arrSet = [];
                            $arrSet['durationInfo'] = '';
                            $this->Database->prepare('UPDATE tl_calendar_events %s WHERE id=?')->set($arrSet)->execute($objEvent->id);
                            Message::addError(sprintf('Die Event-Dauer in "%s" [ID:%s] stimmt nicht mit der Anzahl Event-Daten überein. Setzen SIe für jeden Event-Tag eine Datumszeile!', $objEvent->title, $objEvent->id), TL_MODE);
                        }
                    }
                }
            }
        }
    }

    /**
     * onsubmit_callback adjustRegistrationPeriod().
     */
    public function adjustRegistrationPeriod(DataContainer $dc): void
    {
        // Return if there is no active record (override all)
        if (!$dc->activeRecord) {
            return;
        }

        $objDb = $this->Database->prepare('SELECT * FROM tl_calendar_events WHERE id=?')->limit(1)->execute($dc->activeRecord->id);

        if ($objDb->numRows > 0) {
            if ($objDb->setRegistrationPeriod) {
                $regEndDate = $objDb->registrationEndDate;
                $regStartDate = $objDb->registrationStartDate;

                if ($regEndDate > $objDb->startDate) {
                    $regEndDate = $objDb->startDate;
                    Message::addInfo($GLOBALS['TL_LANG']['MSC']['patchedEndDatePleaseCheck'], TL_MODE);
                }

                if ($regStartDate > $regEndDate) {
                    $regStartDate = $regEndDate - 86400;
                    Message::addInfo($GLOBALS['TL_LANG']['MSC']['patchedStartDatePleaseCheck'], TL_MODE);
                }
                $arrSet['registrationStartDate'] = $regStartDate;
                $arrSet['registrationEndDate'] = $regEndDate;
                $this->Database->prepare('UPDATE tl_calendar_events %s WHERE id=?')->set($arrSet)->execute($objDb->id);
            }
        }
    }

    /**
     * onsubmit_callback onsubmitCallback
     * Set alias.
     */
    public function onsubmitCallback(DataContainer $dc): void
    {
        // Set correct eventReleaseLevel
        $objEvent = CalendarEventsModel::findByPk($dc->activeRecord->id);

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
                                $this->Database->prepare('UPDATE tl_calendar_events %s WHERE id=?')->set($set)->execute($objEvent->id);
                            }
                        }
                    }
                } else {
                    // Add eventReleaseLevel when creating a new event...
                    $oEventReleaseLevelModel = EventReleaseLevelPolicyModel::findFirstLevelByEventId($objEvent->id);
                    $set = ['eventReleaseLevel' => $oEventReleaseLevelModel->id];
                    $this->Database->prepare('UPDATE tl_calendar_events %s WHERE id=?')->set($set)->execute($objEvent->id);
                }
            }
        }
        // End set correct eventReleaseLevel

        // Set filledInEventReportForm, now the invoice form can be printed in tl_calendar_events_instructor_invoice
        if ('writeTourReport' === Input::get('call')) {
            $set = ['filledInEventReportForm' => '1'];
            $this->Database->prepare('UPDATE tl_calendar_events %s WHERE id=?')->set($set)->execute($dc->activeRecord->id);
        }

        $set = ['alias' => 'event-'.$dc->id];
        $this->Database->prepare('UPDATE tl_calendar_events %s WHERE id=?')->set($set)->execute($dc->activeRecord->id);
    }

    /**
     * input_field_callback showFieldValue.
     *
     * @param $dc
     * @param $field
     *
     * @return string
     */
    public function showFieldValue(DataContainer $dc)
    {
        $field = $dc->field;

        $strTable = 'tl_calendar_events';

        if (!\strlen((string) $dc->activeRecord->id)) {
            return '';
        }
        $intId = $dc->activeRecord->id;

        $objRow = $this->Database->prepare('SELECT '.$field.' FROM tl_calendar_events WHERE id=?')
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

            $value = StringUtil::deserialize($row[$i]);

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
                        $objDiff = $this->Database->prepare('SELECT * FROM tl_tour_difficulty WHERE id=?')->limit(1)->execute((int) ($difficulty['tourTechDifficultyMin']));

                        if ($objDiff->numRows) {
                            $strDiff = $objDiff->shortcut;
                        }
                        $objDiff = $this->Database->prepare('SELECT * FROM tl_tour_difficulty WHERE id=?')->limit(1)->execute((int) ($difficulty['tourTechDifficultyMax']));

                        if ($objDiff->numRows) {
                            $max = $objDiff->shortcut;
                            $strDiff .= ' - '.$max;
                        }

                        $arrDiff[] = $strDiff;
                    } elseif (\strlen((string) $difficulty['tourTechDifficultyMin'])) {
                        $objDiff = $this->Database->prepare('SELECT * FROM tl_tour_difficulty WHERE id=?')->limit(1)->execute((int) ($difficulty['tourTechDifficultyMin']));

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
                    $objKey = $this->Database->prepare('SELECT '.$chunks[1].' AS value FROM '.$chunks[0].' WHERE id=?')
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
                        if (($objFile = FilesModel::findByUuid($vv)) instanceof FilesModel) {
                            $value[$kk] = $objFile->path.' ('.StringUtil::binToUuid($vv).')';
                        } else {
                            $value[$kk] = '';
                        }
                    }

                    $row[$i] = implode('<br>', $value);
                } else {
                    if (($objFile = FilesModel::findByUuid($value)) instanceof FilesModel) {
                        $row[$i] = $objFile->path.' ('.StringUtil::binToUuid($value).')';
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
            } elseif ('date' === $GLOBALS['TL_DCA'][$strTable]['fields'][$i]['eval']['rgxp']) {
                $row[$i] = $value ? Date::parse(Config::get('dateFormat'), $value) : '-';
            } elseif ('time' === $GLOBALS['TL_DCA'][$strTable]['fields'][$i]['eval']['rgxp']) {
                $row[$i] = $value ? Date::parse(Config::get('timeFormat'), $value) : '-';
            } elseif ('datim' === $GLOBALS['TL_DCA'][$strTable]['fields'][$i]['eval']['rgxp'] || (isset($GLOBALS['TL_DCA'][$strTable]['fields'][$i]['flag']) && \in_array($GLOBALS['TL_DCA'][$strTable]['fields'][$i]['flag'], [5, 6, 7, 8, 9, 10], false)) || 'tstamp' === $i) {
                $row[$i] = $value ? Date::parse(Config::get('datimFormat'), $value) : '-';
            } elseif ('checkbox' === $GLOBALS['TL_DCA'][$strTable]['fields'][$i]['inputType'] && !$GLOBALS['TL_DCA'][$strTable]['fields'][$i]['eval']['multiple']) {
                $row[$i] = $value ? $GLOBALS['TL_LANG']['MSC']['yes'] : $GLOBALS['TL_LANG']['MSC']['no'];
            } elseif ('email' === $GLOBALS['TL_DCA'][$strTable]['fields'][$i]['eval']['rgxp']) {
                $row[$i] = Idna::decodeEmail($value);
            } elseif ('textarea' === $GLOBALS['TL_DCA'][$strTable]['fields'][$i]['inputType'] && ($GLOBALS['TL_DCA'][$strTable]['fields'][$i]['eval']['allowHtml'] || $GLOBALS['TL_DCA'][$strTable]['fields'][$i]['eval']['preserveTags'])) {
                $row[$i] = StringUtil::specialchars($value);
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
                echo $i.' ';
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
     * @param $arrValues
     *
     * @return array|string|null
     */
    public function loadCallbackeventDates($arrValues, DataContainer $dc)
    {
        if ('' !== $arrValues) {
            $arrValues = StringUtil::deserialize($arrValues, true);

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
     * @param $arrButtons
     * @param $dc
     *
     * @return mixed
     */
    public function buttonsCallback($arrButtons, $dc)
    {
        if ('writeTourReport' === Input::get('call')) {
            unset($arrButtons['saveNcreate'], $arrButtons['saveNduplicate'], $arrButtons['saveNedit']);
        }

        return $arrButtons;
    }

    /**
     * @return array
     */
    public function optionsCallbackGetEventDuration()
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
     * @return array
     */
    public function optionsCallbackGetOrganizers()
    {
        $arrOptions = [];
        $objOrganizer = Database::getInstance()->prepare('SELECT * FROM tl_event_organizer ORDER BY sorting')->execute();

        while ($objOrganizer->next()) {
            $arrOptions[$objOrganizer->id] = $objOrganizer->title;
        }

        return $arrOptions;
    }

    /**
     * @return array
     */
    public function optionsCallbackCourseTypeLevel0()
    {
        $arrOpt = [];
        $objDatabase = Database::getInstance()->execute('SELECT * FROM tl_course_main_type ORDER BY code');

        while ($objDatabase->next()) {
            $arrOpt[$objDatabase->id] = $objDatabase->name;
        }

        return $arrOpt;
    }

    /**
     * options_callback optionsCallbackTourDifficulties().
     *
     * @return array
     */
    public function optionsCallbackTourDifficulties(MultiColumnWizard $dc)
    {
        $options = [];
        $objDb = $this->Database->execute('SELECT * FROM tl_tour_difficulty ORDER BY pid ASC, code ASC');

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
     * @return array
     */
    public function optionsCallbackEventType(DataContainer $dc)
    {
        $arrEventTypes = [];

        if (!$dc->id && CURRENT_ID > 0) {
            $objCalendar = CalendarModel::findByPk(CURRENT_ID);
        } elseif ($dc->id > 0) {
            $objCalendar = CalendarEventsModel::findByPk($dc->id)->getRelated('pid');
        }

        $arrAllowedEventTypes = [];
        $objUser = BackendUser::getInstance();

        if (null !== $objUser) {
            $arrGroups = StringUtil::deserialize($objUser->groups, true);

            foreach ($arrGroups as $group) {
                $objGroup = UserGroupModel::findByPk($group);

                if (!empty($objGroup->allowedEventTypes) && \is_array($objGroup->allowedEventTypes)) {
                    $arrAllowedEvtTypes = StringUtil::deserialize($objGroup->allowedEventTypes, true);

                    foreach ($arrAllowedEvtTypes as $eventType) {
                        if (!\in_array($eventType, $arrAllowedEventTypes, false)) {
                            $arrAllowedEventTypes[] = $eventType;
                        }
                    }
                }
            }
        }

        if (null !== $objCalendar) {
            $arrEventTypes = StringUtil::deserialize($objCalendar->allowedEventTypes, true);
        }

        return $arrEventTypes;
    }

    /**
     * options_callback optionsCallbackCourseSubType().
     *
     * @return array
     */
    public function optionsCallbackCourseSubType()
    {
        $options = [];

        if ('edit' === Input::get('act')) {
            $objEvent = CalendarEventsModel::findByPk(Input::get('id'));
            $sql = "SELECT * FROM tl_course_sub_type WHERE pid='".$objEvent->courseTypeLevel0."' ORDER BY pid, code";
        } else {
            $sql = 'SELECT * FROM tl_course_sub_type ORDER BY pid, code';
        }

        $objType = $this->Database->execute($sql);

        while ($objType->next()) {
            $options[$objType->id] = $objType->code.' '.$objType->name;
        }

        return $options;
    }

    /**
     * options_callback optionsCallbackListReleaseLevels.
     *
     * @return array
     */
    public function optionsCallbackListReleaseLevels(DataContainer $dc)
    {
        $options = [];

        $objUser = BackendUser::getInstance();
        $arrAllowedEventTypes = [];

        if (null !== $objUser) {
            if (!$objUser->admin) {
                $arrGroups = StringUtil::deserialize($objUser->groups, true);

                foreach ($arrGroups as $group) {
                    $objGroup = UserGroupModel::findByPk($group);

                    if (null !== $objGroup) {
                        $arrEventTypes = StringUtil::deserialize($objGroup->allowedEventTypes, true);

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
                            $objEventReleaseLevels = $this->Database->prepare('SELECT * FROM tl_event_release_level_policy WHERE pid=? ORDER BY level ASC')->execute($objEventReleasePackage->id);

                            while ($objEventReleaseLevels->next()) {
                                $options[EventReleaseLevelPolicyModel::findByPk($objEventReleaseLevels->id)->getRelated('pid')->title][$objEventReleaseLevels->id] = $objEventReleaseLevels->title;
                            }
                        }
                    }
                }
            } else {
                $objEventReleaseLevels = $this->Database->prepare('SELECT * FROM tl_event_release_level_policy ORDER BY pid,level ASC')->execute();

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
        $columnFields = null;

        $columnFields = [
            'new_repeat' => [
                'label' => &$GLOBALS['TL_LANG']['tl_calendar_events']['kurstage'],
                'exclude' => true,
                'inputType' => 'text',
                'default' => time(),
                'eval' => ['rgxp' => 'date', 'datepicker' => true, 'doNotCopy' => false, 'style' => 'width:100px', 'tl_class' => 'wizard'],
            ],
        ];

        return $columnFields;
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
     * childrecord_callback listEvents()
     * Add the type of input field.
     *
     * @param array $arrRow
     *
     * @return string
     */
    public function listEvents($arrRow)
    {
        $span = Calendar::calculateSpan($arrRow['startTime'], $arrRow['endTime']);
        $objEvent = CalendarEventsModel::findByPk($arrRow['id']);

        if ($span > 0) {
            $date = Date::parse(Config::get(($arrRow['addTime'] ? 'datimFormat' : 'dateFormat')), $arrRow['startTime']).' – '.Date::parse(Config::get(($arrRow['addTime'] ? 'datimFormat' : 'dateFormat')), $arrRow['endTime']);
        } elseif ((int) $arrRow['startTime'] === (int) $arrRow['endTime']) {
            $date = Date::parse(Config::get('dateFormat'), $arrRow['startTime']).($arrRow['addTime'] ? ' '.Date::parse(Config::get('timeFormat'), $arrRow['startTime']) : '');
        } else {
            $date = Date::parse(Config::get('dateFormat'), $arrRow['startTime']).($arrRow['addTime'] ? ' '.Date::parse(Config::get('timeFormat'), $arrRow['startTime']).' – '.Date::parse(Config::get('timeFormat'), $arrRow['endTime']) : '');
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
     * button_callback toggleIcon()
     * Return the "toggle visibility" button.
     *
     * @param array  $row
     * @param string $href
     * @param string $label
     * @param string $title
     * @param string $icon
     * @param string $attributes
     *
     * @return string
     */
    public function toggleIcon($row, $href, $label, $title, $icon, $attributes)
    {
        if (\strlen((string) Input::get('tid'))) {
            $this->toggleVisibility(Input::get('tid'), ('1' === Input::get('state')), (@func_get_arg(12) ?: null));
            $this->redirect($this->getReferer());
        }

        // Check permissions AFTER checking the tid, so hacking attempts are logged
        if (!$this->User->hasAccess('tl_calendar_events::published', 'alexf')) {
            return '';
        }

        $href .= '&amp;tid='.$row['id'].'&amp;state='.($row['published'] ? '' : 1);

        if (!$row['published']) {
            $icon = 'invisible.svg';
        }

        return '<a href="'.$this->addToUrl($href).'" title="'.StringUtil::specialchars($title).'"'.$attributes.'>'.Image::getHtml($icon, $label, 'data-state="'.($row['published'] ? 1 : 0).'"').'</a> ';
    }

    /**
     * Publish/unpublish event.
     *
     * @param int           $intId
     * @param bool          $blnVisible
     * @param DataContainer $dc
     *
     * @throws AccessDeniedException
     */
    public function toggleVisibility($intId, $blnVisible, DataContainer $dc = null): void
    {
        // Set the ID and action
        Input::setGet('id', $intId);
        Input::setGet('act', 'toggle');

        if ($dc) {
            $dc->id = $intId; // see #8043
        }

        $this->checkPermission();

        // Check the field access
        if (!$this->User->hasAccess('tl_calendar_events::published', 'alexf')) {
            throw new AccessDeniedException('Not enough permissions to publish/unpublish event ID '.$intId.'.');
        }

        $objVersions = new Versions('tl_calendar_events', $intId);
        $objVersions->initialize();

        // Trigger the save_callback
        if (\is_array($GLOBALS['TL_DCA']['tl_calendar_events']['fields']['published']['save_callback'])) {
            foreach ($GLOBALS['TL_DCA']['tl_calendar_events']['fields']['published']['save_callback'] as $callback) {
                if (\is_array($callback)) {
                    $this->import($callback[0]);
                    $blnVisible = $this->{$callback[0]}->{$callback[1]}($blnVisible, ($dc ?: $this));
                } elseif (\is_callable($callback)) {
                    $blnVisible = $callback($blnVisible, ($dc ?: $this));
                }
            }
        }

        // Update the database
        $this->Database
            ->prepare('UPDATE tl_calendar_events SET tstamp='.time().", published='".($blnVisible ? '1' : '')."' WHERE id=?")
            ->execute($intId)
        ;

        $objVersions->create();

        // Update the RSS feed (for some reason it does not work without sleep(1))
        sleep(1);
        $this->import('Calendar');
        $this->Calendar->generateFeedsByCalendar(CURRENT_ID);
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
     * @return string
     */
    public function releaseLevelNext($row, $href, $label, $title, $icon, $attributes)
    {
        $strDirection = 'up';

        $canSendToNextReleaseLevel = false;
        $objReleaseLevelModel = EventReleaseLevelPolicyModel::findByPk($row['eventReleaseLevel']);
        $nextReleaseLevel = null;

        if (null !== $objReleaseLevelModel) {
            $nextReleaseLevel = $objReleaseLevelModel->level + 1;
        }
        // Save to database
        if ('releaseLevelNext' === Input::get('action') && (int) Input::get('eventId') === (int) $row['id']) {
            if (true === EventReleaseLevelPolicyModel::allowSwitchingEventReleaseLevel($this->User->id, $row['id'], 'up') && true === EventReleaseLevelPolicyModel::levelExists($row['id'], $nextReleaseLevel)) {
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
                                System::importStatic($callback[0])->{$callback[1]}($objEvent, $strDirection);
                            }
                        }
                    }
                }
            }
            $this->redirect($this->getReferer());
        }

        if (true === EventReleaseLevelPolicyModel::allowSwitchingEventReleaseLevel($this->User->id, $row['id'], $strDirection) && true === EventReleaseLevelPolicyModel::levelExists($row['id'], $nextReleaseLevel)) {
            $canSendToNextReleaseLevel = true;
        }

        if (false === $canSendToNextReleaseLevel) {
            return '';
        }

        return '<a href="'.$this->addToUrl($href.'&amp;eventId='.$row['id']).'" title="'.StringUtil::specialchars($title).'"'.$attributes.'>'.Image::getHtml($icon, $label).'</a> ';
    }

    /**
     * save_callback for tl_calendar_events.instructor
     * Update main instructor (first instructor in the list is the main instructor).
     *
     * @param $varValue
     *
     * @return mixed
     */
    public function saveCallbackSetMaininstructor($varValue, DataContainer $dc)
    {
        if (isset($dc) && $dc->id > 0) {
            $arrInstructors = StringUtil::deserialize($varValue, true);

            // Use a child table to store instructors
            // Delete instructor
            $this->Database
                ->prepare('DELETE FROM tl_calendar_events_instructor WHERE pid=?')
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
                $this->Database->prepare('INSERT INTO tl_calendar_events_instructor %s')
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
                    $this->Database
                        ->prepare('UPDATE tl_calendar_events %s WHERE id=?')
                        ->set($set)
                        ->execute($dc->id)
                    ;

                    return $varValue;
                }
            }

            $set = ['mainInstructor' => 0];
            $this->Database->prepare('UPDATE tl_calendar_events %s WHERE id=?')->set($set)->execute($dc->id);
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
                        Message::addInfo(sprintf($GLOBALS['TL_LANG']['MSC']['publishedEvent'], $objEvent->id));
                    }
                    $objEvent->published = '1';

                    // HOOK: publishEvent, f.ex advice tourenchef by email
                    if (isset($GLOBALS['TL_HOOKS']['publishEvent']) && \is_array($GLOBALS['TL_HOOKS']['publishEvent'])) {
                        foreach ($GLOBALS['TL_HOOKS']['publishEvent'] as $callback) {
                            System::importStatic($callback[0])->{$callback[1]}($objEvent);
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
                                Message::addError(sprintf('Die Freigabestufe für Event "%s (ID: %s)" konnte nicht auf "%s" geändert werden, weil diese Freigabestufe zum Event-Typ ungültig ist. ', $objEvent->title, $objEvent->id, $eventReleaseModel->title));
                            } else {
                                $newEventReleaseLevelId = $firstEventReleaseModel->id;
                                Message::addError(sprintf('Die Freigabestufe für Event "%s (ID: %s)" musste auf "%s" korrigiert werden, weil eine zum Event-Typ ungültige Freigabestufe gewählt wurde. ', $objEvent->title, $objEvent->id, $firstEventReleaseModel->title));
                            }
                        }
                    }

                    if ($objEvent->published) {
                        Message::addInfo(sprintf($GLOBALS['TL_LANG']['MSC']['unpublishedEvent'], $objEvent->id));
                    }
                    $objEvent->published = '';
                }
                $objEvent->save();

                if (!$hasError) {
                    // Display message in the backend
                    Message::addInfo(sprintf($GLOBALS['TL_LANG']['MSC']['setEventReleaseLevelTo'], $objEvent->id, EventReleaseLevelPolicyModel::findByPk($newEventReleaseLevelId)->level));
                }
            }
        }

        return $newEventReleaseLevelId;
    }

    /**
     * @param $strDuration
     * @param null $eventId
     *
     * @return string
     */
    public function onSubmitCallbackDurationInfo($strDuration, DataContainer $dc = null, $eventId = null)
    {
        // Get event id
        if ($dc->activeRecord->id > 0) {
            $objEvent = CalendarEventsModel::findByPk($dc->activeRecord->id);
        } elseif ($eventId > 0) {
            $objEvent = CalendarEventsModel::findByPk($eventId);
        }

        if (false !== CalendarEventsHelper::getEventTimestamps($objEvent)) {
            $countTimestamps = \count(CalendarEventsHelper::getEventTimestamps($objEvent));

            $arrDuration = $GLOBALS['TL_CONFIG']['SAC-EVENT-TOOL-CONFIG']['durationInfo'][$strDuration];

            if (!empty($arrDuration) && \is_array($arrDuration)) {
                $duration = $arrDuration['dateRows'];

                if ($duration !== $countTimestamps) {
                    Message::addError(sprintf('Die Event-Dauer in "%s" [ID:%s] stimmt nicht mit der Anzahl Event-Daten überein.', $objEvent->title, $objEvent->id));

                    return '';
                }
            }
        }

        return $strDuration;
    }

    /**
     * @param $strEventType
     * @param null $eventId
     *
     * @return mixed
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
     */
    public function releaseLevelPrev($row, $href, $label, $title, $icon, $attributes)
    {
        $strDirection = 'down';

        $canSendToNextReleaseLevel = false;
        $prevReleaseLevel = null;
        $objReleaseLevelModel = EventReleaseLevelPolicyModel::findByPk($row['eventReleaseLevel']);

        if (null !== $objReleaseLevelModel) {
            $prevReleaseLevel = $objReleaseLevelModel->level - 1;
        }

        // Save to database
        if ('releaseLevelPrev' === Input::get('action') && (int) Input::get('eventId') === (int) $row['id']) {
            if (true === EventReleaseLevelPolicyModel::allowSwitchingEventReleaseLevel($this->User->id, $row['id'], 'down') && true === EventReleaseLevelPolicyModel::levelExists($row['id'], $prevReleaseLevel)) {
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
                                System::importStatic($callback[0])->{$callback[1]}($objEvent, $strDirection);
                            }
                        }
                    }
                }
            }
            $this->redirect($this->getReferer());
        }

        if (true === EventReleaseLevelPolicyModel::allowSwitchingEventReleaseLevel($this->User->id, $row['id'], $strDirection) && true === EventReleaseLevelPolicyModel::levelExists($row['id'], $prevReleaseLevel)) {
            $canSendToNextReleaseLevel = true;
        }

        if (false === $canSendToNextReleaseLevel) {
            return '';
        }

        return '<a href="'.$this->addToUrl($href.'&amp;eventId='.$row['id']).'" title="'.StringUtil::specialchars($title).'"'.$attributes.'>'.Image::getHtml($icon, $label).'</a> ';
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
     * @return string
     */
    public function deleteIcon($row, $href, $label, $title, $icon, $attributes)
    {
        $blnAllow = EventReleaseLevelPolicyModel::canDeleteEvent($this->User->id, $row['id']);

        if (!$blnAllow) {
            return '';
        }

        return '<a href="'.$this->addToUrl($href.'&amp;id='.$row['id']).'" title="'.StringUtil::specialchars($title).'"'.$attributes.'>'.Image::getHtml($icon, $label).'</a> ';
    }

    /**
     * @param $row
     * @param $href
     * @param $label
     * @param $title
     * @param $icon
     * @param $attributes
     *
     * @return string
     */
    public function copyIcon($row, $href, $label, $title, $icon, $attributes)
    {
        $blnAllow = EventReleaseLevelPolicyModel::hasWritePermission($this->User->id, $row['id']);

        if (!$blnAllow) {
            return '';
        }

        return '<a href="'.$this->addToUrl($href.'&amp;id='.$row['id']).'" title="'.StringUtil::specialchars($title).'"'.$attributes.'>'.Image::getHtml($icon, $label).'</a> ';
    }
}
