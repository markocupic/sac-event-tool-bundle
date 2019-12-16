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
use Contao\CalendarEventsModel;
use Contao\Config;
use Contao\Controller;
use Contao\CoreBundle\Controller\FrontendModule\AbstractFrontendModuleController;
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\CoreBundle\Routing\ScopeMatcher;
use Contao\Database;
use Contao\Date;
use Contao\Environment;
use Contao\FrontendUser;
use Contao\Input;
use Contao\Message;
use Contao\ModuleModel;
use Contao\PageModel;
use Contao\System;
use Contao\Template;
use Contao\Validator;
use Haste\Form\Form;
use Haste\Util\Url;
use Markocupic\SacEventToolBundle\CalendarEventsHelper;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;
use Symfony\Component\Security\Core\Security;
use Contao\CoreBundle\ServiceAnnotation\FrontendModule;

/**
 * Class MemberDashboardEventReportListController
 * @package Markocupic\SacEventToolBundle\Controller\FrontendModule
 * @FrontendModule(category="sac_event_tool_fe_modules", type="member_dashboard_event_report_list")
 */
class MemberDashboardEventReportListController extends AbstractFrontendModuleController
{

    /**
     * @var ContaoFramework
     */
    protected $framework;

    /**
     * @var Security
     */
    protected $security;

    /**
     * @var RequestStack
     */
    protected $requestStack;

    /**
     * @var ScopeMatcher
     */
    protected $scopeMatcher;

    /**
     * @var string
     */
    protected $projectDir;

    /**
     * @var FrontendUser
     */
    protected $objUser;

    /**
     * @var Template
     */
    protected $template;

    /**
     * MemberDashboardEventReportController constructor.
     * @param ContaoFramework $framework
     * @param Security $security
     * @param RequestStack $requestStack
     * @param ScopeMatcher $scopeMatcher
     * @param string $projectDir
     */
    public function __construct(ContaoFramework $framework, Security $security, RequestStack $requestStack, ScopeMatcher $scopeMatcher, string $projectDir)
    {
        $this->framework = $framework;
        $this->security = $security;
        $this->requestStack = $requestStack;
        $this->scopeMatcher = $scopeMatcher;
        $this->projectDir = $projectDir;
    }

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
            // Get logged in member object
            if (($objUser = $this->security->getUser()) instanceof FrontendUser)
            {
                $this->objUser = $objUser;
            }

            // Do not allow for not authorized users
            if ($this->objUser === null)
            {
                throw new UnauthorizedHttpException();
            }

