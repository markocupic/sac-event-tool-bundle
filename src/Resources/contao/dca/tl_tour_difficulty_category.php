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

use Markocupic\SacEventToolBundle\Dca\TlTourDifficultyCategory;

$GLOBALS['TL_DCA']['tl_tour_difficulty_category'] = array
(
	'config'   => array
	(
		'dataContainer'    => 'Table',
		'ctable'           => array('tl_tour_difficulty'),
		'doNotCopyRecords' => true,
		'enableVersioning' => true,
		'switchToEdit'     => true,
		'sql'              => array
		(
			'keys' => array
			(
				'id' => 'primary',
			),
		),
	),
	'list'     => array
	(
		'sorting'           => array
		(
			'mode'        => 1,
			'fields'      => array('title ASC'),
			'flag'        => 1,
			'panelLayout' => 'filter;sort,search,limit',
		),
		'label'             => array
		(
			'fields'      => array('title'),
			'showColumns' => true,
		),
		'global_operations' => array
		(
			'all' => array
			(
				'label'      => &$GLOBALS['TL_LANG']['MSC']['all'],
				'href'       => 'act=select',
				'class'      => 'header_edit_all',
				'attributes' => 'onclick="Backend.getScrollOffset();"',
			),
		),
		'operations'        => array
		(
			'edit'       => array
			(
				'label' => &$GLOBALS['TL_LANG']['tl_tour_difficulty_category']['edit'],
				'href'  => 'table=tl_tour_difficulty',
				'icon'  => 'edit.svg',
			),
			'editheader' => array
			(
				'label'           => &$GLOBALS['TL_LANG']['tl_tour_difficulty_category']['editheader'],
				'href'            => 'table=tl_tour_difficulty_category&amp;act=edit',
				'icon'            => 'header.svg',
				'button_callback' => array(TlTourDifficultyCategory::class, 'editHeader'),
			),
			'copy'       => array
			(
				'label' => &$GLOBALS['TL_LANG']['tl_tour_difficulty_category']['copy'],
				'href'  => 'act=copy',
				'icon'  => 'copy.gif',
			),
			'delete'     => array
			(
				'label'      => &$GLOBALS['TL_LANG']['tl_tour_difficulty_category']['delete'],
				'href'       => 'act=delete',
				'icon'       => 'delete.gif',
				'attributes' => 'onclick="if (!confirm(\'' . $GLOBALS['TL_LANG']['MSC']['deleteConfirm'] . '\')) return false; Backend.getScrollOffset();"',
			),
		),
	),
	'palettes' => array
	(
		'default' => 'title',
	),

	'fields' => array
	(
		'id'     => array
		(
			'sql' => "int(10) unsigned NOT NULL auto_increment",
		),
		'tstamp' => array
		(
			'sql' => "int(10) unsigned NOT NULL default '0'",
		),
		'title'  => array
		(
			'label'     => &$GLOBALS['TL_LANG']['tl_tour_difficulty_category']['title'],
			'exclude'   => true,
			'search'    => true,
			'sorting'   => true,
			'filter'    => true,
			'inputType' => 'text',
			'eval'      => array('mandatory' => true),
			'sql'       => "varchar(255) NOT NULL default ''",
		),
	),
);
