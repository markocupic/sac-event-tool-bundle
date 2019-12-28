<?php

declare(strict_types=1);

/**
 * SAC Event Tool Web Plugin for Contao
 * Copyright (c) 2008-2020 Marko Cupic
 * @package sac-event-tool-bundle
 * @author Marko Cupic m.cupic@gmx.ch, 2017-2020
 * @link https://github.com/markocupic/sac-event-tool-bundle
 */

namespace Markocupic\SacEventToolBundle\Cron\Contao;

use Contao\Config;
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\System;
use Markocupic\SacEventToolBundle\Services\FrontendUser\ClearFrontendUserData;
use Markocupic\SacEventToolBundle\Services\Pdf\PrintWorkshopsAsPdf;

/**
 * Class DailyCron
 * @package Markocupic\SacEventToolBundle\Cron\Contao
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
     * @param ContaoFramework $framework
     * @param string $projectDir
     */
    public function __construct(ContaoFramework $framework, string $projectDir)
    {
        $this->framework = $framework;
        $this->projectDir = $projectDir;

        // Initialize contao framework
        $this->framework->initialize();
    }

    /**
     * Generate workshop pdf booklet
     */
    public function generateWorkshopPdfBooklet()
    {
        /** @var Config $configAdapter */
        $configAdapter = $this->framework->getAdapter(Config::class);
        $year = (int)$configAdapter->get('SAC_EVT_WORKSHOP_FLYER_YEAR');
        $calendarId = (int)$configAdapter->get('SAC_EVT_WORKSHOP_FLYER_CALENDAR_ID');

        /** @var PrintWorkshopsAsPdf $pdf */
        $pdf = System::getContainer()->get('Markocupic\SacEventToolBundle\Services\Pdf\PrintWorkshopsAsPdf');
        $pdf->setYear($year)
            ->setCalendarId($calendarId)
            ->setDownload(false)
            ->printWorkshopsAsPdf();
    }

    /**
     * Anonymize orphaned calendar events member datarecords
     */
    public function anonymizeOrphanedCalendarEventsMemberDataRecords()
    {
        /** @var  ClearFrontendUserData $cron */
        $cron = System::getContainer()->get('Markocupic\SacEventToolBundle\Services\FrontendUser\ClearFrontendUserData');
        $cron->anonymizeOrphanedCalendarEventsMemberDataRecords();
    }

}
