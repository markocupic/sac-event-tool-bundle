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

$GLOBALS['TL_DCA']['tl_sac_section'] = [
	'config'   => [
		'dataContainer'    => DC_Table::class,
		'doNotCopyRecords' => true,
		'enableVersioning' => true,
		'switchToEdit'     => true,
		'sql'              => [
			'keys' => [
				'id'        => 'primary',
				'sectionId' => 'unique',
			],
		],
	],
	'list'     => [
		'sorting'           => [
			'mode'        => DataContainer::MODE_SORTABLE,
			'fields'      => ['sectionId ASC'],
			'flag'        => DataContainer::SORT_INITIAL_LETTER_ASC,
			'panelLayout' => 'filter;sort,search,limit',
		],
		'label'             => [
			'fields'      => ['name'],
			'showColumns' => true,
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
			'delete' => [
				'href'       => 'act=delete',
				'icon'       => 'delete.svg',
				'attributes' => 'onclick="if(!confirm(\''.($GLOBALS['TL_LANG']['MSC']['deleteConfirm'] ?? null).'\'))return false;Backend.getScrollOffset()"',
			],
			'show'   => [
				'href' => 'act=show',
				'icon' => 'show.svg',
			],
		],
	],
	'palettes' => [
		'default' => '{title_legend},sectionId,name',
	],
	'fields'   => [
		'id'        => [
			'sql' => 'int(10) unsigned NOT NULL auto_increment',
		],
		'tstamp'    => [
			'sql' => "int(10) unsigned NOT NULL default '0'",
		],
		'sectionId' => [
			'exclude'   => true,
			'search'    => true,
			'sorting'   => true,
			'inputType' => 'text',
			'eval'      => ['mandatory' => true, 'unique' => true, 'rgxp' => 'natural', 'maxlength' => 4, 'minlength' => 4, 'tl_class' => 'w50'],
			'sql'       => "varchar(4) NOT NULL default ''",
		],
		'name'      => [
			'exclude'   => true,
			'search'    => true,
			'sorting'   => true,
			'inputType' => 'text',
			'eval'      => ['mandatory' => true, 'maxlength' => 255, 'tl_class' => 'w50'],
			'sql'       => "varchar(255) NOT NULL default ''",
		],
	],
];
