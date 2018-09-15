<?php

/**
 * SAC Event Tool Web Plugin for Contao
 * Copyright (c) 2008-2017 Marko Cupic
 * @package sac-event-tool-bundle
 * @author Marko Cupic m.cupic@gmx.ch, 2017-2018
 * @link https://sac-kurse.kletterkader.com
 */

namespace Markocupic\SacEventToolBundle;


use Contao\EventReleaseLevelPolicyModel;
use Contao\Module;


/**
 * Class ModuleSacEventToolPrintExport
 * @package Markocupic\SacEventToolBundle
 */
abstract class ModuleSacEventToolPrintExport extends Module
{

    /**
     * @param $objEvent
     * @return bool
     */
    public function hasValidReleaseLevel($objEvent, $minLevel = null)
    {
        if ($objEvent->published)
        {
            return true;
        }

        if ($objEvent !== null)
        {

            if ($objEvent->eventReleaseLevel > 0)
            {
                $objEventReleaseLevel = EventReleaseLevelPolicyModel::findByPk($objEvent->eventReleaseLevel);
                if ($objEventReleaseLevel !== null)
                {
                    if ($minLevel === null)
                    {

                        $nextLevelModel = EventReleaseLevelPolicyModel::findNextLevel($objEvent->eventReleaseLevel);
                        $lastLevelModel = EventReleaseLevelPolicyModel::findLastLevelByEventId($objEvent->id);
                        if ($nextLevelModel !== null && $lastLevelModel !== null)
                        {
                            if ($nextLevelModel->id === $lastLevelModel->id)
                            {
                                return true;
                            }
                        }
                    }
                    else
                    {
                       if($objEventReleaseLevel->level >= $minLevel)
                       {
                           return true;
                       }
                    }
                }
            }
        }
        return false;
    }

    /**
     * Replace unwanted chars
     * @param $strValue
     * @return mixed
     */
    public function searchAndReplace($strValue)
    {

        $arrReplace = array(
            // Replace (at) with @
            '(at)'         => '@',
            '&#40;at&#41;' => '@',
        );

        foreach ($arrReplace as $k => $v)
        {
            $strValue = str_replace($k, $v, $strValue);
        }

        return $strValue;
    }
}