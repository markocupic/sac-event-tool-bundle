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

use Contao\BackendUser;
use Contao\CoreBundle\DataContainer\PaletteManipulator;
use Markocupic\SacEventToolBundle\Config\EventType;
use Contao\DataContainer;

$GLOBALS['TL_DCA']['tl_calendar']['list']['operations'] = [
	'edit',
	'children',
	'copyWithoutChildRecords' => [
		'href' => 'act=paste&mode=copy&children=0',
		'icon' => 'copy.svg',
	],
	'copy'                    => [
		'href' => 'act=paste&mode=copy',
		'icon' => 'copychildren.svg',
	],
	'cut',
	'delete',
	'show',
];

// Table config
$GLOBALS['TL_DCA']['tl_calendar']['config']['ptable'] = 'tl_calendar_container';

// List
$GLOBALS['TL_DCA']['tl_calendar']['list']['sorting']['mode'] = DataContainer::MODE_PARENT;
$GLOBALS['TL_DCA']['tl_calendar']['list']['sorting']['headerFields'] = ['title'];
$GLOBALS['TL_DCA']['tl_calendar']['list']['sorting']['disableGrouping'] = true;

// Subpalettes
$GLOBALS['TL_DCA']['tl_calendar']['subpalettes']['enableMaxEventReleaseLevelProtection'] = 'maxEventReleaseLevelTimeLimit';
$GLOBALS['TL_DCA']['tl_calendar']['subpalettes']['enableEventStartDateValidation'] = 'validTimePeriodStart,validTimePeriodStop';
$GLOBALS['TL_DCA']['tl_calendar']['subpalettes']['sendEventReminder'] = 'eventReminderOffset,eventReminderNotification';
$GLOBALS['TL_DCA']['tl_calendar']['subpalettes']['sendEventCompletionReminder'] = 'eventCompletionReminderNotification,eventCompletionReminderEventTypes,eventCompletionReminderFirstOffset,eventCompletionReminderInterval';
$GLOBALS['TL_DCA']['tl_calendar']['subpalettes']['autoPublishEvents'] = 'autoPublishEventsDate,autoPublishEventsStatus';

// Define selectors
$GLOBALS['TL_DCA']['tl_calendar']['palettes']['__selector__'][] = 'enableMaxEventReleaseLevelProtection';
$GLOBALS['TL_DCA']['tl_calendar']['palettes']['__selector__'][] = 'enableEventStartDateValidation';
$GLOBALS['TL_DCA']['tl_calendar']['palettes']['__selector__'][] = 'sendEventReminder';
$GLOBALS['TL_DCA']['tl_calendar']['palettes']['__selector__'][] = 'sendEventCompletionReminder';
$GLOBALS['TL_DCA']['tl_calendar']['palettes']['__selector__'][] = 'autoPublishEvents';

// Palettes
PaletteManipulator::create()
	->addLegend('valid_time_period_legend', 'protected_legend', PaletteManipulator::POSITION_BEFORE)
	->addLegend('event_release_level_legend', 'protected_legend', PaletteManipulator::POSITION_BEFORE)
	->addLegend('auto_publish_events_legend', 'protected_legend', PaletteManipulator::POSITION_BEFORE)
	->addLegend('event_type_legend', 'protected_legend', PaletteManipulator::POSITION_BEFORE)
	->addLegend('event_reader_legend', 'protected_legend', PaletteManipulator::POSITION_BEFORE)
	->addLegend('event_reminder_legend', 'protected_legend', PaletteManipulator::POSITION_BEFORE)
	->addLegend('event_completion_reminder_legend', 'protected_legend', PaletteManipulator::POSITION_BEFORE)
	->addField(['enableEventStartDateValidation'], 'valid_time_period_legend', PaletteManipulator::POSITION_APPEND)
	->addField(['enableMaxEventReleaseLevelProtection'], 'event_release_level_legend', PaletteManipulator::POSITION_APPEND)
	->addField(['autoPublishEvents'], 'auto_publish_events_legend', PaletteManipulator::POSITION_APPEND)
	->addField(['allowedEventTypes,notifyOnEventReleaseLevelChange,notifyOnEventPublish'], 'event_type_legend', PaletteManipulator::POSITION_APPEND)
	->addField(['userPortraitJumpTo'], 'event_reader_legend', PaletteManipulator::POSITION_APPEND)
	->addField(['sendEventReminder'], 'event_reminder_legend', PaletteManipulator::POSITION_APPEND)
	->addField(['sendEventCompletionReminder'], 'event_completion_reminder_legend', PaletteManipulator::POSITION_APPEND)
	->applyToPalette('default', 'tl_calendar');

// Fields
$GLOBALS['TL_DCA']['tl_calendar']['fields']['pid'] = [
	'foreignKey' => 'tl_calendar_container.title',
	'sql'        => "int(10) unsigned NOT NULL default 0",
	'relation'   => ['type' => 'belongsTo', 'load' => 'eager'],
];

