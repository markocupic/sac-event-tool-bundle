<?php

/*
 * This file is part of SAC Event Tool Bundle.
 *
 * (c) Marko Cupic 2021 <m.cupic@gmx.ch>
 * @license MIT
 * For the full copyright and license information,
 * please view the LICENSE file that was distributed with this source code.
 * @link https://github.com/markocupic/sac-event-tool-bundle
 */

use Contao\Config;
use Contao\FilesModel;
use Contao\MemberModel;
use Contao\Picture;
use Contao\StringUtil;
use Contao\System;
use Contao\UserModel;
use Contao\Validator;

/**
 * @param $userId
 * @param $groupId
 * @param  string $mode
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
		$objUser = UserModel::findByPk($userId);
	}
	else
	{
		$objUser = MemberModel::findByPk($userId);
	}

	if ($objUser !== null)
	{
		$arrGroups = StringUtil::deserialize($objUser->groups, true);

		if (in_array($groupId, $arrGroups, false))
		{
			return true;
		}
	}

	return false;
}

/**
 * @param $userId
 * @param  string      $mode
 * @return bool|string
 */
function getAvatar($userId, $mode = 'BE')
{
	// Get root dir
	$rootDir = System::getContainer()->getParameter('kernel.project_dir');

	$mode = strtoupper($mode);

	if ($mode != 'BE' && $mode != 'FE')
	{
		return false;
	}

	if ($mode === 'BE')
	{
		$objUser = UserModel::findByPk($userId);
	}
	else
	{
		$objUser = MemberModel::findByPk($userId);
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
				return Config::get('SAC_EVT_AVATAR_FEMALE');
			}

			return Config::get('SAC_EVT_AVATAR_MALE');
		}

		return '';
	}

	if ($mode === 'BE')
	{
		if ($objUser !== null)
		{
			if ($objUser->avatarSRC != '')
			{
				$objFile = FilesModel::findByUuid($objUser->avatarSRC);

				if ($objFile !== null)
				{
					if (Validator::isUuid($objUser->avatarSRC))
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
			if (is_file($rootDir . '/' . Config::get('SAC_EVT_AVATAR_FEMALE')))
			{
				return Config::get('SAC_EVT_AVATAR_FEMALE');
			}
		}
		else
		{
			if (is_file($rootDir . '/' . Config::get('SAC_EVT_AVATAR_MALE')))
			{
				return Config::get('SAC_EVT_AVATAR_MALE');
			}
		}
	}

	return '';
}

/**
 * @param $userId
 * @param  string      $size
 * @param  string      $mode
 * @return string|null
 */
function generateAvatar($userId, $size, $mode = 'BE')
{
	$path = getAvatar($userId, $mode);

	return Picture::create($path, $size)->getTemplateData();
}

/**
 * Check if user has a certain role
 * @param $userId
 * @param $strRole
 * @return bool
 */
function userHasRole($userId, $strRole)
{
	$objUser = UserModel::findByPk($userId);

	if ($objUser !== null)
	{
		$arrRole = StringUtil::deserialize($objUser->role, true);

		if (in_array($strRole, $arrRole, false))
		{
			return true;
		}
	}

	return false;
}

/**
 * @param  string       $strNumber
 * @return mixed|string
 */
function beautifyPhoneNumber($strNumber = '')
{
	if ($strNumber != '')
	{
		// Remove whitespaces
		$strNumber = preg_replace('/\s+/', '', $strNumber);
		// Remove country code
		$strNumber = str_replace('+41', '', $strNumber);
		$strNumber = str_replace('0041', '', $strNumber);

		// Add a leading zero, if there is no f.ex 41
		if (substr($strNumber, 0, 1) != '0' && strlen($strNumber) === 9)
		{
			$strNumber = '0' . $strNumber;
		}

		// Search for 0799871234 and replace it with 079 987 12 34
		$pattern = '/^([0]{1})([0-9]{2})([0-9]{3})([0-9]{2})([0-9]{2})$/';

		if (preg_match($pattern, $strNumber))
		{
			$pattern = '/^([0]{1})([0-9]{2})([0-9]{3})([0-9]{2})([0-9]{2})$/';
			$replace = '$1$2 $3 $4 $5';
			$strNumber = preg_replace($pattern, $replace, $strNumber);
		}
	}

	return $strNumber;
}
