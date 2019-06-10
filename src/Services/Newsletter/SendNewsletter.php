<?php

/**
 * SAC Event Tool Web Plugin for Contao
 * Copyright (c) 2008-2019 Marko Cupic
 * @package sac-event-tool-bundle
 * @author Marko Cupic m.cupic@gmx.ch, 2017-2019
 * @link https://github.com/markocupic/sac-event-tool-bundle
 */

namespace Markocupic\SacEventToolBundle\Services\Newsletter;

use Contao\Email;
use Contao\Database;
use Contao\FrontendTemplate;
use Contao\Validator;

/**
 * Class SendNewsletter
 * @package Markocupic\SacEventToolBundle\Services\Newsletter
 */
class SendNewsletter
{

    /**
     * @param int $limit
     */
    public static function sendSurveyNewsletter($limit = 25)
    {
        if ($limit < 1)
        {
            $limit = 25;
        }

        $objMember = Database::getInstance()->prepare("SELECT * FROM tl_member WHERE isSacMember = ? AND email != ? AND newsletterSent=?")->limit($limit)->execute('1', '', '');
        if (!$objMember->numRows)
        {
            return;
        }

        while ($objMember->next())
        {
            $objEmail = new Email();

            //$this->email->from = 'm.cupic@gmx.ch';
            $objEmail->from = 'geschaeftsstelle@sac-pilatus.ch';

            $objEmail->fromName = 'Geschaeftsstelle SAC Sektion Pilatus';

            //$this->email->replyTo('m.cupic@gmx.ch');
            $objEmail->replyTo('geschaeftsstelle@sac-pilatus.ch');

            $objEmail->subject = 'Umfrage zum Redesign der Webseite des SAC Pilatus';

            // HTML
            $objTemplate = new FrontendTemplate('newsletterRelaunchWebsiteSurveyHtml');
            $objTemplate->firstname = $objMember->firstname;
            $objTemplate->imageSRC = 'https://sac-kurse.kletterkader.com/files/fileadmin/page_assets/newsletter/image-sac-survey.jpg';
            $objTemplate->surveyLink = 'https://docs.google.com/forms/d/e/1FAIpQLSftI21CwMu6s4gxKykPugg-sSAkEaxBtxzfVG29-D2-F1-UZg/viewform';
            $objEmail->html = $objTemplate->parse();

            // Text
            $objTemplate = new FrontendTemplate('newsletterRelaunchWebsiteSurveyText');
            $objTemplate->firstname = $objMember->firstname;
            $objEmail->text = $objTemplate->parse();

            // Send email
            if (Validator::isEmail($objMember->email))
            {
                //$this->email->sendTo('m.cupic@gmx.ch');
                $objEmail->sendTo($objMember->email);
            }

            // Set flag in tl_member
            $set = array('newsletterSent' => '1');
            Database::getInstance()->prepare('UPDATE tl_member %s WHERE id=?')->set($set)->execute($objMember->id);
            exit();
        }
    }
}
