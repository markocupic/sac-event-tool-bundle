<?php

declare(strict_types=1);

/*
 * This file is part of SAC Event Tool Bundle.
 *
 * (c) Marko Cupic 2023 <m.cupic@gmx.ch>
 * @license GPL-3.0-or-later
 * For the full copyright and license information,
 * please view the LICENSE file that was distributed with this source code.
 * @link https://github.com/markocupic/sac-event-tool-bundle
 */

namespace Markocupic\SacEventToolBundle\DataContainer;

use Contao\CoreBundle\ServiceAnnotation\Callback;
use Contao\CourseMainTypeModel;
use Contao\CourseSubTypeModel;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Driver\Exception;

class EventFilterForm
{
    private Connection $connection;

    public function __construct(Connection $connection)
    {
        $this->connection = $connection;
    }

    /**
     * @Callback(table="tl_event_filter_form", target="fields.courseType.options")
     */
    public function getCourseTypes(): array
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
     * @Callback(table="tl_event_filter_form", target="fields.organizers.options")
     *
     * @throws Exception
     * @throws \Doctrine\DBAL\Exception
     */
    public function getOrganizers(): array
    {
        $arrOptions = [];

        $stmt = $this->connection->executeQuery('SELECT * FROM tl_event_organizer WHERE hideInEventFilter = ? ORDER BY sorting', ['']);

        while (false !== ($arrOrganizer = $stmt->fetchAssociative())) {
            $arrOptions[$arrOrganizer['id']] = $arrOrganizer['title'];
        }

        return $arrOptions;
    }
}
