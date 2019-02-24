<?php
/**
 * SAC Event Tool Web Plugin for Contao
 * Copyright (c) 2008-2019 Marko Cupic
 * @package sac-event-tool-bundle
 * @author Marko Cupic m.cupic@gmx.ch, 2017-2019
 * @link https://github.com/markocupic/sac-event-tool-bundle
 */

namespace Markocupic\SacEventToolBundle\Controller;

use Contao\Input;
use Contao\System;
use Contao\Config;
use Markocupic\SacEventToolBundle\Services\Newsletter\SendNewsletter;
use Markocupic\SacEventToolBundle\Services\Newsletter\SendPasswordToMembers;
use Markocupic\SacEventToolBundle\Services\Pdf\PrintWorkshopsAsPdf;
use Markocupic\SacEventToolBundle\Services\SacMemberDatabase\SyncSacMemberDatabase;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;


/**
 * Class CronjobController
 * @package Markocupic\SacEventToolBundle\Controller
 */
class CronjobController extends AbstractController
{


    /**
     * !!! No more used
     * Send password to backend users
     * /_cronjob/send_password_to_backend_users
     * @Route("/_cronjob/send_password_to_backend_users", name="sac_event_tool_cronjob_send_password_to_backend_users", defaults={"_scope" = "frontend", "_token_check" = false})
     */
    public function sendPasswordToBackendUsers()
    {
        // Initialize contao framework
        System::getContainer()->get('contao.framework')->initialize();

        //SendPasswordToMembers::sendPasswordToMembers(25);

        return new Response();
    }


    /**
     * !!! No more used
     * Send survey newsletter
     * /_cronjob/send_survey_newsletter
     * @Route("/_cronjob/send_survey_newsletter", name="sac_event_tool_cronjob_send_survey_newsletter", defaults={"_scope" = "frontend", "_token_check" = false})
     */
    public function sendSurveyNewsletter()
    {
        // Initialize contao framework
        System::getContainer()->get('contao.framework')->initialize();

        //SendNewsletter::sendSurveyNewsletter(25);

        return new Response();
    }


    /**
     * Sync SAC member database
     * @see $GLOBALS['TL_CRON']['hourly']['syncSacMemberDatabase']
     * /_cronjob/sync_sac_member_database
     * @Route("/_cronjob/sync_sac_member_database", name="sac_event_tool_cronjob_sync_sac_member_database", defaults={"_scope" = "frontend", "_token_check" = false})
     */
    public function syncSacMemberDatabase()
    {
        // Initialize contao framework
        System::getContainer()->get('contao.framework')->initialize();

        // Sync SAC member database with tl_member
        System::getContainer()->get('markocupic.sac_event_tool_bundle.sync_sac_member_database')->loadDataFromFtp()->syncContaoDatabase();

        return new Response();
    }


    /**
     * Generate workshop pdf booklet
     * @see $GLOBALS['TL_CRON']['daily']['generateWorkshopPdfBooklet']
     * /_cronjob/generate_workshop_pdf_booklet
     * @Route("/_cronjob/generate_workshop_pdf_booklet", name="sac_event_tool_cronjob_generate_workshop_pdf_booklet", defaults={"_scope" = "frontend", "_token_check" = false})
     */
    public function generateWorkshopPdfBooklet()
    {
        // Initialize contao framework
        System::getContainer()->get('contao.framework')->initialize();

        $year = Config::get('SAC_EVT_WORKSHOP_FLYER_YEAR');
        $calendarId = Config::get('SAC_WORKSHOP_FLYER_CALENDAR_ID');
        $objPrint = new PrintWorkshopsAsPdf($year, $calendarId, null, false);
        $objPrint->printWorkshopsAsPdf();

        return new Response();
    }
}