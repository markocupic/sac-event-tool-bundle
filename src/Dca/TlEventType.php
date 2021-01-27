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

use Contao\Backend;
use Contao\DataContainer;

/**
 * Class TlEventType.
 */
class TlEventType extends Backend
{
    /**
     * @param $strValue
     *
     * @return mixed
     */
    public function loadCallbackAlias($strValue, DataContainer $dc)
    {
        // Prevent renaming the alias if it was set
        if ('' !== $strValue) {
            $GLOBALS['TL_DCA']['tl_event_type']['fields']['alias']['eval']['readonly'] = true;
        }

        return $strValue;
    }
}
