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

namespace Markocupic\SacEventToolBundle\Dca;

use Contao\Input;
use Contao\System;

class TlCalendar extends \tl_calendar
{
    /**
     * Import the back end user object.
     */
    public function __construct()
    {
        // Set correct referer
        if ('sac_calendar_events_tool' === Input::get('do') && '' !== Input::get('ref')) {
            $objSession = System::getContainer()->get('session');
            $ref = Input::get('ref');
            $session = $objSession->get('referer');

            if (isset($session[$ref]['tl_calendar_container'])) {
                $session[$ref]['tl_calendar_container'] = str_replace('do=calendar', 'do=sac_calendar_events_tool', $session[$ref]['tl_calendar_container']);
                $objSession->set('referer', $session);
            }

            if (isset($session[$ref]['tl_calendar'])) {
                $session[$ref]['tl_calendar'] = str_replace('do=calendar', 'do=sac_calendar_events_tool', $session[$ref]['tl_calendar']);
                $objSession->set('referer', $session);
            }

            if (isset($session[$ref]['tl_calendar_events'])) {
                $session[$ref]['tl_calendar_events'] = str_replace('do=calendar', 'do=sac_calendar_events_tool', $session[$ref]['tl_calendar_events']);
                $objSession->set('referer', $session);
            }

            if (isset($session[$ref]['tl_calendar_events_instructor_invoice'])) {
                $session[$ref]['tl_calendar_events_instructor_invoice'] = str_replace('do=calendar', 'do=sac_calendar_events_tool', $session[$ref]['tl_calendar_events_instructor_invoice']);
                $objSession->set('referer', $session);
            }
        }

        parent::__construct();
    }

    /**
     * List items.
     *
     * @param array $arrRow
     *
     * @return string
     */
    public function listCalendars($arrRow)
    {
        return $arrRow['title'];
    }
}
