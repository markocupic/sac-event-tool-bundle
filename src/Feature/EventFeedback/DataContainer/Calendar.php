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

use Contao\CoreBundle\DependencyInjection\Attribute\AsCallback;
use Doctrine\DBAL\Connection;
use Markocupic\SacEventToolBundle\Feature\EventFeedback\EventFeedbackHelper;
use Markocupic\SacEventToolBundle\Feature\EventFeedback\NotificationType\EventFeedbackReminderNotificationType;

/**
 * tl_calendar options callbacks for the event feedback settings.
 */
readonly class Calendar
{
    public function __construct(
        private Connection $connection,
        private EventFeedbackHelper $eventFeedbackHelper,
    ) {
    }

    /**
     * @return list<string> configurations from sacevt.feature.event_feedback.configs
     */
    #[AsCallback(table: 'tl_calendar', target: 'fields.onlineFeedbackConfiguration.options')]
    public function getConfigurationOptions(): array
    {
        return $this->eventFeedbackHelper->getConfigurationNames();
    }

    /**
     * @return array<int, string> only notifications of type "event_feedback_reminder"
     */
    #[AsCallback(table: 'tl_calendar', target: 'fields.onlineFeedbackNotification.options')]
    public function getNotificationOptions(): array
    {
        return $this->connection->fetchAllKeyValue(
            'SELECT id, title FROM tl_nc_notification WHERE type = ? ORDER BY title',
            [EventFeedbackReminderNotificationType::NAME],
        );
    }

    /**
     * @return array<int, string> only forms marked as event feedback form
     */
    #[AsCallback(table: 'tl_calendar', target: 'fields.onlineFeedbackForm.options')]
    public function getFormOptions(): array
    {
        return $this->connection->fetchAllKeyValue("SELECT id, title FROM tl_form WHERE isSacEventFeedbackForm = '1' ORDER BY title");
    }
}
