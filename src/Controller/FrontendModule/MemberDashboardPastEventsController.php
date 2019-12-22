<?php

/**
 * SAC Event Tool Web Plugin for Contao
 * Copyright (c) 2008-2020 Marko Cupic
 * @package sac-event-tool-bundle
 * @author Marko Cupic m.cupic@gmx.ch, 2017-2020
 * @link https://github.com/markocupic/sac-event-tool-bundle
 */

namespace Markocupic\SacEventToolBundle\Controller\FrontendModule;

use Contao\CalendarEventsMemberModel;
use Contao\Config;
use Contao\Controller;
use Contao\CoreBundle\Controller\FrontendModule\AbstractFrontendModuleController;
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\CoreBundle\Routing\ScopeMatcher;
use Contao\Date;
use Contao\Frontend;
use Contao\FrontendUser;
use Contao\Input;
use Contao\MemberModel;
use Contao\Message;
use Contao\ModuleModel;
use Contao\PageModel;
use Contao\StringUtil;
use Contao\System;
use Contao\Template;
use Contao\Validator;
use Markocupic\CloudconvertBundle\Services\DocxToPdfConversion;
use Markocupic\PhpOffice\PhpWord\MsWordTemplateProcessor;
use Markocupic\SacEventToolBundle\CalendarEventsHelper;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;
use Symfony\Component\Security\Core\Security;
use Contao\CoreBundle\ServiceAnnotation\FrontendModule;

/**
 * Class MemberDashboardPastEventsController
 * @package Markocupic\SacEventToolBundle\Controller\FrontendModule
 * @FrontendModule(category="sac_event_tool_fe_modules", type="member_dashboard_past_events")
 */
class MemberDashboardPastEventsController extends AbstractFrontendModuleController
{

    /**
     * @var FrontendUser
     */
    protected $objUser;

    /**
     * @var Template
     */
    protected $template;

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
        // Return empty string, if user is not logged in as a frontend user
        if ($this->isFrontend())
        {
            // Set adapters
            $inputAdapter = $this->get('contao.framework')->getAdapter(Input::class);

            // Get logged in member object
            if (($objUser = $this->get('security.helper')->getUser()) instanceof FrontendUser)
            {
                $this->objUser = $objUser;
            }

            // Neither cache nor search page
            $page->noSearch = 1;
            $page->cache = 0;

            // Do not allow for not authorized users
            if ($this->objUser === null)
            {
                throw new UnauthorizedHttpException();
            }

            // Print course certificate
            if ($inputAdapter->get('do') === 'download_course_certificate' && strlen($inputAdapter->get('id')) && $this->objUser !== null)
            {
                $this->downloadCourseCertificate();
            }
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

        $services['contao.framework'] = ContaoFramework::class;
        $services['security.helper'] = Security::class;
        $services['request_stack'] = RequestStack::class;
        $services['contao.routing.scope_matcher'] = ScopeMatcher::class;

        return $services;
    }

    /**
     * @param Template $template
     * @param ModuleModel $model
     * @param Request $request
     * @return null|Response
     */
    protected function getResponse(Template $template, ModuleModel $model, Request $request): ?Response
    {
        $this->template = $template;

        // Set adapters
        $messageAdapter = $this->get('contao.framework')->getAdapter(Message::class);
        $validatorAdapter = $this->get('contao.framework')->getAdapter(Validator::class);
        $calendarEventsMemberModelAdapter = $this->get('contao.framework')->getAdapter(CalendarEventsMemberModel::class);
        $controllerAdapter = $this->get('contao.framework')->getAdapter(Controller::class);
        $stringUtilAdapter = $this->get('contao.framework')->getAdapter(StringUtil::class);
        $frontendAdapter = $this->get('contao.framework')->getAdapter(Frontend::class);

        // Handle messages
        if ($this->objUser->email == '' || !$validatorAdapter->isEmail($this->objUser->email))
        {
            $messageAdapter->addInfo('Leider wurde für dieses Konto in der Datenbank keine E-Mail-Adresse gefunden. Daher stehen einige Funktionen nur eingeschränkt zur Verf&uuml;gung. Bitte hinterlegen Sie auf der Internetseite des Zentralverbands Ihre E-Mail-Adresse.');
        }

        // Add messages to template
        $this->addMessagesToTemplate();

        // Load language
        $controllerAdapter->loadLanguageFile('tl_calendar_events_member');

        // Get event type filter from module model
        $arrEventTypeFilter = $stringUtilAdapter->deserialize($model->eventType, true);

        // Past events
        $arrPastEvents = $calendarEventsMemberModelAdapter->findPastEventsByMemberId($this->objUser->id, $arrEventTypeFilter);
        $arrEvents = array();
        foreach ($arrPastEvents as $k => $event)
        {
            // Do only list if member has participated
            if ($event['role'] === 'member')
            {
                if ($event['eventRegistrationModel'] !== null)
                {
                    if (!$event['eventRegistrationModel']->hasParticipated)
                    {
                        continue;
                    }
                }
            }
            if ($event['eventType'] === 'course')
            {
                $event['downloadCourseConfirmationLink'] = $frontendAdapter->addToUrl('do=download_course_certificate&amp;id=' . $event['registrationId']);
            }
            $arrEvents[] = $event;
        }

        $this->template->arrPastEvents = $arrEvents;

        return $this->template->getResponse();
    }

