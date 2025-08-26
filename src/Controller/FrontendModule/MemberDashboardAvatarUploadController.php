<?php

declare(strict_types=1);

/*
 * This file is part of SAC Event Tool Bundle.
 *
 * (c) Marko Cupic <m.cupic@gmx.ch>
 * @license GPL-3.0-or-later
 * For the full copyright and license information,
 * please view the LICENSE file that was distributed with this source code.
 * @link https://github.com/markocupic/sac-event-tool-bundle
 */

namespace Markocupic\SacEventToolBundle\Controller\FrontendModule;

use Codefog\HasteBundle\Form\Form;
use Codefog\HasteBundle\UrlParser;
use Contao\Controller;
use Contao\CoreBundle\Controller\FrontendModule\AbstractFrontendModuleController;
use Contao\CoreBundle\DependencyInjection\Attribute\AsFrontendModule;
use Contao\CoreBundle\Exception\PageNotFoundException;
use Contao\CoreBundle\Exception\ResponseException;
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\CoreBundle\Twig\FragmentTemplate;
use Contao\Dbafs;
use Contao\Environment;
use Contao\File;
use Contao\FilesModel;
use Contao\Folder;
use Contao\FrontendUser;
use Contao\MemberModel;
use Contao\Message;
use Contao\ModuleModel;
use Contao\PageModel;
use Markocupic\SacEventToolBundle\Download\BinaryFileDownload;
use Markocupic\SacEventToolBundle\Image\RotateImage;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Filesystem\Path;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\UriSigner;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;

#[AsFrontendModule(MemberDashboardAvatarUploadController::TYPE, category: 'sac_event_tool_frontend_modules', template: 'mod_member_dashboard_avatar_upload')]
class MemberDashboardAvatarUploadController extends AbstractFrontendModuleController
{
    public const string TYPE = 'member_dashboard_avatar_upload';

    private FrontendUser|null $user;

    private FragmentTemplate|null $template;

    public function __construct(
        private readonly BinaryFileDownload $binaryFileDownload,
        private readonly ContaoFramework $framework,
        private readonly RotateImage $rotateImage,
        private readonly Security $security,
        private readonly UriSigner $uriSigner,
        private readonly UrlParser $urlParser,
        private readonly string $projectDir,
        private readonly string $sacevtUserFrontendAvatarDir,
        #[Autowire('%contao.image.valid_extensions%')]
        private readonly array $validExtensions,
    ) {
    }

    public function __invoke(Request $request, ModuleModel $model, string $section, array|null $classes = null, PageModel|null $page = null): Response
    {
        if (($user = $this->security->getUser()) instanceof FrontendUser) {
            $this->user = $user;
        }

        if (null !== $page) {
            // Neither cache nor search page
            $page->noSearch = 1;
            $page->cache = 0;
        }

        if (null !== $this->user && \in_array($request->query->get('do'), ['download-image', 'rotate-image', 'delete-image'], true)) {
            // This will also call $this->hasValidAvatar()
            $files = $this->getUserAvatarFile();

            if (null === $files || !$this->uriSigner->check($request->getUri())) {
                return throw new PageNotFoundException('Page not found: '.$request->getUri());
            }

            if ('rotate-image' === $request->query->get('do')) {
                $this->rotateImage->rotate($files, 90);
                $this->framework->getAdapter(Controller::class)->redirect($page->getFrontendUrl());
            }

            if ('download-image' === $request->query->get('do')) {
                return throw new ResponseException($this->download($files));
            }

            if ('delete-image' === $request->query->get('do')) {
                $this->deleteAvatar();
                $this->framework->getAdapter(Controller::class)->redirect($page->getFrontendUrl());
            }
        }

        return parent::__invoke($request, $model, $section, $classes);
    }

    /**
     * @throws \Exception
     */
    protected function getResponse(FragmentTemplate $template, ModuleModel $model, Request $request): Response
    {
        // Do not allow for not authorized users
        if (null === $this->user) {
            throw new UnauthorizedHttpException('Not authorized. Please log in as frontend user.');
        }

        // Check for valid avatar image and valid upload directory
        $this->tidyAvatar();

        $user = $this->framework->getAdapter(MemberModel::class)->findById($this->user->id);

        $this->template = $template;
        $template->set('user', $user->row());
        $template->set('userModel', $user);
        $template->set('has_avatar', $this->hasValidAvatar());

        // This will also call $this->hasValidAvatar()
        $filesModel = $this->getUserAvatarFile();

        if (null !== $filesModel) {
            $template->set('avatar', $filesModel->row());
            $template->set('rotate_image_url', $this->uriSigner->sign($this->urlParser->addQueryString('do=rotate-image')));
            $template->set('download_image_url', $this->uriSigner->sign($this->urlParser->addQueryString('do=download-image')));
            $template->set('delete_image_url', $this->uriSigner->sign($this->urlParser->addQueryString('do=delete-image')));
        } else {
            $this->template->form = $this->generateAvatarForm($request);
        }

        // Add messages to template
        $this->addMessagesToTemplate($request);

        return $this->template->getResponse();
    }

