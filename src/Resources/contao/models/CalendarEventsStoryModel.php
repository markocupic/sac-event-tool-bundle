<?php

/**
 * SAC Event Tool Web Plugin for Contao
 * Copyright (c) 2008-2017 Marko Cupic
 * @package sac-event-tool-bundle
 * @author Marko Cupic m.cupic@gmx.ch, 2017-2018
 * @link https://sac-kurse.kletterkader.com
 */

namespace Contao;


class CalendarEventsStoryModel extends \Model
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
