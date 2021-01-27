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

class EventOrganizerModel extends Model
{
	/**
	 * Table name
	 * @var string
	 */
	protected static $strTable = 'tl_event_organizer';

	/**
	 * Find organizers by their IDs
	 *
	 * @param array $arrIds     An array of organizer IDs
	 * @param array $arrOptions An optional options array
	 *
	 * @return Collection|EventOrganizerModel[]|EventOrganizerModel|null A collection of models or null if there are no organizers
	 */
	public static function findByIds($arrIds, array $arrOptions = array())
	{
		if (empty($arrIds) || !\is_array($arrIds))
		{
			return null;
		}

		$t = static::$strTable;

		return static::findBy(array("$t.id IN(" . implode(',', array_map('\intval', $arrIds)) . ")"), null, $arrOptions);
	}
}
