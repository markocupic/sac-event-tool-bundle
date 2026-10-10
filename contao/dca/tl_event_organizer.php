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

$GLOBALS['TL_DCA']['tl_event_organizer'] = [
	'config'      => [
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
	'list'        => [
		'sorting'           => [
			'mode'        => DataContainer::MODE_SORTABLE,
			'fields'      => ['sorting ASC'],
			'flag'        => DataContainer::SORT_INITIAL_LETTER_ASC,
			'panelLayout' => 'filter;sort,search,limit',
		],
		'label'             => [
			'fields'      => ['title'],
			'showColumns' => true,
		],
		'global_operations' => [
			'all',
		],
	],
	'palettes'    => [
		'__selector__' => ['addLogo', 'enableRapportNotification'],
		'default'      => '
		{title_legend},title,titlePrint,belongsToOrganization,sorting;
		{eventList_legend},ignoreFilterInEventList,hideInEventFilter;
		{event_rapport_legend},enableRapportNotification;
		{event_regulation_legend},codeOfConductSRC,tourRegulationExtract,tourRegulationSRC,courseRegulationExtract,courseRegulationSRC,avbSvbUrl;
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
			'sql' => "int(10) unsigned NOT NULL default 0",
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
			'eval'      => ['mandatory' => true, 'maxlength' => 255, 'rgxp' => 'digit'],
			'sql'       => "int(10) unsigned NOT NULL default 0",
		],
		'belongsToOrganization'              => [
			'exclude'   => true,
			'filter'    => true,
			'inputType' => 'select',
			'eval'      => ['chosen' => true, 'multiple' => true, 'tl_class' => 'clr m12'],
			'sql'       => 'blob NULL',
		],
		'ignoreFilterInEventList'            => [
			'exclude'   => true,
			'filter'    => true,
			'inputType' => 'checkbox',
			'eval'      => ['tl_class' => 'clr m12'],
			'sql'       => ['type' => 'boolean', 'default' => false],
		],
		'hideInEventFilter'                  => [
			'exclude'   => true,
			'inputType' => 'checkbox',
			'eval'      => ['tl_class' => 'clr m12'],
			'sql'       => ['type' => 'boolean', 'default' => false],
		],
		'enableRapportNotification'          => [
			'exclude'   => true,
			'inputType' => 'checkbox',
			'eval'      => ['submitOnChange' => true],
			'sql'       => ['type' => 'boolean', 'default' => false],
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
			'eval'      => ['helpwizard' => true, 'mandatory' => true, 'rte' => 'tinyMCE', 'tl_class' => 'clr m12'],
			'sql'       => 'text NULL',
		],
		'courseRegulationExtract'            => [
			'exclude'   => true,
			'inputType' => 'textarea',
			'eval'      => ['helpwizard' => true, 'mandatory' => true, 'rte' => 'tinyMCE', 'tl_class' => 'clr m12'],
			'sql'       => 'text NULL',
		],
		'tourRegulationSRC'                  => [
			'exclude'   => true,
			'inputType' => 'fileTree',
			'eval'      => ['fieldType' => 'radio', 'filesOnly' => true, 'mandatory' => false, 'tl_class' => 'clr'],
			'sql'       => 'binary(16) NULL',
		],
		'courseRegulationSRC'                => [
			'exclude'   => true,
			'inputType' => 'fileTree',
			'eval'      => ['fieldType' => 'radio', 'filesOnly' => true, 'mandatory' => false, 'tl_class' => 'clr'],
			'sql'       => 'binary(16) NULL',
		],
		'codeOfConductSRC'                   => [
			'exclude'   => true,
			'inputType' => 'fileTree',
			'eval'      => ['fieldType' => 'radio', 'filesOnly' => true, 'mandatory' => false, 'tl_class' => 'clr'],
			'sql'       => 'binary(16) NULL',
		],
		'avbSvbUrl'                          => [
			'exclude'   => true,
			'search'    => true,
			'inputType' => 'text',
			'eval'      => ['decodeEntities' => true, 'mandatory' => true, 'maxlength' => 1022, 'rgxp' => 'url', 'tl_class' => 'w50'],
			'sql'       => "varchar(1022) NOT NULL default ''"
		],
		'notifyWebmasterOnNewEventBlog'      => [
			'exclude'   => true,
			'filter'    => true,
			'inputType' => 'select',
			'eval'      => ['chosen' => true, 'includeBlankOption' => true, 'multiple' => true, 'tl_class' => 'clr'],
			'sql'       => 'blob NULL',
		],
		'emergencyConcept'                   => [
			'exclude'   => true,
			'inputType' => 'textarea',
			'eval'      => ['mandatory' => true, 'tl_class' => 'clr m12'],
			'sql'       => 'text NULL',
		],
		'addLogo'                            => [
			'exclude'   => true,
			'inputType' => 'checkbox',
			'eval'      => ['submitOnChange' => true],
			'sql'       => ['type' => 'boolean', 'default' => false],
		],
		'singleSRC'                          => [
			'exclude'   => true,
			'inputType' => 'fileTree',
			'eval'      => ['extensions' => '%contao.image.valid_extensions%', 'fieldType' => 'radio', 'filesOnly' => true, 'mandatory' => true, 'tl_class' => 'clr'],
			'sql'       => 'binary(16) NULL',
		],
		'annualProgramShowHeadline'          => [
			'exclude'   => true,
			'inputType' => 'checkbox',
			'eval'      => ['tl_class' => 'w50'],
			'sql'       => ['type' => 'boolean', 'default' => false],
		],
		'annualProgramShowTeaser'            => [
			'exclude'   => true,
			'inputType' => 'checkbox',
			'eval'      => ['tl_class' => 'w50'],
			'sql'       => ['type' => 'boolean', 'default' => false],
		],
		'annualProgramShowDetails'           => [
			'exclude'   => true,
			'inputType' => 'checkbox',
			'eval'      => ['tl_class' => 'w50'],
			'sql'       => ['type' => 'boolean', 'default' => false],
		],
	],
];
