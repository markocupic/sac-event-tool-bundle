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

namespace Markocupic\SacEventToolBundle\Ical;

use Contao\CalendarEventsModel;
use Contao\CoreBundle\Framework\Adapter;
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\CoreBundle\InsertTag\InsertTagParser;
use Contao\Environment;
use Contao\Events;
use Eluceo\iCal\Domain\Entity\Calendar;
use Eluceo\iCal\Domain\Entity\Event;
use Eluceo\iCal\Domain\ValueObject\DateTime;
use Eluceo\iCal\Domain\ValueObject\Location;
use Eluceo\iCal\Domain\ValueObject\TimeSpan;
use Eluceo\iCal\Domain\ValueObject\Uri;
use Eluceo\iCal\Presentation\Factory\CalendarFactory;

class SendEventIcal
{
    private ContaoFramework $framework;
    private InsertTagParser $insertTagParser;

    // Adapters
    private Adapter $environment;
    private Adapter $events;

    public function __construct(ContaoFramework $framework, InsertTagParser $insertTagParser)
    {
        $this->framework = $framework;
        $this->insertTagParser = $insertTagParser;

        // Adapters
        $this->environment = $this->framework->getAdapter(Environment::class);
        $this->events = $this->framework->getAdapter(Events::class);
    }

    /**
     * @param CalendarEventsModel $objEvent
     * @return void
     * @throws \Exception
     */
    public function sendEventIcalToBrowser(CalendarEventsModel $objEvent): void
    {
        // start- & end-date
        if ($objEvent->startTime === $objEvent->startDate && $objEvent->endTime === $objEvent->endDate) {
            $dateFormat = 'd.m.Y';
        } else {
            $dateFormat = 'd.m.Y H:i:s';
        }

        $objStartDate = new DateTime(new \DateTime(date($dateFormat, (int) $objEvent->startTime)), false);
        $objEndDate = new DateTime(new \DateTime(date($dateFormat, (int) $objEvent->endTime)), false);

        // summary
        $summary = strip_tags(html_entity_decode((string) $objEvent->title));
        $summary = $this->insertTagParser->replaceInline($summary);

        // location
        $location = strip_tags(html_entity_decode((string) $objEvent->location));
        $location = $this->insertTagParser->replaceInline($location);

        // Get url
        $url = $this->environment->get('url').'/'.$this->events->generateEventUrl($objEvent);
        $vEvent = new Event();

        $vEvent
            ->setSummary($summary)
            ->setLocation(new Location($location))
            ->setUrl(new Uri($url))
            ->setOccurrence(new TimeSpan($objStartDate, $objEndDate))
        ;

        $vCalendar = new Calendar([$vEvent]);
        $componentFactory = new CalendarFactory();
        $calendarComponent = $componentFactory->createCalendar($vCalendar);

        header('Content-Type: text/calendar; charset=utf-8');
        header('Content-Disposition: attachment; filename="'.$objEvent->alias.'.ics"');
        echo $calendarComponent;

        exit;
    }
}
