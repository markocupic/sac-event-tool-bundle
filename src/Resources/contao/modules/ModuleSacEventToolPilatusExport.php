<?php

/**
 * SAC Event Tool Web Plugin for Contao
 * Copyright (c) 2008-2019 Marko Cupic
 * @package sac-event-tool-bundle
 * @author Marko Cupic m.cupic@gmx.ch, 2017-2019
 * @link https://github.com/markocupic/sac-event-tool-bundle
 */

namespace Markocupic\SacEventToolBundle;

use Contao\BackendTemplate;
use Contao\Calendar;
use Contao\CalendarEventsJourneyModel;
use Contao\CalendarEventsModel;
use Contao\Config;
use Contao\Controller;
use Contao\Database;
use Contao\Date;
use Contao\Environment;
use Contao\FrontendTemplate;
use Contao\Input;
use Contao\StringUtil;
use Contao\Validator;
use Haste\Form\Form;
use Patchwork\Utf8;

/**
 * Class ModuleSacEventToolPilatusExport
 * @package Markocupic\SacEventToolBundle
 */
class ModuleSacEventToolPilatusExport extends ModuleSacEventToolPrintExport
{

    /**
     * Template
     * @var string
     */
    protected $strTemplate = 'mod_sac_event_tool_event_pilatus_export';

    /**
     * @var
     */
    protected $objForm;

    /**
     * @var
     */
    protected $startDate;

    /**
     * @var
     */
    protected $endDate;

    /**
     * @var
     */
    protected $eventReleaseLevel;

    /**
     * @var
     */
    protected $dateFormat = 'j.';

    /**
     * @var null
     */
    protected $allEventsTable = null;

    /**
     * @var null
     */
    protected $courses = null;

    /**
     * @var null
     */
    protected $tours = null;

    /**
     * @var null
     */
    protected $generalEvents = null;

    /**
     * Display a wildcard in the back end
     *
     * @return string
     */
    public function generate()
    {
        if (TL_MODE == 'BE')
        {
            /** @var BackendTemplate|object $objTemplate */
            $objTemplate = new BackendTemplate('be_wildcard');

            $objTemplate->wildcard = '### ' . Utf8::strtoupper($GLOBALS['TL_LANG']['FMD']['eventToolEventToolPilatusExport'][0]) . ' ###';
            $objTemplate->title = $this->headline;
            $objTemplate->id = $this->id;
            $objTemplate->link = $this->name;
            $objTemplate->href = 'contao/main.php?do=themes&amp;table=tl_module&amp;act=edit&amp;id=' . $this->id;

            return $objTemplate->parse();
        }


        if (Input::post('FORM_SUBMIT') === 'edit-event')
        {
            $set = array();
            foreach (explode(';', Input::post('submitted_fields')) as $field)
            {
                $set[$field] = Input::post($field);
            }
            $objUpdateStmt = Database::getInstance()->prepare('UPDATE tl_calendar_events %s WHERE id=?')->set($set)->execute(Input::post('id'));
            if ($objUpdateStmt->affectedRows)
            {
                $arrReturn = array('status' => 'success', 'message' => 'Saved changes successfully to the Database.');
            }
            else
            {
                $arrReturn = array('status' => 'error', 'message' => 'Error during the upload process.');
            }

            die(\json_encode($arrReturn));
        }

        Controller::loadLanguageFile('tl_calendar_events');


        return parent::generate();
    }


    /**
     * Generate the module
     */
    protected function compile()
    {

        // Load language file
        Controller::loadLanguageFile('tl_calendar_events');

        $this->generateForm();

        $this->Template->form = $this->objForm;

        // Course table
        $objPartial = new FrontendTemplate('mod_sac_event_tool_event_pilatus_export_all_event_table_partial');
        $objPartial->eventTable = $this->courseTable;
        $this->Template->courseTable = $objPartial->parse();
        $this->Template->courses = $this->courses;

        // Tour table
        $objPartial = new FrontendTemplate('mod_sac_event_tool_event_pilatus_export_all_event_table_partial');
        $objPartial->eventTable = $this->tourTable;
        $this->Template->tourTable = $objPartial->parse();
        $this->Template->tours = $this->tours;
        $this->Template->generalEvents = $this->generalEvents;
    }


