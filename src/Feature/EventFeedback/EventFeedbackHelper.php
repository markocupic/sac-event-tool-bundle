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

namespace Markocupic\SacEventToolBundle\Feature\EventFeedback;

use Contao\CalendarEventsModel;
use Contao\CalendarModel;
use Contao\FormModel;
use Contao\PageModel;
use Doctrine\DBAL\Connection;
use Markocupic\SacEventToolBundle\Feature\EventFeedback\NotificationType\EventFeedbackReminderNotificationType;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Reads the event feedback settings of an event and its calendar.
 *
 * Deliberately not "readonly" so it can be mocked in unit tests.
 */
class EventFeedbackHelper
{
    /**
     * @param array<string, array<string, mixed>> $feedbackConfigs sacevt.feature.event_feedback.configs
     */
    public function __construct(
        private readonly Connection $connection,
        #[Autowire(param: 'sacevt.feature.event_feedback.configs')]
        private readonly array $feedbackConfigs,
    ) {
    }

    /**
     * @return list<string> names of the available configurations
     */
    public function getConfigurationNames(): array
    {
        return array_map('strval', array_keys($this->feedbackConfigs));
    }

    /**
     * Returns true or an error code.
     */
    public function eventHasValidFeedbackConfiguration(CalendarEventsModel $event): bool|string
    {
        if (!$event->enableOnlineEventFeedback) {
            return 'online_feedback_disabled_on_event';
        }

        if (null === ($calendar = CalendarModel::findById($event->pid))) {
            return 'missing_related_parent_calendar';
        }

        if (!$calendar->enableOnlineEventFeedback) {
            return 'online_feedback_disabled_on_related_calendar';
        }

        if (null === $this->getOnlineFeedbackConfiguration($event)) {
            return 'online_feedback_configuration_not_found';
        }

        if (null === $this->getForm($event)) {
            return 'event_feedback_form_not_set';
        }

        if (null === $this->getNotificationId($event)) {
            return 'event_feedback_notification_not_set';
        }

        if (null === $this->getPage($event)) {
            return 'online_feedback_page_not_found';
        }

        return true;
    }

    /**
     * Is the feedback set up completely on the calendar (configuration, form, notification)?
     */
    public function calendarHasValidFeedbackConfiguration(CalendarModel $calendar): bool
    {
        if (!$calendar->enableOnlineEventFeedback || !isset($this->feedbackConfigs[$calendar->onlineFeedbackConfiguration])) {
            return false;
        }

        if (null === FormModel::findById($calendar->onlineFeedbackForm)) {
            return false;
        }

        return null !== $this->findNotificationId((int) $calendar->onlineFeedbackNotification);
    }

    public function getForm(CalendarEventsModel $event): FormModel|null
    {
        if (null === ($calendar = $this->getEnabledCalendar($event))) {
            return null;
        }

        return FormModel::findById($calendar->onlineFeedbackForm);
    }

    public function getNotificationId(CalendarEventsModel $event): int|null
    {
        if (null === ($calendar = $this->getEnabledCalendar($event))) {
            return null;
        }

        return $this->findNotificationId((int) $calendar->onlineFeedbackNotification);
    }

    public function getPage(CalendarEventsModel $event): PageModel|null
    {
        if (null === ($calendar = $this->getEnabledCalendar($event))) {
            return null;
        }

        return PageModel::findById($calendar->onlineFeedbackPage);
    }

    /**
     * @return array<string, mixed>|null the configuration chosen in the calendar (sacevt.feature.event_feedback.configs)
     */
    public function getOnlineFeedbackConfiguration(CalendarEventsModel $event): array|null
    {
        if (null === ($calendar = $this->getEnabledCalendar($event))) {
            return null;
        }

        return $this->getConfigurationByName((string) $calendar->onlineFeedbackConfiguration);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getConfigurationByName(string $name): array|null
    {
        if ('' === $name || !isset($this->feedbackConfigs[$name])) {
            return null;
        }

        return $this->feedbackConfigs[$name];
    }

    private function getEnabledCalendar(CalendarEventsModel $event): CalendarModel|null
    {
        $calendar = CalendarModel::findById($event->pid);

        return null !== $calendar && $calendar->enableOnlineEventFeedback ? $calendar : null;
    }

    private function findNotificationId(int $notificationId): int|null
    {
        $id = $this->connection->fetchOne(
            'SELECT id FROM tl_nc_notification WHERE id = ? AND type = ?',
            [$notificationId, EventFeedbackReminderNotificationType::NAME],
        );

        return false !== $id ? (int) $id : null;
    }
}
