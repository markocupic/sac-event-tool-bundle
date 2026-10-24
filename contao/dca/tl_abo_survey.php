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
use Contao\System;
use Markocupic\SacEventToolBundle\Feature\AboSurvey\AboSurveyAboType;
use Markocupic\SacEventToolBundle\Feature\AboSurvey\DataContainer\AboSurveyTable;

/*
 * Entries of the abo survey (content element "abo_survey").
 * One row per member and year (unique index sacMemberId,year).
 * Entries are created in the frontend only; in the back end module "abo_survey"
 * they can be listed, edited, deleted and exported as CSV (global operation "exportCsv",
 * see Feature\AboSurvey\DataContainer\AboSurveyTable). The export applies the year and abo filter of the list.
 *
 * See docs/features/abo-survey.md
 */
$GLOBALS['TL_DCA']['tl_abo_survey'] = [
	'config'   => [
		'dataContainer'    => DC_Table::class,
		'notCreatable'     => true,
		'notCopyable'      => true,
		'doNotCopyRecords' => true,
		'enableVersioning' => true,
		'sql'              => [
			'keys' => [
				'id'               => 'primary',
				'sacMemberId,year' => 'unique',
				'year'             => 'index',
			],
		],
	],
	'list'     => [
		'sorting'           => [
			'mode'        => DataContainer::MODE_SORTABLE,
			'fields'      => ['year'],
			'flag'        => DataContainer::SORT_DESC,
			'panelLayout' => 'filter;sort,search,limit',
		],
		'label'             => [
			'fields'      => ['year', 'aboType', 'sacMemberId', 'email', 'street', 'postal', 'city', 'dateAdded'],
			'showColumns' => true,
		],
		'global_operations' => [
			'exportCsv' => [
				'href'       => 'action=' . AboSurveyTable::ACTION_EXPORT_CSV . '&key=noref', // The "key" param prevents Contao from saving the url in the referer list
				'class'      => 'header_export_csv',
				'icon'       => System::getContainer()->get('assets.packages')->getUrl('icons/fontawesome/file-excel-regular.svg', 'markocupic_sac_event_tool'),
				'attributes' => 'data-turbo="false"',
			],
			'all',
		],
		'operations'        => [
			'edit',
			'delete',
			'show',
		],
	],
	'palettes' => [
		'default' => '{abo_legend},year,aboType;{member_legend},sacMemberId,email;{address_legend},street,postal,city;{meta_legend},dateAdded',
	],
	'fields'   => [
		'id'          => [
			'sql' => 'int(10) unsigned NOT NULL auto_increment',
		],
		'tstamp'      => [
			'sql' => 'int(10) unsigned NOT NULL default 0',
		],
		'dateAdded'   => [
			'sorting'   => true,
			'flag'      => DataContainer::SORT_DAY_DESC,
			'inputType' => 'text',
			'eval'      => ['rgxp' => 'datim', 'datepicker' => true, 'doNotCopy' => true, 'tl_class' => 'w50 wizard'],
			'sql'       => 'int(10) unsigned NOT NULL default 0',
		],
		// Year the survey is for, e.g. 2027 (tl_content.aboSurveyYear)
		'year'        => [
			'filter'    => true,
			'sorting'   => true,
			'flag'      => DataContainer::SORT_DESC,
			'inputType' => 'text',
			'eval'      => ['mandatory' => true, 'rgxp' => 'natural', 'minlength' => 4, 'maxlength' => 4, 'tl_class' => 'w50'],
			'sql'       => 'smallint(5) unsigned NOT NULL default 0',
		],
		'aboType'     => [
			'filter'    => true,
			'sorting'   => true,
			'inputType' => 'select',
			'options'   => AboSurveyAboType::ALL,
			'reference' => &$GLOBALS['TL_LANG']['tl_abo_survey'],
			'eval'      => ['mandatory' => true, 'includeBlankOption' => true, 'tl_class' => 'w50'],
			'sql'       => "varchar(32) NOT NULL default ''",
		],
		'sacMemberId' => [
			'search'    => true,
			'sorting'   => true,
			'inputType' => 'text',
			'eval'      => ['mandatory' => true, 'rgxp' => 'natural', 'maxlength' => 7, 'tl_class' => 'w50'],
			'sql'       => 'int(10) unsigned NOT NULL default 0',
		],
		'email'       => [
			'search'    => true,
			'sorting'   => true,
			'inputType' => 'text',
			'eval'      => ['rgxp' => 'email', 'maxlength' => 255, 'decodeEntities' => true, 'tl_class' => 'w50'],
			'sql'       => "varchar(255) NOT NULL default ''",
		],
		'street'      => [
			'search'    => true,
			'inputType' => 'text',
			'eval'      => ['maxlength' => 255, 'tl_class' => 'w50'],
			'sql'       => "varchar(255) NOT NULL default ''",
		],
		'postal'      => [
			'search'    => true,
			'sorting'   => true,
			'inputType' => 'text',
			'eval'      => ['maxlength' => 32, 'tl_class' => 'w50'],
			'sql'       => "varchar(32) NOT NULL default ''",
		],
		'city'        => [
			'search'    => true,
			'sorting'   => true,
			'inputType' => 'text',
			'eval'      => ['maxlength' => 255, 'tl_class' => 'w50'],
			'sql'       => "varchar(255) NOT NULL default ''",
		],
	],
];