    protected function deleteAvatar(): void
    {
        if (!$this->hasValidAvatar()) {
            return;
        }

        (new Folder($this->getUserAvatarUploadDir()))->purge();

        $file = $this->getUserAvatarFile();
        $file?->delete();
        $this->user->avatar = '';
        $this->user->save();
    }

    private function getUserAvatarFile(): FilesModel|null
    {
        if (!$this->hasValidAvatar()) {
            return null;
        }

        return $this->framework->getAdapter(FilesModel::class)->findOneByUuid($this->user->avatar);
    }

    private function getUserAvatarUploadDir(): string
    {
        return \sprintf(
            '%s/%s',
            $this->sacevtUserFrontendAvatarDir,
            $this->user->id,
        );
    }

    /**
     * @throws \Exception
     */
    private function tidyAvatar(): void
    {
        if ($this->hasValidAvatar()) {
            return;
        }

        $this->user->avatar = '';
        $this->user->save();
        $objUploadFolder = new Folder($this->getUserAvatarUploadDir());
        $objUploadFolder->purge();
        $objUploadFolder->delete();
    }

    private function hasValidAvatar(): bool
    {
        // Check for valid avatar
        if (null === $this->user) {
            return false;
        }

        if (empty($this->user->avatar)) {
            return false;
        }

        $files = $this->framework->getAdapter(FilesModel::class)->findByUuid($this->user->avatar);

        if (null === $files) {
            return false;
        }

        if (!is_file($files->getAbsolutePath())) {
            return false;
        }

        if (!\in_array($files->extension, $this->validExtensions, true)) {
            return false;
        }

        if (!(new File($files->path))->isGdImage) {
            return false;
        }

        return true;
    }

    /**
     * @throws \Exception
     */
    private function generateAvatarForm(Request $request): string
    {
        // Set adapters
        $controllerAdapter = $this->framework->getAdapter(Controller::class);
        $environmentAdapter = $this->framework->getAdapter(Environment::class);
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
            'eval' => ['class' => 'd-none custom-input-file', 'mandatory' => false],
        ]);

        // Let's add  a submit button
        $objForm->addFormField('submitButton', [
            'label' => 'Speichern',
            'inputType' => 'submit',
            'eval' => ['class' => 'd-none'],
        ]);

        // Create the folder if it not exists
        $objUploadFolder = new Folder($this->getUserAvatarUploadDir());
        $dbafsAdapter->addResource($objUploadFolder->path);

        $objWidget = $objForm->getWidget('avatar');
        $objWidget->addAttribute('accept', '.jpg,.jpeg,.png');
        $objWidget->extensions = 'jpg,jpeg,png';

        if ($objForm->validate()) {
            // Delete avatar
            if ($request->request->get('deleteAvatar')) {
                $this->deleteAvatar();
            }

            $widget = $objForm->getWidget('avatar');
            $arrFile = $widget->value;

            if (empty($arrFile['tmp_name'])) {
                // Reload page
                $controllerAdapter->reload();
            }

            $this->deleteAvatar();

            // Generate target path
            $strAvatarRelativePath = Path::canonicalize(\sprintf(
                '%s/avatar-%s.%s',
                $objUploadFolder->path,
                $this->user->id,
                strtolower(Path::getExtension($arrFile['name'])),
            ));

            $strAvatarAbsolutePath = Path::makeAbsolute($strAvatarRelativePath, $this->projectDir);

            $fs = new Filesystem();

            // Move picture from system temp dir to the target path.
            $fs->rename($arrFile['tmp_name'], $strAvatarAbsolutePath);

            // Assign the new avatar to the user
            $fileModel = $dbafsAdapter->addResource($strAvatarRelativePath);

            if ($fileModel) {
                $oMember = $memberModelAdapter->findById($this->user->id);
                $oMember->avatar = $fileModel->uuid;
                $oMember->save();
            }

            // Reload page
            $controllerAdapter->reload();
        }

        return $objForm->generate();
    }

    private function download(FilesModel $files): Response
    {
        return $this->binaryFileDownload->sendFileToBrowser($files->getAbsolutePath());
    }

    /**
     * Add messages from session to template.
     */
    private function addMessagesToTemplate(Request $request): void
    {
        $messageAdapter = $this->framework->getAdapter(Message::class);

        $this->template->set('hasInfoMessage', false);
        $this->template->set('hasErrorMessage', false);

        $session = $request->getSession();

        if ($messageAdapter->hasInfo()) {
            $bag = $session->getFlashBag()->get('contao.FE.info');
            $this->template->set('hasInfoMessage', true);
            $this->template->set('infoMessage', $bag[0]);
            $this->template->set('infoMessages', $bag);
        }

        if ($messageAdapter->hasError()) {
            $bag = $session->getFlashBag()->get('contao.FE.error');
            $this->template->set('hasErrorMessage', true);
            $this->template->set('errorMessage', $bag[0]);
            $this->template->set('errorMessages', $bag);
        }

        $messageAdapter->reset();
    }
}
