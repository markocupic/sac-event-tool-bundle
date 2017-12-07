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
use Contao\Environment;
use Markocupic\SacEventToolBundle\Services\Pdf\PrintWorkshopsAsPdf;
use Markocupic\SacEventToolBundle\FrontendAjax;
use Markocupic\SacEventToolBundle\ExportEvents2Docx;
use Markocupic\SacEventToolBundle\Services\SacMemberDatabase\SyncSacMemberDatabase;
use Markocupic\SacEventToolBundle\Newsletter\SendNewsletter;

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

        // Service: Download Kursbroschuere
        if (Input::get('printSACWorkshops') == 'true')
        {
            $objPrint = new PrintWorkshopsAsPdf(Input::get('year'), Input::get('id'), Input::get('eventId'));
            $objPrint->printWorkshopsAsPdf();
            exit();
        }

        // Download Events as docx file
        // ?action=exportEvents2Docx&calendarId=6&year=2017
        // ?action=exportEvents2Docx&calendarId=6&year=2017&eventId=89
        if (Input::get('action') === 'exportEvents2Docx' && Input::get('year') && Input::get('calendarId'))
        {
            ExportEvents2Docx::sendToBrowser(Input::get('calendarId'), Input::get('year'), Input::get('eventId'));
        }


        // Trigger ajax requests
        if (Environment::get('isAjaxRequest') && (Input::get('xhrAction') || Input::post('xhrAction')))
        {
            $objXhr = new FrontendAjax();
            $objXhr->generateAjax();
            exit();
        }

        // Sync SAC member database with tl_member
        if (Input::get('cronjob') === 'true' && Input::get('action') === 'syncSacMemberDatabase')
        {
            $objSync = new SyncSacMemberDatabase($GLOBALS['TL_CONFIG']['SAC_EVT_SAC_SECTION_IDS'], $GLOBALS['TL_CONFIG']['SAC_EVT_FTPSERVER_MEMBER_DB_BERN_HOSTNAME'], $GLOBALS['TL_CONFIG']['SAC_EVT_FTPSERVER_MEMBER_DB_BERN_USERNAME'], $GLOBALS['TL_CONFIG']['SAC_EVT_FTPSERVER_MEMBER_DB_BERN_PASSWORD']);
            // Load files fromftp
            $objSync->loadDataFromFtp();
            // Then sync with tl_member
            $objSync->syncContaoDatabase();
        }

        // Send sacpilatus survey newsletter
        if (Input::get('cronjob') === 'true' && Input::get('action') === 'sendSurveyNewsletter')
        {
            SendNewsletter::sendSurveyNewsletter(25);
        }
    }


}


