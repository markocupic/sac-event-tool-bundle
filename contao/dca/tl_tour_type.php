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
				'id' => 'primary',
			],
		],
	],
	'list'     => [
		'sorting'           => [
			'mode'        => DataContainer::MODE_TREE,
			'fields'      => ['sorting'],
			'flag'        => DataContainer::SORT_INITIAL_LETTER_ASC,
			'panelLayout' => 'filter;search,limit',
		],
		'label'             => [
			'fields' => ['shortcut', 'title'],
			'format' => '%s %s',
		],
		'global_operations' => [
			'all' => [
				'href'       => 'act=select',
				'class'      => 'header_edit_all',
				'attributes' => 'onclick="Backend.getScrollOffset();"',
			],
		],
		'operations'        => [
			'edit'   => [
				'href' => 'act=edit',
				'icon' => 'edit.svg',
			],
			'copy'   => [
				'href' => 'act=copy',
				'icon' => 'copy.svg',
			],
			'cut'    => [
				'href'       => 'act=paste&mode=cut',
				'icon'       => 'cut.svg',
				'attributes' => 'onclick="Backend.getScrollOffset();"',
			],
			'delete' => [
				'href'       => 'act=delete',
				'icon'       => 'delete.svg',
				'attributes' => 'onclick="if(!confirm(\''.($GLOBALS['TL_LANG']['MSC']['deleteConfirm'] ?? null).'\'))return false;Backend.getScrollOffset()"',
			],
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
			'sql' => "int(10) unsigned NOT NULL default '0'",
		],
		'pid'         => [
			'sql' => "int(10) unsigned NOT NULL default '0'",
		],
		'sorting'     => [
			'sql' => "int(10) unsigned NOT NULL default '0'",
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
