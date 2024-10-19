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
use Contao\Database;
use Contao\Date;
use Contao\Environment;
use Contao\ModuleModel;
use Contao\PageModel;
use Contao\StringUtil;
use Markocupic\SacEventToolBundle\Util\CalendarEventsUtil;
use Markocupic\SacEventToolBundle\Config\CourseLevels;
use Markocupic\SacEventToolBundle\Config\EventType;
use Markocupic\SacEventToolBundle\Model\CourseMainTypeModel;
use Markocupic\SacEventToolBundle\Model\CourseSubTypeModel;
use Markocupic\SacEventToolBundle\Model\EventOrganizerModel;
use Markocupic\SacEventToolBundle\Model\UserRoleModel;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;

#[AsFrontendModule(JahresprogrammExportController::TYPE, category:'sac_event_tool_frontend_modules', template:'mod_jahresprogramm_export')]
class JahresprogrammExportController extends AbstractPrintExportController
{
    public const TYPE = 'jahresprogramm_export';
    private const DEFAULT_EVENT_RELEASE_LEVEL = 3;

    private FragmentTemplate|null $template = null;
    private int|null $startDate = null;
    private int|null $endDate = null;
    private int|null $organizer = null;
    private string|null $eventType = null;
    private int $eventReleaseLevel = self::DEFAULT_EVENT_RELEASE_LEVEL;
    private array|null $events = null;
    private array|null $instructors = null;

    public function __construct(
        private readonly ContaoFramework $framework,
        private readonly CourseLevels $courseLevels,
        private readonly RequestStack $requestStack,
    ) {
        parent::__construct($framework);
    }

    public function __invoke(Request $request, ModuleModel $model, string $section, array|null $classes = null, PageModel|null $page = null): Response
    {
        return parent::__invoke($request, $model, $section, $classes);
    }

    /**
     * @throws \Exception
     */
    protected function getResponse(FragmentTemplate $template, ModuleModel $model, Request $request): Response
    {
        $this->template = $template;

        $controllerAdapter = $this->framework->getAdapter(Controller::class);

        // Load language file
        $controllerAdapter->loadLanguageFile('tl_calendar_events');

        $template->set('form', $this->generateForm($request));

        return $this->template->getResponse();
    }

