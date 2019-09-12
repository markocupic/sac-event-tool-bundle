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
use Contao\Config;
use Contao\Controller;
use Contao\Database;
use Contao\Date;
use Contao\Environment;
use Contao\Input;
use Contao\Module;
use Haste\Form\Form;
use League\Csv\Reader;
use League\Csv\Writer;
use Patchwork\Utf8;

/**
 * Class ModuleSacEventToolCsvEventMemberExport
 * @package Markocupic\SacEventToolBundle
 */
class ModuleSacEventToolCsvEventMemberExport extends Module
{

    /**
     * Template
     * @var string
     */
    protected $strTemplate = 'sac_event_tool_csv_event_member_export';

    /**
     * @var
     */
    protected $objForm;

    /**
     * @var string
     */
    protected $strDelimiter = ';';

    /**
     * @var string
     */
    protected $strEnclosure = '"';

    /**
     * @var
     */
    protected $defaultPassword;

    /**
     * @var array
     */
    protected $arrLines = array();

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

            $objTemplate->wildcard = '### ' . Utf8::strtoupper($GLOBALS['TL_LANG']['FMD']['eventToolCsvEventMemberExport'][0]) . ' ###';
            $objTemplate->title = $this->headline;
            $objTemplate->id = $this->id;
            $objTemplate->link = $this->name;
            $objTemplate->href = 'contao/main.php?do=themes&amp;table=tl_module&amp;act=edit&amp;id=' . $this->id;

