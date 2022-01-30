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

namespace Markocupic\SacEventToolBundle\Config;

class Log
{
    /**
     * MEMBER_DATABASE_SYNC: Log type for a successful sync.
     */
    public const MEMBER_DATABASE_SYNC_SUCCESS = 'MEMBER_DB_SYNC_SUCCESS';

    /**
     * MEMBER_DATABASE_SYNC: Log type if there has been a db transaction error.
     */
    public const MEMBER_DATABASE_SYNC_TRANSACTION_ERROR = 'MEMBER_DB_SYNC_TRANSACTION_ERROR';

    /**
     * MEMBER_DATABASE_SYNC: Log type when a new member has been inserted.
     */
    public const MEMBER_DATABASE_SYNC_INSERT_NEW_MEMBER = 'MEMBER_DB_SYNC_INSERT_NEW';

    /**
     * MEMBER_DATABASE_SYNC: Log type when a new member has been updated.
     */
    public const MEMBER_DATABASE_SYNC_UPDATE_NEW_MEMBER = 'MEMBER_DB_SYNC_UPDATE';

    /**
     * MEMBER_DATABASE_SYNC: Log type when a new member has been disabled.
     */
    public const MEMBER_DATABASE_SYNC_DISABLE_MEMBER = 'MEMBER_DB_SYNC_DISABLE_MEMBER';

    /**
     * MEMBER_WITH_USER_SYNC: Log type when tl_member has been synced with tl_user.
     */
    public const MEMBER_WITH_USER_SYNC_SUCCESS = 'MEMBER_WITH_USER_SYNC_SUCCESS';

    /**
     * EVENT: Log type if a user has unsubscribed.
     */
    public const EVENT_UNSUBSCRIPTION = 'EVENT_UNSUBSCRIPTION';

    /**
     * EVENT: Log type if a user has subscribed.
     */
    public const EVENT_SUBSCRIPTION = 'EVENT_SUBSCRIPTION';

    /**
     * EVENT: Log type if a there has been an error during the subscription process.
     */
    public const EVENT_SUBSCRIPTION_ERROR = 'EVENT_SUBSCRIPTION_ERROR';

    /**
     * DOWNLOAD: Log type if a user has downloaded its certificate of attendance.
     */
    public const DOWNLOAD_CERTIFICATE_OF_ATTENDANCE = 'DOWNLOAD_CERTIFICATE_OF_ATTENDANCE';

    /**
     * DOWNLOAD: Log type if a user has downloaded the workshop booklet.
     */
    public const DOWNLOAD_WORKSHOP_BOOKLET = 'DOWNLOAD_WORKSHOP_BOOKLET';
}
