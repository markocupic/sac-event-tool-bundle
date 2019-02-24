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


/**
 * Class GeneratePage
 * @package Markocupic\SacEventToolBundle\ContaoHooks
 */
class GeneratePage
{
    /**
     * @var ContaoFramework
     */
    private $framework;


    /**
     * Constructor.
     *
     * @param ContaoFramework $framework
     */
    public function __construct(ContaoFramework $framework)
    {
        $this->framework = $framework;
    }


    /**
     *
     */
    public function generatePage()
    {
        // empty at the moment

    }

}


