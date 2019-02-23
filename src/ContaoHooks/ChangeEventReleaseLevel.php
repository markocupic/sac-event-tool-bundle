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
use Contao\Date;
use Contao\Email;
use Contao\EventReleaseLevelPolicyModel;
use Contao\Input;
use Contao\MemberModel;
use Contao\StringUtil;


/**
 * Class ChangeEventReleaseLevel
 * @package Markocupic\SacEventToolBundle\ContaoHooks
 */
class ChangeEventReleaseLevel
{


    /**
     * Beta state
     * @param $objEvent
     * @param $strDirection
     */
    public function changeEventReleaseLevel($objEvent, $strDirection)
    {
        if (TL_MODE === 'BE')
        {
            $objCalendar = $objEvent->getRelated('pid');
            if ($objCalendar !== null)
            {
                if ($objCalendar->adviceOnEventReleaseLevelChange !== '')
                {
                    $objEventReleaseLevel = EventReleaseLevelPolicyModel::findByPk($objEvent->eventReleaseLevel);
                    if ($objEventReleaseLevel !== null)
                    {
                        $objUser = BackendUser::getInstance();
                        $objEmail = new Email();
                        $objEmail->from = Config::get('adminEmail');
                        $objEmail->fromName = 'Administrator SAC Pilatus';
                        $objEmail->subject = sprintf('Neue Freigabestufe (%s) für Event %s.', $objEventReleaseLevel->level, $objEvent->title);

                        if ($strDirection === 'down')
                        {
                            $objEmail->text = sprintf("Hallo \nEvent %s wurde soeben durch %s auf Freigabestufe %s (%s) hinuntergestuft. \n\n\nDies ist eine automatische Nachricht mit Ursprung in: %s\n Liebe Grüsse \n\n Administrator SAC Pilatus", $objEvent->title, $objUser->name, $objEventReleaseLevel->level, $objEventReleaseLevel->title, __METHOD__ . ' LINE: ' . __LINE__);
                        }
                        else
                        {
                            $objEmail->text = sprintf("Hallo \nEvent %s wurde soeben durch %s auf Freigabestufe %s (%s) hochgestuft. \n\n\nDies ist eine automatische Nachricht mit Ursprung in: %s\n Liebe Grüsse \n\n Administrator SAC Pilatus", $objEvent->title, $objUser->name, $objEventReleaseLevel->level, $objEventReleaseLevel->title, __METHOD__ . ' LINE: ' . __LINE__);
                        }
                        $objEmail->sendTo($objCalendar->adviceOnEventReleaseLevelChange);
                    }
                }
            }
        }
    }
}


