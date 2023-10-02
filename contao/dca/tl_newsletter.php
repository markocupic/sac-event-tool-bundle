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

// Palettes
PaletteManipulator::create()
    ->addLegend('sac_evt_legend', 'title_legend', PaletteManipulator::POSITION_AFTER)
    ->addField(['deleteRecipientOnNewsletterSend'], 'sac_evt_legend', PaletteManipulator::POSITION_APPEND)
    ->applyToPalette('default', 'tl_newsletter');

$GLOBALS['TL_DCA']['tl_newsletter']['fields']['deleteRecipientOnNewsletterSend'] = [
    'exclude'   => true,
    'filter'    => true,
    'inputType' => 'checkbox',
    'eval'      => ['tl_class' => 'clr m12', 'boolean' => true, 'mandatory' => false],
    'sql'       => "char(1) NOT NULL default ''",
];
