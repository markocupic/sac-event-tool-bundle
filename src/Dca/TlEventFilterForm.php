<?php

/**
 * SAC Event Tool Web Plugin for Contao
 * Copyright (c) 2008-2020 Marko Cupic
 * @package sac-event-tool-bundle
 * @author Marko Cupic m.cupic@gmx.ch, 2017-2020
 * @link https://github.com/markocupic/sac-event-tool-bundle
 */

namespace Markocupic\SacEventToolBundle\Dca;

use Contao\Backend;
use Contao\TourTypeModel;
use Contao\CourseMainTypeModel;
use Contao\CourseSubTypeModel;
use Contao\Database;


/**
 * Class TlEventFilterForm
 */
class TlEventFilterForm extends Backend
{
    /**
     * @return array
     */
    public function getTourTypes()
    {
        $arrOptions = array();
        $objTourType = TourTypeModel::findAll();
        while ($objTourType->next())
        {
            $arrOptions[$objTourType->id] = $objTourType->title;
        }
        return $arrOptions;
    }

    /**
     * @return array
     */
    public function getCourseTypes()
    {
        $opt = array();
        $mainTypes = CourseMainTypeModel::findAll();
        while ($mainTypes->next())
        {
            $opt[$mainTypes->name] = array();
            $subTypes = CourseSubTypeModel::findByPid($mainTypes->id);
            while ($subTypes->next())
            {
                $opt[$mainTypes->name][$subTypes->id] = $subTypes->name;
            }
        }
        return $opt;
    }

    /**
     * @return array
     */
    public function getOrganizers()
    {
        $arrOptions = array();
        $objOrganizer = Database::getInstance()
            ->prepare('SELECT * FROM tl_event_organizer WHERE hideInEventFilter=? ORDER BY sorting')
            ->execute('');
        while ($objOrganizer->next())
        {
            $arrOptions[$objOrganizer->id] = $objOrganizer->title;
        }
        return $arrOptions;
    }
}
