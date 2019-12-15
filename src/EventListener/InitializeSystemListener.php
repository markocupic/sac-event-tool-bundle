<?php

declare(strict_types=1);

/**
 * SAC Event Tool Web Plugin for Contao
 * Copyright (c) 2008-2020 Marko Cupic
 * @package sac-event-tool-bundle
 * @author Marko Cupic m.cupic@gmx.ch, 2017-2020
 * @link https://github.com/markocupic/sac-event-tool-bundle
 */

namespace Markocupic\SacEventToolBundle\EventListener;

use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\System;

/**
 * Class InitializeSystemListener
 * @package Markocupic\SacEventToolBundle\EventListener
 */
class InitializeSystemListener
{
    /**
     * @var ContaoFramework
     */
    private $framework;

    /**
     * InitializeSystemListener constructor.
     * @param ContaoFramework $framework
     */
    public function __construct(ContaoFramework $framework)
    {
        $this->framework = $framework;
    }

    /**
     * Prepare the SAC Event Tool plugin environment
     */
    public function onInitializeSystem(): void
    {
        $systemAdapter = $this->framework->getAdapter(System::class);
        // Prepare Plugin environment, create folders, etc.
        $objPluginEnv = $systemAdapter->getContainer()->get('markocupic.sac_event_tool_bundle.prepare_plugin_environment');
        $objPluginEnv->preparePluginEnvironment();
    }

}
