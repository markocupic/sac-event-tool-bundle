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

use Contao\DataContainer;
use Contao\DC_Table;

/*
 * Submitted event feedbacks. One row per registration (uuid = tl_calendar_events_member.uuid).
 * The columns are the field names of the feedback form (tl_form_field.name).
 *
 * See docs/features/event-feedback.md
 */
$GLOBALS['TL_DCA']['tl_event_feedback'] = [
	'config'   => [
		'dataContainer'    => DC_Table::class,
		'enableVersioning' => true,
		'sql'              => [
			'keys' => [
				'id'   => 'primary',
				'uuid' => 'unique',
			],
		],
	],
	'list'     => [
		'sorting'           => [
			'mode'        => DataContainer::MODE_SORTABLE,
			'fields'      => ['dateAdded'],
			'flag'        => DataContainer::SORT_DAY_DESC,
			'panelLayout' => 'filter;sort,search,limit',
		],
		'label'             => [
			'fields'      => ['dateAdded', 'pid', 'uuid'],
			'showColumns' => true,
		],
		'global_operations' => [
			'all',
		],
	],
	'palettes' => [
		'default' => '{first_legend},pid,form,uuid,dateAdded;{survey_legend},learningEffectIndex,learningGoalsAchievedIndex,theoryAndPracticeBalanceIndex,recommendationIndex,safetyFeelingIndex,durationIndex,improvementOpportunity,highlights,comments,wildcard',
	],
	'fields'   => [
		'id' => [
			'sql' => 'int(10) unsigned NOT NULL auto_increment',
		],
		'pid' => [
			'filter'     => true,
			'foreignKey' => 'tl_calendar_events.title',
			'eval'       => ['rgxp' => 'natural', 'tl_class' => 'w50'],
			'relation'   => ['type' => 'belongsTo', 'load' => 'lazy'],
			'sql'        => 'int(10) unsigned NOT NULL default 0',
		],
		'tstamp' => [
			'sql' => "int(10) unsigned NOT NULL default '0'",
		],
		'dateAdded' => [
			'exclude'   => true,
			'inputType' => 'text',
			'sorting'   => true,
			'flag'      => DataContainer::SORT_DAY_DESC,
			'eval'      => ['rgxp' => 'datim', 'datepicker' => true, 'tl_class' => 'w50 wizard'],
			'sql'       => 'int(11) unsigned NOT NULL default 0',
		],
		'form' => [
			'exclude'    => true,
			'inputType'  => 'select',
			'foreignKey' => 'tl_form.title',
			'eval'       => ['includeBlankOption' => true, 'tl_class' => 'w50'],
			'relation'   => ['type' => 'belongsTo', 'load' => 'lazy'],
			'sql'        => 'int(10) unsigned NOT NULL default 0',
		],
		'uuid' => [
			'exclude'   => true,
			'inputType' => 'text',
			'search'    => true,
			'eval'      => ['mandatory' => true, 'unique' => true, 'readonly' => true, 'tl_class' => 'w50'],
			'sql'       => 'varchar(64) BINARY NULL',
		],
		'learningEffectIndex' => [
			'exclude'   => true,
			'inputType' => 'select',
			'filter'    => true,
			'options'   => ['1', '2', '3', '4'],
			'reference' => &$GLOBALS['TL_LANG']['tl_event_feedback']['learningEffectIndexReference'],
			'eval'      => ['readonly' => true, 'includeBlankOption' => true, 'tl_class' => 'w50'],
			'sql'       => "char(1) NOT NULL default ''",
		],
		'learningGoalsAchievedIndex' => [
			'exclude'   => true,
			'inputType' => 'select',
			'filter'    => true,
			'options'   => ['1', '2', '3', '4'],
			'reference' => &$GLOBALS['TL_LANG']['tl_event_feedback']['learningEffectIndexReference'],
			'eval'      => ['readonly' => true, 'includeBlankOption' => true, 'tl_class' => 'w50'],
			'sql'       => "char(1) NOT NULL default ''",
		],
		'theoryAndPracticeBalanceIndex' => [
			'exclude'   => true,
			'inputType' => 'select',
			'filter'    => true,
			'options'   => ['1', '2', '3', '4'],
			'reference' => &$GLOBALS['TL_LANG']['tl_event_feedback']['learningEffectIndexReference'],
			'eval'      => ['readonly' => true, 'includeBlankOption' => true, 'tl_class' => 'w50'],
			'sql'       => "char(1) NOT NULL default ''",
		],
		'recommendationIndex' => [
			'exclude'   => true,
			'inputType' => 'select',
			'filter'    => true,
			'options'   => ['1', '2', '3', '4'],
			'reference' => &$GLOBALS['TL_LANG']['tl_event_feedback']['learningEffectIndexReference'],
			'eval'      => ['readonly' => true, 'includeBlankOption' => true, 'tl_class' => 'w50'],
			'sql'       => "char(1) NOT NULL default ''",
		],
		'safetyFeelingIndex' => [
			'exclude'   => true,
			'inputType' => 'select',
			'filter'    => true,
			'options'   => ['1', '2', '3', '4'],
			'reference' => &$GLOBALS['TL_LANG']['tl_event_feedback']['learningEffectIndexReference'],
			'eval'      => ['readonly' => true, 'includeBlankOption' => true, 'tl_class' => 'w50'],
			'sql'       => "char(1) NOT NULL default ''",
		],
		'durationIndex' => [
			'exclude'   => true,
			'inputType' => 'select',
			'filter'    => true,
			'options'   => ['1', '2', '3', '4', '5'],
			'reference' => &$GLOBALS['TL_LANG']['tl_event_feedback']['durationIndexReference'],
			'eval'      => ['readonly' => true, 'includeBlankOption' => true, 'tl_class' => 'w50'],
			'sql'       => "char(1) NOT NULL default ''",
		],
		'improvementOpportunity' => [
			'exclude'   => true,
			'inputType' => 'textarea',
			'search'    => true,
			'eval'      => ['readonly' => true, 'tl_class' => 'clr'],
			'sql'       => 'text NULL',
		],
		'highlights' => [
			'exclude'   => true,
			'inputType' => 'textarea',
			'search'    => true,
			'eval'      => ['readonly' => true, 'tl_class' => 'clr'],
			'sql'       => 'text NULL',
		],
		'wildcard' => [
			'exclude'   => true,
			'inputType' => 'textarea',
			'search'    => true,
			'eval'      => ['tl_class' => 'clr'],
			'sql'       => 'text NULL',
		],
		'comments' => [
			'exclude'   => true,
			'inputType' => 'textarea',
			'search'    => true,
			'eval'      => ['readonly' => true, 'tl_class' => 'clr'],
			'sql'       => 'text NULL',
		],
	],
];
