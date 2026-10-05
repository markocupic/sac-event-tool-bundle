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

namespace Markocupic\SacEventToolBundle\Feature\EventFeedback\DataContainer;

use Contao\CalendarEventsModel;
use Contao\CalendarModel;
use Contao\CoreBundle\DataContainer\PaletteManipulator;
use Contao\CoreBundle\DependencyInjection\Attribute\AsCallback;
use Contao\DataContainer;
use Markocupic\SacEventToolBundle\Config\EventType;
use Markocupic\SacEventToolBundle\Feature\EventFeedback\EventFeedbackHelper;

/**
 * Shows the field "enableOnlineEventFeedback" only if the feedback is set up completely on the calendar.
 */
readonly class CalendarEvents
{
    public function __construct(private EventFeedbackHelper $eventFeedbackHelper)
    {
    }

    #[AsCallback(table: 'tl_calendar_events', target: 'config.onload')]
    public function removeFeedbackFieldIfNotConfigured(DataContainer $dc): void
    {
        if (!$dc->id) {
            return;
        }

        $event = CalendarEventsModel::findById($dc->id);
        $calendar = null !== $event ? CalendarModel::findById($event->pid) : null;

        if (null !== $calendar && $this->eventFeedbackHelper->calendarHasValidFeedbackConfiguration($calendar)) {
            return;
        }

        $paletteManipulator = PaletteManipulator::create()->removeField('enableOnlineEventFeedback');

        foreach (['default', ...EventType::ALL] as $palette) {
            if (isset($GLOBALS['TL_DCA']['tl_calendar_events']['palettes'][$palette])) {
                $paletteManipulator->applyToPalette($palette, 'tl_calendar_events');
            }
        }
    }
}
