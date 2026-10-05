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

namespace Markocupic\SacEventToolBundle\Feature\EventRegistrationReminder;

/**
 * An upcoming event with unconfirmed registrations.
 *
 * - overdueRegistrations: registered longer ago than the calendar setting "sendFirstReminderAfter" (trigger the reminder)
 * - recentRegistrations: further unconfirmed registrations of the same event (shown, but do not trigger the reminder)
 */
final readonly class PendingEvent
{
    /**
     * @param list<PendingRegistration> $overdueRegistrations
     * @param list<PendingRegistration> $recentRegistrations
     */
    public function __construct(
        public int $eventId,
        public string $title,
        public string $eventType,
        public array $overdueRegistrations,
        public array $recentRegistrations,
    ) {
    }
}
