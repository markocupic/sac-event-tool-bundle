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
use Markocupic\SacEventToolBundle\Database\SyncMemberDatabase;
use Markocupic\SacEventToolBundle\User\BackendUser\SyncMemberWithUser;

#[AsCronJob('1 5 * * *')]
readonly class MemberDatabaseSyncCron
{
    public function __construct(
        private ContaoFramework $framework,
        private SyncMemberDatabase $syncMemberDatabase,
        private SyncMemberWithUser $syncMemberWithUser,
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
        $this->syncMemberDatabase->run();
    }

    private function syncMemberWithUser(): void
    {
        $this->syncMemberWithUser->syncMemberWithUser();
    }
}