    /**
     * Identify the Contao scope (TL_MODE) of the current request
     * @return bool
     */
    protected function isFrontend(): bool
    {
        return $this->get('request_stack')->getCurrentRequest() !== null ? $this->get('contao.routing.scope_matcher')->isFrontendRequest($this->get('request_stack')->getCurrentRequest()) : false;
    }

    /**
     * @throws \Exception
     */
    protected function downloadCourseCertificate()
    {
        // Set adapters
        $calendarEventsMemberModelAdapter = $this->get('contao.framework')->getAdapter(CalendarEventsMemberModel::class);
        $inputAdapter = $this->get('contao.framework')->getAdapter(Input::class);
        $memberModelAdapter = $this->get('contao.framework')->getAdapter(MemberModel::class);
        $dateAdapter = $this->get('contao.framework')->getAdapter(Date::class);
        $calendarEventsHelperAdapter = $this->get('contao.framework')->getAdapter(CalendarEventsHelper::class);
        $systemAdapter = $this->get('contao.framework')->getAdapter(System::class);
        $configAdapter = $this->get('contao.framework')->getAdapter(Config::class);

        if ($this->objUser !== null)
        {
            $objRegistration = $calendarEventsMemberModelAdapter->findByPk($inputAdapter->get('id'));
            if ($objRegistration !== null)
            {
                if ($this->objUser->sacMemberId == $objRegistration->sacMemberId)
                {
                    $objMember = $memberModelAdapter->findBySacMemberId($this->objUser->sacMemberId);
                    $startDate = '';
                    $arrDates = array();
                    $courseId = '';
                    $eventTitle = $objRegistration->eventName;

                    $objEvent = $objRegistration->getRelated('eventId');
                    if ($objEvent !== null)
                    {
                        $startDate = $dateAdapter->parse('Y', $objEvent->startDate);

                        // Build up $arrData;
                        // Get event dates from event object
                        $arrDates = array_map(function ($tstmp) {
                            $dateAdapter = $this->get('contao.framework')->getAdapter(Date::class);
                            return $dateAdapter->parse('m.d.Y', $tstmp);
                        }, $calendarEventsHelperAdapter->getEventTimestamps($objEvent->id));

                        // Course id
                        $courseId = htmlspecialchars(html_entity_decode($objEvent->courseId));

                        // Event title
                        $eventTitle = htmlspecialchars(html_entity_decode($objEvent->title));
                    }

                    // Log
                    $systemAdapter->log(sprintf('New event confirmation download. SAC-User-ID: %s. Event-ID: %s.', $objMember->sacMemberId, $objEvent->id), __FILE__ . ' Line: ' . __LINE__, $configAdapter->get('SAC_EVT_LOG_EVENT_CONFIRMATION_DOWNLOAD'));
                    // Create phpWord instance
                    $filenamePattern = str_replace('%%s', '%s', $configAdapter->get('SAC_EVT_COURSE_CONFIRMATION_FILE_NAME_PATTERN'));
                    $filename = sprintf($filenamePattern, $objMember->sacMemberId, $objRegistration->id, 'docx');
                    $destFilename = $configAdapter->get('SAC_EVT_TEMP_PATH') . '/' . $filename;
                    $objPhpWord = new MsWordTemplateProcessor($configAdapter->get('SAC_EVT_COURSE_CONFIRMATION_TEMPLATE_SRC'), $destFilename);

                    // Replace template vars
                    $objPhpWord->replace('eventDates', implode(', ', $arrDates));
                    $objPhpWord->replace('firstname', htmlspecialchars(html_entity_decode($objMember->firstname)));
                    $objPhpWord->replace('lastname', htmlspecialchars(html_entity_decode($objMember->lastname)));
                    $objPhpWord->replace('memberId', $objMember->sacMemberId);
                    $objPhpWord->replace('eventYear', $startDate);
                    $objPhpWord->replace('eventId', htmlspecialchars(html_entity_decode($objRegistration->eventId)));
                    $objPhpWord->replace('eventName', $eventTitle);
                    $objPhpWord->replace('regId', $objRegistration->id);
                    $objPhpWord->replace('courseId', $courseId);

                    // Generate ms word file and send it to the browser
                    $objPhpWord->generateUncached(false)
                        ->sendToBrowser(false)
                        ->generate();

                    // Generate pdf
                    $objConversion = new DocxToPdfConversion($destFilename, $configAdapter->get('cloudconvertApiKey'));
                    $objConversion->sendToBrowser(true)->createUncached(false)->convert();

                    exit();
                }
                throw new \Exception('There was an error while trying to generate the course confirmation.');
            }
        }
    }

    /**
     * Add messages from session to template
     */
    protected function addMessagesToTemplate(): void
    {
        $messageAdapter = $this->get('contao.framework')->getAdapter(Message::class);
        $systemAdapter = $this->get('contao.framework')->getAdapter(System::class);

        if ($messageAdapter->hasInfo())
        {
            $this->template->hasInfoMessage = true;
            $session = $systemAdapter->getContainer()->get('session')->getFlashBag()->get('contao.FE.info');
            $this->template->infoMessage = $session[0];
        }

        if ($messageAdapter->hasError())
        {
            $this->template->hasErrorMessage = true;
            $session = $systemAdapter->getContainer()->get('session')->getFlashBag()->get('contao.FE.error');
            $this->template->errorMessage = $session[0];
            $this->template->errorMessages = $session;
        }

        $messageAdapter->reset();
    }

}
