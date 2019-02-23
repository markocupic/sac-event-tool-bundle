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
     * !!! Not in use, not in use.....
     * @throws \Exception
     */
    protected function avatarUpload()
    {
        $objDb = Database::getInstance()->prepare('SELECT * FROM tl_user')->execute('');
        while ($objDb->next())
        {
            if ($objDb->avatarSRC == '')
            {
                /**
                 * $targetSRC = sprintf('files/sac_pilatus/be_user_home_directories/%s/avatar/%s.jpg', $objDb->id, $objDb->username);
                 * if(is_file(TL_ROOT . '/' . $targetSRC))
                 * {
                 * $objFile = FilesModel::findByPath($targetSRC);
                 * if($objFile !== null)
                 * {
                 * $set = array(
                 * 'avatarSRC'    => $objFile->uuid,
                 * 'avatarUpload' => '1',
                 * 'tstamp'       => time()
                 * );
                 * $objStmt = Database::getInstance()->prepare('UPDATE tl_user %s WHERE id=?')->set($set)->execute($objDb->id);
                 * }
                 * }
                 **/
                //avatarUpload
                $src = sprintf('files/avatare/%s.jpg', $objDb->username);
                //echo $src . '<br>';
                if (is_file(TL_ROOT . '/' . $src))
                {
                    $objFile = new File($src);
                    if ($objFile->isGdImage)
                    {
                        $targetSRC = sprintf('files/sac_pilatus/be_user_home_directories/%s/avatar/%s.jpg', $objDb->id, $objDb->username);
                        if (!is_file(TL_ROOT . '/' . $targetSRC))
                        {

                            if (Files::getInstance()->rename($src, $targetSRC))
                            {
                                Dbafs::addResource($targetSRC);
                                $objNew = FilesModel::findByPath($targetSRC);
                                if ($objNew !== null)
                                {
                                    $set = array(
                                        'avatarSRC'    => $objNew->uuid,
                                        'avatarUpload' => '1',
                                        'tstamp'       => time()
                                    );
                                    $objStmt = Database::getInstance()->prepare('UPDATE tl_user %s WHERE id=?')->set($set)->execute($objDb->id);
                                    if ($objStmt->insertId > 0)
                                    {
                                        echo sprintf('Avatar von %s hochgeladen.', $objDb->username) . '<br>';
                                    }
                                    else
                                    {
                                        //Files::getInstance()->delete($targetSRC);
                                        //echo sprintf("Error 1 bei %s", $objDb->username) . "<br>";
                                    }
                                }
                                else
                                {
                                    //Files::getInstance()->delete($targetSRC);
                                    echo sprintf("Error 2 bei %s", $objDb->username) . "<br>";
                                }
                            }
                            else
                            {
                                echo sprintf("Error 3 bei %s", $objDb->username) . "<br>";
                            }
                        }
                    }
                }

            }
        }
    }


    /**
     *
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






        // Für Downloads z.B. Downloadlink auf www.sac-pilatus.ch/kurse
        if (Input::get('action') == 'downloadKursbroschuere' && Input::get('year') != '')
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
            //die($fileSRC);
            //Controller::sendFileToBrowser($fileSRC, true);
            //Controller::sendFileToBrowser($fileSRC);
            $rootDir = System::getContainer()->getParameter('kernel.project_dir');

            $filepath = $rootDir;
            $filename = $fileSRC;
            header('Content-disposition: attachment; filename='.$filename);
            header('Content-type: application/octet-stream' );
            header('Content-Length: '. filesize($filepath.'/' . $filename));
            readfile($filepath.'/' . $filename);
            exit;
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


        
        

        /** @todo Delete orphaned entries
         * $oDb = Database::getInstance()->execute('SELECT * FROM tl_calendar_events_member');
         * while($oDb->next())
         * {
         * $oEv = CalendarEventsModel::findByPk($oDb->eventId);
         * if($oEv === null)
         * {
         * echo $oDb->lastname . ' ' . $oDb->firstname . '<br>';
         * //Database::getInstance()->prepare('DELETE FROM tl_calendar_events_member WHERE id=?')->execute($oDb->id);
         * }
         * }
         **/

        // Prepare Plugin environment, create folders, etc.
        $objPluginEnv = System::getContainer()->get('markocupic.sac_event_tool_bundle.prepare_plugin_environment');

        $objPluginEnv->preparePluginEnvironment();

        // Convert events to ical
        if (Input::get('action') === 'exportEventsToIcal' && Input::get('id'))
        {
            ExportEvents2Ical::sendToBrowser(Input::get('id'));
        }

        $objNotification = \NotificationCenter\Model\Notification::findOneByType('default_email');
        if ($objNotification === null)
        {
            $set = array(
                'type'   => 'default_email',
                'title'  => 'Standard E-Mail (nur mit Platzhaltern)',
                'tstamp' => time()
            );
            $oInsertStmt = Database::getInstance()->prepare('INSERT into tl_nc_notification %s')->set($set)->execute();
            $set = array(
                'pid'            => $oInsertStmt->insertId,
                'tstamp'         => time(),
                'title'          => 'Standard Nachricht',
                'gateway'        => 1,
                'gateway_type'   => 'email',
                'email_priority' => 3,
                'email_template' => 'mail_default',
                'published'      => 1
            );
            $oInsertStmt2 = Database::getInstance()->prepare('INSERT into tl_nc_message %s')->set($set)->execute();

            $set = array(
                'pid'                  => $oInsertStmt2->insertId,
                'tstamp'               => time(),
                'gateway_type'         => 'email',
                'language'             => 'de',
                'fallback'             => '1',
                'recipients'           => '##send_to##',
                'attachment_tokens'    => '#attachment_tokens##',
                'email_sender_name'    => '##email_sender_name##',
                'email_sender_address' => '##email_sender_email##',
                'email_recipient_cc'   => '##recipient_cc##',
                'email_recipient_bcc'  => '##recipient_bcc##',
                'email_replyTo'        => '##reply_to##',
                'email_subject'        => '##email_subject##',
                'email_mode'           => 'extOnly',
                'email_text'           => '##email_text##'
            );
            $oInsertStmt3 = Database::getInstance()->prepare('INSERT into tl_nc_language %s')->set($set)->execute();


        }
    }

}