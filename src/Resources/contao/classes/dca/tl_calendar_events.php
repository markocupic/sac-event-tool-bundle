<?php

/**
 * SAC Event Tool Web Plugin for Contao
 * Copyright (c) 2008-2017 Marko Cupic
 * @package sac-event-tool-bundle
 * @author Marko Cupic m.cupic@gmx.ch, 2017-2018
 * @link https://sac-kurse.kletterkader.com
 */

use League\Csv\CharsetConverter;
use League\Csv\Writer;


/**
 * Class tl_calendar_events_sac_event_tool
 */
class tl_calendar_events_sac_event_tool extends tl_calendar_events
{

    /**
     * Import the back end user object
     */
    public function __construct()
    {
        // Set correct referer
        if (Input::get('do') === 'sac_calendar_events_tool' && Input::get('ref') != '')
        {
            $objSession = static::getContainer()->get('session');
            $ref = Input::get('ref');
            $session = $objSession->get('referer');
            if (isset($session[$ref]['tl_calendar_container']))
            {
                $session[$ref]['tl_calendar_container'] = str_replace('do=calendar', 'do=sac_calendar_events_tool', $session[$ref]['tl_calendar_container']);
                $objSession->set('referer', $session);
            }
            if (isset($session[$ref]['tl_calendar']))
            {
                $session[$ref]['tl_calendar'] = str_replace('do=calendar', 'do=sac_calendar_events_tool', $session[$ref]['tl_calendar']);
                $objSession->set('referer', $session);
            }
            if (isset($session[$ref]['tl_calendar_events']))
            {
                $session[$ref]['tl_calendar_events'] = str_replace('do=calendar', 'do=sac_calendar_events_tool', $session[$ref]['tl_calendar_events']);
                $objSession->set('referer', $session);
            }
            if (isset($session[$ref]['tl_calendar_events_instructor_invoice']))
            {
                $session[$ref]['tl_calendar_events_instructor_invoice'] = str_replace('do=calendar', 'do=sac_calendar_events_tool', $session[$ref]['tl_calendar_events_instructor_invoice']);
                $objSession->set('referer', $session);
            }
        }

        $this->import('BackendUser', 'User');


        return parent::__construct();
    }


    /**
     * Manipulate palette when creating a new datarecord
     *
     * @param DataContainer $dc
     *
     */
    public function setPaletteWhenCreatingNew(DataContainer $dc)
    {
        if (Input::get('act') === 'edit')
        {
            $objCalendarEventsModel = CalendarEventsModel::findByPk($dc->id);
            if ($objCalendarEventsModel !== null)
            {
                if ($objCalendarEventsModel->tstamp == 0 && $objCalendarEventsModel->eventType == '')
                {
                    $GLOBALS['TL_DCA']['tl_calendar_events']['palettes']['default'] = 'eventType';
                }
            }
        }
    }

