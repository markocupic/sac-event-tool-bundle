<?php

declare(strict_types=1);

/*
 * This file is part of SAC Event Tool Bundle.
 *
 * (c) Marko Cupic <m.cupic@gmx.ch>
 * @license GPL-3.0-or-later
 * For the full copyright and license information,
 * please view the LICENSE file that was distributed with this source code.
 * @link https://github.com/markocupic/sac-event-tool-bundle
 */

namespace Markocupic\SacEventToolBundle\DataContainer;

use Contao\CoreBundle\DependencyInjection\Attribute\AsCallback;
use Contao\CoreBundle\Framework\ContaoFramework;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;
use Doctrine\DBAL\Types\Types;
use Markocupic\SacEventToolBundle\Model\CourseMainTypeModel;
use Markocupic\SacEventToolBundle\Model\CourseSubTypeModel;

readonly class EventFilterForm
{
    public function __construct(
        private Connection $connection,
        private ContaoFramework $framework,
    ) {
    }

    /**
     * Course sub types grouped by course main type.
     */
    #[AsCallback(table: 'tl_event_filter_form', target: 'fields.courseType.options', priority: 100)]
    public function getCourseTypes(): array
    {
        $courseMainTypeModel = $this->framework->getAdapter(CourseMainTypeModel::class);
        $courseSubTypeModel = $this->framework->getAdapter(CourseSubTypeModel::class);

        $options = [];

        foreach ($courseMainTypeModel->findAll() ?? [] as $mainType) {
            $options[$mainType->name] = [];

            foreach ($courseSubTypeModel->findByPid($mainType->id) ?? [] as $subType) {
                $options[$mainType->name][$subType->id] = $subType->name;
            }
        }

        return $options;
    }

    /**
     * @throws Exception
     */
    #[AsCallback(table: 'tl_event_filter_form', target: 'fields.organizers.options', priority: 100)]
    public function getOrganizers(): array
    {
        return $this->connection->fetchAllKeyValue(
            'SELECT id, title FROM tl_event_organizer WHERE hideInEventFilter = ? ORDER BY sorting',
            [0],
            [Types::INTEGER],
        );
    }
}
