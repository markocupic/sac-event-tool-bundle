<?php

declare(strict_types=1);

/**
 * SAC Event Tool Web Plugin for Contao
 * Copyright (c) 2008-2020 Marko Cupic
 * @package sac-event-tool-bundle
 * @author Marko Cupic m.cupic@gmx.ch, 2017-2020
 * @link https://github.com/markocupic/sac-event-tool-bundle
 */

namespace Markocupic\SacEventToolBundle\ContaoBackendMaintenance;

use Contao\CalendarEventsStoryModel;
use Contao\Config;
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\CoreBundle\Monolog\ContaoContext;
use Contao\Folder;
use Contao\Message;
use Contao\System;
use Psr\Log\LogLevel;
use Contao\PurgeData;

/**
 * Class MaintainModuleEventStory
 * @package Markocupic\SacEventToolBundle\ContaoBackendMaintenance
 */
class MaintainModuleEventStory
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
     * MaintainModuleEventStory constructor.
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
     * Delete image upload folders that aren't assigned to an event story
     * @throws \Exception
     */
    public function run(): void
    {
        // Set adapters
        /** @var CalendarEventsStoryModel $calendarEventsStoryModelAdapter */
        $calendarEventsStoryModelAdapter = $this->framework->getAdapter(CalendarEventsStoryModel::class);

        /** @var Config $configAdapter */
        $configAdapter = $this->framework->getAdapter(Config::class);

        // Get the image upload path
        $eventStoriesUploadPath = $configAdapter->get('SAC_EVT_EVENT_STORIES_UPLOAD_PATH');

        $arrScan = scan($this->projectDir . '/' . $eventStoriesUploadPath);
        foreach ($arrScan as $folder)
        {
            if (is_dir($this->projectDir . '/' . $eventStoriesUploadPath . '/' . $folder) && $folder !== 'tmp')
            {
                $objFolder = new Folder($eventStoriesUploadPath . '/' . $folder);
                if (null === $calendarEventsStoryModelAdapter->findByPk($folder))
                {
                    // Log
                    $logger = System::getContainer()->get('monolog.logger.contao');
                    $strText = sprintf('Successfully deleted event story media folder "%s".', $objFolder->path);
                    $logger->log(LogLevel::INFO, $strText, array('contao' => new ContaoContext(__METHOD__, ContaoContext::GENERAL)));

                    // Display the confirmation message in the backend maintenance module
                    Message::addConfirmation($strText, PurgeData::class);
                    $objFolder->delete();
                }
            }
        }

        // Purge the tmp folder
        if (is_dir($this->projectDir . '/' . $eventStoriesUploadPath . '/tmp'))
        {
            $objFolder = new Folder($eventStoriesUploadPath . '/tmp');
            $objFolder->purge();
        }
    }
}
