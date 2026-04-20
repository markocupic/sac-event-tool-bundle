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
use Contao\CoreBundle\Exception\PageNotFoundException;
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\CoreBundle\Monolog\ContaoContext;
use Contao\FrontendUser;
use Markocupic\SacEventToolBundle\Config\Log;
use Markocupic\SacEventToolBundle\Docx\ExportEvents2Docx;
use Markocupic\SacEventToolBundle\ICal\EventICal;
use Markocupic\SacEventToolBundle\Pdf\WorkshopBookletGenerator;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

class DownloadController extends AbstractController
{
    public function __construct(
        private readonly TokenStorageInterface $tokenStorage,
        private readonly ContaoFramework $framework,
        private readonly EventICal $eventICal,
        private readonly ExportEvents2Docx $exportEvents2Docx,
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
        $this->contaoGeneralLogger->info(
            'The course booklet has been downloaded.',
            ['contao' => new ContaoContext(__METHOD__, Log::DOWNLOAD_WORKSHOP_BOOKLET)],
        );

        return $this->workshopBookletGenerator->generate();
    }

    /**
     * Download events as docx document:
     * /_download/print_workshop_details_as_docx --> current year
     * /_download/print_workshop_details_as_docx/2017
     * /_download/print_workshop_details_as_docx/year=2017/89.
     */
    #[Route('/_download/print_workshop_details_as_docx/{year}/{eventId}',
        name: 'sac_event_tool_download_print_workshop_details_as_docx',
        requirements: ['year' => '\d+', 'eventId' => '\d+'],
        defaults: ['_scope' => 'frontend', '_token_check' => false])]
    public function printWorkshopDetailsAsDocxAction(int $year = 0, int|null $eventId = null): Response
    {
        $user = $this->tokenStorage->getToken()?->getUser();

        // Do not make the service available to non-logged-in users.
        if (!$user instanceof FrontendUser) {
            throw new PageNotFoundException('Page not found!');
        }

        $event = $this->framework->getAdapter(CalendarEventsModel::class)->findById($eventId);

        if (null !== $eventId && null === $event) {
            return new Response('Download failed. Please check if the event id is valid.', Response::HTTP_BAD_REQUEST);
        }

        if (0 === $year) {
            $year = date('Y');
        }

        return $this->exportEvents2Docx->generate((int) $year, $eventId);
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
