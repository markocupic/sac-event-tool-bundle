<?php

/**
 * SAC Event Tool Web Plugin for Contao
 * Copyright (c) 2008-2017 Marko Cupic
 * @package sac-event-tool-bundle
 * @author Marko Cupic m.cupic@gmx.ch, 2017-2018
 * @link https://sac-kurse.kletterkader.com
 */

namespace Markocupic\SacEventToolBundle\Controller;

use Contao\Config;
use Contao\Controller;
use Contao\CoreBundle\Monolog\ContaoContext;
use Contao\Date;
use Contao\Environment;
use Contao\Input;
use Contao\System;
use Markocupic\SacEventToolBundle\Services\Docx\ExportEvents2Docx;
use Markocupic\SacEventToolBundle\Services\Pdf\PrintWorkshopsAsPdf;
use Psr\Log\LogLevel;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;

class DownloadController extends AbstractController
{


    /**
     * Handles ajax requests.
     * @return JsonResponse
     * @Route("/download", name="sac_event_tool_download_frontend", defaults={"_scope" = "frontend", "_token_check" = false})
     */
    public function downloadAction()
    {
        $this->container->get('contao.framework')->initialize();

        // Für Downloads workshops as pdf booklet
        if (Input::get('action') == 'downloadWorkshopBooklet' && Input::get('year') != '')
        {

            $year = Input::get('year');

            if (Input::get('year') === 'current')
            {
                $year = Date::parse('Y', time());
            }

            // Log download
            $container = System::getContainer();
            $logger = $container->get('monolog.logger.contao');
            $logger->log(LogLevel::INFO, 'The course booklet has been downloaded.', array('contao' => new ContaoContext(__FILE__ . ' Line: ' . __LINE__, Config::get('SAC_EVT_LOG_COURSE_BOOKLET_DOWNLOAD'))));

            $filenamePattern = str_replace('%%s', '%s', Config::get('SAC_EVT_WORKSHOP_FLYER_SRC'));
            $fileSRC = sprintf($filenamePattern, $year);

            Controller::sendFileToBrowser($fileSRC, false);
        }


        // Download Events as docx file
        // ?action=exportEvents2Docx&calendarId=6&year=2017
        // ?action=exportEvents2Docx&calendarId=6&year=2017&eventId=89
        if (Input::get('action') === 'exportEvents2Docx' && Input::get('year') && Input::get('calendarId'))
        {
            ExportEvents2Docx::sendToBrowser(Input::get('calendarId'), Input::get('year'), Input::get('eventId'));
        }


        // Generate a selected course description
        if (Input::get('printSACWorkshops') === 'true' && Input::get('eventId'))
        {
            $objPrint = new PrintWorkshopsAsPdf(0, 0, Input::get('eventId'), true);
            $objPrint->printWorkshopsAsPdf();
            exit();
        }


        exit();
    }
}