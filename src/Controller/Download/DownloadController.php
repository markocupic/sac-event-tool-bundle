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

namespace Markocupic\SacEventToolBundle\Controller\Download;

use Contao\CalendarEventsModel;
use Contao\CalendarModel;
use Contao\Config;
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\CoreBundle\Monolog\ContaoContext;
use Contao\Date;
use Contao\System;
use Markocupic\SacEventToolBundle\DocxTemplator\ExportEvents2Docx;
use Markocupic\SacEventToolBundle\Ical\SendEventIcal;
use Markocupic\SacEventToolBundle\Pdf\PrintWorkshopsAsPdf;
use Psr\Log\LogLevel;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\Annotation\Route;

/**
 * Class DownloadController.
 */
class DownloadController extends AbstractController
{
    /**
     * @var ContaoFramework
     */
    private $framework;

    /**
     * @var RequestStack
     */
    private $requestStack;

    /**
     * DownloadController constructor.
     */
    public function __construct(ContaoFramework $framework, RequestStack $requestStack)
    {
        $this->framework = $framework;
        $this->requestStack = $requestStack;

        $this->framework->initialize();
    }

    /**
     * Download workshops as pdf booklet
     * /_download/print_workshop_booklet_as_pdf?year=2019&cat=0
     * /_download/print_workshop_booklet_as_pdf?year=current&cat=0.
     *
     * @Route("/_download/print_workshop_booklet_as_pdf", name="sac_event_tool_download_print_workshop_booklet_as_pdf", defaults={"_scope" = "frontend", "_token_check" = false})
     */
    public function printWorkshopBookletAsPdfAction(): void
    {
        /** @var PrintWorkshopsAsPdf $pdf */
        $pdf = System::getContainer()->get('Markocupic\SacEventToolBundle\Pdf\PrintWorkshopsAsPdf');

        /** @var Request $request */
        $request = $this->requestStack->getCurrentRequest();

        /** @var Date $dateAdapter */
        $dateAdapter = $this->framework->getAdapter(Date::class);

        /** @var Config $configAdapter */
        $configAdapter = $this->framework->getAdapter(Config::class);

        $year = '' !== $request->query->get('year') ? (int) $request->query->get('year') : null;
        $calendarId = '' !== $request->query->get('calendarId') ? (int) $request->query->get('calendarId') : null;

        if (!empty($year)) {
            if ('current' === $year) {
                $year = (int) $dateAdapter->parse('Y');
            }
            $pdf = $pdf->setYear($year);
        }

        if (!empty($calendarId)) {
            $pdf = $pdf->setCalendarId($calendarId);
        }

        $pdf->setDownload(true);

        // Log download
        $container = System::getContainer();
        $logger = $container->get('monolog.logger.contao');
        $logger->log(LogLevel::INFO, 'The course booklet has been downloaded.', ['contao' => new ContaoContext(__METHOD__, $configAdapter->get('SAC_EVT_LOG_COURSE_BOOKLET_DOWNLOAD'))]);

        $pdf->printWorkshopsAsPdf();

        exit();
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

        /** @var ExportEvents2Docx $exportEvents2DocxAdapter */
        $exportEvents2DocxAdapter = $this->framework->getAdapter(ExportEvents2Docx::class);

        /** @var CalendarModel $calendarModelAdapter */
        $calendarModelAdapter = $this->framework->getAdapter(CalendarModel::class);

        /** @var CalendarModel $objCalendar */
        $objCalendar = $calendarModelAdapter->findByPk($request->query->get('calendarId'));

        if ($request->query->get('year') && null !== $objCalendar) {
            $exportEvents2DocxAdapter->generate($objCalendar, $request->query->get('year'), $request->query->get('eventId'));
        }
        exit();
    }

    /**
     * Download workshop details as pdf
     * /_download/print_workshop_details_as_pdf?eventId=643.
     *
     * @Route("/_download/print_workshop_details_as_pdf", name="sac_event_tool_download_print_workshop_details_as_pdf", defaults={"_scope" = "frontend", "_token_check" = false})
     */
    public function printWorkshopDetailsAsPdfAction(): void
    {
        /** @var Request $request */
        $request = $this->requestStack->getCurrentRequest();

        /** @var PrintWorkshopsAsPdf $pdf */
        $pdf = System::getContainer()->get('Markocupic\SacEventToolBundle\Pdf\PrintWorkshopsAsPdf');

        $eventId = $request->query->get('eventId') ? (int) $request->query->get('eventId') : null;

        if (null !== $eventId) {
            $pdf->setEventId($eventId);
        }

        $pdf->setDownload(true);
        $pdf->printWorkshopsAsPdf();
        exit();
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
    public function defaultAction($slug = ''): void
    {
        echo sprintf('Welcome to %s::%s. You have called the Service with this route: _download/%s', self::class, __FUNCTION__, $slug);
        exit();
    }
}
