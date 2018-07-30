<?php

/**
 * SAC Event Tool Web Plugin for Contao
 * Copyright (c) 2008-2017 Marko Cupic
 * @package sac-event-tool-bundle
 * @author Marko Cupic m.cupic@gmx.ch, 2017-2018
 * @link https://sac-kurse.kletterkader.com
 */

namespace Markocupic\SacEventToolBundle\ContaoHooks;

/**
 * Class GetContentElement
 * @package Markocupic\SacEventToolBundle\ContaoHooks
 */
class GetContentElement
{


    /**
     *
     */
    public function getContentElement($objElement, $strBuffer)
    {

        return $strBuffer;
    }

}


