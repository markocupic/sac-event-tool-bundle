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

$GLOBALS['TL_DCA']['tl_event_release_level_policy'] = [
	'config'   => [
		'dataContainer'    => 'Table',
		'ptable'           => 'tl_event_release_level_policy_package',
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
			'mode'            => 4,
			'fields'          => ['level'],
			'panelLayout'     => 'filter;search,limit',
			'headerFields'    => ['level', 'title'],
			'disableGrouping' => true,
		],
		'label'             => [
			'fields'      => ['level', 'title'],
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
		],
	],
	'palettes' => [
		'default' => '
		{title_legend},level,title,description;
		{event_grants_legend},allowWriteAccessToAuthor,allowWriteAccessToInstructors,allowDeleteAccessToAuthor,allowDeleteAccessToInstructors,groupEventPerm;
		{event_release_level_grants_legend},allowSwitchingToPrevLevel,allowSwitchingToNextLevel,groupReleaseLevelPerm;
		{event_registrations_grants_legend},allowRegistration',
	],
	'fields'   => [
		'id'                             => [
			'sql' => 'int(10) unsigned NOT NULL auto_increment',
		],
		'pid'                            => [
			'foreignKey' => 'tl_event_release_level_policy_package.title',
			'sql'        => "int(10) unsigned NOT NULL default '0'",
			'relation'   => ['type' => 'belongsTo', 'load' => 'eager'],
		],
		'tstamp'                         => [
			'sql' => "int(10) unsigned NOT NULL default '0'",
		],
		'level'                          => [
			'exclude'   => true,
			'inputType' => 'select',
			'options'   => range(1, 10),
			'eval'      => ['mandatory' => true, 'tl_class' => 'clr'],
			'sql'       => "smallint(2) unsigned NOT NULL default '0'",
		],
		'title'                          => [
			'exclude'   => true,
			'inputType' => 'text',
			'eval'      => ['mandatory' => true, 'maxlength' => 255, 'tl_class' => 'clr'],
			'sql'       => "varchar(255) NOT NULL default ''",
		],
		'description'                    => [
			'exclude'   => true,
			'inputType' => 'textarea',
			'eval'      => ['mandatory' => true, 'tl_class' => 'clr'],
			'sql'       => 'text NULL',
		],
		'allowSwitchingToPrevLevel'      => [
			'exclude'   => true,
			'filter'    => true,
			'inputType' => 'checkbox',
			'sql'       => "char(1) NOT NULL default ''",
		],
		'allowSwitchingToNextLevel'      => [
			'exclude'   => true,
			'filter'    => true,
			'inputType' => 'checkbox',
			'sql'       => "char(1) NOT NULL default ''",
		],
		'allowWriteAccessToAuthor'       => [
			'exclude'   => true,
			'filter'    => true,
			'inputType' => 'checkbox',
			'sql'       => "char(1) NOT NULL default ''",
		],
		'allowWriteAccessToInstructors'  => [
			'exclude'   => true,
			'filter'    => true,
			'inputType' => 'checkbox',
			'sql'       => "char(1) NOT NULL default ''",
		],
		'allowDeleteAccessToAuthor'      => [
			'exclude'   => true,
			'filter'    => true,
			'inputType' => 'checkbox',
			'sql'       => "char(1) NOT NULL default ''",
		],
		'allowDeleteAccessToInstructors' => [
			'exclude'   => true,
			'filter'    => true,
			'inputType' => 'checkbox',
			'sql'       => "char(1) NOT NULL default '1'",
		],
		'groupEventPerm'                 => [
			'exclude'   => true,
			'inputType' => 'multiColumnWizard',
			'eval'      => [
				'mandatory'    => false,
				'columnFields' => [
					'group'       => [
						'label'      => &$GLOBALS['TL_LANG']['tl_event_release_level_policy']['group'],
						'exclude'    => true,
						'inputType'  => 'select',
						'reference'  => &$GLOBALS['TL_LANG']['tl_event_release_level_policy'],
						'relation'   => ['type' => 'hasMany', 'load' => 'eager'],
						'foreignKey' => 'tl_user_group.name',
						'eval'       => ['style' => 'width: 80%', 'mandatory' => false, 'includeBlankOption' => true],
					],
					'permissions' => [
						'label'     => &$GLOBALS['TL_LANG']['tl_event_release_level_policy']['permissions'],
						'exclude'   => true,
						'inputType' => 'select',
						'options'   => ['canWriteEvent', 'canDeleteEvent'],
						'reference' => &$GLOBALS['TL_LANG']['tl_event_release_level_policy'],
						'eval'      => ['style' => 'width: 80%', 'multiple' => true, 'chosen' => true, 'mandatory' => false],
					],
				],
			],
			'sql'       => 'blob NULL',
		],
		'allowRegistration'              => [
			'exclude'   => true,
			'filter'    => true,
			'inputType' => 'checkbox',
			'eval'      => ['isBoolean' => true],
			'sql'       => "char(1) NOT NULL default ''",
		],
		'groupReleaseLevelPerm'          => [
			'exclude'   => true,
			'inputType' => 'multiColumnWizard',
			'eval'      => [
				'tl_class'     => 'mcwColumnCount_4',
				'mandatory'    => false,
				'columnFields' => [
					'group'       => [
						'label'      => &$GLOBALS['TL_LANG']['tl_event_release_level_policy']['group'],
						'exclude'    => true,
						'inputType'  => 'select',
						'reference'  => &$GLOBALS['TL_LANG']['tl_event_release_level_policy'],
						'relation'   => ['type' => 'hasMany', 'load' => 'eager'],
						'foreignKey' => 'tl_user_group.name',
						'eval'       => ['tl_class' => 'w50', 'mandatory' => false, 'includeBlankOption' => true],
					],
					'permissions' => [
						'label'     => &$GLOBALS['TL_LANG']['tl_event_release_level_policy']['permissions'],
						'exclude'   => true,
						'inputType' => 'select',
						'options'   => ['canRelLevelUp', 'canRelLevelDown'],
						'reference' => &$GLOBALS['TL_LANG']['tl_event_release_level_policy'],
						'eval'      => ['tl_class' => 'w50', 'multiple' => true, 'chosen' => true, 'mandatory' => false],
					],
				],
			],
			'sql'       => 'blob NULL',
		],
	],
];
