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
use Contao\Controller;
use Contao\CoreBundle\Controller\FrontendModule\AbstractFrontendModuleController;
use Contao\CoreBundle\DependencyInjection\Attribute\AsFrontendModule;
use Contao\CoreBundle\Monolog\ContaoContext;
use Contao\CoreBundle\Twig\FragmentTemplate;
use Contao\FrontendUser;
use Contao\MemberModel;
use Contao\ModuleModel;
use Contao\PageModel;
use Contao\StringUtil;
use Markocupic\ContaoFrontendUserNotification\Notification\DefaultFrontendUserNotification;
use Markocupic\SacEventToolBundle\Config\Log;
use Markocupic\SacEventToolBundle\Database\SyncEventRegistrationDatabase;
use Markocupic\SacEventToolBundle\Model\SacSectionModel;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

#[AsFrontendModule(MemberDashboardEditProfileController::TYPE, category: 'sac_event_tool_frontend_modules')]
class MemberDashboardEditProfileController extends AbstractFrontendModuleController
{
    public const string TYPE = 'member_dashboard_edit_profile';

    private FrontendUser|null $user;

    public function __construct(
        private readonly SyncEventRegistrationDatabase $syncEventRegistrationDatabase,
        private readonly TokenStorageInterface $tokenStorage,
        private readonly TranslatorInterface $translator,
        private readonly LoggerInterface|null $contaoGeneralLogger = null,
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

    protected function getResponse(FragmentTemplate $template, ModuleModel $model, Request $request): Response
    {
        if (null === $this->user) {
            throw new \Exception('No logged in Contao frontend user found.');
        }

        $template->set('user', $this->user);
        $template->set('sac_sections', $this->getSacSections($this->user));
        $template->set('form', $this->getForm($request));

        return $template->getResponse();
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

    private function getForm(Request $request): Form
    {
        $form = new Form(
            'form-user-profile',
            Request::METHOD_POST,
        );

        $form->addContaoHiddenFields();

        $form->setAction($request->getUri());

        // Now let's add form fields:
        $form->addFormField('emergencyPhone', [
            'label' => $this->translator->trans('FORM.evt_reg_emergencyPhone', [], 'contao_default'),
            'inputType' => 'text',
            'eval' => ['class' => 'form-control', 'rgxp' => 'phone', 'mandatory' => true, 'maxlength' => 64],
        ]);

        $form->addFormField('emergencyPhoneName', [
            'label' => $this->translator->trans('FORM.evt_reg_emergencyPhoneName', [], 'contao_default'),
            'inputType' => 'text',
            'eval' => ['class' => 'form-control', 'mandatory' => true, 'maxlength' => 255],
        ]);

        $form->addFormField('foodHabits', [
            'label' => $this->translator->trans('FORM.evt_reg_foodHabits', [], 'contao_default'),
            'inputType' => 'text',
            'eval' => ['class' => 'form-control', 'mandatory' => false, 'maxlength' => 5000],
        ]);

        $form->addFormField('ahvNumber', [
            'label' => $this->translator->trans('FORM.evt_reg_ahvNumber', [], 'contao_default'),
            'inputType' => 'text',
            'eval' => ['class' => 'form-control', 'rgxp' => 'ahv', 'mandatory' => false, 'maxlength' => 16],
        ]);

        $form->addFormField('submit', [
            'label' => $this->translator->trans('MSC.save', [], 'contao_default'),
            'inputType' => 'submit',
            'eval' => ['class' => ''],
        ]);

        // Get form presets from tl_member
        $fields = ['emergencyPhone', 'emergencyPhoneName', 'foodHabits', 'ahvNumber'];

        foreach ($fields as $field) {
            $widget = $form->getWidget($field);

            if (empty($widget->value)) {
                $widget = $form->getWidget($field);
                $widget->value = $this->user->{$field};
            }
        }

        // Bind the form to the MemberModel
        $model = $this->getContaoAdapter(MemberModel::class)->findById($this->user->id);
        $form->setBoundModel($model);

        if ($form->validate()) {
            // The model will now contain the changes, so you can save it.
            if ($model->isModified()) {
                $model->save();

                if ($this->syncEventRegistrationDatabase->syncMember($model->id)) {
                    new DefaultFrontendUserNotification(
                        $this->user,
                        'member_dashboard_edit_profile_controller::update_contact_data',
                        'Mitteilung',
                        'All deine persönlichen Daten (Adresse, Tel.-Nr., Notfallangaben, Essgewohnheiten etc.) wurden anhand deiner Eingaben bei deinen laufenden Anmeldungen aktualisiert.',
                        time() + 60,
                    );
                }
            }

            $this->contaoGeneralLogger->info(
                \sprintf(
                    'Frontend user %s %s "%s" has updated his user profile.',
                    $this->user->firstname,
                    $this->user->lastname,
                    $this->user->username,
                ),
                ['contao' => new ContaoContext(__METHOD__, Log::MEMBER_DASHBOARD_UPDATE_PROFILE)],
            );

            $this->getContaoAdapter(Controller::class)->reload();
        }

        return $form;
    }

    private function getSacSections(FrontendUser $user): array
    {
        $model = $this->getContaoAdapter(MemberModel::class)->findById($user->id);

        // SAC sections user belongs to
        $sacSectionNames = ['-'];
        $sacSectionIds = $this->getContaoAdapter(StringUtil::class)->deserialize($model->sectionId, true);

        if (null !== ($sections = $this->getContaoAdapter(SacSectionModel::class)->findMultipleBySectionIds($sacSectionIds))) {
            // Override default
            $sacSectionNames = [];

            foreach ($sections as $section) {
                $sacSectionNames[] = $section->name;
            }
        }

        return $sacSectionNames;
    }
}
