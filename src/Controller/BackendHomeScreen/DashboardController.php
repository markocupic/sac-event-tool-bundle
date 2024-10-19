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

namespace Markocupic\SacEventToolBundle\Controller\BackendHomeScreen;

use Code4Nix\UriSigner\UriSigner;
use Codefog\HasteBundle\UrlParser;
use Contao\BackendUser;
use Contao\CalendarEventsModel;
use Contao\Config;
use Contao\CoreBundle\Csrf\ContaoCsrfTokenManager;
use Contao\CoreBundle\Framework\Adapter;
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\StringUtil;
use Contao\System;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;
use Markocupic\SacEventToolBundle\Config\EventType;
use Markocupic\SacEventToolBundle\Controller\BackendModule\EventParticipantEmailController;
use Markocupic\SacEventToolBundle\Util\CalendarEventsUtil;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\RouterInterface;
use Twig\Environment as Twig;
use Twig\Error\LoaderError;
use Twig\Error\RuntimeError;
use Twig\Error\SyntaxError;

class DashboardController
{
    private Adapter $calendarEventsUtilAdapter;
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
        private readonly UrlParser $urlParser,
        private readonly UriSigner $uriSigner,
        private readonly RouterInterface $router,
    ) {
        // Adapters
        $this->calendarEventsUtilAdapter = $this->framework->getAdapter(CalendarEventsUtil::class);
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
                '@MarkocupicSacEventTool/Backend/BackendHomeScreen/dashboard.html.twig',
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

        $arrAllowedCalIds = $this->getAllowedCalendarIds();
        $arrAllowedCalIds = empty($arrAllowedCalIds) ? [0] : $arrAllowedCalIds;

        $result = $this->connection->executeQuery(
            'SELECT * FROM tl_calendar_events AS t1 WHERE pid IN('.implode(',', $arrAllowedCalIds).') AND (t1.registrationGoesTo = ? OR t1.id IN (SELECT t2.pid FROM tl_calendar_events_instructor AS t2 WHERE t2.userId = ?)) AND t1.startDate > ? ORDER BY t1.startDate',
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

        $arrAllowedCalIds = $this->getAllowedCalendarIds();
        $arrAllowedCalIds = empty($arrAllowedCalIds) ? [0] : $arrAllowedCalIds;

        $result = $this->connection->executeQuery(
            'SELECT * FROM tl_calendar_events AS t1 WHERE pid IN ('.implode(',', $arrAllowedCalIds).') AND (t1.registrationGoesTo = ? OR t1.id IN (SELECT t2.pid FROM tl_calendar_events_instructor AS t2 WHERE t2.userId = ?)) AND t1.startDate <= ? AND t1.startDate > ? ORDER BY t1.startDate DESC LIMIT 0,10',
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
     * @throws \Exception
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

            $hrefEvent = $this->router->generate('contao_backend', [
                'do' => 'calendar',
                'table' => 'tl_calendar_events',
                'id' => $eventModel->id,
                'act' => 'edit',
                'rt' => $rt,
                'ref' => $refId,
            ]);

            $hrefRegistrations = $this->router->generate('contao_backend', [
                'do' => 'calendar',
                'table' => 'tl_calendar_events_member',
                'id' => $eventModel->id,
                'rt' => $rt,
                'ref' => $refId,
            ]);

            $hrefEventListing = $this->router->generate('contao_backend', [
                'do' => 'calendar',
                'table' => 'tl_calendar_events',
                'id' => $eventModel->pid,
                'rt' => $rt,
                'ref' => $refId,
            ]);

            $event = [];
            $event['row_class'] = $rowClass;
            $event['badge'] = $this->calendarEventsUtilAdapter->getEventStateOfSubscriptionBadgesString($eventModel);
            $event['title'] = $title;
            $event['date'] = date($this->configAdapter->get('dateFormat'), (int) $eventModel->startDate);
            $event['state_icon'] = $this->calendarEventsUtilAdapter->getEventStateIcon($eventModel);
            $event['release_level'] = $this->calendarEventsUtilAdapter->getEventReleaseLevelAsString($eventModel);
            $event['href_eventListing'] = $hrefEventListing;
            $event['href_email'] = $this->generateEmailHref($eventModel);
            $event['href_event'] = $hrefEvent;
            $event['href_preview'] = $this->calendarEventsUtilAdapter->generateEventPreviewUrl($eventModel);
            $event['href_print_report'] = $this->generatePrintReportHref($eventModel);
            $event['href_registrations'] = $hrefRegistrations;
            $event['href_report'] = $this->generateReportHref($eventModel);
            $event['has_filled_in_tour_report'] = $eventModel->filledInEventReportForm;

            $events[] = $event;
        }

        return $events;
    }

    /**
     * @throws Exception
     */
    private function generateEmailHref(CalendarEventsModel $eventModel): string|null
    {
        $regId = $this->connection->fetchOne('SELECT id FROM tl_calendar_events_member WHERE eventId = ?', [$eventModel->id]);

        if ($regId) {
            $url = System::getContainer()->get('router')->generate(EventParticipantEmailController::class);

            $url = $this->urlParser->addQueryString('eventId='.$eventModel->id, $url);
            $url = $this->urlParser->addQueryString('rt='.$this->contaoCsrfTokenManager->getDefaultTokenValue(), $url);
            $url = $this->urlParser->addQueryString('sid='.uniqid(), $url);

            return $this->uriSigner->sign($url);
        }

        return null;
    }

    private function generateReportHref(CalendarEventsModel $eventModel): string|null
    {
        $rt = $this->contaoCsrfTokenManager->getDefaultTokenValue();
        $refId = $this->requestStack->getCurrentRequest()->attributes->get('_contao_referer_id');

        if (EventType::TOUR === $eventModel->eventType || EventType::LAST_MINUTE_TOUR === $eventModel->eventType) {
            return $this->router->generate('contao_backend', [
                'do' => 'calendar',
                'table' => 'tl_calendar_events',
                'act' => 'edit',
                'call' => 'writeTourReport',
                'id' => $eventModel->id,
                'rt' => $rt,
                'ref' => $refId,
            ]);
        }

        return null;
    }

    private function generatePrintReportHref(CalendarEventsModel $eventModel): string|null
    {
        $rt = $this->contaoCsrfTokenManager->getDefaultTokenValue();
        $refId = $this->requestStack->getCurrentRequest()->attributes->get('_contao_referer_id');

        if (EventType::TOUR === $eventModel->eventType || EventType::LAST_MINUTE_TOUR === $eventModel->eventType) {
            return $this->router->generate('contao_backend', [
                'do' => 'calendar',
                'table' => 'tl_calendar_events_instructor_invoice',
                'id' => $eventModel->id,
                'rt' => $rt,
                'ref' => $refId,
            ]);
        }

        return null;
    }

    /**
     * @throws Exception
     *
     * @return array<int>
     */
    private function getAllowedCalendarContainerIds(): array
    {
        /** @var BackendUser $user */
        $user = $this->security->getUser();

        if ($this->security->isGranted('ROLE_ADMIN')) {
            $arrIds = $this->connection->fetchFirstColumn('SELECT id FROM tl_calendar_container');
        } else {
            $arrIds = $user->calendar_containers;

            if (!\is_array($arrIds) || empty($arrIds)) {
                $arrIds = [];
            }
        }

        return array_map('\intval', $arrIds);
    }

    /**
     * @throws Exception
     *
     * @return array<int>
     */
    private function getAllowedCalendarIds(): array
    {
        $arrCalContainerIds = $this->getAllowedCalendarContainerIds();

        /** @var BackendUser $user */
        $user = $this->security->getUser();

        if ($this->security->isGranted('ROLE_ADMIN')) {
            $arrCalendarIds = $this->connection->fetchFirstColumn('SELECT id FROM tl_calendar');
        } else {
            $arrCalendarIds = $user->calendars;

            if (!\is_array($arrCalendarIds) || empty($arrCalendarIds)) {
                $arrCalendarIds = [];
            }
        }

        $arrAllowed = [];

        foreach ($arrCalendarIds as $calId) {
            $pid = $this->connection->fetchOne('SELECT pid FROM tl_calendar WHERE id = ?', [$calId]);

            if (false !== $pid) {
                if (\in_array($pid, $arrCalContainerIds, true)) {
                    $arrAllowed[] = $calId;
                }
            }
        }

        return array_map('\intval', $arrAllowed);
    }
}
