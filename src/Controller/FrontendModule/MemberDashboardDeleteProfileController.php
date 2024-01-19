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
use Contao\CoreBundle\Controller\FrontendModule\AbstractFrontendModuleController;
use Contao\CoreBundle\DependencyInjection\Attribute\AsFrontendModule;
use Contao\CoreBundle\Exception\RedirectResponseException;
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\Environment;
use Contao\FrontendUser;
use Contao\Message;
use Contao\ModuleModel;
use Contao\PageModel;
use Contao\Template;
use Markocupic\SacEventToolBundle\User\FrontendUser\ClearFrontendUserData;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;
use Symfony\Component\Security\Core\Security;

#[AsFrontendModule(MemberDashboardDeleteProfileController::TYPE, category:'sac_event_tool_frontend_modules', template:'mod_member_dashboard_delete_profile')]
class MemberDashboardDeleteProfileController extends AbstractFrontendModuleController
{
    public const TYPE = 'member_dashboard_delete_profile';

    private FrontendUser|null $user;

    public function __construct(
        private readonly ContaoFramework $framework,
        private readonly Security $security,
        private readonly ClearFrontendUserData $clearFrontendUserData,
    ) {
    }

    public function __invoke(Request $request, ModuleModel $model, string $section, array $classes = null, PageModel $page = null): Response
    {
        // Get logged in member object
        $user = $this->security->getUser();

        if ($user instanceof FrontendUser) {
            $this->user = $user;
        }

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
    protected function getResponse(Template $template, ModuleModel $model, Request $request): Response
    {
        // Do not allow for not authorized users
        if (null === $this->user) {
            throw new UnauthorizedHttpException('Not authorized. Please log in as frontend user.');
        }

        $template->passedConfirmation = false;
        $template->user = $this->user;

        if ('clear-profile' === $request->query->get('action')) {
            // Generate the delete profile form
            $template->deleteProfileForm = $this->generateDeleteProfileForm($request);
            $template->passedConfirmation = true;
        }

        // Add messages to template
        $this->addMessagesToTemplate($request, $template);

        return $template->getResponse();
    }

    /**
     * @throws \Exception
     */
    protected function generateDeleteProfileForm(Request $request): string
    {
        $environmentAdapter = $this->framework->getAdapter(Environment::class);

        $objForm = new Form(
            'form-clear-profile',
            'POST',
        );

        $objForm->setAction($environmentAdapter->get('uri'));

        $objForm->addFormField('deleteProfile', [
            'label' => ['Profil löschen', ''],
            'inputType' => 'select',
            'options' => ['false' => 'Nein', 'true' => 'Ja'],
        ]);

        $objForm->addFormField('sacMemberId', [
            'label' => ['SAC-Mitgliedernummer', ''],
            'inputType' => 'text',
        ]);

        // Let's add a submit button
        $objForm->addFormField('submit', [
            'label' => 'Profil unwiderruflich löschen',
            'inputType' => 'submit',
        ]);

        if ($objForm->validate()) {
            if ('form-clear-profile' === $request->request->get('FORM_SUBMIT')) {
                $blnHasError = false;

                if ('true' !== $request->request->get('deleteProfile')) {
                    $blnHasError = true;
                    $objFormField1 = $objForm->getWidget('deleteProfile');
                    $objFormField1->addError('Falsche Eingabe. Das Profil konnte nicht gelöscht werden.');
                }

                if ($request->request->get('sacMemberId') !== (string) $this->user->sacMemberId) {
                    $blnHasError = true;
                    $objFormField2 = $objForm->getWidget('sacMemberId');
                    $objFormField2->addError('Das Profil konnte nicht gelöscht werden. Die Mitgliedernummer ist falsch.');
                }

                if (!$blnHasError) {
                    // Clear account and redirect to start page
                    if (true === $this->clearFrontendUserData->clearMemberProfile((int) $this->user->id)) {
                        $this->clearFrontendUserData->disableLogin((int) $this->user->id);
                        $this->clearFrontendUserData->deleteFrontendAccount((int) $this->user->id);

                        throw new RedirectResponseException($request->getSchemeAndHttpHost());
                    }
                }
            }
        }

        return $objForm->generate();
    }

    /**
     * Add messages from session to template.
     */
    protected function addMessagesToTemplate(Request $request, Template $template): void
    {
        $messageAdapter = $this->framework->getAdapter(Message::class);

        $session = $request->getSession();
        $template->hasInfoMessages = false;
        $template->hasErrorMessages = false;

        if ($messageAdapter->hasInfo()) {
            $template->hasInfoMessage = true;
            $bag = $session->getFlashBag()->get('contao.FE.info');
            $template->infoMessage = $bag[0];
            $template->infoMessages = $bag;
        }

        if ($messageAdapter->hasError()) {
            $template->hasErrorMessage = true;
            $bag = $session->getFlashBag()->get('contao.FE.error');
            $template->errorMessage = $bag[0];
            $template->errorMessages = $bag;
        }

        $messageAdapter->reset();
    }
}
