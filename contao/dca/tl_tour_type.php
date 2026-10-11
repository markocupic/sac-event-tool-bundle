<?php

declare(strict_types=1);

/*
 * This file is part of SAC Event Tool Bundle.
 *
 * (c) Marko Cupic <m.cupic@gmx.ch>
 * @license GPL-3.0-or-later
 * For the full copyright and license information,
 * please view the LICENSE file that was distributed with this source code.
 * @link https://github.com/markocupic/sac-event-tool-bundle
 */

use Contao\DC_Table;
use Contao\DataContainer;

$GLOBALS['TL_DCA']['tl_tour_type'] = [
	'config'   => [
		'dataContainer'    => DC_Table::class,
		'doNotCopyRecords' => true,
		'enableVersioning' => true,
		'switchToEdit'     => true,
		'sql'              => [
			'keys' => [
				'id'          => 'primary',
				'pid,sorting' => 'index',
			],
		],
	],
	'list'     => [
		// Flat list, manually sortable via the "sorting" field (cut & paste).
		// DC_Table only supports manual sorting in MODE_PARENT and MODE_TREE. Since this table has no parent
		// table, MODE_TREE is used, with all records on the root level (pid = 0). Nesting is prevented by
		// the TourTypeFlatListVoter.
		'sorting'           => [
			'mode'        => DataContainer::MODE_TREE,
			'rootPaste'   => true,
			'panelLayout' => 'filter;search',
		],
		'label'             => [
			'fields' => ['shortcut', 'title'],
			'format' => '%s %s',
		],
		'global_operations' => [
			'all',
		],
		'operations'        => [
			'edit',
			'copy',
			'cut',
			'delete',
			'show',
		],
	],
	'palettes' => [
		'default' => 'shortcut,title,description',
	],
	'fields'   => [
		'id'          => [
			'sql' => 'int(10) unsigned NOT NULL auto_increment',
		],
		'tstamp'      => [
			'sql' => "int(10) unsigned NOT NULL default 0",
		],
		'pid'         => [
			'sql' => "int(10) unsigned NOT NULL default 0",
		],
		'sorting'     => [
			'sql' => "int(10) unsigned NOT NULL default 0",
		],
		'title'       => [
			'exclude'   => true,
			'search'    => true,
			'sorting'   => false,
			'inputType' => 'text',
			'eval'      => ['mandatory' => true, 'maxlength' => 255],
			'sql'       => "varchar(255) NOT NULL default ''",
		],
		'shortcut'    => [
			'exclude'   => true,
			'search'    => true,
			'sorting'   => false,
			'inputType' => 'text',
			'eval'      => ['mandatory' => true, 'maxlength' => 255],
			'sql'       => "varchar(255) NOT NULL default ''",
		],
		'description' => [
			'exclude'   => true,
			'search'    => true,
			'sorting'   => false,
			'inputType' => 'textarea',
			'eval'      => ['mandatory' => false],
			'sql'       => 'text NULL',
		],
	],
];
