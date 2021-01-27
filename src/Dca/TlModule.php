<?php

declare(strict_types=1);

/*
 * This file is part of SAC Event Tool Bundle.
 *
 * (c) Marko Cupic 2021 <m.cupic@gmx.ch>
 * @license MIT
 * For the full copyright and license information,
 * please view the LICENSE file that was distributed with this source code.
 * @link https://github.com/markocupic/sac-event-tool-bundle
 */

namespace Markocupic\SacEventToolBundle\Dca;

use Contao\Controller;
use Contao\System;

/**
 * Class TlModule.
 */
class TlModule extends \tl_module
{
    /**
     * @return array
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
     * Return all calendar templates as array.
     *
     * @return array
     */
    public function getEventListTemplates()
    {
        return $this->getTemplateGroup('event_list_partial_');
    }
}
