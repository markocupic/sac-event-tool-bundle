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

namespace Markocupic\SacEventToolBundle\Controller\BackendHomeScreen;

use Contao\BackendUser;
use Contao\CalendarEventsModel;
use Contao\Config;
use Contao\CoreBundle\Csrf\ContaoCsrfTokenManager;
use Contao\CoreBundle\Framework\Adapter;
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\StringUtil;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;
use Markocupic\SacEventToolBundle\CalendarEventsHelper;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Security;
use Twig\Environment as Twig;
use Twig\Error\LoaderError;
use Twig\Error\RuntimeError;
use Twig\Error\SyntaxError;

class DashboardController
{
    private Adapter $calendarEventsHelperAdapter;
    private Adapter $calendarEventsModelAdapter;
    private Adapter $configAdapter;
    private Adapter $stringUtilAdapter;

    public function __construct(
        private readonly ContaoFramework $framework,
        private readonly Connection $connection,
        private readonly RequestStack $requestStack,
        private readonly Twig $twig,
        private readonly Security $security,
        private readonly ContaoCsrfTokenManager $contaoCsrfTokenManager,
    ) {
        // Adapters
        $this->calendarEventsHelperAdapter = $this->framework->getAdapter(CalendarEventsHelper::class);
        $this->calendarEventsModelAdapter = $this->framework->getAdapter(CalendarEventsModel::class);
        $this->configAdapter = $this->framework->getAdapter(Config::class);
        $this->stringUtilAdapter = $this->framework->getAdapter(StringUtil::class);
    }

    /**
     * @throws Exception
     * @throws LoaderError
     * @throws RuntimeError
     * @throws SyntaxError
     */
    public function generate(): Response
    {
        $html = '';

        /** @var BackendUser $user */
        $user = $this->security->getUser();

        if ($user instanceof BackendUser) {
            $upcomingEvents = $this->getUpcomingEvents($user);
            $pastEvents = $this->getPastEvents($user);

            $events = array_merge(
                [['separator' => 'upcoming-events']],
                $this->prepareForTwig($upcomingEvents, 'upcoming-event'),
                [['separator' => 'past-events']],
                $this->prepareForTwig($pastEvents, 'past-event')
            );

            $html = $this->twig->render(
                '@MarkocupicSacEventTool/BackendHomeScreen/dashboard.html.twig',
                [
                    'events' => $events,
                    'has_upcoming_events' => !empty($upcomingEvents),
                    'has_past_events' => !empty($pastEvents),
                ]
            );
        }

        return new Response($html);
    }

    /**
     * @throws Exception
     */
    private function getUpcomingEvents(BackendUser $user): array
    {
        $timeCut = time() - 15 * 24 * 3600; // 14 + 1 days

        $result = $this->connection->executeQuery(
            'SELECT * FROM tl_calendar_events AS t1 WHERE (t1.registrationGoesTo = ? OR t1.id IN (SELECT t2.pid FROM tl_calendar_events_instructor AS t2 WHERE t2.userId = ?)) AND t1.startDate > ? ORDER BY t1.startDate',
            [
                $user->id,
                $user->id,
                $timeCut,
            ]
        );

        return $result->fetchAllAssociative();
    }

    /**
     * @throws Exception
     */
    private function getPastEvents(BackendUser $user): array
    {
        $timeCut = time() - 15 * 24 * 3600; // 14 + 1 days

        $result = $this->connection->executeQuery(
            'SELECT * FROM tl_calendar_events AS t1 WHERE (t1.registrationGoesTo = ? OR t1.id IN (SELECT t2.pid FROM tl_calendar_events_instructor AS t2 WHERE t2.userId = ?)) AND t1.startDate <= ? AND t1.startDate > ? ORDER BY t1.startDate DESC LIMIT 0,10',
            [
                $user->id,
                $user->id,
                $timeCut,
                time() - 1.5 * 365 * 24 * 3600,
            ]
        );

        return $result->fetchAllAssociative();
    }

