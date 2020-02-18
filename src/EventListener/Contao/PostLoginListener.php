<?php

declare(strict_types=1);

/**
 * SAC Event Tool Web Plugin for Contao
 * Copyright (c) 2008-2020 Marko Cupic
 * @package sac-event-tool-bundle
 * @author Marko Cupic m.cupic@gmx.ch, 2017-2020
 * @link https://github.com/markocupic/sac-event-tool-bundle
 */

namespace Markocupic\SacEventToolBundle\EventListener\Contao;

use Contao\BackendUser;
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\User;
use Contao\UserModel;
use Markocupic\SacEventToolBundle\User\BackendUser\MaintainBackendUsersHomeDirectory;

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
     * @var MaintainBackendUsersHomeDirectory
     */
    private $maintainBackendUsersHomeDirectory;

    /**
     * PostLoginListener constructor.
     * @param ContaoFramework $framework
     * @param MaintainBackendUsersHomeDirectory $maintainBackendUsersHomeDirectory
     */
    public function __construct(ContaoFramework $framework, MaintainBackendUsersHomeDirectory $maintainBackendUsersHomeDirectory)
    {
        $this->framework = $framework;
        $this->maintainBackendUsersHomeDirectory = $maintainBackendUsersHomeDirectory;
        $this->framework->initialize();
    }

    /**
     * Create user directories if they do not exist
     * and remove them if they are no more used
     * @param User $user
     */
    public function onPostLogin(User $user)
    {
        /** @var UserModel $userModelAdapter */
        $userModelAdapter = $this->framework->getAdapter(UserModel::class);

        if ($user instanceof BackendUser)
        {
            $userModel = $userModelAdapter->findAll();
            if ($userModel !== null)
            {
                // Create user directories if they do not exist
                while ($userModel->next())
                {
                    $this->maintainBackendUsersHomeDirectory->createBackendUsersHomeDirectory($userModel->current());
                }
            }

            // Scan for unused/old directories and remove them
            $this->maintainBackendUsersHomeDirectory->removeUnusedBackendUsersHomeDirectories();
        }
    }

}
