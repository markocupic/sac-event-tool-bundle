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

namespace Markocupic\SacEventToolBundle\DataContainer;

use Contao\CoreBundle\DependencyInjection\Attribute\AsCallback;

class TourDifficulty
{
    #[AsCallback(table: 'tl_tour_difficulty', target: 'list.sorting.child_record', priority: 100)]
    public function listDifficulties(array $row): string
    {
        return '<div class="tl_content_left"><span class="level">'.$row['title'].'</span> '.$row['shortcut']."</div>\n";
    }
}
