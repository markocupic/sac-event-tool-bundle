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

use Contao\CalendarEventsMemberModel;
use Contao\CalendarEventsModel;
use Contao\Config;
use Contao\Controller;
use Contao\CoreBundle\Controller\FrontendModule\AbstractFrontendModuleController;
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\CoreBundle\ServiceAnnotation\FrontendModule;
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
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;
use Symfony\Component\Security\Core\Security;

/**
 * @FrontendModule(MemberDashboardEventBlogListController::TYPE, category="sac_event_tool_frontend_modules")
 */
class MemberDashboardEventBlogListController extends AbstractFrontendModuleController
{
    public const TYPE = 'member_dashboard_event_blog_list';

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

    public function __invoke(Request $request, ModuleModel $model, string $section, array $classes = null, PageModel $page = null): Response
    {
        // Get logged in member object
        if (($objUser = $this->get('security.helper')->getUser()) instanceof FrontendUser) {
            $this->objUser = $objUser;
        }

        if (null !== $page) {
            // Neither cache nor search page
            $page->noSearch = 1;
            $page->cache = 0;
        }

        $this->projectDir = $this->getParameter('kernel.project_dir');

        // Call the parent method
        return parent::__invoke($request, $model, $section, $classes);
    }

    public static function getSubscribedServices(): array
    {
        $services = parent::getSubscribedServices();

        $services['contao.framework'] = ContaoFramework::class;
        $services['security.helper'] = Security::class;

        return $services;
    }

    protected function getResponse(Template $template, ModuleModel $model, Request $request): Response|null
    {
        // Do not allow for not authorized users
        if (null === $this->objUser) {
            throw new UnauthorizedHttpException('Not authorized. Please log in as frontend user.');
        }

        $this->template = $template;

        // Set adapters
        $messageAdapter = $this->get('contao.framework')->getAdapter(Message::class);
        $validatorAdapter = $this->get('contao.framework')->getAdapter(Validator::class);

        // Handle messages
        if (empty($this->objUser->email) || !$validatorAdapter->isEmail($this->objUser->email)) {
            $messageAdapter->addInfo('Leider wurde für dieses Konto in der Datenbank keine E-Mail-Adresse gefunden. Daher stehen einige Funktionen nur eingeschränkt zur Verfügung. Bitte hinterlegen Sie auf der Internetseite des Zentralverbands Ihre E-Mail-Adresse.');
        }

        // Get the time span for creating a new event blog
        $this->template->eventBlogTimeSpanForCreatingNew = $model->eventBlogTimeSpanForCreatingNew;

        // Add messages to template
        $this->addMessagesToTemplate();
        $objForm = $this->generateCreateNewEventBlogForm($model);
        $this->template->newEventBlogForm = $objForm->generate();

        // Get event report list
        $this->template->arrEventBlogs = $this->getEventBlogs($model);

        return $this->template->getResponse();
    }

    protected function getEventBlogs(ModuleModel $model): array
    {
        // Set adapters
        $calendarEventsModelAdapter = $this->get('contao.framework')->getAdapter(CalendarEventsModel::class);
        $dateAdapter = $this->get('contao.framework')->getAdapter(Date::class);
        $calendarEventsHelperAdapter = $this->get('contao.framework')->getAdapter(CalendarEventsHelper::class);
        $configAdapter = $this->get('contao.framework')->getAdapter(Config::class);
        $databaseAdapter = $this->get('contao.framework')->getAdapter(Database::class);
        $pageModelAdapter = $this->get('contao.framework')->getAdapter(PageModel::class);
        $urlAdapter = $this->get('contao.framework')->getAdapter(Url::class);

        $arrEventBlogs = [];

        if (null !== $this->objUser) {
            // Event blogs
            $objEventBlog = $databaseAdapter->getInstance()
                ->prepare('SELECT * FROM tl_calendar_events_blog WHERE sacMemberId=? ORDER BY eventStartDate DESC')
                ->execute($this->objUser->sacMemberId)
            ;

            while ($objEventBlog->next()) {
                $arrEventBlog = $objEventBlog->row();

                // Defaults
                $arrEventBlog['date'] = $dateAdapter->parse($configAdapter->get('dateFormat'), $objEventBlog->eventStartDate);
                $arrEventBlog['canEditBlog'] = false;
                $arrEventBlog['blogLink'] = '';

                // Check if the event blog is still editable
                if ($objEventBlog->eventEndDate + $model->eventBlogTimeSpanForCreatingNew * 24 * 60 * 60 > time()) {
                    if ('1' === $objEventBlog->publishState) {
                        $arrEventBlog['canEditBlog'] = true;
                    }
                }

                // Check if event still exists
                if (($objEvent = $calendarEventsModelAdapter->findByPk($objEventBlog->eventId)) !== null) {
                    // Overwrite date if event still exists in tl_calendar_events
                    $arrEventBlog['date'] = $calendarEventsHelperAdapter->getEventPeriod($objEvent, $configAdapter->get('dateFormat'), false);
                    $objPage = $pageModelAdapter->findByPk($model->eventBlogFormJumpTo);

                    if (null !== $objPage) {
                        $arrEventBlog['blogLink'] = $urlAdapter->addQueryString('eventId='.$objEventBlog->eventId, $objPage->getFrontendUrl());
                    }
                }
                $arrEventBlogs[] = $arrEventBlog;
            }
        }

        return $arrEventBlogs;
    }

