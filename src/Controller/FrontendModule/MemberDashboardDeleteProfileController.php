<?php

/**
 * SAC Event Tool Web Plugin for Contao
 * Copyright (c) 2008-2020 Marko Cupic
 * @package sac-event-tool-bundle
 * @author Marko Cupic m.cupic@gmx.ch, 2017-2020
 * @link https://github.com/markocupic/sac-event-tool-bundle
 */

namespace Markocupic\SacEventToolBundle\Controller\FrontendModule;

use Contao\Controller;
use Contao\CoreBundle\Controller\FrontendModule\AbstractFrontendModuleController;
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\CoreBundle\Routing\ScopeMatcher;
use Contao\Environment;
use Contao\FrontendUser;
use Contao\Input;
use Contao\Message;
use Contao\ModuleModel;
use Contao\PageModel;
use Contao\System;
use Contao\Template;
use Haste\Form\Form;
use Markocupic\SacEventToolBundle\ClearPersonalMemberData;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;
use Symfony\Component\Security\Core\Security;
use Contao\CoreBundle\ServiceAnnotation\FrontendModule;

/**
 * Class MemberDashboardDeleteProfileController
 * @package Markocupic\SacEventToolBundle\Controller\FrontendModule
 * @FrontendModule(category="sac_event_tool_fe_modules", type="member_dashboard_delete_profile")
 */
class MemberDashboardDeleteProfileController extends AbstractFrontendModuleController
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
     * @var PageModel
     */
    protected $objPage;

    /**
     * MemberDashboardDeleteProfileController constructor.
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

            // Neither cache nor search page
            $page->noSearch = 1;
            $page->cache = 0;

            // Set the page object
            $this->objPage = $page;

            if ($this->objUser === null)
            {
                throw new UnauthorizedHttpException();
            }
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
        // Set adapters
        $inputAdapter = $this->framework->getAdapter(Input::class);

        $this->template = $template;

        $this->template->objUser = $this->objUser;

        if ($inputAdapter->get('action') === 'clear-profile')
        {
            // Generate the my profileform
            $this->template->deleteProfileForm = $this->generateDeleteProfileForm();
            $this->template->passedConfirmation = true;
        }

        // Add messages to template
        $this->addMessagesToTemplate();

        return $this->template->getResponse();
    }

    /**
     * Identify the Contao scope (TL_MODE) of the current request
     * @return bool
     */
    protected function isFrontend(): bool
    {
        return $this->requestStack->getCurrentRequest() !== null ? $this->scopeMatcher->isFrontendRequest($this->requestStack->getCurrentRequest()) : false;
    }

    /**
     * Add messages from session to template
     */
    protected function addMessagesToTemplate(): void
    {
        $systemAdapter = $this->framework->getAdapter(System::class);
        $messageAdapter = $this->framework->getAdapter(Message::class);

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

    /**
     * @return string|void
     */
    protected function generateDeleteProfileForm()
    {
        // Set adapters
        $controllerAdapter = $this->framework->getAdapter(Controller::class);
        $inputAdapter = $this->framework->getAdapter(Input::class);
        $environmentAdapter = $this->framework->getAdapter(Environment::class);
        $clearPersonalMemberDataAdapter = $this->framework->getAdapter(ClearPersonalMemberData::class);

        $objForm = new Form('form-clear-profile', 'POST', function ($objHaste) {
            $inputAdapter = $this->framework->getAdapter(Input::class);
            return $inputAdapter->post('FORM_SUBMIT') === $objHaste->getFormId();
        });

        $objForm->setFormActionFromUri($environmentAdapter->get('uri'));

        // Now let's add form fields:
        // Now let's add form fields:
        $objForm->addFormField('deleteProfile', array(
            'label'     => array('Profil löschen', ''),
            'inputType' => 'select',
            'options'   => array('false' => 'Nein', 'true' => 'Ja'),
        ));

        $objForm->addFormField('sacMemberId', array(
            'label'     => array('SAC-Mitgliedernummer', ''),
            'inputType' => 'text',
        ));

        // Let's add  a submit button
        $objForm->addFormField('submit', array(
            'label'     => 'Profil unwiederkehrlich löschen',
            'inputType' => 'submit',
        ));

        if ($objForm->validate())
        {
            if ($inputAdapter->post('FORM_SUBMIT') === 'form-clear-profile')
            {
                $blnError = false;
                if ($inputAdapter->post('deleteProfile') !== 'true')
                {
                    $blnError = true;
                    $objFormField1 = $objForm->getWidget('deleteProfile');
                    $objFormField1->addError('Falsche Eingabe. Das Profil konnte nicht gelöscht werden.');
                }
                if ($inputAdapter->post('sacMemberId') != $this->objUser->sacMemberId)
                {
                    $blnError = true;
                    $objFormField2 = $objForm->getWidget('sacMemberId');
                    $objFormField2->addError('Das Profil konnte nicht gelöscht werden. Die Mitgliedernummer ist falsch.');
                }

                if (!$blnError)
                {
                    // Clear account
                    $clearPersonalMemberDataAdapter->clearMemberProfile($this->objUser->id);
                    $clearPersonalMemberDataAdapter->disableLogin($this->objUser->id);
                    $clearPersonalMemberDataAdapter->deleteFrontendAccount($this->objUser->id);
                    $controllerAdapter->redirect('');
                }
            }
        }

        return $objForm->generate();
    }

}
