<?php

declare(strict_types=1);

/*
 * This file is part of SAC Event Tool Bundle.
 *
 * (c) Marko Cupic 2023 <m.cupic@gmx.ch>
 * @license GPL-3.0-or-later
 * For the full copyright and license information,
 * please view the LICENSE file that was distributed with this source code.
 * @link https://github.com/markocupic/sac-event-tool-bundle
 */

namespace Markocupic\SacEventToolBundle\Model;

use Contao\CalendarEventsModel;
use Contao\Database;
use Contao\Date;
use Contao\Events;
use Contao\Frontend;
use Contao\MemberModel;
use Contao\Model;
use Contao\UserModel;
use Markocupic\SacEventToolBundle\Config\EventSubscriptionState;

class CalendarEventsMemberModel extends Model
{
    /**
     * Table name.
     *
     * @var string
     */
    protected static $strTable = 'tl_calendar_events_member';

    /**
     * @param $memberId
     * @param $eventId
     *
     * @return bool
     */
    public static function isRegistered($memberId, $eventId)
    {
        $objMember = MemberModel::findByPk($memberId);

        if (null !== $objMember) {
            if ('' !== $objMember->sacMemberId) {
                $objEventsMembers = Database::getInstance()
                    ->prepare('SELECT * FROM '.static::$strTable.' WHERE eventId=? AND sacMemberId=?')
                    ->execute($eventId, $objMember->sacMemberId)
                ;

                if ($objEventsMembers->numRows) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * @return static|null
     */
    public static function findByMemberAndEvent(MemberModel $objMember, CalendarEventsModel $eventModel): self|null
    {
        $objDb = Database::getInstance()
            ->prepare('SELECT * FROM tl_calendar_events_member WHERE sacMemberId=? AND eventId=?')
            ->execute($objMember->sacMemberId, $eventModel->id)
        ;

        if ($objDb->numRows) {
            return static::findByPk($objDb->id);
        }

        return null;
    }

    /**
     * @param $memberId
     * @param array $arrEventTypeFilter
     * @param null  $intStartDateMin
     * @param null  $intStartDateMax
     * @param bool  $blnInstructorRole
     */
    public static function findEventsByMemberId($memberId, $arrEventTypeFilter = [], $intStartDateMin = null, $intStartDateMax = null, $blnInstructorRole = false): array
    {
        $arrEvents = [];
        $arrEventIDS = [];
        $objMember = MemberModel::findByPk($memberId);
        $blnHasEventsAsInstructor = false;

        $queryMin = null !== $intStartDateMin ? sprintf('startDate >= %s AND ', $intStartDateMin) : '';
        $queryMax = null !== $intStartDateMax ? sprintf('startDate <= %s AND ', $intStartDateMax) : '';

        if (null !== $objMember) {
            $objJoinedEvents = Database::getInstance()
                ->prepare('SELECT * FROM tl_calendar_events_member WHERE sacMemberId=?')
                ->execute($objMember->sacMemberId)
            ;

            if ($objJoinedEvents->numRows) {
                $arrEventIDS = $objJoinedEvents->fetchEach('eventId');
                $arrEventIDS = array_values(array_unique($arrEventIDS));
            }

            if ($blnInstructorRole) {
                $objUser = UserModel::findOneBySacMemberId($objMember->sacMemberId);

                if (null !== $objUser) {
                    $objJoinedEventsAsInstructor = Database::getInstance()
                        ->prepare('SELECT * FROM tl_calendar_events_instructor WHERE userId=?')
                        ->execute($objUser->id)
                    ;

                    if ($objJoinedEventsAsInstructor->numRows) {
                        $blnHasEventsAsInstructor = true;
                        $arrEventIDS = array_merge($objJoinedEventsAsInstructor->fetchEach('pid'), $arrEventIDS);
                        $arrEventIDS = array_values(array_unique($arrEventIDS));
                    }
                }
            }

            if (\count($arrEventIDS)) {
                $objEvents = Database::getInstance()
                    ->execute('SELECT * FROM tl_calendar_events WHERE '.$queryMin.$queryMax.'id IN('.implode(',', $arrEventIDS).') ORDER BY startDate DESC')
                ;

                while ($objEvents->next()) {
                    // Filter by event type
                    if (\count($arrEventTypeFilter) > 0) {
                        if (!\in_array($objEvents->eventType, $arrEventTypeFilter, false)) {
                            continue;
                        }
                    }

                    $objJoinedEvents = Database::getInstance()
                        ->prepare('SELECT * FROM tl_calendar_events_member WHERE sacMemberId=? AND eventId=?')
                        ->limit(1)
                        ->execute($objMember->sacMemberId, $objEvents->id)
                    ;

                    if ($objJoinedEvents->numRows) {
                        // If member had the role of a participant
                        $objEventModel = CalendarEventsModel::findByPk($objEvents->id);
                        $arr = $objEventModel->row();
                        $arr['dateSpan'] = $objEvents->startDate !== $objEvents->endDate ? Date::parse('d.m.', $objEvents->startDate).' - '.Date::parse('d.m.Y', $objEvents->endDate) : Date::parse('d.m.Y', $objEvents->startDate);
                        $arr['registrationId'] = $objJoinedEvents->id;
                        $arr['role'] = 'member';
                        $arr['objEvent'] = $objEventModel;
                        $arr['eventModel'] = $objEventModel;
                        $arr['eventRegistrationModel'] = self::findByPk($objJoinedEvents->id);
                        $arr['eventUrl'] = Events::generateEventUrl($objEventModel);
                        $arr['unregisterUrl'] = Frontend::addToUrl('do=unregisterUserFromEvent&amp;registrationId='.$objJoinedEvents->id);
                        $arrEvents[] = $arr;
                    } else {
                        // If member had the role of an instructor
                        if ($blnInstructorRole && $blnHasEventsAsInstructor) {
                            $objEventModel = CalendarEventsModel::findByPk($objEvents->id);
                            $arr = $objEventModel->row();
                            $arr['dateSpan'] = $objEvents->startDate !== $objEvents->endDate ? Date::parse('d.m.', $objEvents->startDate).' - '.Date::parse('d.m.Y', $objEvents->endDate) : Date::parse('d.m.Y', $objEvents->startDate);
                            $arr['registrationId'] = null;
                            $arr['role'] = 'instructor';
                            $arr['objEvent'] = $objEventModel;
                            $arr['eventModel'] = $objEventModel;
                            $arr['eventUrl'] = Events::generateEventUrl($objEventModel);
                            $arrEvents[] = $arr;
                        }
                    }
                }
            }
        }

        return $arrEvents;
    }

    /**
     * @param $memberId
     * @param array $arrEventTypeFilter
     *
     * @return array
     */
    public static function findUpcomingEventsByMemberId($memberId, $arrEventTypeFilter = [], $blnInstructorRole = false)
    {
        return static::findEventsByMemberId($memberId, $arrEventTypeFilter, time(), null, $blnInstructorRole);
    }

    /**
     * @param $memberId
     * @param array $arrEventTypeFilter
     */
    public static function findPastEventsByMemberId($memberId, $arrEventTypeFilter = [], $blnInstructorRole = false): array
    {
        return static::findEventsByMemberId($memberId, $arrEventTypeFilter, null, time(), $blnInstructorRole);
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

            $objDb = Database::getInstance()
                ->prepare('SELECT * FROM tl_calendar_events_member WHERE id != ? && eventId=? && stateOfSubscription=?')
                ->execute($objMember->id, $objEvent->id, EventSubscriptionState::SUBSCRIPTION_ACCEPTED)
            ;

            if ($objDb->numRows < $objEvent->maxMembers) {
                return true;
            }
        }

        return false;
    }
}
