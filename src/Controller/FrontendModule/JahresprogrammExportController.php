<?php

declare(strict_types=1);

/*
 * This file is part of SAC Event Tool Bundle.
 *
 * (c) Marko Cupic 2022 <m.cupic@gmx.ch>
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
use Contao\CoreBundle\ServiceAnnotation\FrontendModule;
use Contao\CourseMainTypeModel;
use Contao\CourseSubTypeModel;
use Contao\Database;
use Contao\Date;
use Contao\Environment;
use Contao\EventOrganizerModel;
use Contao\ModuleModel;
use Contao\PageModel;
use Contao\StringUtil;
use Contao\Template;
use Contao\UserRoleModel;
use Doctrine\DBAL\Connection;
use Haste\Form\Form;
use Markocupic\SacEventToolBundle\CalendarEventsHelper;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;

/**
 * @FrontendModule(JahresprogrammExportController::TYPE, category="sac_event_tool_frontend_modules")
 */
class JahresprogrammExportController extends AbstractPrintExportController
{
    public const TYPE = 'jahresprogramm_export';

    protected ModuleModel|null $model = null;
    protected Template|null $template = null;
    protected int|null $startDate = null;
    protected int|null $endDate = null;
    protected int|null $organizer = null;
    protected string|null $eventType = null;
    protected int|null $eventReleaseLevel;
    protected array|null $events = null;
    protected array|null $instructors = null;
    protected array|null $specialUsers = null;