    /**
     * @throws \Exception
     */
    private function generateForm(Request $request): Form
    {
        $eventOrganizerModelAdapter = $this->framework->getAdapter(EventOrganizerModel::class);
        $environmentAdapter = $this->framework->getAdapter(Environment::class);
        $databaseAdapter = $this->framework->getAdapter(Database::class);

        $objForm = new Form(
            'form-jahresprogramm-export',
            'POST',
        );

        $objForm->setAction($environmentAdapter->get('uri'));

        // Now let's add form fields:
        $objForm->addFormField('minEventDuration', [
            'label' => 'Event-Mindestdauer',
            'inputType' => 'select',
            'options' => [4 => 'min. 4 Tage', 3 => 'min. 3 Tage', 2 => 'min. 2 Tage', 1 => 'min. 1 Tag (alle)'], // Do only list events with a duration of min. 4 days (default)
            'eval' => ['includeBlankOption' => false, 'mandatory' => true],
        ]);

        $objForm->addFormField('eventType', [
            'label' => 'Event-Typ',
            'reference' => $GLOBALS['TL_LANG']['MSC'],
            'inputType' => 'select',
            'options' => EventType::ALL,
            'eval' => ['includeBlankOption' => true, 'mandatory' => true],
        ]);

        $arrOrganizers = [];
        $objOrganizer = $databaseAdapter->getInstance()->prepare('SELECT * FROM tl_event_organizer ORDER BY sorting')->execute();

        while ($objOrganizer->next()) {
            $arrOrganizers[$objOrganizer->id] = $objOrganizer->title;
        }

        $objForm->addFormField('organizer', [
            'label' => 'Organisierende Gruppe',
            'inputType' => 'select',
            'options' => $arrOrganizers,
            'eval' => ['includeBlankOption' => true, 'mandatory' => false],
        ]);

        $objForm->addFormField('startDate', [
            'label' => 'Startdatum',
            'inputType' => 'text',
            'eval' => ['rgxp' => 'date', 'mandatory' => true],
        ]);

        $objForm->addFormField('endDate', [
            'label' => 'Enddatum',
            'inputType' => 'text',
            'eval' => ['rgxp' => 'date', 'mandatory' => true],
        ]);

        $objForm->addFormField('eventReleaseLevel', [
            'label' => 'Zeige an ab Freigabestufe (Wenn leer gelassen, wird ab 2. höchster FS gelistet!)',
            'inputType' => 'select',
            'options' => [1 => 'FS1', 2 => 'FS2', 3 => 'FS3', 4 => 'FS4'],
            'eval' => ['mandatory' => false, 'includeBlankOption' => true],
        ]);

        $arrUserRoles = [];
        $objUserRoles = $databaseAdapter->getInstance()->prepare('SELECT * FROM tl_user_role ORDER BY title')->execute();

        while ($objUserRoles->next()) {
            $arrUserRoles[$objUserRoles->id] = $objUserRoles->title;
        }

        $objForm->addFormField('userRoles', [
            'label' => 'Neben den Event-Leitern zusätzliche Funktionäre anzeigen',
            'inputType' => 'select',
            'options' => $arrUserRoles,
            'eval' => ['multiple' => true, 'mandatory' => false],
        ]);

        // Let's add  a submit button
        $objForm->addFormField('submit', [
            'label' => 'Export starten',
            'inputType' => 'submit',
        ]);

        // validate() also checks whether the form has been submitted
        if ($objForm->validate()) {
            if ($request->request->get('startDate') && $request->request->get('endDate') && $request->request->get('eventType')) {
                $this->startDate = strtotime($request->request->get('startDate'));
                $this->endDate = strtotime($request->request->get('endDate'));
                $this->eventType = $request->request->get('eventType');
                $this->organizer = $request->request->get('organizer') > 0 ? (int) $request->request->get('organizer') : null;

                $this->eventReleaseLevel = empty($request->request->get('eventReleaseLevel')) ? $this->eventReleaseLevel : (int) $request->request->get('eventReleaseLevel');

                // Get events and instructors (fill $this->events and $this->instructors)
                $this->getEventsAndInstructors($request);

                $this->template->eventType = $this->eventType;
                $this->template->eventTypeLabel = $GLOBALS['TL_LANG']['MSC'][$this->eventType];
                $this->template->startDate = $this->startDate;
                $this->template->endDate = $this->endDate;
                $this->template->organizer = $this->organizer > 0 ? $eventOrganizerModelAdapter->findByPk($this->organizer)->title : 'Alle Gruppen';
                $this->template->events = $this->events;
                $this->template->instructors = $this->instructors;

                $arrayUserRoles = empty($request->request->all()['userRoles']) ? [] : $request->request->all()['userRoles'];
                $specialUsers = $this->getUsersByUserRole($arrayUserRoles);
                $this->template->specialUsers = $specialUsers;
            }
        }

        return $objForm;
    }

