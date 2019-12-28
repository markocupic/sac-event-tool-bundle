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

use Contao\Environment;
use Contao\FrontendUser;
use Contao\Input;
use Contao\Template;
use Contao\MemberModel;
use Contao\Message;
use Contao\PageModel;
use Contao\System;
use Haste\Form\Form;
use Contao\ModuleModel;
use Contao\CoreBundle\Controller\FrontendModule\AbstractFrontendModuleController;
use Contao\CoreBundle\Framework\ContaoFramework;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;
use Symfony\Component\Security\Core\Security;
use Contao\CoreBundle\ServiceAnnotation\FrontendModule;

/**
 * Class MemberDashboardEditProfileController
 * @package Markocupic\SacEventToolBundle\Controller\FrontendModule
 * @FrontendModule(category="sac_event_tool_frontend_modules", type="member_dashboard_edit_profile")
 */
class MemberDashboardEditProfileController extends AbstractFrontendModuleController
{

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
     * @param Request $request
     * @param ModuleModel $model
     * @param string $section
     * @param array|null $classes
     * @param PageModel|null $page
     * @return Response
     */
    public function __invoke(Request $request, ModuleModel $model, string $section, array $classes = null, PageModel $page = null): Response
    {
        // Get logged in member object
        if (($objUser = $this->get('security.helper')->getUser()) instanceof FrontendUser)
        {
            $this->objUser = $objUser;
        }

        if ($page !== null)
        {
            // Neither cache nor search page
            $page->noSearch = 1;
            $page->cache = 0;

            // Set the page object
            $this->objPage = $page;
        }

        $this->projectDir = System::getContainer()->getParameter('kernel.project_dir');

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
        // Do not allow for not authorized users
        if ($this->objUser === null)
        {
            throw new UnauthorizedHttpException('Not authorized. Please log in as frontend user.');
        }

        $this->template = $template;

        $this->template->objUser = $this->objUser;

        // Generate the my profile form
        $this->template->userProfileForm = $this->generateUserProfileForm();

        // Add messages to template
        $this->addMessagesToTemplate();

        return $this->template->getResponse();
    }

    /**
     * Add messages from session to template
     */
    protected function addMessagesToTemplate(): void
    {
        $systemAdapter = $this->get('contao.framework')->getAdapter(System::class);
        $messageAdapter = $this->get('contao.framework')->getAdapter(Message::class);

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
     * Generate the avatar upload form
     * @return Form
     */
    protected function generateUserProfileForm()
    {
        // Set adapters
        $environmentAdapter = $this->get('contao.framework')->getAdapter(Environment::class);
        $memberModelAdapter = $this->get('contao.framework')->getAdapter(MemberModel::class);

        $objForm = new Form('form-user-profile', 'POST', function ($objHaste) {
            $inputAdapter = $this->get('contao.framework')->getAdapter(Input::class);
            return $inputAdapter->post('FORM_SUBMIT') === $objHaste->getFormId();
        });

        $objForm->setFormActionFromUri($environmentAdapter->get('uri'));

        // Now let's add form fields:
        $objForm->addFormField('emergencyPhone', array(
            'label'     => 'Notfallnummer',
            'inputType' => 'text',
            'eval'      => array('rgxp' => 'phone', 'mandatory' => true),
        ));
        $objForm->addFormField('emergencyPhoneName', array(
            'label'     => 'Name und Bezug des Angeh&ouml;rigen',
            'inputType' => 'text',
            'eval'      => array('mandatory' => true),
        ));
        $objForm->addFormField('foodHabits', array(
            'label'     => 'Essgewohnheiten (Vegetarier, Laktoseintoleranz, etc.)',
            'inputType' => 'text',
            'eval'      => array('mandatory' => false),
        ));

        // Let's add  a submit button
        $objForm->addFormField('submit', array(
            'label'     => 'Speichern',
            'inputType' => 'submit',
        ));

        // Get form presets from tl_member
        $arrFields = array('emergencyPhone', 'emergencyPhoneName', 'foodHabits');
        foreach ($arrFields as $field)
        {
            $objWidget = $objForm->getWidget($field);
            if ($objWidget->value == '')
            {
                $objWidget = $objForm->getWidget($field);
                $objWidget->value = $this->objUser->{$field};
            }
        }

        // Bind form to the MemberModel
        $objModel = $memberModelAdapter->findByPk($this->objUser->id);
        $objForm->bindModel($objModel);

        if ($objForm->validate())
        {
            // The model will now contain the changes so you can save it
            $objModel->save();
        }

        return $objForm->generate();
    }

}
