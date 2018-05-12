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
use Contao\Database;
use Contao\Date;
use Contao\Environment;
use Contao\Input;
use Contao\Module;
use Haste\Form\Form;
use Patchwork\Utf8;

/**
 * Class ModuleSacEventToolPilatusExport
 * @package Markocupic\SacEventToolBundle
 */
class ModuleSacEventToolPilatusExport extends Module
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
    protected $dateFormat;

    /**
     * @var null
     */
    protected $tours = null;

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


        return parent::generate();
    }


    /**
     * Generate the module
     */
    protected function compile()
    {

        $this->generateForm();

        $this->Template->form = $this->objForm;
        $this->Template->tours = $this->tours;


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
        $start = $now%2>0 ? -11: -10;

        for ($i = $start; $i < $start + 30; $i+=2)
        {
            // echo Date::parse('Y-m-d',strtotime(Date::parse("Y-m-1", strtotime($i . " month"))));
            //echo "<br>";
            $key = Date::parse("Y-m-1", strtotime($i . " month")) . '|' . Date::parse("Y-m-t", strtotime($i+1 . "  month"));
            $range[$key] = Date::parse("1.m.Y", strtotime($i . " month")) . '-' . Date::parse("t.m.Y", strtotime($i+1 . "  month"));
        }

        $dateFormat = array();
        $dateFormat['d'] = "Nur den Tag";
        $dateFormat['d.m'] = "Tag und Monat";
        $dateFormat['d.m.Y'] = "Tag Monat und Jahr";


        // Now let's add form fields:
        $objForm->addFormField('timeRange', array(
            'label'     => 'Notfalltelefonnummer/In Notf&auml;llen zu kontaktieren',
            'inputType' => 'select',
            'options'   => $range,
            //'default'   => $this->User->emergencyPhone,
            'eval'      => array('mandatory' => true),
        ));

        $objForm->addFormField('dateFormat', array(
            'label'     => 'Notfalltelefonnummer/In Notf&auml;llen zu kontaktieren',
            'inputType' => 'select',
            'options'   => $dateFormat,
            //'default'   => $this->User->emergencyPhone,
            'eval'      => array('mandatory' => true),
        ));

        // Let's add  a submit button
        $objForm->addFormField('submit', array(
            'label'     => 'Export starten',
            'inputType' => 'submit',
        ));

        // validate() also checks whether the form has been submitted
        if ($objForm->validate())
        {
            if (Input::post('timeRange') != 0 && Input::post('dateFormat') != '')
            {
                $arrRange = explode('|', Input::post('timeRange'));
                $this->startDate = strtotime($arrRange[0]);
                $this->endDate = strtotime($arrRange[1]);
                $this->dateFormat = Input::post('dateFormat');
                $this->generateList();
            }
        }

        $this->objForm = $objForm;
    }


    /**
     *
     */
    protected function generateList()
    {
        $objDatabase = Database::getInstance();
        $arrTour = array();
        $objTour = $objDatabase->prepare('SELECT * FROM tl_calendar_events WHERE published=? AND (eventType=? OR eventType=?) AND startDate>=? AND endDate<=? ORDER BY startDate ASC')->execute('1', 'tour', 'generalEvent', $this->startDate, $this->endDate);
        while ($objTour->next())
        {
            $arrTour[] = array(
                'week'        => Date::parse('W', $objTour->startDate),
                'eventDates'  => $this->getEventPeriod($objTour->id, $this->dateFormat),
                'weekday'     => $this->getEventPeriod($objTour->id, 'D'),
                'title'       => $objTour->title,
                'tourType'    => implode(', ', CalendarSacEvents::getTourTypesAsArray($objTour->id, 'shortcut', false)),
                'instructors' => implode(', ', CalendarSacEvents::getInstructorNamesAsArray($objTour->id)),
                'organizers'  => implode(', ', CalendarSacEvents::getEventOrganizersAsArray($objTour->id)),
                'id'          => $objTour->id,
            );
        }
        $this->tours = count($arrTour) > 0 ? $arrTour : null;
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


        $span = Calendar::calculateSpan(CalendarSacEvents::getStartDate($id), CalendarSacEvents::getEndDate($id)) + 1;
        if (CalendarSacEvents::getEventDuration($id) == 1)
        {
            return Date::parse($dateFormat, CalendarSacEvents::getStartDate($id));
        }
        elseif ($span == CalendarSacEvents::getEventDuration($id))
        {
            return Date::parse($dateFormatShortened, CalendarSacEvents::getStartDate($id)) . ' - ' . Date::parse($dateFormat, CalendarSacEvents::getEndDate($id));
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
}