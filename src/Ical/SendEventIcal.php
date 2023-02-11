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

namespace Markocupic\SacEventToolBundle\Ical;

use Contao\CalendarEventsModel;
use Contao\CoreBundle\Framework\Adapter;
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\CoreBundle\InsertTag\InsertTagParser;
use Contao\Environment;
use Contao\Events;
use Eluceo\iCal\Domain\Entity\Calendar;
use Eluceo\iCal\Domain\Entity\Event;
use Eluceo\iCal\Domain\ValueObject\Date;
use Eluceo\iCal\Domain\ValueObject\Location;
use Eluceo\iCal\Domain\ValueObject\SingleDay;
use Eluceo\iCal\Domain\ValueObject\Uri;
use Eluceo\iCal\Presentation\Factory\CalendarFactory;
use Markocupic\SacEventToolBundle\CalendarEventsHelper;

class SendEventIcal
{
    // Adapters
    private Adapter $environment;
    private Adapter $events;

    public function __construct(
        private readonly ContaoFramework $framework,
        private readonly InsertTagParser $insertTagParser,
    ) {
        // Adapters
        $this->environment = $this->framework->getAdapter(Environment::class);
        $this->events = $this->framework->getAdapter(Events::class);
    }

    /**
     * @throws \Exception
     */
    public function sendEventIcalToBrowser(CalendarEventsModel $objEvent): void
    {
        $dateFormat = 'd.m.Y';

        // summary
        $summary = strip_tags(html_entity_decode((string) $objEvent->title));
        $summary = $this->insertTagParser->replaceInline($summary);

        // location
        $location = strip_tags(html_entity_decode((string) $objEvent->location));
        $location = $this->insertTagParser->replaceInline($location);

        // Get url
        $url = $this->environment->get('url').'/'.$this->events->generateEventUrl($objEvent);

        $arrEvents = [];
        $arrEventTstamps = CalendarEventsHelper::getEventTimestamps($objEvent);

        foreach ($arrEventTstamps as $timestamp) {
            $occurence = new SingleDay(new Date(new \DateTime(date($dateFormat, (int) $timestamp)), true));

            $vEvent = new Event();
            $vEvent
                ->setSummary($summary)
                ->setLocation(new Location($location))
                ->setUrl(new Uri($url))
                ->setOccurrence($occurence)
                     ;
            $arrEvents[] = $vEvent;
        }

        $vCalendar = new Calendar([...$arrEvents]);
        $componentFactory = new CalendarFactory();
        $calendarComponent = $componentFactory->createCalendar($vCalendar);

        header('Content-Type: text/calendar; charset=utf-8');
        header('Content-Disposition: attachment; filename="'.$objEvent->alias.'.ics"');
        echo $calendarComponent;

        exit;
    }
}
