<?php

/**
 * SAC Event Tool Web Plugin for Contao
 * Copyright (c) 2008-2019 Marko Cupic
 * @package sac-event-tool-bundle
 * @author Marko Cupic m.cupic@gmx.ch, 2017-2019
 * @link https://github.com/markocupic/sac-event-tool-bundle
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
            $objFiles = FilesModel::findByUuid($objUser->avatar);
            if ($objFiles !== null)
            {
                if (is_file($rootDir . '/' . $objFiles->path))
                {
                    return $objFiles->path;
                }
            }

            if ($objUser->gender === 'female')
            {
                return \Config::get('SAC_EVT_AVATAR_FEMALE');
            }
            else
            {
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

/**
 * @param string $strNumber
 * @return mixed|string
 */
function beautifyPhoneNumber($strNumber = '')
{
    if ($strNumber != '')
    {
        $strNumber = str_replace(' ', '', $strNumber);
        $strNumber = str_replace('+41', '', $strNumber);
        if (substr($strNumber, 0, 1) != '0')
        {
            $strNumber = '0' . $strNumber;
        }
        if (strlen($strNumber) === 10)
        {
            $strNumber = substr($strNumber, 0, 3) . ' ' .
                substr($strNumber, 3, 3) . ' ' .
                substr($strNumber, 6, 2) . ' ' .
                substr($strNumber, 8, 2);
        }
    }

    return $strNumber;
}




