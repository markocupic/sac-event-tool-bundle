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

namespace Markocupic\SacEventToolBundle\Feature\AboSurvey;

use Contao\StringUtil;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Types\Types;
use Markocupic\SacEventToolBundle\Config\EventType;
use Markocupic\SacEventToolBundle\Config\TourguideQualification;

/**
 * Who may fill in the abo survey for a year:
 * - the member has a SAC member id (> 0) and
 * - there is an active back end user (tl_user: disable = 0, within start/stop) with the same sacMemberId
 *   (tl_user.sacMemberId is unique) and
 * - this back end user is not a mountain guide (tl_user.leiterQualifikation contains
 *   TourguideQualification::MOUNTAIN_GUIDE); this exclusion applies in any case and
 *   takes precedence over all of the following and
 * - one of the following applies to this back end user:
 *   - he is selected in the content element (tl_content.aboSurveyAllowedUsers),
 *   - he has a role selected in the content element (tl_content.aboSurveyAllowedUserRoles),
 *   - he has a role that belongs to the executive board (tl_user_role.belongsToExecutiveBoard),
 *   - he is instructor (tl_calendar_events_instructor, main or co-instructor) of at least one
 *     tour or course that starts within the event period (tl_content.aboSurveyEventPeriodStart
 *     until tl_content.aboSurveyEventPeriodEnd) and has release level 3 or higher
 *     (tl_event_release_level_policy.level >= 3).
 *
 * check() returns null if the member is eligible, otherwise the reason (one of the REASON_* constants).
 */
final readonly class AboSurveyEligibility
{
    public const string REASON_NO_SAC_MEMBER_ID = 'no_sac_member_id';

    public const string REASON_NO_BACKEND_USER = 'no_backend_user';

    public const string REASON_MOUNTAIN_GUIDE = 'mountain_guide';

    public const string REASON_NO_INSTRUCTOR_EVENT = 'no_instructor_event';

    public const int MIN_RELEASE_LEVEL = 3;

    public const array EVENT_TYPES = [
        EventType::TOUR,
        EventType::COURSE,
    ];

    public function __construct(private Connection $connection)
    {
    }

    /**
     * Checks whether the member may fill in the abo survey.
     *
     * @param int               $eventPeriodStart   events must start at or after this timestamp
     * @param int               $eventPeriodEnd     events must start at or before this timestamp
     * @param array<int|string> $allowedUserRoleIds tl_user_role.id selected in the content element
     * @param array<int|string> $allowedUserIds     tl_user.id selected in the content element
     *
     * @return string|null null if the member has access to the form, otherwise the reason
     *                     why he has no access (one of the REASON_* constants)
     */
    public function check(int $sacMemberId, int $eventPeriodStart, int $eventPeriodEnd, array $allowedUserRoleIds = [], array $allowedUserIds = []): string|null
    {
        if ($sacMemberId < 1) {
            return self::REASON_NO_SAC_MEMBER_ID;
        }

        // Active back end user only: not disabled and within the start/stop period.
        // tl_user.sacMemberId is unique, so there is at most one back end user per SAC member id.
        $now = time();

        $user = $this->connection->fetchAssociative(
            "SELECT id, userRole, leiterQualifikation FROM tl_user WHERE disable = ? AND sacMemberId = ? AND (start = '' OR start < ?) AND (stop = '' OR stop > ?)",
            [0, $sacMemberId, $now, $now],
        );

        if (false === $user) {
            return self::REASON_NO_BACKEND_USER;
        }

        // Mountain guides may never fill in the survey
        if ($this->isMountainGuide($user)) {
            return self::REASON_MOUNTAIN_GUIDE;
        }

        $userId = (int) $user['id'];

        // Explicitly allowed back end users
        if (\in_array($userId, array_map('intval', $allowedUserIds), true)) {
            return null;
        }

        $userRoleIds = $this->getUserRoleIds($user);

        // Explicitly allowed roles
        if (array_intersect($userRoleIds, array_map('intval', $allowedUserRoleIds))) {
            return null;
        }

        // Members of the executive board
        if ($this->hasExecutiveBoardRole($userRoleIds)) {
            return null;
        }

        if ($this->isInstructorOfReleasedEvent($userId, $eventPeriodStart, $eventPeriodEnd)) {
            return null;
        }

        return self::REASON_NO_INSTRUCTOR_EVENT;
    }

    /**
     * @param array<string, mixed> $user
     */
    private function isMountainGuide(array $user): bool
    {
        $qualifications = array_map('intval', StringUtil::deserialize($user['leiterQualifikation'] ?? null, true));

        return \in_array(TourguideQualification::MOUNTAIN_GUIDE, $qualifications, true);
    }

    /**
     * @param array<string, mixed> $user
     *
     * @return array<int>
     */
    private function getUserRoleIds(array $user): array
    {
        $roleIds = array_map('intval', StringUtil::deserialize($user['userRole'] ?? null, true));

        return array_values(array_unique(array_filter($roleIds)));
    }

    private function hasExecutiveBoardRole(array $userRoleIds): bool
    {
        if (empty($userRoleIds)) {
            return false;
        }

        $roleId = $this->connection->fetchOne(
            'SELECT id FROM tl_user_role WHERE id IN (:roleIds) AND belongsToExecutiveBoard = 1 LIMIT 1',
            ['roleIds' => $userRoleIds],
            ['roleIds' => ArrayParameterType::INTEGER],
        );

        return false !== $roleId;
    }

    private function isInstructorOfReleasedEvent(int $userId, int $eventPeriodStart, int $eventPeriodEnd): bool
    {
        $eventId = $this->connection->fetchOne(
            'SELECT e.id FROM tl_calendar_events e'
            .' INNER JOIN tl_calendar_events_instructor i ON i.pid = e.id'
            .' INNER JOIN tl_event_release_level_policy r ON r.id = e.eventReleaseLevel'
            .' WHERE i.userId = :userId'
            .' AND e.eventType IN (:eventTypes) AND r.level >= :minLevel'
            .' AND e.startDate BETWEEN :start AND :end'
            .' LIMIT 1',
            [
                'userId' => $userId,
                'eventTypes' => self::EVENT_TYPES,
                'minLevel' => self::MIN_RELEASE_LEVEL,
                'start' => $eventPeriodStart,
                'end' => $eventPeriodEnd,
            ],
            [
                'userId' => Types::INTEGER,
                'eventTypes' => ArrayParameterType::STRING,
            ],
        );

        return false !== $eventId;
    }
}