    /**
     * Do not show the same filters for different event types
     * @param DataContainer $dc
     */
    public function setFilterSearchAndSortingBoard(DataContainer $dc)
    {

        if (CURRENT_ID > 0)
        {
            $objCalendar = \Contao\CalendarModel::findByPk(CURRENT_ID);
            if ($objCalendar !== null)
            {
                $arrAllowedEventTypes = \Contao\StringUtil::deserialize($objCalendar->allowedEventTypes, true);
                if (!in_array('tour', $arrAllowedEventTypes) && !in_array('lastMinuteTour', $arrAllowedEventTypes))
                {
                    $GLOBALS['TL_DCA']['tl_calendar_events']['fields']['tourType']['filter'] = false;
                    $GLOBALS['TL_DCA']['tl_calendar_events']['fields']['tourType']['search'] = false;
                    $GLOBALS['TL_DCA']['tl_calendar_events']['fields']['tourType']['sorting'] = false;
                }

                if (!in_array('course', $arrAllowedEventTypes))
                {
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
     * onload_callback deleteInvalidEvents
     * @param DataContainer $dc
     */
    public function deleteInvalidEvents(DataContainer $dc)
    {
        $this->Database->prepare('DELETE FROM tl_calendar_events WHERE tstamp<? AND tstamp>? AND title=?')->execute(time() - 24 * 60 * 60, 0, '');
    }

    /**
     * onload_callback onloadCallback
     * @param DataContainer $dc
     */
    public function onloadCallback(DataContainer $dc)
    {
        // Minimize header fields for default users
        if (!$this->User->isAdmin)
        {
            $GLOBALS['TL_DCA']['tl_calendar_events']['list']['sorting']['headerFields'] = array('title');
        }

        // Minimize operations for default users
        if (!$this->User->isAdmin)
        {
            unset($GLOBALS['TL_DCA']['tl_calendar_events']['list']['operations']['show']);
        }


        // Do not allow this to default users
        if (!$this->User->isAdmin)
        {
            unset($GLOBALS['TL_DCA']['tl_calendar_events']['list']['global_operations']['plus1year']);
            unset($GLOBALS['TL_DCA']['tl_calendar_events']['list']['global_operations']['minus1year']);
        }


        // Set tl_calendar_events.mainInstructor from tl_calendar_events.instructor
        // First instructor in the list will be the main instructor
        $objEvent = $this->Database->prepare('SELECT * FROM tl_calendar_events')->execute();
        while ($objEvent->next())
        {
            $arrGuides = \Markocupic\SacEventToolBundle\CalendarEventsHelper::getInstructorsAsArray($objEvent->id, false);
            if (count($arrGuides) > 0)
            {
                $objEv = CalendarEventsModel::findByPk($objEvent->id);
                if ($objEv !== null)
                {
                    // First instructor in the list will be the main instructor
                    $objEv->mainInstructor = $arrGuides[0];
                    $objEv->save();
                }
            }
        }


        // Special treatment for tl_calendar_events.eventReleaseLevel
        // Do not allow multi edit on tl_calendar_events.eventReleaseLevel, if user has not write permissions on every level
        if (Input::get('act') === 'editAll' || Input::get('act') === 'overrideAll')
        {
            $allow = true;
            $objSession = System::getContainer()->get('session');
            $session = $objSession->get('CURRENT');
            $arrIDS = $session['IDS'];
            foreach ($arrIDS as $eventId)
            {
                $objEvent = \Contao\CalendarEventsModel::findByPk($eventId);
                if ($objEvent !== null)
                {
                    $objEventReleaseLevelPolicyPackageModel = \Contao\EventReleaseLevelPolicyPackageModel::findReleaseLevelPolicyPackageModelByEventId($eventId);
                    if ($objEventReleaseLevelPolicyPackageModel !== null)
                    {
                        $objReleaseLevelModel = \Contao\EventReleaseLevelPolicyModel::findByPid($objEventReleaseLevelPolicyPackageModel->id);
                        if ($objReleaseLevelModel !== null)
                        {
                            while ($objReleaseLevelModel->next())
                            {
                                $allow = false;
                                $arrGroupsUserBelongsTo = \StringUtil::deserialize($this->User->groups, true);
                                $arrGroups = \StringUtil::deserialize($objReleaseLevelModel->groupReleaseLevelRights, true);
                                foreach ($arrGroups as $k => $v)
                                {
                                    if (in_array($v['group'], $arrGroupsUserBelongsTo))
                                    {
                                        if ($v['releaseLevelRights'] === 'upAndDown')
                                        {
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

            if ($this->User->isAdmin || $allow === true)
            {
                \Contao\CoreBundle\DataContainer\PaletteManipulator::create()
                    ->addField(array('eventReleaseLevel'), 'title_legend', \Contao\CoreBundle\DataContainer\PaletteManipulator::POSITION_APPEND)
                    ->applyToPalette('default', 'tl_calendar_events');
            }
        }


        if ($this->User->isAdmin)
        {
            return;
        }


        // Do not allow cutting an editing
        $GLOBALS['TL_DCA']['tl_calendar_events']['list']['operations']['edit'] = null;


        // Limit filter fields
        foreach ($GLOBALS['TL_DCA']['tl_calendar_events']['fields'] as $k => $v)
        {
            if ($k === 'author' || $k === 'organizers' || $k === 'tourType' || $k === 'eventReleaseLevel' || $k === 'mainInstructor' || $k === 'courseTypeLevel0' || $k === 'startTime')
            {
                continue;
            }

            $GLOBALS['TL_DCA']['tl_calendar_events']['fields'][$k]['filter'] = null;
        }


        // Schutz vor unbefugtem veroeffentlichen
        if (Input::get('tid'))
        {
            $objDb = $this->Database->prepare('SELECT * FROM tl_calendar_events WHERE id=?')->execute(Input::get('tid'));
            if ($objDb->next())
            {
                if (!EventReleaseLevelPolicyModel::hasWritePermission($this->User->id, $objDb->id))
                {
                    Message::addError(sprintf($GLOBALS['TL_LANG']['MSC']['missingPermissionsToPublishOrUnpublishEvent'], $objDb->id));
                    $this->redirect($this->getReferer());
                }
            }
        }


        // Schutz vor unbefugtem Loeschen
        if (Input::get('act') === 'delete')
        {
            $objDb = $this->Database->prepare('SELECT * FROM tl_calendar_events WHERE id=?')->limit(1)->execute($dc->id);
            if ($objDb->numRows)
            {
                if (!EventReleaseLevelPolicyModel::canDeleteEvent($this->User->id, $objDb->id))
                {
                    Message::addError(sprintf($GLOBALS['TL_LANG']['MSC']['missingPermissionsToDeleteEvent'], $objDb->id));
                    $this->redirect($this->getReferer());
                }
            }
        }


        // Schutz vor unbefugtem Bearbeiten
        if (Input::get('act') === 'edit')
        {
            $objEventsModel = CalendarEventsModel::findOneById(Input::get('id'));
            if ($objEventsModel !== null)
            {
                if (EventReleaseLevelPolicyModel::findByPk($objEventsModel->eventReleaseLevel) !== null)
                {
                    if (!EventReleaseLevelPolicyModel::hasWritePermission($this->User->id, $objEventsModel->id) && $this->User->id !== $objEventsModel->registrationGoesTo)
                    {
                        // User has no write access to the datarecord, so present field values without the form input
                        foreach ($GLOBALS['TL_DCA']['tl_calendar_events']['fields'] as $field => $dca)
                        {
                            $GLOBALS['TL_DCA']['tl_calendar_events']['fields'][$field]['input_field_callback'] = array('tl_calendar_events_sac_event_tool', 'showFieldValue');
                        }
                        if (Input::post('FORM_SUBMIT') === 'tl_calendar_events')
                        {
                            Message::addError(sprintf($GLOBALS['TL_LANG']['MSC']['missingPermissionsToEditEvent'], $objEventsModel->id));
                            $this->redirect($this->getReferer());
                        }
                    }
                    else
                    {
                        // Protect fields with $GLOBALS['TL_DCA']['tl_calendar_events']['fields'][$fieldname]['allowEdititingOnFirstReleaseLevelOnly'] == true,
                        // if the event is on the first release level
                        if (!$this->User->isAdmin)
                        {
                            $objEventReleaseLevelPolicyPackageModel = EventReleaseLevelPolicyPackageModel::findReleaseLevelPolicyPackageModelByEventId($objEventsModel->id);
                            if ($objEventReleaseLevelPolicyPackageModel !== null)
                            {
                                if ($objEventsModel->eventReleaseLevel > 0)
                                {
                                    $objEventReleaseLevelPolicyModel = EventReleaseLevelPolicyModel::findFirstLevelByEventId($objEventsModel->id);
                                    if ($objEventReleaseLevelPolicyModel !== null)
                                    {
                                        if ($objEventReleaseLevelPolicyModel->id != $objEventsModel->eventReleaseLevel)
                                        {
                                            foreach ($GLOBALS['TL_DCA']['tl_calendar_events']['fields'] as $fieldname => $arrDca)
                                            {
                                                if ($GLOBALS['TL_DCA']['tl_calendar_events']['fields'][$fieldname]['allowEdititingOnFirstReleaseLevelOnly'] == true && $GLOBALS['TL_DCA']['tl_calendar_events']['fields'][$fieldname]['inputType'] != '')
                                                {
                                                    $GLOBALS['TL_DCA']['tl_calendar_events']['fields'][$fieldname]['input_field_callback'] = array('tl_calendar_events_sac_event_tool', 'showFieldValue');
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


        // Allow select mode only, if there is set an eventReleaseLevel filter.
        if (Input::get('act') === 'select')
        {
            /** @var AttributeBagInterface $objSessionBag */
            $objSessionBag = \System::getContainer()->get('session')->getBag('contao_backend');
            $session = $objSessionBag->all();
            $filter = ($GLOBALS['TL_DCA']['tl_calendar_events']['list']['sorting']['mode'] == 4) ? 'tl_calendar_events_' . CURRENT_ID : 'tl_calendar_events';

            if (!isset($session['filter'][$filter]['eventReleaseLevel']))
            {
                Message::addInfo('"Mehrere bearbeiten" nur möglich, wenn ein Freigabestufen-Filter gesetzt wurde."');
                $this->redirect($this->getReferer());
                return;
            }
        }


        // Nur Datensätze auflisten bei denen der angemeldete Benutzer schreibberechtigt ist
        if (Input::get('act') === 'select' || Input::get('act') === 'editAll')
        {
            $arrIDS = array(0);
            $objDb = $this->Database->prepare('SELECT * FROM tl_calendar_events WHERE pid=?')->execute(CURRENT_ID);
            while ($objDb->next())
            {
                if (EventReleaseLevelPolicyModel::hasWritePermission($this->User->id, $objDb->id))
                {
                    $arrIDS[] = $objDb->id;
                }
            }
            $GLOBALS['TL_DCA']['tl_calendar_events']['list']['sorting']['root'] = $arrIDS;

        }

        // Do not allow editing write protected fields in editAll mode
        // Use input_field_callback to only show the field values without any form input field
        if (Input::get('act') === 'editAll' || Input::get('act') === 'overrideAll')
        {
            $objSession = System::getContainer()->get('session');
            $session = $objSession->get('CURRENT');
            $arrIDS = $session['IDS'];
            if (!empty($arrIDS) && is_array($arrIDS))
            {
                $objEventsModel = CalendarEventsModel::findByPk($arrIDS[1]);
                if ($objEventsModel !== null)
                {
                    if ($objEventsModel->eventReleaseLevel > 0)
                    {
                        $objEventReleaseLevelPolicyModel = EventReleaseLevelPolicyModel::findFirstLevelByEventId($objEventsModel->id);
                        if ($objEventReleaseLevelPolicyModel !== null)
                        {
                            if ($objEventReleaseLevelPolicyModel->id != $objEventsModel->eventReleaseLevel)
                            {
                                foreach ($GLOBALS['TL_DCA']['tl_calendar_events']['fields'] as $fieldname => $arrDca)
                                {
                                    if ($GLOBALS['TL_DCA']['tl_calendar_events']['fields'][$fieldname]['allowEdititingOnFirstReleaseLevelOnly'] == true)
                                    {
                                        if (Input::get('act') === 'editAll')
                                        {
                                            $GLOBALS['TL_DCA']['tl_calendar_events']['fields'][$fieldname]['input_field_callback'] = array('tl_calendar_events_sac_event_tool', 'showFieldValue');

                                        }
                                        else
                                        {
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
     * onload_callback setPalettes
     * Set palette for course, tour, tour_report, etc
     * @param DataContainer $dc
     */
    public function setPalettes(DataContainer $dc)
    {
        if (\Input::get('act') === 'editAll' || \Input::get('act') === 'overrideAll')
        {
            return;
        }

        if ($dc->id > 0)
        {
            if (\Input::get('call') === 'writeTourReport')
            {
                $GLOBALS['TL_DCA']['tl_calendar_events']['palettes']['default'] = $GLOBALS['TL_DCA']['tl_calendar_events']['palettes']['tour_report'];
                return;
            }


            // Set palette fort tour and course
            $objCalendarEventsModel = CalendarEventsModel::findByPk($dc->id);
            if ($objCalendarEventsModel !== null)
            {

                if (isset($GLOBALS['TL_DCA']['tl_calendar_events']['palettes'][$objCalendarEventsModel->eventType]))
                {
                    $GLOBALS['TL_DCA']['tl_calendar_events']['palettes']['default'] = $GLOBALS['TL_DCA']['tl_calendar_events']['palettes'][$objCalendarEventsModel->eventType];
                }
            }
        }
    }

    /**
     * onload_callback triggerGlobalOperations
     * transform dates of all events of a certain calendar
     * https://somehost/contao?do=sac_calendar_events_tool&table=tl_calendar_events&id=21&transformDate=+52weeks&rt=hUFF18TV1YCLddb-Cyb48dRH8y_9iI-BgM-Nc1rB8o8&ref=2sjHl6mB
     */
    public function triggerGlobalOperations(DataContainer $dc)
    {

        if (Input::get('action') === 'downloadEventList' && Input::get('id') > 0)
        {


            //Create empty document
            $csv = Writer::createFromString('');

            // Set encoding from utf-8 to is0-8859-15 (windows)
            $encoder = (new CharsetConverter())
                ->outputEncoding('iso-8859-15');
            $csv->addFormatter($encoder);

            // Set delimiter
            $csv->setDelimiter(';');


            // Header fields
            $arrFields = array('id', 'title', 'dates', 'organizers', 'mainInstructor', 'instructors', 'eventType', 'tourType', 'tourTechDifficulties', 'eventReleaseLevel');
            $csv->insertOne($arrFields);

            $objEvent = $this->Database->prepare('SELECT * FROM tl_calendar_events WHERE pid=? ORDER BY startDate ASC')->execute(Input::get('id'));
            while ($objEvent->next())
            {
                $arrRow = array();
                foreach ($arrFields as $field)
                {
                    if ($field === 'mainInstructor')
                    {
                        $objUser = \Contao\UserModel::findByPk($objEvent->{$field});
                        $arrRow[] = $objUser !== null ? html_entity_decode($objUser->lastname . ' ' . $objUser->firstname) : '';
                    }
                    elseif ($field === 'tourTechDifficulties')
                    {
                        $arrDiff = \Markocupic\SacEventToolBundle\CalendarEventsHelper::getTourTechDifficultiesAsArray($objEvent->id, false);
                        $arrRow[] = implode(' und ', $arrDiff);
                    }
                    elseif ($field === 'dates')
                    {
                        $arrTimestamps = \Markocupic\SacEventToolBundle\CalendarEventsHelper::getEventTimestamps($objEvent->id);
                        $arrDates = array_map(function ($tstamp) {
                            return \Contao\Date::parse(\Contao\Config::get('dateFormat'), $tstamp);
                        }, $arrTimestamps);
                        $arrRow[] = implode(',', $arrDates);
                    }
                    elseif ($field === 'organizers')
                    {
                        $arrOrganizers = \Markocupic\SacEventToolBundle\CalendarEventsHelper::getEventOrganizersAsArray($objEvent->id, 'title');
                        $arrRow[] = html_entity_decode(implode(',', $arrOrganizers));
                    }
                    elseif ($field === 'instructors')
                    {
                        $arrInstructors = \Markocupic\SacEventToolBundle\CalendarEventsHelper::getInstructorNamesAsArray($objEvent->id, false, false);
                        $arrRow[] = html_entity_decode(implode(',', $arrInstructors));
                    }
                    elseif ($field === 'tourType')
                    {
                        $arrTourTypes = \Markocupic\SacEventToolBundle\CalendarEventsHelper::getTourTypesAsArray($objEvent->id, 'title');
                        $arrRow[] = html_entity_decode(implode(',', $arrTourTypes));
                    }
                    elseif ($field === 'eventReleaseLevel')
                    {
                        $objFS = \Contao\EventReleaseLevelPolicyModel::findByPk($objEvent->{$field});
                        $arrRow[] = $objFS !== null ? $objFS->level : '';
                    }
                    else
                    {
                        $arrRow[] = $objEvent->{$field};
                    }
                }
                $csv->insertOne($arrRow);

            }

            $objCalendar = \Contao\CalendarModel::findByPk(Input::get('id'));
            $csv->output($objCalendar->title . '.csv');
            die();
        }


        if (Input::get('transformDates'))
        {
            // $mode may be "+52weeks" or "+1year"
            $mode = Input::get('transformDates');
            if (strtotime($mode) !== false)
            {
                $calendarId = Input::get('id');

                $objEvent = $this->Database->prepare('SELECT * FROM tl_calendar_events WHERE pid=?')->execute($calendarId);
                while ($objEvent->next())
                {
                    $set['startTime'] = strtotime($mode, $objEvent->startTime);
                    $set['endTime'] = strtotime($mode, $objEvent->endTime);
                    $set['startDate'] = strtotime($mode, $objEvent->startDate);
                    $set['endDate'] = strtotime($mode, $objEvent->endDate);

                    if ($objEvent->registrationStartDate > 0)
                    {
                        $set['registrationStartDate'] = strtotime($mode, $objEvent->registrationStartDate);
                    }
                    if ($objEvent->registrationEndDate > 0)
                    {
                        $set['registrationEndDate'] = strtotime($mode, $objEvent->registrationEndDate);
                    }

                    $arrRepeats = StringUtil::deserialize($objEvent->eventDates, true);
                    $newArrRepeats = array();
                    if (count($arrRepeats) > 0)
                    {
                        foreach ($arrRepeats as $repeat)
                        {
                            $repeat['new_repeat'] = strtotime($mode, $repeat['new_repeat']);
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
     * input_field_callback showFieldValue
     * @param $dc
     * @param $field
     * @return string
     */
    public function showFieldValue(Contao\DC_Table $dc)
    {
        $field = $dc->field;

        $strTable = 'tl_calendar_events';
        if (!strlen($dc->activeRecord->id))
        {
            return '';
        }
        $intId = $dc->activeRecord->id;

        $objRow = $this->Database->prepare("SELECT " . $field . " FROM tl_calendar_events WHERE id=?")
            ->limit(1)
            ->execute($intId);

        if ($objRow->numRows < 1)
        {
            return '';
        }

        $return = '';
        $row = $objRow->row();

        // Get the order fields
        $objDcaExtractor = \DcaExtractor::getInstance($strTable);
        $arrOrder = $objDcaExtractor->getOrderFields();

        // Get all fields
        $fields = array_keys($row);
        $allowedFields = array('id', 'pid', 'sorting', 'tstamp');

        if (is_array($GLOBALS['TL_DCA'][$strTable]['fields']))
        {
            $allowedFields = array_unique(array_merge($allowedFields, array_keys($GLOBALS['TL_DCA'][$strTable]['fields'])));
        }

        // Use the field order of the DCA file
        $fields = array_intersect($allowedFields, $fields);

        // Show all allowed fields
        foreach ($fields as $i)
        {

            if (!in_array($i, $allowedFields) || $GLOBALS['TL_DCA'][$strTable]['fields'][$i]['inputType'] == 'password' || $GLOBALS['TL_DCA'][$strTable]['fields'][$i]['eval']['doNotShow'] || $GLOBALS['TL_DCA'][$strTable]['fields'][$i]['eval']['hideInput'])
            {
                continue;
            }

            // Special treatment for table tl_undo
            if ($strTable == 'tl_undo' && $i == 'data')
            {
                continue;
            }

            $value = \StringUtil::deserialize($row[$i]);


            // Decrypt the value
            if ($GLOBALS['TL_DCA'][$strTable]['fields'][$i]['eval']['encrypt'])
            {
                $value = \Encryption::decrypt($value);
            }

            // Default value
            $row[$i] = '';

            // Get the field value
            if ($i === 'eventType')
            {
                $row[$i] = $value;
            }
            elseif ($i === 'eventState')
            {
                $row[$i] = $value === '' ? '---' : $value;
            }
            elseif ($i === 'eventDates')
            {
                if (!empty($value) && is_array($value))
                {
                    $arrDate = array();
                    foreach ($value as $arrTstamp)
                    {
                        $arrDate[] = \Date::parse('D, d.m.Y', $arrTstamp['new_repeat']);
                    }
                    $row[$i] = implode('<br>', $arrDate);
                }
            }
            elseif ($i === 'tourProfile')
            {
                // Special treatment for tourProfile
                $arrProfile = array();
                $m = 0;
                if (!empty($value) && is_array($value))
                {
                    foreach ($value as $profile)
                    {
                        $m++;
                        if (count($value) > 1)
                        {
                            $pattern = $m . '. Tag &nbsp;&nbsp;&nbsp; Aufstieg: %s m/%s h &nbsp;&nbsp;&nbsp;Abstieg: %s m/%s h';
                        }
                        else
                        {
                            $pattern = 'Aufstieg: %s m/%s h &nbsp;&nbsp;&nbsp;Abstieg: %s m/%s h';
                        }
                        $arrProfile[] = sprintf($pattern, $profile['tourProfileAscentMeters'], $profile['tourProfileAscentTime'], $profile['tourProfileDescentMeters'], $profile['tourProfileDescentTime']);
                    }
                }
                if (!empty($arrProfile))
                {
                    $row[$i] = implode('<br>', $arrProfile);
                }
            }
            elseif ($i === 'instructor')
            {
                // Special treatment for instructor
                $arrInstructors = array();
                foreach ($value as $arrInstructor)
                {
                    if ($arrInstructor['instructorId'] > 0)
                    {
                        $objUser = UserModel::findByPk($arrInstructor['instructorId']);
                        {
                            if ($objUser !== null)
                            {
                                $arrInstructors[] = $objUser->name;
                            }
                        }
                    }
                }

                if (!empty($arrInstructors))
                {
                    $row[$i] = implode('<br>', $arrInstructors);
                }
            }
            elseif ($i === 'tourTechDifficulty')
            {
                // Special treatment for tourTechDifficulty
                $arrDiff = array();
                foreach ($value as $difficulty)
                {
                    $strDiff = '';
                    if (strlen($difficulty['tourTechDifficultyMin']) && strlen($difficulty['tourTechDifficultyMax']))
                    {
                        $objDiff = $this->Database->prepare('SELECT * FROM tl_tour_difficulty WHERE id=?')->limit(1)->execute(intval($difficulty['tourTechDifficultyMin']));
                        if ($objDiff->numRows)
                        {
                            $strDiff = $objDiff->shortcut;
                        }
                        $objDiff = $this->Database->prepare('SELECT * FROM tl_tour_difficulty WHERE id=?')->limit(1)->execute(intval($difficulty['tourTechDifficultyMax']));
                        if ($objDiff->numRows)
                        {
                            $max = $objDiff->shortcut;
                            $strDiff .= ' - ' . $max;
                        }

                        $arrDiff[] = $strDiff;

                    }
                    elseif (strlen($difficulty['tourTechDifficultyMin']))
                    {
                        $objDiff = $this->Database->prepare('SELECT * FROM tl_tour_difficulty WHERE id=?')->limit(1)->execute(intval($difficulty['tourTechDifficultyMin']));
                        if ($objDiff->numRows)
                        {
                            $strDiff = $objDiff->shortcut;
                        }
                        $arrDiff[] = $strDiff;
                    }
                }

                if (!empty($arrDiff))
                {
                    $row[$i] = implode(', ', $arrDiff);
                }
            }
            elseif (isset($GLOBALS['TL_DCA'][$strTable]['fields'][$i]['foreignKey']))
            {
                $temp = array();
                $chunks = explode('.', $GLOBALS['TL_DCA'][$strTable]['fields'][$i]['foreignKey'], 2);

                foreach ((array)$value as $v)
                {
                    $objKey = $this->Database->prepare("SELECT " . $chunks[1] . " AS value FROM " . $chunks[0] . " WHERE id=?")
                        ->limit(1)
                        ->execute($v);

                    if ($objKey->numRows)
                    {
                        $temp[] = $objKey->value;
                    }
                }

                $row[$i] = implode(', ', $temp);
            }
            elseif ($GLOBALS['TL_DCA'][$strTable]['fields'][$i]['inputType'] == 'fileTree' || in_array($i, $arrOrder))
            {
                if (is_array($value))
                {
                    foreach ($value as $kk => $vv)
                    {
                        if (($objFile = \FilesModel::findByUuid($vv)) instanceof FilesModel)
                        {
                            $value[$kk] = $objFile->path . ' (' . \StringUtil::binToUuid($vv) . ')';
                        }
                        else
                        {
                            $value[$kk] = '';
                        }
                    }

                    $row[$i] = implode('<br>', $value);
                }
                else
                {
                    if (($objFile = \FilesModel::findByUuid($value)) instanceof FilesModel)
                    {
                        $row[$i] = $objFile->path . ' (' . \StringUtil::binToUuid($value) . ')';
                    }
                    else
                    {
                        $row[$i] = '';
                    }
                }
            }
            elseif (is_array($value))
            {
                if (count($value) == 2 && isset($value['value']) && isset($value['unit']))
                {
                    $row[$i] = trim($value['value'] . $value['unit']);
                }
                else
                {
                    foreach ($value as $kk => $vv)
                    {
                        if (is_array($vv))
                        {
                            $vals = array_values($vv);
                            $value[$kk] = array_shift($vals) . ' (' . implode(', ', array_filter($vals)) . ')';
                        }
                    }

                    $row[$i] = implode('<br>', $value);
                }
            }
            elseif ($GLOBALS['TL_DCA'][$strTable]['fields'][$i]['eval']['rgxp'] == 'date')
            {
                $row[$i] = $value ? \Date::parse(\Config::get('dateFormat'), $value) : '-';
            }
            elseif ($GLOBALS['TL_DCA'][$strTable]['fields'][$i]['eval']['rgxp'] == 'time')
            {
                $row[$i] = $value ? \Date::parse(\Config::get('timeFormat'), $value) : '-';
            }
            elseif ($GLOBALS['TL_DCA'][$strTable]['fields'][$i]['eval']['rgxp'] == 'datim' || in_array($GLOBALS['TL_DCA'][$strTable]['fields'][$i]['flag'], array(5, 6, 7, 8, 9, 10)) || $i == 'tstamp')
            {
                $row[$i] = $value ? \Date::parse(\Config::get('datimFormat'), $value) : '-';
            }
            elseif ($GLOBALS['TL_DCA'][$strTable]['fields'][$i]['inputType'] == 'checkbox' && !$GLOBALS['TL_DCA'][$strTable]['fields'][$i]['eval']['multiple'])
            {
                $row[$i] = $value ? $GLOBALS['TL_LANG']['MSC']['yes'] : $GLOBALS['TL_LANG']['MSC']['no'];
            }
            elseif ($GLOBALS['TL_DCA'][$strTable]['fields'][$i]['eval']['rgxp'] == 'email')
            {
                $row[$i] = \Idna::decodeEmail($value);
            }
            elseif ($GLOBALS['TL_DCA'][$strTable]['fields'][$i]['inputType'] == 'textarea' && ($GLOBALS['TL_DCA'][$strTable]['fields'][$i]['eval']['allowHtml'] || $GLOBALS['TL_DCA'][$strTable]['fields'][$i]['eval']['preserveTags']))
            {
                $row[$i] = \StringUtil::specialchars($value);
            }
            elseif (is_array($GLOBALS['TL_DCA'][$strTable]['fields'][$i]['reference']))
            {
                $row[$i] = isset($GLOBALS['TL_DCA'][$strTable]['fields'][$i]['reference'][$row[$i]]) ? ((is_array($GLOBALS['TL_DCA'][$strTable]['fields'][$i]['reference'][$row[$i]])) ? $GLOBALS['TL_DCA'][$strTable]['fields'][$i]['reference'][$row[$i]][0] : $GLOBALS['TL_DCA'][$strTable]['fields'][$i]['reference'][$row[$i]]) : $row[$i];
            }
            elseif ($GLOBALS['TL_DCA'][$strTable]['fields'][$i]['eval']['isAssociative'] || array_is_assoc($GLOBALS['TL_DCA'][$strTable]['fields'][$i]['options']))
            {
                $row[$i] = $GLOBALS['TL_DCA'][$strTable]['fields'][$i]['options'][$row[$i]];
            }
            else
            {
                $row[$i] = $value;
            }


            // Label & help
            if (isset($GLOBALS['TL_DCA'][$strTable]['fields'][$i]['label']))
            {
                $label = is_array($GLOBALS['TL_DCA'][$strTable]['fields'][$i]['label']) ? $GLOBALS['TL_DCA'][$strTable]['fields'][$i]['label'][0] : $GLOBALS['TL_DCA'][$strTable]['fields'][$i]['label'];
                $help = is_array($GLOBALS['TL_DCA'][$strTable]['fields'][$i]['label']) ? $GLOBALS['TL_DCA'][$strTable]['fields'][$i]['label'][1] : $GLOBALS['TL_DCA'][$strTable]['fields'][$i]['label'];
            }
            else
            {
                echo $i . ' ';
                $label = is_array($GLOBALS['TL_LANG']['MSC'][$i]) ? $GLOBALS['TL_LANG']['MSC'][$i][0] : $GLOBALS['TL_LANG']['MSC'][$i];
                $help = is_array($GLOBALS['TL_LANG']['MSC'][$i]) ? $GLOBALS['TL_LANG']['MSC'][$i][1] : $GLOBALS['TL_LANG']['MSC'][$i];
            }

            if ($label == '')
            {
                $label = $i;
            }

            if ($help != '')
            {
                $help = '<p class="tl_help tl_tip">' . $help . '</p>';
            }


            $return .= '
<div class="clr readonly">
    <h3><label for="ctrl_title">' . $label . '</label></h3>
    <div class="field-content-box">' . $row[$i] . '</div>
' . $help . '
</div>';
        }

        // Return Html
        return $return;
    }

    /**
     * @param $arrValues
     * @param \Contao\DC_Table $dc
     * @return array|null|string
     */
    public function loadCallbackeventDates($arrValues, Contao\DC_Table $dc)
    {
        if ($arrValues !== '')
        {
            $arrValues = Contao\StringUtil::deserialize($arrValues, true);
            if (isset($arrValues[0]))
            {
                if ($arrValues[0]['new_repeat'] <= 0)
                {
                    // Replace invalid date to empty string
                    $arrValues = '';
                }
            }
        }

        return $arrValues;
    }

    /**
     * buttons_callback buttonsCallback
     * @param $arrButtons
     * @param $dc
     * @return mixed
     */
    public function buttonsCallback($arrButtons, $dc)
    {

        if (\Contao\Input::get('call') === 'writeTourReport')
        {
            unset($arrButtons['saveNcreate']);
            unset($arrButtons['saveNduplicate']);
            unset($arrButtons['saveNedit']);
        }

        return $arrButtons;
    }

    /**
     * oncreate_callback oncreateCallback
     * @param DataContainer $dc
     */
    public function oncreateCallback($strTable, $insertId, $set, DataContainer $dc)
    {

        // Set source and add author, first release level, when a new event was created & set customEventRegistrationConfirmationEmailText
        $objEventsModel = CalendarEventsModel::findByPk($insertId);
        if ($objEventsModel !== null)
        {
            // Set source always to "default"
            $objEventsModel->source = 'default';

            // Set logged in User as author
            $objEventsModel->author = $this->User->id;
            $objEventsModel->mainInstructor = $this->User->id;
            $objEventsModel->instructor = serialize(array(array('instructorId' => $this->User->id)));

            // Set customEventRegistrationConfirmationEmailText
            $objEventsModel->customEventRegistrationConfirmationEmailText = str_replace('{{br}}',"\n", Config::get('SAC_EVT_ACCEPT_REGISTRATION_EMAIL_TEXT'));

            $objEventsModel->save();
        }
    }

    /**
     * oncopy_callback oncopyCallback
     * @param $insertId
     * @param DC_Table $dc
     */
    public function oncopyCallback($insertId, DC_Table $dc)
    {
        // Add author & first release level, when a new event was created
        $objEventsModel = CalendarEventsModel::findByPk($insertId);
        if ($objEventsModel !== null)
        {
            // Set logged in User as author
            $objEventsModel->author = $this->User->id;
            $objEventsModel->save();

            // Set eventReleaseLevel
            if ($objEventsModel->eventType != '')
            {
                $objEventReleaseLevelPolicyModel = EventReleaseLevelPolicyModel::findFirstLevelByEventId($objEventsModel->id);
                if ($objEventReleaseLevelPolicyModel !== null)
                {
                    $objEventsModel->eventReleaseLevel = $objEventReleaseLevelPolicyModel->id;
                    $objEventsModel->save();
                }
            }
        }
    }

    /**
     * ondelete_callback ondeleteCallback
     * Do not allow non-admins deleting records if there are child records (registrations) in tl_calendar_events_member
     * @param DataContainer $dc
     */
    public function ondeleteCallback(DataContainer $dc)
    {
        // Return if there is no ID
        if (!$dc->activeRecord)
        {
            return;
        }


        if (!$this->User->admin)
        {
            $objDb = $this->Database->prepare('SELECT * FROM tl_calendar_events_member WHERE eventId=?')->execute($dc->activeRecord->id);
            if ($objDb->numRows)
            {
                Message::addError(sprintf($GLOBALS['TL_LANG']['MSC']['deleteEventMembersBeforeDeleteEvent'], $dc->activeRecord->id));
                $this->redirect($this->getReferer());
            }
        }
        /**
         * else
         * {
         * $objDb = $this->Database->prepare('SELECT * FROM tl_calendar_events_member WHERE eventId=?')->execute($dc->activeRecord->id);
         * while ($objDb->next())
         * {
         * $this->Database->prepare('UPDATE tl_calendar_events_member SET eventId=? WHERE id=?')->execute(0, $objDb->id);
         * }
         * }
         *
         * **/
    }

    /**
     * @return array
     */
    public function optionsCallbackGetEventDuration()
    {
        if (is_array($GLOBALS['TL_CONFIG']['SAC-EVENT-TOOL-CONFIG']['durationInfo']) && !empty($GLOBALS['TL_CONFIG']['SAC-EVENT-TOOL-CONFIG']['durationInfo']))
        {
            $opt = $GLOBALS['TL_CONFIG']['SAC-EVENT-TOOL-CONFIG']['durationInfo'];
        }
        else
        {
            $opt = array();
        }
        $arrOpt = array();
        foreach ($opt as $k => $v)
        {
            $arrOpt[] = $k;
        }
        return $arrOpt;
    }

    /**
     * options_callback optionsCbTourDifficulties()
     * @return array
     */
    public function optionsCbTourDifficulties(MultiColumnWizard $dc)
    {
        $options = array();
        $objDb = $this->Database->execute('SELECT * FROM tl_tour_difficulty ORDER BY pid ASC, code ASC');
        while ($objDb->next())
        {
            $objDiffCat = \Contao\TourDifficultyCategoryModel::findByPk($objDb->pid);
            if ($objDiffCat !== null)
            {

                if ($objDiffCat->title != '')
                {
                    if (!isset($options[$objDiffCat->title]))
                    {
                        $options[$objDiffCat->title] = array();
                    }

                    $options[$objDiffCat->title][$objDb->id] = $objDb->shortcut;
                }

            }
        }

        return $options;
    }

    /**
     * @param DataContainer $dc
     * @return array
     */
    public function optionsCbEventType(DataContainer $dc)
    {
        $arrEventTypes = array();
        if (!$dc->id && CURRENT_ID > 0)
        {
            $objCalendar = CalendarModel::findByPk(CURRENT_ID);
        }
        elseif ($dc->id > 0)
        {
            $objCalendar = CalendarEventsModel::findByPk($dc->id)->getRelated('pid');
        }

        $arrAllowedEventTypes = array();
        $objUser = \Contao\BackendUser::getInstance();
        if ($objUser !== null)
        {
            $arrGroups = \Contao\StringUtil::deserialize($objUser->groups, true);
            foreach ($arrGroups as $group)
            {
                $objGroup = \Contao\UserGroupModel::findByPk($group);
                if (!empty($objGroup->allowedEventTypes) && is_array($objGroup->allowedEventTypes))
                {
                    $arrAllowedEvtTypes = \Contao\StringUtil::deserialize($objGroup->allowedEventTypes, true);
                    foreach ($arrAllowedEvtTypes as $eventType)
                    {
                        if (!in_array($eventType, $arrAllowedEventTypes))
                        {
                            $arrAllowedEventTypes[] = $eventType;
                        }
                    }
                }
            }
        }

        if ($objCalendar !== null)
        {
            $arrEventTypes = \Contao\StringUtil::deserialize($objCalendar->allowedEventTypes, true);
            //$arrEventTypes = array_intersect($arrEventTypes,$arrAllowedEventTypes);
        }

        return $arrEventTypes;

    }


    /**
     * options_callback optionsCbCourseSubType()
     * @return array
     */
    public function optionsCbCourseSubType()
    {
        $options = array();
        if (Input::get('act') === 'edit')
        {
            $objEvent = CalendarEventsModel::findByPk(Input::get('id'));
            $sql = "SELECT * FROM tl_course_sub_type WHERE pid='" . $objEvent->courseTypeLevel0 . "' ORDER BY pid, code";
        }
        else
        {
            $sql = "SELECT * FROM tl_course_sub_type ORDER BY pid, code";
        }

        $objType = $this->Database->execute($sql);
        while ($objType->next())
        {
            $options[$objType->id] = $objType->code . ' ' . $objType->name;
        }

        return $options;
    }

    /**
     * options_callback listReleaseLevels
     * @param \Contao\DC_Table $dc
     * @return array
     */
    public function listReleaseLevels(DataContainer $dc)
    {
        $options = array();

        $objUser = \Contao\BackendUser::getInstance();
        $arrAllowedEventTypes = array();

        if ($objUser !== null)
        {
            if (!$objUser->admin)
            {
                $arrGroups = \Contao\StringUtil::deserialize($objUser->groups, true);
                foreach ($arrGroups as $group)
                {
                    $objGroup = \Contao\UserGroupModel::findByPk($group);
                    if ($objGroup !== null)
                    {
                        $arrEventTypes = \Contao\StringUtil::deserialize($objGroup->allowedEventTypes, true);
                        foreach ($arrEventTypes as $eventType)
                        {
                            if (!in_array($eventType, $arrAllowedEventTypes))
                            {
                                $arrAllowedEventTypes[] = $eventType;
                            }
                        }
                    }
                }
                foreach ($arrAllowedEventTypes as $eventType)
                {
                    $objEventType = \Contao\EventTypeModel::findByPk($eventType);
                    if ($objEventType !== null)
                    {
                        $objEventReleasePackage = \Contao\EventReleaseLevelPolicyPackageModel::findByPk($objEventType->levelAccessPermissionPackage);
                        if ($objEventReleasePackage !== null)
                        {
                            $objEventReleaseLevels = $this->Database->prepare('SELECT * FROM tl_event_release_level_policy WHERE pid=? ORDER BY level ASC')->execute($objEventReleasePackage->id);
                            while ($objEventReleaseLevels->next())
                            {
                                $options[\Contao\EventReleaseLevelPolicyModel::findByPk($objEventReleaseLevels->id)->getRelated('pid')->title][$objEventReleaseLevels->id] = $objEventReleaseLevels->title;
                            }
                        }
                    }
                }
            }
            else
            {
                $objEventReleaseLevels = $this->Database->prepare('SELECT * FROM tl_event_release_level_policy ORDER BY pid,level ASC')->execute();
                while ($objEventReleaseLevels->next())
                {
                    $options[\Contao\EventReleaseLevelPolicyModel::findByPk($objEventReleaseLevels->id)->getRelated('pid')->title][$objEventReleaseLevels->id] = $objEventReleaseLevels->title;
                }
            }
        }

        return $options;
    }

    /**
     * multicolumnwizard columnsCallback listFixedDates()
     * @return array|null
     */
    public function listFixedDates()
    {
        $columnFields = null;

        $columnFields = array(
            'new_repeat' => array(
                'label'     => &$GLOBALS['TL_LANG']['tl_calendar_events']['kurstage'],
                'exclude'   => true,
                'inputType' => 'text',
                'default'   => time(),
                'eval'      => array('rgxp' => 'date', 'datepicker' => true, 'doNotCopy' => false, 'style' => 'width:100px', 'tl_class' => 'wizard'),
            ),
        );

        return $columnFields;
    }

    /**
     * onsubmit_callback setEventToken()
     * @param DataContainer $dc
     */
    public function setEventToken(DataContainer $dc)
    {
        // Return if there is no active record (override all)
        if (!$dc->activeRecord)
        {
            return;
        }

        $strToken = md5(rand(100000000, 999999999)) . $dc->id;
        $this->Database->prepare('UPDATE tl_calendar_events SET eventToken=? WHERE id=? AND eventToken=?')->execute($strToken, $dc->activeRecord->id, '');
    }

    /**
     * onsubmit_callback adjustImageSize()
     * @param DataContainer $dc
     */
    public function adjustImageSize(DataContainer $dc)
    {
        // Return if there is no active record (override all)
        if (!$dc->activeRecord)
        {
            return;
        }

        $arrSet['size'] = serialize(array("", "", 11));
        $this->Database->prepare('UPDATE tl_calendar_events %s WHERE id=?')->set($arrSet)->execute($dc->activeRecord->id);

    }

    /**
     * onsubmit_callback adjustEndDate()
     * @param DataContainer $dc
     */
    public function adjustEndDate(DataContainer $dc)
    {
        // Return if there is no active record (override all)
        if (!$dc->activeRecord)
        {
            return;
        }

        $arrDates = StringUtil::deserialize($dc->activeRecord->eventDates);
        if (!is_array($arrDates) || empty($arrDates))
        {
            return;
        }

        $aNew = array();
        foreach ($arrDates as $k => $v)
        {
            $objDate = new Date($v['new_repeat']);
            $aNew[$objDate->timestamp] = $objDate->timestamp;
        }
        ksort($aNew);
        $arrDates = array();
        foreach ($aNew as $v)
        {
            // Save as timestamp
            $arrDates[] = array('new_repeat' => $v);
        }
        $arrSet = array();
        $arrSet['eventDates'] = serialize($arrDates);
        $startTime = !empty($arrDates[0]['new_repeat']) ? $arrDates[0]['new_repeat'] : 0;
        $endTime = !empty($arrDates[count($arrDates) - 1]['new_repeat']) ? $arrDates[count($arrDates) - 1]['new_repeat'] : 0;

        $arrSet['endTime'] = $endTime;
        $arrSet['endDate'] = $endTime;
        $arrSet['startDate'] = $startTime;
        $arrSet['startTime'] = $startTime;
        $this->Database->prepare('UPDATE tl_calendar_events %s WHERE id=?')->set($arrSet)->execute($dc->activeRecord->id);

    }

    /**
     * onsubmit_callback adjustEventReleaseLevel()
     * @param DataContainer $dc
     */
    public function adjustEventReleaseLevel(DataContainer $dc)
    {
        // Return if there is no active record (override all)
        if (!$dc->activeRecord)
        {
            return;
        }

        if ($dc->activeRecord->eventReleaseLevel)
        {
            return;
        }

        // Set releaseLevel to level 1
        $eventReleaseLevelModel = EventReleaseLevelPolicyModel::findFirstLevelByEventId($dc->activeRecord->id);
        if ($eventReleaseLevelModel !== null)
        {
            $set = array('eventReleaseLevel' => $eventReleaseLevelModel->id);
            $this->Database->prepare('UPDATE tl_calendar_events %s WHERE id=?')->set($set)->execute($dc->activeRecord->id);
        }
    }

    /**
     * @param DataContainer $dc
     */
    public function adjustDurationInfo(DataContainer $dc)
    {
        // Return if there is no active record (override all)
        if (!$dc->activeRecord)
        {
            return;
        }

        $objEvent = $this->Database->prepare('SELECT * FROM tl_calendar_events WHERE id=?')->limit(1)->execute($dc->activeRecord->id);
        if ($objEvent->numRows > 0)
        {
            $arrTimestamps = \Markocupic\SacEventToolBundle\CalendarEventsHelper::getEventTimestamps($objEvent->id);
            if ($objEvent->durationInfo != '' && !empty($arrTimestamps) && is_array($arrTimestamps))
            {
                $countTimestamps = count($arrTimestamps);
                if (isset($GLOBALS['TL_CONFIG']['SAC-EVENT-TOOL-CONFIG']['durationInfo'][$objEvent->durationInfo]))
                {
                    $arrDuration = $GLOBALS['TL_CONFIG']['SAC-EVENT-TOOL-CONFIG']['durationInfo'][$objEvent->durationInfo];
                    if (!empty($arrDuration) && is_array($arrDuration))
                    {
                        $duration = $arrDuration['dateRows'];
                        if ($duration != $countTimestamps)
                        {
                            $arrSet = array();
                            $arrSet['durationInfo'] = '';
                            $this->Database->prepare('UPDATE tl_calendar_events %s WHERE id=?')->set($arrSet)->execute($objEvent->id);
                            \Contao\Message::addError(sprintf('Die Event-Dauer in "%s" [ID:%s] stimmt nicht mit der Anzahl Event-Daten überein. Setzen SIe für jeden Event-Tag eine Datumszeile!', $objEvent->title, $objEvent->id), TL_MODE);
                        }
                    }
                }
            }
        }
    }

    /**
     * onsubmit_callback adjustRegistrationPeriod()
     * @param DataContainer $dc
     */
    public function adjustRegistrationPeriod(DataContainer $dc)
    {
        // Return if there is no active record (override all)
        if (!$dc->activeRecord)
        {
            return;
        }

        $objDb = $this->Database->prepare('SELECT * FROM tl_calendar_events WHERE id=?')->limit(1)->execute($dc->activeRecord->id);
        if ($objDb->numRows > 0)
        {
            if ($objDb->setRegistrationPeriod)
            {
                $regEndDate = $objDb->registrationEndDate;
                $regStartDate = $objDb->registrationStartDate;

                if ($regEndDate > $objDb->startDate)
                {
                    $regEndDate = $objDb->startDate;
                    Message::addInfo($GLOBALS['TL_LANG']['MSC']['patchedEndDatePleaseCheck'], TL_MODE);
                }

                if ($regStartDate > $regEndDate)
                {
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
     * Set alias
     * @param DataContainer $dc
     */
    public function onsubmitCallback(DataContainer $dc)
    {
        // Set correct eventReleaseLevel
        $objEvent = CalendarEventsModel::findByPk($dc->activeRecord->id);
        if ($objEvent !== null)
        {
            if ($objEvent->eventType !== '')
            {
                if ($objEvent->eventReleaseLevel > 0)
                {
                    $objEventReleaseLevel = \Contao\EventReleaseLevelPolicyModel::findByPk($objEvent->eventReleaseLevel);
                    if ($objEventReleaseLevel !== null)
                    {
                        $objEventReleaseLevelPackage = EventReleaseLevelPolicyPackageModel::findReleaseLevelPolicyPackageModelByEventId($objEvent->id);
                        // Change eventReleaseLevel when changing eventType...
                        if ($objEventReleaseLevel->pid !== $objEventReleaseLevelPackage->id)
                        {
                            $oEventReleaseLevelModel = EventReleaseLevelPolicyModel::findFirstLevelByEventId($objEvent->id);
                            if ($oEventReleaseLevelModel !== null)
                            {
                                $set = array('eventReleaseLevel' => $oEventReleaseLevelModel->id);
                                $this->Database->prepare('UPDATE tl_calendar_events %s WHERE id=?')->set($set)->execute($objEvent->id);
                            }
                        }
                    }
                }
                else
                {
                    // Add eventReleaseLevel when creating a new event...
                    $oEventReleaseLevelModel = EventReleaseLevelPolicyModel::findFirstLevelByEventId($objEvent->id);
                    $set = array('eventReleaseLevel' => $oEventReleaseLevelModel->id);
                    $this->Database->prepare('UPDATE tl_calendar_events %s WHERE id=?')->set($set)->execute($objEvent->id);
                }
            }
        }
        // End set correct eventReleaseLevel


        // Set filledInEventReportForm, now the invoice form can be printed in tl_calendar_events_instructor_invoice
        if (Input::get('call') === 'writeTourReport')
        {
            $set = array('filledInEventReportForm' => '1');
            $this->Database->prepare('UPDATE tl_calendar_events %s WHERE id=?')->set($set)->execute($dc->activeRecord->id);
        }

        $set = array('alias' => 'event-' . $dc->id);
        $this->Database->prepare('UPDATE tl_calendar_events %s WHERE id=?')->set($set)->execute($dc->activeRecord->id);
    }

    /**
     * childrecord_callback listEvents()
     * Add the type of input field
     * @param array $arrRow
     * @return string
     */
    public function listEvents($arrRow)
    {
        $span = Calendar::calculateSpan($arrRow['startTime'], $arrRow['endTime']);

        if ($span > 0)
        {
            $date = Date::parse(Config::get(($arrRow['addTime'] ? 'datimFormat' : 'dateFormat')), $arrRow['startTime']) . ' – ' . Date::parse(Config::get(($arrRow['addTime'] ? 'datimFormat' : 'dateFormat')), $arrRow['endTime']);
        }
        elseif ($arrRow['startTime'] == $arrRow['endTime'])
        {
            $date = Date::parse(Config::get('dateFormat'), $arrRow['startTime']) . ($arrRow['addTime'] ? ' ' . Date::parse(Config::get('timeFormat'), $arrRow['startTime']) : '');
        }
        else
        {
            $date = Date::parse(Config::get('dateFormat'), $arrRow['startTime']) . ($arrRow['addTime'] ? ' ' . Date::parse(Config::get('timeFormat'), $arrRow['startTime']) . ' – ' . Date::parse(Config::get('timeFormat'), $arrRow['endTime']) : '');
        }


        // Add icon
        if ($arrRow['published'])
        {
            $icon = Image::getHtml('visible.svg', $GLOBALS['TL_LANG']['MSC']['published'], 'title="' . $GLOBALS['TL_LANG']['MSC']['published'] . '"');
        }
        else
        {
            $icon = Image::getHtml('invisible.svg', $GLOBALS['TL_LANG']['MSC']['unpublished'], 'title="' . $GLOBALS['TL_LANG']['MSC']['unpublished'] . '"');
        }


        // Add main instructor
        $strAuthor = '';
        $objUser = UserModel::findByPk($arrRow['mainInstructor']);
        if ($objUser !== null)
        {
            $strAuthor = ' <span style="color:#b3b3b3;padding-left:3px">[Hauptleiter: ' . $objUser->name . ']</span><br>';
        }

        $strRegistrations = '';
        $intNotConfirmed = 0;
        $intAccepted = 0;
        $intRefused = 0;
        $intWaitlisted = 0;
        $intUnsubscribedUser = 0;

        $eventsMemberModel = CalendarEventsMemberModel::findByEventId($arrRow['id']);
        if ($eventsMemberModel !== null)
        {
            while ($eventsMemberModel->next())
            {

                if ($eventsMemberModel->stateOfSubscription === 'subscription-not-confirmed')
                {
                    $intNotConfirmed++;
                }
                if ($eventsMemberModel->stateOfSubscription === 'subscription-accepted')
                {
                    $intAccepted++;
                }
                if ($eventsMemberModel->stateOfSubscription === 'subscription-refused')
                {
                    $intRefused++;
                }
                if ($eventsMemberModel->stateOfSubscription === 'subscription-waitlisted')
                {
                    $intWaitlisted++;
                }
                if ($eventsMemberModel->stateOfSubscription === 'user-has-unsubscribed')
                {
                    $intUnsubscribedUser++;
                }
            }

            $refererId = System::getContainer()->get('request_stack')->getCurrentRequest()->get('_contao_referer_id');

            $href = sprintf("'contao?do=sac_calendar_events_tool&table=tl_calendar_events_member&id=%s&rt=%s&ref=%s'", $arrRow['id'], REQUEST_TOKEN, $refererId);

            if ($intNotConfirmed > 0)
            {
                $strRegistrations .= sprintf('<span class="subscription-badge not-confirmed" title="%s unbestätigte Anmeldungen" role="button" onclick="window.location.href=%s">%s</span>', $intNotConfirmed, $href, $intNotConfirmed);
            }
            if ($intAccepted > 0)
            {
                $strRegistrations .= sprintf('<span class="subscription-badge accepted" title="%s bestätigte Anmeldungen" role="button" onclick="window.location.href=%s">%s</span>', $intAccepted, $href, $intAccepted);
            }
            if ($intRefused > 0)
            {
                $strRegistrations .= sprintf('<span class="subscription-badge refused" title="%s abgelehnte Anmeldungen" role="button" onclick="window.location.href=%s">%s</span>', $intRefused, $href, $intRefused);
            }
            if ($intWaitlisted > 0)
            {
                $strRegistrations .= sprintf('<span class="subscription-badge waitlisted" title="%s Anmeldungen auf Warteliste" role="button" onclick="window.location.href=%s">%s</span>', $intWaitlisted, $href, $intWaitlisted);
            }
            if ($intUnsubscribedUser > 0)
            {
                $strRegistrations .= sprintf('<span class="subscription-badge unsubscribed-user" title="%s Abgemeldete Teilnehmer" role="button" onclick="window.location.href=%s">%s</span>', $intUnsubscribedUser, $href, $intUnsubscribedUser);
            }
        }
        if ($strRegistrations != '')
        {
            $strRegistrations = '<br>' . $strRegistrations;
        }


        // Add event release level
        $strLevel = '';
        $eventReleaseLevelModel = EventReleaseLevelPolicyModel::findByPk($arrRow['eventReleaseLevel']);
        if ($eventReleaseLevelModel !== null)
        {
            $strLevel = sprintf('<span class="release-level-%s" title="Freigabestufe: %s">FS: %s</span> ', $eventReleaseLevelModel->level, $eventReleaseLevelModel->title, $eventReleaseLevelModel->level);
        }

        return '<div class="tl_content_left">' . $icon . ' ' . $strLevel . $arrRow['title'] . ' <span style="color:#999;padding-left:3px">[' . $date . ']</span>' . $strAuthor . $strRegistrations . '</div>';
    }

    /**
     * button_callback toggleIcon()
     * Return the "toggle visibility" button
     *
     * @param array $row
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
        if (strlen(Input::get('tid')))
        {
            $this->toggleVisibility(Input::get('tid'), (Input::get('state') == 1), (@func_get_arg(12) ?: null));
            $this->redirect($this->getReferer());
        }


        // Check permissions AFTER checking the tid, so hacking attempts are logged
        if (!$this->User->hasAccess('tl_calendar_events::published', 'alexf'))
        {
            return '';
        }

        $href .= '&amp;tid=' . $row['id'] . '&amp;state=' . ($row['published'] ? '' : 1);

        if (!$row['published'])
        {
            $icon = 'invisible.svg';
        }

        return '<a href="' . $this->addToUrl($href) . '" title="' . StringUtil::specialchars($title) . '"' . $attributes . '>' . Image::getHtml($icon, $label, 'data-state="' . ($row['published'] ? 1 : 0) . '"') . '</a> ';
    }

    /**
     * Publish/unpublish event
     *
     * @param integer $intId
     * @param boolean $blnVisible
     * @param DataContainer $dc
     *
     * @throws Contao\CoreBundle\Exception\AccessDeniedException
     */
    public function toggleVisibility($intId, $blnVisible, DataContainer $dc = null)
    {
        // Set the ID and action
        Input::setGet('id', $intId);
        Input::setGet('act', 'toggle');

        if ($dc)
        {
            $dc->id = $intId; // see #8043
        }

        $this->checkPermission();

        // Check the field access
        if (!$this->User->hasAccess('tl_calendar_events::published', 'alexf'))
        {
            throw new Contao\CoreBundle\Exception\AccessDeniedException('Not enough permissions to publish/unpublish event ID ' . $intId . '.');
        }

        $objVersions = new Versions('tl_calendar_events', $intId);
        $objVersions->initialize();

        // Trigger the save_callback
        if (is_array($GLOBALS['TL_DCA']['tl_calendar_events']['fields']['published']['save_callback']))
        {
            foreach ($GLOBALS['TL_DCA']['tl_calendar_events']['fields']['published']['save_callback'] as $callback)
            {
                if (is_array($callback))
                {
                    $this->import($callback[0]);
                    $blnVisible = $this->{$callback[0]}->{$callback[1]}($blnVisible, ($dc ?: $this));
                }
                elseif (is_callable($callback))
                {
                    $blnVisible = $callback($blnVisible, ($dc ?: $this));
                }
            }
        }

        // Update the database
        $this->Database->prepare("UPDATE tl_calendar_events SET tstamp=" . time() . ", published='" . ($blnVisible ? '1' : '') . "' WHERE id=?")
            ->execute($intId);

        $objVersions->create();

        // Update the RSS feed (for some reason it does not work without sleep(1))
        sleep(1);
        $this->import('Calendar');
        $this->Calendar->generateFeedsByCalendar(CURRENT_ID);
    }

    /**
     * Push event to the next release level
     * @param $row
     * @param $href
     * @param $label
     * @param $title
     * @param $icon
     * @param $attributes
     * @return string
     */
    public function releaseLevelNext($row, $href, $label, $title, $icon, $attributes)
    {
        $strDirection = 'up';

        $canSendToNextReleaseLevel = false;
        $objReleaseLevelModel = EventReleaseLevelPolicyModel::findByPk($row['eventReleaseLevel']);
        $nextReleaseLevel = null;
        if ($objReleaseLevelModel !== null)
        {
            $nextReleaseLevel = $objReleaseLevelModel->level + 1;
        }
        // Save to database
        if (Input::get('action') === 'releaseLevelNext' && Input::get('eventId') == $row['id'])
        {

            if (EventReleaseLevelPolicyModel::allowSwitchingEventReleaseLevel($this->User->id, $row['id'], 'up') === true && EventReleaseLevelPolicyModel::levelExists($row['id'], $nextReleaseLevel) === true)
            {
                $objEvent = CalendarEventsModel::findByPk(Input::get('eventId'));
                if ($objEvent !== null)
                {
                    $objReleaseLevelModel = EventReleaseLevelPolicyModel::findNextLevel($objEvent->eventReleaseLevel);
                    if ($objReleaseLevelModel !== null)
                    {
                        $objEvent->eventReleaseLevel = $objReleaseLevelModel->id;
                        $objEvent->save();
                        $this->saveCallbackEventReleaseLevel($objEvent->eventReleaseLevel, null, $objEvent->id);

                        // HOOK: changeEventReleaseLevel, f.ex inform tourenchef via email
                        if (isset($GLOBALS['TL_HOOKS']['changeEventReleaseLevel']) && \is_array($GLOBALS['TL_HOOKS']['changeEventReleaseLevel']))
                        {
                            foreach ($GLOBALS['TL_HOOKS']['changeEventReleaseLevel'] as $callback)
                            {
                                System::importStatic($callback[0])->{$callback[1]}($objEvent, $strDirection);
                            }
                        }

                    }
                }
            }
            $this->redirect($this->getReferer());
        }

        if (EventReleaseLevelPolicyModel::allowSwitchingEventReleaseLevel($this->User->id, $row['id'], $strDirection) === true && EventReleaseLevelPolicyModel::levelExists($row['id'], $nextReleaseLevel) === true)
        {
            $canSendToNextReleaseLevel = true;
        }

        if ($canSendToNextReleaseLevel === false)
        {
            return '';
        }

        return '<a href="' . $this->addToUrl($href . '&amp;eventId=' . $row['id']) . '" title="' . StringUtil::specialchars($title) . '"' . $attributes . '>' . Image::getHtml($icon, $label) . '</a> ';

    }


    /**
     * save_callback saveCallbackEventReleaseLevel()
     * Publish or unpublish events if eventReleaseLevel has reached the highest/last level
     * @param $newEventReleaseLevelId
     * @param DC_Table $dc
     * @param null $eventId
     * @return mixed
     */
    public function saveCallbackEventReleaseLevel($newEventReleaseLevelId, DC_Table $dc = null, $eventId = null)
    {
        $hasError = false;
        // Get event id
        if ($dc->activeRecord->id > 0)
        {
            $objEvent = CalendarEventsModel::findByPk($dc->activeRecord->id);
        }
        elseif ($eventId > 0)
        {
            $objEvent = CalendarEventsModel::findByPk($eventId);
        }
        if ($objEvent !== null)
        {
            $lastEventReleaseModel = EventReleaseLevelPolicyModel::findLastLevelByEventId($objEvent->id);
            if ($lastEventReleaseModel !== null)
            {
                // Display message in the backend if event is published or unpublished now
                if ($lastEventReleaseModel->id == $newEventReleaseLevelId)
                {
                    if (!$objEvent->published)
                    {
                        Message::addInfo(sprintf($GLOBALS['TL_LANG']['MSC']['publishedEvent'], $objEvent->id));
                    }
                    $objEvent->published = '1';

                    // HOOK: publishEvent, f.ex inform tourenchef via email
                    if (isset($GLOBALS['TL_HOOKS']['publishEvent']) && \is_array($GLOBALS['TL_HOOKS']['publishEvent']))
                    {
                        foreach ($GLOBALS['TL_HOOKS']['publishEvent'] as $callback)
                        {
                             System::importStatic($callback[0])->{$callback[1]}($objEvent);
                        }
                    }
                }
                else
                {
                    $eventReleaseModel = EventReleaseLevelPolicyModel::findByPk($newEventReleaseLevelId);
                    $firstEventReleaseModel = EventReleaseLevelPolicyModel::findFirstLevelByEventId($objEvent->id);
                    if ($eventReleaseModel !== null)
                    {
                        if ($eventReleaseModel->pid !== $firstEventReleaseModel->pid)
                        {
                            $hasError = true;
                            if ($objEvent->eventReleaseLevel > 0)
                            {
                                $newEventReleaseLevelId = $objEvent->eventReleaseLevel;
                                Message::addError(sprintf('Die Freigabestufe für Event "%s (ID: %s)" konnte nicht auf "%s" geändert werden, weil diese Freigabestufe zum Event-Typ ungültig ist. ', $objEvent->title, $objEvent->id, $eventReleaseModel->title));
                            }
                            else
                            {
                                $newEventReleaseLevelId = $firstEventReleaseModel->id;
                                Message::addError(sprintf('Die Freigabestufe für Event "%s (ID: %s)" musste auf "%s" korrigiert werden, weil eine zum Event-Typ ungültige Freigabestufe gewählt wurde. ', $objEvent->title, $objEvent->id, $firstEventReleaseModel->title));
                            }
                        }
                    }

                    if ($objEvent->published)
                    {
                        Message::addInfo(sprintf($GLOBALS['TL_LANG']['MSC']['unpublishedEvent'], $objEvent->id));
                    }
                    $objEvent->published = '';
                }
                $objEvent->save();
                if (!$hasError)
                {
                    // Display message in the backend
                    Message::addInfo(sprintf($GLOBALS['TL_LANG']['MSC']['setEventReleaseLevelTo'], $objEvent->id, EventReleaseLevelPolicyModel::findByPk($newEventReleaseLevelId)->level));
                }
            }
        }
        return $newEventReleaseLevelId;

    }


    /**
     * @param $strDuration
     * @param DC_Table|null $dc
     * @param null $eventId
     * @return string
     */
    public function onSubmitCallbackDurationInfo($strDuration, DC_Table $dc = null, $eventId = null)
    {
        // Get event id
        if ($dc->activeRecord->id > 0)
        {
            $objEvent = CalendarEventsModel::findByPk($dc->activeRecord->id);
        }
        elseif ($eventId > 0)
        {
            $objEvent = CalendarEventsModel::findByPk($eventId);
        }

        if (\Markocupic\SacEventToolBundle\CalendarEventsHelper::getEventTimestamps($objEvent->id) !== false)
        {
            $countTimestamps = count(\Markocupic\SacEventToolBundle\CalendarEventsHelper::getEventTimestamps($objEvent->id));

            $arrDuration = $GLOBALS['TL_CONFIG']['SAC-EVENT-TOOL-CONFIG']['durationInfo'][$strDuration];
            if (!empty($arrDuration) && is_array($arrDuration))
            {
                $duration = $arrDuration['dateRows'];
                if ($duration != $countTimestamps)
                {
                    \Contao\Message::addError(sprintf('Die Event-Dauer in "%s" [ID:%s] stimmt nicht mit der Anzahl Event-Daten überein.', $objEvent->title, $objEvent->id));
                    return '';
                }
            }
        }
        return $strDuration;
    }


    /**
     * @param $strEventType
     * @param DC_Table|null $dc
     * @param null $eventId
     * @return mixed
     */
    public function saveCallbackEventType($strEventType, DC_Table $dc = null, $eventId = null)
    {
        if ($strEventType !== '')
        {

            // Get event id
            if ($dc->activeRecord->id > 0)
            {
                $objEvent = CalendarEventsModel::findByPk($dc->activeRecord->id);
            }
            elseif ($eventId > 0)
            {
                $objEvent = CalendarEventsModel::findByPk($eventId);
            }
            // !important, because if eventType is not saved, then no eventReleaseLevel can be assigned
            $objEvent->eventType = $strEventType;
            $objEvent->save();

            if ($objEvent !== null)
            {
                if (EventReleaseLevelPolicyModel::findByPk($objEvent->eventReleaseLevel) === null)
                {

                    $objEventReleaseModel = EventReleaseLevelPolicyModel::findFirstLevelByEventId($objEvent->id);
                    if ($objEventReleaseModel !== null)
                    {
                        $objEvent->eventReleaseLevel = $objEventReleaseModel->id;
                        $objEvent->save();
                    }
                }
            }
        }

        return $strEventType;

    }

    /**
     * Pull event to the previous release level
     * @param $row
     * @param $href
     * @param $label
     * @param $title
     * @param $icon
     * @param $attributes
     * @return string
     */
    public function releaseLevelPrev($row, $href, $label, $title, $icon, $attributes)
    {
        $strDirection = 'down';

        $canSendToNextReleaseLevel = false;
        $prevReleaseLevel = null;
        $objReleaseLevelModel = EventReleaseLevelPolicyModel::findByPk($row['eventReleaseLevel']);
        if ($objReleaseLevelModel !== null)
        {
            $prevReleaseLevel = $objReleaseLevelModel->level - 1;
        }

        // Save to database
        if (Input::get('action') === 'releaseLevelPrev' && Input::get('eventId') == $row['id'])
        {

            if (EventReleaseLevelPolicyModel::allowSwitchingEventReleaseLevel($this->User->id, $row['id'], 'down') === true && EventReleaseLevelPolicyModel::levelExists($row['id'], $prevReleaseLevel) === true)
            {
                $objEvent = CalendarEventsModel::findByPk(Input::get('eventId'));
                if ($objEvent !== null)
                {
                    $objReleaseLevelModel = EventReleaseLevelPolicyModel::findPrevLevel($objEvent->eventReleaseLevel);
                    if ($objReleaseLevelModel !== null)
                    {
                        $objEvent->eventReleaseLevel = $objReleaseLevelModel->id;
                        $objEvent->save();
                        $this->saveCallbackEventReleaseLevel($objEvent->eventReleaseLevel, null, $objEvent->id);

                        // HOOK: changeEventReleaseLevel, f.ex inform tourenchef via email
                        if (isset($GLOBALS['TL_HOOKS']['changeEventReleaseLevel']) && \is_array($GLOBALS['TL_HOOKS']['changeEventReleaseLevel']))
                        {
                            foreach ($GLOBALS['TL_HOOKS']['changeEventReleaseLevel'] as $callback)
                            {
                                System::importStatic($callback[0])->{$callback[1]}($objEvent, $strDirection);
                            }
                        }
                    }
                }
            }
            $this->redirect($this->getReferer());
        }

        if (EventReleaseLevelPolicyModel::allowSwitchingEventReleaseLevel($this->User->id, $row['id'], $strDirection) === true && EventReleaseLevelPolicyModel::levelExists($row['id'], $prevReleaseLevel) === true)
        {
            $canSendToNextReleaseLevel = true;
        }

        if ($canSendToNextReleaseLevel === false)
        {
            return '';
        }

        return '<a href="' . $this->addToUrl($href . '&amp;eventId=' . $row['id']) . '" title="' . StringUtil::specialchars($title) . '"' . $attributes . '>' . Image::getHtml($icon, $label) . '</a> ';

    }

    /**
     * Return the delete icon
     *
     * @param array $row
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

        $blnAllow = \Contao\EventReleaseLevelPolicyModel::canDeleteEvent($this->User->id, $row['id']);
        if (!$blnAllow)
        {
            return '';
        }
        return '<a href="' . $this->addToUrl($href . '&amp;id=' . $row['id']) . '" title="' . StringUtil::specialchars($title) . '"' . $attributes . '>' . Image::getHtml($icon, $label) . '</a> ';
    }

    /**
     * @param $row
     * @param $href
     * @param $label
     * @param $title
     * @param $icon
     * @param $attributes
     * @return string
     */
    public function copyIcon($row, $href, $label, $title, $icon, $attributes)
    {

        $blnAllow = \Contao\EventReleaseLevelPolicyModel::hasWritePermission($this->User->id, $row['id']);

        if (!$blnAllow)
        {
            return '';
        }
        return '<a href="' . $this->addToUrl($href . '&amp;id=' . $row['id']) . '" title="' . StringUtil::specialchars($title) . '"' . $attributes . '>' . Image::getHtml($icon, $label) . '</a> ';
    }

}
