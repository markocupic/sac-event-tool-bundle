<?php

/**
 * SAC Event Tool Web Plugin for Contao
 * Copyright (c) 2008-2020 Marko Cupic
 * @package sac-event-tool-bundle
 * @author Marko Cupic m.cupic@gmx.ch, 2017-2020
 * @link https://github.com/markocupic/sac-event-tool-bundle
 */


namespace Markocupic\SacEventToolBundle\Dca;

use Contao\DataContainer;
use Contao\Backend;

/**
 * Class TlEventType
 * @package Markocupic\SacEventToolBundle\Dca
 */
class TlEventType extends Backend
{

    /**
     * @param $strValue
     * @param DataContainer $dc
     * @return mixed
     */
    public function loadCallbackAlias($strValue, DataContainer $dc)
    {
        // Prevent renaming the alias if it was set
        if ($strValue != '')
        {
            $GLOBALS['TL_DCA']['tl_event_type']['fields']['alias']['eval']['readonly'] = true;
        }

        return $strValue;
    }

}
