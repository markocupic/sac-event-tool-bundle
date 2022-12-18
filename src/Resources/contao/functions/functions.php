<?php

declare(strict_types=1);

/*
 * This file is part of SAC Event Tool Bundle.
 *
 * (c) Marko Cupic 2022 <m.cupic@gmx.ch>
 * @license GPL-3.0-or-later
 * For the full copyright and license information,
 * please view the LICENSE file that was distributed with this source code.
 * @link https://github.com/markocupic/sac-event-tool-bundle
 */

use Contao\MemberModel;
use Contao\StringUtil;
use Contao\UserModel;

/**
 * @param $userId
 * @param $groupId
 * @param string $mode
 *
 * @return bool
 */
function isInGroup($userId, $groupId, $mode = 'BE')
{
    $mode = strtoupper($mode);

    if ('BE' !== $mode && 'FE' !== $mode) {
        return false;
    }

    if ('BE' === $mode) {
        $objUser = UserModel::findByPk($userId);
    } else {
        $objUser = MemberModel::findByPk($userId);
    }

    if (null !== $objUser) {
        $arrGroups = StringUtil::deserialize($objUser->groups, true);

        if (in_array($groupId, $arrGroups, false)) {
            return true;
        }
    }

    return false;
}

/**
 * Check if user has a certain role.
 *
 * @param $userId
 * @param $strRole
 *
 * @return bool
 */
function userHasRole($userId, $strRole)
{
    $objUser = UserModel::findByPk($userId);

    if (null !== $objUser) {
        $arrRole = StringUtil::deserialize($objUser->role, true);

        if (in_array($strRole, $arrRole, false)) {
            return true;
        }
    }

    return false;
}

/**
 * @param string $strNumber
 *
 * @return mixed|string
 */
function beautifyPhoneNumber($strNumber = '')
{
    if ('' !== $strNumber) {
        // Remove whitespaces
        $strNumber = preg_replace('/\s+/', '', $strNumber);
        // Remove country code
        $strNumber = str_replace('+41', '', $strNumber);
        $strNumber = str_replace('0041', '', $strNumber);

        // Add a leading zero, if there is no f.ex 41
        if ('0' !== substr($strNumber, 0, 1) && 9 === strlen($strNumber)) {
            $strNumber = '0'.$strNumber;
        }

        // Search for 0799871234 and replace it with 079 987 12 34
        $pattern = '/^([0]{1})([0-9]{2})([0-9]{3})([0-9]{2})([0-9]{2})$/';

        if (preg_match($pattern, $strNumber)) {
            $pattern = '/^([0]{1})([0-9]{2})([0-9]{3})([0-9]{2})([0-9]{2})$/';
            $replace = '$1$2 $3 $4 $5';
            $strNumber = preg_replace($pattern, $replace, $strNumber);
        }
    }

    return $strNumber;
}