    /**
     * @return Form
     */
    protected function generateForm()
    {

        $objForm = new Form('form-pilatus-export', 'POST', function ($objHaste) {
            return Input::post('FORM_SUBMIT') === $objHaste->getFormId();
        });
        $objForm->setFormActionFromUri(Environment::get('uri'));


        $range = array();
        $range[0] = '---';

        $now = Date::parse('n');
        $start = $now % 2 > 0 ? -7 : -6;

        for ($i = $start; $i < $start + 16; $i += 2)
        {
            // echo Date::parse('Y-m-d',strtotime(Date::parse("Y-m-1", strtotime($i . " month"))));
            //echo "<br>";
            $key = Date::parse("Y-m-01", strtotime($i . " month")) . '|' . Date::parse("Y-m-t", strtotime($i + 1 . "  month"));
            $range[$key] = Date::parse("01.m.Y", strtotime($i . " month")) . '-' . Date::parse("t.m.Y", strtotime($i + 1 . "  month"));
        }


        // Now let's add form fields:
        $objForm->addFormField('timeRange', array(
            'label'     => 'Zeitspanne (fixe Zeitspanne)',
            'inputType' => 'select',
            'options'   => $range,
            //'default'   => $this->User->emergencyPhone,
            'eval'      => array('mandatory' => false),
        ));

        // Now let's add form fields:
        $objForm->addFormField('timeRangeStart', array(
            'label'     => array('Zeitspanne manuelle Eingabe (Startdatum)', 'sdff'),
            'inputType' => 'text',
            'eval'      => array('mandatory' => false, 'maxlength' => '10', 'minlength' => 8, 'placeholder' => 'dd.mm.YYYY'),
            'value'     => Input::post('timeRangeStart')
        ));

        // Now let's add form fields:
        $objForm->addFormField('timeRangeEnd', array(
            'label'     => 'Zeitspanne manuelle Eingabe (Enddatum)',
            'inputType' => 'text',
            'eval'      => array('mandatory' => false, 'maxlength' => '10', 'minlength' => 8, 'placeholder' => 'dd.mm.YYYY'),
            'value'     => Input::post('timeRangeEnd')
        ));

        $objForm->addFormField('eventReleaseLevel', array(
            'label'     => 'Zeige an ab Freigabestufe (Wenn leer gelassen, wird ab 2. höchster FS gelistet!)',
            'inputType' => 'select',
            'options'   => array(1 => 'FS1', 2 => 'FS2', 3 => 'FS3', 4 => 'FS4'),
            'eval'      => array('mandatory' => false, 'includeBlankOption' => true),
        ));


        // Let's add  a submit button
        $objForm->addFormField('submit', array(
            'label'     => 'Export starten',
            'inputType' => 'submit',
        ));

        // validate() also checks whether the form has been submitted
        if ($objForm->validate())
        {
            if (Input::post('timeRange') != 0)
            {
                $arrRange = explode('|', Input::post('timeRange'));
                $this->startDate = strtotime($arrRange[0]);
                $this->endDate = strtotime($arrRange[1]);
                $objForm->getWidget('timeRangeStart')->value = '';
                $objForm->getWidget('timeRangeEnd')->value = '';
            }

            // Alternatively you can add the date manualy
            elseif (Input::post('timeRangeStart') != '' && Input::post('timeRangeEnd') != '')
            {
                if (strtotime(Input::post('timeRangeStart')) > 0 && strtotime(Input::post('timeRangeStart')) > 0)
                {
                    $objWidgetStart = $objForm->getWidget('timeRangeStart');
                    $objWidgetEnd = $objForm->getWidget('timeRangeEnd');

                    $intStart = strtotime(Input::post('timeRangeStart'));
                    $intEnd = strtotime(Input::post('timeRangeEnd'));

                    if ($intStart > $intEnd || !Validator::isDate($objWidgetStart->value) || !Validator::isDate($objWidgetEnd->value))
                    {
                        $strError = 'Ungültige Datumseingabe. Gib das Datum im Format \'dd.mm.YYYY\' ein. Das Startdatum muss kleiner sein als das Enddatum.';
                        $objForm->getWidget('timeRangeStart')->addError($strError);
                        $objForm->getWidget('timeRangeEnd')->addError($strError);
                    }
                    else
                    {
                        $this->startDate = $intStart;
                        $this->endDate = $intEnd;
                        Input::setPost('timeRange', '');
                        $objForm->getWidget('timeRange')->value = '';
                    }
                }
            }

            if ($this->startDate && $this->endDate)
            {
                $this->eventReleaseLevel = Input::post('eventReleaseLevel') > 0 ? Input::post('eventReleaseLevel') : null;
                $this->courseTable = $this->generateEventTable(['course']);
                $this->tourTable = $this->generateEventTable(['tour', 'generalEvent']);

                $this->generateCourses();
                $this->generateEvents('tour');
                $this->generateEvents('generalEvent');
            }
        }

        $this->objForm = $objForm;
    }

