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
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;

readonly class EventOrganizer
{
    public function __construct(private Connection $connection)
    {
    }

    /**
     * @throws Exception
     */
    #[AsCallback(table: 'tl_event_organizer', target: 'fields.belongsToOrganization.options', priority: 100)]
    public function getSacSections(): array
    {
        return $this->connection->fetchAllKeyValue('SELECT sectionId, name FROM tl_sac_section');
    }

    /**
     * User roles with an email address and active backend users.
     *
     * @throws Exception
     */
    #[AsCallback(table: 'tl_event_organizer', target: 'fields.notifyWebmasterOnNewEventBlog.options', priority: 100)]
    public function getTourReportReviewers(): array
    {
        $options = [];

        $roles = $this->connection->fetchAllAssociative("SELECT id, title, email FROM tl_user_role WHERE email != '' ORDER BY title");
        $users = $this->connection->fetchAllAssociative('SELECT id, name, email FROM tl_user WHERE disable = 0 ORDER BY name');

        foreach ($roles as $role) {
            $options['user_role_id:'.$role['id']] = \sprintf('%s [%s]', $role['title'], $role['email']);
        }

        foreach ($users as $user) {
            $options['user_id:'.$user['id']] = \sprintf('%s [%s]', $user['name'], $user['email']);
        }

        return $options;
    }
}
