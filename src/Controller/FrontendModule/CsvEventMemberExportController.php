<?php

/**
 * SAC Event Tool Web Plugin for Contao
 * Copyright (c) 2008-2020 Marko Cupic
 * @package sac-event-tool-bundle
 * @author Marko Cupic m.cupic@gmx.ch, 2017-2020
 * @link https://github.com/markocupic/sac-event-tool-bundle
 */

namespace Markocupic\SacEventToolBundle\Controller\FrontendModule;

use Contao\CalendarEventsModel;
use Contao\Config;
use Contao\Controller;
use Contao\CoreBundle\Controller\FrontendModule\AbstractFrontendModuleController;
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\Date;
use Contao\Environment;
use Contao\MemberModel;
use Contao\ModuleModel;
use Contao\Template;
use Doctrine\DBAL\Connection;
use Haste\Form\Form;
use League\Csv\Reader;
use League\Csv\Writer;
use Markocupic\SacEventToolBundle\CalendarEventsHelper;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Contao\CoreBundle\ServiceAnnotation\FrontendModule;

/**
 * Class CsvEventMemberExportController
 * @package Markocupic\SacEventToolBundle\Controller\FrontendModule
 * @FrontendModule(category="sac_event_tool_fe_modules", type="csv_event_member_export")
 */
class CsvEventMemberExportController extends AbstractFrontendModuleController
{

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
     * @return array
     */
    public static function getSubscribedServices(): array
    {
        $services = parent::getSubscribedServices();

        $services['contao.framework'] = ContaoFramework::class;
        $services['database_connection'] = Connection::class;
        $services['request_stack'] = RequestStack::class;

        return $services;
    }

    /**
     * @param Template $template
     * @param ModuleModel $model
     * @param Request $request
     * @return null|Response
     * @throws \League\Csv\Exception
     */
    protected function getResponse(Template $template, ModuleModel $model, Request $request): ?Response
    {
        $this->generateForm();
        $template->form = $this->objForm;
        $template->dateFormat = $this->get('contao.framework')->getAdapter(Config::class)->get('dateFormat');

        return $template->getResponse();
    }

