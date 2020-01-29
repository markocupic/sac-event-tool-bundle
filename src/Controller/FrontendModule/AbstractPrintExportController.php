<?php

declare(strict_types=1);

/**
 * SAC Event Tool Web Plugin for Contao
 * Copyright (c) 2008-2020 Marko Cupic
 * @package sac-event-tool-bundle
 * @author Marko Cupic m.cupic@gmx.ch, 2017-2020
 * @link https://github.com/markocupic/sac-event-tool-bundle
 */

namespace Markocupic\SacEventToolBundle\Controller\FrontendModule;

use Contao\CalendarEventsModel;
use Contao\EventReleaseLevelPolicyModel;
use Contao\CoreBundle\Controller\FrontendModule\AbstractFrontendModuleController;
use Contao\ModuleModel;
use Contao\PageModel;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Class AbstractModuleSacEventToolPrintExport
 * @package Markocupic\SacEventToolBundle
 */
abstract class AbstractPrintExportController extends AbstractFrontendModuleController
{

    /**
     * @param CalendarEventsModel $objEvent
     * @param int|null $minLevel
     * @return bool
     */
    public function hasValidReleaseLevel(CalendarEventsModel $objEvent, int $minLevel = null): bool
    {
        /** @var EventReleaseLevelPolicyModel $eventReleaseLevelPolicyModelAdapter */
        $eventReleaseLevelPolicyModelAdapter = $this->get('contao.framework')->getAdapter(EventReleaseLevelPolicyModel::class);

        if ($objEvent->published)
        {
            return true;
        }

        if ($objEvent !== null)
        {
            if ($objEvent->eventReleaseLevel > 0)
            {
                $objEventReleaseLevel = $eventReleaseLevelPolicyModelAdapter->findByPk($objEvent->eventReleaseLevel);
                if ($objEventReleaseLevel !== null)
                {
                    if ($minLevel === null)
                    {
                        /** @var  EventReleaseLevelPolicyModel $nextLevelModel */
                        $nextLevelModel = $eventReleaseLevelPolicyModelAdapter->findNextLevel($objEvent->eventReleaseLevel);
                        $lastLevelModel = $eventReleaseLevelPolicyModelAdapter->findLastLevelByEventId($objEvent->id);
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
                        if ($objEventReleaseLevel->level >= $minLevel)
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
     * Replace chars
     * @param string $strValue
     * @return string
     */
    public function searchAndReplace(string $strValue = ''): string
    {
        if (!empty($strValue))
        {
            $arrReplace = [
                // Replace (at) with @
                '(at)'         => '@',
                '&#40;at&#41;' => '@',
            ];

            foreach ($arrReplace as $k => $v)
            {
                $strValue = str_replace($k, $v, $strValue);
            }
        }

        return $strValue;
    }
}
