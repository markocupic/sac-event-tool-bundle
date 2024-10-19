<?php

declare(strict_types=1);

/*
 * This file is part of SAC Event Tool Bundle.
 *
 * (c) Marko Cupic 2024 <m.cupic@gmx.ch>
 * @license GPL-3.0-or-later
 * For the full copyright and license information,
 * please view the LICENSE file that was distributed with this source code.
 * @link https://github.com/markocupic/sac-event-tool-bundle
 */

namespace Markocupic\SacEventToolBundle\Controller\FrontendModule;

use Codefog\HasteBundle\Form\Form;
use Contao\Calendar;
use Contao\CalendarEventsModel;
use Contao\Config;
use Contao\Controller;
use Contao\CoreBundle\DependencyInjection\Attribute\AsFrontendModule;
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\CoreBundle\Twig\FragmentTemplate;
use Contao\Date;
use Contao\Environment;
use Contao\FrontendTemplate;
use Contao\ModuleModel;
use Contao\PageModel;
use Contao\StringUtil;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Markocupic\SacEventToolBundle\Util\CalendarEventsUtil;
use Markocupic\SacEventToolBundle\Config\EventType;
use Markocupic\SacEventToolBundle\Model\CalendarEventsJourneyModel;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

#[AsFrontendModule(PilatusExportController::TYPE, category:'sac_event_tool_frontend_modules', template:'mod_pilatus_export')]
class PilatusExportController extends AbstractPrintExportController
{
    public const TYPE = 'pilatus_export';
    private const DEFAULT_EVENT_RELEASE_LEVEL = 3;

    private ModuleModel|null $model;
    private Form|null $objForm = null;
    private int|null $startDate = null;
    private int|null $endDate = null;
    private int $eventReleaseLevel = self::DEFAULT_EVENT_RELEASE_LEVEL;
    private array|null $htmlCourseTable = null;
    private array|null $htmlTourTable = null;
    private array $events = [];

    // Editable course fields.
    private array $courseFeEditableFields = ['teaser', 'issues', 'terms', 'requirements', 'equipment', 'leistungen', 'bookingEvent', 'meetingPoint', 'miscellaneous'];

    // Editable tour fields.
    private array $tourFeEditableFields = ['teaser', 'tourDetailText', 'requirements', 'equipment', 'leistungen', 'bookingEvent', 'meetingPoint', 'miscellaneous'];

    public function __construct(
        private readonly Connection $connection,
        private readonly ContaoFramework $framework,
    ) {
        parent::__construct($this->framework);
    }

    public function __invoke(Request $request, ModuleModel $model, string $section, array|null $classes = null, PageModel|null $page = null): Response
    {
        $this->model = $model;

        return parent::__invoke($request, $model, $section, $classes);
    }

