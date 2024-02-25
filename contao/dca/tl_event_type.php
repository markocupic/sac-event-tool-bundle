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

$GLOBALS['TL_DCA']['tl_event_type'] = [
	'config'   => [
		'dataContainer'    => DC_Table::class,
		'switchToEdit'     => true,
		'enableVersioning' => true,
		'sql'              => [
			'keys' => [
				'id' => 'primary',
			],
		],
	],
	'list'     => [
		'sorting'           => [
			'mode'        => DataContainer::MODE_SORTABLE,
			'fields'      => ['title'],
			'flag'        => DataContainer::SORT_INITIAL_LETTER_ASC,
			'panelLayout' => 'filter;search,limit',
		],
		'label'             => [
			'fields' => ['title'],
			'format' => '%s',
		],
		'global_operations' => [
			'all' => [
				'href'       => 'act=select',
				'class'      => 'header_edit_all',
				'attributes' => 'onclick="Backend.getScrollOffset()" accesskey="e"',
			],
		],
		'operations'        => [
			'edit'   => [
				'href' => 'act=edit',
				'icon' => 'edit.svg',
			],
			'copy'   => [
				'href' => 'act=paste&amp;mode=copy',
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
		'default' => '{title_legend},alias,title;{release_level_legend},levelAccessPermissionPackage;{preview_page_legend},previewPage;',
	],
	'fields'   => [
		'id'                           => [
			'sql' => 'int(10) unsigned NOT NULL auto_increment',
		],
		'tstamp'                       => [
			'sql' => "int(10) unsigned NOT NULL default '0'",
		],
		'alias'                        => [
			'inputType' => 'text',
			'exclude'   => true,
			'search'    => true,
			'flag'      => DataContainer::SORT_INITIAL_LETTER_ASC,
			'eval'      => ['mandatory' => true, 'rgxp' => 'alnum', 'maxlength' => 128, 'tl_class' => 'w50'],
			'sql'       => 'varchar(128) NULL',
		],
		'title'                        => [
			'inputType' => 'text',
			'exclude'   => true,
			'search'    => true,
			'flag'      => DataContainer::SORT_INITIAL_LETTER_ASC,
			'eval'      => ['mandatory' => true, 'maxlength' => 128, 'tl_class' => 'w50'],
			'sql'       => 'varchar(128) NULL',
		],
		'levelAccessPermissionPackage' => [
			'exclude'    => true,
			'inputType'  => 'select',
			'relation'   => ['type' => 'belongsTo', 'load' => 'eager'],
			'foreignKey' => 'tl_event_release_level_policy_package.title',
			'sql'        => "int(10) unsigned NOT NULL default '0'",
			'eval'       => ['includeBlankOption' => true, 'mandatory' => true, 'tl_class' => 'clr'],
		],
		'previewPage'                  => [
			'exclude'    => true,
			'inputType'  => 'pageTree',
			'foreignKey' => 'tl_page.title',
			'eval'       => ['mandatory' => true, 'fieldType' => 'radio', 'tl_class' => 'clr'],
			'sql'        => "int(10) unsigned NOT NULL default '0'",
			'relation'   => ['type' => 'hasOne', 'load' => 'lazy'],
		],
	],
];
