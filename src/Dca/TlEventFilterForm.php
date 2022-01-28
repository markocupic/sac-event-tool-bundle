<?php

declare(strict_types=1);

/*
 * This file is part of SAC Event Tool Bundle.
 *
 * (c) Marko Cupic 2022 <m.cupic@gmx.ch>
 * @license GPL-3.0-or-later
 * For the full copyright and license information,
 * please view the LICENSE file that was distributed with this source code.
 * @link https://github.com/markocupic/sac-event-tool-bundle
 */

namespace Markocupic\SacEventToolBundle\Dca;

use Contao\Backend;
use Contao\CourseMainTypeModel;
use Contao\CourseSubTypeModel;
use Contao\Database;
use Contao\TourTypeModel;

/**
 * Class TlEventFilterForm.
 */
class TlEventFilterForm extends Backend
{
    /**
     * @return array
     */
    public function getTourTypes()
    {
        $arrOptions = [];
        $objTourType = TourTypeModel::findAll();

        while ($objTourType->next()) {
            $arrOptions[$objTourType->id] = $objTourType->title;
        }

        return $arrOptions;
    }

    /**
     * @return array
     */
    public function getCourseTypes()
    {
        $opt = [];
        $mainTypes = CourseMainTypeModel::findAll();

        while ($mainTypes->next()) {
            $opt[$mainTypes->name] = [];
            $subTypes = CourseSubTypeModel::findByPid($mainTypes->id);

            while ($subTypes->next()) {
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
        $arrOptions = [];
        $objOrganizer = Database::getInstance()
            ->prepare('SELECT * FROM tl_event_organizer WHERE hideInEventFilter=? ORDER BY sorting')
            ->execute('')
        ;

        while ($objOrganizer->next()) {
            $arrOptions[$objOrganizer->id] = $objOrganizer->title;
        }

        return $arrOptions;
    }
}
