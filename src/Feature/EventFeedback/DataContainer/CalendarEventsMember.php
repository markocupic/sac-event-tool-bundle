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
use Contao\CoreBundle\DependencyInjection\Attribute\AsCallback;
use Contao\DataContainer;
use Markocupic\SacEventToolBundle\Feature\EventFeedback\EventFeedbackHelper;
use Markocupic\SacEventToolBundle\Feature\EventFeedback\FeedbackReminder;
use Markocupic\SacEventToolBundle\Model\CalendarEventsMemberModel;
use Markocupic\SacEventToolBundle\Model\EventFeedbackModel;

/**
 * Confirming the participation (hasParticipated) schedules the feedback requests,
 * removing it deletes them again (as long as none has been sent yet).
 */
readonly class CalendarEventsMember
{
    public function __construct(
        private EventFeedbackHelper $eventFeedbackHelper,
        private FeedbackReminder $feedbackReminder,
    ) {
    }

    #[AsCallback(table: 'tl_calendar_events_member', target: 'fields.hasParticipated.save')]
    public function onParticipationChange(mixed $value, DataContainer $dc): mixed
    {
        $registration = CalendarEventsMemberModel::findById($dc->id);
        $event = null !== $registration ? CalendarEventsModel::findById($registration->eventId) : null;

        if (null === $event || true !== $this->eventFeedbackHelper->eventHasValidFeedbackConfiguration($event)) {
            return $value;
        }

        // Feedback already given or requests already sent: nothing to change
        if ($registration->countOnlineEventFeedbackNotifications || null !== EventFeedbackModel::findOneByUuid($registration->uuid)) {
            return $value;
        }

        if (!$value) {
            $this->feedbackReminder->deleteByUuid((string) $registration->uuid);

            return $value;
        }

        $config = $this->eventFeedbackHelper->getOnlineFeedbackConfiguration($event);

        $this->feedbackReminder->schedule(
            $registration,
            (int) $event->endDate,
            array_map('intval', $config['send_reminder_after_days'] ?? []),
            (int) ($config['feedback_expiration_time'] ?? 0),
        );

        return $value;
    }
}
