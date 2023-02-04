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

namespace Markocupic\SacEventToolBundle\Controller\BackendWelcomePage;

use Contao\BackendUser;
use Contao\CalendarEventsModel;
use Contao\Config;
use Contao\CoreBundle\Csrf\ContaoCsrfTokenManager;
use Contao\CoreBundle\Framework\Adapter;
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\System;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;
use Markocupic\SacEventToolBundle\CalendarEventsHelper;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Security;
use Twig\Environment as Twig;
use Twig\Error\LoaderError;
use Twig\Error\RuntimeError;
use Twig\Error\SyntaxError;

class DashboardController
{
    private Adapter $calendarEventsHelperAdapter;
    private Adapter $configAdapter;

    public function __construct(
        ContaoFramework $framework,
        private readonly Connection $connection,
        private readonly Twig $twig,
        private readonly Security $security,
        private readonly ContaoCsrfTokenManager $contaoCsrfTokenManager,
    ) {
        // Adapters
        $this->calendarEventsHelperAdapter = $framework->getAdapter(CalendarEventsHelper::class);
        $this->configAdapter = $framework->getAdapter(Config::class);
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
            $html = $this->twig->render(
                '@MarkocupicSacEventTool/BackendWelcomePage/dashboard.html.twig',
                [
                    'upcoming_events' => $this->prepareForTwig($this->getUpcomingEvents($user)),
                    'past_events' => $this->prepareForTwig($this->getPastEvents($user)),
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

    private function prepareForTwig(array $arrEvents): array
    {
        $events = [];
        $rt = $this->contaoCsrfTokenManager->getToken(System::getContainer()->getParameter('contao.csrf_token_name'));

        foreach ($arrEvents as $row) {
            $event = [];
            $eventModel = CalendarEventsModel::findByPk($row['id']);
            $linkEvent = sprintf('contao/main.php?do=sac_calendar_events_tool&table=tl_calendar_events&id=%s&act=edit&rt=%s', $eventModel->id, $rt);
            $linkRegistrations = sprintf('contao/main.php?do=sac_calendar_events_tool&table=tl_calendar_events_member&id=%s&rt=%s', $eventModel->id, $rt);

            $event['row_class'] = $eventModel->endDate > time() ? 'upcoming-event' : 'past-event';
            $event['badge'] = $this->calendarEventsHelperAdapter->getEventStateOfSubscriptionBadgesString($eventModel);
            $event['title'] = $eventModel->title;
            $event['date'] = date($this->configAdapter->get('dateFormat'), (int) $eventModel->startDate);
            $event['link_event'] = $linkEvent;
            $event['link_registrations'] = $linkRegistrations;

            $events[] = $event;
        }

        return $events;
    }
}
