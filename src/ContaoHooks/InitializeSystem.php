<?php

/**
 * SAC Event Tool Web Plugin for Contao
 * Copyright (c) 2008-2019 Marko Cupic
 * @package sac-event-tool-bundle
 * @author Marko Cupic m.cupic@gmx.ch, 2017-2019
 * @link https://github.com/markocupic/sac-event-tool-bundle
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
     * Prepare the plugin environment
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



        // Prepare Plugin environment, create folders, etc.
        $objPluginEnv = System::getContainer()->get('markocupic.sac_event_tool_bundle.prepare_plugin_environment');
        $objPluginEnv->preparePluginEnvironment();



        // Create default notification
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