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
use Doctrine\DBAL\Types\Types;
use Markocupic\RssFeedGeneratorBundle\Feed\FeedFactory;
use Markocupic\RssFeedGeneratorBundle\Item\Item;
use Markocupic\RssFeedGeneratorBundle\Item\ItemGroup;
use Markocupic\SacEventToolBundle\Config\EventType;
use Markocupic\SacEventToolBundle\Util\CalendarEventsUtil;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Filesystem\Path;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Routing\Annotation\Route;

class UpcomingEventsController extends AbstractController
{
    // Maximum allowed limit to prevent DoS via large queries
    private const int MAX_LIMIT = 1000;

    private readonly Adapter $calendarEventsModel;

    private readonly Adapter $events;

    private readonly Adapter $environment;

    private readonly Adapter $stringUtil;

    public function __construct(
        private readonly CalendarEventsUtil $calendarEventsUtil,
        private readonly Connection $connection,
        private readonly ContaoFramework $framework,
        private readonly FeedFactory $feedFactory,
        private readonly LockFactory $lockFactory,
        private readonly string $sacevtLocale,
        private readonly string $projectDir,
    ) {
        $this->calendarEventsModel = $this->framework->getAdapter(CalendarEventsModel::class);
        $this->events = $this->framework->getAdapter(Events::class);
        $this->environment = $this->framework->getAdapter(Environment::class);
        $this->stringUtil = $this->framework->getAdapter(StringUtil::class);
    }

    /**
     * Generate the RSS Feed for https://www.sac-cas.ch/de/der-sac/sektionen/sac-pilatus/.
     */
    #[Route('/_rssfeeds/sac_cas_upcoming_events/{section}/{limit}',
        name: 'sac_event_tool_rss_feed_sac_cas_upcoming_events',
        requirements: ['section' => '\d+', 'limit' => '\d+'],
        defaults: ['_scope' => 'frontend'])]
    public function printLatestEvents(int $section = 4250, int $limit = 100): Response
    {
        // Initialize Contao framework
        $this->framework->initialize();

        // Enforce bounds on limit to prevent DoS via massive queries
        $limit = min(max(1, $limit), self::MAX_LIMIT);

        $arrSectionIds = $this->connection->fetchFirstColumn('SELECT sectionId FROM tl_sac_section', []);

        // Do not disclose valid section IDs in the error response
        if (!\in_array($section, array_map('intval', $arrSectionIds), true)) {
            return new Response('Section not found.', Response::HTTP_NOT_FOUND);
        }

        $sectionName = $this->connection->fetchOne('SELECT name FROM tl_sac_section WHERE sectionId = ?', [$section]);

        // Sanitize section-name to prevent path traversal when building the file path.
        // Only allow alphanumeric characters, hyphens and underscores.
        $safeName = preg_replace('/[^a-z0-9_-]/', '_', strtolower((string) $sectionName));
        $filePath = 'share/rss_feed_'.$safeName.'.xml';
        $absolutePath = Path::join($this->projectDir, 'public', $filePath);

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

        // Add channel fields Add an atom link
        $rss->addChannelField(
            new Item('atom:link', '', [], [
                'href' => $this->environment->get('base').$filePath,
                'rel' => 'self',
                'type' => 'application/rss+xml',
            ]),
        );

        $rss->addChannelField(
            new Item('title', str_replace(['&quot;', '&#40;', '&#41;'], ['"', '(', ')'], $this->stringUtil->specialchars(strip_tags($this->stringUtil->stripInsertTags($sectionName.' upcoming events'))))),
        );

        $rss->addChannelField(
            new Item('description', $this->stringUtil->specialchars('Provides the latest events for https://www.sac-cas.ch/de/der-sac/sektionen'), ['cdata' => false]),
        );

        $rss->addChannelField(
            new Item('link', $this->stringUtil->specialchars($this->environment->get('url'))),
        );

        $rss->addChannelField(
            new Item('language', $this->sacevtLocale),
        );

        $rss->addChannelField(
            new Item('copyright', 'Copyright '.date('Y').', '.$sectionName),
        );

        $rss->addChannelField(
            new Item('pubDate', date('r', time() - 3600)),
        );

        $rss->addChannelField(
            new Item('lastBuildDate', date('r', time())),
        );

        $rss->addChannelField(
            new Item('ttl', '60'),
        );

        $rss->addChannelField(
            new Item('category', 'Mountaineering events: '.$sectionName),
        );

        $rss->addChannelField(
            new Item('category', 'Touren'),
        );

        $rss->addChannelField(
            new Item('generator', $this->stringUtil->specialchars(self::class)),
        );

        // Guard against null return from getEvents() (no organizers found)
        $stmt = $this->getEvents($section, $limit);

        if (null !== $stmt) {
            $events = $stmt->fetchAllAssociative();

            foreach ($events as $event) {
                $eventsModel = $this->calendarEventsModel->findById($event['id']);

                $event = array_map(
                    static fn ($varValue) => str_replace(['&quot;', '&#40;', '&#41;', '[-]', '&shy;', '[nbsp]', '&nbsp;'], ['"', '(', ')', '', '', ' ', ' '], (string) $varValue),
                    $event,
                );

                // Escape instructor names to prevent XML injection in the author field
                $authorNames = array_map(
                    static fn (string $name) => htmlspecialchars($name, ENT_XML1 | ENT_QUOTES, 'UTF-8'),
                    $this->calendarEventsUtil->getInstructorNamesAsArray($eventsModel),
                );

                $rss->addChannelItemField(
                    new ItemGroup('item', [
                        new Item('title', strip_tags($this->stringUtil->stripInsertTags($event['title'])), ['cdata' => true]),
                        new Item('link', $this->stringUtil->specialchars($this->events->generateEventUrl($eventsModel, true))),
                        new Item('description', strip_tags(preg_replace('/[\n\r]+/', ' ', $event['teaser'])), ['cdata' => true]),
                        new Item('pubDate', date('r', (int) $eventsModel->startDate)),
                        new Item('author', implode(', ', $authorNames)),
                        new Item('guid', $this->stringUtil->specialchars($this->events->generateEventUrl($eventsModel, true))),
                        new Item('tourdb:startdate', date('Y-m-d', (int) $eventsModel->startDate)),
                        new Item('tourdb:enddate', date('Y-m-d', (int) $eventsModel->endDate)),
                    ]),
                );
            }
        }

        // Use an exclusive lock to prevent race conditions when multiple requests
        // try to write the same feed file concurrently.
        $lock = $this->lockFactory->createLock(self::class);
        $lock->acquire(true);

        try {
            $response = $rss->render($absolutePath);
        } finally {
            $lock->release();
        }

        return $response;
    }

    /**
     * @throws Exception
     */
    private function getEvents(int $section, int $limit): Result|null
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
            ->setParameter('published', 1, Types::INTEGER)
            ->setParameter('startDate', time(), Types::INTEGER)
            ->setParameter('eventTypes', EventType::ALL, ArrayParameterType::STRING)
        ;

        $arrOrExpr = [];

        foreach ($arrOrgIds as $orgId) {
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
