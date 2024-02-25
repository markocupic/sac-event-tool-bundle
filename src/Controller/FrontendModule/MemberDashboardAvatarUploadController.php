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
use Contao\Controller;
use Contao\CoreBundle\Controller\FrontendModule\AbstractFrontendModuleController;
use Contao\CoreBundle\DependencyInjection\Attribute\AsFrontendModule;
use Contao\CoreBundle\Framework\ContaoFramework;
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
use Contao\Template;
use Markocupic\SacEventToolBundle\Avatar\Avatar;
use Markocupic\SacEventToolBundle\Image\RotateImage;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;

#[AsFrontendModule(MemberDashboardAvatarUploadController::TYPE, category:'sac_event_tool_frontend_modules', template:'mod_member_dashboard_avatar_upload')]
class MemberDashboardAvatarUploadController extends AbstractFrontendModuleController
{
    public const TYPE = 'member_dashboard_avatar_upload';

    private FrontendUser|null $user;
    private Template|null $template;

    public function __construct(
        private readonly ContaoFramework $framework,
        private readonly Security $security,
        private readonly RotateImage $rotateImage,
        private readonly Avatar $avatar,
        private readonly string $projectDir,
        private readonly string $sacevtUserFrontendAvatarDir,
    ) {
    }

    public function __invoke(Request $request, ModuleModel $model, string $section, array $classes = null, PageModel $page = null): Response
    {
        $inputAdapter = $this->framework->getAdapter(Input::class);
        $controllerAdapter = $this->framework->getAdapter(Controller::class);

        // Get logged in member object
        if (($user = $this->security->getUser()) instanceof FrontendUser) {
            $this->user = $user;
        }

        if (null !== $page) {
            // Neither cache nor search page
            $page->noSearch = 1;
            $page->cache = 0;
        }

        // Rotate image by 90°
        if ('rotate-image' === $inputAdapter->get('do') && '' !== $inputAdapter->get('fileId')) {
            $objFiles = FilesModel::findOneById($inputAdapter->get('fileId'));
            $this->rotateImage->rotate($objFiles, 90);

            $controllerAdapter->redirect($page->getFrontendUrl());
        }

        // Call the parent method
        return parent::__invoke($request, $model, $section, $classes);
    }

    /**
     * @throws \Exception
     */
    protected function getResponse(Template $template, ModuleModel $model, Request $request): Response
    {
        // Do not allow for not authorized users
        if (null === $this->user) {
            throw new UnauthorizedHttpException('Not authorized. Please log in as frontend user.');
        }
        $memberModelAdapter = $this->framework->getAdapter(MemberModel::class);
        $filesModelAdapter = $this->framework->getAdapter(FilesModel::class);

        $this->template = $template;

        $user = $memberModelAdapter->findByPk($this->user->id);

        $arrUser = $user->row();

        $objUploadFolder = new Folder($this->getUploadDir());

        // Check for valid avatar image and valid upload directory
        $this->checkAvatar();

        $arrUser['hasAvatar'] = false;

        if (!$objUploadFolder->isEmpty()) {
            $filesModel = $filesModelAdapter->findByPath($this->avatar->getAvatarResourcePath($user));

            if (null !== $filesModel) {
                $template->avatar = $filesModel->row();
                $arrUser['hasAvatar'] = true;
            }
        }

        // Generate avatar uploader
        $this->template->avatarForm = $this->generateAvatarForm();

        $template->user = $arrUser;
        $template->userModel = $user;

        // Add messages to template
        $this->addMessagesToTemplate($request);

        return $this->template->getResponse();
    }

    private function getUploadDir(): string
    {
        return sprintf(
            '%s/%s',
            $this->sacevtUserFrontendAvatarDir,
            $this->user->id,
        );
    }

    /**
     * @throws \Exception
     */
    private function checkAvatar(): void
    {
        $hasError = false;

        // Set adapters
        $filesModelAdapter = $this->framework->getAdapter(FilesModel::class);

        // Check for valid avatar
        if (null !== $this->user) {
            if ($this->user->avatar) {
                $objFile = $filesModelAdapter->findByUuid($this->user->avatar);

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
                    $this->user->avatar = '';
                    $this->user->save();
                    $objUploadFolder = new Folder($this->getUploadDir());
                    $objUploadFolder->purge();
                    $objUploadFolder->delete();
                }
            }
        }
    }

    /**
     * @throws \Exception
     */
    private function generateAvatarForm(): string
    {
        // Set adapters
        $controllerAdapter = $this->framework->getAdapter(Controller::class);
        $inputAdapter = $this->framework->getAdapter(Input::class);
        $environmentAdapter = $this->framework->getAdapter(Environment::class);
        $filesModelAdapter = $this->framework->getAdapter(FilesModel::class);
        $memberModelAdapter = $this->framework->getAdapter(MemberModel::class);
        $dbafsAdapter = $this->framework->getAdapter(Dbafs::class);

        $objForm = new Form(
            'form-avatar-upload',
            'POST',
        );

        $objForm->setAction($environmentAdapter->get('uri'));

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
            $oMember = $memberModelAdapter->findByPk($this->user->id);

            if (null !== $oMember) {
                $oMember->avatar = '';
                $oMember->save();
            }
        }

        // Standardize name
        if ('form-avatar-upload' === $inputAdapter->post('FORM_SUBMIT') && !empty($_FILES['avatar']['tmp_name'])) {
            $objUploadFolder->purge();

            $objFile = new File($_FILES['avatar']['name']);

            // Rename upload to avatar-<user-id>.jpg
            $strAvatarFileName = 'avatar-'.$this->user->id.'.'.strtolower($objFile->extension);

            // Move uploaded file, so we can save the avatar uuid in tl_member.avatar
            move_uploaded_file($_FILES['avatar']['tmp_name'], $this->projectDir.'/'.$objUploadFolder->path.'/'.$strAvatarFileName);

            // Add file to DBAFS
            $dbafsAdapter->addResource($objUploadFolder->path.'/'.$strAvatarFileName);

            $fileModel = $filesModelAdapter->findByPath($objUploadFolder->path.'/'.$strAvatarFileName);

            $oMember = $memberModelAdapter->findByPk($this->user->id);

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
     * Add messages from session to template.
     */
    private function addMessagesToTemplate(Request $request): void
    {
        $messageAdapter = $this->framework->getAdapter(Message::class);

        $this->template->hasInfoMessage = false;
        $this->template->hasErrorMessage = false;

        $session = $request->getSession();

        if ($messageAdapter->hasInfo()) {
            $this->template->hasInfoMessage = true;
            $bag = $session->getFlashBag()->get('contao.FE.info');
            $this->template->infoMessage = $bag[0];
            $this->template->infoMessages = $bag;
        }

        if ($messageAdapter->hasError()) {
            $this->template->hasErrorMessage = true;
            $bag = $session->getFlashBag()->get('contao.FE.error');
            $this->template->errorMessage = $bag[0];
            $this->template->errorMessages = $bag;
        }

        $messageAdapter->reset();
    }
}
