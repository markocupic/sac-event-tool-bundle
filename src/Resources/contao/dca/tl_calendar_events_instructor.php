<?php

declare(strict_types=1);

/*
 * This file is part of SAC Event Tool Bundle.
 *
 * (c) Marko Cupic 2022 <m.cupic@gmx.ch>
 * @license GPL-3.0-or-later
 * For the full copyright and license information,
 * please view the LICENSE file that was distributed with this source code.
 * @link https://github.com/markocupic/sac-event-tool-bundle
 */

/*
 * Table tl_calendar_events_instructor
 */
$GLOBALS['TL_DCA']['tl_calendar_events_instructor'] = [
    'config' => [
        'dataContainer'     => 'Table',
        'notCopyable'       => true,
        'ptable'            => 'tl_calendar_events',
        // Do not copy nor delete records, if an item has been deleted!
        'onload_callback'   => [],
        'onsubmit_callback' => [],
        'ondelete_callback' => [],
        'sql'               => [
            'keys' => [
                'id'     => 'primary',
                'pid'    => 'index',
                'userId' => 'index',
            ],
        ],
    ],
    // List
    'list'   => [
        'sorting'           => [],
        'label'             => [],
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
            'delete' => [
                'href'       => 'act=delete',
                'icon'       => 'delete.svg',
                'attributes' => 'onclick="if(!confirm(\''.$GLOBALS['TL_LANG']['MSC']['deleteConfirm'].'\'))return false;Backend.getScrollOffset()"',
            ],
        ],
    ],
    'fields' => [
        'id'               => [
            'sql' => 'int(10) unsigned NOT NULL auto_increment',
        ],
        // Parent: tl_calendar_events.id
        'pid'              => [
            'sql' => 'int(10) unsigned NOT NULL default 0',
        ],
        'tstamp'           => [
            'sql' => 'int(10) unsigned NOT NULL default 0',
        ],
        // Parent tl_user.id
        'userId'           => [
            'sql' => 'int(10) unsigned NOT NULL default 0',
        ],
        'isMainInstructor' => [
            'sql' => "char(1) NOT NULL default ''",
        ],
    ],
];
