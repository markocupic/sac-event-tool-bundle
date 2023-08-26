<?php

declare(strict_types=1);

/*
 * This file is part of SAC Event Tool Bundle.
 *
 * (c) Marko Cupic 2023 <m.cupic@gmx.ch>
 * @license GPL-3.0-or-later
 * For the full copyright and license information,
 * please view the LICENSE file that was distributed with this source code.
 * @link https://github.com/markocupic/sac-event-tool-bundle
 */

use Contao\Config;

$GLOBALS['TL_DCA']['tl_event_organizer'] = [
	'config'      => [
		'dataContainer'    => 'Table',
		'doNotCopyRecords' => true,
		'enableVersioning' => true,
		'switchToEdit'     => true,
		'sql'              => [
			'keys' => [
				'id' => 'primary',
			],
		],
	],
	'list'        => [
		'sorting'           => [
			'mode'        => 2,
			'fields'      => ['sorting ASC'],
			'flag'        => 1,
			'panelLayout' => 'filter;sort,search,limit',
		],
		'label'             => [
			'fields'      => ['title'],
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
				'icon' => 'edit.gif',
			],
			'copy'   => [
				'href' => 'act=copy',
				'icon' => 'copy.gif',
			],
			'delete' => [
				'href'       => 'act=delete',
				'icon'       => 'delete.gif',
				'attributes' => 'onclick="if(!confirm(\''.($GLOBALS['TL_LANG']['MSC']['deleteConfirm'] ?? null).'\'))return false;Backend.getScrollOffset()"',
			],
			'show'   => [
				'href' => 'act=show',
				'icon' => 'show.svg',
			],
		],
	],
	'palettes'    => [
		'__selector__' => ['addLogo', 'enableRapportNotification'],
		'default'      => '
		{title_legend},title,titlePrint,belongsToOrganization,sorting;
		{eventList_legend},ignoreFilterInEventList,hideInEventFilter;
		{event_rapport_legend},enableRapportNotification;
		{event_regulation_legend},tourRegulationExtract,tourRegulationSRC,courseRegulationExtract,courseRegulationSRC;
		{event_blog_legend},notifyWebmasterOnNewEventBlog;
		{emergency_concept_legend},emergencyConcept;
		{logo_legend},addLogo;{annual_program_legend},annualProgramShowHeadline,annualProgramShowTeaser,annualProgramShowDetails
		',
	],
	'subpalettes' => [
		'addLogo'                   => 'singleSRC',
		'enableRapportNotification' => 'eventRapportNotificationRecipients',
	],
	'fields'      => [
		'id'                                 => [
			'sql' => 'int(10) unsigned NOT NULL auto_increment',
		],
		'tstamp'                             => [
			'sql' => "int(10) unsigned NOT NULL default '0'",
		],
		'title'                              => [
			'exclude'   => true,
			'search'    => true,
			'sorting'   => true,
			'inputType' => 'text',
			'eval'      => [
				'mandatory' => true,
				'maxlength' => 255,
			],
			'sql'       => "varchar(255) NOT NULL default ''",
		],
		'titlePrint'                         => [
			'exclude'   => true,
			'search'    => true,
			'sorting'   => true,
			'inputType' => 'text',
			'eval'      => ['mandatory' => true, 'maxlength' => 255],
			'sql'       => "varchar(255) NOT NULL default ''",
		],
		'sorting'                            => [
			'exclude'   => true,
			'search'    => true,
			'sorting'   => true,
			'inputType' => 'text',
			'eval'      => ['rgxp' => 'digit', 'mandatory' => true, 'maxlength' => 255],
			'sql'       => "int(10) unsigned NOT NULL default '0'",
		],
		'belongsToOrganization'              => [
			'exclude'   => true,
			'filter'    => true,
			'inputType' => 'select',
			'eval'      => ['multiple' => true, 'chosen' => true, 'tl_class' => 'clr m12'],
			'sql'       => 'blob NULL',
		],
		'ignoreFilterInEventList'            => [
			'exclude'   => true,
			'filter'    => true,
			'inputType' => 'checkbox',
			'eval'      => ['tl_class' => 'clr m12'],
			'sql'       => "char(1) NOT NULL default ''",
		],
		'hideInEventFilter'                  => [
			'exclude'   => true,
			'inputType' => 'checkbox',
			'eval'      => ['tl_class' => 'clr m12'],
			'sql'       => "char(1) NOT NULL default ''",
		],
		'enableRapportNotification'          => [
			'exclude'   => true,
			'inputType' => 'checkbox',
			'eval'      => ['submitOnChange' => true],
			'sql'       => "char(1) NOT NULL default ''",
		],
		'eventRapportNotificationRecipients' => [
			'exclude'   => true,
			'inputType' => 'text',
			'eval'      => ['mandatory' => true, 'rgxp' => 'emails', 'tl_class' => 'clr'],
			'sql'       => "varchar(255) NOT NULL default ''",
		],
		'tourRegulationExtract'              => [
			'exclude'   => true,
			'inputType' => 'textarea',
			'eval'      => ['tl_class' => 'clr m12', 'rte' => 'tinyMCE', 'helpwizard' => true, 'mandatory' => true],
			'sql'       => 'text NULL',
		],
		'courseRegulationExtract'            => [
			'exclude'   => true,
			'inputType' => 'textarea',
			'eval'      => ['tl_class' => 'clr m12', 'rte' => 'tinyMCE', 'helpwizard' => true, 'mandatory' => true],
			'sql'       => 'text NULL',
		],
		'tourRegulationSRC'                  => [
			'exclude'   => true,
			'inputType' => 'fileTree',
			'eval'      => ['filesOnly' => true, 'fieldType' => 'radio', 'mandatory' => false, 'tl_class' => 'clr'],
			'sql'       => 'binary(16) NULL',
		],
		'courseRegulationSRC'                => [
			'exclude'   => true,
			'inputType' => 'fileTree',
			'eval'      => ['filesOnly' => true, 'fieldType' => 'radio', 'mandatory' => false, 'tl_class' => 'clr'],
			'sql'       => 'binary(16) NULL',
		],
		'notifyWebmasterOnNewEventBlog'      => [
			'exclude'    => true,
			'filter'     => true,
			'inputType'  => 'select',
			'relation'   => ['type' => 'hasOne', 'load' => 'eager'],
			'foreignKey' => 'tl_user.name',
			'eval'       => ['multiple' => true, 'chosen' => true, 'includeBlankOption' => true, 'tl_class' => 'clr'],
			'sql'        => 'blob NULL',
		],
		'emergencyConcept'                   => [
			'exclude'   => true,
			'inputType' => 'textarea',
			'eval'      => ['tl_class' => 'clr m12', 'mandatory' => true],
			'sql'       => 'text NULL',
		],
		'addLogo'                            => [
			'exclude'   => true,
			'inputType' => 'checkbox',
			'eval'      => ['submitOnChange' => true],
			'sql'       => "char(1) NOT NULL default ''",
		],
		'singleSRC'                          => [
			'exclude'   => true,
			'inputType' => 'fileTree',
			'eval'      => ['filesOnly' => true, 'extensions' => Config::get('validImageTypes'), 'fieldType' => 'radio', 'mandatory' => true, 'tl_class' => 'clr'],
			'sql'       => 'binary(16) NULL',
		],
		'annualProgramShowHeadline'          => [
			'exclude'   => true,
			'inputType' => 'checkbox',
			'eval'      => ['tl_class' => 'w50'],
			'sql'       => "char(1) NOT NULL default ''",
		],
		'annualProgramShowTeaser'            => [
			'exclude'   => true,
			'inputType' => 'checkbox',
			'eval'      => ['tl_class' => 'w50'],
			'sql'       => "char(1) NOT NULL default ''",
		],
		'annualProgramShowDetails'           => [
			'exclude'   => true,
			'inputType' => 'checkbox',
			'eval'      => ['tl_class' => 'w50'],
			'sql'       => "char(1) NOT NULL default ''",
		],
	],
];
