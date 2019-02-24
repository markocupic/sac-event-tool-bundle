<?php

/**
 * SAC Event Tool Web Plugin for Contao
 * Copyright (c) 2008-2019 Marko Cupic
 * @package sac-event-tool-bundle
 * @author Marko Cupic m.cupic@gmx.ch, 2017-2019
 * @link https://github.com/markocupic/sac-event-tool-bundle
 */

namespace Markocupic\SacEventToolBundle\ContaoHooks;

use Contao\BackendUser;
use Contao\Config;
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\Date;
use Contao\Email;
use Contao\Input;
use Contao\MemberModel;
use Contao\StringUtil;


/**
 * Class PublishEvent
 * @package Markocupic\SacEventToolBundle\ContaoHooks
 */
class PublishEvent
{

    /**
     * Beta state
     * @param $objEvent
     */
    public function publishEvent($objEvent)
    {
        if (TL_MODE === 'BE')
        {
            $objCalendar = $objEvent->getRelated('pid');
            if ($objCalendar !== null)
            {
                if ($objCalendar->adviceOnEventReleaseLevelChange !== '')
                {
                    $objUser = BackendUser::getInstance();
                    $objEmail = new Email();
                    $objEmail->from = Config::get('adminEmail');
                    $objEmail->fromName = 'Administrator SAC Pilatus';
                    $objEmail->subject = sprintf('Event %s wurde veröffentlicht', $objEvent->title);
                    $objEmail->text = sprintf("Hallo \nEvent %s wurde soeben durch %s veröffentlicht. \n\nDies ist eine automatische Nachricht mit Ursprung in: %s\n Liebe Grüsse\n\nAdministrator SAC Pilatus",  $objEvent->title, $objUser->name, __METHOD__ . ' LINE: ' . __LINE__);
                    $objEmail->sendTo($objCalendar->adviceOnEventReleaseLevelChange);
                }
            }
        }
    }
}


