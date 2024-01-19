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

use Contao\Controller;
use Contao\CoreBundle\DataContainer\PaletteManipulator;
use Contao\CoreBundle\DependencyInjection\Attribute\AsCallback;
use Contao\DataContainer;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Driver\Exception;
use Markocupic\SacEventToolBundle\Controller\ContentElement\UserPortraitListController;

class Content
{
    public function __construct(
        private readonly Connection $connection,
    ) {
    }

    /**
     * @throws \Doctrine\DBAL\Exception
     */
    #[AsCallback(table: 'tl_content', target: 'config.onload', priority: 100)]
    public function setPalette(DataContainer $dc): void
    {
        if ($dc->id > 0) {
            $arrRow = $this->connection->fetchAssociative('SELECT * FROM tl_content WHERE id = ?', [$dc->id]);

            if ($arrRow) {
                // Set palette for content element "user_portrait_list"
                if ('user_portrait_list' === $arrRow['type']) {
                    if ('selectUsers' === $arrRow['userList_selectMode']) {
                        PaletteManipulator::create()
                            ->removeField('userList_userRoles')
                            ->removeField('userList_queryType')
                            ->applyToPalette(UserPortraitListController::TYPE, $dc->table)
                        ;
                    } else {
                        PaletteManipulator::create()
                            ->removeField('userList_users')
                            ->applyToPalette(UserPortraitListController::TYPE, $dc->table)
                        ;
                    }
                }
            }
        }
    }

    /**
     * Get all user roles.
     *
     * @throws Exception
     * @throws \Doctrine\DBAL\Exception
     */
    #[AsCallback(table: 'tl_content', target: 'fields.userList_userRoles.options', priority: 100)]
    public function optionsCallbackUserRoles(): array
    {
        $options = [];

        $stmt = $this->connection->executeQuery('SELECT * FROM tl_user_role ORDER BY sorting ASC', []);

        while (false !== ($arrUserRole = $stmt->fetchAssociative())) {
            $options[$arrUserRole['id']] = $arrUserRole['title'];
        }

        return $options;
    }

    /**
     * Return all user portrait list templates as array.
     */
    #[AsCallback(table: 'tl_content', target: 'fields.userList_template.options', priority: 100)]
    public function getUserListTemplates(): array
    {
        return Controller::getTemplateGroup('ce_user_portrait_list');
    }

    /**
     * Return all user portrait list partial templates as array.
     */
    #[AsCallback(table: 'tl_content', target: 'fields.userList_partial_template.options', priority: 100)]
    public function getUserListPartialTemplates(): array
    {
        return Controller::getTemplateGroup('user_portrait_list_partial_');
    }
}