    /**
     * @throws \Exception
     */
    private function getEventsAndInstructors(Request $request): void
    {
        $stringUtilAdapter = $this->framework->getAdapter(StringUtil::class);
        $dateAdapter = $this->framework->getAdapter(Date::class);
        $courseMainTypeModelAdapter = $this->framework->getAdapter(CourseMainTypeModel::class);
        $courseSubTypeModelAdapter = $this->framework->getAdapter(CourseSubTypeModel::class);
        $calendarEventsModelAdapter = $this->framework->getAdapter(CalendarEventsModel::class);
        $calendarEventsUtilAdapter = $this->framework->getAdapter(CalendarEventsUtil::class);
        $databaseAdapter = $this->framework->getAdapter(Database::class);
        $eventOrganizerModelAdapter = $this->framework->getAdapter(EventOrganizerModel::class);

        $events = [];
        $objEvents = $databaseAdapter->getInstance()->prepare('SELECT * FROM tl_calendar_events WHERE startDate>=? AND startDate<=?')->execute($this->startDate, $this->endDate);

        while ($objEvents->next()) {
            // Check if event is at least on second-highest level (Level 3/4)
            $eventModel = $calendarEventsModelAdapter->findByPk($objEvents->id);

            $arrTimestamps = $calendarEventsUtilAdapter->getEventTimestamps($eventModel);

            // Filter events by event duration
            $minDurationInDays = (int) $request->request->get('minEventDuration');

            // Do only list events with a duration of at least 4 days (default)!
            if ($minDurationInDays > \count($arrTimestamps)) {
                continue;
            }

            if (!$this->hasValidReleaseLevel($eventModel, $this->eventReleaseLevel)) {
                continue;
            }

            if ($this->organizer) {
                $arrOrganizer = $stringUtilAdapter->deserialize($objEvents->organizers, true);

                if (!\in_array($this->organizer, $arrOrganizer, false)) {
                    continue;
                }
            }

            if ($this->eventType !== $objEvents->eventType) {
                continue;
            }

            $events[] = (int) ($objEvents->id);
        }

        $arrInstructors = [];

        if (\count($events) > 0) {
            $arrEvents = [];

            // Let's use different queries for each event type
            if (EventType::COURSE === $this->eventType) {
                $objEvent = CalendarEventsModel::findMultipleByIds($events, ['order' => 'tl_calendar_events.courseTypeLevel0, tl_calendar_events.courseTypeLevel1, tl_calendar_events.startDate, tl_calendar_events.endDate, tl_calendar_events.courseId']);
            } else {
                $objEvent = CalendarEventsModel::findMultipleByIds($events, ['order' => 'tl_calendar_events.startDate, tl_calendar_events.endDate']);
            }

            if (null !== $objEvent) {
                while ($objEvent->next()) {
                    $arrInstructors = array_merge($arrInstructors, $calendarEventsUtilAdapter->getInstructorsAsArray($objEvent->current()));

                    // tourType && date format
                    $arrTourType = $calendarEventsUtilAdapter->getTourTypesAsArray($objEvent->current(), 'shortcut', false);
                    $dateFormat = 'D, j.';

                    if (EventType::COURSE === $objEvent->eventType) {
                        // KU = Kurs
                        $arrTourType[] = 'KU';
                        //$dateFormat = 'j.n.';
                        $dateFormat = 'D, j.n.';
                    }
                    $showHeadline = true;
                    $showTeaser = true;
                    $showDetails = true;

                    // Details
                    $minMax = [];

                    if ($objEvent->minMembers) {
                        $minMax[] = 'min. '.$objEvent->minMembers;
                    }

                    if ($objEvent->maxMembers) {
                        $minMax[] = 'max. '.$objEvent->maxMembers;
                    }

                    if ($this->organizer) {
                        if (null !== ($eventOrganizerModel = $eventOrganizerModelAdapter->findByPk($this->organizer))) {
                            $showHeadline = (bool) $eventOrganizerModel->annualProgramShowHeadline;
                            $showTeaser = (bool) $eventOrganizerModel->annualProgramShowTeaser;
                            $showDetails = (bool) $eventOrganizerModel->annualProgramShowDetails;
                        }
                    }

                    $arrTitle = [];
                    $arrTitlePrint = [];

                    if ('' !== $objEvent->organizers) {
                        $arrOrganizers = $stringUtilAdapter->deserialize($objEvent->organizers, true);

                        foreach ($arrOrganizers as $orgId) {
                            $arrTitle[] = $eventOrganizerModelAdapter->findByPk($orgId)->title;
                            $arrTitlePrint[] = $eventOrganizerModelAdapter->findByPk($orgId)->titlePrint;
                        }
                    }
                    $organizerTitle = implode(', ', $arrTitle);
                    $organizerTitlePrint = implode(', ', $arrTitlePrint);

                    $arrData = $objEvent->row();

                    $arrData['eventId'] = $calendarEventsUtilAdapter->getEventData($objEvent->current(), 'eventId');
                    $arrData['organizers'] = implode(', ', $calendarEventsUtilAdapter->getEventOrganizersAsArray($objEvent->current(), 'title'));
                    $arrData['organizerTitle'] = $organizerTitle;
                    $arrData['organizerTitlePrint'] = $organizerTitlePrint;
                    $arrData['courseLevel'] = $objEvent->courseLevel ? $this->courseLevels->get($objEvent->courseLevel) : '';
                    $arrData['courseTypeLevel0'] = null !== $courseMainTypeModelAdapter->findByPk($objEvent->courseTypeLevel0) ? $courseMainTypeModelAdapter->findByPk($objEvent->courseTypeLevel0)->name : '';
                    $arrData['courseTypeLevel1'] = null !== $courseSubTypeModelAdapter->findByPk($objEvent->courseTypeLevel1) ? $courseSubTypeModelAdapter->findByPk($objEvent->courseTypeLevel1)->name : '';
                    $arrData['date'] = $this->getEventPeriod($objEvent->current(), $dateFormat);
                    $arrData['month'] = $dateAdapter->parse('F', $objEvent->startDate);
                    $arrData['instructors'] = implode(', ', $calendarEventsUtilAdapter->getInstructorNamesAsArray($objEvent->current(), false, true));
                    $arrData['tourType'] = implode(', ', $arrTourType);
                    $arrData['difficulty'] = implode(', ', $calendarEventsUtilAdapter->getTourTechDifficultiesAsArray($objEvent->current()));
                    // Layout settings
                    $arrData['showHeadline'] = $showHeadline;
                    $arrData['showTeaser'] = $showTeaser;
                    $arrData['showDetails'] = $showDetails;

                    // Details
                    $arrData['arrTourProfile'] = $calendarEventsUtilAdapter->getEventData($objEvent->current(), 'arrTourProfile');
                    $arrData['journey'] = $calendarEventsUtilAdapter->getEventData($objEvent->current(), 'journey');
                    $arrData['minMaxMembers'] = implode('/', $minMax);

                    $arrData['bookingInfo'] = 'Event-Nummer '.$calendarEventsUtilAdapter->getEventData($objEvent->current(), 'eventId');

                    if (EventType::COURSE === $objEvent->eventType) {
                        $arrData['bookingInfo'] = 'Kurs-Nummer '.$calendarEventsUtilAdapter->getEventData($objEvent->current(), 'courseId');
                    }
                    $arrEvents[] = $arrData;
                }
            }

            $this->events = $arrEvents;

            $arrInstructors = array_unique($arrInstructors);
            $aInstructors = [];
            $objUser = $databaseAdapter->getInstance()->execute('SELECT * FROM tl_user WHERE id IN ('.implode(',', array_map('\intval', $arrInstructors)).') ORDER BY lastname, firstname');

            while ($objUser->next()) {
                $arrLeft = [];
                $arrLeft[] = trim($objUser->lastname.' '.$objUser->firstname);
                $arrLeft[] = $objUser->street;
                $arrLeft[] = trim($objUser->postal.' '.$objUser->city);
                $arrLeft = array_filter($arrLeft);

                $arrRight = [];

                if ('' !== $objUser->phone) {
                    $arrRight[] = 'P '.$objUser->phone;
                }

                if ('' !== $objUser->mobile) {
                    $arrRight[] = 'M '.$objUser->mobile;
                }

                if ('' !== $objUser->email) {
                    $arrRight[] = $objUser->email;
                }

                $aInstructors[] = [
                    'id' => $objUser->id,
                    'leftCol' => implode(', ', $arrLeft),
                    'rightCol' => implode(', ', $arrRight),
                ];
            }
            $arrInstructors = $aInstructors;
            $this->instructors = $arrInstructors;
        }
    }

