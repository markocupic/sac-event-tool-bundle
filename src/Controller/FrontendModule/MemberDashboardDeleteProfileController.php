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
use Contao\CoreBundle\Controller\FrontendModule\AbstractFrontendModuleController;
use Contao\CoreBundle\DependencyInjection\Attribute\AsFrontendModule;
use Contao\CoreBundle\Exception\RedirectResponseException;
use Contao\CoreBundle\Twig\FragmentTemplate;
use Contao\Environment;
use Contao\FrontendUser;
use Contao\Message;
use Contao\ModuleModel;
use Contao\PageModel;
use Markocupic\SacEventToolBundle\User\FrontendUser\ClearFrontendUserData;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

#[AsFrontendModule(MemberDashboardDeleteProfileController::TYPE, category: 'sac_event_tool_frontend_modules')]
class MemberDashboardDeleteProfileController extends AbstractFrontendModuleController
{
    public const string TYPE = 'member_dashboard_delete_profile';

    private FrontendUser|null $user;

    public function __construct(
        private readonly TokenStorageInterface $tokenStorage,
        private readonly ClearFrontendUserData $clearFrontendUserData,
    ) {
    }

    public function __invoke(Request $request, ModuleModel $model, string $section, array|null $classes = null, PageModel|null $page = null): Response
    {
        $this->user = $this->getUserFromToken();

        if (null !== $page) {
            // Neither cache nor search page
            $page->noSearch = 1;
            $page->cache = 0;
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

        $template->set('passedConfirmation', false);
        $template->set('user', $this->user);

        if ('clear-profile' === $request->query->get('action')) {
            // Generate the delete profile form
            $template->set('deleteProfileForm', $this->generateDeleteProfileForm($request));
            $template->set('passedConfirmation', true);
        }

        // Add messages to the template
        $this->addMessagesToTemplate($request, $template);

        return $template->getResponse();
    }

    /**
     * @throws \Exception
     */
    protected function generateDeleteProfileForm(Request $request): string
    {
        $environmentAdapter = $this->getContaoAdapter(Environment::class);

        $form = new Form(
            'form-clear-profile',
            'POST',
        );

        $form->setAction($environmentAdapter->get('uri'));

        $form->addFormField('deleteProfile', [
            'label' => ['Profil löschen', ''],
            'inputType' => 'select',
            'options' => ['false' => 'Nein', 'true' => 'Ja'],
        ]);

        $form->addFormField('sacMemberId', [
            'label' => ['SAC-Mitgliedernummer', ''],
            'inputType' => 'text',
        ]);

        // Add a submit-button
        $form->addFormField('submit', [
            'label' => 'Profil unwiderruflich löschen',
            'inputType' => 'submit',
        ]);

        if ($form->validate()) {
            if ('form-clear-profile' === $request->request->get('FORM_SUBMIT')) {
                $blnHasError = false;

                if ('true' !== $request->request->get('deleteProfile')) {
                    $blnHasError = true;
                    $formField1 = $form->getWidget('deleteProfile');
                    $formField1->addError('Falsche Eingabe. Das Profil konnte nicht gelöscht werden.');
                }

                if ($request->request->get('sacMemberId') !== (string) $this->user->sacMemberId) {
                    $blnHasError = true;
                    $formField2 = $form->getWidget('sacMemberId');
                    $formField2->addError('Das Profil konnte nicht gelöscht werden. Die Mitgliedernummer ist falsch.');
                }

                if (!$blnHasError) {
                    // Clear the account and redirect to the start page
                    if (true === $this->clearFrontendUserData->clearMemberProfile((int) $this->user->id)) {
                        $this->clearFrontendUserData->disableLogin((int) $this->user->id);
                        $this->clearFrontendUserData->deleteFrontendAccount((int) $this->user->id);

                        throw new RedirectResponseException($request->getSchemeAndHttpHost());
                    }
                }
            }
        }

        return $form->generate();
    }

    /**
     * Add messages from session to template.
     */
    protected function addMessagesToTemplate(Request $request, FragmentTemplate $template): void
    {
        $messageAdapter = $this->getContaoAdapter(Message::class);

        $session = $request->getSession();
        $template->set('hasInfoMessages', false);
        $template->set('hasErrorMessages', false);

        if ($messageAdapter->hasInfo()) {
            $template->set('hasInfoMessage', true);
            $bag = $session->getFlashBag()->get('contao.FE.info');
            $template->set('infoMessage', $bag[0]);
            $template->set('infoMessages', $bag);
        }

        if ($messageAdapter->hasError()) {
            $template->set('hasErrorMessage', true);
            $bag = $session->getFlashBag()->get('contao.FE.error');
            $template->set('errorMessage', $bag[0]);
            $template->set('errorMessages', $bag);
        }

        $messageAdapter->reset();
    }

    private function getUserFromToken(): FrontendUser|null
    {
        $user = $this->tokenStorage
            ->getToken()
            ?->getUser()
        ;

        if ($user instanceof FrontendUser) {
            return $user;
        }

        return null;
    }
}
