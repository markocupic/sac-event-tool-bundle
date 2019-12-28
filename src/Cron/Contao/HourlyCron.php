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

use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\System;
use Markocupic\SacEventToolBundle\Services\SacMemberDatabase\SyncSacMemberDatabase;

/**
 * Class HourlyCron
 * @package Markocupic\SacEventToolBundle\Cron\Contao
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
     * Sync SAC member database
     */
    public function syncSacMemberDatabase()
    {
        /** @var  SyncSacMemberDatabase $cron */
        $cron = System::getContainer()->get('Markocupic\SacEventToolBundle\Services\SacMemberDatabase\SyncSacMemberDatabase');
        $cron->run();
    }

}
