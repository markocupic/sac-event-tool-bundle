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

use Contao\Config;
use Contao\Controller;
use Contao\CoreBundle\Controller\FrontendModule\AbstractFrontendModuleController;
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\CoreBundle\ServiceAnnotation\FrontendModule;
use Contao\Dbafs;
use Contao\Environment;
use Contao\File;
use Contao\FilesModel;
use Contao\Folder;
use Contao\FrontendUser;
use Contao\Input;
use Contao\MemberModel;
use Contao\Message;
use Contao\ModuleModel;
use Contao\PageModel;
use Contao\System;
use Contao\Template;
use Haste\Form\Form;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;
use Symfony\Component\Security\Core\Security;

/**
 * Class MemberDashboardAvatarUploadController.
 *
 * @FrontendModule(MemberDashboardAvatarUploadController::TYPE, category="sac_event_tool_frontend_modules")
 */
class MemberDashboardAvatarUploadController extends AbstractFrontendModuleController
{
    public const TYPE = 'member_dashboard_avatar_upload';

    protected ?string $projectDir;

    protected ?FrontendUser $objUser;

    protected ?Template $template;

    protected ?PageModel $objPage;

    public function __invoke(Request $request, ModuleModel $model, string $section, array $classes = null, PageModel $page = null): Response
    {
        $inputAdapter = $this->get('contao.framework')->getAdapter(Input::class);
        $controllerAdapter = $this->get('contao.framework')->getAdapter(Controller::class);
        // Get logged in member object
        if (($objUser = $this->get('security.helper')->getUser()) instanceof FrontendUser) {
            $this->objUser = $objUser;
        }

        if (null !== $page) {
            // Neither cache nor search page
            $page->noSearch = 1;
            $page->cache = 0;

            // Set the page object
            $this->objPage = $page;
        }

        // Rotate image by 90°
        if ('rotate-image' === $inputAdapter->get('do') && '' !== $inputAdapter->get('fileId')) {
            // Get the image rotate service
            $objRotateImage = $this->get('Markocupic\SacEventToolBundle\Image\RotateImage');

            $objFiles = FilesModel::findOneById($inputAdapter->get('fileId'));
            $objRotateImage->rotate($objFiles);

            $controllerAdapter->redirect($page->getFrontendUrl());
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

    /**
     * @param Template $template
     * @param ModuleModel $model
     * @param Request $request
     * @return Response|null
     * @throws \Exception
     */
    protected function getResponse(Template $template, ModuleModel $model, Request $request): ?Response
    {
        // Do not allow for not authorized users
        if (null === $this->objUser) {
            throw new UnauthorizedHttpException('Not authorized. Please log in as frontend user.');
        }

        $this->template = $template;


        $this->template->objUser = $this->objUser;



        $objUploadFolder = new Folder($this->getUploadDir());

        // Check for valid avatar image and valid upload directory
        $this->checkAvatar();

        if (!$objUploadFolder->isEmpty()) {
            $this->template->objUser->hasAvatar = true;
        }

        // Generate avatar uploader
        $this->template->avatarForm = $this->generateAvatarForm();

        // Add messages to template
        $this->addMessagesToTemplate();

        return $this->template->getResponse();
    }

    /**
     * Add messages from session to template.
     */
    private function addMessagesToTemplate(): void
    {
        $systemAdapter = $this->get('contao.framework')->getAdapter(System::class);
        $messageAdapter = $this->get('contao.framework')->getAdapter(Message::class);

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

    /**
     * @return string
     * @throws \Exception
     */
    private function generateAvatarForm(): string
    {
        // Set adapters
        $controllerAdapter = $this->get('contao.framework')->getAdapter(Controller::class);
        $inputAdapter = $this->get('contao.framework')->getAdapter(Input::class);
        $environmentAdapter = $this->get('contao.framework')->getAdapter(Environment::class);
        $filesModelAdapter = $this->get('contao.framework')->getAdapter(FilesModel::class);
        $memberModelAdapter = $this->get('contao.framework')->getAdapter(MemberModel::class);
        $dbafsAdapter = $this->get('contao.framework')->getAdapter(Dbafs::class);

        $objForm = new Form(
            'form-avatar-upload',
            'POST',
            function ($objHaste) {
                $inputAdapter = $this->get('contao.framework')->getAdapter(Input::class);

                return $inputAdapter->post('FORM_SUBMIT') === $objHaste->getFormId();
            }
        );

        $objForm->setFormActionFromUri($environmentAdapter->get('uri'));

        // Now let's add form fields:
        $objForm->addFormField('avatar', [
            'label' => 'Profilbild hochladen',
            'inputType' => 'upload',
            'eval' => ['class' => 'custom-input-file', 'mandatory' => false],
        ]);
        $objForm->addFormField('delete-avatar', [
            // Do not show the legend and display the label only
            'label' => ['', 'Profilbild löschen'],
            'inputType' => 'checkbox',
        ]);

        // Let's add  a submit button
        $objForm->addFormField('submit', [
            'label' => 'Speichern',
            'inputType' => 'submit',
        ]);

        // Create the folder if it not exists
        $objUploadFolder = new Folder($this->getUploadDir());
        $dbafsAdapter->addResource($objUploadFolder->path);

        $objWidget = $objForm->getWidget('avatar');
        $objWidget->extensions = 'jpg,jpeg,png,gif,svg';
        $objWidget->storeFile = true;
        $objWidget->uploadFolder = $filesModelAdapter->findByPath($objUploadFolder->path)->uuid;
        $objWidget->addAttribute('accept', '.jpg,.jpeg,.png,.gif,.svg');

        // Delete avatar
        if ('form-avatar-upload' === $inputAdapter->post('FORM_SUBMIT') && $inputAdapter->post('delete-avatar')) {
            $objUploadFolder->purge();
            $oMember = $memberModelAdapter->findByPk($this->objUser->id);

            if (null !== $oMember) {
                $oMember->avatar = '';
                $oMember->save();
            }
        }

        // Standardize name
        if ('form-avatar-upload' === $inputAdapter->post('FORM_SUBMIT') && !empty($_FILES['avatar']['tmp_name'])) {
            $objUploadFolder->purge();
            $objFile = new File($_FILES['avatar']['name']);
            $_FILES['avatar']['name'] = 'avatar-'.$this->objUser->id.'.'.strtolower($objFile->extension);

            // Move uploaded file so we can save the avatar uuid in tl_member.avatar
            move_uploaded_file($_FILES['avatar']['tmp_name'], TL_ROOT.'/'.$objUploadFolder->path.'/'.$_FILES['avatar']['name']);
            $dbafsAdapter->addResource($objUploadFolder->path.'/'.$_FILES['avatar']['name']);
            $fileModel = $filesModelAdapter->findByPath($objUploadFolder->path.'/'.$_FILES['avatar']['name']);
            $oMember = $memberModelAdapter->findByPk($this->objUser->id);

            if (null !== $oMember) {
                $oMember->avatar = $fileModel->uuid;
                $oMember->save();
            }
        }

        if ($objForm->validate()) {
            // Reload page after uploads
            if ('form-avatar-upload' === $inputAdapter->post('FORM_SUBMIT')) {
                $controllerAdapter->reload();
            }
        }

        return $objForm->generate();
    }

    /**
     * @throws \Exception
     */
    private function checkAvatar(): void
    {
        $hasError = false;

        // Set adapters
        $filesModelAdapter = $this->get('contao.framework')->getAdapter(FilesModel::class);

        // Check for valid avatar
        if (null !== $this->objUser) {
            if ('' !== $this->objUser->avatar) {
                $objFile = $filesModelAdapter->findByUuid($this->objUser->avatar);

                if (null === $objFile) {
                    $hasError = true;
                }

                if (!is_file($this->projectDir.'/'.$objFile->path)) {
                    $hasError = true;
                } else {
                    $oFile = new File($objFile->path);

                    if (!$oFile->isGdImage) {
                        $hasError = true;
                    }
                }

                if ($hasError) {
                    $this->objUser->avatar = '';
                    $this->objUser->save();

                    $objUploadFolder = new Folder($this->getUploadDir());
                    $objUploadFolder->purge();
                    $objUploadFolder->delete();
                }
            }
        }
    }


    private function getUploadDir(): string
    {

        return  sprintf(
            '%s/%s',
            $this->getParameter('sacevt.user.frontend.avatar_dir'),
            (string)$this->objUser->id,
        );

}
}
