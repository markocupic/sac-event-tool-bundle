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

namespace Markocupic\SacEventToolBundle\Migration\Version503;

use Contao\CoreBundle\Migration\AbstractMigration;
use Contao\CoreBundle\Migration\MigrationResult;
use Contao\StringUtil;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Types\Types;

/**
 * Converts the permissions of the release levels into permission rules
 * (tl_event_release_level_policy.permissionRules, group widget).
 *
 * - Author and instructors: one rule for both parties. The flags are taken from the author
 *   and instructor fields (allow*AccessTo*, allowAdministerEventRegistrationsTo*,
 *   allowSwitchingTo*). Normally the author and the instructors have the same rights. If not,
 *   the rule only contains the flags that both have (the safe choice) and the migration
 *   reports the release level. The rule is also created if it contains no flags.
 * - Registration coordinator (tl_calendar_events.registrationGoesTo): "can_write_event" on
 *   every release level, additionally
 *   "can_administer_event_registrations" on the highest release level of the release level
 *   system (registrations only exist for published events, i.e. on the highest level).
 * - User groups: one rule per group (groupEventPerm and groupReleaseLevelPerm merged).
 *
 * Only release levels without rules (permissionRules IS NULL) are converted, existing rules
 * are never overwritten. New release levels start with an empty rule set (DCA default), they
 * are not converted either.
 *
 * The old fields have been removed from the DCA. The migration only runs as long as their
 * columns still exist, i.e. the schema update must not drop them before (contao:migrate
 * --no-interaction skips DROP statements unless --with-deletes is given).
 *
 * @internal
 */
class EventReleaseLevelPermissionRulesMigration extends AbstractMigration
{
    public const array FLAGS = [
        'can_write_event',
        'can_delete_event',
        'can_cut_event',
        'can_administer_event_registrations',
        'can_upgrade_release_level',
        'can_downgrade_release_level',
    ];

    private const string TABLE = 'tl_event_release_level_policy';

    private const array PARTIES = ['event_author', 'event_instructors'];

    /**
     * The permissions of the user groups per field, as evaluated by the old CalendarEventsVoter.
     */
    private const array GROUP_PERMISSIONS = [
        'groupEventPerm' => [
            'canWriteEvent' => 'can_write_event',
            'canDeleteEvent' => 'can_delete_event',
            'canCutEvent' => 'can_cut_event',
            'canAdministerEventRegistrations' => 'can_administer_event_registrations',
        ],
        'groupReleaseLevelPerm' => [
            'canRelLevelUp' => 'can_upgrade_release_level',
            'canRelLevelDown' => 'can_downgrade_release_level',
        ],
    ];

    public function __construct(private readonly Connection $connection)
    {
    }

    public function shouldRun(): bool
    {
        $schemaManager = $this->connection->createSchemaManager();

        if (!$schemaManager->tablesExist([self::TABLE])) {
            return false;
        }

        $columns = array_change_key_case($schemaManager->listTableColumns(self::TABLE));

        // The column is created by the schema update, the migration runs afterwards
        if (!isset($columns['permissionrules'])) {
            return false;
        }

        // Nothing to convert if the old permission fields have already been dropped
        if (!isset($columns['allowwriteaccesstoauthor'])) {
            return false;
        }

        return (int) $this->connection->fetchOne('SELECT COUNT(*) FROM tl_event_release_level_policy WHERE permissionRules IS NULL') > 0;
    }

    public function run(): MigrationResult
    {
        $rows = $this->connection->fetchAllAssociative('SELECT * FROM tl_event_release_level_policy WHERE permissionRules IS NULL ORDER BY pid, level');

        // The highest level of every release level system: [pid => level]
        $highestLevels = $this->connection->fetchAllKeyValue('SELECT pid, MAX(level) FROM tl_event_release_level_policy GROUP BY pid');

        $warnings = [];

        foreach ($rows as $row) {
            if (self::getPartyFlags($row, 'Author') !== self::getPartyFlags($row, 'Instructors')) {
                $warnings[] = \sprintf('ID %d (pid %d, level %s)', $row['id'], $row['pid'], $row['level'] ?? '-');
            }

            $this->connection->update(
                self::TABLE,
                ['permissionRules' => serialize(self::createRules($row, self::isHighestLevel($row, $highestLevels)))],
                ['id' => (int) $row['id']],
                ['permissionRules' => Types::BLOB],
            );
        }

        $message = \sprintf('tl_event_release_level_policy: converted the permissions of %d release level(s) into permission rules.', \count($rows));

        if ([] !== $warnings) {
            $message .= \sprintf(' The author and the instructors have different rights on the following release levels, only the common rights have been converted, please check: %s.', implode('; ', $warnings));
        }

        return $this->createResult(true, $message);
    }