    private function getUsersByUserRole(array $arrUserRoles): array
    {
        $stringUtilAdapter = $this->framework->getAdapter(StringUtil::class);
        $userRoleModelAdapter = $this->framework->getAdapter(UserRoleModel::class);
        $databaseAdapter = $this->framework->getAdapter(Database::class);

        $specialUsers = [];

        if (!empty($arrUserRoles)) {
            $objUserRoles = $databaseAdapter->getInstance()->execute('SELECT * FROM tl_user_role WHERE id IN('.implode(',', array_map('\intval', $arrUserRoles)).') ORDER BY sorting');

            while ($objUserRoles->next()) {
                $userRole = $objUserRoles->id;
                $arrUsers = [];
                $objUser = $databaseAdapter
                    ->getInstance()
                    ->prepare('SELECT * FROM tl_user WHERE disable = 0 AND (start = "" OR start < ?) AND (stop = "" OR stop > ?) ORDER BY lastname, firstname')
                    ->execute(time(), time())
                ;

                while ($objUser->next()) {
                    $userRoles = $stringUtilAdapter->deserialize($objUser->userRole, true);

                    if (\in_array($userRole, $userRoles, false)) {
                        $arrLeft = [];
                        $arrLeft[] = trim($objUser->lastname.' '.$objUser->firstname);
                        $arrLeft[] = $objUser->street;
                        $arrLeft[] = trim($objUser->postal.' '.$objUser->city);
                        $arrLeft = array_filter($arrLeft);

                        $arrRight = [];

                        if ('' !== $objUser->phone) {
                            $arrRight[] = 'P '.$objUser->phone;
                        }

                        if ('' !== $objUser->mobile) {
                            $arrRight[] = 'M '.$objUser->mobile;
                        }

                        if ('' !== $objUser->email) {
                            $arrRight[] = $objUser->email;
                        }

                        $arrUsers[] = [
                            'id' => $objUser->id,
                            'leftCol' => implode(', ', $arrLeft),
                            'rightCol' => implode(', ', $arrRight),
                        ];
                    }
                }

                $specialUsers[] = [
                    'title' => $userRoleModelAdapter->findByPk($userRole)->title,
                    'users' => $arrUsers,
                ];
            }
        }

        return $specialUsers;
    }

