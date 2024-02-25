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

namespace Markocupic\SacEventToolBundle\Controller\Feed;

use Contao\CalendarEventsModel;
use Contao\CoreBundle\Framework\Adapter;
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\Environment;
use Contao\Events;
use Contao\StringUtil;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;
use Doctrine\DBAL\Result;
use Markocupic\RssFeedGeneratorBundle\Feed\FeedFactory;
use Markocupic\RssFeedGeneratorBundle\Item\Item;
use Markocupic\RssFeedGeneratorBundle\Item\ItemGroup;
use Markocupic\SacEventToolBundle\CalendarEventsHelper;
use Markocupic\SacEventToolBundle\Config\EventType;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Filesystem\Path;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class UpcomingEventsController extends AbstractController
{
    private readonly Adapter $calendarEventsModel;
    private readonly Adapter $calendarEventsHelper;
    private readonly Adapter $events;
    private readonly Adapter $environment;
    private readonly Adapter $stringUtil;

    public function __construct(
        private readonly ContaoFramework $framework,
        private readonly FeedFactory $feedFactory,
        private readonly Connection $connection,
        private readonly string $sacevtLocale,
		private readonly string $projectDir,
    ) {
        $this->calendarEventsModel = $this->framework->getAdapter(CalendarEventsModel::class);
        $this->calendarEventsHelper = $this->framework->getAdapter(CalendarEventsHelper::class);
        $this->events = $this->framework->getAdapter(Events::class);
        $this->environment = $this->framework->getAdapter(Environment::class);
        $this->stringUtil = $this->framework->getAdapter(StringUtil::class);
    }

    /**
     * Generate the RSS Feed for https://www.sac-cas.ch/de/der-sac/sektionen/sac-pilatus/.
     */
    #[Route('/_rssfeeds/sac_cas_upcoming_events/{section}/{limit}', name: 'sac_event_tool_rss_feed_sac_cas_upcoming_events', defaults: ['_scope' => 'frontend'])]
    public function printLatestEvents(string $section = '4250', int $limit = 100): Response
    {
        // Initialize Contao framework
        $this->framework->initialize();

        $limit = $limit < 1 ? 0 : $limit;

        $arrSectionIds = $this->connection->fetchFirstColumn('SELECT sectionId FROM tl_sac_section', []);

        if (!\in_array($section, $arrSectionIds, true)) {
            return new Response('Section with ID '.$section.' not found. Please use a valid section ID like '.implode(', ', $arrSectionIds).'.');
        }

        $sectionName = $this->connection->fetchOne('SELECT name FROM tl_sac_section WHERE sectionId = ?', [$section]);

        $filePath = 'share/rss_feed_'.str_replace(' ', '_', strtolower($sectionName)).'.xml';

        // Create feed
        $rss = $this->feedFactory->createFeed('utf-8');

        // Set namespace
        $rss->setRootAttributes([
            // Add xmlns:tourdb' => 'http://www.tourenangebot.ch/schema/tourdbrss/1.0,
            // otherwise SAC Bern will not recognize events start- and end-dates
            'xmlns:tourdb' => 'http://www.tourenangebot.ch/schema/tourdbrss/1.0',
            'xmlns:media' => 'http://search.yahoo.com/mrss/',
            'xmlns:atom' => 'http://www.w3.org/2005/Atom',
        ]);

        // Add channel fields

        // Add an atom link
        $rss->addChannelField(
            new Item('atom:link', '', [], [
                'href' => $this->environment->get('base').$filePath,
                'rel' => 'self',
                'type' => 'application/rss+xml',
            ])
        );

        $rss->addChannelField(
            new Item('title', str_replace(['&quot;', '&#40;', '&#41;'], ['"', '(', ')'], $this->stringUtil->specialchars(strip_tags($this->stringUtil->stripInsertTags($sectionName.' upcoming events')))))
        );

        $rss->addChannelField(
            new Item('description', $this->stringUtil->specialchars('Provides the latest events for https://www.sac-cas.ch/de/der-sac/sektionen'), ['cdata' => false])
        );

        $rss->addChannelField(
            new Item('link', $this->stringUtil->specialchars($this->environment->get('url')))
        );

        $rss->addChannelField(
            new Item('language', $this->sacevtLocale)
        );

        $rss->addChannelField(
            new Item('copyright', 'Copyright '.date('Y').', '.$sectionName)
        );

        $rss->addChannelField(
            new Item('pubDate', date('r', time() - 3600))
        );

        $rss->addChannelField(
            new Item('lastBuildDate', date('r', time()))
        );

        $rss->addChannelField(
            new Item('ttl', '60')
        );

        $rss->addChannelField(
            new Item('category', 'Mountaineering events: '.$sectionName)
        );

        $rss->addChannelField(
            new Item('category', 'Touren')
        );

        $rss->addChannelField(
            new Item('generator', $this->stringUtil->specialchars(self::class))
        );

        $stmt = $this->getEvents($section, $limit);

        while (false !== ($arrEvent = $stmt->fetchAssociative())) {
            $eventsModel = $this->calendarEventsModel->findByPk($arrEvent['id']);

            $arrEvent = array_map(
                static fn ($varValue) => str_replace(['&quot;', '&#40;', '&#41;', '[-]', '&shy;', '[nbsp]', '&nbsp;'], ['"', '(', ')', '', '', ' ', ' '], (string) $varValue),
                $arrEvent
            );

            $rss->addChannelItemField(
                new ItemGroup('item', [
                    new Item('title', strip_tags($this->stringUtil->stripInsertTags($arrEvent['title'])), ['cdata' => true]),
                    new Item('link', $this->stringUtil->specialchars($this->events->generateEventUrl($eventsModel, true))),
                    new Item('description', strip_tags(preg_replace('/[\n\r]+/', ' ', $arrEvent['teaser'])), ['cdata' => true]),
                    new Item('pubDate', date('r', (int) $eventsModel->startDate)),
                    new Item('author', implode(', ', $this->calendarEventsHelper->getInstructorNamesAsArray($eventsModel))),
                    new Item('guid', $this->stringUtil->specialchars($this->events->generateEventUrl($eventsModel, true))),
                    new Item('tourdb:startdate', date('Y-m-d', (int) $eventsModel->startDate)),
                    new Item('tourdb:enddate', date('Y-m-d', (int) $eventsModel->endDate)),
                ])
            );
        }

        return $rss->render(Path::join($this->projectDir, 'public').'/'.$filePath);
    }

    /**
     * @throws Exception
     */
    private function getEvents(string $section, int $limit): Result|null
    {
        $qb = $this->connection->createQueryBuilder();
        $qb->select('id')
            ->from('tl_event_organizer', 't')
            ->where($qb->expr()->like('t.belongsToOrganization', $qb->expr()->literal('%'.$section.'%')))
        ;

        $arrOrgIds = $qb->fetchFirstColumn();

        if (empty($arrOrgIds)) {
            return null;
        }

        $qb = $this->connection->createQueryBuilder();
        $qb->select('*')
            ->from('tl_calendar_events', 't')
            ->where('t.published = :published')
            ->andWhere('t.startDate > :startDate')
            ->andWhere($qb->expr()->in('t.eventType', ':eventTypes'))
            ->setParameter('published', '1')
            ->setParameter('startDate', time())
            ->setParameter('eventTypes', EventType::ALL, ArrayParameterType::STRING)
        ;

        $arrOrExpr = [];

        foreach ($arrOrgIds as $orgId) {
            $orgId = (string) $orgId;
            $arrOrExpr[] = $qb->expr()->like('t.organizers', $qb->expr()->literal('%:"'.$orgId.'";%'));
        }

        if (!empty($arrOrExpr)) {
            $qb->andWhere($qb->expr()->or(...$arrOrExpr));
        }

        $qb->orderBy('t.startDate', 'ASC');
        $qb->setMaxResults($limit);

        return $qb->executeQuery();
    }
}
