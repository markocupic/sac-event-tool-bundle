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

namespace Markocupic\SacEventToolBundle\Feature\InstructorPostEventTaskReminder\Task;

use Contao\CalendarEventsModel;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;
use Markocupic\SacEventToolBundle\Config\EventSubscriptionState;
use Markocupic\SacEventToolBundle\Config\EventType;
use Symfony\Component\DependencyInjection\Attribute\AsTaggedItem;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Tours, courses and general events: the participation of the participants has to be confirmed.
 *
 * Only registrations that are accepted OR on the waiting list count
 * (EventSubscriptionState::PARTICIPATION_CONFIRMATION_ALLOWED):
 * - the task only exists if there is at least one such registration
 * - it is done as soon as at least one of them has hasParticipated = 1
 * Registrations in any other state are ignored.
 */
#[AsTaggedItem(priority: 10)]
class ParticipationConfirmationTask implements PostEventTaskInterface
{
    public const NAME = 'participation_confirmation';

    public function __construct(
        private readonly Connection $connection,
        private readonly RouterInterface $router,
        private readonly TranslatorInterface $translator,
    ) {
    }

    public function getName(): string
    {
        return self::NAME;
    }

    public function supports(CalendarEventsModel $event): bool
    {
        return \in_array($event->eventType, [EventType::TOUR, EventType::LAST_MINUTE_TOUR, EventType::COURSE, EventType::GENERAL_EVENT], true);
    }

    public function isOpen(CalendarEventsModel $event): bool
    {
        $row = $this->connection->fetchAssociative(
            'SELECT COUNT(id) AS registrations, COALESCE(SUM(hasParticipated = 1), 0) AS confirmed
            FROM tl_calendar_events_member
            WHERE eventId = ? AND stateOfSubscription IN (?)',
            [(int) $event->id, EventSubscriptionState::PARTICIPATION_CONFIRMATION_ALLOWED],
            [ParameterType::INTEGER, ArrayParameterType::STRING],
        );

        if (false === $row) {
            return false;
        }

        // No accepted or waiting-list registrations: nothing to confirm
        if ((int) $row['registrations'] < 1) {
            return false;
        }

        // Done as soon as one of them has been confirmed
        return (int) $row['confirmed'] < 1;
    }

    public function getLabel(): string
    {
        return $this->translator->trans('MSC.instructor_post_event_task.'.self::NAME, [], 'contao_default');
    }

    public function getUrl(CalendarEventsModel $event): string
    {
        return $this->router->generate(
            'contao_backend',
            [
                'do' => 'calendar',
                'table' => 'tl_calendar_events_member',
                'id' => $event->id,
            ],
            UrlGeneratorInterface::ABSOLUTE_URL,
        );
    }
}
