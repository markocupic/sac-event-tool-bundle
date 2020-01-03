<?php

/**
 * SAC Event Tool Web Plugin for Contao
 * Copyright (c) 2008-2020 Marko Cupic
 * @package sac-event-tool-bundle
 * @author Marko Cupic m.cupic@gmx.ch, 2017-2020
 * @link https://github.com/markocupic/sac-event-tool-bundle
 */

namespace Markocupic\SacEventToolBundle\Controller\FrontendModule;

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
     * @param Request $request
     * @param ModuleModel $model
     * @param string $section
     * @param array|null $classes
     * @param PageModel|null $page
     * @return Response
     */
    public function __invoke(Request $request, ModuleModel $model, string $section, array $classes = null, PageModel $page = null): Response
    {
        // Call parent __invoke
        return parent::__invoke($request, $model, $section, $classes);
    }

    public static function getSubscribedServices(): array
    {
        $services = parent::getSubscribedServices();

        return $services;
    }

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
