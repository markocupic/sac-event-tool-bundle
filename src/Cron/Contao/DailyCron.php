<?php

declare(strict_types=1);

/*
 * This file is part of SAC Event Tool Bundle.
 *
 * (c) Marko Cupic 2021 <m.cupic@gmx.ch>
 * @license MIT
 * For the full copyright and license information,
 * please view the LICENSE file that was distributed with this source code.
 * @link https://github.com/markocupic/sac-event-tool-bundle
 */

namespace Markocupic\SacEventToolBundle\Cron\Contao;

use Contao\Config;
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\System;
use Markocupic\SacEventToolBundle\Pdf\PrintWorkshopsAsPdf;
use Markocupic\SacEventToolBundle\User\FrontendUser\ClearFrontendUserData;

/**
 * Class DailyCron.
 */
class DailyCron
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
     * DailyCron constructor.
     */
    public function __construct(ContaoFramework $framework, string $projectDir)
    {
        $this->framework = $framework;
        $this->projectDir = $projectDir;

        // Initialize contao framework
        $this->framework->initialize();
    }

    /**
     * Generate workshop pdf booklet.
     */
    public function generateWorkshopPdfBooklet(): void
    {
        /** @var Config $configAdapter */
        $configAdapter = $this->framework->getAdapter(Config::class);
        $year = (int) $configAdapter->get('SAC_EVT_WORKSHOP_FLYER_YEAR');
        $calendarId = (int) $configAdapter->get('SAC_EVT_WORKSHOP_FLYER_CALENDAR_ID');

        /** @var PrintWorkshopsAsPdf $pdf */
        $pdf = System::getContainer()->get('Markocupic\SacEventToolBundle\Pdf\PrintWorkshopsAsPdf');
        $pdf->setYear($year)
            ->setCalendarId($calendarId)
            ->setDownload(false)
            ->printWorkshopsAsPdf()
        ;
    }

    /**
     * Anonymize orphaned calendar events member datarecords.
     */
    public function anonymizeOrphanedCalendarEventsMemberDataRecords(): void
    {
        /** @var ClearFrontendUserData $cron */
        $cron = System::getContainer()->get('Markocupic\SacEventToolBundle\User\FrontendUser\ClearFrontendUserData');
        $cron->anonymizeOrphanedCalendarEventsMemberDataRecords(); 
    }
}
