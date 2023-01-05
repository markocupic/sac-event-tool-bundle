<?php

declare(strict_types=1);

/*
 * This file is part of SAC Event Tool Bundle.
 *
 * (c) Marko Cupic 2023 <m.cupic@gmx.ch>
 * @license GPL-3.0-or-later
 * For the full copyright and license information,
 * please view the LICENSE file that was distributed with this source code.
 * @link https://github.com/markocupic/sac-event-tool-bundle
 */

namespace Markocupic\SacEventToolBundle\Event;

use Symfony\Contracts\EventDispatcher\Event;

class EventRegistrationEvent extends Event
{
    public const NAME = 'markocupic.sac_event_tool_bundle.event_registration';

    public function __construct(\stdClass $event)
    {
        $this->framework = $event->framework;
        $this->arrData = $event->arrData;
        $this->memberModel = $event->memberModel;
        $this->eventModel = $event->eventModel;
        $this->eventMemberModel = $event->eventMemberModel;
        $this->moduleModel = $event->moduleModel;
    }
}
