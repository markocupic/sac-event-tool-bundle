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

namespace Markocupic\SacEventToolBundle\Feature\EventPdfExport\Controller;

use Contao\CalendarEventsModel;
use Contao\CoreBundle\Exception\PageNotFoundException;
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\CoreBundle\Monolog\ContaoContext;
use Contao\FrontendUser;
use Markocupic\SacEventToolBundle\Feature\EventPdfExport\EventPdfDataProvider;
use Markocupic\SacEventToolBundle\Feature\EventPdfExport\EventPdfGenerator;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Shows a published tour as a PDF: /_download/event_pdf/{eventId}
 * Linked on the tour detail page next to the iCal download.
 * The contact data of the instructors is only shown to logged-in members.
 *
 * See docs/features/event-pdf-export.md
 */
class EventPdfExportController extends AbstractController
{
    public function __construct(
        private readonly ContaoFramework $framework,
        private readonly EventPdfDataProvider $dataProvider,
        private readonly EventPdfGenerator $generator,
        private readonly Security $security,
        private readonly LoggerInterface|null $contaoErrorLogger = null,
    ) {
    }

    #[Route('/_download/event_pdf/{eventId}',
        name: 'sac_event_tool_download_event_pdf',
        requirements: ['eventId' => '\d+'],
        defaults: ['_scope' => 'frontend', '_token_check' => false])]
    public function __invoke(int $eventId): Response
    {
        $this->framework->initialize();

        $event = $this->framework->getAdapter(CalendarEventsModel::class)->findById($eventId);

        if (null === $event || !$this->dataProvider->supports($event)) {
            throw new PageNotFoundException('Page not found!');
        }

        try {
            // Contact data of the instructors and downloads only for logged-in members
            $forMembers = $this->security->getUser() instanceof FrontendUser;

            $path = $this->generator->generate($event, $forMembers);
        } catch (\Throwable $e) {
            $this->contaoErrorLogger?->error(
                \sprintf('The PDF of the event with ID %s could not be created: %s', $event->id, $e->getMessage()),
                ['contao' => new ContaoContext(__METHOD__, ContaoContext::ERROR)],
            );

            return new Response('Das PDF konnte nicht erstellt werden. Bitte versuche es später nochmals.', Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        $response = new BinaryFileResponse($path);
        $response->setContentDisposition(ResponseHeaderBag::DISPOSITION_INLINE, $this->generator->getFilename($event));
        $response->headers->set('Content-Type', 'application/pdf');
        $response->setPrivate();

        // Without the cache the PDF is a temporary file
        $response->deleteFileAfterSend(!$this->generator->isCacheEnabled());

        return $response;
    }
}
