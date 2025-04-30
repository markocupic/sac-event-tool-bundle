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

namespace Markocupic\SacEventToolBundle\Model;

use Contao\CalendarEventsModel;
use Contao\MemberModel;
use Contao\Model;
use Contao\System;
use Contao\UserModel;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Types\Types;
use Markocupic\SacEventToolBundle\Config\EventSubscriptionState;
use Markocupic\SacEventToolBundle\Config\EventType;
use Markocupic\SacEventToolBundle\Util\CalendarEventsUtil;
use Symfony\Component\OptionsResolver\OptionsResolver;

class CalendarEventsMemberModel extends Model
{
    /**
     * Table name.
     *
     * @var string
     */
    protected static $strTable = 'tl_calendar_events_member';

    public static function isRegistered(int $memberId, int $eventId): bool
    {
        $objMember = MemberModel::findByPk($memberId);

        if (null === $objMember) {
            return false;
        }

        if (empty($objMember->sacMemberId)) {
            return false;
        }

        /** @var Connection $database */
        $database = System::getContainer()->get('database_connection');

        $eventReg = $database->fetchOne(
            'SELECT * FROM '.static::$strTable.' WHERE eventId = ? AND sacMemberId = ?',
            [
                $eventId,
                $objMember->sacMemberId,
            ],
            [
                Types::INTEGER,
                Types::STRING,
            ]
        );

        if (false === $eventReg) {
            return false;
        }

        return true;
    }

    public static function findByMemberAndEvent(MemberModel $objMember, CalendarEventsModel $eventModel): Model|null
    {
        /** @var Connection $database */
        $database = System::getContainer()->get('database_connection');

        $id = $database->fetchOne(
            'SELECT id FROM tl_calendar_events_member WHERE sacMemberId = ? AND eventId = ?',
            [
                $objMember->sacMemberId,
                $eventModel->id,
            ],
            [
                Types::INTEGER,
                Types::INTEGER,
            ]
        );

        return false === $id ? null : static::findByPk($id);
    }

    public static function findEventsByMemberId(int $memberId, array $options): array
    {
        $options = (new OptionsResolver())
            ->setDefaults([
                'eventTypeFilter' => [],
                'startTstamp' => null,
                'endTstamp' => null,
                'blnInstructorRole' => false,
                'blnShowEventsWithParticipationOnly' => false,
                'sorting' => 'DESC',
            ])
            ->addAllowedValues('eventTypeFilter', static fn (array $values = []) => empty(array_diff($values, EventType::ALL)))
            ->addAllowedValues('sorting', ['ASC', 'DESC'])
            ->addAllowedTypes('startTstamp', ['null', 'int'])
            ->addAllowedTypes('endTstamp', ['null', 'int'])
            ->addAllowedTypes('blnInstructorRole', ['bool'])
            ->addAllowedTypes('blnShowEventsWithParticipationOnly', ['bool'])
            ->resolve($options)
        ;

        $arrEvents = [];
        $objMember = MemberModel::findByPk($memberId);
        $blnHasEventsAsInstructor = false;

        if (null === $objMember) {
            return $arrEvents;
        }

        /** @var Connection $database */
        $database = System::getContainer()->get('database_connection');

        if ($options['blnShowEventsWithParticipationOnly']) {
            $eventIDS = $database->fetchFirstColumn(
                'SELECT eventId FROM tl_calendar_events_member WHERE sacMemberId = ? AND hasParticipated = ?',
                [
                    $objMember->sacMemberId,
                    1,
                ],
                [
                    Types::INTEGER,
                    Types::INTEGER,
                ]
            );
        } else {
            $eventIDS = $database->fetchFirstColumn(
                'SELECT eventId FROM tl_calendar_events_member WHERE sacMemberId = ?',
                [
                    $objMember->sacMemberId,
                ],
                [
                    Types::INTEGER,
                ]
            );
        }

        if (true === $options['blnInstructorRole']) {
            $objUser = UserModel::findOneBySacMemberId($objMember->sacMemberId);

            if (null !== $objUser) {
                $arrEventIDSAsInstructor = $database->fetchFirstColumn(
                    'SELECT pid FROM tl_calendar_events_instructor WHERE userId = ?',
                    [
                        $objUser->id,
                    ],
                    [
                        Types::INTEGER,
                    ]
                );

                if (\count($arrEventIDSAsInstructor)) {
                    $blnHasEventsAsInstructor = true;
                    $eventIDS = array_merge($eventIDS, $arrEventIDSAsInstructor);
                }
            }
        }

        $eventIDS = array_filter(array_unique($eventIDS));

        if (!empty($eventIDS)) {
            $qb = $database->createQueryBuilder();
            $qb->select('*')
                ->from('tl_calendar_events', 't')
                ->where('t.id IN (:ids)')
                ->setParameter('ids', array_map('intval', $eventIDS), ArrayParameterType::INTEGER)
            ;

            if (null !== $options['startTstamp']) {
                $qb->andWhere('t.startDate >= :startTstamp')
                    ->setParameter('startTstamp', $options['startTstamp'], Types::INTEGER)
                ;
            }

            if (null !== $options['endTstamp']) {
                $qb->andWhere('t.startDate <= :endTstamp')
                    ->setParameter('endTstamp', $options['endTstamp'], Types::INTEGER)
                ;
            }

            if (!empty($options['eventTypeFilter'])) {
                $qb->andWhere($qb->expr()->in('t.eventType', ':eventTypeFilter'))
                    ->setParameter('eventTypeFilter', $options['eventTypeFilter'], ArrayParameterType::STRING)
                ;
            }

            $qb->orderBy('t.startDate', $options['sorting']);

            foreach ($qb->fetchAllAssociative() as $arrEvent) {
                $rowReg = $database->fetchAssociative(
                    'SELECT * FROM tl_calendar_events_member WHERE sacMemberId = ? AND eventId = ?',
                    [
                        $objMember->sacMemberId,
                        $arrEvent['id'],
                    ],
                    [
                        Types::INTEGER,
                        Types::INTEGER,
                    ]
                );

                $objEvent = CalendarEventsModel::findByPk($arrEvent['id']);
                $rowEvent = $objEvent->row();
                $rowEvent['dateSpan'] = System::getContainer()->get(CalendarEventsUtil::class)->getEventPeriod($objEvent, 'd.m.Y');
                $rowEvent['objEvent'] = $objEvent;
                $rowEvent['eventModel'] = $objEvent;
                $rowEvent['eventUrl'] = System::getContainer()->get('contao.routing.content_url_generator')->generate($objEvent);

                if (false !== $rowReg) {
                    // If member has the role "participant"
                    $rowEvent['eventRegistrationModel'] = self::findByPk($rowReg['id']);
                    $rowEvent['registrationId'] = $rowReg['id'];
                    $rowEvent['role'] = 'member';
                } else {
                    // If member has the role "instructor"
                    if (true === $options['blnInstructorRole'] && $blnHasEventsAsInstructor) {
                        $rowEvent['registrationId'] = null;
                        $rowEvent['role'] = 'instructor';
                    }
                }
                $arrEvents[] = $rowEvent;
            }
        }

        return $arrEvents;
    }

