<?php

/**
 * SAC Event Tool Web Plugin for Contao
 * Copyright (c) 2008-2019 Marko Cupic
 * @package sac-event-tool-bundle
 * @author Marko Cupic m.cupic@gmx.ch, 2017-2019
 * @link https://github.com/markocupic/sac-event-tool-bundle
 */

namespace Markocupic\SacEventToolBundle;


use Contao\Config;
use Contao\Database;
use Contao\UserModel;
use NotificationCenter\Model\Notification;

class ReplaceDefaultPassword
{

    /**
     * @var
     */
    protected $defaultPassword;


    /**
     * @var int
     */
    protected $emailSendLimit = 20;


    /**
     * Replace the default password
     */
    public function sendNewPassword()
    {
        $this->defaultPassword = Config::get('SAC_EVT_DEFAULT_BACKEND_PASSWORD');

        $objDb = Database::getInstance()->prepare('SELECT * FROM tl_user WHERE pwChange=?')->execute('1');
        $counter = 0;
        while ($objDb->next())
        {
            if (($pw = $this->replaceDefaultPassword($objDb->id)) !== false)
            {
                $objUserModel = UserModel::findByPk($objDb->id);
                $objUserModel->pwChange = '1';
                $objUserModel->password = password_hash($pw, PASSWORD_DEFAULT);
                $objUserModel->save();

                // Generate text
                $bodyText = $this->generateEmailText($objUserModel, $pw);

                // Use terminal42/notification_center
                $objEmail = Notification::findOneByType('default_email');
                if ($objEmail !== null)
                {
                    // Set token array
                    $arrTokens = array(
                        'email_sender_name'  => 'Administrator SAC Pilatus',
                        'email_sender_email' => Config::get('adminEmail'),
                        'replyTo'            => Config::get('adminEmail'),
                        'email_subject'      => html_entity_decode('Passwortänderung auf der Webseite der SAC Sektion Pilatus'),
                        'email_text'         => $bodyText,
                        'send_to'            => $objUserModel->email
                    );
                    $objEmail->send($arrTokens, 'de');

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
        $objUser = UserModel::findByPk($id);

        if (password_verify($this->defaultPassword, $objUser->password))
        {
            // Generate pw
            $objUserModel = UserModel::findByPk($objUser->id);
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

Projektteam "Neue Website SAC Pilatus"

----------------------------
Dies ist eine automatisch generierte Nachricht. Bitte antworte nicht darauf.';

        return sprintf(html_entity_decode($text), $objMember->firstname, $this->defaultPassword, $pw);
    }
}