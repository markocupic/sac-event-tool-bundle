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

namespace Markocupic\SacEventToolBundle\Controller\Feed;

use Contao\CalendarEventsModel;
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\Environment;
use Contao\Events;
use Contao\StringUtil;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ForwardCompatibility\Result;
use Doctrine\DBAL\Query\QueryBuilder;
use Markocupic\RssFeedGeneratorBundle\Feed\FeedFactory;
use Markocupic\RssFeedGeneratorBundle\Item\Item;
use Markocupic\RssFeedGeneratorBundle\Item\ItemGroup;
use Markocupic\SacEventToolBundle\CalendarEventsHelper;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * Class UpcomingEventsController.
 */
class UpcomingEventsController extends AbstractController
{
    private ContaoFramework $framework;

    private FeedFactory $feedFactory;

    private Connection $connection;

    private string $projectDir;

    private string $locale;

    public function __construct(ContaoFramework $framework, FeedFactory $feedFactory, Connection $connection, string $projectDir, string $locale)
    {
        $this->framework = $framework;
        $this->feedFactory = $feedFactory;
        $this->connection = $connection;
        $this->projectDir = $projectDir;
        $this->locale = $locale;
    }

    /**
     * Generate RSS Feed for https://www.sac-cas.ch/de/der-sac/sektionen/sac-pilatus/.
     *
     * @Route("/_rssfeeds/sac_cas_upcoming_events/{section}/{limit}", name="sac_event_tool_rss_feed_sac_cas_upcoming_events", defaults={"_scope" = "frontend"})
     */
    public function printLatestEvents(int $section = 4250, int $limit = 100): Response
    {
        // Initialize Contao framework
        $this->framework->initialize();

        $limit = $limit < 1 ? 0 : $limit;

        $calendarEventsModelAdapter = $this->framework->getAdapter(CalendarEventsModel::class);
        $calendarEventsHelperAdapter = $this->framework->getAdapter(CalendarEventsHelper::class);
        $eventsAdapter = $this->framework->getAdapter(Events::class);
        $environmentAdapter = $this->framework->getAdapter(Environment::class);
        $stringUtilAdapter = $this->framework->getAdapter(StringUtil::class);

        $arrSectionIds = $this->connection->fetchFirstColumn('SELECT sectionId FROM tl_sac_section', []);

        if (!\in_array($section, $arrSectionIds, false)) {
            return new Response('Section with ID '.$section.' not found. Please use a valid section ID like '.implode(', ', $arrSectionIds).'.');
        }

        $sectionName = $this->connection->fetchOne('SELECT name FROM tl_sac_section WHERE sectionId = ?', [$section]);

        $filePath = 'share/rss_feed_'.str_replace(' ', '_', strtolower($sectionName)).'.xml';

        // Create feed
        $rss = $this->feedFactory->createFeed('utf-8');

        // Set namespace
        $rss->setRootAttributes([
            // Add xmlns:tourdb' => 'http://www.tourenangebot.ch/schema/tourdbrss/1.0,
            // otherwise SAC Bern will not recognize events startdates and enddates
            'xmlns:tourdb' => 'http://www.tourenangebot.ch/schema/tourdbrss/1.0',
            'xmlns:media' => 'http://search.yahoo.com/mrss/',
            'xmlns:atom' => 'http://www.w3.org/2005/Atom',
        ]);

        // Add channel fields

        // Add atom link
        $rss->addChannelField(
            new Item('atom:link', '', [], [
                'href' => $environmentAdapter->get('base').$filePath,
                'rel' => 'self',
                'type' => 'application/rss+xml',
            ])
        );

        $rss->addChannelField(
            new Item('title', str_replace(['&quot;', '&#40;', '&#41;'], ['"', '(', ')'], $stringUtilAdapter->specialchars(strip_tags($stringUtilAdapter->stripInsertTags($sectionName.' upcoming events')))))
        );

        $rss->addChannelField(
            new Item('description', $stringUtilAdapter->specialchars('Provides the latest events for https://www.sac-cas.ch/de/der-sac/sektionen'), ['cdata' => false])
        );

        $rss->addChannelField(
            new Item('link', $stringUtilAdapter->specialchars($environmentAdapter->get('url')))
        );

        $rss->addChannelField(
            new Item('language', $this->locale)
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
            new Item('ttl', (string) 60)
        );

        $rss->addChannelField(
            new Item('category', 'Mountaineering events: '.$sectionName)
        );

        $rss->addChannelField(
            new Item('category', 'Touren')
        );

        $rss->addChannelField(
            new Item('generator', $stringUtilAdapter->specialchars(self::class))
        );

        $results = $this->getEvents($section, $limit);

        if (null !== $results) {
            while (false !== ($arrEvent = $results->fetch())) {
                $eventsModel = $calendarEventsModelAdapter->findByPk($arrEvent['id']);

                $arrEvent = array_map(
                    static fn ($varValue) => str_replace(['&quot;', '&#40;', '&#41;', '[-]', '&shy;', '[nbsp]', '&nbsp;'], ['"', '(', ')', '', '', ' ', ' '], $varValue),
                    $arrEvent
                );
                $rss->addChannelItemField(
                    new ItemGroup('item', [
                        new Item('title', strip_tags($stringUtilAdapter->stripInsertTags($arrEvent['title'])), ['cdata' => true]),
                        new Item('link', $stringUtilAdapter->specialchars($eventsAdapter->generateEventUrl($eventsModel, true))),
                        new Item('description', strip_tags(preg_replace('/[\n\r]+/', ' ', $arrEvent['teaser'])), ['cdata' => true]),
                        new Item('pubDate', date('r', (int) $eventsModel->startDate)),
                        new Item('author', implode(', ', $calendarEventsHelperAdapter->getInstructorNamesAsArray($eventsModel))),
                        //new Item('author',$calendarEventsHelperAdapter->getMainInstructorName($eventsModel)),
                        new Item('guid', $stringUtilAdapter->specialchars($eventsAdapter->generateEventUrl($eventsModel, true))),
                        new Item('tourdb:startdate', date('Y-m-d', (int) $eventsModel->startDate)),
                        new Item('tourdb:enddate', date('Y-m-d', (int) $eventsModel->endDate)),
                        //new Item('tourdb:eventtype',$arrEvent['eventType']),
                        //new Item('tourdb:organizers',implode(', ', CalendarEventsHelper::getEventOrganizersAsArray($eventsModel))),
                        //new Item('tourdb:instructors',implode(', ', $calendarEventsHelperAdapter->getInstructorNamesAsArray($eventsModel))),
                        //new Item('tourdb:tourtype',implode(', ', $calendarEventsHelperAdapter->getTourTypesAsArray($eventsModel, 'title'))),
                        //new Item('tourdb:difficulty',implode(', ', $calendarEventsHelperAdapter->getTourTechDifficultiesAsArray($eventsModel))),
                    ])
                );
            }
        }

        return $rss->render($this->projectDir.'/web/'.$filePath);
    }

