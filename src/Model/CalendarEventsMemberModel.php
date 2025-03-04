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
use Contao\Events;
use Contao\Frontend;
use Contao\MemberModel;
use Contao\Model;
use Contao\System;
use Contao\UserModel;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Types\Types;
use Markocupic\SacEventToolBundle\Config\EventSubscriptionState;
use Markocupic\SacEventToolBundle\Util\CalendarEventsUtil;

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

        if (false === $id) {
            return null;
        }

        return static::findByPk($id);
    }

    public static function findEventsByMemberId(int $memberId, array $arrEventTypeFilter = [], int|null $intStartDateMin = null, int|null $intStartDateMax = null, bool $blnInstructorRole = false, bool $blnShowEventsWithParticipationOnly = false): array
    {
        $arrEvents = [];
        $objMember = MemberModel::findByPk($memberId);
        $blnHasEventsAsInstructor = false;

        if (null === $objMember) {
            return $arrEvents;
        }

        /** @var Connection $database */
        $database = System::getContainer()->get('database_connection');

        if ($blnShowEventsWithParticipationOnly) {
            $arrEventIDS = $database->fetchFirstColumn(
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
            $arrEventIDS = $database->fetchFirstColumn(
                'SELECT eventId FROM tl_calendar_events_member WHERE sacMemberId = ?',
                [
                    $objMember->sacMemberId,
                ],
                [
                    Types::INTEGER,
                ]
            );
        }

        if ($blnInstructorRole) {
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
                    $arrEventIDS = array_merge($arrEventIDS, $arrEventIDSAsInstructor);
                }
            }
        }

        $arrEventIDS = array_filter(array_unique($arrEventIDS));

        if (\count($arrEventIDS)) {
            $qb = $database->createQueryBuilder();
            $qb->select('*')
                ->from('tl_calendar_events', 't')
                ->where('t.id IN (:ids)')
                ->setParameter('ids', array_map('intval', $arrEventIDS), ArrayParameterType::INTEGER)
            ;

            if (null !== $intStartDateMin) {
                $qb->andWhere('t.startDate >= :startDateMin')
                    ->setParameter('startDateMin', $intStartDateMin, Types::INTEGER)
                ;
            }

            if (null !== $intStartDateMax) {
                $qb->andWhere('t.startDate <= :startDateMax')
                    ->setParameter('startDateMax', $intStartDateMax, Types::INTEGER)
                ;
            }

            foreach ($qb->fetchAllAssociative() as $arrEvent) {
                // Filter by event type
                if (!empty($arrEventTypeFilter)) {
                    if (!\in_array($arrEvent['eventType'], $arrEventTypeFilter, true)) {
                        continue;
                    }
                }

                $arrEventReg = $database->fetchAssociative(
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

                $objEventModel = CalendarEventsModel::findByPk($arrEvent['id']);
                $row = $objEventModel->row();
                $row['dateSpan'] = System::getContainer()->get(CalendarEventsUtil::class)->getEventPeriod($objEventModel, 'd.m.Y');
                $row['objEvent'] = $objEventModel;
                $row['eventModel'] = $objEventModel;
                $row['eventUrl'] = Events::generateEventUrl($objEventModel);

                if (false !== $arrEventReg) {
                    // If member has the role "participant"
                    $row['eventRegistrationModel'] = self::findByPk($arrEventReg['id']);
                    $row['registrationId'] = $arrEventReg['id'];
                    $row['role'] = 'member';
                    $row['unregisterUrl'] = Frontend::addToUrl('do=unregisterUserFromEvent&amp;registrationId='.$arrEventReg['id']);
                } else {
                    // If member has the role "instructor"
                    if ($blnInstructorRole && $blnHasEventsAsInstructor) {
                        $row['registrationId'] = null;
                        $row['role'] = 'instructor';
                    }
                }
                $arrEvents[] = $row;
            }
        }

        return $arrEvents;
    }

    public static function findUpcomingEventsByMemberId(int $memberId, array $arrEventTypeFilter = [], bool $blnInstructorRole = false): array
    {
        return static::findEventsByMemberId($memberId, $arrEventTypeFilter, time(), null, $blnInstructorRole);
    }

    public static function findPastEventsByMemberId(int $memberId, array $arrEventTypeFilter = [], bool $blnInstructorRole = false, bool $blnShowEventsWithParticipationOnly = true): array
    {
        return static::findEventsByMemberId($memberId, $arrEventTypeFilter, null, time(), $blnInstructorRole, $blnShowEventsWithParticipationOnly);
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