    protected function generateCreateNewEventBlogForm(ModuleModel $model): Form
    {
        // Set adapters
        $calendarEventsMemberModelAdapter = $this->get('contao.framework')->getAdapter(CalendarEventsMemberModel::class);
        $environmentAdapter = $this->get('contao.framework')->getAdapter(Environment::class);
        $controllerAdapter = $this->get('contao.framework')->getAdapter(Controller::class);
        $urlAdapter = $this->get('contao.framework')->getAdapter(Url::class);
        $inputAdapter = $this->get('contao.framework')->getAdapter(Input::class);
        $pageModelAdapter = $this->get('contao.framework')->getAdapter(PageModel::class);

        $objForm = new Form(
            'form-create-new-event-blog',
            'POST',
            function ($objHaste) {
                $inputAdapter = $this->get('contao.framework')->getAdapter(Input::class);

                return $inputAdapter->post('FORM_SUBMIT') === $objHaste->getFormId();
            }
        );

        $objForm->setFormActionFromUri($environmentAdapter->get('uri'));

        $arrOptions = [];
        $intStartDateMin = $model->eventBlogTimeSpanForCreatingNew > 0 ? time() - $model->eventBlogTimeSpanForCreatingNew * 24 * 3600 : time();
        $arrEvents = $calendarEventsMemberModelAdapter->findEventsByMemberId($this->objUser->id, [], $intStartDateMin, time(), true);

        if (!empty($arrEvents) && \is_array($arrEvents)) {
            foreach ($arrEvents as $event) {
                if (null !== $event['objEvent']) {
                    $objEvent = $event['objEvent'];
                    $arrOptions[$event['id']] = $objEvent->title;
                }
            }
        }

        // Now let's add form fields:
        $objForm->addFormField('event', [
            'label' => 'Tourenbericht zu einem Event erstellen',
            'inputType' => 'select',
            'options' => $arrOptions,
            'eval' => ['mandatory' => true],
        ]);

        // Let's add  a submit button
        $objForm->addFormField('submit', [
            'label' => 'Weiter',
            'inputType' => 'submit',
        ]);

        if ($objForm->validate()) {
            // Redirect to the page with the event report form
            if ('form-create-new-event-blog' === $inputAdapter->post('FORM_SUBMIT')) {
                $href = '';
                $objWidget = $objForm->getWidget('event');
                $objPage = $pageModelAdapter->findByPk($model->eventBlogFormJumpTo);

                if (null !== $objPage) {
                    $href = $urlAdapter->addQueryString('eventId='.$objWidget->value, $objPage->getFrontendUrl());
                }
                $controllerAdapter->redirect($href);
            }
        }

        return $objForm;
    }

    /**
     * Add messages from session to template.
     */
    protected function addMessagesToTemplate(): void
    {
        $messageAdapter = $this->get('contao.framework')->getAdapter(Message::class);
        $systemAdapter = $this->get('contao.framework')->getAdapter(System::class);

        if ($messageAdapter->hasInfo()) {
            $this->template->hasInfoMessage = true;
            $session = $systemAdapter->getContainer()->get('session')->getFlashBag()->get('contao.FE.info');
            $this->template->infoMessage = $session[0];
        }

        if ($messageAdapter->hasError()) {
            $this->template->hasErrorMessage = true;
            $session = $systemAdapter->getContainer()->get('session')->getFlashBag()->get('contao.FE.error');
            $this->template->errorMessage = $session[0];
            $this->template->errorMessages = $session;
        }

        $messageAdapter->reset();
    }
}