    /**
     * @throws \Doctrine\DBAL\DBALException
     * @throws \League\Csv\Exception
     */
    private function generateForm()
    {
        $objForm = new Form('form-event-member-export', 'POST', function ($objHaste) {
            $request = $this->get('request_stack')->getCurrentRequest();
            return $request->get('FORM_SUBMIT') === $objHaste->getFormId();
        });

        $environment = $this->get('contao.framework')->getAdapter(Environment::class);
        $objForm->setFormActionFromUri($environment->get('uri'));

        // Now let's add form fields:
        $objForm->addFormField('event-type', array(
            'label'     => array('Event-Typ auswählen', ''),
            'inputType' => 'select',
            'options'   => array('all' => 'Alle Events', 'tour' => 'Tour', 'course' => 'Kurs'),
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
            'label'     => array('Bergführer', 'Nur Events mit Bergführer exportieren'),
            'inputType' => 'checkbox',
            'eval'      => array(),
        ));

        // Let's add  a submit button
        $objForm->addFormField('submit', array(
            'label'     => 'Export starten',
            'inputType' => 'submit',
        ));

        if ($objForm->validate())
        {
            $request = $this->get('request_stack')->getCurrentRequest();
            if ($request->get('FORM_SUBMIT') === 'form-event-member-export')
            {
                $eventType = $request->get('event-type');
                $arrFields = array('id', 'eventId', 'eventName', 'startDate', 'endDate', 'mainInstructor', 'mountainguide', 'eventState', 'executionState', 'firstname', 'lastname', 'gender', 'dateOfBirth', 'street', 'postal', 'city', 'phone', 'mobile', 'email', 'sacMemberId', 'bookingType', 'hasParticipated', 'stateOfSubscription', 'addedOn');
                $startDate = strtotime($request->get('startDate'));
                $endDate = strtotime($request->get('endDate'));
                $this->getHeadline($arrFields);

                $statement1 = $this->get('database_connection')->executeQuery('SELECT * FROM tl_calendar_events WHERE startDate>=? AND startDate<=? ORDER BY startDate', array($startDate, $endDate));
                while (false !== ($objEvent = $statement1->fetch(\PDO::FETCH_OBJ)))
                {
                    if ($eventType != 'all')
                    {
                        if ($objEvent->eventType != $eventType)
                        {
                            continue;
                        }
                    }

                    if ($request->get('mountainguide'))
                    {
                        if (!$objEvent->mountainguide)
                        {
                            continue;
                        }
                    }

                    // Set tl_member.disable to true if member was not found in the csv-file
                    $statement2 = $this->get('database_connection')->executeQuery('SELECT * FROM tl_calendar_events_member WHERE eventId=? ORDER BY lastname', array($objEvent->id));
                    while (false !== ($objEventMember = $statement2->fetch(\PDO::FETCH_OBJ)))
                    {
                        $this->addLine($arrFields, $objEventMember);
                    }
                }
                $date = $this->get('contao.framework')->getAdapter(Date::class);

                $this->printCsv(sprintf('Event-Member-Export_%s.csv', $date->parse('Y-m-d')));
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
     */
    private function getHeadline($arrFields)
    {
        // Write headline
        $controller = $this->get('contao.framework')->getAdapter(Controller::class);
        $arrHeadline = array();
        foreach ($arrFields as $field)
        {
            if ($field === 'mainInstructor' || $field === 'mountainguide' || $field === 'startDate' || $field === 'endDate' || $field === 'eventState' || $field === 'executionState')
            {
                $controller->loadLanguageFile('tl_calendar_events');
                $arrHeadline[] = isset($GLOBALS['TL_LANG']['tl_calendar_events'][$field][0]) ? $GLOBALS['TL_LANG']['tl_calendar_events'][$field][0] : $field;
            }
            elseif ($field === 'phone')
            {
                $controller->loadLanguageFile('tl_member');
                $arrHeadline[] = isset($GLOBALS['TL_LANG']['tl_member'][$field][0]) ? $GLOBALS['TL_LANG']['tl_member'][$field][0] : $field;
            }
            else
            {
                $controller->loadLanguageFile('tl_calendar_events_member');
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
        $date = $this->get('contao.framework')->getAdapter(Date::class);
        $config = $this->get('contao.framework')->getAdapter(Config::class);
        $controller = $this->get('contao.framework')->getAdapter(Controller::class);
        $calendarEventsHelper = $this->get('contao.framework')->getAdapter(CalendarEventsHelper::class);
        $calendarEventsModel = $this->get('contao.framework')->getAdapter(CalendarEventsModel::class);
        $memberModel = $this->get('contao.framework')->getAdapter(MemberModel::class);

        if ($field === 'password')
        {
            return '#######';
        }
        elseif ($field === 'addedOn')
        {
            return $date->parse('Y-m-d', $objEventMember->addedOn);
        }
        elseif ($field === 'dateOfBirth')
        {
            if (is_numeric($objEventMember->$field))
            {
                return $date->parse($config->get('dateFormat'), $objEventMember->$field);
            }
        }
        elseif ($field === 'stateOfSubscription')
        {
            return isset($GLOBALS['TL_LANG']['tl_calendar_events_member'][$objEventMember->$field]) ? $GLOBALS['TL_LANG']['tl_calendar_events_member'][$objEventMember->$field] : $objEventMember->$field;
        }
        elseif ($field === 'startDate')
        {
            $objEvent = $calendarEventsModel->findByPk($objEventMember->eventId);
            if ($objEvent !== null)
            {
                return $date->parse('Y-m-d', $objEvent->startDate);
            }
            else
            {
                return '';
            }
        }
        elseif ($field === 'endDate')
        {
            $objEvent = $calendarEventsModel->findByPk($objEventMember->eventId);
            if ($objEvent !== null)
            {
                return $date->parse('Y-m-d', $objEvent->endDate);
            }
            else
            {
                return '';
            }
        }
        elseif ($field === 'executionState')
        {
            $controller->loadLanguageFile('tl_calendar_events');
            $objEvent = $calendarEventsModel->findByPk($objEventMember->eventId);
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
            $controller->loadLanguageFile('tl_calendar_events');
            $objEvent = $calendarEventsModel->findByPk($objEventMember->eventId);
            if ($objEvent !== null)
            {
                return isset($GLOBALS['TL_LANG']['tl_calendar_events'][$objEvent->$field][0]) ? $GLOBALS['TL_LANG']['tl_calendar_events'][$objEvent->$field][0] : $objEvent->$field;
            }
            else
            {
                return '';
            }
        }
        elseif ($field === 'mainInstructor')
        {
            $objEvent = $calendarEventsModel->findByPk($objEventMember->eventId);
            if ($objEvent !== null)
            {
                return $calendarEventsHelper->getMainInstructorName($objEventMember->eventId);
            }
            else
            {
                return '';
            }
        }
        elseif ($field === 'mountainguide')
        {
            $objEvent = $calendarEventsModel->findByPk($objEventMember->eventId);
            if ($objEvent !== null)
            {
                return $objEvent->$field;
            }
            else
            {
                return '';
            }
        }
        elseif ($field === 'phone')
        {
            $objMember = $memberModel->findBySacMemberId($objEventMember->sacMemberId);
            if ($objMember !== null)
            {
                return $objMember->$field;
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

        // Load the CSV document from a string
        $csv = Writer::createFromString('');
        $csv->setOutputBOM(Reader::BOM_UTF8);

        $csv->setDelimiter($this->strDelimiter);
        $csv->setEnclosure($this->strEnclosure);

        // Insert all the records
        $csv->insertAll($arrFinal);

        // Send file to browser
        $csv->output($filename);
        exit;
    }
}