    /**
     * @param $arrAllowedEventType
     * @return array|null
     * @throws \Exception
     */
    protected function generateEventTable($arrAllowedEventType)
    {
        $objDatabase = Database::getInstance();
        $arrTours = array();

        $oEvent = $objDatabase->prepare('SELECT * FROM tl_calendar_events WHERE startDate>=? AND startDate<=? ORDER BY startDate ASC')->execute($this->startDate, $this->endDate);
        while ($oEvent->next())
        {

            if(!in_array($oEvent->eventType, $arrAllowedEventType))
            {
                continue;
            }

            $objEvent = CalendarEventsModel::findByPk($oEvent->id);
            if (null === $objEvent)
            {
                continue;
            }

            // Check if event has allowed type
            $arrAllowedEventTypes = StringUtil::deserialize($this->print_export_allowedEventTypes, true);
            if (!in_array($objEvent->eventType, $arrAllowedEventTypes))
            {
                continue;
            }

            // Check if event is at least on second highest level (Level 3/4)
            if (!$this->hasValidReleaseLevel($objEvent, $this->eventReleaseLevel))
            {
                continue;
            }

            $arrRow = $objEvent->row();
            $arrRow['week'] = Date::parse('W', $objEvent->startDate) . ', ' . Date::parse('j.', $this->getFirstDayOfWeekTimestamp($objEvent->startDate)) . '-' . Date::parse('j. F', $this->getLastDayOfWeekTimestamp($objEvent->startDate));
            $arrRow['eventDates'] = $this->getEventPeriod($objEvent->id, 'd.');
            $arrRow['weekday'] = $this->getEventPeriod($objEvent->id, 'D');
            $arrRow['title'] = $objEvent->title . ($objEvent->eventType === 'lastMinuteTour' ? ' (LAST MINUTE TOUR!)' : '');
            $arrRow['instructors'] = implode(', ', CalendarEventsHelper::getInstructorNamesAsArray($objEvent->id, false, false));
            $arrRow['organizers'] = implode(', ', CalendarEventsHelper::getEventOrganizersAsArray($objEvent->id, 'titlePrint'));

            // tourType
            $arrEventType = CalendarEventsHelper::getTourTypesAsArray($objEvent->id, 'shortcut', false);
            if ($objEvent->eventType === 'course')
            {
                // KU = Kurs
                $arrEventType[] = 'KU';
            }
            $arrRow['tourType'] = implode(', ', $arrEventType);


            // Add row to $arrTour
            $arrTours[] = $arrRow;
        }

        return count($arrTours) > 0 ? $arrTours : null;
    }

    /**
     * Helper method
     * @param $timestamp
     * @return int
     */
    private function getFirstDayOfWeekTimestamp($timestamp)
    {
        $date = Date::parse('d-m-Y', $timestamp);
        $day = \DateTime::createFromFormat('d-m-Y', $date);
        $day->setISODate((int)$day->format('o'), (int)$day->format('W'), 1);
        return $day->getTimestamp();
    }

    /**
     * Helper method
     * @param $timestamp
     * @return int
     */
    private function getLastDayOfWeekTimestamp($timestamp)
    {
        return $this->getFirstDayOfWeekTimestamp($timestamp) + 6 * 24 * 3600;
    }

