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

namespace Markocupic\SacEventToolBundle\Event;

use Contao\CalendarEventsModel;
use Knp\Menu\MenuItem;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Contracts\EventDispatcher\Event;

class GenerateEventDashboardEvent extends Event
{
    public function __construct(
        private readonly MenuItem $menuItem,
        private readonly CalendarEventsModel $calEvent,
        private readonly Request $request,
    ) {
    }

    public function getMenuItem(): MenuItem
    {
        return $this->menuItem;
    }

    public function getCalendarEvent(): CalendarEventsModel
    {
        return $this->calEvent;
    }

    public function getRequest(): Request
    {
        return $this->request;
    }
}
