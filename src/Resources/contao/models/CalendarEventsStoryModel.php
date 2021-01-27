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

namespace Contao;

class CalendarEventsStoryModel extends Model
{
	/**
	 * Table name
	 * @var string
	 */
	protected static $strTable = 'tl_calendar_events_story';

	/**
	 * @param $sacMemberId
	 * @param $eventId
	 * @return static
	 */
	public static function findOneBySacMemberIdAndEventId($sacMemberId, $eventId)
	{
		return self::findOneBy(array('tl_calendar_events_story.sacMemberId=? AND tl_calendar_events_story.eventId=?'), array($sacMemberId, $eventId));
	}
}
