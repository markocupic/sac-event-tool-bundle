<?php

/**
 * SAC Event Tool Web Plugin for Contao
 * Copyright (c) 2008-2017 Marko Cupic
 * @package sac-event-tool-bundle
 * @author Marko Cupic m.cupic@gmx.ch, 2017-2018
 * @link https://sac-kurse.kletterkader.com
 */

/**
 * @param $userId
 * @param $groupId
 * @param string $mode
 * @return bool
 */
function isInGroup($userId, $groupId, $mode = 'BE')
{
    $mode = strtoupper($mode);
    if ($mode != 'BE' && $mode != 'FE')
    {
        return false;
    }
    if ($mode === 'BE')
    {
        $objUser = \UserModel::findByPk($userId);
    }
    else
    {
        $objUser = \MemberModel::findByPk($userId);
    }
    if ($objUser !== null)
    {
        $arrGroups = StringUtil::deserialize($objUser->groups, true);
        if (in_array($groupId, $arrGroups))
        {
            return true;
        }
    }

    return false;
}

/**
 * @param $userId
 * @param string $mode
 * @return bool|string
 */
function getAvatar($userId, $mode = 'BE')
{

    // Get root dir
    $rootDir = \System::getContainer()->getParameter('kernel.project_dir');

    $mode = strtoupper($mode);
    if ($mode != 'BE' && $mode != 'FE')
    {
        return false;
    }
    if ($mode === 'BE')
    {
        $objUser = \UserModel::findByPk($userId);
    }
    else
    {
        $objUser = \MemberModel::findByPk($userId);
    }

    if ($mode === 'FE')
    {
        if ($objUser !== null)
        {
            if (is_file($rootDir . '/' . \Config::get('SAC_EVT_FE_USER_AVATAR_DIRECTORY') . '/avatar-' . $objUser->id . '.jpeg'))
            {
                return \Config::get('SAC_EVT_FE_USER_AVATAR_DIRECTORY') . '/avatar-' . $objUser->id . '.jpeg';
            }
            else
            {
                if ($objUser->gender === 'female')
                {
                    return \Config::get('SAC_EVT_AVATAR_FEMALE');
                }
                return \Config::get('SAC_EVT_AVATAR_MALE');
            }
        }
        else
        {
            return '';
        }
    }


    if ($mode === 'BE')
    {
        if ($objUser !== null)
        {
            if ($objUser->avatarSRC != '')
            {
                $objFile = \FilesModel::findByUuid($objUser->avatarSRC);
                if ($objFile !== null)
                {
                    if (\Validator::isUuid($objUser->avatarSRC))
                    {
                        if (is_file($rootDir . '/' . $objFile->path))
                        {
                            return $objFile->path;
                        }
                    }
                }
            }
        }
        else
        {
            return '';
        }

        if ($objUser->gender === 'female')
        {
            if (is_file($rootDir . '/' . \Config::get('SAC_EVT_AVATAR_FEMALE')))
            {
                return \Config::get('SAC_EVT_AVATAR_FEMALE');
            }

        }
        else
        {
            if (is_file($rootDir . '/' . \Config::get('SAC_EVT_AVATAR_MALE')))
            {
                return \Config::get('SAC_EVT_AVATAR_MALE');
            }
        }
    }

    return '';
}

/**
 * @param $userId
 * @param string $size
 * @param string $mode
 * @return null|string
 */
function generateAvatar($userId, $size, $mode = 'BE')
{
    $path = getAvatar($userId, $mode);
    return \Picture::create($path, $size)->getTemplateData();
}

/**
 * Check if user has a certain role
 * @param $userId
 * @param $strRole
 * @return bool
 */
function userHasRole($userId, $strRole)
{
    $objUser = \UserModel::findByPk($userId);
    if ($objUser !== null)
    {
        $arrRole = \StringUtil::deserialize($objUser->role, true);
        if (in_array($strRole, $arrRole))
        {
            return true;
        }
    }
    return false;
}