$GLOBALS['TL_DCA']['tl_calendar']['fields']['allowedEventTypes'] = [
	'label'     => &$GLOBALS['TL_LANG']['tl_calendar']['allowedEventTypes'],
	'exclude'   => true,
	'filter'    => true,
	'inputType' => 'checkbox',
	'reference' => &$GLOBALS['TL_LANG']['MSC'],
	'options'   => EventType::ALL,
	'eval'      => ['multiple' => true, 'includeBlankOption' => false, 'doNotShow' => false, 'tl_class' => 'clr m12', 'mandatory' => true],
	'sql'       => 'blob NULL',
];

$GLOBALS['TL_DCA']['tl_calendar']['fields']['notifyOnEventReleaseLevelChange'] = [
	'label'     => &$GLOBALS['TL_LANG']['tl_calendar']['notifyOnEventReleaseLevelChange'],
	'exclude'   => true,
	'filter'    => false,
	'inputType' => 'text',
	'eval'      => ['tl_class' => 'clr m12', 'mandatory' => false],
	'sql'       => "varchar(255) NOT NULL default ''",
];

$GLOBALS['TL_DCA']['tl_calendar']['fields']['notifyOnEventPublish'] = [
	'label'     => &$GLOBALS['TL_LANG']['tl_calendar']['notifyOnEventPublish'],
	'exclude'   => true,
	'filter'    => false,
	'inputType' => 'text',
	'eval'      => ['tl_class' => 'clr m12', 'mandatory' => false],
	'sql'       => "varchar(255) NOT NULL default ''",
];

$GLOBALS['TL_DCA']['tl_calendar']['fields']['userPortraitJumpTo'] = [
	'exclude'    => true,
	'inputType'  => 'pageTree',
	'foreignKey' => 'tl_page.title',
	'eval'       => ['fieldType' => 'radio'],
	'sql'        => 'int(10) unsigned NOT NULL default 0',
	'relation'   => ['type' => 'hasOne', 'load' => 'lazy'],
];

$GLOBALS['TL_DCA']['tl_calendar']['fields']['enableEventStartDateValidation'] = [
	'filter'    => true,
	'inputType' => 'checkbox',
	'eval'      => ['submitOnChange' => true, 'tl_class' => 'm12 clr'],
	'sql'       => ['type' => 'boolean', 'default' => false],
];

$GLOBALS['TL_DCA']['tl_calendar']['fields']['validTimePeriodStart'] = [
	'inputType' => 'text',
	'eval'      => ['mandatory' => true, 'rgxp' => 'date', 'datepicker' => true, 'tl_class' => 'w50 wizard'],
	'sql'       => "varchar(10) COLLATE ascii_bin NOT NULL default ''",
];

$GLOBALS['TL_DCA']['tl_calendar']['fields']['validTimePeriodStop'] = [
	'inputType' => 'text',
	'eval'      => ['mandatory' => true, 'rgxp' => 'date', 'datepicker' => true, 'tl_class' => 'w50 wizard'],
	'sql'       => "varchar(10) COLLATE ascii_bin NOT NULL default ''",
];

$GLOBALS['TL_DCA']['tl_calendar']['fields']['enableMaxEventReleaseLevelProtection'] = [
	'filter'    => true,
	'inputType' => 'checkbox',
	'eval'      => ['submitOnChange' => true, 'tl_class' => 'm12 clr'],
	'sql'       => ['type' => 'boolean', 'default' => false],
];

$GLOBALS['TL_DCA']['tl_calendar']['fields']['maxEventReleaseLevelTimeLimit'] = [
	'default'   => time(),
	'exclude'   => true,
	'inputType' => 'text',
	'eval'      => ['rgxp' => 'datim', 'mandatory' => true, 'datepicker' => true, 'tl_class' => 'w50 wizard'],
	'sql'       => 'bigint(20) unsigned NULL',
];

// Event reminder
// Reminds instructors and participants x days before the event start.
// See docs/features/event-reminder.md
$GLOBALS['TL_DCA']['tl_calendar']['fields']['sendEventReminder'] = [
	'filter'    => true,
	'inputType' => 'checkbox',
	'eval'      => ['submitOnChange' => true, 'tl_class' => 'm12 clr'],
	'sql'       => ['type' => 'boolean', 'default' => false],
];

// Options: only notifications of type "event_reminder" (see Feature\EventReminder\DataContainer\Calendar)
$GLOBALS['TL_DCA']['tl_calendar']['fields']['eventReminderNotification'] = [
	'exclude'   => true,
	'inputType' => 'select',
	'eval'      => ['mandatory' => true, 'includeBlankOption' => true, 'chosen' => true, 'tl_class' => 'w50'],
	'sql'       => "int(10) unsigned NOT NULL default 0",
];

