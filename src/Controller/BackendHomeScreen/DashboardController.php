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
use Markocupic\SacEventToolBundle\CalendarEventsHelper;
use Markocupic\SacEventToolBundle\Config\EventType;
use Markocupic\SacEventToolBundle\Controller\BackendModule\EventParticipantEmailController;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
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
            $event['state_icon'] = $this->calendarEventsHelperAdapter->getEventStateIcon($eventModel);
            $event['release_level'] = $this->calendarEventsHelperAdapter->getEventReleaseLevelAsString($eventModel);
            $event['href_eventListing'] = $hrefEventListing;
            $event['href_email'] = $this->generateEmailHref($eventModel);
            $event['href_event'] = $hrefEvent;
            $event['href_preview'] = $this->calendarEventsHelperAdapter->generateEventPreviewUrl($eventModel);
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
            return System::getContainer()->get('code4nix_uri_signer.uri_signer')->sign(System::getContainer()->get('router')->generate(EventParticipantEmailController::class, [
                'event_id' => $eventModel->id,
                'rt' => $this->contaoCsrfTokenManager->getDefaultTokenValue(),
                'sid' => uniqid(),
            ]));
        }

        return null;
    }

    private function generateReportHref(CalendarEventsModel $eventModel): string|null
    {
        $rt = $this->contaoCsrfTokenManager->getDefaultTokenValue();
        $refId = $this->requestStack->getCurrentRequest()->attributes->get('_contao_referer_id');

        if (EventType::TOUR === $eventModel->eventType || EventType::LAST_MINUTE_TOUR === $eventModel->eventType) {
            return sprintf('contao?act=edit&do=sac_calendar_events_tool&table=tl_calendar_events&id=%d&call=writeTourReport&rt=%s&ref=%s', $eventModel->id, $rt, $refId);
        }

        return null;
    }

    private function generatePrintReportHref(CalendarEventsModel $eventModel): string|null
    {
        $rt = $this->contaoCsrfTokenManager->getDefaultTokenValue();
        $refId = $this->requestStack->getCurrentRequest()->attributes->get('_contao_referer_id');

        if (EventType::TOUR === $eventModel->eventType || EventType::LAST_MINUTE_TOUR === $eventModel->eventType) {
            return sprintf('contao?do=sac_calendar_events_tool&table=tl_calendar_events_instructor_invoice&id=%d&rt=%s&ref=%s', $eventModel->id, $rt, $refId);
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