    private function getEventPeriod(CalendarEventsModel $objEvent, string $dateFormat = ''): string
    {
        $dateAdapter = $this->framework->getAdapter(Date::class);
        $calendarEventsUtilAdapter = $this->framework->getAdapter(CalendarEventsUtil::class);
        $configAdapter = $this->framework->getAdapter(Config::class);
        $calendarAdapter = $this->framework->getAdapter(Calendar::class);

        if (empty($dateFormat)) {
            $dateFormat = $configAdapter->get('dateFormat');
        }

        if ('j.n.' === $dateFormat) {
            $dateFormatShortened = 'j.n.';
        } elseif ('j.' === $dateFormat) {
            $dateFormatShortened = 'j.';
        } else {
            $dateFormatShortened = $dateFormat;
        }

        $eventDuration = \count($calendarEventsUtilAdapter->getEventTimestamps($objEvent));
        $span = $calendarAdapter->calculateSpan($calendarEventsUtilAdapter->getStartDate($objEvent), $calendarEventsUtilAdapter->getEndDate($objEvent)) + 1;

        if (1 === $eventDuration) {
            return $dateAdapter->parse($dateFormat, $calendarEventsUtilAdapter->getStartDate($objEvent));
        }

        if (2 === $eventDuration && $span !== $eventDuration) {
            return $dateAdapter->parse($dateFormatShortened, $calendarEventsUtilAdapter->getStartDate($objEvent)).' + '.$dateAdapter->parse($dateFormat, $calendarEventsUtilAdapter->getEndDate($objEvent));
        }

        if ($span === $eventDuration) {
            // Check if event dates are not in the same month
            if ($dateAdapter->parse('n.Y', $calendarEventsUtilAdapter->getStartDate($objEvent)) === $dateAdapter->parse('n.Y', $calendarEventsUtilAdapter->getEndDate($objEvent))) {
                return $dateAdapter->parse($dateFormatShortened, $calendarEventsUtilAdapter->getStartDate($objEvent)).' - '.$dateAdapter->parse($dateFormat, $calendarEventsUtilAdapter->getEndDate($objEvent));
            }

            return $dateAdapter->parse('j.n.', $calendarEventsUtilAdapter->getStartDate($objEvent)).' - '.$dateAdapter->parse('j.n.', $calendarEventsUtilAdapter->getEndDate($objEvent));
        }

        $arrDates = [];
        $dates = $calendarEventsUtilAdapter->getEventTimestamps($objEvent);

        foreach ($dates as $date) {
            $arrDates[] = $dateAdapter->parse($dateFormat, $date);
        }

        return implode(' + ', $arrDates);
    }
}
