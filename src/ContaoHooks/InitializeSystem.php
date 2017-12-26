<?php

/**
 * SAC Event Tool Web Plugin for Contao
 * Copyright (c) 2008-2017 Marko Cupic
 * @package sac-event-tool-bundle
 * @author Marko Cupic m.cupic@gmx.ch, 2017
 * @link    https://sac-kurse.kletterkader.com
 */

namespace Markocupic\SacEventToolBundle\ContaoHooks;

use Contao\CoreBundle\Framework\ContaoFrameworkInterface;
use Contao\Input;
use Contao\System;
use Markocupic\SacEventToolBundle\ExportEvents2Typo3;


/**
 * Class InitializeSystem
 * @package Markocupic\SacEventToolBundle\ContaoHooks
 */
class InitializeSystem
{
    /**
     * @var ContaoFrameworkInterface
     */
    private $framework;


    /**
     * Constructor
     *
     * @param ContaoFrameworkInterface $framework
     */
    public function __construct(ContaoFrameworkInterface $framework)
    {
        $this->framework = $framework;
    }


    /**
     *
     */
    public function initializeSystem()
    {

        // Prepare Plugin environment, create folders, etc.
        $objPluginEnv = System::getContainer()->get('markocupic.sac_event_tool_bundle.prepare_plugin_environment');

        $objPluginEnv->createPluginDirectories();


        // Convert events to typo3 html export file
        if (Input::get('action') === 'exportEvents2Typo3' && Input::get('id'))
        {
            ExportEvents2Typo3::sendToBrowser(Input::get('id'));
        }

        // Convert events to ical
        if (Input::get('action') === 'exportEventsToIcal' && Input::get('id'))
        {
            ExportEvents2Ical::sendToBrowser(Input::get('id'));
        }
    }


}