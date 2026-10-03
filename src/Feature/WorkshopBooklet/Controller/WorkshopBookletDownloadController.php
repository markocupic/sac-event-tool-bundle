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

namespace Markocupic\SacEventToolBundle\Feature\WorkshopBooklet\Controller;

use Contao\CalendarEventsModel;
use Contao\CoreBundle\Exception\PageNotFoundException;
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\CoreBundle\Monolog\ContaoContext;
use Contao\FrontendUser;
use Markocupic\SacEventToolBundle\Config\Log;
use Markocupic\SacEventToolBundle\Feature\WorkshopBooklet\WorkshopBookletGenerator;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

/**
 * Downloads of the course programme as a PDF: the whole booklet of a year or a single course.
 * Only for logged-in members.
 *
 * The routes are loaded before the routes in src/Controller/ (see ContaoManager\Plugin), so the
 * fallback route "/_download/{slug}" of Controller\Download\DownloadController does not catch them.
 *
 * See docs/features/workshop-booklet.md
 */
class WorkshopBookletDownloadController extends AbstractController
{
    public function __construct(
        private readonly TokenStorageInterface $tokenStorage,
        private readonly ContaoFramework $framework,
        private readonly WorkshopBookletGenerator $workshopBookletGenerator,
        private readonly LoggerInterface|null $contaoGeneralLogger = null,
    ) {
        $this->framework->initialize();
    }

    /**
     * Download workshops as a PDF booklet:
     * /_download/print_workshop_booklet_as_pdf/2023
     * /_download/print_workshop_booklet_as_pdf -> current year.
     */
    #[Route('/_download/print_workshop_booklet_as_pdf/{year}',
        name: 'sac_event_tool_download_print_workshop_booklet_as_pdf',
        requirements: ['year' => '\d+'],
        defaults: ['_scope' => 'frontend', '_token_check' => false])]
    public function printWorkshopBookletAsPdfAction(int $year = 0): Response
    {
        $user = $this->tokenStorage->getToken()?->getUser();

        // Do not make the service available to non-logged-in users.
        if (!$user instanceof FrontendUser) {
            throw new PageNotFoundException('Page not found!');
        }

        if (!$year) {
            $year = (int) date('Y');
        }

        $this->workshopBookletGenerator->setYear($year);
        $this->workshopBookletGenerator->setDownload(true);

        // Log download
        $this->contaoGeneralLogger?->info(
            'The course booklet has been downloaded.',
            ['contao' => new ContaoContext(__METHOD__, Log::DOWNLOAD_WORKSHOP_BOOKLET)],
        );

        return $this->workshopBookletGenerator->generate();
    }

    /**
     * Download workshop details as a PDF document:
     * /_download/print_workshop_details_as_pdf/643.
     */
    #[Route('/_download/print_workshop_details_as_pdf/{eventId}',
        name: 'sac_event_tool_download_print_workshop_details_as_pdf',
        requirements: ['eventId' => '\d+'],
        defaults: ['_scope' => 'frontend', '_token_check' => false])]
    public function printWorkshopDetailsAsPdfAction(int $eventId): Response
    {
        $user = $this->tokenStorage->getToken()?->getUser();

        // Do not make the service available to non-logged-in users.
        if (!$user instanceof FrontendUser) {
            throw new PageNotFoundException('Page not found!');
        }

        $event = $this->framework->getAdapter(CalendarEventsModel::class)->findById($eventId);

        if (null !== $event) {
            $this->workshopBookletGenerator->setEventId($eventId);
            $this->workshopBookletGenerator->setDownload(true);

            return $this->workshopBookletGenerator->generate();
        }

        return new Response('Download failed. Please check if the event id is valid.', Response::HTTP_BAD_REQUEST);
    }
}
