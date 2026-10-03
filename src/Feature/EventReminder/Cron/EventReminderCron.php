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

namespace Markocupic\SacEventToolBundle\Feature\EventReminder\Cron;

use Contao\CoreBundle\DependencyInjection\Attribute\AsCronJob;
use Doctrine\DBAL\Connection;
use Markocupic\SacEventToolBundle\Config\EventSubscriptionState;
use Markocupic\SacEventToolBundle\Feature\EventReminder\Messenger\Message\SendEventReminderMessage;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * Finds all events that start in exactly "tl_calendar.eventReminderOffset" days
 * and dispatches one SendEventReminderMessage per event.
 * The email itself is sent by SendEventReminderHandler (at most once per event).
 *
 * Deliberately not "readonly" so it can be mocked in unit tests (EventReminderCommandTest).
 */
#[AsCronJob('30 3,4 * * *')]
class EventReminderCron
{
    public function __construct(
        private readonly Connection $connection,
        private readonly MessageBusInterface $messageBus,
        private readonly LoggerInterface|null $contaoCronLogger,
    ) {
    }

    public function __invoke(): void
    {
        $count = 0;

        foreach ($this->findDueEventIds() as $eventId) {
            ++$count;
            $this->messageBus->dispatch(new SendEventReminderMessage($eventId));
        }

        // The messages are handled (and the emails sent) asynchronously by SendEventReminderHandler
        $this->contaoCronLogger?->info(\sprintf('Event reminder cron: dispatched %d reminder message(s).', $count));
    }

    /**
     * Events that start in exactly "tl_calendar.eventReminderOffset" days and have not been reminded yet.
     * Also used by EventReminderCommand.
     *
     * @return list<int>
     */
    public function findDueEventIds(): array
    {
        $dueEventIds = [];

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

            foreach ($qb->fetchFirstColumn() as $eventId) {
                $dueEventIds[] = (int) $eventId;
            }
        }

        return $dueEventIds;
    }
}
