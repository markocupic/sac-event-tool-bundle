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
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\StringUtil;

readonly class TourDifficulty
{
    public function __construct(private ContaoFramework $framework)
    {
    }

    #[AsCallback(table: 'tl_tour_difficulty', target: 'list.sorting.child_record', priority: 100)]
    public function listDifficulties(array $row): string
    {
        $stringUtil = $this->framework->getAdapter(StringUtil::class);

        return '<div class="tl_content_left"><span class="level">'.$stringUtil->specialchars($row['title']).'</span> '.$stringUtil->specialchars($row['shortcut'])."</div>\n";
    }
}