    public function __invoke(Request $request, ModuleModel $model, string $section, array $classes = null, PageModel $page = null): Response
    {
        $this->model = $model;

        // Call the parent method
        return parent::__invoke($request, $model, $section, $classes);
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
    protected function getResponse(Template $template, ModuleModel $model, Request $request): Response|null
    {
        $this->template = $template;

        /** @var Controller $controllerAdapter */
        $controllerAdapter = $this->get('contao.framework')->getAdapter(Controller::class);

        // Load language file
        $controllerAdapter->loadLanguageFile('tl_calendar_events');

        $template->form = $this->generateForm();

        return $this->template->getResponse();
    }

    /**
     * @throws \Exception
     */
    protected function generateForm(): Form
    {
        /** @var Request $request */
        $request = $this->get('request_stack')->getCurrentRequest();

        /** @var EventOrganizerModel $eventOrganizerModelAdapter */
        $eventOrganizerModelAdapter = $this->get('contao.framework')->getAdapter(EventOrganizerModel::class);

        /** @var Environment $environmentAdapter */
        $environmentAdapter = $this->get('contao.framework')->getAdapter(Environment::class);

        /** @var Database $databaseAdapter */
        $databaseAdapter = $this->get('contao.framework')->getAdapter(Database::class);

        /** @var Form $objForm */
        $objForm = new Form(
            'form-jahresprogramm-export',
            'POST',
            function (Form $objHaste): bool {
                /** @var Request $request */
                $request = $this->get('request_stack')->getCurrentRequest();

                return $request->request->get('FORM_SUBMIT') === $objHaste->getFormId();
            }
        );

        $objForm->setFormActionFromUri($environmentAdapter->get('uri'));

        // Now let's add form fields:
        $objForm->addFormField('eventType', [
            'label' => 'Event-Typ',
            'reference' => $GLOBALS['TL_LANG']['MSC'],
            'inputType' => 'select',
            'options' => $GLOBALS['TL_CONFIG']['SAC-EVENT-TOOL-CONFIG']['EVENT-TYPE'],
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
            if ('' !== $request->request->get('startDate') && '' !== $request->request->get('endDate') && '' !== $request->request->get('eventType')) {
                $this->startDate = strtotime($request->request->get('startDate'));
                $this->endDate = strtotime($request->request->get('endDate'));
                $this->eventType = $request->request->get('eventType');
                $this->organizer = $request->request->get('organizer') > 0 ? (int) $request->request->get('organizer') : null;
                $this->eventReleaseLevel = $request->request->get('eventReleaseLevel') > 0 ? (int) $request->request->get('eventReleaseLevel') : null;

                // Get events and instructors (fill $this->events and $this->instructors)
                $this->getEventsAndInstructors();

                $this->template->eventType = $this->eventType;
                $this->template->eventTypeLabel = $GLOBALS['TL_LANG']['MSC'][$this->eventType];
                $this->template->startDate = $this->startDate;
                $this->template->endDate = $this->endDate;
                $this->template->organizer = $this->organizer > 0 ? $eventOrganizerModelAdapter->findByPk($this->organizer)->title : 'Alle Gruppen';
                $this->template->events = $this->events;
                $this->template->instructors = $this->instructors;

                $arrayUserRoles = empty($request->request->get('userRoles')) ? [] : $request->request->get('userRoles');
                $this->specialUsers = $this->getUsersByUserRole($arrayUserRoles);
                $this->template->specialUsers = $this->specialUsers;
            }
        }

        return $objForm;
    }

    /**
     * @throws \Exception
     */
    protected function getEventsAndInstructors(): void
    {
        /** @var StringUtil $stringUtilAdapter */
        $stringUtilAdapter = $this->get('contao.framework')->getAdapter(StringUtil::class);

        /** @var Date $dateAdapter */
        $dateAdapter = $this->get('contao.framework')->getAdapter(Date::class);

        /** @var CourseMainTypeModel $courseMainTypeModelAdapter */
        $courseMainTypeModelAdapter = $this->get('contao.framework')->getAdapter(CourseMainTypeModel::class);

        /** @var CourseSubTypeModel $courseSubTypeModelAdapter */
        $courseSubTypeModelAdapter = $this->get('contao.framework')->getAdapter(CourseSubTypeModel::class);

        /** @var CalendarEventsModel $calendarEventsModelAdapter */
        $calendarEventsModelAdapter = $this->get('contao.framework')->getAdapter(CalendarEventsModel::class);

        /** @var CalendarEventsHelper $calendarEventsHelperAdapter */
        $calendarEventsHelperAdapter = $this->get('contao.framework')->getAdapter(CalendarEventsHelper::class);

        /** @var Database $databaseAdapter */
        $databaseAdapter = $this->get('contao.framework')->getAdapter(Database::class);

        /** @var EventOrganizerModel $eventOrganizerModelAdapter */
        $eventOrganizerModelAdapter = $this->get('contao.framework')->getAdapter(EventOrganizerModel::class);

        $events = [];
        $objEvents = $databaseAdapter->getInstance()->prepare('SELECT * FROM tl_calendar_events WHERE startDate>=? AND startDate<=?')->execute($this->startDate, $this->endDate);

        while ($objEvents->next()) {
            // Check if event is at least on second highest level (Level 3/4)
            $eventModel = $calendarEventsModelAdapter->findByPk($objEvents->id);

            if (!$this->hasValidReleaseLevel($eventModel, (int) $this->eventReleaseLevel)) {
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
            if ('course' === $this->eventType) {
                $objEvent = CalendarEventsModel::findMultipleByIds($events, ['order' => 'tl_calendar_events.courseTypeLevel0, tl_calendar_events.courseTypeLevel1, tl_calendar_events.startDate, tl_calendar_events.endDate, tl_calendar_events.courseId']);
            } else {
                $objEvent = CalendarEventsModel::findMultipleByIds($events, ['order' => 'tl_calendar_events.startDate, tl_calendar_events.endDate']);
            }

            if (null !== $objEvent) {
                while ($objEvent->next()) {
                    $arrInstructors = array_merge($arrInstructors, $calendarEventsHelperAdapter->getInstructorsAsArray($objEvent->current(), false));

                    // tourType && date format
                    $arrTourType = $calendarEventsHelperAdapter->getTourTypesAsArray($objEvent->current(), 'shortcut', false);
                    $dateFormat = 'D, j.';

                    if ('course' === $objEvent->eventType) {
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
                            $showHeadline = $eventOrganizerModel->annualProgramShowHeadline ? true : false;
                            $showTeaser = $eventOrganizerModel->annualProgramShowTeaser ? true : false;
                            $showDetails = $eventOrganizerModel->annualProgramShowDetails ? true : false;
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

                    $arrData['eventId'] = $calendarEventsHelperAdapter->getEventData($objEvent->current(), 'eventId');
                    $arrData['organizers'] = implode(', ', $calendarEventsHelperAdapter->getEventOrganizersAsArray($objEvent->current(), 'title'));
                    $arrData['organizerTitle'] = $organizerTitle;
                    $arrData['organizerTitlePrint'] = $organizerTitlePrint;
                    $arrData['courseLevel'] = $GLOBALS['TL_CONFIG']['SAC-EVENT-TOOL-CONFIG']['courseLevel'][$objEvent->courseLevel] ?? '';
                    $arrData['courseTypeLevel0'] = null !== $courseMainTypeModelAdapter->findByPk($objEvent->courseTypeLevel0) ? $courseMainTypeModelAdapter->findByPk($objEvent->courseTypeLevel0)->name : '';
                    $arrData['courseTypeLevel1'] = null !== $courseSubTypeModelAdapter->findByPk($objEvent->courseTypeLevel1) ? $courseSubTypeModelAdapter->findByPk($objEvent->courseTypeLevel1)->name : '';
                    $arrData['date'] = $this->getEventPeriod($objEvent->current(), $dateFormat);
                    $arrData['month'] = $dateAdapter->parse('F', $objEvent->startDate);
                    $arrData['instructors'] = implode(', ', $calendarEventsHelperAdapter->getInstructorNamesAsArray($objEvent->current(), false, false));
                    $arrData['tourType'] = implode(', ', $arrTourType);
                    $arrData['difficulty'] = implode(', ', $calendarEventsHelperAdapter->getTourTechDifficultiesAsArray($objEvent->current()));
                    // Layout settings
                    $arrData['showHeadline'] = $showHeadline;
                    $arrData['showTeaser'] = $showTeaser;
                    $arrData['showDetails'] = $showDetails;

                    // Details
                    $arrData['arrTourProfile'] = $calendarEventsHelperAdapter->getEventData($objEvent->current(), 'arrTourProfile');
                    $arrData['journey'] = $calendarEventsHelperAdapter->getEventData($objEvent->current(), 'journey');
                    $arrData['minMaxMembers'] = implode('/', $minMax);

                    $arrData['bookingInfo'] = 'Event-Nummer '.$calendarEventsHelperAdapter->getEventData($objEvent->current(), 'eventId');

                    if ('course' === $objEvent->eventType) {
                        $arrData['bookingInfo'] = 'Kurs-Nummer '.$calendarEventsHelperAdapter->getEventData($objEvent->current(), 'courseId');
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

    protected function getUsersByUserRole(array $arrUserRoles): array
    {
        /** @var StringUtil $stringUtilAdapter */
        $stringUtilAdapter = $this->get('contao.framework')->getAdapter(StringUtil::class);

        /** @var UserRoleModel $userRoleModelAdapter */
        $userRoleModelAdapter = $this->get('contao.framework')->getAdapter(UserRoleModel::class);

        /** @var Database $databaseAdapter */
        $databaseAdapter = $this->get('contao.framework')->getAdapter(Database::class);

        $specialUsers = [];

        if (!empty($arrUserRoles) && \is_array($arrUserRoles)) {
            $objUserRoles = $databaseAdapter->getInstance()->execute('SELECT * FROM tl_user_role WHERE id IN('.implode(',', array_map('\intval', $arrUserRoles)).') ORDER BY sorting');

            while ($objUserRoles->next()) {
                $userRole = $objUserRoles->id;
                $arrUsers = [];
                $objUser = $databaseAdapter->getInstance()->execute('SELECT * FROM tl_user ORDER BY lastname, firstname');

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

        if ('j.n.' === $dateFormat) {
            $dateFormatShortened = 'j.n.';
        } elseif ('j.' === $dateFormat) {
            $dateFormatShortened = 'j.';
        } else {
            $dateFormatShortened = $dateFormat;
        }

        $eventDuration = \count($calendarEventsHelperAdapter->getEventTimestamps($objEvent));
        $span = $calendarAdapter->calculateSpan($calendarEventsHelperAdapter->getStartDate($objEvent), $calendarEventsHelperAdapter->getEndDate($objEvent)) + 1;

        if (1 === $eventDuration) {
            return $dateAdapter->parse($dateFormat, $calendarEventsHelperAdapter->getStartDate($objEvent));
        }

        if (2 === $eventDuration && $span !== $eventDuration) {
            return $dateAdapter->parse($dateFormatShortened, $calendarEventsHelperAdapter->getStartDate($objEvent)).' + '.$dateAdapter->parse($dateFormat, $calendarEventsHelperAdapter->getEndDate($objEvent));
        }

        if ($span === $eventDuration) {
            // Check if event dates are not in the same month
            if ($dateAdapter->parse('n.Y', $calendarEventsHelperAdapter->getStartDate($objEvent)) === $dateAdapter->parse('n.Y', $calendarEventsHelperAdapter->getEndDate($objEvent))) {
                return $dateAdapter->parse($dateFormatShortened, $calendarEventsHelperAdapter->getStartDate($objEvent)).' - '.$dateAdapter->parse($dateFormat, $calendarEventsHelperAdapter->getEndDate($objEvent));
            }

            return $dateAdapter->parse('j.n.', $calendarEventsHelperAdapter->getStartDate($objEvent)).' - '.$dateAdapter->parse('j.n.', $calendarEventsHelperAdapter->getEndDate($objEvent));
        }

        $arrDates = [];
        $dates = $calendarEventsHelperAdapter->getEventTimestamps($objEvent);

        foreach ($dates as $date) {
            $arrDates[] = $dateAdapter->parse($dateFormat, $date);
        }

        return implode(' + ', $arrDates);
    }
}
