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

namespace Markocupic\SacEventToolBundle\Feature\MemberToUserSync\Cron;

use Contao\CoreBundle\DependencyInjection\Attribute\AsCronJob;
use Contao\CoreBundle\Framework\ContaoFramework;
use Markocupic\SacEventToolBundle\Feature\MemberToUserSync\MemberToUserSync;

/**
 * Syncs tl_member -> tl_user (backend users with a SAC member ID).
 * Runs one hour after Feature\MemberDatabaseSync\Cron\MemberDatabaseSyncCron (05:01), so the member data is up to date.
 *
 * See docs/features/member-to-user-sync.md
 */
#[AsCronJob('1 6 * * *')]
readonly class MemberToUserSyncCron
{
    public function __construct(
        private ContaoFramework $framework,
        private MemberToUserSync $memberToUserSync,
    ) {
    }

    /**
     * Sync tl_member with tl_user.
     *
     * @throws \Exception
     */
    public function __invoke(): void
    {
        // Initialize contao framework
        $this->framework->initialize();

        // Merge from tl_member -> tl_user
        $this->memberToUserSync->run();
    }
}
