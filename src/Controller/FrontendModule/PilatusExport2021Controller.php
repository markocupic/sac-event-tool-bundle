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

namespace Markocupic\SacEventToolBundle\Controller\FrontendModule;

use Contao\Calendar;
use Contao\CalendarEventsJourneyModel;
use Contao\CalendarEventsModel;
use Contao\Config;
use Contao\Controller;
use Contao\CoreBundle\ServiceAnnotation\FrontendModule;
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

/**
 * Class PilatusExport2021Controller.
 *
 * @FrontendModule("pilatus_export_2021", category="sac_event_tool_frontend_modules")
 */
class PilatusExport2021Controller extends AbstractPrintExportController
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
     * @var string
     */
    protected $dateFormat = 'j.';

    /**
     * @var bool
     */
    protected $showQrCode = false;

    /**
     * @var null
     */
    protected $allEventsTable;

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
    protected $events = [];

    /**
     * Editable course fields.
     *
     * @var array
     */
    protected $courseFeEditableFields = ['teaser', 'issues', 'terms', 'requirements', 'equipment', 'leistungen', 'bookingEvent', 'meetingPoint', 'miscellaneous'];

    /**
     * Editable tour fields.
     *
     * @var array
     */
    protected $tourFeEditableFields = ['teaser', 'tourDetailText', 'requirements', 'equipment', 'leistungen', 'bookingEvent', 'meetingPoint', 'miscellaneous'];

    public function __invoke(Request $request, ModuleModel $model, string $section, array $classes = null, ?PageModel $page = null): Response
    {
        $this->model = $model;

        /** @var Request $request */
        $request = $this->get('request_stack')->getCurrentRequest();

        // Handle form submits and reload page
        if ('update-record' === $request->request->get('FORM_SUBMIT')) {
            $this->updateRecord();
            exit;
        }

        // Call the parent method
        return parent::__invoke($request, $model, $section, $classes, $page);
    }

    public static function getSubscribedServices(): array
    {
        $services = parent::getSubscribedServices();

        $services['request_stack'] = RequestStack::class;
        $services['database_connection'] = Connection::class;

        return $services;
    }

    /**
     * @throws \Exception
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
        /** @var FrontendTemplate $objPartial */
        $objPartial = new FrontendTemplate('mod_pilatus_export_2021_events_table_partial');
        $objPartial->eventTable = $this->htmlCourseTable;
        $objPartial->isCourse = true;
        $template->htmlCourseTable = $objPartial->parse();

        // Tour & general event table
        /** @var FrontendTemplate $objPartial */
        $objPartial = new FrontendTemplate('mod_pilatus_export_2021_events_table_partial');
        $objPartial->eventTable = $this->htmlTourTable;
        $objPartial->isCourse = false;
        $template->htmlTourTable = $objPartial->parse();

        // The event array() courses, tours, generalEvents
        $template->events = $this->events;

        // Pass editable fields to the template object
        $template->courseFeEditableFields = $this->courseFeEditableFields;
        $template->tourFeEditableFields = $this->tourFeEditableFields;

        return $template->getResponse();
    }

    /**
     * Update Record.
     */
    protected function updateRecord(): JsonResponse
    {
        /** @var Request $request */
        $request = $this->get('request_stack')->getCurrentRequest();

        $arrFields = [];

        if ('course' === $request->request->get('EVENT_TYPE')) {
            $arrFields = $this->courseFeEditableFields;
        } elseif ('tour' === $request->request->get('EVENT_TYPE')) {
            $arrFields = $this->tourFeEditableFields;
        }

        $set = [];

        foreach ($arrFields as $field) {
            $set[$field] = $request->request->get($field);
        }

        $eventId = $request->request->get('id');

        $arrReturn = [
            'status' => 'error',
            'eventId' => $eventId,
            'message' => '',
            'set' => $set,
        ];

        if ($eventId > 0 && \count($set) > 0) {
            try {
                /** @var Connection $conn */
                $conn = System::getContainer()->get('database_connection');
                $conn->update('tl_calendar_events', $set, ['id' => $eventId]);

                $arrReturn['status'] = 'success';
                $arrReturn['message'] = sprintf('Saved datarecord with ID %s successfully to the Database (tl_calendar_events).', $eventId);
            } catch (\Exception $e) {
                $arrReturn['status'] = 'error';
                $arrReturn['message'] = 'Error during the upload process: '.$e->getMessage();
            }
        }

        /** @var JsonResponse $json */
        $json = new JsonResponse($arrReturn, 200);

        return $json->send();
    }

    /**
     * Generate the form.
     *
     * @throws \Exception
     */
    protected function generateForm(): void
    {
        /** @var Request $request */
        $request = $this->get('request_stack')->getCurrentRequest();

        /** @var Date $dateAdapter */
        $dateAdapter = $this->get('contao.framework')->getAdapter(Date::class);

        /** @var Environment $environmentAdapter */
        $environmentAdapter = $this->get('contao.framework')->getAdapter(Environment::class);

        /** @var Validator $validatorAdapter */
        $validatorAdapter = $this->get('contao.framework')->getAdapter(Validator::class);

        /** @var Form $objForm */
        $objForm = new Form(
            'form-pilatus-export',
            'POST',
            function (Form $objHaste): bool {
                $request = $this->get('request_stack')->getCurrentRequest();

                return $request->request->get('FORM_SUBMIT') === $objHaste->getFormId();
            }
        );
        $objForm->setFormActionFromUri($environmentAdapter->get('uri'));

        $year = (int) date('Y');

        $arrRange = [];
        $arrRange[0] = '---';
        $arrRange[1] = date('Y-m-01', strtotime((string) ($year - 1).'-10-01')).' - '.$dateAdapter->parse('Y-m-t', strtotime((string) ($year - 1).'-12-01'));
        $arrRange[2] = date('Y-m-01', strtotime((string) $year.'-01-01')).' - '.$dateAdapter->parse('Y-m-t', strtotime((string) $year.'-03-01'));
        $arrRange[3] = date('Y-m-01', strtotime((string) $year.'-04-01')).' - '.$dateAdapter->parse('Y-m-t', strtotime((string) $year.'-06-01'));
        $arrRange[4] = date('Y-m-01', strtotime((string) $year.'-07-01')).' - '.$dateAdapter->parse('Y-m-t', strtotime((string) $year.'-09-01'));
        $arrRange[5] = date('Y-m-01', strtotime((string) $year.'-10-01')).' - '.$dateAdapter->parse('Y-m-t', strtotime((string) $year.'-12-01'));
        $arrRange[6] = date('Y-m-01', strtotime((string) ($year + 1).'-01-01')).' - '.$dateAdapter->parse('Y-m-t', strtotime((string) ($year + 1).'-03-01'));

        $range = [];

        foreach ($arrRange as $strRange) {
            $key = str_replace(' - ', '|', $strRange);
            $range[$key] = $strRange;
        }

        // Now let's add form fields:
        $objForm->addFormField('timeRange', [
            'label' => 'Zeitspanne (fixe Zeitspanne)',
            'inputType' => 'select',
            'options' => $range,
            'eval' => ['mandatory' => false],
        ]);

        $objForm->addFormField('timeRangeStart', [
            'label' => ['Zeitspanne manuelle Eingabe (Startdatum)'],
            'inputType' => 'text',
            'eval' => ['mandatory' => false, 'maxlength' => '10', 'minlength' => 8, 'placeholder' => 'dd.mm.YYYY'],
            'value' => $request->request->get('timeRangeStart'),
        ]);

        $objForm->addFormField('timeRangeEnd', [
            'label' => 'Zeitspanne manuelle Eingabe (Enddatum)',
            'inputType' => 'text',
            'eval' => ['mandatory' => false, 'maxlength' => '10', 'minlength' => 8, 'placeholder' => 'dd.mm.YYYY'],
            'value' => $request->request->get('timeRangeEnd'),
        ]);

        $objForm->addFormField('eventReleaseLevel', [
            'label' => 'Zeige an ab Freigabestufe (Wenn leer gelassen, wird ab 2. höchster FS gelistet!)',
            'inputType' => 'select',
            'options' => [1 => 'FS1', 2 => 'FS2', 3 => 'FS3', 4 => 'FS4'],
            'eval' => ['mandatory' => false, 'includeBlankOption' => true],
        ]);

        $objForm->addFormField('showQrCode', [
            'label' => ['QR Code', 'QR Code anzeigen?'],
            'inputType' => 'checkbox',
            'eval' => ['mandatory' => false],
        ]);

        // Let's add a submit button
        $objForm->addFormField('submit', [
            'label' => 'Export starten',
            'inputType' => 'submit',
        ]);

        // validate() also checks whether the form has been submitted
        if ($objForm->validate()) {
            // User has selected a predefined time range
            if ($request->request->get('timeRange') && $request->request->get('timeRange') !== '---') {
                $arrRange = explode('|', $request->request->get('timeRange'));
                $this->startDate = strtotime($arrRange[0]);
                $this->endDate = strtotime($arrRange[1]);
                $objForm->getWidget('timeRangeStart')->value = '';
                $objForm->getWidget('timeRangeEnd')->value = '';
            }
            // If the user has set the start & end date manually
            elseif ('' !== $request->request->get('timeRangeStart') || '' !== $request->request->get('timeRangeEnd')) {
                $addError = false;

                if (empty($request->request->get('timeRangeStart')) || empty($request->request->get('timeRangeEnd'))) {
                    $addError = true;
                } elseif (strtotime($request->request->get('timeRangeStart')) > 0 && strtotime($request->request->get('timeRangeStart')) > 0) {
                    $objWidgetStart = $objForm->getWidget('timeRangeStart');
                    $objWidgetEnd = $objForm->getWidget('timeRangeEnd');

                    $intStart = strtotime($request->request->get('timeRangeStart'));
                    $intEnd = strtotime($request->request->get('timeRangeEnd'));

                    if ($intStart > $intEnd || (!isset($arrRange) && (!$validatorAdapter->isDate($objWidgetStart->value) || !$validatorAdapter->isDate($objWidgetEnd->value)))) {
                        $addError = true;
                    } else {
                        $this->startDate = $intStart;
                        $this->endDate = $intEnd;
                        $request->request->set('timeRange', '');
                        $objForm->getWidget('timeRange')->value = '';
                    }
                }

                if ($addError) {
                    $strError = 'Ungültige Datumseingabe. Gib das Datum im Format \'dd.mm.YYYY\' ein. Das Startdatum muss kleiner sein als das Enddatum.';
                    $objForm->getWidget('timeRangeStart')->addError($strError);
                    $objForm->getWidget('timeRangeEnd')->addError($strError);
                }
            }

            // Generate QR code
            if ($request->request->get('showQrCode')) {
                $this->showQrCode = true;
            }

            if ($this->startDate && $this->endDate) {
                $this->eventReleaseLevel = (int) $request->request->get('eventReleaseLevel') > 0 ? (int) $request->request->get('eventReleaseLevel') : null;
                $this->htmlCourseTable = $this->generateEventTable(['course']);
                $this->htmlTourTable = $this->generateEventTable(['tour', 'generalEvent']);

                //$this->generateCourses();
                //$this->generateEvents('tour');
                //$this->generateEvents('generalEvent');
            }
        }

        $this->objForm = $objForm;
    }

    /**
     * @throws \Exception
     */
    protected function generateEventTable(array $arrAllowedEventType): ?array
    {
        /** @var Date $dateAdapter */
        $dateAdapter = $this->get('contao.framework')->getAdapter(Date::class);

        /** @var CalendarEventsHelper $calendarEventsHelperAdapter */
        $calendarEventsHelperAdapter = $this->get('contao.framework')->getAdapter(CalendarEventsHelper::class);

        /** @var StringUtil $stringUtilAdapter */
        $stringUtilAdapter = $this->get('contao.framework')->getAdapter(StringUtil::class);

        /** @var CalendarEventsModel $calendarEventsModelAdapter */
        $calendarEventsModelAdapter = $this->get('contao.framework')->getAdapter(CalendarEventsModel::class);

        $arrTours = [];

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
            ->addOrderBy('t.startDate', 'ASC')
        ;

        /** @var PDOStatement $results */
        $results = $qb->execute();

        while (false !== ($oEvent = $results->fetch(\PDO::FETCH_OBJ))) {
            $objEvent = $calendarEventsModelAdapter->findByPk($oEvent->id);

            if (null === $objEvent) {
                continue;
            }

            // Check if event has allowed type
            $arrAllowedEventTypes = $stringUtilAdapter->deserialize($this->model->print_export_allowedEventTypes, true);

            if (!\in_array($objEvent->eventType, $arrAllowedEventTypes, false)) {
                continue;
            }

            // Check if event is at least on second highest level (Level 3/4)
            if (!$this->hasValidReleaseLevel($objEvent, $this->eventReleaseLevel)) {
                continue;
            }

            $arrRow = $objEvent->row();
            $arrRow['eventType'] = $objEvent->eventType;
            $arrRow['week'] = $dateAdapter->parse('W', $objEvent->startDate).', '.$dateAdapter->parse('j.', $this->getFirstDayOfWeekTimestamp((int) $objEvent->startDate)).'-'.$dateAdapter->parse('j. F', $this->getLastDayOfWeekTimestamp((int) $objEvent->startDate));
            $arrRow['eventDates'] = $this->getEventPeriod($objEvent, 'd.');
            $arrRow['weekday'] = $this->getEventPeriod($objEvent, 'D');
            $arrRow['title'] = $objEvent->title.('lastMinuteTour' === $objEvent->eventType ? ' (LAST MINUTE TOUR!)' : '');
            $arrRow['instructors'] = implode(', ', $calendarEventsHelperAdapter->getInstructorNamesAsArray($objEvent, false, false));
            $arrRow['organizers'] = implode(', ', $calendarEventsHelperAdapter->getEventOrganizersAsArray($objEvent, 'titlePrint'));
            $arrRow['eventId'] = date('Y', (int) $objEvent->startDate) . '-' . $objEvent->id;
            if($objEvent->eventType === 'course')
            {
                $arrRow['eventId'] = $objEvent->courseId;
            }

            // tourType
            $arrEventType = $calendarEventsHelperAdapter->getTourTypesAsArray($objEvent, 'shortcut', false);

            if ('course' === $objEvent->eventType) {
                // KU = Kurs
                $arrEventType[] = 'KU';
            }
            $arrRow['tourType'] = implode(', ', $arrEventType);

            // Add row to $arrTour
            $arrTours[] = $arrRow;
        }

        return \count($arrTours) > 0 ? $arrTours : null;
    }

    /**
     * Helper method.
     */
    protected function getFirstDayOfWeekTimestamp(int $timestamp): int
    {
        /** @var Date $dateAdapter */
        $dateAdapter = $this->get('contao.framework')->getAdapter(Date::class);

        $date = $dateAdapter->parse('d-m-Y', $timestamp);
        $day = \DateTime::createFromFormat('d-m-Y', $date);
        $day->setISODate((int) $day->format('o'), (int) $day->format('W'), 1);

        return $day->getTimestamp();
    }

    /**
     * Helper method.
     */
    protected function getLastDayOfWeekTimestamp(int $timestamp): int
    {
        return $this->getFirstDayOfWeekTimestamp((int) $timestamp) + 6 * 24 * 3600;
    }

    /**
     * Helper method.
     */
    protected function getEventPeriod(CalendarEventsModel $objEvent, string $dateFormat = ''): string
    {
        /** @var Date $dateAdapter */
        $dateAdapter = $this->get('contao.framework')->getAdapter(Date::class);

        /** @var CalendarEventsHelper $calendarEventsHelperAdapter */
        $calendarEventsHelperAdapter = $this->get('contao.framework')->getAdapter(CalendarEventsHelper::class);

        /** @var Config $configAdapter */
        $configAdapter = $this->get('contao.framework')->getAdapter(Config::class);

        /** @var Calendar $calendarAdapter */
        $calendarAdapter = $this->get('contao.framework')->getAdapter(Calendar::class);

        if (empty($dateFormat)) {
            $dateFormat = $configAdapter->get('dateFormat');
        }

        $dateFormatShortened = [];

        if ('d.' === $dateFormat) {
            $dateFormatShortened['from'] = 'd.';
            $dateFormatShortened['to'] = 'd.';
        } elseif ('j.m.' === $dateFormat) {
            $dateFormatShortened['from'] = 'j.';
            $dateFormatShortened['to'] = 'j.m.';
        } elseif ('j.-j. F' === $dateFormat) {
            $dateFormatShortened['from'] = 'j.';
            $dateFormatShortened['to'] = 'j. F';
        } elseif ('D' === $dateFormat) {
            $dateFormatShortened['from'] = 'D';
            $dateFormatShortened['to'] = 'D';
        } else {
            $dateFormatShortened['from'] = 'j.';
            $dateFormatShortened['to'] = 'j.m.';
        }

        $eventDuration = \count($calendarEventsHelperAdapter->getEventTimestamps($objEvent));
        $span = $calendarAdapter->calculateSpan($calendarEventsHelperAdapter->getStartDate($objEvent), $calendarEventsHelperAdapter->getEndDate($objEvent)) + 1;

        if (1 === $eventDuration) {
            return $dateAdapter->parse($dateFormatShortened['to'], $calendarEventsHelperAdapter->getStartDate($objEvent));
        }

        if (2 === $eventDuration && $span !== $eventDuration) {
            return $dateAdapter->parse($dateFormatShortened['from'], $calendarEventsHelperAdapter->getStartDate($objEvent)).' & '.$dateAdapter->parse($dateFormatShortened['to'], $calendarEventsHelperAdapter->getEndDate($objEvent));
        }

        if ($span === $eventDuration) {
            return $dateAdapter->parse($dateFormatShortened['from'], $calendarEventsHelperAdapter->getStartDate($objEvent)).'-'.$dateAdapter->parse($dateFormatShortened['to'], $calendarEventsHelperAdapter->getEndDate($objEvent));
        }

        $arrDates = [];
        $dates = $calendarEventsHelperAdapter->getEventTimestamps($objEvent);

        foreach ($dates as $date) {
            $arrDates[] = $dateAdapter->parse($dateFormatShortened['to'], $date);
        }

        return implode(', ', $arrDates);
    }

    /**
     * Generate course
     * array: $this->events['courses'].
     *
     * @throws \Exception
     */
    protected function generateCourses(): void
    {
        /** @var StringUtil $stringUtilAdapter */
        $stringUtilAdapter = $this->get('contao.framework')->getAdapter(StringUtil::class);

        /** @var CalendarEventsModel $calendarEventsModelAdapter */
        $calendarEventsModelAdapter = $this->get('contao.framework')->getAdapter(CalendarEventsModel::class);

        $arrEvents = [];

        $eventType = 'course';

        $arrAllowedEventTypes = $stringUtilAdapter->deserialize($this->model->print_export_allowedEventTypes, true);

        /** @var Connection $conn */
        $conn = System::getContainer()->get('database_connection');

        // Select statement with 2 subqueries ;-)
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
            ->orderBy('t3.code', 'ASC')
        ;

        /** @var QueryBuilder $qbSub */
        $qbSub = $conn->createQueryBuilder();
        $qbSub->select('id')
            ->from('tl_course_sub_type', 't2')
            ->where($qbSub->expr()->in('t2.pid', $qbSubSub->getSQL()))
            ->orderBy('t2.code', 'ASC')
        ;

        /** @var QueryBuilder $qb */
        $qb = $conn->createQueryBuilder();
        $qb->select('*')
            ->from('tl_calendar_events', 't1')
            ->where('t1.eventType = :eventtype')
            ->andWhere($qb->expr()->in('t1.eventType', ':arrAllowedEventTypes'))
            ->andWhere('t1.startDate >= :startdate')
            ->andWhere('t1.startDate <= :enddate')
            ->andWhere($qb->expr()->in('t1.courseTypeLevel1', $qbSub->getSQL()))
            ->setParameter('eventtype', $eventType)
            ->setParameter('arrAllowedEventTypes', $arrAllowedEventTypes, Connection::PARAM_STR_ARRAY)
            ->setParameter('startdate', $this->startDate)
            ->setParameter('enddate', $this->endDate)
            ->orderBy('t1.courseId')
            ->addOrderBy('t1.startDate', 'ASC')
        ;

        /** @var PDOStatement $results */
        $results = $qb->execute();

        while (false !== ($objEvent = $results->fetch(\PDO::FETCH_OBJ))) {
            $eventModel = $calendarEventsModelAdapter->findByPk($objEvent->id);

            if (null === $eventModel) {
                continue;
            }

            // Check if event is on an enough high level
            if (!$this->hasValidReleaseLevel($eventModel, $this->eventReleaseLevel)) {
                continue;
            }

            // Call helper method
            $arrRow = $this->getEventDetails($eventModel);

            // Headline
            $arrHeadline = [];
            $arrHeadline[] = $this->getEventPeriod($eventModel, 'j.-j. F');
            $arrHeadline[] = $this->getEventPeriod($eventModel, 'D');
            $arrHeadline[] = $eventModel->title;

            if (isset($GLOBALS['TL_CONFIG']['SAC-EVENT-TOOL-CONFIG']['courseLevel'][$eventModel->courseLevel])) {
                $arrHeadline[] = 'Kursstufe '.$GLOBALS['TL_CONFIG']['SAC-EVENT-TOOL-CONFIG']['courseLevel'][$eventModel->courseLevel];
            }

            if ('' !== $eventModel->courseId) {
                $arrHeadline[] = 'Kurs-Nr. '.$eventModel->courseId;
            }
            $arrRow['headline'] = implode(' > ', $arrHeadline);

            // Add record to the collection
            $arrEvents[] = $arrRow;
        }

        $this->events['courses'] = \count($arrEvents) > 0 ? $arrEvents : null;
    }

    /**
     * Generate tours and generalEvents.
     *
     * @throws \Exception
     */
    protected function generateEvents(string $eventType): void
    {
        /** @var CalendarEventsHelper $calendarEventsHelperAdapter */
        $calendarEventsHelperAdapter = $this->get('contao.framework')->getAdapter(CalendarEventsHelper::class);

        /** @var StringUtil $stringUtilAdapter */
        $stringUtilAdapter = $this->get('contao.framework')->getAdapter(StringUtil::class);

        /** @var CalendarEventsModel $calendarEventsModelAdapter */
        $calendarEventsModelAdapter = $this->get('contao.framework')->getAdapter(CalendarEventsModel::class);

        $arrOrganizerContainer = [];

        // Check if event has allowed type
        $arrAllowedEventTypes = $stringUtilAdapter->deserialize($this->model->print_export_allowedEventTypes, true);

        /** @var Connection $conn */
        $conn = System::getContainer()->get('database_connection');

        /** @var QueryBuilder $qbOrganizers */
        $qbOrganizers = $conn->createQueryBuilder();
        $qbOrganizers->select('*')
            ->from('tl_event_organizer', 't2')
            ->orderBy('t2.sorting', 'ASC')
        ;

        /** @var PDOStatement $resultsOrganizers */
        $resultsOrganizers = $qbOrganizers->execute();

        while (false !== ($objOrganizer = $resultsOrganizers->fetch(\PDO::FETCH_OBJ))) {
            $arrOrganizerEvents = [];

            /** @var QueryBuilder $qb */
            $qbEvents = $conn->createQueryBuilder();
            $qbEvents->select('*')
                ->from('tl_calendar_events', 't1')
                ->where('t1.eventType = :eventtype')
                ->andWhere($qbEvents->expr()->in('t1.eventType', ':arrAllowedEventTypes'))
                ->andWhere('t1.startDate >= :startdate')
                ->andWhere('t1.startDate <= :enddate')
                ->setParameter('eventtype', $eventType)
                ->setParameter('arrAllowedEventTypes', $arrAllowedEventTypes, Connection::PARAM_STR_ARRAY)
                ->setParameter('startdate', $this->startDate)
                ->setParameter('enddate', $this->endDate)
                ->orderBy('t1.startDate', 'ASC')
            ;

            /** @var PDOStatement $resultsEvents */
            $resultsEvents = $qbEvents->execute();

            while (false !== ($objEvent = $resultsEvents->fetch(\PDO::FETCH_OBJ))) {
                $eventModel = $calendarEventsModelAdapter->findByPk($objEvent->id);

                if (null === $eventModel) {
                    continue;
                }

                $arrOrganizers = $stringUtilAdapter->deserialize($eventModel->organizers, true);

                if (!\in_array($objOrganizer->id, $arrOrganizers, false)) {
                    continue;
                }

                // Check if event is at least on second highest level (Level 3/4)
                if (!$this->hasValidReleaseLevel($eventModel, $this->eventReleaseLevel)) {
                    continue;
                }

                // Call helper method
                $arrRow = $this->getEventDetails($eventModel);

                // Headline
                $arrHeadline = [];
                $arrHeadline[] = $this->getEventPeriod($eventModel, 'j.-j. F');
                $arrHeadline[] = $this->getEventPeriod($eventModel, 'D');
                $arrHeadline[] = $eventModel->title;
                $strDifficulties = implode(', ', $calendarEventsHelperAdapter->getTourTechDifficultiesAsArray($eventModel));

                if ('' !== $strDifficulties) {
                    $arrHeadline[] = $strDifficulties;
                }
                $arrRow['headline'] = implode(' > ', $arrHeadline);

                // Add row to $arrOrganizerEvents
                $arrOrganizerEvents[] = $arrRow;
            }

            $arrOrganizerContainer[] = [
                'id' => $objOrganizer->id,
                'title' => $objOrganizer->title,
                'events' => $arrOrganizerEvents,
            ];
        }

        $this->events[$eventType.'s'] = $arrOrganizerContainer;
    }

    /**
     * Helper method.
     *
     * @throws \Exception
     */
    private function getEventDetails(CalendarEventsModel $objEvent): array
    {
        /** @var Date $dateAdapter */
        $dateAdapter = $this->get('contao.framework')->getAdapter(Date::class);

        /** @var Environment $environmentAdapter */
        $environmentAdapter = $this->get('contao.framework')->getAdapter(Environment::class);

        /** @var CalendarEventsHelper $calendarEventsHelperAdapter */
        $calendarEventsHelperAdapter = $this->get('contao.framework')->getAdapter(CalendarEventsHelper::class);

        /** @var Events $eventsAdapter */
        $eventsAdapter = $this->get('contao.framework')->getAdapter(Events::class);

        /** @var CalendarEventsJourneyModel $calendarEventsJourneyModelAdapter */
        $calendarEventsJourneyModelAdapter = $this->get('contao.framework')->getAdapter(CalendarEventsJourneyModel::class);

        /** @var Controller $controllerAdapter */
        $controllerAdapter = $this->get('contao.framework')->getAdapter(Controller::class);

        $arrRow = $objEvent->row();
        $arrRow['url'] = $environmentAdapter->get('url').'/'.$eventsAdapter->generateEventUrl($objEvent);

        if ($this->showQrCode) {
            $arrRow['qrCode'] = $calendarEventsHelperAdapter->getEventQrCode($objEvent, ['scale' => 4]);
        }
        $arrRow['eventState'] = '' !== $objEvent->eventState ? $GLOBALS['TL_LANG']['tl_calendar_events'][$objEvent->eventState][0] : '';
        $arrRow['week'] = $dateAdapter->parse('W', $objEvent->startDate);
        $arrRow['eventDates'] = $this->getEventPeriod($objEvent, $this->dateFormat);
        $arrRow['weekday'] = $this->getEventPeriod($objEvent, 'D');
        $arrRow['instructors'] = implode(', ', $calendarEventsHelperAdapter->getInstructorNamesAsArray($objEvent, false, false));
        $arrRow['organizers'] = implode(', ', $calendarEventsHelperAdapter->getEventOrganizersAsArray($objEvent, 'title'));
        $arrRow['tourProfile'] = implode('<br>', $calendarEventsHelperAdapter->getTourProfileAsArray($objEvent));
        $arrRow['journey'] = null !== $calendarEventsJourneyModelAdapter->findByPk($objEvent->journey) ? $calendarEventsJourneyModelAdapter->findByPk($objEvent->journey)->title : '';

        // If registration end time! is set to default --> 23:59 then only show registration end date!
        if ($objEvent->setRegistrationPeriod) {
            $endDate = $dateAdapter->parse('j.m.Y', $objEvent->registrationEndDate);

            if (abs($objEvent->registrationEndDate - strtotime($endDate)) === (24 * 3600) - 60) {
                $formatedEndDate = $dateAdapter->parse('j.m.Y', $objEvent->registrationEndDate);
            } else {
                $formatedEndDate = $dateAdapter->parse('j.m.Y H:i', $objEvent->registrationEndDate);
            }
            $arrRow['registrationPeriod'] = $dateAdapter->parse('j.m.Y', $objEvent->registrationStartDate).' bis '.$formatedEndDate;
        }

        // MinMaxMembers
        $arrMinMaxMembers = [];

        if ($objEvent->addMinAndMaxMembers && $objEvent->minMembers > 0) {
            $arrMinMaxMembers[] = 'min. '.$objEvent->minMembers;
        }

        if ($objEvent->addMinAndMaxMembers && $objEvent->maxMembers > 0) {
            $arrMinMaxMembers[] = 'max. '.$objEvent->maxMembers;
        }
        $arrRow['minMaxMembers'] = implode('/', $arrMinMaxMembers);

        $arrEvents = [];

        foreach ($arrRow as $k => $v) {
            $strValue = nl2br((string) $v);
            // Replace Contao insert tags
            $arrEvents[$k] = $controllerAdapter->replaceInsertTags($strValue);
        }

        return $arrEvents;
    }
}
