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

use Contao\CoreBundle\ServiceAnnotation\Callback;

class Calendar
{
    private Util $util;

    public function __construct(Util $util)
    {
        $this->util = $util;
    }

    /**
     * Set correct referer.
     *
     * @Callback(table="tl_calendar", target="config.onload", priority=100)
     */
    public function setCorrectReferer(): void
    {
        $this->util->setCorrectReferer();
    }

    /**
     * @Callback(table="tl_calendar", target="list.sorting.child_record")
     */
    public function listCalendars(array $arrRow): string
    {
        return $arrRow['title'];
    }
}
