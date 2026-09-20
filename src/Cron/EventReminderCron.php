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

namespace Markocupic\SacEventToolBundle\Cron;

use Contao\CoreBundle\DependencyInjection\Attribute\AsCronJob;
use Doctrine\DBAL\Connection;
use Markocupic\SacEventToolBundle\Config\EventSubscriptionState;
use Markocupic\SacEventToolBundle\Messenger\Message\SendEventReminderMessage;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * Finds all events that start in exactly "tl_calendar.eventReminderOffset" days
 * and dispatches one SendEventReminderMessage per event.
 * The email itself is sent by SendEventReminderHandler (retried on failure).
 */
#[AsCronJob('10 4,5 * * *')]
readonly class EventReminderCron
{
    public function __construct(
        private Connection $connection,
        private MessageBusInterface $messageBus,
    ) {
    }

    public function __invoke(): void
    {
        $calendars = $this->connection->fetchAllAssociative(
            'SELECT id, eventReminderOffset FROM tl_calendar WHERE sendEventReminder = 1 AND eventReminderNotification > 0',
        );

        foreach ($calendars as $calendar) {
            // The whole day, "offset" days from today, in the PHP/Contao time zone
            $day = new \DateTimeImmutable('today')->modify(\sprintf('+%d days', $calendar['eventReminderOffset']));
            $from = $day->getTimestamp();
            $to = $day->modify('+1 day')->getTimestamp() - 1;

            $qb = $this->connection->createQueryBuilder();
            $qb
                ->select('e.id')
                ->from('tl_calendar_events', 'e')
                ->where('e.pid = :pid')
                ->andWhere('e.title != ""')
                ->andWhere('e.eventType != ""')
                ->andWhere('e.published = 1')
                ->andWhere('e.eventReminderSentAt = 0')
                ->andWhere('e.startDate BETWEEN :from AND :to')
                ->andWhere('EXISTS (
					SELECT 1
					FROM tl_calendar_events_member m
					WHERE m.eventId = e.id
					  AND m.stateOfSubscription = :state
				)')
                ->setParameter('pid', $calendar['id'])
                ->setParameter('from', $from)
                ->setParameter('to', $to)
                ->setParameter('state', EventSubscriptionState::SUBSCRIPTION_ACCEPTED)
            ;

            $eventIds = $qb->fetchFirstColumn();

            foreach ($eventIds as $eventId) {
                $this->messageBus->dispatch(new SendEventReminderMessage((int) $eventId));
            }
        }
    }
}
