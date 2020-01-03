<?php

/**
 * SAC Event Tool Web Plugin for Contao
 * Copyright (c) 2008-2020 Marko Cupic
 * @package sac-event-tool-bundle
 * @author Marko Cupic m.cupic@gmx.ch, 2017-2020
 * @link https://github.com/markocupic/sac-event-tool-bundle
 */

namespace Markocupic\SacEventToolBundle\Controller\FrontendModule;

use Contao\Calendar;
use Contao\CalendarEventsJourneyModel;
use Contao\CalendarEventsModel;
use Contao\Config;
use Contao\Controller;
use Contao\Date;
use Contao\Environment;
use Contao\Events;
use Contao\FrontendTemplate;
use Contao\ModuleModel;
use Contao\PageModel;
use Contao\StringUtil;
use Contao\System;
use Contao\Template;
use Contao\Validator;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Driver\PDOStatement;
use Doctrine\DBAL\Query\QueryBuilder;
use Haste\Form\Form;
use Markocupic\SacEventToolBundle\CalendarEventsHelper;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Contao\CoreBundle\ServiceAnnotation\FrontendModule;

/**
 * Class PilatusExportController
 * @package Markocupic\SacEventToolBundle\Controller\FrontendModule
 * @FrontendModule(category="sac_event_tool_frontend_modules", type="pilatus_export")
 */
class PilatusExportController extends AbstractPrintExportController
{

    /**
     * @var ModuleModel
     */
    protected $model;

    /**
     * @var
     */
    protected $objForm;

    /**
     * @var int
     */
    protected $startDate;

    /**
     * @var int
     */
    protected $endDate;

    /**
     * @var int|null
     */
    protected $eventReleaseLevel;

    /**
     * @var string $dateFormat
     */
    protected $dateFormat = 'j.';

    /**
     * @var boolean $showQrCode
     */
    protected $showQrCode = false;

    /**
     * @var null
     */
    protected $allEventsTable = null;

    /**
     * @var string
     */
    protected $htmlCourseTable = '';

    /**
     * @var string
     */
    protected $htmlTourTable = '';

    /**
     * @var array
     */
    protected $events = array();

    /**
     * Editable course fields
     * @var array
     */
    protected $courseFeEditableFields = array('teaser', 'issues', 'terms', 'requirements', 'equipment', 'leistungen', 'bookingEvent', 'meetingPoint', 'miscellaneous');

    /**
     * Editable tour fields
     * @var array
     */
    protected $tourFeEditableFields = array('teaser', 'tourDetailText', 'requirements', 'equipment', 'leistungen', 'bookingEvent', 'meetingPoint', 'miscellaneous');

    /**
     * @param Request $request
     * @param ModuleModel $model
     * @param string $section
     * @param array|null $classes
     * @param PageModel|null $page
     * @return Response
     */
    public function __invoke(Request $request, ModuleModel $model, string $section, array $classes = null, PageModel $page = null): Response
    {
        $this->model = $model;

        /** @var Request $request */
        $request = $this->get('request_stack')->getCurrentRequest();

        // Handle form submits and reload page
        if ($request->request->get('FORM_SUBMIT') === 'edit-event')
        {
            if ($request->request->get('EVENT_TYPE') === 'course')
            {
                $arrFields = $this->courseFeEditableFields;
            }
            elseif ($request->request->get('EVENT_TYPE') === 'tour')
            {
                $arrFields = $this->tourFeEditableFields;
            }

            $set = array();
            foreach ($arrFields as $field)
            {
                $set[$field] = $request->request->get($field);
            }

            $eventId = $request->request->get('id');

            $arrReturn = array(
                'status'  => 'error',
                'eventId' => $eventId,
                'message' => '',
                'set'     => $set,
            );

            if ($eventId > 0 && count($set) > 0)
            {
                try
                {
                    /** @var  Connection $conn */
                    $conn = System::getContainer()->get('database_connection');

                    /** @var QueryBuilder $qb */
                    $qb = $conn->createQueryBuilder();
                    $qb->update('tl_calendar_events', 't')
                        ->values($set)
                        ->where('t.id = :id')
                        ->setParameter('id', $eventId);
                    $qb->execute();

                    $arrReturn['status'] = 'success';
                    $arrReturn['message'] = sprintf('Saved datarecord with ID %s successfully to the Database (tl_calendar_events).', $eventId);
                } catch (\Exception $e)
                {
                    $arrReturn['status'] = 'error';
                    $arrReturn['message'] = 'Error during the upload process: ' . $e->getMessage();
                }
            }

            /** @var JsonResponse $json */
            $json = new JsonResponse($arrReturn, 200);
            $json->send();
            exit;
        }

        // Call the parent method
        return parent::__invoke($request, $model, $section, $classes);
    }

