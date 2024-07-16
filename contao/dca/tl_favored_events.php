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

$GLOBALS['TL_DCA']['tl_favored_events'] = [
    'config' => [
        'dataContainer'    => DC_Table::class,
        'doNotCopyRecords' => true,
        'enableVersioning' => true,
        'switchToEdit'     => true,
        'sql'              => [
            'keys' => [
                'id'       => 'primary',
                'eventId'  => 'index',
                'memberId' => 'index',
            ],
        ],
    ],
    'list'   => [
        'sorting'           => [
            'mode' => DataContainer::MODE_UNSORTED,
        ],
        'global_operations' => [
            'all',
        ],
    ],
    'fields' => [
        'id'       => [
            'sql' => 'int(10) unsigned NOT NULL auto_increment',
        ],
        'tstamp'   => [
            'sql' => "int(10) unsigned NOT NULL default 0",
        ],
        'eventId'  => [
            'foreignKey' => 'tl_calendar_events.title',
            'eval'       => ['mandatory' => true, 'fieldType' => 'radio', 'tl_class' => 'clr'],
            'sql'        => "int(10) unsigned NOT NULL default 0",
            'relation'   => ['type' => 'belongsTo', 'field' => 'id', 'load' => 'lazy'],
        ],
        'memberId' => [
            'foreignKey' => 'tl_member.id',
            'eval'       => ['mandatory' => true, 'fieldType' => 'radio', 'tl_class' => 'clr'],
            'sql'        => "int(10) unsigned NOT NULL default 0",
            'relation'   => ['type' => 'belongsTo', 'field' => 'id', 'load' => 'lazy'],
        ],
    ],
];
