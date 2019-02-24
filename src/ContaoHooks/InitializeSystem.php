<?php

/**
 * SAC Event Tool Web Plugin for Contao
 * Copyright (c) 2008-2019 Marko Cupic
 * @package sac-event-tool-bundle
 * @author Marko Cupic m.cupic@gmx.ch, 2017-2019
 * @link https://github.com/markocupic/sac-event-tool-bundle
 */

namespace Markocupic\SacEventToolBundle\ContaoHooks;

use Contao\Automator;
use Contao\CalendarEventsModel;
use Contao\Controller;
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\CoreBundle\Monolog\ContaoContext;
use Contao\Database;
use Contao\Date;
use Contao\Dbafs;
use Contao\Config;
use Contao\File;
use Contao\Files;
use Contao\FilesModel;
use Contao\Input;
use Contao\System;
use Markocupic\SacEventToolBundle\Services\Docx\ExportEvents2Docx;
use Markocupic\SacEventToolBundle\Services\Pdf\PrintWorkshopsAsPdf;
use NotificationCenter\Model\Language;
use NotificationCenter\Model\Message;
use NotificationCenter\Model\Notification;
use Psr\Log\LogLevel;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\File\MimeType\FileinfoMimeTypeGuesser;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;


/**
 * Class InitializeSystem
 * @package Markocupic\SacEventToolBundle\ContaoHooks
 */
class InitializeSystem
{
    /**
     * @var ContaoFramework
     */
    private $framework;


    /**
     * Constructor
     *
     * @param ContaoFramework $framework
     */
    public function __construct(ContaoFramework $framework)
    {
        $this->framework = $framework;
    }

    /**
     * Prepare the plugin environment
     */
    public function initializeSystem()
    {

        // Purge script cache in dev mode
        $kernel = System::getContainer()->get('kernel');
        if ($kernel->isDebug())
        {
            $objAutomator = new Automator();
            $objAutomator->purgeScriptCache();
            $rootDir = System::getContainer()->getParameter('kernel.project_dir');
            if (is_file($rootDir . '/files/theme-sac-pilatus/scss/main.scss'))
            {
                touch($rootDir . '/files/theme-sac-pilatus/scss/main.scss');
            }
        }


        // Prepare Plugin environment, create folders, etc.
        $objPluginEnv = System::getContainer()->get('markocupic.sac_event_tool_bundle.prepare_plugin_environment');
        $objPluginEnv->preparePluginEnvironment();

    }

}