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

namespace Markocupic\SacEventToolBundle\Security;

final class SacEventToolPermissions
{

    public const USER_CAN_EDIT_CALENDAR_CONTAINERS = 'contao_user.calendar_containers';

    public const USER_CAN_CREATE_CALENDAR_CONTAINERS = 'contao_user.calendar_containerp.create';

    public const USER_CAN_DELETE_CALENDAR_CONTAINERS = 'contao_user.calendar_containerp.delete';
}
