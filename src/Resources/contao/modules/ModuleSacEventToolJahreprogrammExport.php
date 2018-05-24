<?php

/**
 * SAC Event Tool Web Plugin for Contao
 * Copyright (c) 2008-2017 Marko Cupic
 * @package sac-event-tool-bundle
 * @author Marko Cupic m.cupic@gmx.ch, 2017-2018
 * @link https://sac-kurse.kletterkader.com
 */

namespace Markocupic\SacEventToolBundle;

use Contao\BackendTemplate;
use Contao\Calendar;
use Contao\CalendarEventsJourneyModel;
use Contao\Controller;
use Contao\CourseMainTypeModel;
use Contao\CourseSubTypeModel;
use Contao\Database;
use Contao\Date;
use Contao\Environment;
use Contao\EventOrganizerModel;
use Contao\Input;
use Contao\Module;
use Contao\StringUtil;
use Contao\UserRoleModel;
use Haste\Form\Form;
use Patchwork\Utf8;


/**
 * Class ModuleSacEventToolJahresprogrammExport
 * @package Markocupic\SacEventToolBundle
 */
class ModuleSacEventToolJahresprogrammExport extends Module
{

    /**
     * Template
     * @var string
     */
    protected $strTemplate = 'mod_sac_event_tool_event_jahresprogramm_export';

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
    protected $organizer;

    /**
     * @var null
     */
    protected $eventType;

    /**
     * @var null
     */
    protected $events = null;

    /**
     * @var null
     */
    protected $instructors = null;

    /**
     * @var null
     */
    protected $specialUsers = null;

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

            $objTemplate->wildcard = '### ' . Utf8::strtoupper($GLOBALS['TL_LANG']['FMD']['eventToolEventToolJahresprogrammExport'][0]) . ' ###';
            $objTemplate->title = $this->headline;
            $objTemplate->id = $this->id;
            $objTemplate->link = $this->name;
            $objTemplate->href = 'contao/main.php?do=themes&amp;table=tl_module&amp;act=edit&amp;id=' . $this->id;

