<?php

/**
 * SAC Event Tool Web Plugin for Contao
 * Copyright (c) 2008-2019 Marko Cupic
 * @package sac-event-tool-bundle
 * @author Marko Cupic m.cupic@gmx.ch, 2017-2019
 * @link https://github.com/markocupic/sac-event-tool-bundle
 */

namespace Markocupic\SacEventToolBundle\ContaoHooks;

use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\System;

/**
 * Class InitializeSystem
 * @package Markocupic\SacEventToolBundle\ContaoHooks
 */
class InitializeSystem
{
    /**
     * @var ContaoFramework
     */
    private $framework;

    /**
     * Constructor
     *
     * @param ContaoFramework $framework
     */
    public function __construct(ContaoFramework $framework)
    {
        $this->framework = $framework;
    }

    /**
     * Prepare the plugin environment
     */
    public function initializeSystem()
    {
        // Prepare Plugin environment, create folders, etc.
        $objPluginEnv = System::getContainer()->get('markocupic.sac_event_tool_bundle.prepare_plugin_environment');
        $objPluginEnv->preparePluginEnvironment();
    }

}
