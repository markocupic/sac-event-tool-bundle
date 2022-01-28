<?php

declare(strict_types=1);

/*
 * This file is part of SAC Event Tool Bundle.
 *
 * (c) Marko Cupic 2022 <m.cupic@gmx.ch>
 * @license GPL-3.0-or-later
 * For the full copyright and license information,
 * please view the LICENSE file that was distributed with this source code.
 * @link https://github.com/markocupic/sac-event-tool-bundle
 */

namespace Markocupic\SacEventToolBundle\Cron\Contao;

use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\System;
use Markocupic\SacEventToolBundle\SacMemberDatabase\SyncSacMemberDatabase;
use Markocupic\SacEventToolBundle\User\BackendUser\SyncMemberWithUser;

/**
 * Class HourlyCron.
 */
class HourlyCron
{
    /**
     * @var ContaoFramework
     */
    private $framework;

    /**
     * @var string
     */
    private $projectDir;

    /**
     * HourlyCron constructor.
     */
    public function __construct(ContaoFramework $framework, string $projectDir)
    {
        $this->framework = $framework;
        $this->projectDir = $projectDir;

        // Initialize contao framework
        $this->framework->initialize();
    }

    /**
     * Sync SAC member database.
     */
    public function syncSacMemberDatabase(): void
    {
        /** @var SyncSacMemberDatabase $cron */
        $cron = System::getContainer()->get('Markocupic\SacEventToolBundle\SacMemberDatabase\SyncSacMemberDatabase');
        $cron->run();
    }

    /**
     * Sync tl_member with tl_user.
     */
    public function syncMemberWithUser(): void
    {
        /** @var SyncMemberWithUser $cron */
        $cron = System::getContainer()->get('Markocupic\SacEventToolBundle\User\BackendUser\SyncMemberWithUser');
        $cron->syncMemberWithUser();
    }
}
