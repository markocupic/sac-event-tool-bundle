<?php

declare(strict_types=1);

/*
 * This file is part of SAC Event Tool Bundle.
 *
 * (c) Marko Cupic 2022 <m.cupic@gmx.ch>
 * @license GPL-3.0-or-later
 * For the full copyright and license information,
 * please view the LICENSE file that was distributed with this source code.
 * @link https://github.com/markocupic/sac-event-tool-bundle
 */

namespace Markocupic\SacEventToolBundle\Controller\Download;

use Contao\CalendarEventsModel;
use Contao\Config;
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\CoreBundle\Monolog\ContaoContext;
use Contao\System;
use Markocupic\SacEventToolBundle\Docx\ExportEvents2Docx;
use Markocupic\SacEventToolBundle\Ical\SendEventIcal;
use Markocupic\SacEventToolBundle\Pdf\PrintWorkshopsAsPdf;
use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * Class DownloadController.
 */
class DownloadController extends AbstractController
{
    private ContaoFramework $framework;

    private RequestStack $requestStack;

    private PrintWorkshopsAsPdf $printWorkshopsAsPdf;

    private ExportEvents2Docx $exportEvents2Docx;

    private ?LoggerInterface $logger;

    public function __construct(ContaoFramework $framework, RequestStack $requestStack, PrintWorkshopsAsPdf $printWorkshopsAsPdf, ExportEvents2Docx $exportEvents2Docx, ?LoggerInterface $logger)
    {
        $this->framework = $framework;
        $this->requestStack = $requestStack;
        $this->printWorkshopsAsPdf = $printWorkshopsAsPdf;
        $this->exportEvents2Docx = $exportEvents2Docx;
        $this->logger = $logger;

        $this->framework->initialize();
    }

    /**
     * Download workshops as pdf booklet
     * /_download/print_workshop_booklet_as_pdf?year=2019&cat=0
     * /_download/print_workshop_booklet_as_pdf?year=current&cat=0.
     *
     * @Route("/_download/print_workshop_booklet_as_pdf", name="sac_event_tool_download_print_workshop_booklet_as_pdf", defaults={"_scope" = "frontend", "_token_check" = false})
     */
    public function printWorkshopBookletAsPdfAction(): Response
    {
        /** @var Request $request */
        $request = $this->requestStack->getCurrentRequest();

        /** @var Config $configAdapter */
        $configAdapter = $this->framework->getAdapter(Config::class);

        $year = $request->query->get('year') ?: null;

        if (!empty($year)) {
            if ('current' === $year) {
                $year = date('Y');
            }
            $this->printWorkshopsAsPdf->setYear((int) $year);
        }

        $this->printWorkshopsAsPdf->setDownload(true);

        // Log download
        $this->logger->log(
            LogLevel::INFO,
            'The course booklet has been downloaded.',
            ['contao' => new ContaoContext(__METHOD__, $configAdapter->get('SAC_EVT_LOG_COURSE_BOOKLET_DOWNLOAD'))]
        );

        return $this->printWorkshopsAsPdf->generate();
    }

    /**
     * Download events as docx file
     * /_download/print_workshop_details_as_docx?calendarId=6&year=2017
     * /_download/print_workshop_details_as_docx?calendarId=6&year=2017&eventId=89.
     *
     * @Route("/_download/print_workshop_details_as_docx", name="sac_event_tool_download_print_workshop_details_as_docx", defaults={"_scope" = "frontend", "_token_check" = false})
     */
    public function printWorkshopDetailsAsDocxAction(): void
    {
        /** @var Request $request */
        $request = $this->requestStack->getCurrentRequest();

        if ($request->query->has('year')) {
            $year = $request->query->get('year');

            if ('current' === $year) {
                $year = date('Y');
            }

            $this->exportEvents2Docx->generate((int) $year, $request->query->get('eventId', null));
        }
    }

    /**
     * Download workshop details as pdf
     * /_download/print_workshop_details_as_pdf?eventId=643.
     *
     * @Route("/_download/print_workshop_details_as_pdf", name="sac_event_tool_download_print_workshop_details_as_pdf", defaults={"_scope" = "frontend", "_token_check" = false})
     */
    public function printWorkshopDetailsAsPdfAction(): Response
    {
        /** @var Request $request */
        $request = $this->requestStack->getCurrentRequest();

        $eventId = $request->query->get('eventId', null);

        if (null !== $eventId) {
            $this->printWorkshopsAsPdf->setEventId((int) $eventId);
        }

        $this->printWorkshopsAsPdf->setDownload(true);

        return $this->printWorkshopsAsPdf->generate();
    }

    /**
     * Send ical to the browser.
     *
     * @Route("/_download/download_event_ical", name="sac_event_tool_download_download_event_ical", defaults={"_scope" = "frontend", "_token_check" = false})
     */
    public function downloadEventIcalAction(): void
    {
        /** @var Request $request */
        $request = $this->requestStack->getCurrentRequest();

        /** @var CalendarEventsModel $calendarEventsModelAdapter */
        $calendarEventsModelAdapter = $this->framework->getAdapter(CalendarEventsModel::class);

        // Course Filter
        if ($request->query->get('eventId') > 0) {
            $objEvent = $calendarEventsModelAdapter->findByPk($request->query->get('eventId'));

            if (null !== $objEvent) {
                /** @var SendEventIcal $ical */
                $ical = System::getContainer()->get('Markocupic\SacEventToolBundle\Ical\SendEventIcal');
                $ical->sendIcsFile($objEvent);
            }
        }
        exit();
    }

    /**
     * The defaultAction has to be at the bottom of the class
     * Handles download requests.
     *
     * @Route("/_download/{slug}", name="sac_event_tool_download", defaults={"_scope" = "frontend", "_token_check" = false})
     */
    public function defaultAction($slug = ''): Response
    {
        $msg = sprintf('Welcome to %s::%s. You have called the Service with this route: _download/%s', self::class, __FUNCTION__, $slug);

        return new Response($msg);
    }
}
