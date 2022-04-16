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

use Contao\Controller;
use Contao\CoreBundle\Controller\FrontendModule\AbstractFrontendModuleController;
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\CoreBundle\ServiceAnnotation\FrontendModule;
use Contao\Environment;
use Contao\FrontendUser;
use Contao\Input;
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
 * @FrontendModule(MemberDashboardDeleteProfileController::TYPE, category="sac_event_tool_frontend_modules")
 */
class MemberDashboardDeleteProfileController extends AbstractFrontendModuleController
{
    public const TYPE = 'member_dashboard_delete_profile';

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

            // Set the page object
            $this->objPage = $page;
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

    protected function getResponse(Template $template, ModuleModel $model, Request $request): ?Response
    {
        // Do not allow for not authorized users
        if (null === $this->objUser) {
            throw new UnauthorizedHttpException('Not authorized. Please log in as frontend user.');
        }

        // Set adapters
        $inputAdapter = $this->get('contao.framework')->getAdapter(Input::class);

        $this->template = $template;

        $this->template->objUser = $this->objUser;

        if ('clear-profile' === $inputAdapter->get('action')) {
            // Generate the my profileform
            $this->template->deleteProfileForm = $this->generateDeleteProfileForm();
            $this->template->passedConfirmation = true;
        }

        // Add messages to template
        $this->addMessagesToTemplate();

        return $this->template->getResponse();
    }

    /**
     * Add messages from session to template.
     */
    protected function addMessagesToTemplate(): void
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
     * @return string|void
     */
    protected function generateDeleteProfileForm()
    {
        // Set adapters
        /** @var Input $inputAdapter */
        $inputAdapter = $this->get('contao.framework')->getAdapter(Input::class);

        /** @var Environment $environmentAdapter */
        $environmentAdapter = $this->get('contao.framework')->getAdapter(Environment::class);

        $objForm = new Form(
            'form-clear-profile',
            'POST',
            function ($objHaste) {
                $inputAdapter = $this->get('contao.framework')->getAdapter(Input::class);

                return $inputAdapter->post('FORM_SUBMIT') === $objHaste->getFormId();
            }
        );

        $objForm->setFormActionFromUri($environmentAdapter->get('uri'));

        // Now let's add form fields:
        // Now let's add form fields:
        $objForm->addFormField('deleteProfile', [
            'label' => ['Profil löschen', ''],
            'inputType' => 'select',
            'options' => ['false' => 'Nein', 'true' => 'Ja'],
        ]);

        $objForm->addFormField('sacMemberId', [
            'label' => ['SAC-Mitgliedernummer', ''],
            'inputType' => 'text',
        ]);

        // Let's add  a submit button
        $objForm->addFormField('submit', [
            'label' => 'Profil unwiederkehrlich löschen',
            'inputType' => 'submit',
        ]);

        if ($objForm->validate()) {
            if ('form-clear-profile' === $inputAdapter->post('FORM_SUBMIT')) {
                $blnError = false;

                if ('true' !== $inputAdapter->post('deleteProfile')) {
                    $blnError = true;
                    $objFormField1 = $objForm->getWidget('deleteProfile');
                    $objFormField1->addError('Falsche Eingabe. Das Profil konnte nicht gelöscht werden.');
                }

                if ($inputAdapter->post('sacMemberId') !== $this->objUser->sacMemberId) {
                    $blnError = true;
                    $objFormField2 = $objForm->getWidget('sacMemberId');
                    $objFormField2->addError('Das Profil konnte nicht gelöscht werden. Die Mitgliedernummer ist falsch.');
                }

                if (!$blnError) {
                    // Clear account
                    $objClearFrontendUserData = System::getContainer()->get('Markocupic\SacEventToolBundle\User\FrontendUser\ClearFrontendUserData');

                    if (true === $objClearFrontendUserData->clearMemberProfile((int) $this->objUser->id)) {
                        $objClearFrontendUserData->disableLogin((int) $this->objUser->id);
                        $objClearFrontendUserData->deleteFrontendAccount((int) $this->objUser->id);
                        Controller::redirect('');
                    }
                }
            }
        }

        return $objForm->generate();
    }
}
