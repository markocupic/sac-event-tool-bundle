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
	 * EVENT: Log type when a user has subscribed.
	 */
	public const EVENT_SUBSCRIPTION = 'EVENT_SUBSCRIPTION';

	/**
	 * EVENT: Log type if a there has been an error during the subscription process.
	 */
	public const EVENT_SUBSCRIPTION_ERROR = 'EVENT_SUBSCRIPTION_ERROR';

	/**
	 * EVENT: Log type when an event registration has been confirmed by a tour guide.
	 */
	public const EVENT_PARTICIPATION_CONFIRM = 'EVENT_PARTICIPATION_CONFIRM';

	/**
	 * EVENT: Log type when an event registration has been unconfirmed by a tour guide.
	 */
	public const EVENT_PARTICIPATION_UNCONFIRM = 'EVENT_PARTICIPATION_UNCONFIRM';

	/**
	 * DOWNLOAD: Log type when a user has downloaded its certificate of attendance.
	 */
	public const DOWNLOAD_CERTIFICATE_OF_ATTENDANCE = 'DOWNLOAD_CERTIFICATE_OF_ATTENDANCE';

	/**
	 * DOWNLOAD: Log type when a user has downloaded the workshop booklet.
	 */
	public const DOWNLOAD_WORKSHOP_BOOKLET = 'DOWNLOAD_WORKSHOP_BOOKLET';

	/**
	 * MEMBER_DASHBOARD_UPDATE_PROFILE: Log type when a frontend user has updated its profile.
	 */
	public const MEMBER_DASHBOARD_UPDATE_PROFILE = 'MEMBER_DASHBOARD_UPDATE_PROFILE';

	/**
	 * CREATE_USER_HOME_DIRECTORY: Log type when home directory for a backend user has been created.
	 */
	public const CREATE_USER_HOME_DIRECTORY = 'CREATE_USER_HOME_DIRECTORY';

	/**
	 * ANONYMIZE_EVENT_REGISTRATION: Log type when an event registration has been anonymized, because it could not be assigned to a user.
	 */
	public const ANONYMIZE_EVENT_REGISTRATION = 'ANONYMIZE_EVENT_REGISTRATION';

	/**
	 * DISABLE_FRONTEND_USER_LOGIN: Log type when a frontend users login has been disabled.
	 */
	public const DISABLE_FRONTEND_USER_LOGIN = 'DISABLE_FRONTEND_USER_LOGIN';

	/**
	 * DELETE_FRONTEND_USER: Log type when a frontend user has been deleted.
	 */
	public const DELETE_FRONTEND_USER = 'DELETE_FRONTEND_USER';

	/**
	 * DELETE_FRONTEND_USER_AVATAR_DIRECTORY: Log type when a frontend users avatar directory has been deleted.
	 */
	public const DELETE_FRONTEND_USER_AVATAR_DIRECTORY = 'DELETE_FRONTEND_USER_AVATAR_DIRECTORY';
}