    /**
     * @throws \Exception
     */
    protected function getResponse(FragmentTemplate $template, ModuleModel $model, Request $request): Response
    {
        $controllerAdapter = $this->framework->getAdapter(Controller::class);

        // Load language file
        $controllerAdapter->loadLanguageFile('tl_calendar_events');

        // Generate the filter form
        $this->generateForm($request);
        $template->set('form', $this->objForm);

        // Course table
        $objPartial = new FrontendTemplate('mod_pilatus_export_events_table_partial');
        $objPartial->eventTable = $this->htmlCourseTable;
        $objPartial->isCourse = true;
        $template->set('htmlCourseTable', $objPartial->parse());

        // Tour & general event table
        $objPartial = new FrontendTemplate('mod_pilatus_export_events_table_partial');
        $objPartial->eventTable = $this->htmlTourTable;
        $objPartial->isCourse = false;
        $template->set('htmlTourTable', $objPartial->parse());

        // The event array() courses, tours, generalEvents
        $template->set('events', $this->events);

        // Pass editable fields to the template object
        $template->set('courseFeEditableFields', $this->courseFeEditableFields);
        $template->set('tourFeEditableFields', $this->tourFeEditableFields);

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

        $objForm = new Form(
            'form-pilatus-export',
            'GET',
        );

        $objForm->setAction($environmentAdapter->get('uri'));

        $year = (int) date('Y');

        $arrRange = [];
        $arrRange[0] = '---';
        // date('Y-m-t') --> t returns the number of days in the month of a given date
        $arrRange[1] = date('Y-m-01', strtotime(($year - 1).'-07-01')).' - '.$dateAdapter->parse('Y-m-t', strtotime(($year - 1).'-10-01'));
        $arrRange[2] = date('Y-m-01', strtotime(($year - 1).'-10-01')).' - '.$dateAdapter->parse('Y-m-t', strtotime(($year - 1).'-11-01'));
        $arrRange[3] = date('Y-m-01', strtotime(($year - 1).'-12-01')).' - '.$dateAdapter->parse('Y-m-t', strtotime($year.'-04-01'));
        $arrRange[4] = date('Y-m-01', strtotime($year.'-04-01')).' - '.$dateAdapter->parse('Y-m-t', strtotime($year.'-07-01'));
        $arrRange[5] = date('Y-m-01', strtotime($year.'-07-01')).' - '.$dateAdapter->parse('Y-m-t', strtotime($year.'-10-01'));
        $arrRange[6] = date('Y-m-01', strtotime($year.'-10-01')).' - '.$dateAdapter->parse('Y-m-t', strtotime($year.'-11-01'));
        $arrRange[7] = date('Y-m-01', strtotime($year.'-12-01')).' - '.$dateAdapter->parse('Y-m-t', strtotime(($year + 1).'-04-01'));
        $arrRange[8] = date('Y-m-01', strtotime(($year + 1).'-04-01')).' - '.$dateAdapter->parse('Y-m-t', strtotime(($year + 1).'-07-01'));
        $arrRange[9] = date('Y-m-01', strtotime(($year + 1).'-07-01')).' - '.$dateAdapter->parse('Y-m-t', strtotime(($year + 1).'-10-01'));
        $arrRange[10] = date('Y-m-01', strtotime(($year + 1).'-10-01')).' - '.$dateAdapter->parse('Y-m-t', strtotime(($year + 1).'-11-01'));

        $range = [];

        foreach ($arrRange as $strRange) {
            $key = str_replace(' - ', '|', $strRange);
            $range[$key] = $strRange;
        }

        $strDateTimeRangeStart = '';

        if ($request->query->has('timeRangeStart')) {
            $tstamp = strtotime($request->query->get('timeRangeStart'));
            $strDateTimeRangeStart = false !== $tstamp ? $request->query->has('timeRangeStart') : '';
        }

        $strDateTimeRangeEnd = '';

        if ($request->query->has('timeRangeEnd')) {
            $tstamp = strtotime($request->query->get('timeRangeEnd'));
            $strDateTimeRangeEnd = false !== $tstamp ? $request->query->has('timeRangeEnd') : '';
        }

        // Now let's add the form fields:
        $objForm->addFormField('timeRange', [
            'label' => 'Zeitspanne (fixe Zeitspanne)',
            'inputType' => 'select',
            'options' => $range,
            'eval' => ['mandatory' => false],
        ]);

        $objForm->addFormField('timeRangeStart', [
            'label' => ['Zeitspanne manuelle Eingabe (Startdatum)'],
            'inputType' => 'text',
            'eval' => ['mandatory' => false, 'maxlength' => '10', 'minlength' => 10, 'rgxp' => 'date', 'placeholder' => 'dd.mm.YYYY'],
            'value' => $strDateTimeRangeStart,
        ]);

        $objForm->addFormField('timeRangeEnd', [
            'label' => 'Zeitspanne manuelle Eingabe (Enddatum)',
            'inputType' => 'text',
            'eval' => ['mandatory' => false, 'maxlength' => '10', 'minlength' => 10, 'rgxp' => 'date', 'placeholder' => 'dd.mm.YYYY'],
            'value' => $strDateTimeRangeEnd,
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
            if ($request->query->get('timeRange') && '---' !== $request->query->get('timeRange')) {
                $arrRange = explode('|', $request->query->get('timeRange'));
                $this->startDate = strtotime($arrRange[0]);
                $this->endDate = strtotime($arrRange[1]);
                $objForm->getWidget('timeRange')->value = $request->query->get('timeRange');
                $objForm->getWidget('timeRangeStart')->value = '';
                $objForm->getWidget('timeRangeEnd')->value = '';
            }
            // If the user has set the start & end date manually
            elseif (null !== $request->query->get('timeRangeStart') || null !== $request->query->get('timeRangeEnd')) {
                $addError = false;

                if (null === $request->query->get('timeRangeStart') || null === $request->query->get('timeRangeEnd')) {
                    $addError = true;
                } elseif (false !== strtotime($request->query->get('timeRangeStart')) && false !== strtotime($request->query->get('timeRangeStart'))) {
                    $objWidgetStart = $objForm->getWidget('timeRangeStart');
                    $objWidgetEnd = $objForm->getWidget('timeRangeEnd');

                    // Set the value from GET
                    $objWidgetStart->value = $request->query->get('timeRangeStart');
                    $objWidgetEnd->value = $request->query->get('timeRangeEnd');

                    // Get the timestamps
                    $tstampStart = strtotime($request->query->get('timeRangeStart'));
                    $tstampEnd = strtotime($request->query->get('timeRangeEnd'));

                    if ($tstampStart > $tstampEnd) {
                        $addError = true;
                    } else {
                        $this->startDate = $tstampStart;
                        $this->endDate = $tstampEnd;
                        $request->query->set('timeRange', '');
                        $objForm->getWidget('timeRange')->value = '';
                    }
                }

                if ($addError) {
                    $strError = 'Ungültige Datumseingabe. Das Datum muss im Format \'dd.mm.YYYY\' eingegeben werden, und das Startdatum muss zeitlich vor dem Enddatum liegen.';
                    $objForm->getWidget('timeRangeStart')->addError($strError);
                    $objForm->getWidget('timeRangeEnd')->addError($strError);
                }
            }

            if ($this->startDate && $this->endDate) {
                $this->eventReleaseLevel = empty($request->query->get('eventReleaseLevel')) ? $this->eventReleaseLevel : (int) $request->query->get('eventReleaseLevel');
                $this->htmlCourseTable = $this->generateEventTable([EventType::COURSE]);
                $this->htmlTourTable = $this->generateEventTable([EventType::TOUR, EventType::GENERAL_EVENT]);
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
        $calendarEventsUtilAdapter = $this->framework->getAdapter(CalendarEventsUtil::class);
        $calendarEventsJourneyModelAdapter = $this->framework->getAdapter(CalendarEventsJourneyModel::class);
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
            ->setParameter('eventtypes', $arrAllowedEventType, ArrayParameterType::STRING)
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
            $arrRow['title'] = $objEvent->title.(EventType::LAST_MINUTE_TOUR === $objEvent->eventType ? ' (LAST MINUTE TOUR!)' : '');
            $arrRow['instructors'] = implode(', ', $calendarEventsUtilAdapter->getInstructorNamesAsArray($objEvent, false, true));
            $arrRow['organizers'] = implode(', ', $calendarEventsUtilAdapter->getEventOrganizersAsArray($objEvent, 'titlePrint'));
            $arrRow['eventId'] = date('Y', (int) $objEvent->startDate).'-'.$objEvent->id;
            $arrRow['journey'] = null !== $calendarEventsJourneyModelAdapter->findByPk($objEvent->journey) ? $calendarEventsJourneyModelAdapter->findByPk($objEvent->journey)->title : null;

            if (EventType::COURSE === $objEvent->eventType) {
                $arrRow['eventId'] = $objEvent->courseId;
            }

            // tourType
            $arrEventType = $calendarEventsUtilAdapter->getTourTypesAsArray($objEvent, 'shortcut', false);

            if (EventType::COURSE === $objEvent->eventType) {
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
        $calendarEventsUtilAdapter = $this->framework->getAdapter(CalendarEventsUtil::class);
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

        $eventDuration = \count($calendarEventsUtilAdapter->getEventTimestamps($objEvent));
        // !!! Type casting is necessary here
        $span = (int) $calendarAdapter->calculateSpan($calendarEventsUtilAdapter->getStartDate($objEvent), $calendarEventsUtilAdapter->getEndDate($objEvent)) + 1;

        if (1 === $eventDuration) {
            return $dateAdapter->parse($dateFormatShortened['to'], $calendarEventsUtilAdapter->getStartDate($objEvent));
        }

        if (2 === $eventDuration && $span !== $eventDuration) {
            return $dateAdapter->parse($dateFormatShortened['from'], $calendarEventsUtilAdapter->getStartDate($objEvent)).' & '.$dateAdapter->parse($dateFormatShortened['to'], $calendarEventsUtilAdapter->getEndDate($objEvent));
        }

        if ($span === $eventDuration) {
            return $dateAdapter->parse($dateFormatShortened['from'], $calendarEventsUtilAdapter->getStartDate($objEvent)).'-'.$dateAdapter->parse($dateFormatShortened['to'], $calendarEventsUtilAdapter->getEndDate($objEvent));
        }

        $arrDates = [];
        $dates = $calendarEventsUtilAdapter->getEventTimestamps($objEvent);

        foreach ($dates as $date) {
            $arrDates[] = $dateAdapter->parse($dateFormatShortened['to'], $date);
        }

        return implode(', ', $arrDates);
    }
}
