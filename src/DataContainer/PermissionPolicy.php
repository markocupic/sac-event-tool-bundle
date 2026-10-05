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

namespace Markocupic\SacEventToolBundle\DataContainer;

use Contao\CoreBundle\DependencyInjection\Attribute\AsCallback;
use Contao\DataContainer;

class PermissionPolicy
{
    /**
     * Every permission policy has its own palette, named after its identifier.
     */
    #[AsCallback(table: 'tl_permission_policy', target: 'config.onload', priority: 90)]
    public function setPalette(DataContainer $dc): void
    {
        $identifier = $dc->getCurrentRecord()['identifier'] ?? null;

        if (empty($identifier) || !isset($GLOBALS['TL_DCA']['tl_permission_policy']['palettes'][$identifier])) {
            return;
        }

        $GLOBALS['TL_DCA']['tl_permission_policy']['palettes']['default'] = $GLOBALS['TL_DCA']['tl_permission_policy']['palettes'][$identifier];
    }
}
