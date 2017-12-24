<?php
/**
 * SAC Event Tool Web Plugin for Contao
 * Copyright (c) 2008-2017 Marko Cupic
 * @package sac-event-tool-bundle
 * @author Marko Cupic m.cupic@gmx.ch, 2017
 * @link    https://sac-kurse.kletterkader.com
 */

declare(strict_types=1);

namespace Markocupic\SacEventToolBundle\Services\Ical;

use Contao\CalendarEventsModel;
use Contao\Environment;
use Eluceo\iCal\Component\Calendar;
use Eluceo\iCal\Component\Event;


class SendEventIcal
{

    /**
     * @param CalendarEventsModel $objEvent
     */
    public function sendIcsFile(CalendarEventsModel $objEvent): void
    {
        $vCalendar = new Calendar(Environment::get('url'));
        $vEvent = new Event();
        $noTime = false;
        if ($objEvent->startTime === $objEvent->startDate && $objEvent->endTime === $objEvent->endDate) {
            $noTime = true;
        }
        $vEvent
            ->setDtStart(\DateTime::createFromFormat('d.m.Y - H:i:s', date('d.m.Y - H:i:s', (int) $objEvent->startTime)))
            ->setDtEnd(\DateTime::createFromFormat('d.m.Y - H:i:s', date('d.m.Y - H:i:s', (int) $objEvent->endTime)))
            ->setSummary(strip_tags($this->replaceInsertTags($objEvent->title)))
            ->setUseUtc(false)
            ->setLocation($objEvent->location)
            ->setNoTime($noTime)
        ;
        // HOOK: modify the vEvent
        if (isset($GLOBALS['TL_HOOKS']['modifyIcsFile']) && is_array($GLOBALS['TL_HOOKS']['modifyIcsFile'])) {
            foreach ($GLOBALS['TL_HOOKS']['modifyIcsFile'] as $callback) {
                $this->import($callback[0]);
                $this->{$callback[0]}->{$callback[1]}($vEvent, $objEvent, $this);
            }
        }
        $vCalendar->addComponent($vEvent);
        header('Content-Type: text/calendar; charset=utf-8');
        header('Content-Disposition: attachment; filename="'.$objEvent->alias.'.ics"');
        echo $vCalendar->render();
        exit;
    }


}
