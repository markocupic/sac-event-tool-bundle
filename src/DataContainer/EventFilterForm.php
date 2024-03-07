<?php

declare(strict_types=1);

/*
 * This file is part of SAC Event Tool Bundle.
 *
 * (c) Marko Cupic 2024 <m.cupic@gmx.ch>
 * @license GPL-3.0-or-later
 * For the full copyright and license information,
 * please view the LICENSE file that was distributed with this source code.
 * @link https://github.com/markocupic/sac-event-tool-bundle
 */

namespace Markocupic\SacEventToolBundle\DataContainer;

use Contao\CoreBundle\DependencyInjection\Attribute\AsCallback;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Driver\Exception;
use Doctrine\DBAL\Types\Types;
use Markocupic\SacEventToolBundle\Model\CourseMainTypeModel;
use Markocupic\SacEventToolBundle\Model\CourseSubTypeModel;

class EventFilterForm
{
    public function __construct(
        private readonly Connection $connection,
    ) {
    }

    #[AsCallback(table: 'tl_event_filter_form', target: 'fields.courseType.options', priority: 100)]
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
     * @throws Exception
     * @throws \Doctrine\DBAL\Exception
     */
    #[AsCallback(table: 'tl_event_filter_form', target: 'fields.organizers.options', priority: 100)]
    public function getOrganizers(): array
    {
        $arrOptions = [];

        $stmt = $this->connection->executeQuery('SELECT * FROM tl_event_organizer WHERE hideInEventFilter = ? ORDER BY sorting', [0], [Types::INTEGER]);

        while (false !== ($arrOrganizer = $stmt->fetchAssociative())) {
            $arrOptions[$arrOrganizer['id']] = $arrOrganizer['title'];
        }

        return $arrOptions;
    }
}
