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

namespace Markocupic\SacEventToolBundle\Model;

use Contao\Database;
use Contao\Model;
use Contao\Model\Collection;

class SacSectionModel extends Model
{
	/**
	 * Table name.
	 *
	 * @var string
	 */
	protected static $strTable = 'tl_sac_section';

	/**
	 * Find multiple sections by their section ids.
	 */
	public static function findMultipleBySectionIds($arrSectionsIds, array $arrOptions = []): Collection|array|null
	{
		if (empty($arrSectionsIds) || !\is_array($arrSectionsIds)) {
			return null;
		}

		$t = static::$strTable;

		if (!isset($arrOptions['order'])) {
			$arrOptions['order'] = Database::getInstance()->findInSet("$t.sectionId", $arrSectionsIds);
		}

		return static::findBy(["$t.sectionId IN(".implode(',', array_fill(0, \count($arrSectionsIds), '?')).')'], $arrSectionsIds, $arrOptions);
	}
}
