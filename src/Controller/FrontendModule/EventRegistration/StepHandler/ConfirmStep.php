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

namespace Markocupic\SacEventToolBundle\Controller\FrontendModule\EventRegistration\StepHandler;

use Contao\CalendarEventsModel;
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\CoreBundle\Routing\ContentUrlGenerator;
use Contao\FrontendUser;
use Contao\MemberModel;
use Contao\ModuleModel;
use Markocupic\SacEventToolBundle\Model\CalendarEventsMemberModel;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Contracts\Translation\TranslatorInterface;

#[AutoconfigureTag('sacevt.event_registration.step_handler')]
class ConfirmStep implements StepHandlerInterface
{
    private const string STEP = 'confirm';

    private const string TEMPLATE = '@Contao_MarkocupicSacEventToolBundle/frontend_module/partials/event_registration/step/confirm.html.twig';

    private const int PRIORITY = 100;

    public function __construct(
        private readonly ContaoFramework $framework,
        private readonly ContentUrlGenerator $contentUrlGenerator,
        private readonly Security $security,
        private readonly TranslatorInterface $translator,
    ) {
    }

    public static function getName(): string
    {
        return self::STEP;
    }

    public static function getPriority(): int
    {
        return self::PRIORITY;
    }

    public function getTemplateName(): string
    {
        return self::TEMPLATE;
    }

    public function doAutoForward(CalendarEventsModel $eventModel, Request $request, ModuleModel $moduleModel): bool
    {
        return false;
    }

    public function prepareStep(CalendarEventsModel $eventModel, Request $request, ModuleModel $moduleModel): array
    {
        $user = $this->security->getUser();

        if (!$user instanceof FrontendUser) {
            throw new \Exception('No logged in user found.');
        }

        $memberModel = $this->framework->getAdapter(MemberModel::class)->findById($user->id);

        $registrationModel = $this->framework
            ->getAdapter(CalendarEventsMemberModel::class)
            ->findByMemberAndEvent($memberModel, $eventModel)
        ;

        if (null === $registrationModel) {
            throw new \Exception('No registration found for member '.$memberModel->id.' and event '.$eventModel->id);
        }

        $arrEvent = $eventModel->row();
        $arrEvent['eventUrl'] = $this->contentUrlGenerator->generate($eventModel);

        $arrEventsMember = $registrationModel->row();
        $arrEventsMember['stateOfSubscriptionTrans'] = $this->translator->trans('MSC.'.$arrEventsMember['stateOfSubscription'], [], 'contao_default');

        $arrMember = $memberModel->row();

        return [
            'event_model' => $arrEvent,
            'event_member_model' => $arrEventsMember,
            'member_model' => $arrMember,
        ];
    }
}