    /**
     * Helper method
     * @param $id
     * @param string $dateFormat
     * @return string
     * @throws \Exception
     */
    private function getEventPeriod($id, $dateFormat = '')
    {
        if ($dateFormat == '')
        {
            $dateFormat = Config::get('dateFormat');
        }

        $dateFormatShortened = array();


        if ($dateFormat === 'd.')
        {
            $dateFormatShortened['from'] = 'd.';
            $dateFormatShortened['to'] = 'd.';
        }

        elseif ($dateFormat === 'j.m.')
        {
            $dateFormatShortened['from'] = 'j.';
            $dateFormatShortened['to'] = 'j.m.';
        }

        elseif ($dateFormat === 'j.-j. F')
        {
            $dateFormatShortened['from'] = 'j.';
            $dateFormatShortened['to'] = 'j. F';
        }
        elseif ($dateFormat === 'D')
        {
            $dateFormatShortened['from'] = 'D';
            $dateFormatShortened['to'] = 'D';
        }
        else
        {
            $dateFormatShortened['from'] = 'j.';
            $dateFormatShortened['to'] = 'j.m.';
        }


        $eventDuration = count(CalendarEventsHelper::getEventTimestamps($id));
        $span = Calendar::calculateSpan(CalendarEventsHelper::getStartDate($id), CalendarEventsHelper::getEndDate($id)) + 1;

        if ($eventDuration == 1)
        {
            return Date::parse($dateFormatShortened['to'], CalendarEventsHelper::getStartDate($id));
        }
        if ($eventDuration == 2 && $span != $eventDuration)
        {
            return Date::parse($dateFormatShortened['from'], CalendarEventsHelper::getStartDate($id)) . ' & ' . Date::parse($dateFormatShortened['to'], CalendarEventsHelper::getEndDate($id));
        }
        elseif ($span == $eventDuration)
        {
            return Date::parse($dateFormatShortened['from'], CalendarEventsHelper::getStartDate($id)) . '-' . Date::parse($dateFormatShortened['to'], CalendarEventsHelper::getEndDate($id));
        }
        else
        {
            $arrDates = array();
            $dates = CalendarEventsHelper::getEventTimestamps($id);
            foreach ($dates as $date)
            {
                $arrDates[] = Date::parse($dateFormatShortened['to'], $date);
            }

            return implode(', ', $arrDates);
        }
    }

    /**
     * Generate courses
     * @throws \Exception
     */
    protected function generateCourses()
    {
        $objDatabase = Database::getInstance();
        $arrEvents = array();

        $objEvent = $objDatabase->prepare('SELECT * FROM tl_calendar_events WHERE eventType=? AND startDate>=? AND startDate<=? ORDER BY courseTypeLevel0, courseId, courseTypeLevel1, startDate ASC')->execute('course', $this->startDate, $this->endDate);
        while ($objEvent->next())
        {
            $eventModel = CalendarEventsModel::findByPk($objEvent->id);
            if (null === $eventModel)
            {
                continue;
            }

            // Check if event has allowed type
            $arrAllowedEventTypes = StringUtil::deserialize($this->print_export_allowedEventTypes, true);
            if (!in_array($eventModel->eventType, $arrAllowedEventTypes))
            {
                continue;
            }

            // Check if event is on an enough high level
            if (!$this->hasValidReleaseLevel($eventModel, $this->eventReleaseLevel))
            {
                continue;
            }

            // Call helper method
            $arrRow = $this->getEventDetails($eventModel);

            // Headline
            $arrHeadline = array();
            $arrHeadline[] = $this->getEventPeriod($eventModel->id, 'j.-j. F');
            $arrHeadline[] = $this->getEventPeriod($eventModel->id, 'D');
            $arrHeadline[] = $eventModel->title;
            if (isset($GLOBALS['TL_CONFIG']['SAC-EVENT-TOOL-CONFIG']['courseLevel'][$eventModel->courseLevel]))
            {
                $arrHeadline[] = 'Kursstufe ' . $GLOBALS['TL_CONFIG']['SAC-EVENT-TOOL-CONFIG']['courseLevel'][$eventModel->courseLevel];
            }
            if ($eventModel->courseId != '')
            {
                $arrHeadline[] = 'Kurs-Nr. ' . $eventModel->courseId;
            }
            $arrRow['headline'] = implode(' > ', $arrHeadline);

            // Fe-editable textareas
            $arrRow['feEditables'] = array('teaser', 'issues', 'terms', 'requirements', 'equipment', 'leistungen', 'bookingEvent', 'meetingPoint', 'miscellaneous',);

            // Add row to $arrTour
            $arrEvents[] = $arrRow;

        }
        $this->courses = count($arrEvents) > 0 ? $arrEvents : null;
    }

