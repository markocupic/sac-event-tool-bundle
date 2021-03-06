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

/**
 * Class TlEventReleaseLevelPolicy.
 */
class TlEventReleaseLevelPolicy extends Backend
{
    /**
     * List a style sheet.
     *
     * @param array $row
     *
     * @return string
     */
    public function listReleaseLevels($row)
    {
        return '<div class="tl_content_left"><span class="level">Stufe: '.$row['level'].'</span> '.$row['title']."</div>\n";
    }
}
