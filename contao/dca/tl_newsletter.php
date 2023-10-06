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

use Contao\CoreBundle\DataContainer\PaletteManipulator;

// Subpalettes
$GLOBALS['TL_DCA']['tl_newsletter']['subpalettes']['enableSendAndDeleteCron'] = 'sendPerMinute,cronJobStart';

// Define selectors
$GLOBALS['TL_DCA']['tl_newsletter']['palettes']['__selector__'][] = 'enableSendAndDeleteCron';

// Palettes
PaletteManipulator::create()
    ->addLegend('sac_evt_legend', 'title_legend', PaletteManipulator::POSITION_AFTER)
    ->addField(['enableSendAndDeleteCron'], 'sac_evt_legend', PaletteManipulator::POSITION_APPEND)
    ->applyToPalette('default', 'tl_newsletter');

$GLOBALS['TL_DCA']['tl_newsletter']['fields']['enableSendAndDeleteCron'] = [
    'exclude'   => true,
    'filter'    => true,
    'sorting'   => true,
    'inputType' => 'checkbox',
    'eval'      => ['tl_class' => 'clr m12', 'doNotCopy' => true, 'submitOnChange' => true, 'boolean' => true],
    'sql'       => "char(1) NOT NULL default ''",
];

$GLOBALS['TL_DCA']['tl_newsletter']['fields']['sendPerMinute'] = [
    'exclude'   => true,
    'filter'    => true,
    'inputType' => 'text',
    'eval'      => ['rgxp' => 'natural', 'tl_class' => 'w50', 'mandatory' => true],
    'sql'       => "smallint(2) unsigned NOT NULL default 15",
];

$GLOBALS['TL_DCA']['tl_newsletter']['fields']['cronJobStart'] = [
    'exclude'   => true,
    'inputType' => 'text',
    'default'   => time() + 24 * 3600,
    'eval'      => ['rgxp' => 'datim', 'datepicker' => true, 'doNotCopy' => true, 'tl_class' => 'w50 wizard'],
    'sql'       => "varchar(10) COLLATE ascii_bin NOT NULL default ''",
];
