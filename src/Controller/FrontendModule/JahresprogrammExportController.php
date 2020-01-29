<?php

declare(strict_types=1);

/**
 * SAC Event Tool Web Plugin for Contao
 * Copyright (c) 2008-2020 Marko Cupic
 * @package sac-event-tool-bundle
 * @author Marko Cupic m.cupic@gmx.ch, 2017-2020
 * @link https://github.com/markocupic/sac-event-tool-bundle
 */

namespace Markocupic\SacEventToolBundle\Controller\FrontendModule;

use Contao\Calendar;
use Contao\CalendarEventsModel;
use Contao\Config;
use Contao\Controller;
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
use Contao\CoreBundle\ServiceAnnotation\FrontendModule;

/**
 * Class JahresprogrammExportController
 * @package Markocupic\SacEventToolBundle\Controller\FrontendModule
 * @FrontendModule("jahresprogramm_export", category="sac_event_tool_frontend_modules")
 */
class JahresprogrammExportController extends AbstractPrintExportController
{

    /**
     * @var ModuleModel
     */
    protected $model;

    /**
     * @var Template
     */
    protected $template;

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
     * @param Template $template
     * @param ModuleModel $model
     * @param Request $request
     * @return null|Response
     * @throws \Exception
     */
    protected function getResponse(Template $template, ModuleModel $model, Request $request): ?Response
    {
        $this->template = $template;

        /** @var  Controller $controllerAdapter */
        $controllerAdapter = $this->get('contao.framework')->getAdapter(Controller::class);

        // Load language file
        $controllerAdapter->loadLanguageFile('tl_calendar_events');

        $template->form = $this->generateForm();

        return $this->template->getResponse();
    }

