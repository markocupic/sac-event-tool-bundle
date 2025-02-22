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
use Contao\Date;
use Contao\Events;
use Contao\Frontend;
use Contao\MemberModel;
use Contao\Model;
use Contao\System;
use Contao\UserModel;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Types\Types;
use Markocupic\SacEventToolBundle\Config\EventSubscriptionState;

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
            $queryStartDate = null !== $intStartDateMin ? sprintf('startDate >= %s AND ', $intStartDateMin) : '';
            $queryEndDate = null !== $intStartDateMax ? sprintf('startDate <= %s AND ', $intStartDateMax) : '';

            $events = $database->fetchAllAssociative('SELECT * FROM tl_calendar_events WHERE '.$queryStartDate.$queryEndDate.'id IN('.implode(',', $arrEventIDS).') ORDER BY startDate DESC');

            foreach ($events as $arrEvent) {
                // Filter by event type
                if (!empty($arrEventTypeFilter)) {
                    if (!\in_array($arrEvent['eventType'], $arrEventTypeFilter, true)) {
                        continue;
                    }
                }

                $arrEventReg = $database->fetchAssociative(
                    'SELECT * FROM tl_calendar_events_member WHERE sacMemberId=? AND eventId = ?',
                    [
                        $objMember->sacMemberId,
                        $arrEvent['id'],
                    ],
                    [
                        Types::INTEGER,
                        Types::INTEGER,
                    ]
                );

                if (false !== $arrEventReg) {
                    // If member has the role "participant"
                    $objEventModel = CalendarEventsModel::findByPk($arrEvent['id']);
                    $row = $objEventModel->row();
                    $row['dateSpan'] = $arrEvent['startDate'] !== $arrEvent['endDate'] ? Date::parse('d.m.', $arrEvent['startDate']).' - '.Date::parse('d.m.Y', $arrEvent['endDate']) : Date::parse('d.m.Y', $arrEvent['startDate']);
                    $row['registrationId'] = $arrEventReg['id'];
                    $row['role'] = 'member';
                    $row['objEvent'] = $objEventModel;
                    $row['eventModel'] = $objEventModel;
                    $row['eventRegistrationModel'] = self::findByPk($arrEventReg['id']);
                    $row['eventUrl'] = Events::generateEventUrl($objEventModel);
                    $row['unregisterUrl'] = Frontend::addToUrl('do=unregisterUserFromEvent&amp;registrationId='.$arrEventReg['id']);
                    $arrEvents[] = $row;
                } else {
                    // If member has the role "instructor"
                    if ($blnInstructorRole && $blnHasEventsAsInstructor) {
                        $objEventModel = CalendarEventsModel::findByPk($arrEvent['id']);
                        $row = $objEventModel->row();
                        $row['dateSpan'] = $arrEvent['startDate'] !== $arrEvent['endDate'] ? Date::parse('d.m.', $arrEvent['startDate']).' - '.Date::parse('d.m.Y', $arrEvent['endDate']) : Date::parse('d.m.Y', $arrEvent['startDate']);
                        $row['registrationId'] = null;
                        $row['role'] = 'instructor';
                        $row['objEvent'] = $objEventModel;
                        $row['eventModel'] = $objEventModel;
                        $row['eventUrl'] = Events::generateEventUrl($objEventModel);
                        $arrEvents[] = $row;
                    }
                }
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
