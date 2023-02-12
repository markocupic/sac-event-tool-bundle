<?php

declare(strict_types=1);

/*
 * This file is part of SAC Event Tool Bundle.
 *
 * (c) Marko Cupic 2023 <m.cupic@gmx.ch>
 * @license GPL-3.0-or-later
 * For the full copyright and license information,
 * please view the LICENSE file that was distributed with this source code.
 * @link https://github.com/markocupic/sac-event-tool-bundle
 */

namespace Markocupic\SacEventToolBundle\Controller\FrontendModule;

use Contao\Calendar;
use Contao\CalendarEventsModel;
use Contao\Config;
use Contao\Controller;
use Contao\CoreBundle\DependencyInjection\Attribute\AsFrontendModule;
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\CoreBundle\InsertTag\InsertTagParser;
use Contao\Date;
use Contao\Environment;
use Contao\Events;
use Contao\FrontendTemplate;
use Contao\ModuleModel;
use Contao\PageModel;
use Contao\StringUtil;
use Contao\Template;
use Contao\Validator;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Query\QueryBuilder;
use Haste\Form\Form;
use Markocupic\SacEventToolBundle\CalendarEventsHelper;
use Markocupic\SacEventToolBundle\Model\CalendarEventsJourneyModel;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

#[AsFrontendModule(PilatusExport2021Controller::TYPE, category:'sac_event_tool_frontend_modules', template:'mod_pilatus_export_2021')]
class PilatusExport2021Controller extends AbstractPrintExportController
{
    public const TYPE = 'pilatus_export_2021';

    private ModuleModel|null $model;
    private Form|null $objForm = null;
    private int|null $startDate = null;
    private int|null $endDate = null;
    private int|null $eventReleaseLevel = null;
    private string $dateFormat = 'j.';
    private array|null $htmlCourseTable = null;
    private array|null $htmlTourTable = null;
    private array $events = [];

    // Editable course fields.
    private array $courseFeEditableFields = ['teaser', 'issues', 'terms', 'requirements', 'equipment', 'leistungen', 'bookingEvent', 'meetingPoint', 'miscellaneous'];

    // Editable tour fields.
    private array $tourFeEditableFields = ['teaser', 'tourDetailText', 'requirements', 'equipment', 'leistungen', 'bookingEvent', 'meetingPoint', 'miscellaneous'];

    public function __construct(
        private readonly ContaoFramework $framework,
        private readonly Connection $connection,
        private readonly InsertTagParser $insertTagParser,
    ) {
        parent::__construct($this->framework);
    }

    public function __invoke(Request $request, ModuleModel $model, string $section, array $classes = null, PageModel $page = null): Response
    {
        $this->model = $model;

        return parent::__invoke($request, $model, $section, $classes);
    }

