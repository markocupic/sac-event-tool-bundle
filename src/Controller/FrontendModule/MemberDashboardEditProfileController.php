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
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\CoreBundle\Monolog\ContaoContext;
use Contao\CoreBundle\Twig\FragmentTemplate;
use Contao\Environment;
use Contao\FrontendUser;
use Contao\MemberModel;
use Contao\ModuleModel;
use Contao\PageModel;
use Contao\StringUtil;
use Contao\User;
use Markocupic\SacEventToolBundle\Config\Log;
use Markocupic\SacEventToolBundle\Model\SacSectionModel;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Contracts\Translation\TranslatorInterface;

#[AsFrontendModule(MemberDashboardEditProfileController::TYPE, category:'sac_event_tool_frontend_modules', template:'mod_member_dashboard_edit_profile')]
class MemberDashboardEditProfileController extends AbstractFrontendModuleController
{
    public const TYPE = 'member_dashboard_edit_profile';

    private FrontendUser|null $user;
    private FragmentTemplate|null $template;

    public function __construct(
        private readonly ContaoFramework $framework,
        private readonly Security $security,
        private readonly TranslatorInterface $translator,
        private readonly LoggerInterface|null $contaoGeneralLogger = null,
    ) {
    }

    public function __invoke(Request $request, ModuleModel $model, string $section, array|null $classes = null, PageModel|null $page = null): Response
    {
        // Get logged in member object
        $user = $this->security->getUser();

        if (!$user instanceof FrontendUser) {
            return parent::__invoke($request, $model, $section, $classes);
        }

        $this->user = $user;

        if (null !== $page) {
            // Neither cache nor search page
            $page->noSearch = 1;
            $page->cache = 0;
        }

        return parent::__invoke($request, $model, $section, $classes);
    }

    protected function getResponse(FragmentTemplate $template, ModuleModel $model, Request $request): Response
    {
        $this->template = $template;
        $this->template->set('user', $this->user);
        $this->template->set('sac_sections', $this->getSacSections($this->user));
        $this->template->set('form', $this->getForm());

        return $this->template->getResponse();
    }

    private function getForm(): Form
    {
        $form = new Form(
            'form-user-profile',
            'POST',
        );

        $form->addContaoHiddenFields();

        $form->setAction($this->framework->getAdapter(Environment::class)->get('uri'));

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

        $form->addFormField('submit', [
            'label' => $this->translator->trans('MSC.save', [], 'contao_default'),
            'inputType' => 'submit',
            'eval' => ['class' => ''],
        ]);

        // Get form presets from tl_member
        $arrFields = ['emergencyPhone', 'emergencyPhoneName', 'foodHabits'];

        foreach ($arrFields as $field) {
            $objWidget = $form->getWidget($field);

            if (empty($objWidget->value)) {
                $objWidget = $form->getWidget($field);
                $objWidget->value = $this->user->{$field};
            }
        }

        // Bind form to the MemberModel
        $model = $this->framework->getAdapter(MemberModel::class)->findByPk($this->user->id);
        $form->setBoundModel($model);

        if ($form->validate()) {
            // The model will now contain the changes, so you can save it.
            $model->save();

            $this->contaoGeneralLogger->info(
                sprintf(
                    'Frontend user %s %s "%s" has updated his user profile.',
                    $this->user->firstname,
                    $this->user->lastname,
                    $this->user->username,
                ),
                ['contao' => new ContaoContext(__METHOD__, Log::MEMBER_DASHBOARD_UPDATE_PROFILE)]
            );
        }

        return $form;
    }

    private function getSacSections(User|null $user = null): array
    {
        if (null === $user) {
            return ['-'];
        }

        $model = $this->framework->getAdapter(MemberModel::class)->findByPk($user->id);

        // SAC sections user belongs to
        $arrSectionNames = ['-'];
        $arrSectionIds = $this->framework->getAdapter(StringUtil::class)->deserialize($model->sectionId, true);

        if (null !== ($sections = $this->framework->getAdapter(SacSectionModel::class)->findMultipleBySectionIds($arrSectionIds))) {
            // Override default
            $arrSectionNames = [];

            foreach ($sections as $section) {
                $arrSectionNames[] = $section->name;
            }
        }

        return $arrSectionNames;
    }
}
