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

class EventType
{
	public const COURSE = 'course';
	public const TOUR = 'tour';
	public const LAST_MINUTE_TOUR = 'lastMinuteTour';
	public const GENERAL_EVENT = 'generalEvent';
	public const ALL = [
		self::COURSE,
		self::TOUR,
		self::LAST_MINUTE_TOUR,
		self::GENERAL_EVENT,
	];
}
