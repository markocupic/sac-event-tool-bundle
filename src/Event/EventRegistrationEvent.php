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

namespace Markocupic\SacEventToolBundle\Event;

use Contao\CalendarEventsModel;
use Contao\MemberModel;
use Contao\ModuleModel;
use Markocupic\SacEventToolBundle\Model\CalendarEventsMemberModel;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Contracts\EventDispatcher\Event;

class EventRegistrationEvent extends Event
{
    public const NAME = 'markocupic.sac_event_tool_bundle.event_registration';

    public function __construct(
        private readonly Request $request,
        private readonly CalendarEventsMemberModel $eventMemberModel,
        private readonly CalendarEventsModel $eventModel,
        private readonly MemberModel $memberModel,
        private readonly ModuleModel $moduleModel,
        private readonly array $arrData,
    ) {
    }

    public function getRequest(): Request
    {
        return $this->request;
    }

    public function getRegistration(): CalendarEventsMemberModel
    {
        return $this->eventMemberModel;
    }

    public function getEvent(): CalendarEventsModel
    {
        return $this->eventModel;
    }

    public function getContaoMemberModel(): MemberModel
    {
        return $this->memberModel;
    }

    public function getRegistrationModule(): ModuleModel
    {
        return $this->moduleModel;
    }

    public function getData(): array
    {
        return $this->arrData;
    }
}