$GLOBALS['TL_DCA']['tl_calendar']['fields']['eventReminderOffset'] = [
	'exclude'   => true,
	'inputType' => 'select',
	'options'   => range(1, 365),
	'eval'      => ['rgxp' => 'natural', 'tl_class' => 'w50'],
	'sql'       => ['type' => 'integer', 'notnull' => true, 'default' => 14, 'unsigned' => true],
];

// Event completion reminder
// Reminds instructors and registration coordinators of open tasks (tour report, participation confirmation) after an event.
// See docs/features/event-completion-reminder.md
$GLOBALS['TL_DCA']['tl_calendar']['fields']['sendEventCompletionReminder'] = [
	'exclude'   => true,
	'filter'    => true,
	'inputType' => 'checkbox',
	'eval'      => ['submitOnChange' => true, 'tl_class' => 'm12 clr'],
	'sql'       => ['type' => 'boolean', 'default' => false],
];

// Options: only notifications of type "event_completion_reminder" (see Feature\EventCompletionReminder\DataContainer\Calendar)
$GLOBALS['TL_DCA']['tl_calendar']['fields']['eventCompletionReminderNotification'] = [
	'exclude'   => true,
	'inputType' => 'select',
	'eval'      => ['mandatory' => true, 'includeBlankOption' => true, 'chosen' => true, 'tl_class' => 'w50'],
	'sql'       => "int(10) unsigned NOT NULL default 0",
];

// Only events of these types are checked for open tasks.
// Which tasks apply to an event type is decided by the task classes (PostEventTaskInterface::supports()).
$GLOBALS['TL_DCA']['tl_calendar']['fields']['eventCompletionReminderEventTypes'] = [
	'exclude'   => true,
	'inputType' => 'select',
	'options'   => EventType::ALL,
	'reference' => &$GLOBALS['TL_LANG']['MSC'],
	'default'   => [EventType::TOUR, EventType::LAST_MINUTE_TOUR, EventType::COURSE],
	'eval'      => ['mandatory' => true, 'multiple' => true, 'chosen' => true, 'tl_class' => 'w50'],
	'sql'       => 'blob NULL',
];

// Completion period: days after tl_calendar_events.endDate before the first notification is sent
$GLOBALS['TL_DCA']['tl_calendar']['fields']['eventCompletionReminderFirstOffset'] = [
	'exclude'   => true,
	'inputType' => 'select',
	'options' => range(1, 30),
	'eval'      => ['mandatory' => true, 'rgxp' => 'natural', 'tl_class' => 'w50 clr'],
	'sql'       => ['type' => 'integer', 'notnull' => true, 'default' => 7, 'unsigned' => true],
];

// Days between two notifications to the same recipient for this calendar
$GLOBALS['TL_DCA']['tl_calendar']['fields']['eventCompletionReminderInterval'] = [
	'exclude'   => true,
	'inputType' => 'select',
	'options' => range(1, 30),
	'eval'      => ['mandatory' => true, 'rgxp' => 'natural', 'tl_class' => 'w50'],
	'sql'       => ['type' => 'integer', 'notnull' => true, 'default' => 7, 'unsigned' => true],
];

// Auto publish events
// On the given date and time, events on the second-highest release level are promoted to the highest level and published.
// See docs/features/auto-publish-events.md
$GLOBALS['TL_DCA']['tl_calendar']['fields']['autoPublishEvents'] = [
	'exclude'   => true,
	'filter'    => true,
	'inputType' => 'checkbox',
	'eval'      => ['submitOnChange' => true, 'doNotCopy' => true, 'tl_class' => 'm12 clr'],
	'sql'       => ['type' => 'boolean', 'default' => false],
];

// Due date incl. time. The cron runs every 15 minutes.
$GLOBALS['TL_DCA']['tl_calendar']['fields']['autoPublishEventsDate'] = [
	'exclude'   => true,
	'inputType' => 'text',
	'eval'      => ['rgxp' => 'datim', 'mandatory' => true, 'datepicker' => true, 'nullIfEmpty' => true, 'doNotCopy' => true, 'tl_class' => 'w50 wizard'],
	'sql'       => 'bigint(20) unsigned NULL',
];

// Read-only status (last run), rendered by Feature\AutoPublishEvents\DataContainer\Calendar. No database column.
$GLOBALS['TL_DCA']['tl_calendar']['fields']['autoPublishEventsStatus'] = [
	'exclude' => true,
];

// Set by the cron after the run, never edited in the backend (not part of any palette).
// The run is due again as soon as autoPublishEventsDate differs from autoPublishEventsExecutedForDate.
$GLOBALS['TL_DCA']['tl_calendar']['fields']['autoPublishEventsExecutedAt'] = [
	'eval' => ['doNotCopy' => true],
	'sql'  => 'bigint(20) unsigned NULL',
];

$GLOBALS['TL_DCA']['tl_calendar']['fields']['autoPublishEventsExecutedForDate'] = [
	'eval' => ['doNotCopy' => true],
	'sql'  => 'bigint(20) unsigned NULL',
];
