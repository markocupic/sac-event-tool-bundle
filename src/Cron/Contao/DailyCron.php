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
use Markocupic\SacEventToolBundle\User\FrontendUser\ClearFrontendUserData;

class DailyCron
{
    private ContaoFramework $framework;

    public function __construct(ContaoFramework $framework)
    {
        $this->framework = $framework;
    }

    /**
     * Anonymize orphaned event registration datarecords (tl_calendar_events_member).
     */
    public function dailyCron(): void
    {
        // Initialize contao framework
        $this->framework->initialize();

        $cron = System::getContainer()->get(ClearFrontendUserData::class);
        $cron->anonymizeOrphanedCalendarEventsMemberDataRecords();
    }
}
