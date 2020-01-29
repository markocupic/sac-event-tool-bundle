<?php

declare(strict_types=1);

/**
 * SAC Event Tool Web Plugin for Contao
 * Copyright (c) 2008-2020 Marko Cupic
 * @package sac-event-tool-bundle
 * @author Marko Cupic m.cupic@gmx.ch, 2017-2020
 * @link https://github.com/markocupic/sac-event-tool-bundle
 */

namespace Markocupic\SacEventToolBundle\Ical;

use Contao\CalendarEventsModel;
use Contao\Controller;
use Contao\Environment;
use Contao\Events;
use Eluceo\iCal\Component\Calendar;
use Eluceo\iCal\Component\Event;

/**
 * Class SendEventIcal
 * @package Markocupic\SacEventToolBundle\Ical
 */
class SendEventIcal
{

    /**
     * @param CalendarEventsModel $objEvent
     */
    public function sendIcsFile(CalendarEventsModel $objEvent)
    {
        $vCalendar = new Calendar(Environment::get('url') . '/' . Events::generateEventUrl($objEvent));
        $vEvent = new Event();
        $noTime = false;
        if ($objEvent->startTime === $objEvent->startDate && $objEvent->endTime === $objEvent->endDate)
        {
            $noTime = true;
        }
        $vEvent
            ->setDtStart(\DateTime::createFromFormat('d.m.Y - H:i:s', date('d.m.Y - H:i:s', (int)$objEvent->startTime)))
            ->setDtEnd(\DateTime::createFromFormat('d.m.Y - H:i:s', date('d.m.Y - H:i:s', (int)$objEvent->endTime)))
            ->setSummary(strip_tags(Controller::replaceInsertTags($objEvent->title)))
            ->setUseUtc(false)
            ->setLocation($objEvent->location)
            ->setNoTime($noTime);

        $vCalendar->addComponent($vEvent);
        header('Content-Type: text/calendar; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $objEvent->alias . '.ics"');
        echo $vCalendar->render();
        exit;
    }
}
