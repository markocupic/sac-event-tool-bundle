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

namespace Markocupic\SacEventToolBundle\Cron;

use Contao\CoreBundle\DependencyInjection\Attribute\AsCronJob;
use Contao\CoreBundle\Framework\ContaoFramework;
use Markocupic\SacEventToolBundle\SacMemberDatabase\SyncSacMemberDatabase;
use Markocupic\SacEventToolBundle\User\BackendUser\SyncMemberWithUser;

#[AsCronJob('hourly')]
class SacMemberDatabaseSyncCron
{
    private ContaoFramework $framework;
    private SyncSacMemberDatabase $syncSacMemberDatabase;
    private SyncMemberWithUser $syncMemberWithUser;

    public function __construct(ContaoFramework $framework, SyncSacMemberDatabase $syncSacMemberDatabase, SyncMemberWithUser $syncMemberWithUser)
    {
        $this->framework = $framework;
        $this->syncSacMemberDatabase = $syncSacMemberDatabase;
        $this->syncMemberWithUser = $syncMemberWithUser;
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

        $this->syncSacMemberDatabase->run();

        $this->syncMemberWithUser->syncMemberWithUser();
    }
}
