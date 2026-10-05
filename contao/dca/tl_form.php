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

use Contao\CoreBundle\DataContainer\PaletteManipulator;

// Event feedback: marks a form as event feedback form (values are stored in tl_event_feedback).
// See docs/features/event-feedback.md
PaletteManipulator::create()
	->addLegend('sac_event_feedback_legend', 'title_legend', PaletteManipulator::POSITION_AFTER)
	->addField('isSacEventFeedbackForm', 'sac_event_feedback_legend', PaletteManipulator::POSITION_APPEND)
	->applyToPalette('default', 'tl_form')
;

$GLOBALS['TL_DCA']['tl_form']['fields']['isSacEventFeedbackForm'] = [
	'exclude'   => true,
	'filter'    => true,
	'inputType' => 'checkbox',
	'eval'      => ['tl_class' => 'w50'],
	'sql'       => "char(1) NOT NULL default ''",
];
