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
use Symfony\Component\HttpFoundation\Request;
use Symfony\Contracts\EventDispatcher\Event;

class ChangeEventReleaseLevelEvent extends Event
{
    public function __construct(
        private readonly Request $request,
        private readonly CalendarEventsModel $event,
        private readonly string $direction,
    ) {
    }

    public function getRequest(): Request
    {
        return $this->request;
    }

    public function getEvent(): CalendarEventsModel
    {
        return $this->event;
    }

    public function getDirection(): string
    {
        return $this->direction;
    }
}