    /**
     * @return array
     */
    public static function getSubscribedServices(): array
    {
        $services = parent::getSubscribedServices();

        $services['request_stack'] = RequestStack::class;
        $services['database_connection'] = Connection::class;

        return $services;
    }

    /**
     * Generate the module
     */
    protected function getResponse(Template $template, ModuleModel $model, Request $request): ?Response
    {
        /** @var Controller $controllerAdapter */
        $controllerAdapter = $this->get('contao.framework')->getAdapter(Controller::class);

        // Load language file
        $controllerAdapter->loadLanguageFile('tl_calendar_events');

        // Generate the filter form
        $this->generateForm();
        $template->form = $this->objForm;

        // Course table
        /** @var  FrontendTemplate $objPartial */
        $objPartial = new FrontendTemplate('mod_pilatus_export_events_table_partial');
        $objPartial->eventTable = $this->htmlCourseTable;
        $template->htmlCourseTable = $objPartial->parse();

        // Tour & general event table
        /** @var  FrontendTemplate $objPartial */
        $objPartial = new FrontendTemplate('mod_pilatus_export_events_table_partial');
        $objPartial->eventTable = $this->htmlTourTable;
        $template->htmlTourTable = $objPartial->parse();

        // The event array() courses, tours, generalEvents
        $template->events = $this->events;

        // Pass editable fields to the template object
        $template->courseFeEditableFields = $this->courseFeEditableFields;
        $template->tourFeEditableFields = $this->tourFeEditableFields;

        return $template->getResponse();
    }

