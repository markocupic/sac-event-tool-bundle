<?php

declare(strict_types=1);

/*
 * This file is part of SAC Event Tool Bundle.
 *
 * (c) Marko Cupic 2024 <m.cupic@gmx.ch>
 * @license GPL-3.0-or-later
 * For the full copyright and license information,
 * please view the LICENSE file that was distributed with this source code.
 * @link https://github.com/markocupic/sac-event-tool-bundle
 */

namespace Markocupic\SacEventToolBundle\Cron;

use Contao\CoreBundle\DependencyInjection\Attribute\AsCronJob;
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\CoreBundle\Monolog\ContaoContext;
use Markocupic\SacEventToolBundle\Config\Log;
use Markocupic\SacEventToolBundle\Database\SyncMemberDatabase;
use Markocupic\SacEventToolBundle\User\BackendUser\SyncMemberWithUser;
use Psr\Log\LoggerInterface;

#[AsCronJob('1 4 * * *')]
#[AsCronJob('1 5 * * *')]
readonly class MemberDatabaseSyncCron
{
    public function __construct(
        private ContaoFramework $framework,
        private SyncMemberDatabase $syncMemberDatabase,
        private SyncMemberWithUser $syncMemberWithUser,
        private LoggerInterface|null $logger = null,
    ) {
    }

    /**
     * Sync SAC member database.
     * Sync tl_member with tl_user.
     *
     * @throws \Exception
     */
    public function __invoke(): void
    {
        // Initialize contao framework
        $this->framework->initialize();

        // Sync from SAC member database (Bern) -> tl_member
        $this->syncMemberDatabase();

        // Merge from tl_member -> tl_user
        $this->syncMemberWithUser();
    }

    private function syncMemberDatabase(): void
    {
        $this->syncMemberDatabase->run();

        // Log
        $arrLog = $this->syncMemberDatabase->getSyncLog();

        $msg = sprintf(
            'Successfully completed the merging process from the SAC member database to tl_member. Processed %d data records. Total inserts: %d. Total updates: %d. Disabled %d user(s). Duration: %d s.',
            $arrLog['processed'],
            $arrLog['inserts'],
            $arrLog['updates'],
            $arrLog['disabled'],
            $arrLog['duration'],
        );

        $this->logger?->info(
            $msg,
            ['contao' => new ContaoContext(__METHOD__, Log::MEMBER_DATABASE_SYNC_SUCCESS)]
        );
    }

    private function syncMemberWithUser(): void
    {
        $this->syncMemberWithUser->syncMemberWithUser();

        // Log
        $arrLog = $this->syncMemberWithUser->getSyncLog();

        $msg = sprintf(
            'Successfully completed the merging process from tl_member to tl_user. Processed %d data records. Total updates: %d. Disabled %d user(s). Duration: %d s.',
            $arrLog['processed'],
            $arrLog['updates'],
            $arrLog['disabled'],
            $arrLog['duration'],
        );

        $this->logger?->info(
            $msg,
            ['contao' => new ContaoContext(__METHOD__, Log::MEMBER_WITH_USER_SYNC_SUCCESS)]
        );
    }
}
