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
use Doctrine\DBAL\Connection;
use Markocupic\SacEventToolBundle\Config\EventSubscriptionState;
use Markocupic\SacEventToolBundle\Config\EventType;
use Symfony\Component\DependencyInjection\Attribute\AsTaggedItem;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Tours and courses: the participation of the accepted participants has to be confirmed.
 *
 * The task only exists if there is at least one accepted registration.
 * It is done as soon as at least one ACCEPTED registration has hasParticipated = 1.
 * hasParticipated = 1 on a registration that is not accepted (e.g. waiting list) does not count:
 * the reminder makes the instructor update the subscription state as well.
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
        return \in_array($event->eventType, [EventType::TOUR, EventType::LAST_MINUTE_TOUR, EventType::COURSE], true);
    }

    public function isOpen(CalendarEventsModel $event): bool
    {
        $row = $this->connection->fetchAssociative(
            'SELECT COUNT(id) AS accepted, COALESCE(SUM(hasParticipated = 1), 0) AS confirmed FROM tl_calendar_events_member WHERE eventId = ? AND stateOfSubscription = ?',
            [(int) $event->id, EventSubscriptionState::SUBSCRIPTION_ACCEPTED],
        );

        if (false === $row) {
            return false;
        }

        // No accepted participants: nothing to confirm
        if ((int) $row['accepted'] < 1) {
            return false;
        }

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