    /**
     * @return Form
     * @throws \Exception
     */
    protected function generateForm(): Form
    {
        /** @var Request $request */
        $request = $this->get('request_stack')->getCurrentRequest();

        /** @var  EventOrganizerModel $eventOrganizerModelAdapter */
        $eventOrganizerModelAdapter = $this->get('contao.framework')->getAdapter(EventOrganizerModel::class);

        /** @var Environment $environmentAdapter */
        $environmentAdapter = $this->get('contao.framework')->getAdapter(Environment::class);

        /** @var Database $databaseAdapter */
        $databaseAdapter = $this->get('contao.framework')->getAdapter(Database::class);

        /** @var Form $objForm */
        $objForm = new Form('form-jahresprogramm-export', 'POST', function (Form $objHaste): bool {
            /** @var Request $request */
            $request = $this->get('request_stack')->getCurrentRequest();

            return $request->request->get('FORM_SUBMIT') === $objHaste->getFormId();
        });

        $objForm->setFormActionFromUri($environmentAdapter->get('uri'));

        // Now let's add form fields:
        $objForm->addFormField('eventType', array(
            'label'     => 'Event-Typ',
            'reference' => $GLOBALS['TL_LANG']['MSC'],
            'inputType' => 'select',
            'options'   => $GLOBALS['TL_CONFIG']['SAC-EVENT-TOOL-CONFIG']['EVENT-TYPE'],
            'eval'      => array('includeBlankOption' => true, 'mandatory' => true),
        ));

        $arrOrganizers = array();
        $objOrganizer = $databaseAdapter->getInstance()->prepare('SELECT * FROM tl_event_organizer ORDER BY sorting')->execute();
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
        $objUserRoles = $databaseAdapter->getInstance()->prepare('SELECT * FROM tl_user_role ORDER BY title')->execute();
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
            if ($request->request->get('startDate') != '' && $request->request->get('endDate') != '' && $request->request->get('eventType') != '')
            {
                $this->startDate = strtotime($request->request->get('startDate'));
                $this->endDate = strtotime($request->request->get('endDate'));
                $this->eventType = $request->request->get('eventType');
                $this->organizer = $request->request->get('organizer') > 0 ? $request->request->get('organizer') : null;
                $this->eventReleaseLevel = $request->request->get('eventReleaseLevel') > 0 ? $request->request->get('eventReleaseLevel') : null;

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

        /** @var  CourseMainTypeModel $courseMainTypeModelAdapter */
        $courseMainTypeModelAdapter = $this->get('contao.framework')->getAdapter(CourseMainTypeModel::class);

        /** @var  CourseSubTypeModel $courseSubTypeModelAdapter */
        $courseSubTypeModelAdapter = $this->get('contao.framework')->getAdapter(CourseSubTypeModel::class);

        /** @var  CalendarEventsModel $calendarEventsModelAdapter */
        $calendarEventsModelAdapter = $this->get('contao.framework')->getAdapter(CalendarEventsModel::class);

        /** @var  CalendarEventsHelper $calendarEventsHelperAdapter */
        $calendarEventsHelperAdapter = $this->get('contao.framework')->getAdapter(CalendarEventsHelper::class);

        /** @var Database $databaseAdapter */
        $databaseAdapter = $this->get('contao.framework')->getAdapter(Database::class);

        $arrEvents = array();
        $objEvents = $databaseAdapter->getInstance()->prepare('SELECT * FROM tl_calendar_events WHERE startDate>=? AND startDate<=?')->execute($this->startDate, $this->endDate);

        while ($objEvents->next())
        {
            // Check if event is at least on second highest level (Level 3/4)
            $eventModel = $calendarEventsModelAdapter->findByPk($objEvents->id);
            if (!$this->hasValidReleaseLevel($eventModel, (int)$this->eventReleaseLevel))
            {
                continue;
            }

            if ($this->organizer)
            {
                $arrOrganizer = $stringUtilAdapter->deserialize($objEvents->organizers, true);
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
                $objEvent = CalendarEventsModel::findMultipleByIds($arrEvents, ['order' => 'tl_calendar_events.courseTypeLevel0, tl_calendar_events.courseTypeLevel1, tl_calendar_events.startDate, tl_calendar_events.endDate, tl_calendar_events.courseId']);
            }
            else
            {
                $objEvent = CalendarEventsModel::findMultipleByIds($arrEvents, ['order' => 'tl_calendar_events.startDate, tl_calendar_events.endDate']);
            }
            if($objEvent !== null)
            {
                while ($objEvent->next())
                {
                    $arrInstructors = array_merge($arrInstructors, $calendarEventsHelperAdapter->getInstructorsAsArray($objEvent->current(), false));

                    // tourType && date format
                    $arrTourType = $calendarEventsHelperAdapter->getTourTypesAsArray($objEvent->current(), 'shortcut', false);
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
                        'organizers'       => implode(', ', $calendarEventsHelperAdapter->getEventOrganizersAsArray($objEvent->current(), 'title')),
                        'courseLevel'      => isset($GLOBALS['TL_CONFIG']['SAC-EVENT-TOOL-CONFIG']['courseLevel'][$objEvent->courseLevel]) ? $GLOBALS['TL_CONFIG']['SAC-EVENT-TOOL-CONFIG']['courseLevel'][$objEvent->courseLevel] : '',
                        'courseTypeLevel0' => ($courseMainTypeModelAdapter->findByPk($objEvent->courseTypeLevel0) !== null) ? $courseMainTypeModelAdapter->findByPk($objEvent->courseTypeLevel0)->name : '',
                        'courseTypeLevel1' => ($courseSubTypeModelAdapter->findByPk($objEvent->courseTypeLevel1) !== null) ? $courseSubTypeModelAdapter->findByPk($objEvent->courseTypeLevel1)->name : '',
                        'title'            => $objEvent->title,
                        'date'             => $this->getEventPeriod($objEvent->current(), $dateFormat),
                        'month'            => $dateAdapter->parse('F', $objEvent->startDate),
                        'durationInfo'     => $objEvent->durationInfo,
                        'instructors'      => implode(', ', $calendarEventsHelperAdapter->getInstructorNamesAsArray($objEvent->current(), false, false)),
                        'tourType'         => implode(', ', $arrTourType),
                        'difficulty'       => implode(',', $calendarEventsHelperAdapter->getTourTechDifficultiesAsArray($objEvent->current())),
                    );
                }
            }

            $this->events = $arrEvent;

            $arrInstructors = array_unique($arrInstructors);
            $aInstructors = array();
            $objUser = $databaseAdapter->getInstance()->execute('SELECT * FROM tl_user WHERE id IN (' . implode(',', array_map('\intval', $arrInstructors)) . ') ORDER BY lastname, firstname');
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
     * @param CalendarEventsModel $objEvent
     * @param string $dateFormat
     * @return string
     */
    private function getEventPeriod(CalendarEventsModel $objEvent, string $dateFormat = ''): string
    {
        /** @var Date $dateAdapter */
        $dateAdapter = $this->get('contao.framework')->getAdapter(Date::class);

        /** @var  CalendarEventsHelper $calendarEventsHelperAdapter */
        $calendarEventsHelperAdapter = $this->get('contao.framework')->getAdapter(CalendarEventsHelper::class);

        /** @var Config $configAdapter */
        $configAdapter = $this->get('contao.framework')->getAdapter(Config::class);

        /** @var Calendar $calendarAdapter */
        $calendarAdapter = $this->get('contao.framework')->getAdapter(Calendar::class);

        if ($dateFormat == '')
        {
            $dateFormat = $configAdapter->get('dateFormat');
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

        $eventDuration = count($calendarEventsHelperAdapter->getEventTimestamps($objEvent));
        $span = $calendarAdapter->calculateSpan($calendarEventsHelperAdapter->getStartDate($objEvent), $calendarEventsHelperAdapter->getEndDate($objEvent)) + 1;

        if ($eventDuration == 1)
        {
            return $dateAdapter->parse($dateFormat, $calendarEventsHelperAdapter->getStartDate($objEvent));
        }
        if ($eventDuration == 2 && $span != $eventDuration)
        {
            return $dateAdapter->parse($dateFormatShortened, $calendarEventsHelperAdapter->getStartDate($objEvent)) . '+' . $dateAdapter->parse($dateFormat, $calendarEventsHelperAdapter->getEndDate($objEvent));
        }
        elseif ($span == $eventDuration)
        {
            // Check if event dates are not in the same month
            if ($dateAdapter->parse('n.Y', $calendarEventsHelperAdapter->getStartDate($objEvent)) === $dateAdapter->parse('n.Y', $calendarEventsHelperAdapter->getEndDate($objEvent)))
            {
                return $dateAdapter->parse($dateFormatShortened, $calendarEventsHelperAdapter->getStartDate($objEvent)) . '-' . $dateAdapter->parse($dateFormat, $calendarEventsHelperAdapter->getEndDate($objEvent));
            }
            else
            {
                return $dateAdapter->parse('j.n.', $calendarEventsHelperAdapter->getStartDate($objEvent)) . '-' . $dateAdapter->parse('j.n.', $calendarEventsHelperAdapter->getEndDate($objEvent));
            }
        }
        else
        {
            $arrDates = array();
            $dates = $calendarEventsHelperAdapter->getEventTimestamps($objEvent);
            foreach ($dates as $date)
            {
                $arrDates[] = $dateAdapter->parse($dateFormat, $date);
            }

            return implode('+', $arrDates);
        }
    }

    /**
     * @param array $arrUserRoles
     * @return array
     */
    protected function getUsersByUserRole(array $arrUserRoles): array
    {
        /** @var  StringUtil $stringUtilAdapter */
        $stringUtilAdapter = $this->get('contao.framework')->getAdapter(StringUtil::class);

        /** @var UserRoleModel $userRoleModelAdapter */
        $userRoleModelAdapter = $this->get('contao.framework')->getAdapter(UserRoleModel::class);

        /** @var Database $databaseAdapter */
        $databaseAdapter = $this->get('contao.framework')->getAdapter(Database::class);

        $specialUsers = array();
        if (is_array($arrUserRoles) && !empty($arrUserRoles))
        {
            $objUserRoles = $databaseAdapter->getInstance()->execute('SELECT * FROM tl_user_role WHERE id IN(' . implode(',', array_map('\intval', $arrUserRoles)) . ') ORDER BY sorting');
            while ($objUserRoles->next())
            {
                $userRole = $objUserRoles->id;
                $arrUsers = array();
                $objUser = $databaseAdapter->getInstance()->execute('SELECT * FROM tl_user ORDER BY lastname, firstname');
                while ($objUser->next())
                {
                    $userRoles = $stringUtilAdapter->deserialize($objUser->userRole, true);
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

                    'title' => $userRoleModelAdapter->findByPk($userRole)->title,
                    'users' => $arrUsers,
                );
            }
        }
        return $specialUsers;
    }

}
