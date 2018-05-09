<?php

/**
 * SAC Event Tool Web Plugin for Contao
 * Copyright (c) 2008-2017 Marko Cupic
 * @package sac-event-tool-bundle
 * @author Marko Cupic m.cupic@gmx.ch, 2017-2018
 * @link https://sac-kurse.kletterkader.com
 */

namespace Markocupic\SacEventToolBundle\Services\Newsletter;


use Contao\Database;
use Contao\Email;
use Contao\FrontendTemplate;
use Contao\MemberModel;
use Contao\StringUtil;
use Contao\Validator;


/**
 * Class SendPasswordToMembers
 * @package Markocupic\SacEventToolBundle\Services\Newsletter
 */
class SendPasswordToMembers
{

    /**
     * @param int $limit
     */
    public static function sendPasswordToMembers($limit = 25)
    {
        if ($limit < 1)
        {
            $limit = 25;
        }

        $objMember = Database::getInstance()->prepare("SELECT * FROM tl_member WHERE sacMemberId = ?")->limit($limit)->execute('185155');
        // $objMember = Database::getInstance()->prepare("SELECT * FROM tl_member WHERE isSacMember = ? AND email != ? AND passwordSent=?")->limit($limit)->execute('1', '', '');
        if (!$objMember->numRows)
        {
            return;
        }

        while ($objMember->next())
        {
            $passw = StringUtil::substr(md5(uniqid(true)), 8, '');
            $passwordHash = password_hash($passw, PASSWORD_DEFAULT);

            $objEmail = new Email();

            //$this->email->from = 'm.cupic@gmx.ch';
            $objEmail->from = 'geschaeftsstelle@sac-pilatus.ch';

            $objEmail->fromName = 'Geschaeftsstelle SAC Sektion Pilatus';

            //$this->email->replyTo('m.cupic@gmx.ch');
            $objEmail->replyTo('geschaeftsstelle@sac-pilatus.ch');

            $objEmail->subject = 'Dein Passwort für den neuen Internet-Auftritt der SAC Sektion Pilatus';


            // Text
            $objTemplate = new FrontendTemplate('sendPasswordToMembersText');
            $objTemplate->firstname = $objMember->firstname;
            $objTemplate->sacMemberId = StringUtil::substr($objMember->sacMemberId, 3, '');
            $objTemplate->password = $passw;


            $objEmail->text = $objTemplate->parse();


            // Send email
            if (Validator::isEmail($objMember->email))
            {
                //$this->email->sendTo('m.cupic@gmx.ch');
                $objEmail->sendTo($objMember->email);
                // Set flag in tl_member
                $oMember = MemberModel::findByPk($objMember->id);
                $oMember->password = $passwordHash;
                $oMember->passwordSent = '1';
                $oMember->save();
            }

            exit();
        }

    }
}