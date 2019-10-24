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
use Contao\CalendarEventsModel;
use Contao\Calendar;
use Contao\Controller;
use Contao\CourseMainTypeModel;
use Contao\CourseSubTypeModel;
use Contao\Database;
use Contao\Date;
use Contao\Environment;
use Contao\EventOrganizerModel;
use Contao\Input;
use Contao\StringUtil;
use Contao\UserRoleModel;
use Haste\Form\Form;
use Patchwork\Utf8;


/**
 * Class ModuleSacEventToolJahresprogrammExport
 * @package Markocupic\SacEventToolBundle
 */
class ModuleSacEventToolJahresprogrammExport extends ModuleSacEventToolPrintExport
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
     * @var
     */
    protected $eventReleaseLevel;

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
            'eval'      => array('includeBlankOption' => true, 'mandatory' => true),
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
            'eval'      => array('includeBlankOption' => true, 'mandatory' => false),
        ));

        $objForm->addFormField('startDate', array(
            'label'     => 'Startdatum',
            'inputType' => 'text',
            'eval'      => array('rgxp' => 'date', 'mandatory' => true),
        ));

        $objForm->addFormField('endDate', array(
            'label'     => 'Enddatum',
            'inputType' => 'text',
            'eval'      => array('rgxp' => 'date', 'mandatory' => true),
        ));

        $objForm->addFormField('eventReleaseLevel', array(
            'label'     => 'Zeige an ab Freigabestufe (Wenn leer gelassen, wird ab 2. höchster FS gelistet!)',
            'inputType' => 'select',
            'options'   => array(1 => 'FS1', 2 => 'FS2', 3 => 'FS3', 4 => 'FS4'),
            'eval'      => array('mandatory' => false, 'includeBlankOption' => true),
        ));


        $arrUserRoles = array();
        $objUserRoles = Database::getInstance()->prepare('SELECT * FROM tl_user_role ORDER BY title')->execute();
        while ($objUserRoles->next())
        {
            $arrUserRoles[$objUserRoles->id] = $objUserRoles->title;
        }
        $objForm->addFormField('userRoles', array(
            'label'     => 'Neben den Event-Leitern zus&auml;tzliche Funktion&auml;re anzeigen',
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
            if (Input::post('startDate') != '' && Input::post('endDate') != '' && Input::post('eventType') != '')
            {

                $this->startDate = strtotime(Input::post('startDate'));
                $this->endDate = strtotime(Input::post('endDate'));
                $this->eventType = Input::post('eventType');
                $this->organizer = Input::post('organizer') > 0 ? Input::post('organizer') : null;
                $this->eventReleaseLevel = Input::post('eventReleaseLevel') > 0 ? Input::post('eventReleaseLevel') : null;

                // Get events and instructors (fill $this->events and $this->instructors)
                $this->getEventsAndInstructors();

                $this->Template->eventType = $this->eventType;
                $this->Template->eventTypeLabel = $GLOBALS['TL_LANG']['MSC'][$this->eventType];
                $this->Template->startDate = $this->startDate;
                $this->Template->endDate = $this->endDate;
                $this->Template->organizer = $this->organizer > 0 ? EventOrganizerModel::findByPk($this->organizer)->title : 'Alle Gruppen';
                $this->Template->events = $this->events;
                $this->Template->instructors = $this->instructors;

                $this->specialUsers = $this->getUsersByUserRole(Input::post('userRoles'));
                $this->Template->specialUsers = $this->specialUsers;
            }
        }
        $this->objForm = $objForm;

    }

    /**
     * @throws \Exception
     */
    protected function getEventsAndInstructors()
    {
        $arrEvents = array();
        $objEvents = Database::getInstance()->prepare('SELECT * FROM tl_calendar_events WHERE startDate>=? AND startDate<=?')->execute($this->startDate, $this->endDate);
        while ($objEvents->next())
        {


            // Check if event is at least on second highest level (Level 3/4)
            $eventModel = CalendarEventsModel::findByPk($objEvents->id);
            if (!$this->hasValidReleaseLevel($eventModel, $this->eventReleaseLevel))
            {
                continue;
            }

            if ($this->organizer)
            {
                $arrOrganizer = StringUtil::deserialize($objEvents->organizers, true);
                if (!in_array($this->organizer, $arrOrganizer))
                {
                    continue;
                }
            }

            if ($this->eventType !== $objEvents->eventType)
            {
                continue;
            }
            $arrEvents[] = intval($objEvents->id);
        }


        $arrInstructors = array();
        if (count($arrEvents) > 0)
        {
            $arrEvent = array();

            // Let's use different queries for each event type
            if ($this->eventType === 'course')
            {
                $objEvent = Database::getInstance()->execute('SELECT * FROM tl_calendar_events WHERE id IN (' . implode(',', array_map('\intval', $arrEvents)) . ') ORDER BY courseTypeLevel0, courseTypeLevel1, startDate, endDate, courseId');
            }
            else
            {
                $objEvent = Database::getInstance()->execute('SELECT * FROM tl_calendar_events WHERE id IN (' . implode(',', array_map('\intval', $arrEvents)) . ') ORDER BY startDate, endDate');
            }
            while ($objEvent->next())
            {
                $arrInstructors = array_merge($arrInstructors, CalendarEventsHelper::getInstructorsAsArray($objEvent->id, false));

                // tourType && date format
                $arrTourType = CalendarEventsHelper::getTourTypesAsArray($objEvent->id, 'shortcut', false);
                $dateFormat = 'j.';

                if ($objEvent->eventType === 'course')
                {
                    // KU = Kurs
                    $arrTourType[] = 'KU';
                    $dateFormat = 'j.n.';
                }
                $arrEvent[] = array(
                    'id'               => $objEvent->id,
                    'eventType'        => $objEvent->eventType,
                    'courseId'         => $objEvent->courseId,
                    'organizers'       => implode(', ', CalendarEventsHelper::getEventOrganizersAsArray($objEvent->id, 'title')),
                    'courseLevel'      => isset($GLOBALS['TL_CONFIG']['SAC-EVENT-TOOL-CONFIG']['courseLevel'][$objEvent->courseLevel]) ? $GLOBALS['TL_CONFIG']['SAC-EVENT-TOOL-CONFIG']['courseLevel'][$objEvent->courseLevel] : '',
                    'courseTypeLevel0' => (CourseMainTypeModel::findByPk($objEvent->courseTypeLevel0) !== null) ? CourseMainTypeModel::findByPk($objEvent->courseTypeLevel0)->name : '',
                    'courseTypeLevel1' => (CourseSubTypeModel::findByPk($objEvent->courseTypeLevel1) !== null) ? CourseSubTypeModel::findByPk($objEvent->courseTypeLevel1)->name : '',
                    'title'            => $objEvent->title,
                    'date'             => $this->getEventPeriod($objEvent->id, $dateFormat),
                    'month'            => Date::parse('F', $objEvent->startDate),
                    'durationInfo'     => $objEvent->durationInfo,
                    'instructors'      => implode(', ', CalendarEventsHelper::getInstructorNamesAsArray($objEvent->id, false, false)),
                    'tourType'         => implode(', ', $arrTourType),
                    'difficulty'       => implode(',', CalendarEventsHelper::getTourTechDifficultiesAsArray($objEvent->id)),
                );
            }
            $this->events = $arrEvent;


            $arrInstructors = array_unique($arrInstructors);
            $aInstructors = array();
            $objUser = Database::getInstance()->execute('SELECT * FROM tl_user WHERE id IN (' . implode(',', array_map('\intval', $arrInstructors)) . ') ORDER BY lastname, firstname');
            while ($objUser->next())
            {
                $arrLeft = array();
                $arrLeft[] = trim($objUser->lastname . ' ' . $objUser->firstname);
                $arrLeft[] = $objUser->street;
                $arrLeft[] = trim($objUser->postal . ' ' . $objUser->city);
                $arrLeft = array_filter($arrLeft);


                $arrRight = array();
                if ($objUser->phone != '')
                {
                    $arrRight[] = 'P ' . $objUser->phone;
                }
                if ($objUser->mobile != '')
                {
                    $arrRight[] = 'M ' . $objUser->mobile;
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

        if ($dateFormat === 'j.n.')
        {
            $dateFormatShortened = 'j.n.';
        }
        elseif ($dateFormat === 'j.')
        {
            $dateFormatShortened = 'j.';
        }
        else
        {
            $dateFormatShortened = $dateFormat;
        }


        $eventDuration = count(CalendarEventsHelper::getEventTimestamps($id));
        $span = Calendar::calculateSpan(CalendarEventsHelper::getStartDate($id), CalendarEventsHelper::getEndDate($id)) + 1;

        if ($eventDuration == 1)
        {
            return Date::parse($dateFormat, CalendarEventsHelper::getStartDate($id));
        }
        if ($eventDuration == 2 && $span != $eventDuration)
        {
            return Date::parse($dateFormatShortened, CalendarEventsHelper::getStartDate($id)) . '+' . Date::parse($dateFormat, CalendarEventsHelper::getEndDate($id));
        }
        elseif ($span == $eventDuration)
        {
            // Check if event dates are not in the same month
            if (Date::parse('n.Y', CalendarEventsHelper::getStartDate($id)) === Date::parse('n.Y', CalendarEventsHelper::getEndDate($id)))
            {
                return Date::parse($dateFormatShortened, CalendarEventsHelper::getStartDate($id)) . '-' . Date::parse($dateFormat, CalendarEventsHelper::getEndDate($id));
            }
            else
            {
                return Date::parse('j.n.', CalendarEventsHelper::getStartDate($id)) . '-' . Date::parse('j.n.', CalendarEventsHelper::getEndDate($id));
            }
        }
        else
        {
            $arrDates = array();
            $dates = CalendarEventsHelper::getEventTimestamps($id);
            foreach ($dates as $date)
            {
                $arrDates[] = Date::parse($dateFormat, $date);
            }

            return implode('+', $arrDates);
        }
    }


    /**
     * @param $arrUserRoles
     * @return array
     */
    protected function getUsersByUserRole($arrUserRoles)
    {
        $specialUsers = array();
        if (is_array($arrUserRoles) && !empty($arrUserRoles))
        {
            $objUserRoles = Database::getInstance()->execute('SELECT * FROM tl_user_role WHERE id IN(' . implode(',', array_map('\intval', $arrUserRoles)) . ') ORDER BY sorting');
            while ($objUserRoles->next())
            {
                $userRole = $objUserRoles->id;
                $arrUsers = array();
                $objUser = Database::getInstance()->execute('SELECT * FROM tl_user ORDER BY lastname, firstname');
                while ($objUser->next())
                {
                    $userRoles = StringUtil::deserialize($objUser->userRole, true);
                    if (in_array($userRole, $userRoles))
                    {

                        $arrLeft = array();
                        $arrLeft[] = trim($objUser->lastname . ' ' . $objUser->firstname);
                        $arrLeft[] = $objUser->street;
                        $arrLeft[] = trim($objUser->postal . ' ' . $objUser->city);
                        $arrLeft = array_filter($arrLeft);


                        $arrRight = array();
                        if ($objUser->phone != '')
                        {
                            $arrRight[] = 'P ' . $objUser->phone;
                        }
                        if ($objUser->mobile != '')
                        {
                            $arrRight[] = 'M ' . $objUser->mobile;
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
        return $specialUsers;
    }

}
