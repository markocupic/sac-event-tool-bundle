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

use Contao\Controller;
use Contao\CoreBundle\DataContainer\PaletteManipulator;
use Contao\CoreBundle\DependencyInjection\Attribute\AsCallback;
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\DataContainer;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;
use Markocupic\SacEventToolBundle\Controller\ContentElement\UserPortraitListController;

readonly class Content
{
    public function __construct(
        private Connection $connection,
        private ContaoFramework $framework,
    ) {
    }

    /**
     * Content element "user_portrait_list": Users are either selected one by one or
     * by user role. Only show the fields of the selected mode.
     *
     * @throws Exception
     */
    #[AsCallback(table: 'tl_content', target: 'config.onload', priority: 100)]
    public function setPalette(DataContainer $dc): void
    {
        if ((int) $dc->id <= 0) {
            return;
        }

        $row = $this->connection->fetchAssociative('SELECT type, userList_selectMode FROM tl_content WHERE id = ?', [$dc->id]);

        if (false === $row || 'user_portrait_list' !== $row['type']) {
            return;
        }

        $paletteManipulator = PaletteManipulator::create();

        if ('selectUsers' === $row['userList_selectMode']) {
            $paletteManipulator
                ->removeField('userList_userRoles')
                ->removeField('userList_queryType')
            ;
        } else {
            $paletteManipulator->removeField('userList_users');
        }

        $paletteManipulator->applyToPalette(UserPortraitListController::TYPE, $dc->table);
    }

    /**
     * @throws Exception
     */
    #[AsCallback(table: 'tl_content', target: 'fields.userList_userRoles.options', priority: 100)]
    public function getUserRoles(): array
    {
        return $this->connection->fetchAllKeyValue('SELECT id, title FROM tl_user_role ORDER BY sorting ASC');
    }

    #[AsCallback(table: 'tl_content', target: 'fields.userList_template.options', priority: 100)]
    public function getUserListTemplates(): array
    {
        return $this->framework->getAdapter(Controller::class)->getTemplateGroup('ce_user_portrait_list');
    }

    #[AsCallback(table: 'tl_content', target: 'fields.userList_partial_template.options', priority: 100)]
    public function getUserListPartialTemplates(): array
    {
        return $this->framework->getAdapter(Controller::class)->getTemplateGroup('user_portrait_list_partial_');
    }
}
