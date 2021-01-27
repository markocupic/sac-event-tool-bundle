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

namespace Markocupic\SacEventToolBundle\EventListener\Contao;

use Contao\BackendUser;
use Contao\Config;
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\Database;
use Contao\Date;
use Contao\System;
use Markocupic\SacEventToolBundle\CalendarEventsHelper;
use Markocupic\SacEventToolBundle\ContaoMode\ContaoMode;

/**
 * Class GetSystemMessagesListener.
 */
class GetSystemMessagesListener
{
    /**
     * @var ContaoFramework
     */
    private $framework;

    /**
     * @var ContaoMode;
     */
    private $contaoMode;

    /**
     * GetSystemMessagesListener constructor.
     */
    public function __construct(ContaoFramework $framework, ContaoMode $contaoMode)
    {
        $this->framework = $framework;
        $this->contaoMode = $contaoMode;
    }

    /**
     * Show all upcoming events (where user is main instructor) for the logged in user.
     *
     * @return string
     */
    public function listUntreatedEventSubscriptions()
    {
        $strBuffer = '';

        if ($this->contaoMode->isBackend()) {
            $backendUserAdapter = $this->framework->getAdapter(BackendUser::class);
            $databaseAdapter = $this->framework->getAdapter(Database::class);
            $calendarEventsHelperAdapter = $this->framework->getAdapter(CalendarEventsHelper::class);
            $dateAdapter = $this->framework->getAdapter(Date::class);
            $configAdapter = $this->framework->getAdapter(Config::class);

            $objUser = $backendUserAdapter->getInstance();

            if ($objUser->id > 0) {
                $objEvent = $databaseAdapter->getInstance()->prepare('SELECT * FROM tl_calendar_events WHERE (mainInstructor=? OR registrationGoesTo=?) AND startDate>? ORDER BY startDate')->execute($objUser->id, $objUser->id, time() - 3 * 30 * 24 * 3600);

                if ($objEvent->numRows) {
                    $strBuffer .= '<h3>'.$GLOBALS['TL_LANG']['MSC']['yourUpcomingEvents'].'</h3>';
                    $strBuffer .= '<table id="tl_upcoming_events" class="tl_listing">';
                    $strBuffer .= '<thead><tr><th>Teiln.</th><th>Datum &amp; Eventname</th><th></th></tr></thead>';
                    $strBuffer .= '<tbody>';

                    $container = System::getContainer();
                    $rt = $container->get('contao.csrf.token_manager')->getToken($container->getParameter('contao.csrf_token_name'))->getValue();

                    while ($objEvent->next()) {
                        $strCSSRowClass = $objEvent->endDate > time() ? 'upcoming-event' : 'past-event';
                        $link = sprintf('contao/main.php?do=sac_calendar_events_tool&table=tl_calendar_events&id=%s&act=edit&rt=%s', $objEvent->id, $rt);
                        $linkMemberList = sprintf('contao/main.php?do=sac_calendar_events_tool&table=tl_calendar_events_member&id=%s&rt=%s', $objEvent->id, $rt);
                        $strBuffer .= sprintf('<tr class="hover-row %s"><td>%s</td><td>[%s] <a href="%s" style="text-decoration:underline" target="_blank" title="Event \'%s\' bearbeiten">%s</a></td><td><a href="%s" style="text-decoration:underline" target="_blank" title="Zur TN-Liste für \'%s\'">TN-Liste</a></td></tr>',
                            $strCSSRowClass,
                            $calendarEventsHelperAdapter->getEventStateOfSubscriptionBadgesString($objEvent),
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
            }
        }

        return $strBuffer;
    }
}
