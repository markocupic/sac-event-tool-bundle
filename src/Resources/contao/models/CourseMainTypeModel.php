<?php

/**
 * SAC Event Tool Web Plugin for Contao
 * Copyright (c) 2008-2017 Marko Cupic
 * @package sac-event-tool-bundle
 * @author Marko Cupic m.cupic@gmx.ch, 2017-2018
 * @link https://sac-kurse.kletterkader.com
 */

/**
 * Run in a custom namespace, so the class can be replaced
 */
namespace Contao;

/**
 * Class CourseMainTypeModel
 * @package Contao
 */
class CourseMainTypeModel extends \Model
{

    /**
     * Table name
     * @var string
     */
    protected static $strTable = 'tl_course_main_type';

}
