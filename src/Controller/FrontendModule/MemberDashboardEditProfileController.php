<?php

/**
 * SAC Event Tool Web Plugin for Contao
 * Copyright (c) 2008-2019 Marko Cupic
 * @package sac-event-tool-bundle
 * @author Marko Cupic m.cupic@gmx.ch, 2017-2019
 * @link https://github.com/markocupic/sac-event-tool-bundle
 */

namespace Markocupic\SacEventToolBundle\Controller\FrontendModule;

use Contao\Config;
use Contao\Controller;
use Contao\Database;
use Contao\Dbafs;
use Contao\Environment;
use Contao\File;
use Contao\FilesModel;
use Contao\Folder;
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
use Contao\CoreBundle\Routing\ScopeMatcher;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;
use Symfony\Component\Security\Core\Security;
use Contao\CoreBundle\ServiceAnnotation\FrontendModule;

/**
 * Class MemberDashboardEditProfileController
 * @package Markocupic\SacEventToolBundle\Controller\FrontendModule
 * @FrontendModule(category="sac_event_tool_fe_modules", type="member_dashboard_edit_profile")
 */
class MemberDashboardEditProfileController extends AbstractFrontendModuleController
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
     * MemberDashboardEditProfileController constructor.
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

            // Set the page object
            $this->objPage = $page;
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
        $systemAdapter = $this->framework->getAdapter(System::class);
        $configAdapter = $this->framework->getAdapter(Config::class);

        $this->checkAvatar();

        // Load languages
        $systemAdapter->loadLanguageFile('tl_calendar_events_member');

        $this->template->objUser = $this->objUser;

        $objUploadFolder = new Folder($configAdapter->get('SAC_EVT_FE_USER_AVATAR_DIRECTORY') . '/' . $this->objUser->id);
        if (!$objUploadFolder->isEmpty())
        {
            $this->template->objUser->hasAvatar = true;
        }

        // Generate avatar uploader
        $this->template->avatarForm = $this->generateAvatarForm();

        // Generate the my profile form
        $this->template->userProfileForm = $this->generateUserProfileForm();

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
        return $this->scopeMatcher->isFrontendRequest($this->requestStack->getCurrentRequest());
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
     * Generate the avatar upload form
     * @return Form
     */
    protected function generateAvatarForm()
    {
        // Set adapters
        $controllerAdapter = $this->framework->getAdapter(Controller::class);
        $inputAdapter = $this->framework->getAdapter(Input::class);
        $configAdapter = $this->framework->getAdapter(Config::class);
        $environmentAdapter = $this->framework->getAdapter(Environment::class);
        $filesModelAdapter = $this->framework->getAdapter(FilesModel::class);
        $memberModelAdapter = $this->framework->getAdapter(MemberModel::class);
        $dbafsAdapter = $this->framework->getAdapter(Dbafs::class);

        $objForm = new Form('form-avatar-upload', 'POST', function ($objHaste) {
            $inputAdapter = $this->framework->getAdapter(Input::class);
            return $inputAdapter->post('FORM_SUBMIT') === $objHaste->getFormId();
        });

        $objForm->setFormActionFromUri($environmentAdapter->get('uri'));

        // Now let's add form fields:
        $objForm->addFormField('avatar', array(
            'label'     => 'Profilbild hochladen',
            'inputType' => 'upload',
            'eval'      => array('class' => 'custom-input-file', 'mandatory' => false),
        ));
        $objForm->addFormField('delete-avatar', array(
            'label'     => array('', 'Profilbild l&ouml;schen'),
            'inputType' => 'checkbox',
        ));

        // Let's add  a submit button
        $objForm->addFormField('submit', array(
            'label'     => 'Speichern',
            'inputType' => 'submit',
        ));

        // Create the folder if it not exists
        $objUploadFolder = new Folder($configAdapter->get('SAC_EVT_FE_USER_AVATAR_DIRECTORY') . '/' . $this->objUser->id);
        $dbafsAdapter->addResource($objUploadFolder->path);

        $objWidget = $objForm->getWidget('avatar');
        $objWidget->extensions = 'jpg,jpeg,png,gif,svg';
        $objWidget->storeFile = true;
        $objWidget->uploadFolder = $filesModelAdapter->findByPath($objUploadFolder->path)->uuid;
        $objWidget->addAttribute('accept', '.jpg,.jpeg,.png,.gif,.svg');

        // Delete avatar
        if ($inputAdapter->post('FORM_SUBMIT') === 'form-avatar-upload' && $inputAdapter->post('delete-avatar'))
        {
            $objUploadFolder->purge();
            $oMember = $memberModelAdapter->findByPk($this->objUser->id);
            if ($oMember !== null)
            {
                $oMember->avatar = '';
                $oMember->save();
            }
        }

        // Standardize name
        if ($inputAdapter->post('FORM_SUBMIT') === 'form-avatar-upload' && !empty($_FILES['avatar']['tmp_name']))
        {
            $objUploadFolder->purge();
            $objFile = new File($_FILES['avatar']['name']);
            $_FILES['avatar']['name'] = 'avatar-' . $this->objUser->id . '.' . strtolower($objFile->extension);

            // Move uploaded file so we can save the avatar uuid in tl_member.avatar
            move_uploaded_file($_FILES['avatar']['tmp_name'], TL_ROOT . '/' . $objUploadFolder->path . '/' . $_FILES['avatar']['name']);
            $dbafsAdapter->addResource($objUploadFolder->path . '/' . $_FILES['avatar']['name']);
            $fileModel = $filesModelAdapter->findByPath($objUploadFolder->path . '/' . $_FILES['avatar']['name']);
            $oMember = $memberModelAdapter->findByPk($this->objUser->id);
            if ($oMember !== null)
            {
                $oMember->avatar = $fileModel->uuid;
                $oMember->save();
            }
        }

        if ($objForm->validate())
        {
            // Reload page after uploads
            if ($inputAdapter->post('FORM_SUBMIT') === 'form-avatar-upload')
            {
                $controllerAdapter->reload();
            }
        }

        return $objForm->generate();
    }

    /**
     * Generate the avatar upload form
     * @return Form
     */
    protected function generateUserProfileForm()
    {
        // Set adapters
        $environmentAdapter = $this->framework->getAdapter(Environment::class);
        $memberModelAdapter = $this->framework->getAdapter(MemberModel::class);

        $objForm = new Form('form-user-profile', 'POST', function ($objHaste) {
            $inputAdapter = $this->framework->getAdapter(Input::class);
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

    /**
     * @throws \Exception
     */
    protected function checkAvatar(): void
    {
        // Set adapters
        $configAdapter = $this->framework->getAdapter(Config::class);
        $filesModelAdapter = $this->framework->getAdapter(FilesModel::class);
        $memberModelAdapter = $this->framework->getAdapter(MemberModel::class);

        // Check for valid avatar
        $oMember = $memberModelAdapter->findByPk($this->objUser->id);
        if ($oMember !== null)
        {
            if ($oMember->avatar != '')
            {
                $objFile = $filesModelAdapter->findByUuid($oMember->avatar);
                if ($objFile === null)
                {
                    $hasError = true;
                }
                if (!is_file($this->projectDir . '/' . $objFile->path))
                {
                    $hasError = true;
                }
                if ($hasError)
                {
                    $oMember->avatar = '';
                    $oMember->save();
                    $objUploadFolder = new Folder($configAdapter->get('SAC_EVT_FE_USER_AVATAR_DIRECTORY') . '/' . $this->objUser->id);
                    if ($objUploadFolder !== null)
                    {
                        $objUploadFolder->purge();
                        $objUploadFolder->delete();
                    }
                }
            }
        }
    }

}
