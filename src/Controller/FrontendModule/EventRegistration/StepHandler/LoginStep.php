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
use Contao\FrontendUser;
use Contao\ModuleModel;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;
use Symfony\Component\HttpFoundation\Request;
use Twig\Environment;

#[AutoconfigureTag('sacevt.event_registration.step_handler')]
class LoginStep implements StepHandlerInterface, ValidationStepInterface
{
    private const string STEP = 'login';

    private const string TEMPLATE = '@MarkocupicSacEventTool/EventRegistration/Step/login.html.twig';

    private const int PRIORITY = 300;

    public function __construct(
        private readonly Environment $twig,
        private readonly Security $security,
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
        return true;
    }

    public function validate(CalendarEventsModel $eventModel, Request $request, ModuleModel $moduleModel): bool
    {
        $user = $this->security->getUser();
        if ($user instanceof FrontendUser) {
            return true;
        }

        return false;
    }

    public function prepareStep(CalendarEventsModel $eventModel, Request $request, ModuleModel $moduleModel): array
    {
        return [
            'eventModel' => $eventModel,
            'moduleModel' => $moduleModel,
        ];
    }
}