    /**
     * Returns the rules in the format of the group widget (serialized storage):
     * [1 => ['parties' => [...], 'group' => '', 'flags' => [...]], 2 => ...].
     *
     * @param array<string, mixed> $row            the release level (tl_event_release_level_policy)
     * @param bool                 $isHighestLevel whether it is the highest level of its release level system
     */
    public static function createRules(array $row, bool $isHighestLevel = false): array
    {
        $rules = [];

        // Author and instructors have the same rights
        $partyFlags = array_intersect(self::getPartyFlags($row, 'Author'), self::getPartyFlags($row, 'Instructors'));

        $rules[] = ['parties' => self::PARTIES, 'group' => '', 'flags' => self::sortFlags($partyFlags)];

        // The registration coordinator edits the event and administers the registrations
        $coordinatorFlags = ['can_write_event'];

        if ($isHighestLevel) {
            $coordinatorFlags[] = 'can_administer_event_registrations';
        }

        $rules[] = ['parties' => ['registration_coordinator'], 'group' => '', 'flags' => self::sortFlags($coordinatorFlags)];

        // User groups
        $groupFlags = [];

        foreach (self::GROUP_PERMISSIONS as $field => $permissionMap) {
            foreach (StringUtil::deserialize($row[$field] ?? null, true) as $entry) {
                if (!\is_array($entry) || empty($entry['group'])) {
                    continue;
                }

                foreach (StringUtil::deserialize($entry['permissions'] ?? null, true) as $permission) {
                    if (\is_string($permission) && isset($permissionMap[$permission])) {
                        $groupFlags[(string) $entry['group']][] = $permissionMap[$permission];
                    }
                }
            }
        }

        foreach ($groupFlags as $group => $flags) {
            $rules[] = ['parties' => [], 'group' => (string) $group, 'flags' => self::sortFlags($flags)];
        }

        // The group widget uses the keys 1, 2, 3, ... as element IDs
        return array_combine(range(1, \count($rules)), $rules);
    }

    /**
     * @param array<string, mixed>     $row           the release level (tl_event_release_level_policy)
     * @param array<int|string, mixed> $highestLevels [pid => highest level]
     */
    private static function isHighestLevel(array $row, array $highestLevels): bool
    {
        if (null === ($row['level'] ?? null) || !isset($highestLevels[$row['pid']])) {
            return false;
        }

        return (int) $row['level'] === (int) $highestLevels[$row['pid']];
    }

    /**
     * The rights of the author or the instructors ($party = "Author" or "Instructors"),
     * as in CalendarEventsVoter: switching the level requires write access too.
     *
     * @return list<string>
     */
    private static function getPartyFlags(array $row, string $party): array
    {
        $canWrite = !empty($row['allowWriteAccessTo'.$party]);

        $flags = [];

        if ($canWrite) {
            $flags[] = 'can_write_event';
        }

        if (!empty($row['allowDeleteAccessTo'.$party])) {
            $flags[] = 'can_delete_event';
        }

        if (!empty($row['allowCutAccessTo'.$party])) {
            $flags[] = 'can_cut_event';
        }

        // The field names use the plural for authors: allowAdministerEventRegistrationsToAuthors
        if (!empty($row['allowAdministerEventRegistrationsTo'.('Author' === $party ? 'Authors' : $party)])) {
            $flags[] = 'can_administer_event_registrations';
        }

        if ($canWrite && !empty($row['allowSwitchingToNextLevel'])) {
            $flags[] = 'can_upgrade_release_level';
        }

        if ($canWrite && !empty($row['allowSwitchingToPrevLevel'])) {
            $flags[] = 'can_downgrade_release_level';
        }

        return $flags;
    }

    /**
     * @param list<string> $flags
     *
     * @return list<string>
     */
    private static function sortFlags(array $flags): array
    {
        return array_values(array_intersect(self::FLAGS, $flags));
    }
}
