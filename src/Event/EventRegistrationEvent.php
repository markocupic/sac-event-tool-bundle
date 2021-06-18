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

class EventRegistrationEvent extends Event
{
    public const NAME = 'markocupic.sac_event_tool_bundle.event_registration';

    public function __construct(\stdClass $event)
    {
        $this->framework = $event->framework;
        $this->session = $event->session;
        $this->tagStorage = $event->tagStorage;
        $this->fileStorage = $event->fileStorage;
        $this->input = $event->input;
        $this->message = $event->message;
        $this->skeletonPath = $event->skeletonPath;
        $this->projectDir = $event->projectDir;
    }
}
