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

namespace Markocupic\SacEventToolBundle\Cron;

use Contao\CoreBundle\DependencyInjection\Attribute\AsCronJob;
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\CoreBundle\Monolog\ContaoContext;
use Markocupic\SacEventToolBundle\Config\Log;
use Markocupic\SacEventToolBundle\Database\SyncMember\SyncLogger;
use Markocupic\SacEventToolBundle\Database\SyncMemberDatabase;
use Markocupic\SacEventToolBundle\User\BackendUser\SyncMemberWithUser;
use Psr\Log\LoggerInterface;

#[AsCronJob('1 5 * * *')]
readonly class MemberDatabaseSyncCron
{
    public function __construct(
        private ContaoFramework $framework,
        private SyncMemberDatabase $syncMemberDatabase,
        private SyncMemberWithUser $syncMemberWithUser,
        private LoggerInterface|null $contaoGeneralLogger = null,
        private LoggerInterface|null $contaoErrorLogger = null,
    ) {
    }

    /**
     * Sync SAC member database. Sync tl_member with tl_user.
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
        $syncLogger = new SyncLogger();
        $this->syncMemberDatabase->run($syncLogger);

        $errors = $syncLogger->getErrors();

        if (!empty($errors)) {
            foreach ($errors as $error) {
                $this->contaoErrorLogger->error($error);
            }

            return;
        }

        $message = \sprintf(
            'Successfully synced members from SAC Zentralverband database (Bern) to the Contao database (tl_member). Processed: %d, Inserts: %d, Updates: %d, Disabled: %d, Duration: %s',
            $syncLogger->getCountProcessedRecords(),
            \count($syncLogger->getInsertMessages()),
            \count($syncLogger->getUpdateMessages()),
            \count($syncLogger->getDisabledMessages()),
            $syncLogger->getDuration(),
        );

        $this->contaoGeneralLogger?->info($message, ['contao' => new ContaoContext(__METHOD__, Log::MEMBER_DATABASE_SYNC_SUCCESS)]);

        foreach ($syncLogger->getInsertMessages() as $message) {
            $this->contaoGeneralLogger?->info($message, ['contao' => new ContaoContext(__METHOD__, Log::MEMBER_DATABASE_SYNC_INSERT_NEW_MEMBER)]);
        }

        foreach ($syncLogger->getUpdateMessages() as $message) {
            $this->contaoGeneralLogger?->info($message, ['contao' => new ContaoContext(__METHOD__, Log::MEMBER_DATABASE_SYNC_UPDATE_MEMBER)]);
        }

        foreach ($syncLogger->getDisabledMessages() as $message) {
            $this->contaoGeneralLogger?->info($message, ['contao' => new ContaoContext(__METHOD__, Log::MEMBER_DATABASE_SYNC_DISABLE_MEMBER)]);
        }
    }

    private function syncMemberWithUser(): void
    {
        $this->syncMemberWithUser->syncMemberWithUser();
    }
}