    /**
     * @throws Exception
     */
    private function prepareForTwig(array $arrEvents, string $rowClass): array
    {
        $events = [];
        $rt = $this->contaoCsrfTokenManager->getDefaultTokenValue();
        $refId = $this->requestStack->getCurrentRequest()->attributes->get('_contao_referer_id');

        foreach ($arrEvents as $row) {
            $eventModel = $this->calendarEventsModelAdapter->findByPk($row['id']);
            $title = $this->stringUtilAdapter->decodeEntities($eventModel->title);
            $title = $this->stringUtilAdapter->restoreBasicEntities($title);

            $hrefEvent = sprintf(
                'contao?do=sac_calendar_events_tool&table=tl_calendar_events&id=%s&act=edit&rt=%s&ref=%s',
                $eventModel->id,
                $rt,
                $refId,
            );

            $hrefRegistrations = sprintf(
                'contao?do=sac_calendar_events_tool&table=tl_calendar_events_member&id=%s&rt=%s&ref=%s',
                $eventModel->id,
                $rt,
                $refId,
            );

            $hrefEventListing = sprintf(
                'contao?do=sac_calendar_events_tool&table=tl_calendar_events&id=%d&rt=%s&ref=%s',
                $eventModel->pid,
                $rt,
                $refId,
            );

            $event = [];
            $event['row_class'] = $rowClass;
            $event['badge'] = $this->calendarEventsHelperAdapter->getEventStateOfSubscriptionBadgesString($eventModel);
            $event['title'] = $title;
            $event['date'] = date($this->configAdapter->get('dateFormat'), (int) $eventModel->startDate);
            $event['href_eventListing'] = $hrefEventListing;
            $event['href_email'] = $this->generateEmailHref($eventModel);
            $event['href_event'] = $hrefEvent;
            $event['href_preview'] = $this->calendarEventsHelperAdapter->generateEventPreviewUrl($eventModel);
            $event['href_print_report'] = $this->generatePrintReportHref($eventModel);
            $event['href_registrations'] = $hrefRegistrations;
            $event['href_report'] = $this->generateReportHref($eventModel);

            $events[] = $event;
        }

        return $events;
    }

    /**
     * @throws Exception
     */
    private function generateEmailHref(CalendarEventsModel $eventModel): string|null
    {
        $rt = $this->contaoCsrfTokenManager->getDefaultTokenValue();
        $refId = $this->requestStack->getCurrentRequest()->attributes->get('_contao_referer_id');

        $regId = $this->connection->fetchOne('SELECT id FROM tl_calendar_events_member WHERE eventId = ?', [$eventModel->id]);

        if ($regId) {
            return sprintf('contao?do=sac_calendar_events_tool&id=%d&table=tl_calendar_events_member&act=edit&action=sendEmail&eventId=%d&rt=%s&ref=%s', $regId, $eventModel->id, $rt, $refId);
        }

        return null;
    }

    private function generateReportHref(CalendarEventsModel $eventModel): string|null
    {
        $rt = $this->contaoCsrfTokenManager->getDefaultTokenValue();
        $refId = $this->requestStack->getCurrentRequest()->attributes->get('_contao_referer_id');

        if ('tour' === $eventModel->eventType || 'lastMinuteTour' === $eventModel->eventType) {
            return sprintf('contao?act=edit&do=sac_calendar_events_tool&table=tl_calendar_events&id=%d&call=writeTourReport&rt=%s&ref=%s', $eventModel->id, $rt, $refId);
        }

        return null;
    }

    private function generatePrintReportHref(CalendarEventsModel $eventModel): string|null
    {
        $rt = $this->contaoCsrfTokenManager->getDefaultTokenValue();
        $refId = $this->requestStack->getCurrentRequest()->attributes->get('_contao_referer_id');

        if ('tour' === $eventModel->eventType || 'lastMinuteTour' === $eventModel->eventType) {
            return sprintf('contao?do=sac_calendar_events_tool&table=tl_calendar_events_instructor_invoice&id=%d&rt=%s&ref=%s', $eventModel->id, $rt, $refId);
        }

        return null;
    }
}