    /**
     * @return Form
     */
    protected function generateForm()
    {
        /** @var Request $request */
        $request = $this->get('request_stack')->getCurrentRequest();

        /** @var Date $dateAdapter */
        $dateAdapter = $this->get('contao.framework')->getAdapter(Date::class);

        /** @var  Environment $environmentAdapter */
        $environmentAdapter = $this->get('contao.framework')->getAdapter(Environment::class);

        /** @var Validator $validatorAdapter */
        $validatorAdapter = $this->get('contao.framework')->getAdapter(Validator::class);

        /** @var Form $objForm */
        $objForm = new Form('form-pilatus-export', 'POST', function ($objHaste) {
            $request = $this->get('request_stack')->getCurrentRequest();
            return $request->request->get('FORM_SUBMIT') === $objHaste->getFormId();
        });
        $objForm->setFormActionFromUri($environmentAdapter->get('uri'));

        $range = array();
        $range[0] = '---';

        $now = $dateAdapter->parse('n');
        $start = $now % 2 > 0 ? -7 : -6;

        for ($i = $start; $i < $start + 16; $i += 2)
        {
            $key = $dateAdapter->parse("Y-m-01", strtotime($i . " month")) . '|' . $dateAdapter->parse("Y-m-t", strtotime($i + 1 . "  month"));
            $range[$key] = $dateAdapter->parse("01.m.Y", strtotime($i . " month")) . '-' . $dateAdapter->parse("t.m.Y", strtotime($i + 1 . "  month"));
        }

        // Now let's add form fields:
        $objForm->addFormField('timeRange', array(
            'label'     => 'Zeitspanne (fixe Zeitspanne)',
            'inputType' => 'select',
            'options'   => $range,
            'eval'      => array('mandatory' => false),
        ));

        $objForm->addFormField('timeRangeStart', array(
            'label'     => array('Zeitspanne manuelle Eingabe (Startdatum)'),
            'inputType' => 'text',
            'eval'      => array('mandatory' => false, 'maxlength' => '10', 'minlength' => 8, 'placeholder' => 'dd.mm.YYYY'),
            'value'     => $request->request->get('timeRangeStart')
        ));

        $objForm->addFormField('timeRangeEnd', array(
            'label'     => 'Zeitspanne manuelle Eingabe (Enddatum)',
            'inputType' => 'text',
            'eval'      => array('mandatory' => false, 'maxlength' => '10', 'minlength' => 8, 'placeholder' => 'dd.mm.YYYY'),
            'value'     => $request->request->get('timeRangeEnd')
        ));

        $objForm->addFormField('eventReleaseLevel', array(
            'label'     => 'Zeige an ab Freigabestufe (Wenn leer gelassen, wird ab 2. höchster FS gelistet!)',
            'inputType' => 'select',
            'options'   => array(1 => 'FS1', 2 => 'FS2', 3 => 'FS3', 4 => 'FS4'),
            'eval'      => array('mandatory' => false, 'includeBlankOption' => true),
        ));

        $objForm->addFormField('showQrCode', array(
            'label'     => array('QR Code', 'QR Code anzeigen?'),
            'inputType' => 'checkbox',
            'eval'      => array('mandatory' => false),
        ));

        // Let's add  a submit button
        $objForm->addFormField('submit', array(
            'label'     => 'Export starten',
            'inputType' => 'submit',
        ));

        // validate() also checks whether the form has been submitted
        if ($objForm->validate())
        {
            // User has selected a predefined time range
            if ($request->request->get('timeRange') != 0)
            {
                $arrRange = explode('|', $request->request->get('timeRange'));
                $this->startDate = strtotime($arrRange[0]);
                $this->endDate = strtotime($arrRange[1]);
                $objForm->getWidget('timeRangeStart')->value = '';
                $objForm->getWidget('timeRangeEnd')->value = '';
            }
            // If the user has set the start & end date manually
            elseif ($request->request->get('timeRangeStart') != '' || $request->request->get('timeRangeEnd') != '')
            {
                if ($request->request->get('timeRangeStart') == '' || $request->request->get('timeRangeEnd') == '')
                {
                    $addError = true;
                }
                elseif (strtotime($request->request->get('timeRangeStart')) > 0 && strtotime($request->request->get('timeRangeStart')) > 0)
                {
                    $objWidgetStart = $objForm->getWidget('timeRangeStart');
                    $objWidgetEnd = $objForm->getWidget('timeRangeEnd');

                    $intStart = strtotime($request->request->get('timeRangeStart'));
                    $intEnd = strtotime($request->request->get('timeRangeEnd'));

                    if ($intStart > $intEnd || (!isset($arrRange) && (!$validatorAdapter->isDate($objWidgetStart->value) || !$validatorAdapter->isDate($objWidgetEnd->value))))
                    {
                        $addError = true;
                    }
                    else
                    {
                        $this->startDate = $intStart;
                        $this->endDate = $intEnd;
                        $request->request->set('timeRange', '');
                        $objForm->getWidget('timeRange')->value = '';
                    }
                }

                if ($addError)
                {
                    $strError = 'Ungültige Datumseingabe. Gib das Datum im Format \'dd.mm.YYYY\' ein. Das Startdatum muss kleiner sein als das Enddatum.';
                    $objForm->getWidget('timeRangeStart')->addError($strError);
                    $objForm->getWidget('timeRangeEnd')->addError($strError);
                }
            }

            // Generate QR code
            if ($request->request->get('showQrCode'))
            {
                $this->showQrCode = true;
            }

            if ($this->startDate && $this->endDate)
            {
                $this->eventReleaseLevel = (int)$request->request->get('eventReleaseLevel') > 0 ? (int)$request->request->get('eventReleaseLevel') : null;
                $this->htmlCourseTable = $this->generateEventTable(['course']);
                $this->htmlTourTable = $this->generateEventTable(['tour', 'generalEvent']);

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
        /** @var Date $dateAdapter */
        $dateAdapter = $this->get('contao.framework')->getAdapter(Date::class);

        /** @var  CalendarEventsHelper $calendarEventsHelperAdapter */
        $calendarEventsHelperAdapter = $this->get('contao.framework')->getAdapter(CalendarEventsHelper::class);

        /** @var  StringUtil $stringUtilAdapter */
        $stringUtilAdapter = $this->get('contao.framework')->getAdapter(StringUtil::class);

        /** @var CalendarEventsModel $calendarEventsModelAdapter */
        $calendarEventsModelAdapter = $this->get('contao.framework')->getAdapter(CalendarEventsModel::class);

        $arrTours = array();

        /** @var Connection $conn */
        $conn = System::getContainer()->get('database_connection');

        /** @var QueryBuilder $qb */
        $qb = $conn->createQueryBuilder();
        $qb->select('*')
            ->from('tl_calendar_events', 't')
            ->where('t.startDate >= :startdate')
            ->andWhere('t.startDate <= :enddate')
            ->andWhere($qb->expr()->in('t.eventType', ':eventtypes'))
            ->setParameter('startdate', $this->startDate)
            ->setParameter('enddate', $this->endDate)
            ->setParameter('eventtypes', $arrAllowedEventType, Connection::PARAM_STR_ARRAY)
            ->addOrderBy('t.startDate', 'ASC');

        /** @var PDOStatement $results */
        $results = $qb->execute();

        while (false !== ($oEvent = $results->fetch(\PDO::FETCH_OBJ)))
        {
            $objEvent = $calendarEventsModelAdapter->findByPk($oEvent->id);
            if (null === $objEvent)
            {
                continue;
            }

            // Check if event has allowed type
            $arrAllowedEventTypes = $stringUtilAdapter->deserialize($this->model->print_export_allowedEventTypes, true);
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
            $arrRow['week'] = $dateAdapter->parse('W', $objEvent->startDate) . ', ' . $dateAdapter->parse('j.', $this->getFirstDayOfWeekTimestamp($objEvent->startDate)) . '-' . $dateAdapter->parse('j. F', $this->getLastDayOfWeekTimestamp($objEvent->startDate));
            $arrRow['eventDates'] = $this->getEventPeriod($objEvent->id, 'd.');
            $arrRow['weekday'] = $this->getEventPeriod($objEvent->id, 'D');
            $arrRow['title'] = $objEvent->title . ($objEvent->eventType === 'lastMinuteTour' ? ' (LAST MINUTE TOUR!)' : '');
            $arrRow['instructors'] = implode(', ', $calendarEventsHelperAdapter->getInstructorNamesAsArray($objEvent->id, false, false));
            $arrRow['organizers'] = implode(', ', $calendarEventsHelperAdapter->getEventOrganizersAsArray($objEvent->id, 'titlePrint'));

            // tourType
            $arrEventType = $calendarEventsHelperAdapter->getTourTypesAsArray($objEvent->id, 'shortcut', false);
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
        /** @var Date $dateAdapter */
        $dateAdapter = $this->get('contao.framework')->getAdapter(Date::class);

        $date = $dateAdapter->parse('d-m-Y', $timestamp);
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
        /** @var Date $dateAdapter */
        $dateAdapter = $this->get('contao.framework')->getAdapter(Date::class);

        /** @var  CalendarEventsHelper $calendarEventsHelperAdapter */
        $calendarEventsHelperAdapter = $this->get('contao.framework')->getAdapter(CalendarEventsHelper::class);

        /** @var  Config $configAdapter */
        $configAdapter = $this->get('contao.framework')->getAdapter(Config::class);

        /** @var  Calendar $calendarAdapter */
        $calendarAdapter = $this->get('contao.framework')->getAdapter(Calendar::class);

        if (empty($dateFormat))
        {
            $dateFormat = $configAdapter->get('dateFormat');
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

        $eventDuration = count($calendarEventsHelperAdapter->getEventTimestamps($id));
        $span = $calendarAdapter->calculateSpan($calendarEventsHelperAdapter->getStartDate($id), $calendarEventsHelperAdapter->getEndDate($id)) + 1;

        if ($eventDuration == 1)
        {
            return $dateAdapter->parse($dateFormatShortened['to'], $calendarEventsHelperAdapter->getStartDate($id));
        }

        if ($eventDuration == 2 && $span != $eventDuration)
        {
            return $dateAdapter->parse($dateFormatShortened['from'], $calendarEventsHelperAdapter->getStartDate($id)) . ' & ' . $dateAdapter->parse($dateFormatShortened['to'], $calendarEventsHelperAdapter->getEndDate($id));
        }
        elseif ($span == $eventDuration)
        {
            return $dateAdapter->parse($dateFormatShortened['from'], $calendarEventsHelperAdapter->getStartDate($id)) . '-' . $dateAdapter->parse($dateFormatShortened['to'], $calendarEventsHelperAdapter->getEndDate($id));
        }
        else
        {
            $arrDates = array();
            $dates = $calendarEventsHelperAdapter->getEventTimestamps($id);
            foreach ($dates as $date)
            {
                $arrDates[] = $dateAdapter->parse($dateFormatShortened['to'], $date);
            }

            return implode(', ', $arrDates);
        }
    }

    /**
     * Generate course
     * array: $this->events['courses']
     * @throws \Exception
     */
    protected function generateCourses()
    {
        /** @var  StringUtil $stringUtilAdapter */
        $stringUtilAdapter = $this->get('contao.framework')->getAdapter(StringUtil::class);

        /** @var CalendarEventsModel $calendarEventsModelAdapter */
        $calendarEventsModelAdapter = $this->get('contao.framework')->getAdapter(CalendarEventsModel::class);

        $arrEvents = array();

        $eventType = 'course';

        /** @var  Connection $conn */
        $conn = System::getContainer()->get('database_connection');

        // SELECT * FROM tl_calendar_events t1 WHERE
        // (t1.eventType = :eventtype) AND
        // (t1.startDate >= :startdate) AND
        // (t1.startDate <= :enddate) AND
        // (t1.courseTypeLevel1 IN (SELECT id FROM tl_course_sub_type t2 WHERE t2.pid IN (SELECT id FROM tl_course_main_type t3 ORDER BY t3.code ASC) ORDER BY t2.code ASC))
        // ORDER BY t1.courseId ASC, t1.startDate ASC

        /** @var QueryBuilder $qbSubSub */
        $qbSubSub = $conn->createQueryBuilder();
        $qbSubSub->select('id')
            ->from('tl_course_main_type', 't3')
            ->orderBy('t3.code', 'ASC');

        /** @var QueryBuilder $qbSub */
        $qbSub = $conn->createQueryBuilder();
        $qbSub->select('id')
            ->from('tl_course_sub_type', 't2')
            ->where($qbSub->expr()->in('t2.pid', $qbSubSub->getSQL()))
            ->orderBy('t2.code', 'ASC');

        /** @var QueryBuilder $qb */
        $qb = $conn->createQueryBuilder();
        $qb->select('*')
            ->from('tl_calendar_events', 't1')
            ->where('t1.eventType = :eventtype')
            ->andWhere('t1.startDate >= :startdate')
            ->andWhere('t1.startDate <= :enddate')
            ->andWhere($qb->expr()->in('t1.courseTypeLevel1', $qbSub->getSQL()))
            ->setParameter('eventtype', $eventType)
            ->setParameter('startdate', $this->startDate)
            ->setParameter('enddate', $this->endDate)
            ->orderBy('t1.courseId')
            ->addOrderBy('t1.startDate', 'ASC');

        /** @var PDOStatement $results */
        $results = $qb->execute();

        while (false !== ($objEvent = $results->fetch(\PDO::FETCH_OBJ)))
        {
            $eventModel = $calendarEventsModelAdapter->findByPk($objEvent->id);
            if (null === $eventModel)
            {
                continue;
            }

            // Check if event has allowed type
            $arrAllowedEventTypes = $stringUtilAdapter->deserialize($this->model->print_export_allowedEventTypes, true);
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

            // Add record to the collection
            $arrEvents[] = $arrRow;
        }

        $this->events['courses'] = count($arrEvents) > 0 ? $arrEvents : null;
    }

    /**
     * Generate tours and generalEvents
     * @param $type
     * @throws \Exception
     */
    function generateEvents($eventType)
    {
        /** @var  CalendarEventsHelper $calendarEventsHelperAdapter */
        $calendarEventsHelperAdapter = $this->get('contao.framework')->getAdapter(CalendarEventsHelper::class);

        /** @var  StringUtil $stringUtilAdapter */
        $stringUtilAdapter = $this->get('contao.framework')->getAdapter(StringUtil::class);

        /** @var CalendarEventsModel $calendarEventsModelAdapter */
        $calendarEventsModelAdapter = $this->get('contao.framework')->getAdapter(CalendarEventsModel::class);

        $arrOrganizerContainer = array();

        /** @var  Connection $conn */
        $conn = System::getContainer()->get('database_connection');

        /** @var QueryBuilder $qbOrganizers */
        $qbOrganizers = $conn->createQueryBuilder();
        $qbOrganizers->select('*')
            ->from('tl_event_organizer', 't2')
            ->orderBy('t2.sorting', 'ASC');

        /** @var PDOStatement $resultsOrganizers */
        $resultsOrganizers = $qbOrganizers->execute();

        while (false !== ($objOrganizer = $resultsOrganizers->fetch(\PDO::FETCH_OBJ)))
        {
            $arrOrganizerEvents = array();

            /** @var QueryBuilder $qb */
            $qbEvents = $conn->createQueryBuilder();
            $qbEvents->select('*')
                ->from('tl_calendar_events', 't1')
                ->where('t1.eventType = :eventtype')
                ->andWhere('t1.startDate >= :startdate')
                ->andWhere('t1.startDate <= :enddate')
                ->setParameter('eventtype', $eventType)
                ->setParameter('startdate', $this->startDate)
                ->setParameter('enddate', $this->endDate)
                ->orderBy('t1.startDate', 'ASC');

            /** @var PDOStatement $resultsEvents */
            $resultsEvents = $qbEvents->execute();

            while (false !== ($objEvent = $resultsEvents->fetch(\PDO::FETCH_OBJ)))
            {
                $eventModel = $calendarEventsModelAdapter->findByPk($objEvent->id);
                if (null === $eventModel)
                {
                    continue;
                }

                $arrOrganizers = $stringUtilAdapter->deserialize($eventModel->organizers, true);
                if (!in_array($objOrganizer->id, $arrOrganizers))
                {
                    continue;
                }

                // Check if event has allowed type
                $arrAllowedEventTypes = $stringUtilAdapter->deserialize($this->model->print_export_allowedEventTypes, true);
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
                $strDifficulties = implode(', ', $calendarEventsHelperAdapter->getTourTechDifficultiesAsArray($eventModel->id));

                if ($strDifficulties != '')
                {
                    $arrHeadline[] = $strDifficulties;
                }
                $arrRow['headline'] = implode(' > ', $arrHeadline);

                // Add row to $arrOrganizerEvents
                $arrOrganizerEvents[] = $arrRow;
            }

            $arrOrganizerContainer[] = array(
                'id'     => $objOrganizer->id,
                'title'  => $objOrganizer->title,
                'events' => $arrOrganizerEvents,
            );
        }

        $this->events[$eventType . 's'] = $arrOrganizerContainer;
    }

    /**
     * Helper method
     * @param $objEvent
     * @return mixed
     * @throws \Exception
     */
    private function getEventDetails($objEvent)
    {
        /** @var Date $dateAdapter */
        $dateAdapter = $this->get('contao.framework')->getAdapter(Date::class);

        /** @var  Environment $environmentAdapter */
        $environmentAdapter = $this->get('contao.framework')->getAdapter(Environment::class);

        /** @var  CalendarEventsHelper $calendarEventsHelperAdapter */
        $calendarEventsHelperAdapter = $this->get('contao.framework')->getAdapter(CalendarEventsHelper::class);

        /** @var Events $eventsAdapter */
        $eventsAdapter = $this->get('contao.framework')->getAdapter(Events::class);

        /** @var  CalendarEventsJourneyModel $calendarEventsJourneyModelAdapter */
        $calendarEventsJourneyModelAdapter = $this->get('contao.framework')->getAdapter(CalendarEventsJourneyModel::class);

        /** @var Controller $controllerAdapter */
        $controllerAdapter = $this->get('contao.framework')->getAdapter(Controller::class);

        $arrRow = $objEvent->row();
        $arrRow['url'] = $environmentAdapter->get('url') . '/' . $eventsAdapter->generateEventUrl($objEvent);
        if ($this->showQrCode)
        {
            $arrRow['qrCode'] = $calendarEventsHelperAdapter->getEventQrCode($objEvent);
        }
        $arrRow['eventState'] = $objEvent->eventState != '' ? $GLOBALS['TL_LANG']['tl_calendar_events'][$objEvent->eventState][0] : '';
        $arrRow['week'] = $dateAdapter->parse('W', $objEvent->startDate);
        $arrRow['eventDates'] = $this->getEventPeriod($objEvent->id, $this->dateFormat);
        $arrRow['weekday'] = $this->getEventPeriod($objEvent->id, 'D');
        $arrRow['instructors'] = implode(', ', $calendarEventsHelperAdapter->getInstructorNamesAsArray($objEvent->id, false, false));
        $arrRow['organizers'] = implode(', ', $calendarEventsHelperAdapter->getEventOrganizersAsArray($objEvent->id, 'title'));
        $arrRow['tourProfile'] = implode('<br>', $calendarEventsHelperAdapter->getTourProfileAsArray($objEvent->id));
        $arrRow['journey'] = $calendarEventsJourneyModelAdapter->findByPk($objEvent->journey) !== null ? $calendarEventsJourneyModelAdapter->findByPk($objEvent->journey)->title : '';

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
            $arrRow['registrationPeriod'] = $dateAdapter->parse('j.m.Y H:i', $objEvent->registrationStartDate) . ' bis ' . $dateAdapter->parse('j.m.Y H:i', $objEvent->registrationEndDate);
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

        $arrEvents = array();
        foreach ($arrRow as $k => $v)
        {
            // Replace Contao insert tags
            $arrEvents[$k] = $controllerAdapter->replaceInsertTags($v);
        }

        return $arrEvents;
    }

}
