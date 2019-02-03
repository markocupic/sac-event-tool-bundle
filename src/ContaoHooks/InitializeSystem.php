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
use Contao\CoreBundle\Framework\ContaoFrameworkInterface;
use Contao\Database;
use Contao\Dbafs;
use Contao\Config;
use Contao\File;
use Contao\Files;
use Contao\FilesModel;
use Contao\Input;
use Contao\System;
use NotificationCenter\Model\Language;
use NotificationCenter\Model\Message;
use NotificationCenter\Model\Notification;
use Symfony\Component\Filesystem\Filesystem;


/**
 * Class InitializeSystem
 * @package Markocupic\SacEventToolBundle\ContaoHooks
 */
class InitializeSystem
{
    /**
     * @var ContaoFrameworkInterface
     */
    private $framework;


    /**
     * Constructor
     *
     * @param ContaoFrameworkInterface $framework
     */
    public function __construct(ContaoFrameworkInterface $framework)
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

        /** Delete orphaned entries
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
                'attachment_tokens'    => '#attachment_token##',
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