    private function getEvents(int $section, int $limit): ?Result
    {
        $qb = $this->connection->createQueryBuilder();
        $qb->select('id')
            ->from('tl_event_organizer', 't')
            ->where($qb->expr()->like('t.belongsToOrganization', $qb->expr()->literal('%'.$section.'%')))
        ;

        $arrOrgIds = $qb->execute()->fetchAll(\PDO::FETCH_COLUMN, 0);

        if (!\is_array($arrOrgIds) || empty($arrOrgIds)) {
            return null;
        }

        /** @var QueryBuilder $qb */
        $qb = $this->connection->createQueryBuilder();
        $qb->select('*')
            ->from('tl_calendar_events', 't')
            ->where('t.published = :published')
            ->andWhere('t.startDate > :startDate')
            ->andWhere(
                $qb->expr()->or(
                    $qb->expr()->andX("t.eventType = 'tour'"),
                    $qb->expr()->andX("t.eventType = 'course'"),
                    $qb->expr()->andX("t.eventType = 'lastMinuteTour'"),
                    $qb->expr()->andX("t.eventType = 'tour'"),
                )
            )
        ;
        $qb->setParameter('published', '1');
        $qb->setParameter('startDate', time());

        $orxOrg = $qb->expr()->orX();

        foreach ($arrOrgIds as $orgId) {
            $orgId = (string) $orgId;
            $orxOrg->add($qb->expr()->like('t.organizers', $qb->expr()->literal('%:"'.$orgId.'";%')));
        }
        $qb->andWhere($orxOrg);

        $qb->orderBy('t.startDate', 'ASC');
        $qb->setMaxResults($limit);

        return $qb->execute();
    }
}
