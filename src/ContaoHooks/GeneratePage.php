<?php

/**
 * SAC Event Tool Web Plugin for Contao
 * Copyright (c) 2008-2017 Marko Cupic
 * @package sac-event-tool-bundle
 * @author Marko Cupic m.cupic@gmx.ch, 2017-2018
 * @link https://sac-kurse.kletterkader.com
 */

namespace Markocupic\SacEventToolBundle\ContaoHooks;

use Contao\Automator;
use Contao\Config;
use Contao\Controller;
use Contao\CoreBundle\Framework\ContaoFrameworkInterface;
use Contao\CoreBundle\Monolog\ContaoContext;
use Contao\Database;
use Contao\Date;
use Contao\Input;
use Contao\MemberModel;
use Contao\System;
use Markocupic\SacEventToolBundle\Services\Docx\ExportEvents2Docx;
use Markocupic\SacEventToolBundle\Services\Pdf\PrintWorkshopsAsPdf;
use Psr\Log\LogLevel;

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
        $disabled = true;
        if (!$disabled && Input::get('action') === 'importNewsletterRecipients')
        {
            Database::getInstance()->prepare('DELETE FROM tl_newsletter_recipients')->execute();

            $newsletterChannelId = 6;
            $time = time();
            $addedOn = time();
            $objDb = Database::getInstance()->execute('SELECT * FROM tl_newsletter_jugend');
            $sets = array();
            $arrEmail = [];
            while ($objDb->next())
            {
                $token = md5(uniqid(mt_rand(), true));
                $objMember = MemberModel::findByEmail($objDb->email);
                if ($objMember !== null)
                {
                    $objMember->newsletter = serialize([$newsletterChannelId]);
                    $objMember->save();
                    $sets[strtolower($objMember->email)] = array(
                        'pid' => $newsletterChannelId,
                        'tstamp' => $time,
                        'email' => $objMember->email,
                        'active' => '1',
                        'addedOn' => $addedOn,
                        'token' => $token
                        );
                }
                else
                {
                    if($objDb->email != '')
                    {
                        $sets[strtolower($objDb->email)] = array(
                            'pid' => $newsletterChannelId,
                            'tstamp' => $time,
                            'email' => $objDb->email,
                            'active' => '1',
                            'addedOn' => $addedOn,
                            'token' => $token
                        );

                    }
                }
            }

            // Start transaction
            Database::getInstance()->beginTransaction();
            try
            {
                foreach($sets as $set)
                {
                    Database::getInstance()->prepare('INSERT INTO tl_newsletter_recipients %s')->set($set)->execute();
                }
                Database::getInstance()->commitTransaction();
            } catch (\Exception $e)
            {

                //transaction rollback
                Database::getInstance()->rollbackTransaction();
                throw $e;
            }
        }

        // Für Downloads z.B. Downloadlink auf www.sac-pilatus.ch/kurse
        if (Input::get('action') === 'downloadKursbroschuere' && Input::get('year') != '')
        {
            /**
             * @todo Remove this hack if we go on production (the link on sac-pilatus.ch/kurse ist static and set to year=2017)
             */
            $year = Input::get('year') == '2017' ? '2018' : Input::get('year');

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
            Controller::sendFileToBrowser($fileSRC);
        }

        // Generate a selected course description
        if (Input::get('printSACWorkshops') === 'true' && Input::get('eventId'))
        {
            $objPrint = new PrintWorkshopsAsPdf(0, 0, Input::get('eventId'), true);
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

    }

}


