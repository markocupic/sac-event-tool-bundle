<?php

/**
 * SAC Event Tool Web Plugin for Contao
 * Copyright (c) 2008-2020 Marko Cupic
 * @package sac-event-tool-bundle
 * @author Marko Cupic m.cupic@gmx.ch, 2017-2020
 * @link https://github.com/markocupic/sac-event-tool-bundle
 */

/**
 * Run in a custom namespace, so the class can be replaced
 */
namespace Contao;

/**
 * Class TourTypeModel
 * @package Contao
 */
class TourTypeModel extends \Model
{

    /**
     * Table name
     * @var string
     */
    protected static $strTable = 'tl_tour_type';

}
