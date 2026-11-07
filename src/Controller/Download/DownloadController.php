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

namespace Markocupic\SacEventToolBundle\Controller\Download;

use Contao\CalendarEventsModel;
use Contao\CoreBundle\Framework\ContaoFramework;
use Markocupic\SacEventToolBundle\ICal\EventICal;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class DownloadController extends AbstractController
{
    public function __construct(
        private readonly ContaoFramework $framework,
        private readonly EventICal $eventICal,
    ) {
        $this->framework->initialize();
    }

    /**
     * Send ICal to the browser.
     */
    #[Route('/_download/download_event_ical/{eventId}',
        name: 'sac_event_tool_download_event_ical',
        requirements: ['eventId' => '\d+'],
        defaults: ['_scope' => 'frontend', '_token_check' => false])]
    public function downloadEventICalAction(int $eventId): Response
    {
        $event = $this->framework->getAdapter(CalendarEventsModel::class)->findById($eventId);

        if (null !== $event) {
            return $this->eventICal->download($event);
        }

        return new Response('ICal download failed. Please check if the event id is valid.', Response::HTTP_BAD_REQUEST);
    }

    /**
     * The fallback action has to be at the bottom of the class.
     */
    #[Route('/_download/{slug}',
        name: 'sac_event_tool_download',
        defaults: ['_scope' => 'frontend', '_token_check' => false])]
    public function defaultAction($slug = ''): Response
    {
        $safeSlug = htmlspecialchars($slug, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $msg = \sprintf('Welcome to %s::%s. You have called the Service with this route: _download/%s', self::class, __FUNCTION__, $safeSlug);

        return new Response($msg);
    }
}
