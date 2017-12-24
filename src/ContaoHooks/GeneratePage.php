<?php

/**
 * SAC Event Tool Web Plugin for Contao
 * Copyright (c) 2008-2017 Marko Cupic
 * @package sac-event-tool-bundle
 * @author Marko Cupic m.cupic@gmx.ch, 2017
 * @link    https://sac-kurse.kletterkader.com
 */

namespace Markocupic\SacEventToolBundle\ContaoHooks;

use Contao\CoreBundle\Framework\ContaoFrameworkInterface;
use Contao\Automator;
use Contao\Input;
use Contao\Controller;
use Contao\System;
use Markocupic\SacEventToolBundle\Services\Docx\ExportEvents2Docx;

/**
 * Class GeneratePage
 * @package Markocupic\SacEventToolBundle\ContaoHooks
 */
class GeneratePage
{
    /**
     * @var ContaoFrameworkInterface
     */
    private $framework;


    /**
     * Constructor.
     *
     * @param ContaoFrameworkInterface $framework
     */
    public function __construct(ContaoFrameworkInterface $framework)
    {
        $this->framework = $framework;
    }


    /**
     *
     */
    public function generatePage()
    {
        // Purge the script cache if $GLOBALS['TL_CONFIG']['purgeScriptCache'] is set to true in config.php
        if ($GLOBALS['TL_CONFIG']['purgeScriptCache'] === true)
        {
            $objAutomator = $this->framework->createInstance(Automator::class);
            $objAutomator->purgeScriptCache();
        }

        // Für Downloads z.B. Downloadlink auf www.sac-pilatus.ch/kurse
        if (Input::get('action') === 'downloadKursbroschuere' && Input::get('year') != '')
        {
            System::log('The course booklet has been downloaded.', __FILE__ . ' Line: ' . __LINE__, SAC_EVT_LOG_COURSE_BOOKLET_DOWNLOAD);
            $fileSRC = sprintf(SAC_EVT_WORKSHOP_FLYER_SRC, Input::get('year'));
            Controller::sendFileToBrowser($fileSRC);
        }


        // Download Events as docx file
        // ?action=exportEvents2Docx&calendarId=6&year=2017
        // ?action=exportEvents2Docx&calendarId=6&year=2017&eventId=89
        if (Input::get('action') === 'exportEvents2Docx' && Input::get('year') && Input::get('calendarId'))
        {
            ExportEvents2Docx::sendToBrowser(Input::get('calendarId'), Input::get('year'), Input::get('eventId'));
        }

    }

}


