<?php

/**
 * SAC Event Tool Web Plugin for Contao
 * Copyright (c) 2008-2020 Marko Cupic
 * @package sac-event-tool-bundle
 * @author Marko Cupic m.cupic@gmx.ch, 2017-2020
 * @link https://github.com/markocupic/sac-event-tool-bundle
 */

namespace Markocupic\SacEventToolBundle\Contao\EventListener;

use Contao\BackendUser;
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\User;
use Contao\UserModel;
use Doctrine\DBAL\Connection;
use Markocupic\SacEventToolBundle\MaintainBackendUsersHomeDirectory;

/**
 * Class PostLoginListener
 * @package Markocupic\SacEventToolBundle\Contao\EventListener
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
        $maintainBackendUsersHomeDirectoryAdapter = $this->framework->getAdapter(MaintainBackendUsersHomeDirectory::class);
        $userModelAdapter = $this->framework->getAdapter(UserModel::class);

        if ($user instanceof BackendUser)
        {
            // Get all users
            /** @var  Doctrine\DBAL\Query\QueryBuilder $qb */
            $qb = $this->connection->createQueryBuilder();
            $qb->select('id')->from('tl_user');
            $result = $qb->execute();

            // Create user directories if they does not exist
            while (false !== ($row = $result->fetch()))
            {
                $userModel = $userModelAdapter->findByPk($row['id']);
                if ($userModel !== null)
                {
                    $maintainBackendUsersHomeDirectoryAdapter->createBackendUsersHomeDirectory($userModel);
                }
            }

            // Scan for unused old directories and remove them
            $maintainBackendUsersHomeDirectoryAdapter->removeUnusedBackendUsersHomeDirectories();
        }
    }

}
