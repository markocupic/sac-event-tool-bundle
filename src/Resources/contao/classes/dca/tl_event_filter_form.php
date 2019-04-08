<?php

/**
 * SAC Event Tool Web Plugin for Contao
 * Copyright (c) 2008-2019 Marko Cupic
 * @package sac-event-tool-bundle
 * @author Marko Cupic m.cupic@gmx.ch, 2017-2019
 * @link https://github.com/markocupic/sac-event-tool-bundle
 */

/**
 * Class tl_event_filter_form
 */
class tl_event_filter_form extends Backend
{
    /**
     * @return array
     */
    public function getTourTypes()
    {
        $arrOptions = array();
        $objTourType = \Contao\TourTypeModel::findAll();
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
        $mainTypes = \Contao\CourseMainTypeModel::findAll();
        while ($mainTypes->next())
        {
            $opt[$mainTypes->name] = array();
            $subTypes = \Contao\CourseSubTypeModel::findByPid($mainTypes->id);
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
        $objOrganizer = Contao\EventOrganizerModel::findAll();
        while ($objOrganizer->next())
        {
            $arrOptions[$objOrganizer->id] = $objOrganizer->title;
        }
        return $arrOptions;
    }
}