            return $objTemplate->parse();
        }


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


    }


    /**
     * @return Form
     */
    protected function generateForm()
    {

        $objForm = new Form('form-jahresprogramm-export', 'POST', function ($objHaste) {
            return Input::post('FORM_SUBMIT') === $objHaste->getFormId();
        });

        $objForm->setFormActionFromUri(Environment::get('uri'));


        // Now let's add form fields:
        $objForm->addFormField('eventType', array(
            'label'     => 'Event-Typ',
            'reference' => $GLOBALS['TL_LANG']['MSC'],
            'inputType' => 'select',
            'options'   => $GLOBALS['TL_CONFIG']['SAC-EVENT-TOOL-CONFIG']['EVENT-TYPE'],
            //'default'   => $this->User->emergencyPhone,
            'eval'      => array('inkludeBlankOption' => true, 'mandatory' => true),
        ));


        $arrOrganizers = array();
        $objOrganizer = Database::getInstance()->prepare('SELECT * FROM tl_event_organizer ORDER BY sorting')->execute();
        while ($objOrganizer->next())
        {
            $arrOrganizers[$objOrganizer->id] = $objOrganizer->title;
        }
        $objForm->addFormField('organizer', array(
            'label'     => 'Organisierende Gruppe',
            'inputType' => 'select',
            'options'   => $arrOrganizers,
            'eval'      => array('mandatory' => true),
        ));

        $objForm->addFormField('startDate', array(
            'label'     => 'Startdatum',
            'inputType' => 'text',
            'options'   => $arrOrganizers,
            'eval'      => array('rgxp' => 'date', 'mandatory' => true),
        ));

        $objForm->addFormField('endDate', array(
            'label'     => 'Enddatum',
            'inputType' => 'text',
            'options'   => $arrOrganizers,
            'eval'      => array('rgxp' => 'date', 'mandatory' => true),
        ));


        $arrUserRoles = array();
        $objUserRoles = Database::getInstance()->prepare('SELECT * FROM tl_user_role ORDER BY title')->execute();
        while ($objUserRoles->next())
        {
            $arrUserRoles[$objUserRoles->id] = $objUserRoles->title;
        }
        $objForm->addFormField('userRoles', array(
            'label'     => 'Neben den Event-Leitern zus&auml;tliche Funktion&auml;re anzeigen',
            'inputType' => 'select',
            'options'   => $arrUserRoles,
            'eval'      => array('multiple' => true, 'mandatory' => false),
        ));


        // Let's add  a submit button
        $objForm->addFormField('submit', array(
            'label'     => 'Export starten',
            'inputType' => 'submit',
        ));

        // validate() also checks whether the form has been submitted
        if ($objForm->validate())
        {
            if (Input::post('startDate') != '' && Input::post('endDate') != '' && Input::post('organizer') > 0 && Input::post('eventType') != '')
            {

                $this->startDate = strtotime(Input::post('startDate'));
                $this->endDate = strtotime(Input::post('endDate'));
                $this->eventType = Input::post('eventType');
                $this->organizer = Input::post('organizer');

                $this->getEvents();

                $this->Template->eventType = $this->eventType;
                $this->Template->eventTypeLabel = $GLOBALS['TL_LANG']['MSC'][$this->eventType];
                $this->Template->startDate = $this->startDate;
                $this->Template->endDate = $this->endDate;
                $this->Template->organizer = EventOrganizerModel::findByPk($this->organizer)->title;
                $this->Template->events = $this->events;
                $this->Template->instructors = $this->instructors;
                $this->Template->specialUsers = $this->specialUsers;
            }
        }
        $this->objForm = $objForm;

    }


    protected function getEvents()
    {
        $arrEvents = array();
        $objEvents = Database::getInstance()->prepare('SELECT * FROM tl_calendar_events WHERE published=? AND startDate>? AND startDate<?')->execute('1', $this->startDate, $this->endDate);
        while ($objEvents->next())
        {

            $arrOrganizer = StringUtil::deserialize($objEvents->organizers, true);
            if (!in_array($this->organizer, $arrOrganizer))
            {
                continue;
            }
            if ($this->eventType !== $objEvents->eventType)
            {
                continue;
            }
            $arrEvents[] = intval($objEvents->id);
        }

        $arrInstructors = array();
        if (count($arrEvents > 0))
        {
            $arrEvent = array();

            // We se different queries for each event type
            if ($this->eventType === 'course')
            {
                $objEvent = Database::getInstance()->execute('SELECT * FROM tl_calendar_events WHERE id IN (' . implode(',', array_map('\intval', $arrEvents)) . ') ORDER BY courseTypeLevel0, courseTypeLevel1, startDate');
            }
            else
            {
                $objEvent = Database::getInstance()->execute('SELECT * FROM tl_calendar_events WHERE id IN (' . implode(',', array_map('\intval', $arrEvents)) . ') ORDER BY startDate');
            }
            while ($objEvent->next())
            {
                $arrInstructors = array_merge($arrInstructors, CalendarSacEvents::getInstructorsAsArray($objEvent->id));

                // tourType
                $arrTourType = CalendarSacEvents::getTourTypesAsArray($objEvent->id, 'shortcut', false);
                if ($objEvent->eventType === 'course')
                {
                    // KU = Kurs
                    $arrTourType[] = 'KU';
                }
                $arrEvent[] = array(
                    'id'               => $objEvent->id,
                    'eventType'        => $objEvent->eventType,
                    'courseId'         => $objEvent->courseId,
                    'organizers'       => implode(', ', CalendarSacEvents::getEventOrganizersAsArray($objEvent->id)),
                    'courseLevel'      => isset($GLOBALS['TL_CONFIG']['SAC-EVENT-TOOL-CONFIG']['courseLevel'][$objEvent->courseLevel]) ? $GLOBALS['TL_CONFIG']['SAC-EVENT-TOOL-CONFIG']['courseLevel'][$objEvent->courseLevel] : '',
                    'courseTypeLevel0' => (CourseMainTypeModel::findByPk($objEvent->courseTypeLevel0) !== null) ? CourseMainTypeModel::findByPk($objEvent->courseTypeLevel0)->name : '',
                    'courseTypeLevel1' => (CourseSubTypeModel::findByPk($objEvent->courseTypeLevel1) !== null) ? CourseSubTypeModel::findByPk($objEvent->courseTypeLevel1)->name : '',
                    'title'            => $objEvent->title,
                    'date'             => $this->getEventPeriod($objEvent->id, 'm.d'),
                    'month'            => Date::parse('F', $objEvent->startDate),
                    'durationInfo'     => $objEvent->durationInfo,
                    'instructors'      => implode(', ', CalendarSacEvents::getInstructorNamesAsArray($objEvent->id)),
                    'tourType'         => implode(', ', $arrTourType),
                    'difficulty'       => implode(',', CalendarSacEvents::getTourTechDifficultiesAsArray($objEvent->id)),
                );
            }
            $this->events = $arrEvent;

            $specialUsers = array();
            if (Input::post(userRoles))
            {
                $arrUserRoles = Input::post('userRoles');
                foreach ($arrUserRoles as $userRole)
                {
                    $arrUsers = array();
                    $objUser = Database::getInstance()->execute('SELECT * FROM tl_user');
                    while ($objUser->next())
                    {
                        $userRoles = StringUtil::deserialize($objUser->userRole, true);
                        if (in_array($userRole, $userRoles))
                        {

                            $arrLeft = array();
                            $arrLeft[] = $objUser->name;
                            $arrLeft[] = $objUser->street;
                            $arrLeft[] = $objUser->postal;
                            $arrLeft[] = $objUser->city;
                            $arrLeft = array_filter($arrLeft);


                            $arrRight = array();
                            if ($objUser->phone != '')
                            {
                                $arrRight[] = 'P ' . $objUser->phone;
                            }
                            if ($objUser->mobile != '')
                            {
                                $arrRight[] = 'N ' . $objUser->mobile;
                            }
                            if ($objUser->email != '')
                            {
                                $arrRight[] = $objUser->email;
                            }

                            $arrUsers[] = array(
                                'id'       => $objUser->id,
                                'leftCol'  => implode(', ', $arrLeft),
                                'rightCol' => implode(', ', $arrRight),
                            );
                        }
                    }

                    $specialUsers[] = array(

                        'title' => UserRoleModel::findByPk($userRole)->title,
                        'users' => $arrUsers,
                    );
                }
            }
            $this->specialUsers = $specialUsers;

            $arrInstructors = array_unique($arrInstructors);
            $aInstructors = array();
            $objUser = Database::getInstance()->execute('SELECT * FROM tl_user WHERE id IN (' . implode(',', array_map('\intval', $arrInstructors)) . ') ORDER BY name');
            while ($objUser->next())
            {
                $arrLeft = array();
                $arrLeft[] = $objUser->name;
                $arrLeft[] = $objUser->street;
                $arrLeft[] = $objUser->postal;
                $arrLeft[] = $objUser->city;
                $arrLeft = array_filter($arrLeft);


                $arrRight = array();
                if ($objUser->phone != '')
                {
                    $arrRight[] = 'P ' . $objUser->phone;
                }
                if ($objUser->mobile != '')
                {
                    $arrRight[] = 'N ' . $objUser->mobile;
                }
                if ($objUser->email != '')
                {
                    $arrRight[] = $objUser->email;
                }

                $aInstructors[] = array(
                    'id'       => $objUser->id,
                    'leftCol'  => implode(', ', $arrLeft),
                    'rightCol' => implode(', ', $arrRight),
                );
            }
            $arrInstructors = $aInstructors;
            $this->instructors = $arrInstructors;

        }


    }

    /**
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

        $dateFormatShortened = $dateFormat;
        if ($dateFormat === 'd.m.Y' || $dateFormat === 'd.m.')
        {
            $dateFormatShortened = 'd.m.';
        }


        $eventDuration = count(CalendarSacEvents::getEventTimestamps($id));
        $span = Calendar::calculateSpan(CalendarSacEvents::getStartDate($id), CalendarSacEvents::getEndDate($id)) + 1;

        if ($eventDuration == 1)
        {
            return Date::parse($dateFormat, CalendarSacEvents::getStartDate($id));
        }
        if ($eventDuration == 2 && $span != $eventDuration)
        {
            return Date::parse($dateFormatShortened, CalendarSacEvents::getStartDate($id)) . ' & ' . Date::parse($dateFormat, CalendarSacEvents::getEndDate($id));
        }
        elseif ($span == $eventDuration)
        {
            return Date::parse($dateFormatShortened, CalendarSacEvents::getStartDate($id)) . '-' . Date::parse($dateFormat, CalendarSacEvents::getEndDate($id));
        }
        else
        {
            $arrDates = array();
            $dates = CalendarSacEvents::getEventTimestamps($id);
            foreach ($dates as $date)
            {
                $arrDates[] = Date::parse($dateFormat, $date);
            }

            return implode('; ', $arrDates);
        }
    }

    /**
     *
     */
    protected function generateAllEventsTable()
    {
        $objDatabase = Database::getInstance();
        $arrTours = array();

        //$objTour = $objDatabase->prepare('SELECT * FROM tl_calendar_events WHERE published=? AND (eventType=? OR eventType=?) AND startDate>=? AND endDate<=? ORDER BY startDate ASC')->execute('1', 'tour', 'generalEvent', $this->startDate, $this->endDate);
        $objTour = $objDatabase->prepare('SELECT * FROM tl_calendar_events WHERE published=? AND startDate>=? AND endDate<=? ORDER BY startDate ASC')->execute('1', $this->startDate, $this->endDate);
        while ($objTour->next())
        {
            $arrRow = array(
                'week'        => Date::parse('W', $objTour->startDate),
                'eventDates'  => $this->getEventPeriod($objTour->id, $this->dateFormat),
                'weekday'     => $this->getEventPeriod($objTour->id, 'D'),
                'title'       => $objTour->title,
                'instructors' => implode(', ', CalendarSacEvents::getInstructorNamesAsArray($objTour->id)),
                'organizers'  => implode(', ', CalendarSacEvents::getEventOrganizersAsArray($objTour->id)),
                'id'          => $objTour->id,
            );
            // tourType
            $arrEventType = CalendarSacEvents::getTourTypesAsArray($objTour->id, 'shortcut', false);
            if ($objTour->eventType === 'course')
            {
                // KU = Kurs
                $arrEventType[] = 'KU';
            }
            $arrRow['tourType'] = implode(', ', $arrEventType);


            // Add row to $arrTour
            $arrTours[] = $arrRow;

        }
        $this->allEventsTable = count($arrTours) > 0 ? $arrTours : null;
    }

    /**
     *
     */
    protected function generateCourses()
    {
        $objDatabase = Database::getInstance();
        $arrEvents = array();

        //$objEvent = $objDatabase->prepare('SELECT * FROM tl_calendar_events WHERE published=? AND (eventType=? OR eventType=?) AND startDate>=? AND endDate<=? ORDER BY startDate ASC')->execute('1', 'tour', 'generalEvent', $this->startDate, $this->endDate);
        $objEvent = $objDatabase->prepare('SELECT * FROM tl_calendar_events WHERE eventType=? AND published=? AND startDate>=? AND endDate<=? ORDER BY startDate ASC')->execute('course', '1', $this->startDate, $this->endDate);
        while ($objEvent->next())
        {

            $arrRow = $objEvent->row();
            $arrRow['title'] = $objEvent->title;
            $arrRow['eventState'] = $objEvent->eventState != '' ? $GLOBALS['TL_LANG']['tl_calendar_events'][$objEvent->eventState][0] : '';
            $arrRow['teaser'] = nl2br($objEvent->teaser);
            $arrRow['issues'] = nl2br($objEvent->issues);
            $arrRow['terms'] = nl2br($objEvent->issues);
            $arrRow['requirements'] = nl2br($objEvent->requirements);
            $arrRow['location'] = nl2br($objEvent->location);
            $arrRow['journey'] = nl2br($objEvent->location);
            $arrRow['equipment'] = nl2br($objEvent->equipment);
            $arrRow['leistungen'] = nl2br($objEvent->leistungen);
            $arrRow['bookingEvent'] = nl2br($objEvent->bookingEvent);
            $arrRow['meetingPoint'] = nl2br($objEvent->meetingPoint);
            $arrRow['miscellaneous'] = nl2br($objEvent->miscellaneous);
            $arrRow['week'] = Date::parse('W', $objEvent->startDate);
            $arrRow['eventDates'] = $this->getEventPeriod($objEvent->id, $this->dateFormat);
            $arrRow['weekday'] = $this->getEventPeriod($objEvent->id, 'D');
            $arrRow['instructors'] = implode(', ', CalendarSacEvents::getInstructorNamesAsArray($objEvent->id));
            $arrRow['organizers'] = implode(', ', CalendarSacEvents::getEventOrganizersAsArray($objEvent->id));
            $arrRow['meetingPoint'] = nl2br($objEvent->meetingPoint);

            $arrRow['id'] = $objEvent->id;

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

            $arrHeadline = array();
            $arrHeadline[] = $this->getEventPeriod($objEvent->id, 'd. F');
            $arrHeadline[] = $this->getEventPeriod($objEvent->id, 'D');
            $arrHeadline[] = $objEvent->title;
            if (isset($GLOBALS['TL_CONFIG']['SAC-EVENT-TOOL-CONFIG']['courseLevel'][$objEvent->courseLevel]))
            {
                $arrHeadline[] = 'Kursstufe ' . $GLOBALS['TL_CONFIG']['SAC-EVENT-TOOL-CONFIG']['courseLevel'][$objEvent->courseLevel];
            }
            if ($objEvent->courseId != '')
            {
                $arrHeadline[] = 'Kurs-Nr. ' . $objEvent->courseId;
            }
            $arrRow['headline'] = implode(' > ', $arrHeadline);


            // Add row to $arrTour
            $arrEvents[] = $arrRow;

        }
        $this->courses = count($arrEvents) > 0 ? $arrEvents : null;
    }

    /**
     * @param $organizer
     * @return array|null
     * @throws \Exception
     */
    protected function generateTours($organizer)
    {
        $objDatabase = Database::getInstance();
        $arrEvents = array();

        //$objEvent = $objDatabase->prepare('SELECT * FROM tl_calendar_events WHERE published=? AND (eventType=? OR eventType=?) AND startDate>=? AND endDate<=? ORDER BY startDate ASC')->execute('1', 'tour', 'generalEvent', $this->startDate, $this->endDate);
        $objEvent = $objDatabase->prepare('SELECT * FROM tl_calendar_events WHERE eventType=? AND published=? AND startDate>=? AND endDate<=? ORDER BY startDate ASC')->execute('tour', '1', $this->startDate, $this->endDate);
        while ($objEvent->next())
        {
            $arrOrganizers = StringUtil::deserialize($objEvent->organizers, true);
            if (!in_array($organizer, $arrOrganizers))
            {
                continue;
            }

            $arrRow = $objEvent->row();
            $arrRow['title'] = $objEvent->title;
            $arrRow['eventState'] = $objEvent->eventState != '' ? $GLOBALS['TL_LANG']['tl_calendar_events'][$objEvent->eventState][0] : '';
            $arrRow['teaser'] = nl2br($objEvent->teaser);
            $arrRow['issues'] = nl2br($objEvent->issues);
            $arrRow['terms'] = nl2br($objEvent->issues);
            $arrRow['requirements'] = nl2br($objEvent->requirements);
            $arrRow['location'] = nl2br($objEvent->location);
            $arrRow['journey'] = nl2br($objEvent->location);
            $arrRow['equipment'] = nl2br($objEvent->equipment);
            $arrRow['leistungen'] = nl2br($objEvent->leistungen);
            $arrRow['bookingEvent'] = nl2br($objEvent->bookingEvent);
            $arrRow['meetingPoint'] = nl2br($objEvent->meetingPoint);
            $arrRow['miscellaneous'] = nl2br($objEvent->miscellaneous);
            $arrRow['week'] = Date::parse('W', $objEvent->startDate);
            $arrRow['eventDates'] = $this->getEventPeriod($objEvent->id, $this->dateFormat);
            $arrRow['weekday'] = $this->getEventPeriod($objEvent->id, 'D');
            $arrRow['instructors'] = implode(', ', CalendarSacEvents::getInstructorNamesAsArray($objEvent->id));
            $arrRow['organizers'] = implode(', ', CalendarSacEvents::getEventOrganizersAsArray($objEvent->id));
            $arrRow['tourProfile'] = implode('<br>', CalendarSacEvents::getTourProfileAsArray($objEvent->id));
            $arrRow['tourDetailText'] = nl2br($objEvent->tourDetailText);
            $arrRow['meetingPoint'] = nl2br($objEvent->meetingPoint);
            $arrRow['journey'] = CalendarEventsJourneyModel::findByPk($objEvent->journey) !== null ? CalendarEventsJourneyModel::findByPk($objEvent->journey)->title : '';


            $arrRow['id'] = $objEvent->id;

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

            $arrHeadline = array();
            $arrHeadline[] = $this->getEventPeriod($objEvent->id, 'd. F');
            $arrHeadline[] = $this->getEventPeriod($objEvent->id, 'D');
            $arrHeadline[] = $objEvent->title;

            $strDifficulties = implode(', ', CalendarSacEvents::getTourTechDifficultiesAsArray($objEvent->id));
            if ($strDifficulties != '')
            {
                $arrHeadline[] = $strDifficulties;
            }
            $arrRow['headline'] = implode(' > ', $arrHeadline);


            // Add row to $arrTour
            $arrEvents[] = $arrRow;

        }
        return count($arrEvents) > 0 ? $arrEvents : null;
    }
}