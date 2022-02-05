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

namespace Markocupic\SacEventToolBundle\DataContainer;

use Contao\Controller;
use Contao\CoreBundle\ServiceAnnotation\Callback;
use Contao\System;

class Module
{

    /**
     * @Callback(table="tl_module", target="fields.eventFilterBoardFields.options")
     */
    public function getEventFilterBoardFields()
    {
        $opt = [];

        Controller::loadDataContainer('tl_event_filter_form');
        System::loadLanguageFile('tl_event_filter_form');

        foreach (array_keys($GLOBALS['TL_DCA']['tl_event_filter_form']['fields']) as $k) {
            $opt[$k] = $GLOBALS['TL_LANG']['tl_event_filter_form'][$k][0] ?? $k;
        }

        return $opt;
    }

    /**
     * Return all templates as array.
     *
     * @Callback(table="tl_module", target="fields.eventListPartialTpl.options")
     */
    public function getEventListTemplates()
    {
        return Controller::getTemplateGroup('event_list_partial_');
    }
}
