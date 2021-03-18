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

namespace Markocupic\SacEventToolBundle\Controller\Feed;

use Contao\CalendarEventsModel;
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\Database;
use Contao\Date;
use Contao\Events;
use Contao\System;
use Markocupic\SacEventToolBundle\CalendarEventsHelper;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * Class UpcomingEventsController.
 */
class UpcomingEventsController extends AbstractController
{
    const LIMIT = 50;

    /**
     * @var ContaoFramework
     */
    private $framework;

    private $projectDir;

    /**
     * UpcomingEventsController constructor.
     */
    public function __construct(ContaoFramework $framework, string $projectDir)
    {
        $this->framework = $framework;
        $this->projectDir = $projectDir;
        $this->framework->initialize();
    }

    /**
     * Generate RSS Feed for https://www.sac-cas.ch/de/der-sac/sektionen/sac-pilatus/.
     *
     * @Route("/_rssfeeds/sac_cas_upcoming_events", name="sac_event_tool_rss_feed_sac_cas_upcoming_events", defaults={"_scope" = "frontend"})
     */
    public function printLatestEvents(): Response
    {
        $databaseAdapter = $this->framework->getAdapter(Database::class);
        $calendarEventsModelAdapter = $this->framework->getAdapter(CalendarEventsModel::class);
        $calendarEventsHelperAdapter = $this->framework->getAdapter(CalendarEventsHelper::class);
        $systemAdapter = $this->framework->getAdapter(System::class);
        $dateAdapter = $this->framework->getAdapter(Date::class);
        $eventsAdapter = $this->framework->getAdapter(Events::class);

        $rss = new \UniversalFeedCreator();
        //$rss->useCached(); // use cached version if age < 1 hour
        $rss->title = 'SAC Sektion Pilatus upcoming events';
        $rss->description = 'Provide the latest events for https://www.sac-cas.ch/de/der-sac/sektionen/sac-pilatus/';
        $rss->link = 'https://sac-pilatus.ch/_rssfeeds/sac_cas_upcoming_events';
        $rss->language = 'de';
        $rss->copyright = 'Copyright '.date('Y').', SAC Sektion Pilatus';
        $rss->pubDate = time();
        $rss->lastBuildDate = time();
        $rss->ttl = 60;
        //$rss->xslStyleSheet = '';
        $rss->webmaster = 'Marko Cupic, Oberkirch';

        $objEvent = $databaseAdapter->getInstance()
            ->prepare('SELECT * FROM tl_calendar_events WHERE published=? AND startDate>? AND (eventType=? OR eventType=? OR eventType=?) ORDER BY startDate ASC')
            ->limit(self::LIMIT)
            ->execute('1', time(), 'tour', 'course', 'lastMinuteTour')
        ;

        while ($objEvent->next()) {
            $eventsModel = $calendarEventsModelAdapter->findByPk($objEvent->id);
            $item = new \FeedItem();
            $item->title = $objEvent->title;
            $item->link = $eventsAdapter->generateEventUrl($eventsModel, true);
            $item->description = $objEvent->teaser;
            //$item->pubDate = $dateAdapter->parse('Y-m-d', $eventsModel->tstamp);
            //$item->author = CalendarEventsHelper::getMainInstructorName($eventsModel);
            $additional = [
                'pubDate' => $dateAdapter->parse('Y-m-d', $eventsModel->tstamp),
                'author' => CalendarEventsHelper::getMainInstructorName($eventsModel),
                'tourdb:startdate' => $dateAdapter->parse('Y-m-d', $eventsModel->startDate),
                'tourdb:enddate' => $dateAdapter->parse('Y-m-d', $eventsModel->endDate),
                'tourdb:eventtype' => $objEvent->eventType,
                'tourdb:tourtype' => implode(', ', $calendarEventsHelperAdapter->getTourTypesAsArray($eventsModel, 'title')),
                'tourdb:difficulty' => implode(', ', $calendarEventsHelperAdapter->getTourTechDifficultiesAsArray($eventsModel)),
            ];

            $item->additionalElements = $additional;

            //optional
            //$item->descriptionTruncSize = 500;
            $item->descriptionHtmlSyndicated = true;

            $rss->addItem($item);
        }

        return new Response($rss->saveFeed('RSS2.0', $this->projectDir.'/web/share/feed.xml', true));
    }
}
