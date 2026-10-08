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

$GLOBALS['TL_DCA']['tl_event_release_level_policy'] = [
	'config'   => [
		'dataContainer'    => DC_Table::class,
		'ptable'           => 'tl_event_release_level_policy_package',
		'doNotCopyRecords' => true,
		'enableVersioning' => true,
		'switchToEdit'     => true,
		'sql'              => [
			'keys' => [
				'id'        => 'primary',
				// Every level only once per release level system (see DataContainer\EventReleaseLevelPolicy::validateLevel())
				'pid,level' => 'unique',
			],
		],
	],
	'list'     => [
		'sorting'           => [
			'mode'            => DataContainer::MODE_PARENT,
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
			'all',
		],
	],
	'palettes' => [
		'default' => '
		{title_legend},level,title,description;
		{permission_rules_legend},permissionRules;
		{event_registrations_grants_legend},allowRegistration',
	],
	'fields'   => [
		'id'                => [
			'sql' => 'int(10) unsigned NOT NULL auto_increment',
		],
		'pid'               => [
			'foreignKey' => 'tl_event_release_level_policy_package.title',
			'sql'        => "int(10) unsigned NOT NULL default 0",
			'relation'   => ['type' => 'belongsTo', 'load' => 'eager'],
		],
		'tstamp'            => [
			'sql' => "int(10) unsigned NOT NULL default 0",
		],
		// NULL instead of 0 for new, not yet saved records: the unique index pid,level allows several NULL values.
		// doNotCopy: a copy would otherwise violate the unique index.
		'level'             => [
			'exclude'   => true,
			'inputType' => 'select',
			'options'   => range(1, 10),
			'eval'      => ['doNotCopy' => true, 'includeBlankOption' => true, 'mandatory' => true, 'nullIfEmpty' => true, 'tl_class' => 'clr'],
			'sql'       => 'smallint(2) unsigned NULL',
		],
		'title'             => [
			'exclude'   => true,
			'inputType' => 'text',
			'eval'      => ['mandatory' => true, 'maxlength' => 255, 'tl_class' => 'clr'],
			'sql'       => "varchar(255) NOT NULL default ''",
		],
		'description'       => [
			'exclude'   => true,
			'inputType' => 'textarea',
			'eval'      => ['mandatory' => true, 'tl_class' => 'clr'],
			'sql'       => 'text NULL',
		],
		// Permissions of the release level (see EventReleaseLevelPermissionRules and CalendarEventsVoter).
		// They replace the old permission fields (see EventReleaseLevelPermissionRulesMigration).
		// A rule grants its flags to the selected parties of the event or to the members
		// of a user group (or).
		// Important: the names of the group field and its fields must not contain "__", the
		// group widget uses "__" as separator in the names of its virtual fields.
		'permissionRules'   => [
			'exclude'   => true,
			'inputType' => 'group',
			'palette'   => ['parties', 'group', 'flags'],
			'fields'    => [
				'parties' => [
					'label'     => &$GLOBALS['TL_LANG']['tl_event_release_level_policy']['permissionRules_parties'],
					'inputType' => 'select',
					'options'   => ['event_author', 'main_instructor', 'event_instructors', 'registration_coordinator'],
					'reference' => &$GLOBALS['TL_LANG']['tl_event_release_level_policy']['permissionRules_partyOptions'],
					'eval'      => ['chosen' => true, 'multiple' => true, 'tl_class' => 'clr'],
				],
				'group'   => [
					'label'      => &$GLOBALS['TL_LANG']['tl_event_release_level_policy']['permissionRules_group'],
					'inputType'  => 'select',
					'foreignKey' => 'tl_user_group.name',
					'eval'       => ['includeBlankOption' => true, 'tl_class' => 'clr'],
				],
				'flags'   => [
					'label'     => &$GLOBALS['TL_LANG']['tl_event_release_level_policy']['permissionRules_flags'],
					'inputType' => 'select',
					'options'   => ['can_write_event', 'can_delete_event', 'can_cut_event', 'can_administer_event_registrations', 'can_upgrade_release_level', 'can_downgrade_release_level', 'can_view_participant_event_history'],
					'reference' => &$GLOBALS['TL_LANG']['tl_event_release_level_policy']['permissionRules_flagOptions'],
					'eval'      => ['chosen' => true, 'mandatory' => true, 'multiple' => true, 'tl_class' => 'clr'],
				],
			],
			// allow ordering of the rules (default)
			'order'     => true,
			// New release levels start without rules (not NULL, so the migration does not convert them)
			'default'   => [],
			// store serialized into a blob (default storage backend)
			'sql'       => ['type' => 'blob', 'length' => \Doctrine\DBAL\Platforms\MySQLPlatform::LENGTH_LIMIT_BLOB, 'notnull' => false],
		],
		'allowRegistration' => [
			'exclude'   => true,
			'filter'    => true,
			'inputType' => 'checkbox',
			'sql'       => ['type' => 'boolean', 'default' => false],
		],
	],
];
