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

namespace Markocupic\SacEventToolBundle\Feature\EventReminder\DataContainer;

use Contao\CoreBundle\DependencyInjection\Attribute\AsCallback;
use Doctrine\DBAL\Connection;
use Markocupic\SacEventToolBundle\Feature\EventReminder\NotificationType\EventReminderNotificationType;

/**
 * tl_calendar callbacks for the event reminder settings.
 */
readonly class Calendar
{
    public function __construct(private Connection $connection)
    {
    }

    /**
     * Only offer notifications of type "event_reminder".
     *
     * @return array<int, string>
     */
    #[AsCallback(table: 'tl_calendar', target: 'fields.eventReminderNotification.options')]
    public function getNotificationOptions(): array
    {
        $rows = $this->connection->fetchAllAssociative(
            'SELECT id, title FROM tl_nc_notification WHERE type = ? ORDER BY title',
            [EventReminderNotificationType::NAME],
        );

        $options = [];

        foreach ($rows as $row) {
            $options[(int) $row['id']] = $row['title'];
        }

        return $options;
    }
}
