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

use Contao\CoreBundle\DataContainer\PaletteManipulator;

PaletteManipulator::create()
    // Legends
    ->addLegend(
        'sacEventTool_legend',
        'global_legend'
    )
    ->addLegend(
        'sacWorkshopFlyer_legend',
        'global_legend'
    )
    ->addLegend(
        'sacTourArticle_legend',
        'global_legend'
    )
    ->addField(
        ['SAC_EVT_EVENT_MEMBER_LIST_FILE_NAME_PATTERN'],
        'sacEventTool_legend',
        PaletteManipulator::POSITION_APPEND
    )
    ->addField(
        ['SAC_EVT_EVENT_MEMBER_LIST_TEMPLATE_SRC'],
        'sacEventTool_legend',
        PaletteManipulator::POSITION_APPEND
    )
    ->addField(
        ['SAC_EVT_TOUR_ARTICLE_EXPORT_TEMPLATE_SRC'],
        'sacTourArticle_legend',
        PaletteManipulator::POSITION_APPEND
    )
    // Apply Palette
    ->applyToPalette(
        'default',
        'tl_settings'
    );

$GLOBALS['TL_DCA']['tl_settings']['fields']['SAC_EVT_EVENT_MEMBER_LIST_FILE_NAME_PATTERN'] = [
    'inputType' => 'text',
    'eval'      => [
        'mandatory'      => true,
        'decodeEntities' => false,
        'tl_class'       => 'w50',
    ],
];

$GLOBALS['TL_DCA']['tl_settings']['fields']['SAC_EVT_EVENT_MEMBER_LIST_TEMPLATE_SRC'] = [
    'inputType' => 'text',
    'eval'      => [
        'mandatory'      => true,
        'decodeEntities' => false,
        'tl_class'       => 'w50',
    ],
];

$GLOBALS['TL_DCA']['tl_settings']['fields']['SAC_EVT_TOUR_ARTICLE_EXPORT_TEMPLATE_SRC'] = [
    'inputType' => 'text',
    'eval'      => [
        'mandatory'      => true,
        'decodeEntities' => false,
        'tl_class'       => 'w50',
    ],
];
