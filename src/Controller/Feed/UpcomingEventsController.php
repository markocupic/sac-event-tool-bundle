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
use Contao\Config;
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\Date;
use Contao\Environment;
use Contao\Events;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Driver\PDO\Statement;
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
    const LIMIT = 50;

    /**
     * @var ContaoFramework
     */
    private $framework;

    /**
     * @var FeedFactory
     */
    private $feedFactory;

    /**
     * @var Connection
     */
    private $connection;

    /**
     * @var string
     */
    private $projectDir;

    /**
     * UpcomingEventsController constructor.
     */
    public function __construct(ContaoFramework $framework, FeedFactory $feedFactory, Connection $connection, string $projectDir)
    {
        $this->framework = $framework;
        $this->feedFactory = $feedFactory;
        $this->connection = $connection;
        $this->projectDir = $projectDir;

        // Initialize Contao framework
        $this->framework->initialize();
    }

    /**
     * Generate RSS Feed for https://www.sac-cas.ch/de/der-sac/sektionen/sac-pilatus/.
     *
     * @Route("/_rssfeeds/sac_cas_upcoming_events/{section}", name="sac_event_tool_rss_feed_sac_cas_upcoming_events", defaults={"_scope" = "frontend"})
     */
    public function printLatestEvents(int $section = 4250): Response
    {
        $calendarEventsModelAdapter = $this->framework->getAdapter(CalendarEventsModel::class);
        $calendarEventsHelperAdapter = $this->framework->getAdapter(CalendarEventsHelper::class);
        $eventsAdapter = $this->framework->getAdapter(Events::class);
        $configAdapter = $this->framework->getAdapter(Config::class);
        $environmentAdapter = $this->framework->getAdapter(Environment::class);

        $sacEvtConfig = $configAdapter->get('SAC-EVENT-TOOL-CONFIG'); 

        if (!isset($sacEvtConfig['SECTION_IDS'][$section])) {
            return new Response('Section with ID '.$sectionName.' not found. Please use a valid section ID like '.implode(', ', array_keys($sacEvtConfig['SECTION_IDS'])).'.');
        }

        $sectionName = $sacEvtConfig['SECTION_IDS'][$section];

        $rss = $this->feedFactory->createFeed('utf-8');

        $rss->addChannelField(
            new Item('title', $sectionName.' upcoming events')
        );

        $rss->addChannelField(
            new Item('description', 'Provides the latest events for https://www.sac-cas.ch/de/der-sac/sektionen')
        );

        $rss->addChannelField(
            new Item('link', $environmentAdapter->get('url'))
        );

        $rss->addChannelField(
            new Item('language', 'de')
        );

        $rss->addChannelField(
            new Item('copyright', 'Copyright '.date('Y').', '.$sectionName)
        );

        $rss->addChannelField(
            new Item('pubDate', date('r', (time() - 3600)))
        );

        $rss->addChannelField(
            new Item('lastBuildDate', date('r', time()))
        );

        $rss->addChannelField(
            new Item('ttl', (string) 60)
        );

        $rss->addChannelField(
            new Item('category', 'Mountaineering events')
        );

        $rss->addChannelField(
            new Item('generator', self::class)
        );

        $results = $this->getEvents($section);

        if (null !== $results) {
            while (false !== ($arrEvent = $results->fetch())) {
                $eventsModel = $calendarEventsModelAdapter->findByPk($arrEvent['id']);

                $rss->addChannelItemField(
                    new ItemGroup('item',[
                        new Item('title', $arrEvent['title']),
                        new Item('link',$eventsAdapter->generateEventUrl($eventsModel, true)),
                        new Item('description',$arrEvent['teaser'], ['cdata' => true]),
                        new Item('pubDate', date('r',(int)$eventsModel->tstamp)),
                        //new Item('author',$calendarEventsHelperAdapter->getMainInstructorName($eventsModel)),
                        new Item('guid',$eventsAdapter->generateEventUrl($eventsModel, true)),
                        new Item('tourdb:startdate',date('Y-m-d', (int) $eventsModel->startDate)),
                        new Item('tourdb:enddate',date('Y-m-d', (int) $eventsModel->endDate)),
                        new Item('tourdb:eventtype',$arrEvent['eventType']),
                        new Item('tourdb:organizers',implode(', ', CalendarEventsHelper::getEventOrganizersAsArray($eventsModel))),
                        new Item('tourdb:instructors',implode(', ', $calendarEventsHelperAdapter->getInstructorNamesAsArray($eventsModel))),
                        new Item('tourdb:tourtype',implode(', ', $calendarEventsHelperAdapter->getTourTypesAsArray($eventsModel, 'title'))),
                        new Item('tourdb:difficulty',implode(', ', $calendarEventsHelperAdapter->getTourTechDifficultiesAsArray($eventsModel))),
                    ])
                );
            }
        }

        $filename = 'rss_feed_'.str_replace(' ', '_', strtolower($sectionName)).'.xml';

        return $rss->render($this->projectDir.'/web/share/'.$filename);
    }

    private function getEvents(int $section): ?Statement
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
        $qb->setMaxResults(self::LIMIT);

        return $qb->execute();
    }
}
