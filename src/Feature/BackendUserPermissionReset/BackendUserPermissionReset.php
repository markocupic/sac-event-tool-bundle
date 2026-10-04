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

namespace Markocupic\SacEventToolBundle\Feature\BackendUserPermissionReset;

use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\FilesModel;
use Contao\StringUtil;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Types\Types;
use Psr\Log\LoggerInterface;
use Symfony\Component\Filesystem\Path;

/**
 * Resets the personal permissions of backend users who inherit the permissions of their groups
 * (tl_user.inherit = "extend", no admin). This way we can prevent a policy mess.
 *
 * After the reset the user has:
 * - the permissions of his active groups
 * - his home directory as file mount (see Feature\BackendUserHomeDirectory)
 *
 * See docs/features/backend-user-permission-reset.md
 */
class BackendUserPermissionReset
{
    // Contao core permissions, extended by $GLOBALS['TL_PERMISSIONS'] (e.g. calendars, calendarp, news, newp, ...)
    private const array CORE_PERMISSIONS = ['modules', 'themes', 'elements', 'frontendModules', 'fields', 'pagemounts', 'alpty', 'filemounts', 'fop', 'forms', 'formp', 'imageSizes', 'amg'];

    /**
     * @var list<string>|null
     */
    private array|null $permissionFields = null;

    public function __construct(
        private readonly ContaoFramework $framework,
        private readonly Connection $connection,
        private readonly string $sacevtUserBackendHomeDir,
        private readonly LoggerInterface|null $contaoGeneralLogger = null,
    ) {
    }

    /**
     * Only non-admin users who inherit the permissions of their groups are reset.
     */
    public function isResettable(string $username): bool
    {
        return false !== $this->connection->fetchOne(
            "SELECT id FROM tl_user WHERE username = ? AND admin = 0 AND inherit = 'extend'",
            [$username],
        );
    }

    /**
     * Resets the permissions of all resettable users. Also used by the Contao maintenance module (TL_PURGE).
     *
     * @return int number of reset users
     */
    public function resetAll(): int
    {
        $usernames = $this->connection->fetchFirstColumn(
            "SELECT username FROM tl_user WHERE admin = 0 AND inherit = 'extend' AND username <> '' ORDER BY username",
        );

        $count = 0;

        foreach ($usernames as $username) {
            if ($this->resetUser((string) $username)) {
                ++$count;
            }
        }

        $this->contaoGeneralLogger?->info(\sprintf('Successfully reset the backend permissions of %d non-admin backend user(s).', $count));

        return $count;
    }

    /**
     * @return bool false if the user does not exist
     */
    public function resetUser(string $username): bool
    {
        // $GLOBALS['TL_PERMISSIONS'] is only available after the initialization
        $this->framework->initialize();

        $user = $this->connection->fetchAssociative('SELECT id, `groups` FROM tl_user WHERE username = ?', [$username]);

        if (false === $user) {
            return false;
        }

        // Start with empty permissions ...
        $permissions = array_fill_keys($this->getPermissionFields(), []);

        // ... the home directory as file mount ...
        if (isset($permissions['filemounts'])) {
            $permissions['filemounts'] = $this->getHomeDirectoryFileMount((int) $user['id']);
        }

        // ... and the permissions of the active groups
        foreach ($this->findActiveGroups(StringUtil::deserialize($user['groups'], true)) as $group) {
            foreach (array_keys($permissions) as $field) {
                $value = StringUtil::deserialize($group[$field] ?? null, true);

                if ([] !== $value) {
                    $permissions[$field] = array_values(array_unique([...$permissions[$field], ...$value]));
                }
            }
        }

        if ([] !== $permissions) {
            $this->connection->update('tl_user', array_map(serialize(...), $permissions), ['id' => (int) $user['id']]);
        }

        return true;
    }

    /**
     * Permission fields that exist as column in tl_user.
     *
     * @return list<string>
     */
    private function getPermissionFields(): array
    {
        if (null !== $this->permissionFields) {
            return $this->permissionFields;
        }

        $fields = array_unique([...self::CORE_PERMISSIONS, ...($GLOBALS['TL_PERMISSIONS'] ?? [])]);
        $columns = $this->connection->createSchemaManager()->listTableColumns('tl_user');

        return $this->permissionFields = array_values(array_filter(
            $fields,
            static fn (string $field): bool => isset($columns[strtolower($field)]),
        ));
    }

    /**
     * @return list<string> UUID of the home directory or an empty array if there is none
     */
    private function getHomeDirectoryFileMount(int $userId): array
    {
        $folder = $this->framework->getAdapter(FilesModel::class)->findByPath(Path::join($this->sacevtUserBackendHomeDir, (string) $userId));

        return null === $folder ? [] : [$folder->uuid];
    }

    /**
     * Groups that are not disabled and within their start/stop period.
     *
     * @param array<mixed> $groupIds
     *
     * @return list<array<string, mixed>>
     */
    private function findActiveGroups(array $groupIds): array
    {
        $groupIds = array_map(intval(...), $groupIds);

        if ([] === $groupIds) {
            return [];
        }

        // Like Contao\Date::floorToMinute()
        $now = (string) (time() - time() % 60);

        return $this->connection->fetchAllAssociative(
            "SELECT * FROM tl_user_group WHERE id IN (?) AND disable = 0 AND (start = '' OR start <= ?) AND (stop = '' OR stop > ?)",
            [$groupIds, $now, $now],
            [ArrayParameterType::INTEGER, Types::STRING, Types::STRING],
        );
    }
}
