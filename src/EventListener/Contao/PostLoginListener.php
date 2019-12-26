<?php

/**
 * SAC Event Tool Web Plugin for Contao
 * Copyright (c) 2008-2020 Marko Cupic
 * @package sac-event-tool-bundle
 * @author Marko Cupic m.cupic@gmx.ch, 2017-2020
 * @link https://github.com/markocupic/sac-event-tool-bundle
 */

declare(strict_types=1);

namespace Markocupic\SacEventToolBundle\EventListener\Contao;

use Contao\BackendUser;
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\System;
use Contao\User;
use Contao\UserModel;
use Doctrine\DBAL\Connection;
use Markocupic\SacEventToolBundle\MaintainBackendUsersHomeDirectory;

/**
 * Class PostLoginListener
 * @package Markocupic\SacEventToolBundle\EventListener\Contao
 */
class PostLoginListener
{

    /**
     * @var ContaoFramework
     */
    private $framework;

    /**
     * @var string $rootDir
     */
    private $rootDir;

    /**
     * @var Connection
     */
    private $connection;

    /**
     * PostLoginListener constructor.
     * @param ContaoFramework $framework
     * @param Connection $connection
     * @param $rootDir
     */
    public function __construct(ContaoFramework $framework, Connection $connection, $rootDir)
    {
        $this->framework = $framework;
        $this->connection = $connection;
        $this->rootDir = $rootDir;
    }

    /**
     * Create user directories if they does not exist and remove no more used directories
     * @param User $user
     */
    public function onPostLogin(User $user)
    {
        $userModelAdapter = $this->framework->getAdapter(UserModel::class);

        if ($user instanceof BackendUser)
        {
            // Get all users
            /** @var  Doctrine\DBAL\Query\QueryBuilder $qb */
            $qb = $this->connection->createQueryBuilder();
            $qb->select('id')->from('tl_user');
            $result = $qb->execute();

            $objBackendUserDir = System::getContainer()->get('Markocupic\SacEventToolBundle\Services\BackendUser\MaintainBackendUsersHomeDirectory');

            // Create user directories if they does not exist
            while (false !== ($row = $result->fetch()))
            {
                $userModel = $userModelAdapter->findByPk($row['id']);
                if ($userModel !== null)
                {
                    $objBackendUserDir->createBackendUsersHomeDirectory($userModel);
                }
            }

            // Scan for unused old directories and remove them
            $objBackendUserDir->removeUnusedBackendUsersHomeDirectories();
        }
    }

}