    /**
     * @throws \Exception
     */
    protected function getResponse(Template $template, ModuleModel $model, Request $request): Response|null
    {
        $controllerAdapter = $this->framework->getAdapter(Controller::class);

        // Load language file
        $controllerAdapter->loadLanguageFile('tl_calendar_events');

        // Generate the filter form
        $this->generateForm($request);
        $template->form = $this->objForm;

        // Course table
        $objPartial = new FrontendTemplate('mod_pilatus_export_2021_events_table_partial');
        $objPartial->eventTable = $this->htmlCourseTable;
        $objPartial->isCourse = true;
        $template->htmlCourseTable = $objPartial->parse();

        // Tour & general event table
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
     * Generate the form.
     *
     * @throws \Exception
     */
    private function generateForm(Request $request): void
    {
        $dateAdapter = $this->framework->getAdapter(Date::class);
        $environmentAdapter = $this->framework->getAdapter(Environment::class);
        $validatorAdapter = $this->framework->getAdapter(Validator::class);

        $objForm = new Form(
            'form-pilatus-export',
            'POST',
            static fn (Form $objHaste): bool => $request->request->get('FORM_SUBMIT') === $objHaste->getFormId()
        );
        $objForm->setFormActionFromUri($environmentAdapter->get('uri'));

        $year = (int) date('Y');

        $arrRange = [];
        $arrRange[0] = '---';
        $arrRange[1] = date('Y-m-01', strtotime(($year - 1).'-10-01')).' - '.$dateAdapter->parse('Y-m-t', strtotime(($year - 1).'-12-01'));
        $arrRange[2] = date('Y-m-01', strtotime($year.'-01-01')).' - '.$dateAdapter->parse('Y-m-t', strtotime($year.'-03-01'));
        $arrRange[3] = date('Y-m-01', strtotime($year.'-04-01')).' - '.$dateAdapter->parse('Y-m-t', strtotime($year.'-06-01'));
        $arrRange[4] = date('Y-m-01', strtotime($year.'-07-01')).' - '.$dateAdapter->parse('Y-m-t', strtotime($year.'-09-01'));
        $arrRange[5] = date('Y-m-01', strtotime($year.'-10-01')).' - '.$dateAdapter->parse('Y-m-t', strtotime($year.'-12-01'));
        $arrRange[6] = date('Y-m-01', strtotime(($year + 1).'-01-01')).' - '.$dateAdapter->parse('Y-m-t', strtotime(($year + 1).'-03-01'));

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

        $objForm->addFormField('submit', [
            'label' => 'Export starten',
            'inputType' => 'submit',
        ]);

        // validate() also checks whether the form has been submitted
        if ($objForm->validate()) {
            // User has selected a predefined time range
            if ($request->request->get('timeRange') && '---' !== $request->request->get('timeRange')) {
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

            if ($this->startDate && $this->endDate) {
                $this->eventReleaseLevel = (int) $request->request->get('eventReleaseLevel') > 0 ? (int) $request->request->get('eventReleaseLevel') : null;
                $this->htmlCourseTable = $this->generateEventTable(['course']);
                $this->htmlTourTable = $this->generateEventTable(['tour', 'generalEvent']);
            }
        }

        $this->objForm = $objForm;
    }

    /**
     * @throws \Exception
     */
    private function generateEventTable(array $arrAllowedEventType): array|null
    {
        $dateAdapter = $this->framework->getAdapter(Date::class);
        $calendarEventsHelperAdapter = $this->framework->getAdapter(CalendarEventsHelper::class);
        $calendarEventsHelperJourneyModelAdapter = $this->framework->getAdapter(CalendarEventsJourneyModel::class);
        $stringUtilAdapter = $this->framework->getAdapter(StringUtil::class);
        $calendarEventsModelAdapter = $this->framework->getAdapter(CalendarEventsModel::class);

        $arrTours = [];

        $qb = $this->connection->createQueryBuilder();
        $qb->select('*')
            ->from('tl_calendar_events', 't')
            ->where('t.startDate >= :startdate')
            ->andWhere('t.startDate <= :enddate')
            ->andWhere($qb->expr()->in('t.eventType', ':eventtypes'))
            ->setParameter('startdate', $this->startDate)
            ->setParameter('enddate', $this->endDate)
            ->setParameter('eventtypes', $arrAllowedEventType, ArrayParameterType::INTEGER)
            ->addOrderBy('t.startDate', 'ASC')
        ;

        $results = $qb->executeQuery();

        while (false !== ($arrEvent = $results->fetchAssociative())) {
            $objEvent = $calendarEventsModelAdapter->findByPk($arrEvent['id']);

            if (null === $objEvent) {
                continue;
            }

            // Check if event has allowed type
            $arrAllowedEventTypes = $stringUtilAdapter->deserialize($this->model->print_export_allowedEventTypes, true);

            if (!\in_array($objEvent->eventType, $arrAllowedEventTypes, false)) {
                continue;
            }

            // Check if event is at least on second-highest level (Level 3/4)
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
            $arrRow['eventId'] = date('Y', (int) $objEvent->startDate).'-'.$objEvent->id;
            $arrRow['journey'] = null !== $calendarEventsHelperJourneyModelAdapter->findByPk($objEvent->journey) ? $calendarEventsHelperJourneyModelAdapter->findByPk($objEvent->journey)->title : null;

            if ('course' === $objEvent->eventType) {
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
    private function getFirstDayOfWeekTimestamp(int $timestamp): int
    {
        $dateAdapter = $this->framework->getAdapter(Date::class);

        $date = $dateAdapter->parse('d-m-Y', $timestamp);
        $day = \DateTime::createFromFormat('d-m-Y', $date);
        $day->setISODate((int) $day->format('o'), (int) $day->format('W'), 1);

        return $day->getTimestamp();
    }

    /**
     * Helper method.
     */
    private function getLastDayOfWeekTimestamp(int $timestamp): int
    {
        return $this->getFirstDayOfWeekTimestamp($timestamp) + 6 * 24 * 3600;
    }

    /**
     * Helper method.
     */
    private function getEventPeriod(CalendarEventsModel $objEvent, string $dateFormat = ''): string
    {
        $dateAdapter = $this->framework->getAdapter(Date::class);
        $calendarEventsHelperAdapter = $this->framework->getAdapter(CalendarEventsHelper::class);
        $configAdapter = $this->framework->getAdapter(Config::class);
        $calendarAdapter = $this->framework->getAdapter(Calendar::class);

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
        // !!! Type casting is necessary here
        $span = (int) $calendarAdapter->calculateSpan($calendarEventsHelperAdapter->getStartDate($objEvent), $calendarEventsHelperAdapter->getEndDate($objEvent)) + 1;

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
    private function generateCourses(): void
    {
        $stringUtilAdapter = $this->framework->getAdapter(StringUtil::class);
        $calendarEventsModelAdapter = $this->framework->getAdapter(CalendarEventsModel::class);

        $arrEvents = [];

        $eventType = 'course';

        $arrAllowedEventTypes = $stringUtilAdapter->deserialize($this->model->print_export_allowedEventTypes, true);

        // Select statement with 2 subqueries ;-)
        // SELECT * FROM tl_calendar_events t1 WHERE
        // (t1.eventType = :eventtype) AND
        // (t1.startDate >= :startdate) AND
        // (t1.startDate <= :enddate) AND
        // (t1.courseTypeLevel1 IN (SELECT id FROM tl_course_sub_type t2 WHERE t2.pid IN (SELECT id FROM tl_course_main_type t3 ORDER BY t3.code ASC) ORDER BY t2.code ASC))
        // ORDER BY t1.courseId ASC, t1.startDate ASC

        $qbSubSub = $this->connection->createQueryBuilder();
        $qbSubSub->select('id')
            ->from('tl_course_main_type', 't3')
            ->orderBy('t3.code', 'ASC')
        ;

        $qbSub = $this->connection->createQueryBuilder();
        $qbSub->select('id')
            ->from('tl_course_sub_type', 't2')
            ->where($qbSub->expr()->in('t2.pid', $qbSubSub->getSQL()))
            ->orderBy('t2.code', 'ASC')
        ;

        $qb = $this->connection->createQueryBuilder();
        $qb->select('id')
            ->from('tl_calendar_events', 't1')
            ->where('t1.eventType = :eventtype')
            ->andWhere($qb->expr()->in('t1.eventType', ':arrAllowedEventTypes'))
            ->andWhere('t1.startDate >= :startdate')
            ->andWhere('t1.startDate <= :enddate')
            ->andWhere($qb->expr()->in('t1.courseTypeLevel1', $qbSub->getSQL()))
            ->setParameter('eventtype', $eventType)
            ->setParameter('arrAllowedEventTypes', $arrAllowedEventTypes, ArrayParameterType::INTEGER)
            ->setParameter('startdate', $this->startDate)
            ->setParameter('enddate', $this->endDate)
            ->orderBy('t1.courseId')
            ->addOrderBy('t1.startDate', 'ASC')
        ;

        $results = $qb->executeQuery();

        while (false !== ($eventId = $results->fetchOne())) {
            $eventModel = $calendarEventsModelAdapter->findByPk($eventId);

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
    private function generateEvents(string $eventType): void
    {
        $calendarEventsHelperAdapter = $this->framework->getAdapter(CalendarEventsHelper::class);
        $stringUtilAdapter = $this->framework->getAdapter(StringUtil::class);
        $calendarEventsModelAdapter = $this->framework->getAdapter(CalendarEventsModel::class);

        $arrOrganizerContainer = [];

        // Check if event has allowed type
        $arrAllowedEventTypes = $stringUtilAdapter->deserialize($this->model->print_export_allowedEventTypes, true);

        $qbOrganizers = $this->connection->createQueryBuilder();
        $qbOrganizers->select('*')
            ->from('tl_event_organizer', 't2')
            ->orderBy('t2.sorting', 'ASC')
        ;

        $resultsOrganizers = $qbOrganizers->executeQuery();

        while (false !== ($arrOrganizer = $resultsOrganizers->fetchAssociative())) {
            $arrOrganizerEvents = [];

            /** @var QueryBuilder $qb */
            $qbEvents = $this->connection->createQueryBuilder();
            $qbEvents->select('id')
                ->from('tl_calendar_events', 't1')
                ->where('t1.eventType = :eventtype')
                ->andWhere($qbEvents->expr()->in('t1.eventType', ':arrAllowedEventTypes'))
                ->andWhere('t1.startDate >= :startdate')
                ->andWhere('t1.startDate <= :enddate')
                ->setParameter('eventtype', $eventType)
                ->setParameter('arrAllowedEventTypes', $arrAllowedEventTypes, ArrayParameterType::INTEGER)
                ->setParameter('startdate', $this->startDate)
                ->setParameter('enddate', $this->endDate)
                ->orderBy('t1.startDate', 'ASC')
            ;

            $resultsEvents = $qbEvents->executeQuery();

            while (false !== ($eventId = $resultsEvents->fetchOne())) {
                $eventModel = $calendarEventsModelAdapter->findByPk($eventId);

                if (null === $eventModel) {
                    continue;
                }

                $arrOrganizers = $stringUtilAdapter->deserialize($eventModel->organizers, true);

                if (!\in_array($arrOrganizer['id'], $arrOrganizers, false)) {
                    continue;
                }

                // Check if event is at least on second-highest level (Level 3/4)
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
                'id' => $arrOrganizer['id'],
                'title' => $arrOrganizer['title'],
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
        $dateAdapter = $this->framework->getAdapter(Date::class);
        $environmentAdapter = $this->framework->getAdapter(Environment::class);
        $calendarEventsHelperAdapter = $this->framework->getAdapter(CalendarEventsHelper::class);
        $eventsAdapter = $this->framework->getAdapter(Events::class);
        $calendarEventsJourneyModelAdapter = $this->framework->getAdapter(CalendarEventsJourneyModel::class);

        $arrRow = $objEvent->row();
        $arrRow['url'] = $environmentAdapter->get('url').'/'.$eventsAdapter->generateEventUrl($objEvent);
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
            $arrEvents[$k] = $this->insertTagParser->replaceInline($strValue);
        }

        return $arrEvents;
    }
}
