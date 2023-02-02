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

namespace Markocupic\SacEventToolBundle\EventListener\Contao;

use Contao\BackendUser;
use Contao\CalendarEventsModel;
use Contao\Config;
use Contao\CoreBundle\DependencyInjection\Attribute\AsHook;
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\Database;
use Contao\Date;
use Contao\System;
use Markocupic\SacEventToolBundle\CalendarEventsHelper;
use Markocupic\SacEventToolBundle\ContaoScope\ContaoScope;

/**
 * List all upcoming events (where logged in backend user is the main instructor).
 */
#[AsHook('getSystemMessages', priority: 100)]
class GetSystemMessagesListener
{
    private ContaoFramework $framework;
    private ContaoScope $contaoScope;

    public function __construct(ContaoFramework $framework, ContaoScope $contaoScope)
    {
        $this->framework = $framework;
        $this->contaoScope = $contaoScope;
    }

    public function __invoke(): string
    {
        $strBuffer = '';

        if ($this->contaoScope->isBackend()) {
            $backendUserAdapter = $this->framework->getAdapter(BackendUser::class);
            $databaseAdapter = $this->framework->getAdapter(Database::class);
            $calendarEventsHelperAdapter = $this->framework->getAdapter(CalendarEventsHelper::class);
            $dateAdapter = $this->framework->getAdapter(Date::class);
            $configAdapter = $this->framework->getAdapter(Config::class);

            $objUser = $backendUserAdapter->getInstance();

            $timeCut = time() - 15 * 24 * 3600; // 14 + 1 days

            if ($objUser->id > 0) {
                // Dashboard: List all upcoming events where user acts as an instructor or where registration goes to the logged in user.
                $objEvent = $databaseAdapter->getInstance()
                    ->prepare('SELECT * FROM tl_calendar_events AS t1 WHERE (t1.registrationGoesTo=? OR t1.id IN (SELECT t2.pid FROM tl_calendar_events_instructor AS t2 WHERE t2.userId=?)) AND t1.startDate>? ORDER BY t1.startDate')
                    ->execute($objUser->id, $objUser->id, $timeCut)
                ;

                if ($objEvent->numRows) {
                    $strBuffer .= '<h3>'.$GLOBALS['TL_LANG']['MSC']['bmd_yourUpcomingEvents'].'</h3>';
                    $strBuffer .= '<table id="tl_upcoming_events" class="tl_listing">';
                    $strBuffer .= '<thead><tr><th>Anmeld.</th><th>Datum &amp; Eventname</th><th>Teiln.</th></tr></thead>';
                    $strBuffer .= '<tbody>';

                    $container = System::getContainer();
                    $rt = $container->get('contao.csrf.token_manager')->getToken($container->getParameter('contao.csrf_token_name'))->getValue();

                    while ($objEvent->next()) {
                        $eventModel = CalendarEventsModel::findByPk($objEvent->id);

                        $strCSSRowClass = $objEvent->endDate > time() ? 'upcoming-event' : 'past-event';
                        $link = sprintf('contao/main.php?do=sac_calendar_events_tool&table=tl_calendar_events&id=%s&act=edit&rt=%s', $objEvent->id, $rt);
                        $linkMemberList = sprintf('contao/main.php?do=sac_calendar_events_tool&table=tl_calendar_events_member&id=%s&rt=%s', $objEvent->id, $rt);

                        $strBuffer .= sprintf(
                            '<tr class="hover-row %s"><td>%s</td><td>[%s] <a href="%s" style="text-decoration:underline" title="Event \'%s\' bearbeiten"><strong>%s</strong></a></td><td><a href="%s" style="text-decoration:underline" title="Zur TN-Liste und Rapporte für \'%s\'">TN-Liste</a></td></tr>',
                            $strCSSRowClass,
                            $calendarEventsHelperAdapter->getEventStateOfSubscriptionBadgesString($eventModel),
                            $dateAdapter->parse($configAdapter->get('dateFormat'), $objEvent->startDate),
                            $link,
                            $objEvent->title,
                            $objEvent->title,
                            $linkMemberList,
                            $objEvent->title
                        );
                    }
                    $strBuffer .= '</tbody>';
                    $strBuffer .= '</table>';
                }

                // Dashboard: List past 10 events (max. 13 months old) where user acts as an instructor or where registration goes to the logged in user.
                $objEvent = $databaseAdapter->getInstance()
                    ->prepare('SELECT * FROM tl_calendar_events AS t1 WHERE (t1.registrationGoesTo=? OR t1.id IN (SELECT t2.pid FROM tl_calendar_events_instructor AS t2 WHERE t2.userId=?)) AND t1.startDate<=? AND t1.startDate>? ORDER BY t1.startDate DESC LIMIT 10')
                    ->execute($objUser->id, $objUser->id, $timeCut, time() - 396 * 30 * 24 * 3600)
                ;

                if ($objEvent->numRows) {
                    $strBuffer .= '<h3>'.$GLOBALS['TL_LANG']['MSC']['bmd_yourPastEvents'].'</h3>';
                    $strBuffer .= '<table id="tl_upcoming_events" class="tl_listing">';
                    $strBuffer .= '<thead><tr><th>Datum &amp; Eventname</th><th>Teiln. / Rapporte</th></tr></thead>';
                    $strBuffer .= '<tbody>';

                    $container = System::getContainer();
                    $rt = $container->get('contao.csrf.token_manager')->getToken($container->getParameter('contao.csrf_token_name'))->getValue();

                    while ($objEvent->next()) {
                        $eventModel = CalendarEventsModel::findByPk($objEvent->id);

                        $strCSSRowClass = $objEvent->endDate > time() ? 'upcoming-event' : 'past-event';
                        $link = sprintf('contao/main.php?do=sac_calendar_events_tool&table=tl_calendar_events&id=%s&act=edit&rt=%s', $objEvent->id, $rt);
                        $linkMemberList = sprintf('contao/main.php?do=sac_calendar_events_tool&table=tl_calendar_events_member&id=%s&rt=%s', $objEvent->id, $rt);

                        $strBuffer .= sprintf(
                            '<tr class="hover-row %s"><td>[%s] <a href="%s" style="text-decoration:underline" title="Event \'%s\' bearbeiten"><strong>%s</strong></a></td><td><a href="%s" style="text-decoration:underline" title="Zur TN-Liste und Rapporte für \'%s\'">TN-Liste / Rapporte</a></td></tr>',
                            $strCSSRowClass,
                            $dateAdapter->parse($configAdapter->get('dateFormat'), $objEvent->startDate),
                            $link,
                            $objEvent->title,
                            $objEvent->title,
                            $linkMemberList,
                            $objEvent->title
                        );
                    }
                    $strBuffer .= '</tbody>';
                    $strBuffer .= '</table>';
                }

                $strBuffer .= '<h3>Anleitungen und Tutorials</h3>';
                $strBuffer .= '<p>Die Seite "Anleitungen und Tutorials" beim Menü "Service" im Frontend/Website unterstützt Sie bei der Verwendung vom SAC Event-Tool (Backend/Contao).</p>';
                $strBuffer .= '<hr>';
            }
        }

        return $strBuffer;
    }
}
