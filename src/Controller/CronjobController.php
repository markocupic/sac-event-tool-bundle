<?php
/**
 * SAC Event Tool Web Plugin for Contao
 * Copyright (c) 2008-2017 Marko Cupic
 * @package sac-event-tool-bundle
 * @author Marko Cupic m.cupic@gmx.ch, 2017-2018
 * @link https://sac-kurse.kletterkader.com
 */

namespace Markocupic\SacEventToolBundle\Controller;

use Contao\Input;
use Contao\System;
use Contao\Config;
use Markocupic\SacEventToolBundle\Services\Newsletter\SendNewsletter;
use Markocupic\SacEventToolBundle\Services\Newsletter\SendPasswordToMembers;
use Markocupic\SacEventToolBundle\Services\Pdf\PrintWorkshopsAsPdf;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;


/**
 * Class CronjobController
 * @package Markocupic\SacEventToolBundle\Controller
 */
class CronjobController extends Controller
{
    /**
     * Handles cronjob requests.
     * @Route("/cronjob", name="sac_event_tool_cronjob", defaults={"_scope" = "frontend", "_token_check" = false})
     */
    public function cronjobAction()
    {
        $container = System::getContainer();
        $container->get('contao.framework');
        $framework = $this->container->get('contao.framework');
        $framework->initialize();
        $input = $framework->getAdapter(Input::class);

        // Send sacpilatus survey newsletter
        // old and no more used!!!!!!!!!!!
        if ($input->get('action') === 'sendSurveyNewsletter')
        {
            SendNewsletter::sendSurveyNewsletter(25);
            exit();
        }

        // Send sacpilatus survey newsletter
        // old and no more used!!!!!!!!!!!
        if ($input->get('action') === 'sendPasswordToMembers')
        {
            SendPasswordToMembers::sendPasswordToMembers(25);
            exit();
        }
        exit();

    }

    /**
     * Cronjob see config.php $GLOBALS['TL_CRON']['daily']['syncSacMemberDatabase']
     */
    public function syncSacMemberDatabase()
    {
        $container = System::getContainer();
        $container->get('contao.framework');
        $framework = $container->get('contao.framework');
        $framework->initialize();

        // Sync SAC member database with tl_member
        $container->get('markocupic.sac_event_tool_bundle.sync_sac_member_database')->loadDataFromFtp()->syncContaoDatabase();
    }

    /**
     * Cronjob see config.php $GLOBALS['TL_CRON']['hourly']['printSACWorkshops']
     */
    public function printSacWorkshops()
    {
        $container = System::getContainer();
        $container->get('contao.framework');
        $framework = $container->get('contao.framework');
        $framework->initialize();
        $year = Config::get('SAC_WORKSHOP_FLYER_YEAR');
        $calendarId = Config::get('SAC_WORKSHOP_FLYER_CALENDAR_ID');
        $objPrint = new PrintWorkshopsAsPdf($year, $calendarId, null, false);
        $objPrint->printWorkshopsAsPdf();
    }
}