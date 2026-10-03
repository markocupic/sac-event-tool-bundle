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

namespace Markocupic\SacEventToolBundle\Feature\MemberToUserSync;

use Contao\CoreBundle\Monolog\ContaoContext;
use Doctrine\DBAL\Connection;
use Markocupic\SacEventToolBundle\Config\Log;
use Psr\Log\LoggerInterface;
use Symfony\Component\Stopwatch\Stopwatch;

/**
 * Mirror/Update tl_user from tl_member. Unidirectional sync tl_member -> tl_user.
 *
 * The users are read together with the data of their member in one query and streamed
 * row by row. A user is only updated if at least one field differs.
 *
 * See docs/features/member-to-user-sync.md
 */
class MemberToUserSync
{
    // Copied 1:1 from tl_member to tl_user
    private const array FIELDS = ['firstname', 'lastname', 'sectionId', 'dateOfBirth', 'email', 'street', 'postal', 'city', 'country', 'gender', 'phone', 'mobile'];

    private const array EMPTY_SYNC_LOG = [
        'log' => [],
        'processed' => 0,
        'updates' => 0,
        'disabled' => 0,
        'duration' => 0,
        'with_error' => false,
        'exception' => '',
    ];

    private array $syncLog = self::EMPTY_SYNC_LOG;

    public function __construct(
        private readonly Connection $connection,
        private readonly LoggerInterface|null $contaoGeneralLogger = null,
    ) {
    }

    public function run(): void
    {
        $stopWatchEvent = (new Stopwatch())->start('SYNC_MEMBER_WITH_USER');

        // The service is shared: start every run with an empty log
        $this->syncLog = self::EMPTY_SYNC_LOG;

        $this->connection->beginTransaction();

        try {
            $processedUserIds = [];

            foreach ($this->iterateUsers() as $row) {
                $userId = (int) $row['id'];

                // Several members with the same sacMemberId: only the first one (lowest ID) counts
                if (isset($processedUserIds[$userId])) {
                    continue;
                }

                $processedUserIds[$userId] = true;
                ++$this->syncLog['processed'];

                if (null === $row['member_id']) {
                    $this->disableUser($row);
                } else {
                    $this->updateUser($row);
                }
            }

            $this->connection->commit();
        } catch (\Throwable $e) {
            if ($this->connection->isTransactionActive()) {
                $this->connection->rollBack();
            }

            $this->syncLog['with_error'] = true;
            $this->syncLog['exception'] = $e->getMessage();
        }

        $this->syncLog['duration'] = round($stopWatchEvent->stop()->getDuration() / 1000, 6);

        $this->log(\sprintf(
            'Successfully completed the merging process from tl_member to tl_user. Processed %d data records. Total updates: %d. Disabled %d user(s). Duration: %d s.',
            $this->syncLog['processed'],
            $this->syncLog['updates'],
            $this->syncLog['disabled'],
            $this->syncLog['duration'],
        ));
    }

    public function getSyncLog(): array
    {
        return $this->syncLog;
    }

    /**
     * Returns only the fields of the user that differ from the member data.
     * An empty array means: nothing to update.
     *
     * @param array<string, mixed> $row user (fields without prefix) and member data (fields with prefix "member_")
     *
     * @return array<string, string>
     */
    public function getChangedFields(array $row): array
    {
        $target = [];

        foreach (self::FIELDS as $field) {
            $target[$field] = (string) $row['member_'.$field];
        }

        $target['name'] = $target['lastname'].' '.$target['firstname'];

        // A backend user needs an email address
        if ('' === $target['email']) {
            $target['email'] = \sprintf('invalid_%s_%s@noemail.ch', $row['username'], $row['sacMemberId']);
        }

        return array_filter(
            $target,
            static fn (string $value, string $field): bool => (string) $row[$field] !== $value,
            ARRAY_FILTER_USE_BOTH,
        );
    }

    /**
     * @param array<string, mixed> $row
     */
    private function updateUser(array $row): void
    {
        $set = $this->getChangedFields($row);

        if ([] === $set) {
            return;
        }

        $this->connection->update('tl_user', $set, ['id' => (int) $row['id']]);

        ++$this->syncLog['updates'];

        $message = \sprintf(
            'Synced tl_user with tl_member. Updated tl_user (%s %s [SAC Member-ID: %s]).',
            $row['member_firstname'],
            $row['member_lastname'],
            $row['sacMemberId'],
        );

        $this->syncLog['log'][] = $message;
        $this->log($message);
    }

    /**
     * The member no longer exists in tl_member (no longer a club member).
     *
     * @param array<string, mixed> $row
     */
    private function disableUser(array $row): void
    {
        $this->connection->update('tl_user', ['sacMemberId' => 0, 'tstamp' => time()], ['id' => (int) $row['id']]);

        ++$this->syncLog['disabled'];

        $message = \sprintf(
            'Updated "%s". Set tl_user.sacMemberId to "0" after syncing tl_member with tl_user. "%s" no longer seems to be a club member.',
            $row['name'],
            $row['name'],
        );

        $this->syncLog['log'][] = $message;
        $this->log($message);
    }

    /**
     * Streams the users with a SAC member ID together with the data of their member.
     * member_id is NULL if there is no member with this SAC member ID.
     *
     * @return iterable<array<string, mixed>>
     */
    private function iterateUsers(): iterable
    {
        $columns = ['u.id', 'u.username', 'u.name', 'u.sacMemberId', 'm.id AS member_id'];

        foreach (self::FIELDS as $field) {
            $columns[] = 'u.'.$field;
            $columns[] = \sprintf('m.%s AS member_%s', $field, $field);
        }

        $sql = \sprintf(
            'SELECT %s FROM tl_user u LEFT JOIN tl_member m ON m.sacMemberId = u.sacMemberId WHERE u.sacMemberId > 0 ORDER BY u.id, m.id',
            implode(', ', $columns),
        );

        return $this->connection->iterateAssociative($sql);
    }

    private function log(string $message): void
    {
        $this->contaoGeneralLogger?->info(
            $message,
            ['contao' => new ContaoContext(__METHOD__, Log::MEMBER_WITH_USER_SYNC_SUCCESS)],
        );
    }
}