    /**
     * Generate tours and generalEvents
     * @param $type
     * @throws \Exception
     */
    function generateEvents($type)
    {
        $objDatabase = Database::getInstance();
        $arrOrganizerContainer = array();

        $objOrganizer = Database::getInstance()->prepare('SELECT * FROM tl_event_organizer ORDER BY sorting')->execute();
        while ($objOrganizer->next())
        {
            $arrOrganizerEvents = array();

            $objEvent = $objDatabase->prepare('SELECT * FROM tl_calendar_events WHERE (eventType=?) AND startDate>=? AND startDate<=? ORDER BY startDate ASC')->execute($type, $this->startDate, $this->endDate);
            while ($objEvent->next())
            {

                $eventModel = CalendarEventsModel::findByPk($objEvent->id);
                if (null === $eventModel)
                {
                    continue;
                }

                $arrOrganizers = StringUtil::deserialize($eventModel->organizers, true);
                if (!in_array($objOrganizer->id, $arrOrganizers))
                {
                    continue;
                }

                // Check if event has allowed type
                $arrAllowedEventTypes = StringUtil::deserialize($this->print_export_allowedEventTypes, true);
                if (!in_array($eventModel->eventType, $arrAllowedEventTypes))
                {
                    continue;
                }

                // Check if event is at least on second highest level (Level 3/4)
                if (!$this->hasValidReleaseLevel($eventModel, $this->eventReleaseLevel))
                {
                    continue;
                }

                // Call helper method
                $arrRow = $this->getEventDetails($eventModel);

                // Headline
                $arrHeadline = array();
                $arrHeadline[] = $this->getEventPeriod($eventModel->id, 'j.-j. F');
                $arrHeadline[] = $this->getEventPeriod($eventModel->id, 'D');
                $arrHeadline[] = $eventModel->title;
                $strDifficulties = implode(', ', CalendarEventsHelper::getTourTechDifficultiesAsArray($eventModel->id));
                if ($strDifficulties != '')
                {
                    $arrHeadline[] = $strDifficulties;
                }
                $arrRow['headline'] = implode(' > ', $arrHeadline);

                // Fe-editable textareas
                $arrFeEditables = array('teaser', 'tourDetailText', 'requirements', 'equipment', 'leistungen', 'bookingEvent', 'meetingPoint', 'miscellaneous',);

                // Add row to $arrOrganizerEvents
                $arrOrganizerEvents[] = $arrRow;

            }

            $arrOrganizerContainer[] = array(
                'id'          => $objOrganizer->id,
                'title'       => $objOrganizer->title,
                'events'      => $arrOrganizerEvents,
                'feEditables' => $arrFeEditables
            );

        }

        $this->{$type . 's'} = $arrOrganizerContainer;

    }

    /**
     * Helper method
     * @param $objEvent
     * @return mixed
     * @throws \Exception
     */
    private function getEventDetails($objEvent)
    {
        $arrRow = $objEvent->row();
        $arrRow['eventState'] = $objEvent->eventState != '' ? $GLOBALS['TL_LANG']['tl_calendar_events'][$objEvent->eventState][0] : '';
        $arrRow['week'] = Date::parse('W', $objEvent->startDate);
        $arrRow['eventDates'] = $this->getEventPeriod($objEvent->id, $this->dateFormat);
        $arrRow['weekday'] = $this->getEventPeriod($objEvent->id, 'D');
        $arrRow['instructors'] = implode(', ', CalendarEventsHelper::getInstructorNamesAsArray($objEvent->id, false, false));
        $arrRow['organizers'] = implode(', ', CalendarEventsHelper::getEventOrganizersAsArray($objEvent->id, 'title'));
        $arrRow['tourProfile'] = implode('<br>', CalendarEventsHelper::getTourProfileAsArray($objEvent->id));
        $arrRow['journey'] = CalendarEventsJourneyModel::findByPk($objEvent->journey) !== null ? CalendarEventsJourneyModel::findByPk($objEvent->journey)->title : '';


        // Textareas
        $arrTextareas = array('teaser', 'terms', 'issues', 'tourDetailText', 'requirements', 'equipment', 'leistungen', 'bookingEvent', 'meetingPoint', 'miscellaneous',);
        foreach ($arrTextareas as $field)
        {
            $arrRow[$field] = nl2br($objEvent->{$field});
            $arrRow[$field] = $this->searchAndReplace($arrRow[$field]);
            $arrFeEditables[] = $field;
        }


        if ($objEvent->setRegistrationPeriod)
        {
            $arrRow['registrationPeriod'] = Date::parse('j.m.Y H:i', $objEvent->registrationStartDate) . ' bis ' . Date::parse('j.m.Y H:i', $objEvent->registrationEndDate);
        }

        // MinMaxMembers
        $arrMinMaxMembers = array();
        if ($objEvent->addMinAndMaxMembers && $objEvent->minMembers > 0)
        {
            $arrMinMaxMembers[] = 'min. ' . $objEvent->minMembers;
        }
        if ($objEvent->addMinAndMaxMembers && $objEvent->maxMembers > 0)
        {
            $arrMinMaxMembers[] = 'max. ' . $objEvent->maxMembers;
        }
        $arrRow['minMaxMembers'] = implode('/', $arrMinMaxMembers);


        return $arrRow;
    }

}
