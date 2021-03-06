<?php

declare(strict_types=1);

/*
 * This file is part of SAC Event Tool Bundle.
 *
 * (c) Marko Cupic 2021 <m.cupic@gmx.ch>
 * @license MIT
 * For the full copyright and license information,
 * please view the LICENSE file that was distributed with this source code.
 * @link https://github.com/markocupic/sac-event-tool-bundle
 */

namespace Markocupic\SacEventToolBundle\Event;

use Symfony\Contracts\EventDispatcher\Event;

class EventSubscriptionEvent extends Event
{
    public const NAME = 'markocupic.sac_event_tool_bundle.event_subscription';

    public function __construct(\stdClass $event)
    {
        $this->framework = $event->framework;
        $this->arrData = $event->arrData;
        $this->memberModel = $event->memberModel;
        $this->eventMemberModel = $event->eventMemberModel;
        $this->eventModel = $event->eventModel;
        $this->moduleModel = $event->moduleModel;
        $this->message = $event->message;
        $this->skeletonPath = $event->skeletonPath;
        $this->projectDir = $event->projectDir;
    }
}