            // Neither cache nor search page
            $page->noSearch = 1;
            $page->cache = 0;
        }

        // Call the parent method
        return parent::__invoke($request, $model, $section, $classes);
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
        $messageAdapter = $this->framework->getAdapter(Message::class);
        $validatorAdapter = $this->framework->getAdapter(Validator::class);

        // Handle messages
        if ($this->objUser->email == '' || !$validatorAdapter->isEmail($this->objUser->email))
        {
            $messageAdapter->addInfo('Leider wurde für dieses Konto in der Datenbank keine E-Mail-Adresse gefunden. Daher stehen einige Funktionen nur eingeschränkt zur Verf&uuml;gung. Bitte hinterlegen Sie auf der Internetseite des Zentralverbands Ihre E-Mail-Adresse.');
        }

        // Get time span for creating new event story
        $this->template->timeSpanForCreatingNewEventStory = $model->timeSpanForCreatingNewEventStory;

        // Add messages to template
        $this->addMessagesToTemplate();
        $objForm = $this->generateCreateNewEventStoryForm($model);
        $this->template->newEventStoryForm = $objForm->generate();

        // Get event report list
        $this->template->arrEventStories = $this->getEventStories($model);

        return $this->template->getResponse();
    }

    /**
     * Identify the Contao scope (TL_MODE) of the current request
     * @return bool
     */
    protected function isFrontend(): bool
    {
        return $this->scopeMatcher->isFrontendRequest($this->requestStack->getCurrentRequest());
    }

    /**
     * @param ModuleModel $model
     * @return array
     */
    protected function getEventStories(ModuleModel $model): array
    {
        // Set adapters
        $calendarEventsModelAdapter = $this->framework->getAdapter(CalendarEventsModel::class);
        $dateAdapter = $this->framework->getAdapter(Date::class);
        $calendarEventsHelperAdapter = $this->framework->getAdapter(CalendarEventsHelper::class);
        $configAdapter = $this->framework->getAdapter(Config::class);
        $databaseAdapter = $this->framework->getAdapter(Database::class);
        $pageModelAdapter = $this->framework->getAdapter(PageModel::class);
        $urlAdapter = $this->framework->getAdapter(Url::class);

        $arrEventStories = array();

        if ($this->objUser !== null)
        {
            // Event Stories
            $objEventStory = $databaseAdapter->getInstance()->prepare('SELECT * FROM tl_calendar_events_story WHERE sacMemberId=? ORDER BY eventStartDate DESC')->execute($this->objUser->sacMemberId);
            while ($objEventStory->next())
            {
                $arrEventStory = $objEventStory->row();

                // Check if story is still editable
                if ($objEventStory->eventEndDate + $model->timeSpanForCreatingNewEventStory * 24 * 60 * 60 > time())
                {
                    if ($objEventStory->publishState == 1)
                    {
                        $arrEventStory['canEditStory'] = true;
                    }
                }

                $arrEventStory['date'] = $dateAdapter->parse($configAdapter->get('dateFormat'), $objEventStory->eventStartDate);

                // Check if event still exists
                if ($calendarEventsModelAdapter->findByPk($objEventStory->eventId) !== null)
                {
                    // Overwrite date if event still exists in tl_calendar_events
                    $arrEventStory['date'] = $calendarEventsHelperAdapter->getEventPeriod($objEventStory->eventId, $configAdapter->get('dateFormat'), false);
                    $objPage = $pageModelAdapter->findByPk($model->eventStoryFormJumpTo);
                    if ($objPage !== null)
                    {
                        $arrEventStory['storyLink'] = $urlAdapter->addQueryString('eventId=' . $objEventStory->eventId, $objPage->getFrontendUrl());
                    }
                }
                $arrEventStories[] = $arrEventStory;
            }
        }

        return $arrEventStories;
    }

    /**
     * @param ModuleModel $model
     * @return Form
     */
    protected function generateCreateNewEventStoryForm(ModuleModel $model): Form
    {
        // Set adapters
        $calendarEventsMemberModelAdapter = $this->framework->getAdapter(CalendarEventsMemberModel::class);
        $environmentAdapter = $this->framework->getAdapter(Environment::class);
        $controllerAdapter = $this->framework->getAdapter(Controller::class);
        $urlAdapter = $this->framework->getAdapter(Url::class);
        $inputAdapter = $this->framework->getAdapter(Input::class);
        $pageModelAdapter = $this->framework->getAdapter(PageModel::class);

        $objForm = new Form('form-create-new-event-story', 'POST', function ($objHaste) {
            $inputAdapter = $this->framework->getAdapter(Input::class);

            return $inputAdapter->post('FORM_SUBMIT') === $objHaste->getFormId();
        });

        $objForm->setFormActionFromUri($environmentAdapter->get('uri'));

        $arrOptions = array();
        $intStartDateMin = $model->timeSpanForCreatingNewEventStory > 0 ? time() - $model->timeSpanForCreatingNewEventStory * 24 * 3600 : time();
        $arrEvents = $calendarEventsMemberModelAdapter->findEventsByMemberId($this->objUser->id, array(), $intStartDateMin, time(), true);

        if (is_array($arrEvents) && !empty($arrEvents))
        {
            foreach ($arrEvents as $event)
            {
                if ($event['objEvent'] !== null)
                {
                    $objEvent = $event['objEvent'];
                    $arrOptions[$event['id']] = $objEvent->title;
                }
            }
        }

        // Now let's add form fields:
        $objForm->addFormField('event', array(
            'label'     => 'Tourenbericht zu einem Event erstellen',
            'inputType' => 'select',
            'options'   => $arrOptions,
            'eval'      => array('mandatory' => true),
        ));

        // Let's add  a submit button
        $objForm->addFormField('submit', array(
            'label'     => 'Weiter',
            'inputType' => 'submit',
        ));

        if ($objForm->validate())
        {
            // Redirect to the page with the event report form
            if ($inputAdapter->post('FORM_SUBMIT') === 'form-create-new-event-story')
            {
                $href = '';
                $objWidget = $objForm->getWidget('event');
                $objPage = $pageModelAdapter->findByPk($model->eventStoryFormJumpTo);
                if ($objPage !== null)
                {
                    $href = $urlAdapter->addQueryString('eventId=' . $objWidget->value, $objPage->getFrontendUrl());
                }
                $controllerAdapter->redirect($href);
            }
        }

        return $objForm;
    }

    /**
     * Add messages from session to template
     */
    protected function addMessagesToTemplate(): void
    {
        $messageAdapter = $this->framework->getAdapter(Message::class);
        $systemAdapter = $this->framework->getAdapter(System::class);

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