            return $objTemplate->parse();
        }

        $this->defaultPassword = Config::get('SAC_EVT_DEFAULT_BACKEND_PASSWORD');

        return parent::generate();
    }

    /**
     * Generate the module
     */
    protected function compile()
    {
        $this->generateForm();
        $this->Template->form = $this->objForm;
    }

    /**
     * @throws \League\Csv\Exception
     * @throws \TypeError
     */
    private function generateForm()
    {
        $objForm = new Form('form-event-member-export', 'POST', function ($objHaste) {
            return Input::post('FORM_SUBMIT') === $objHaste->getFormId();
        });

        $objForm->setFormActionFromUri(Environment::get('uri'));

        // Now let's add form fields:
        $objForm->addFormField('event-type', array(
            'label'     => array('Event-Typ auswählen', ''),
            'inputType' => 'select',
            'options'   => array('tour' => 'Tour', 'course' => 'Kurs'),
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

        $objForm->addFormField('endDate', array(
            'label'     => 'Enddatum',
            'inputType' => 'text',
            'eval'      => array('rgxp' => 'date', 'mandatory' => true),
        ));

        $objForm->addFormField('mountainguide', array(
            'label'     => array('', 'Event mit Bergführer'),
            'inputType' => 'checkbox',
            'eval'      => array(),
        ));

        /**
         * $objForm->addFormField('keep-groups-in-one-line', array(
         * 'label'     => array('', 'Gruppen einzeilig darstellen'),
         * 'inputType' => 'checkbox',
         * ));
         **/

        // Let's add  a submit button
        $objForm->addFormField('submit', array(
            'label'     => 'Export starten',
            'inputType' => 'submit',
        ));

        if ($objForm->validate())
        {
            if (Input::post('FORM_SUBMIT') === 'form-event-member-export')
            {
                $eventType = Input::post('event-type');
                $arrFields = array('id', 'eventId', 'eventName', 'startDate', 'endDate', 'eventState', 'executionState', 'firstname', 'lastname', 'gender', 'dateOfBirth', 'street', 'postal', 'city', 'phone', 'mobile', 'email', 'sacMemberId', 'hasParticipated', 'stateOfSubscription', 'addedOn');
                $startDate = strtotime(Input::post('startDate'));
                $endDate = strtotime(Input::post('endDate'));

                $objEvent = Database::getInstance()->prepare('SELECT * FROM tl_calendar_events WHERE eventType=? AND startDate>=? AND startDate<=? ORDER BY startDate')->execute($eventType, $startDate, $endDate);
                $this->getHeadline($arrFields);
                while ($objEvent->next())
                {
                    $skipRecord = false;
                    if (Input::post('mountainguide'))
                    {
                        if (!$objEvent->mountainguide)
                        {
                            $skipRecord = true;
                        }
                    }
                    if (!$skipRecord)
                    {
                        $objEventMember = Database::getInstance()->prepare('SELECT * FROM tl_calendar_events_member WHERE eventId=? ORDER BY lastname')->execute($objEvent->id);
                        while ($objEventMember->next())
                        {
                            $this->addLine($arrFields, $objEventMember);
                        }
                    }
                }
                $this->printCsv(sprintf('Event-Member-Export_%s.csv', Date::parse('Y-m-d')));
            }
        }

        $this->objForm = $objForm->generate();
    }

    /**
     * @param $arrFields
     * @param $objEventMember
     */
    private function addLine($arrFields, $objEventMember)
    {
        $arrLine = array();
        foreach ($arrFields as $field)
        {
            $arrLine[] = $this->getField($field, $objEventMember);
        }

        $this->arrLines[] = $arrLine;
    }

    /**
     * @param $arrFields
     * @return array
     */
    private function getHeadline($arrFields)
    {
        // Write headline
        $arrHeadline = array();
        foreach ($arrFields as $field)
        {
            if ($field === 'startDate' || $field === 'endDate' || $field === 'eventState' || $field === 'executionState')
            {
                Controller::loadLanguageFile('tl_calendar_events');
                $arrHeadline[] = isset($GLOBALS['TL_LANG']['tl_calendar_events'][$field][0]) ? $GLOBALS['TL_LANG']['tl_calendar_events'][$field][0] : $field;
            }
            else
            {
                Controller::loadLanguageFile('tl_calendar_events_member');
                $arrHeadline[] = isset($GLOBALS['TL_LANG']['tl_calendar_events_member'][$field][0]) ? $GLOBALS['TL_LANG']['tl_calendar_events_member'][$field][0] : $field;
            }
        }
        $this->arrLines[] = $arrHeadline;
    }

    /**
     * @param $field
     * @param $objEventMember
     * @return string
     */
    private function getField($field, $objEventMember)
    {
        if ($field === 'password')
        {
            return '#######';
        }
        elseif ($field === 'addedOn')
        {
            return Date::parse('Y-m-d', $objEventMember->addedOn);
        }
        elseif ($field === 'dateOfBirth')
        {
            if (is_numeric($objEventMember->$field))
            {
                return Date::parse(Config::get('dateFormat'), $objEventMember->$field);
            }
        }
        elseif ($field === 'stateOfSubscription')
        {
            return isset($GLOBALS['TL_LANG']['tl_calendar_events_member'][$objEventMember->$field]) ? $GLOBALS['TL_LANG']['tl_calendar_events_member'][$objEventMember->$field] : $objEventMember->$field;
        }
        elseif ($field === 'startDate')
        {
            $objEvent = CalendarEventsModel::findByPk($objEventMember->eventId);
            if ($objEvent !== null)
            {
                return Date::parse('Y-m-d', $objEvent->startDate);
            }
            else
            {
                return '';
            }
        }
        elseif ($field === 'endDate')
        {
            $objEvent = CalendarEventsModel::findByPk($objEventMember->eventId);
            if ($objEvent !== null)
            {
                return Date::parse('Y-m-d', $objEvent->endDate);
            }
            else
            {
                return '';
            }
        }
        elseif ($field === 'executionState')
        {
            Controller::loadLanguageFile('tl_calendar_events');
            $objEvent = CalendarEventsModel::findByPk($objEventMember->eventId);
            if ($objEvent !== null)
            {
                return isset($GLOBALS['TL_LANG']['tl_calendar_events'][$objEvent->$field][0]) ? $GLOBALS['TL_LANG']['tl_calendar_events'][$objEvent->$field][0] : $objEvent->$field;
            }
            else
            {
                return '';
            }
        }
        elseif ($field === 'eventState')
        {
            Controller::loadLanguageFile('tl_calendar_events');
            $objEvent = CalendarEventsModel::findByPk($objEventMember->eventId);
            if ($objEvent !== null)
            {
                return isset($GLOBALS['TL_LANG']['tl_calendar_events'][$objEvent->$field][0]) ? $GLOBALS['TL_LANG']['tl_calendar_events'][$objEvent->$field][0] : $objEvent->$field;
            }
            else
            {
                return '';
            }
        }
        else
        {
            return $objEventMember->{$field};
        }
    }

    /**
     * @param $filename
     * @throws \League\Csv\Exception
     */
    private function printCsv($filename)
    {
        $arrData = $this->arrLines;

        // Convert special chars
        $arrFinal = array();
        foreach ($arrData as $arrRow)
        {
            $arrLine = array_map(function ($v) {
                return html_entity_decode(htmlspecialchars_decode($v));
            }, $arrRow);
            $arrFinal[] = $arrLine;
        }

        // Send file to browser
        header('Content-Encoding: UTF-8');
        header('Content-type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="' . $filename);

        // Load the CSV document from a string
        $csv = Writer::createFromString('');
        $csv->setOutputBOM(Reader::BOM_UTF8);

        $csv->setDelimiter($this->strDelimiter);
        $csv->setEnclosure($this->strEnclosure);

        // Insert all the records
        $csv->insertAll($arrFinal);

        // Returns the CSV document as a string
        echo $csv;

        exit;
    }
}
