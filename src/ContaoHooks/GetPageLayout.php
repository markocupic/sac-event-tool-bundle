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
use Contao\FilesModel;
use Contao\StringUtil;
use Contao\System;

/**
 * Class GetPageLayout
 * @package Markocupic\SacEventToolBundle\ContaoHooks
 */
class GetPageLayout
{

    /**
     * @param $objPage
     * @param $objLayout
     * @param $objPty
     */
    public function purgeScriptCache($objPage, $objLayout, $objPty)
    {
        // Purge script cache in dev mode
        $kernel = System::getContainer()->get('kernel');
        if ($kernel->isDebug())
        {
            $objAutomator = new Automator();
            $objAutomator->purgeScriptCache();
            $rootDir = System::getContainer()->getParameter('kernel.project_dir');
            if ($objLayout->external !== '')
            {
                $arrExternal = StringUtil::deserialize($objLayout->external);
                if (!empty($arrExternal) && is_array($arrExternal))
                {
                    $objFile = FilesModel::findMultipleByUuids($arrExternal);
                    while ($objFile->next())
                    {
                        if (is_file($rootDir . '/' . $objFile->path))
                        {
                            touch($rootDir . '/' . $objFile->path);
                        }
                    }
                }
            }
        }
    }
}