    public static function findUpcomingEventsByMemberId(int $memberId, array $eventTypeFilter = [], bool $blnInstructorRole = false, $sorting = 'ASC'): array
    {
        $options = [
            'eventTypeFilter' => $eventTypeFilter,
            'startTstamp' => time(),
            'endTstamp' => null,
            'blnInstructorRole' => $blnInstructorRole,
            'sorting' => $sorting,
        ];

        return static::findEventsByMemberId($memberId, $options);
    }

    public static function findPastEventsByMemberId(int $memberId, array $eventTypeFilter = [], bool $blnInstructorRole = false, bool $blnShowEventsWithParticipationOnly = true, $sorting = 'DESC'): array
    {
        $options = [
            'eventTypeFilter' => $eventTypeFilter,
            'startTstamp' => null,
            'endTstamp' => time(),
            'blnInstructorRole' => $blnInstructorRole,
            'blnShowEventsWithParticipationOnly' => $blnShowEventsWithParticipationOnly,
            'sorting' => $sorting,
        ];

        return static::findEventsByMemberId($memberId, $options);
    }

    public static function canAcceptSubscription(self $objMember, CalendarEventsModel $objEvent): bool
    {
        if (!$objEvent->addMinAndMaxMembers) {
            return true;
        }

        if ($objEvent->addMinAndMaxMembers && (int) $objEvent->maxMembers > 0) {
            if (!$objEvent->addMinAndMaxMembers || ($objEvent->addMinAndMaxMembers && empty($objEvent->maxMembers))) {
                return true;
            }

            /** @var Connection $database */
            $database = System::getContainer()->get('database_connection');

            $regCount = $database->fetchOne(
                'SELECT COUNT(id) FROM tl_calendar_events_member WHERE id != ? AND eventId = ? AND stateOfSubscription = ?',
                [
                    $objMember->id,
                    $objEvent->id,
                    EventSubscriptionState::SUBSCRIPTION_ACCEPTED,
                ],
                [
                    Types::INTEGER,
                    Types::INTEGER,
                    Types::STRING,
                ]
            );

            if ($regCount < $objEvent->maxMembers) {
                return true;
            }
        }

        return false;
    }
}
