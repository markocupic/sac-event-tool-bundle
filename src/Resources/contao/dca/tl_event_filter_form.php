<?php

/*
 * This file is part of SAC Event Tool Bundle.
 *
 * (c) Marko Cupic 2021 <m.cupic@gmx.ch>
 * @license MIT
 * For the full copyright and license information,
 * please view the LICENSE file that was distributed with this source code.
 * @link https://github.com/markocupic/sac-event-tool-bundle
 */

use Contao\Date;
use Markocupic\SacEventToolBundle\Dca\TlEventFilterForm;

$GLOBALS['TL_DCA']['tl_event_filter_form'] = array
(
    'fields' => array
    (
        'year'                 => array
        (
            'label'     => &$GLOBALS['TL_LANG']['tl_event_filter_form']['year'],
            'inputType' => 'select',
            'options'   => range(2016, (int)Date::parse('Y') + 1),
            'eval'      => array('includeBlankOption' => true, 'blankOptionLabel' => &$GLOBALS['TL_LANG']['tl_event_filter_form']['blankOptionLabel']),
        ),
        'dateStart'            => array
        (
            'label'     => &$GLOBALS['TL_LANG']['tl_event_filter_form']['dateStart'],
            'inputType' => 'text',
            'eval'      => array('placeholder' => 'yyyy-mm-dd', 'maxlength' => 12),
        ),
        'tourType'             => array
        (
            'label'            => &$GLOBALS['TL_LANG']['tl_event_filter_form']['tourType'],
            'inputType'        => 'select',
            'options_callback' => array(TlEventFilterForm::class, 'getTourTypes'),
            'eval'             => array('includeBlankOption' => true, 'blankOptionLabel' => &$GLOBALS['TL_LANG']['tl_event_filter_form']['showAll']),
        ),
        'courseType'           => array
        (
            'label'            => &$GLOBALS['TL_LANG']['tl_event_filter_form']['courseType'],
            'inputType'        => 'select',
            'options_callback' => array(TlEventFilterForm::class, 'getCourseTypes'),
            'eval'             => array('includeBlankOption' => true, 'blankOptionLabel' => &$GLOBALS['TL_LANG']['tl_event_filter_form']['showAll']),
        ),
        'organizers'           => array
        (
            'label'            => &$GLOBALS['TL_LANG']['tl_event_filter_form']['organizers'],
            'inputType'        => 'select',
            'options_callback' => array(TlEventFilterForm::class, 'getOrganizers'),
            'eval'             => array('multiple' => true),
        ),
        'textsearch'           => array
        (
            'label'     => &$GLOBALS['TL_LANG']['tl_event_filter_form']['textsearch'],
            'inputType' => 'text',
            'eval'      => array('placeholder' => &$GLOBALS['TL_LANG']['tl_event_filter_form']['enterSearchTerms']),
        ),
        'eventId'              => array
        (
            'label'     => &$GLOBALS['TL_LANG']['tl_event_filter_form']['eventId'],
            'inputType' => 'text',
            'eval'      => array('placeholder' => Date::parse('Y') . '-****'),
        ),
        'courseId'             => array
        (
            'label'     => &$GLOBALS['TL_LANG']['tl_event_filter_form']['courseId'],
            'inputType' => 'text',
            'eval'      => array('placeholder' => $GLOBALS['TL_LANG']['tl_event_filter_form']['courseId'][0]),
        ),
        'suitableForBeginners' => array
        (
            'label'     => &$GLOBALS['TL_LANG']['tl_event_filter_form']['suitableForBeginners'],
            'inputType' => 'checkbox',
        ),
    ),
);
