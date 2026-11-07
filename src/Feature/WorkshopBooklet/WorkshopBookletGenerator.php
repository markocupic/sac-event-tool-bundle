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

namespace Markocupic\SacEventToolBundle\Feature\WorkshopBooklet;

use Contao\CalendarEventsModel;
use Contao\CoreBundle\Exception\PageNotFoundException;
use Contao\CoreBundle\Exception\ResponseException;
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\Date;
use Doctrine\DBAL\Connection;
use Markocupic\SacEventToolBundle\Config\EventType;
use Markocupic\SacEventToolBundle\Download\BinaryFileDownload;
use Markocupic\SacEventToolBundle\Feature\EventPdfExport\EventPdfGenerator;
use Markocupic\SacEventToolBundle\Util\CalendarEventsUtil;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Filesystem\Path;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * Generates the course programme ("Kursprogramm") as a PDF booklet: all published courses of a year
 * with cover and table of contents, or a single course.
 *
 * The PDF is created with Typst in the design "Wegweiser" (EventPdfGenerator::generateBooklet(),
 * template src/Feature/EventPdfExport/typst/course_booklet.typ). A single course is the same PDF
 * as the "PDF" button on the course detail page (EventPdfGenerator::generate()).
 *
 * The course programme is reused for sacevt.feature.workshop_booklet.cache_ttl seconds (default one
 * hour), so the server does not compile it again for every download. A lock makes sure that
 * simultaneous downloads do not compile it several times.
 *
 * See docs/features/workshop-booklet.md
 */
class WorkshopBookletGenerator
{
    private int $year;

    private int|null $eventId = null;

    private bool $download = false;

    public function __construct(
        private readonly ContaoFramework $framework,
        private readonly Connection $connection,
        private readonly CalendarEventsUtil $calendarEventsUtil,
        private readonly EventPdfGenerator $eventPdfGenerator,
        private readonly BinaryFileDownload $binaryFileDownload,
        private readonly string $projectDir,
        private readonly string $sacevtEventCourseBookletFilenamePattern,
        private readonly string $sacevtTempDir,
        #[Autowire('%sacevt.event.course.booklet_cover_image%')]
        private readonly string $coverImage,
        #[Autowire('%sacevt.feature.workshop_booklet.cache_ttl%')]
        private readonly int $cacheTtl,
    ) {
        // Set defaults
        $this->year = (int) date('Y');
    }

    /**
     * @return $this
     *
     * @throws \Exception
     */
    public function setYear(int $year): self
    {
        if (!checkdate(1, 1, $year)) {
            throw new \Exception(\sprintf('%s is not a valid year number. Please use a valid year number f.ex. "2020" as first parameter.', Date::parse('Y', strtotime((string) $year))));
        }

        $this->year = $year;

        return $this;
    }

    /**
     * @return $this
     *
     * @throws \Exception
     */
    public function setEventId(int $eventId): self
    {
        $this->framework->initialize();

        if (null === $this->framework->getAdapter(CalendarEventsModel::class)->findById($eventId)) {
            throw new \Exception('Please use a valid event id as first parameter.');
        }

        $this->eventId = $eventId;

        return $this;
    }

    /**
     * @return $this
     */
    public function setDownload(bool $download): self
    {
        $this->download = $download;

        return $this;
    }

    public function generate(): BinaryFileResponse
    {
        $this->framework->initialize();

        // Single course: the same PDF as the "PDF" button on the course detail page
        if (null !== $this->eventId) {
            return $this->generateSingleCourse($this->eventId);
        }

        // Kursprogramm_%s.pdf
        $filename = \sprintf($this->sacevtEventCourseBookletFilenamePattern, $this->year);
        $path = Path::join($this->projectDir, $this->sacevtTempDir, $filename);

        $this->generateBookletIfOutdated($path);

        if (false === $this->download) {
            throw new ResponseException(new Response(''));
        }

        // Send file to the browser. The file stays for the next downloads (cache).
        return $this->binaryFileDownload->sendFileToBrowser($path, basename($path), false, 0 === $this->cacheTtl);
    }

    /**
     * Compiles the course programme, unless it is younger than the cache TTL. The lock makes
     * simultaneous requests wait for the first one and then use its file.
     */
    private function generateBookletIfOutdated(string $path): void
    {
        if ($this->isFresh($path)) {
            return;
        }

        if (!is_dir(\dirname($path))) {
            mkdir(\dirname($path), 0777, true);
        }

        $lock = fopen($path.'.lock', 'c');

        if (false === $lock) {
            throw new \RuntimeException(\sprintf('Could not create the lock file "%s.lock".', $path));
        }

        try {
            flock($lock, LOCK_EX);

            // Another request may have compiled it while we were waiting for the lock
            if ($this->isFresh($path)) {
                return;
            }

            $this->eventPdfGenerator->generateBooklet(
                \sprintf('Kursprogramm %s', $this->year),
                $this->getCourses(),
                $path,
                Path::makeAbsolute($this->coverImage, $this->projectDir),
            );
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }

    private function isFresh(string $path): bool
    {
        clearstatcache(true, $path);

        return $this->cacheTtl > 0 && is_file($path) && filemtime($path) > time() - $this->cacheTtl;
    }

    private function generateSingleCourse(int $eventId): BinaryFileResponse
    {
        $event = $this->framework->getAdapter(CalendarEventsModel::class)->findById($eventId);

        if (null === $event || !$event->published) {
            throw new PageNotFoundException('Page not found!');
        }

        // The course programme is only for logged-in members: with contact data and downloads
        $path = $this->eventPdfGenerator->generate($event, true);

        return $this->binaryFileDownload->sendFileToBrowser(
            $path,
            $this->eventPdfGenerator->getFilename($event),
            false,
            !$this->eventPdfGenerator->isCacheEnabled(),
        );
    }

    /**
     * Published courses of the year, sorted by course type, title and start date. The group in
     * the table of contents is the course type (e.g. "Ausbildung Hochtouren").
     *
     * @return list<array{event: CalendarEventsModel, group: string}>
     */
    private function getCourses(): array
    {
        $start = (new \DateTime($this->year.'-01-01'))->getTimestamp();
        $stop = (new \DateTime($this->year + 1 .'-01-01'))->getTimestamp();

        $ids = $this->connection->fetchFirstColumn(
            'SELECT id FROM tl_calendar_events WHERE eventType = ? AND startTime >= ? AND endTime < ? AND published = ? ORDER BY courseTypeLevel0, title, startDate',
            [EventType::COURSE, $start, $stop, '1'],
        );

        $courses = [];

        foreach ($ids as $id) {
            $event = $this->framework->getAdapter(CalendarEventsModel::class)->findById($id);

            if (null === $event) {
                continue;
            }

            $courses[] = [
                'event' => $event,
                'group' => html_entity_decode(strip_tags((string) $this->calendarEventsUtil->getEventData($event, 'courseTypeLevel0Name')), ENT_QUOTES | ENT_HTML5, 'UTF-8'),
            ];
        }

        return $courses;
    }
}
