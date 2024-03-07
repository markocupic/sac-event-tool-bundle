<?php

declare(strict_types=1);

/*
 * This file is part of SAC Event Tool Bundle.
 *
 * (c) Marko Cupic 2024 <m.cupic@gmx.ch>
 * @license GPL-3.0-or-later
 * For the full copyright and license information,
 * please view the LICENSE file that was distributed with this source code.
 * @link https://github.com/markocupic/sac-event-tool-bundle
 */

namespace Markocupic\SacEventToolBundle\ICal;

use Contao\CalendarEventsModel;
use Contao\CoreBundle\Framework\Adapter;
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\CoreBundle\InsertTag\InsertTagParser;
use Contao\Events;
use Contao\StringUtil;
use Eluceo\iCal\Domain\Entity\Calendar;
use Eluceo\iCal\Domain\Entity\Event;
use Eluceo\iCal\Domain\ValueObject\Date;
use Eluceo\iCal\Domain\ValueObject\Location;
use Eluceo\iCal\Domain\ValueObject\SingleDay;
use Eluceo\iCal\Domain\ValueObject\Uri;
use Eluceo\iCal\Presentation\Factory\CalendarFactory;
use Markocupic\SacEventToolBundle\CalendarEventsHelper;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\HttpFoundation\Response;

class EventICal
{
	private Adapter $calendarEventsHelper;
	private Adapter $events;
	private Adapter $stringUtil;

	public function __construct(
		private readonly ContaoFramework $framework,
		private readonly InsertTagParser $insertTagParser,
	) {
		$this->calendarEventsHelper = $this->framework->getAdapter(CalendarEventsHelper::class);
		$this->events = $this->framework->getAdapter(Events::class);
		$this->stringUtil = $this->framework->getAdapter(StringUtil::class);
	}

	public function download(CalendarEventsModel $objEvent): Response
	{
		// Summary
		$summary = $this->insertTagParser->replaceInline($objEvent->title);
		$summary = strip_tags($this->stringUtil->revertInputEncoding($summary));

		// Location
		$location = $this->insertTagParser->replaceInline($objEvent->location);
		$location = strip_tags($this->stringUtil->revertInputEncoding($location));

		// Get the url
		$url = $this->events->generateEventUrl($objEvent, true);

		$arrEvents = [];
		$arrEventTimestamps = $this->calendarEventsHelper->getEventTimestamps($objEvent);

		foreach ($arrEventTimestamps as $timestamp) {
			$occurrence = new SingleDay(
				new Date(
					new \DateTime(
						date('d.m.Y', (int)$timestamp)
					)
				)
			);

			$vEvent = new Event();
			$vEvent
				->setSummary($summary)
				->setLocation(new Location($location))
				->setUrl(new Uri($url))
				->setOccurrence($occurrence);

			$arrEvents[] = $vEvent;
		}

		$vCalendar = new Calendar([...$arrEvents]);
		$componentFactory = new CalendarFactory();
		$calendarComponent = $componentFactory->createCalendar($vCalendar);

		$response = new Response((string)$calendarComponent);

		$disposition = HeaderUtils::makeDisposition(
			HeaderUtils::DISPOSITION_ATTACHMENT,
			sprintf('%s.ics', $objEvent->alias),
		);

		$response->headers->addCacheControlDirective('must-revalidate');
		$response->headers->set('Connection', 'close');
		$response->headers->set('Content-Disposition', $disposition);
		$response->headers->set('Content-Type', 'text/calendar; charset=utf-8');

		return $response;
	}
}
