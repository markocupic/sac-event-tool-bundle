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

namespace Markocupic\SacEventToolBundle\EventReleaseLevel;

use Contao\StringUtil;
use Markocupic\SacEventToolBundle\Model\EventReleaseLevelPolicyModel;

/**
 * Evaluates the permission rules of a release level (tl_event_release_level_policy.permissionRules).
 *
 * A rule grants its flags to the selected parties of the event (author, main instructor,
 * instructors, registration coordinator) or to the members of the selected user groups (or).
 * Admins are not covered by the rules, see CalendarEventsVoter.
 */
final class EventReleaseLevelPermissionRules
{
    public const string PARTY_EVENT_AUTHOR = 'event_author';

    public const string PARTY_MAIN_INSTRUCTOR = 'main_instructor';

    public const string PARTY_EVENT_INSTRUCTORS = 'event_instructors';

    public const string PARTY_REGISTRATION_COORDINATOR = 'registration_coordinator';

    public const string FLAG_WRITE_EVENT = 'can_write_event';

    public const string FLAG_DELETE_EVENT = 'can_delete_event';

    public const string FLAG_CUT_EVENT = 'can_cut_event';

    public const string FLAG_ADMINISTER_EVENT_REGISTRATIONS = 'can_administer_event_registrations';

    public const string FLAG_UPGRADE_RELEASE_LEVEL = 'can_upgrade_release_level';

    public const string FLAG_DOWNGRADE_RELEASE_LEVEL = 'can_downgrade_release_level';

    public const string FLAG_VIEW_PARTICIPANT_EVENT_HISTORY = 'can_view_participant_event_history';

    /**
     * The rules are parsed once per request and release level.
     *
     * @var array<int, list<array{parties: list<string>, groups: list<int>, flags: list<string>}>>
     */
    private array $rulesByLevel = [];

    /**
     * Checks whether a rule of the release level grants the flag.
     *
     * The callbacks are only called if needed (e.g. the instructors are only loaded if a
     * rule with the flag applies to the instructors).
     *
     * @param callable(string): bool $isParty       Is the user the given party of the event?
     * @param callable(int): bool    $isGroupMember Is the user a member of the given user group?
     */
    public function isGranted(EventReleaseLevelPolicyModel $level, string $flag, callable $isParty, callable $isGroupMember): bool
    {
        foreach ($this->getRules($level) as $rule) {
            if (!\in_array($flag, $rule['flags'], true)) {
                continue;
            }

            foreach ($rule['parties'] as $party) {
                if ($isParty($party)) {
                    return true;
                }
            }

            foreach ($rule['groups'] as $groupId) {
                if ($isGroupMember($groupId)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * @return list<array{parties: list<string>, groups: list<int>, flags: list<string>}>
     */
    private function getRules(EventReleaseLevelPolicyModel $level): array
    {
        $levelId = (int) $level->id;

        if (isset($this->rulesByLevel[$levelId])) {
            return $this->rulesByLevel[$levelId];
        }

        $rules = [];

        // Group widget: [1 => ['parties' => [...], 'groups' => ['3', '5'], 'flags' => [...]], ...]
        foreach (StringUtil::deserialize($level->permissionRules, true) as $rule) {
            if (!\is_array($rule)) {
                continue;
            }

            $rules[] = [
                'parties' => array_values(array_map('strval', StringUtil::deserialize($rule['parties'] ?? null, true))),
                'groups' => self::getGroupIds($rule),
                'flags' => array_values(array_map('strval', StringUtil::deserialize($rule['flags'] ?? null, true))),
            ];
        }

        return $this->rulesByLevel[$levelId] = $rules;
    }

    /**
     * IDs of the user groups of a rule.
     *
     * @param array<string, mixed> $rule
     *
     * @return list<int>
     */
    private static function getGroupIds(array $rule): array
    {
        $groupIds = StringUtil::deserialize($rule['groups'] ?? null, true);

        return array_values(array_unique(array_filter(array_map('intval', $groupIds), static fn (int $groupId): bool => $groupId > 0)));
    }
}
