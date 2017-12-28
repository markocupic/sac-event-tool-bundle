<?php
/**
 * SAC Event Tool Web Plugin for Contao
 * Copyright (c) 2008-2017 Marko Cupic
 * @package sac-event-tool-bundle
 * @author Marko Cupic m.cupic@gmx.ch, 2017
 * @link    https://sac-kurse.kletterkader.com
 */

namespace Markocupic\SacEventToolBundle\Controller;

use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\JsonResponse;
use Contao\Input;
use Markocupic\SacEventToolBundle\Services\SacMemberDatabase\SyncSacMemberDatabase;
use Markocupic\SacEventToolBundle\Services\Pdf\PrintWorkshopsAsPdf;
use Markocupic\SacEventToolBundle\Services\Newsletter\SendNewsletter;


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
        $this->container->get('contao.framework')->initialize();


        // Sync SAC member database with tl_member
        if (Input::get('action') === 'syncSacMemberDatabase')
        {
            $container = \System::getContainer();
            $objSync = $container->get('markocupic.sac_event_tool_bundle.sync_sac_member_database');

            // Load files fromftp
            $objSync->loadDataFromFtp();
            // Then sync with tl_member
            $objSync->syncContaoDatabase();
            echo "Successfully synced SAC member database.";
            exit();
        }

        // Generate the current Course booklet and save it to the webserver
        if (Input::get('action') === 'printSACWorkshops')
        {
            $objPrint = new PrintWorkshopsAsPdf(Input::get('year'), Input::get('id'), Input::get('eventId'), false);
            $objPrint->printWorkshopsAsPdf();
            exit();
        }

        // Send sacpilatus survey newsletter
        if (Input::get('action') === 'sendSurveyNewsletter')
        {
            SendNewsletter::sendSurveyNewsletter(25);
            exit();
        }

        exit();
    }
}