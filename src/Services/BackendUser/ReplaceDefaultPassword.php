<?php

/**
 * SAC Event Tool Web Plugin for Contao
 * Copyright (c) 2008-2020 Marko Cupic
 * @package sac-event-tool-bundle
 * @author Marko Cupic m.cupic@gmx.ch, 2017-2020
 * @link https://github.com/markocupic/sac-event-tool-bundle
 */

declare(strict_types=1);

namespace Markocupic\SacEventToolBundle\Services\BackendUser;

use Contao\Config;
use Contao\CoreBundle\Monolog\ContaoContext;
use Contao\Database;
use Contao\System;
use Contao\UserModel;
use NotificationCenter\Model\Notification;
use Psr\Log\LogLevel;
use Contao\CoreBundle\Framework\ContaoFramework;

/**
 * Class ReplaceDefaultPassword
 * @package Markocupic\SacEventToolBundle\Services\BackendUser
 */
class ReplaceDefaultPassword
{

    /**
     * @var ContaoFramework
     */
    private $framework;

    /**
     * @var
     */
    protected $defaultPassword;

    /**
     * @var int
     */
    protected $emailSendLimit = 20;

    /**
     * ReplaceDefaultPassword constructor.
     * @param ContaoFramework $framework
     */
    public function __construct(ContaoFramework $framework)
    {
        $this->framework = $framework;

        // Initialize contao framework
        $this->framework->initialize();
    }

    /**
     * Replace default password and send new
     */
    public function replaceDefaultPasswordAndSendNew()
    {
        /** @var  Config $configAdapter */
        $configAdapter = $this->framework->getAdapter(Config::class);

        /** @var Database $databaseAdapter */
        $databaseAdapter = $this->framework->getAdapter(Database::class);

        /** @var UserModel $userModelAdapter */
        $userModelAdapter = $this->framework->getAdapter(UserModel::class);

        /** @var Notification $notificationAdapter */
        $notificationAdapter = $this->framework->getAdapter(Notification::class);

        $this->defaultPassword = $configAdapter->get('SAC_EVT_DEFAULT_BACKEND_PASSWORD');

        $objDb = $databaseAdapter->getInstance()->prepare('SELECT * FROM tl_user WHERE pwChange=?')->execute('1');
        $counter = 0;

        while ($objDb->next())
        {
            if (($pw = $this->replaceDefaultPassword($objDb->id)) !== false)
            {
                $objUserModel = $userModelAdapter->findByPk($objDb->id);
                $objUserModel->pwChange = '1';
                $objUserModel->password = password_hash($pw, PASSWORD_DEFAULT);
                $objUserModel->save();

                // Generate text
                $bodyText = $this->generateEmailText($objUserModel, $pw);

                // Use terminal42/notification_center
                $objEmail = $notificationAdapter->findOneByType('default_email');
                if ($objEmail !== null)
                {
                    // Set token array
                    $arrTokens = array(
                        'email_sender_name'  => 'Administrator SAC Pilatus',
                        'email_sender_email' => $configAdapter->get('adminEmail'),
                        'reply_to'           => $configAdapter->get('adminEmail'),
                        'email_subject'      => html_entity_decode('Passwortänderung für Backend-Zugang auf der Webseite der SAC Sektion Pilatus'),
                        'email_text'         => $bodyText,
                        'send_to'            => $objUserModel->email
                    );

                    $objEmail->send($arrTokens, 'de');

                    // System log
                    $strText = sprintf('The default password for backend user %s has been replaced and sent by e-mail.', $objUserModel->name);
                    $logger = System::getContainer()->get('monolog.logger.contao');
                    $logger->log(LogLevel::INFO, $strText, array('contao' => new ContaoContext(__METHOD__, 'REPLACE DEFAULT PASSWORD')));

                    // Limitize emails
                    $counter++;
                    if ($counter > $this->emailSendLimit)
                    {
                        exit;
                    }
                }
            }
        }
    }

    /**
     * @param $id
     * @return bool|int
     */
    private function replaceDefaultPassword($id)
    {
        /** @var UserModel $userModelAdapter */
        $userModelAdapter = $this->framework->getAdapter(UserModel::class);

        $objUser = $userModelAdapter->findByPk($id);

        if (password_verify($this->defaultPassword, $objUser->password))
        {
            // Generate pw
            $objUserModel = $userModelAdapter->findByPk($objUser->id);
            if ($objUserModel->sacMemberId > 1)
            {
                $pw = rand(44444444, 99999999);
                return $pw;
            }
        }
        return false;
    }

    /**
     * @param $objMember
     * @param $pw
     * @return string
     */
    private function generateEmailText($objMember, $pw)
    {
        $text = 'Hallo %s
        
Dein bisheriges Default Passwort "%s" für den Backend-Zugang ist aus Gründen der Sicherheit ab sofort nicht mehr gültig. Mit dieser Nachricht erhältst du ein neues Passwort. Bitte logge dich mit deiner 6-stelligen Mitgliedernummer und dem Passwort auf https://www.sac-pilatus.ch/contao ein und ändere dein Passwort durch ein eigenes sicheres Passwort.

Benutzername: Deine 6-stellige Mitgliedernummer
Passwort: %s


Wir wünschen dir viel Spass beim Surfen auf unseren Webseite.
https://www.sac-pilatus.ch

Administrator SAC Sektion Pilatus"

----------------------------
Dies ist eine automatisch generierte Nachricht. Bitte antworte nicht darauf.';

        return sprintf(html_entity_decode($text), $objMember->firstname, $this->defaultPassword, $pw);
    }
}
