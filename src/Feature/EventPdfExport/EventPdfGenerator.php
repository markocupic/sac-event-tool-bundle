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

namespace Markocupic\SacEventToolBundle\Feature\EventPdfExport;

use chillerlan\QRCode\Common\EccLevel;
use chillerlan\QRCode\Output\QROutputInterface;
use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;
use Contao\CalendarEventsModel;
use Contao\Config;
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\CoreBundle\Image\Studio\Studio;
use Contao\CoreBundle\Monolog\ContaoContext;
use Contao\CoreBundle\String\HtmlDecoder;
use Contao\Date;
use Contao\Image\ResizeConfiguration;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Filesystem\Path;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;
use Symfony\Component\String\Slugger\AsciiSlugger;

/**
 * Creates PDF files with Typst (https://typst.app) in the design "Wegweiser":
 *
 * - generate(): one event (tour, general event or course), template typst/event_pdf.typ
 * - generateBooklet(): the course programme of a year with cover and table of contents,
 *   template typst/course_booklet.typ
 *
 * Both templates draw the event pages with typst/event_layout.typ. The data is written to
 * data.json. With the cache enabled (sacevt.feature.event_pdf_export.cache), the PDF of an event
 * is cached and only compiled again when the data (or the template) has changed.
 *
 * See docs/features/event-pdf-export.md
 */
class EventPdfGenerator
{
    public const string TEMPLATE_DIR = __DIR__.'/typst';

    /**
     * The layout of an event page, imported by the templates.
     */
    private const string LAYOUT = 'event_layout.typ';

    private const int TIMEOUT = 60;

    private const int BOOKLET_TIMEOUT = 300;

    public function __construct(
        private readonly ContaoFramework $framework,
        private readonly EventPdfDataProvider $dataProvider,
        private readonly Filesystem $filesystem,
        private readonly HtmlDecoder $htmlDecoder,
        private readonly Studio $studio,
        #[Autowire('%kernel.project_dir%')]
        private readonly string $projectDir,
        #[Autowire('%sacevt.section_name%')]
        private readonly string $sectionName,
        #[Autowire('%sacevt.feature.event_pdf_export.typst_binary%')]
        private readonly string $typstBinary,
        #[Autowire('%sacevt.feature.event_pdf_export.logo%')]
        private readonly string $logo,
        #[Autowire('%sacevt.feature.event_pdf_export.cache%')]
        private readonly bool $cache,
        #[Autowire('%sacevt.feature.event_pdf_export.cache_dir%')]
        private readonly string $cacheDir,
        private readonly LoggerInterface|null $contaoErrorLogger = null,
    ) {
    }

    /**
     * Without the cache, the file returned by generate() is a temporary file and has to be
     * deleted after it has been sent.
     */
    public function isCacheEnabled(): bool
    {
        return $this->cache;
    }

    /**
     * Returns the path of the PDF file of one event (from the cache or newly compiled).
     *
     * With $forMembers (logged-in members) the PDF contains the contact data of the
     * instructors and the downloads. Both variants are cached separately.
     *
     * Without the cache (sacevt.feature.event_pdf_export.cache: false), the PDF is compiled on
     * every call into a temporary file.
     *
     * @throws ProcessFailedException if Typst fails
     */
    public function generate(CalendarEventsModel $event, bool $forMembers = false): string
    {
        $this->framework->initialize();

        $data = $this->dataProvider->getData($event, $forMembers);

        if ($this->cache) {
            $eventDir = Path::join($this->getCacheDir(), (string) $event->id, $forMembers ? 'members' : 'public');
            $pdfPath = Path::join($eventDir, $this->getHash($data).'.pdf');

            if (is_file($pdfPath)) {
                return $pdfPath;
            }
        } else {
            $eventDir = Path::join($this->getCacheDir(), 'tmp');
            $pdfPath = Path::join($eventDir, uniqid((string) $event->id.'_', true).'.pdf');
        }

        $workDir = $this->createWorkDir((string) $event->id);

        try {
            $logo = $this->copyTemplates($workDir, 'event_pdf.typ');
            $data = $this->addEventFiles($data, $workDir, '', $logo);

            $this->filesystem->dumpFile(Path::join($workDir, 'data.json'), json_encode($data, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));

            $this->compile($workDir, 'event_pdf.typ', Path::join($workDir, 'event.pdf'), self::TIMEOUT);

            // Remove the older versions of this event, then move the new file into place
            if ($this->cache && is_dir($eventDir)) {
                $this->filesystem->remove(glob(Path::join($eventDir, '*.pdf')) ?: []);
            }

            $this->filesystem->mkdir($eventDir);

            $this->filesystem->rename(Path::join($workDir, 'event.pdf'), $pdfPath, true);
        } finally {
            $this->filesystem->remove($workDir);
        }

        return $pdfPath;
    }

    /**
     * Creates the course programme ("Kursheft"): cover, table of contents and one or more pages
     * per course, saved at $outputPath. The courses are printed in the given order; "group" is
     * the subtitle in the table of contents (course type).
     *
     * @param string                                                 $title      e.g. "Kursprogramm 2027"
     * @param list<array{event: CalendarEventsModel, group: string}> $courses
     * @param string|null                                            $coverImage absolute path of the cover photo
     *
     * @throws ProcessFailedException if Typst fails
     */
    public function generateBooklet(string $title, array $courses, string $outputPath, string|null $coverImage = null, bool $forMembers = true): void
    {
        $this->framework->initialize();

        $workDir = $this->createWorkDir('booklet');

        try {
            $logo = $this->copyTemplates($workDir, 'course_booklet.typ');

            $events = [];
            $website = '';

            foreach ($courses as $i => $course) {
                $data = $this->dataProvider->getData($course['event'], $forMembers);
                $data['tocGroup'] = $course['group'];

                // The files of each course (portraits, QR codes) in their own subdirectory
                $events[] = $this->addEventFiles($data, $workDir, 'e'.$i, $logo);

                if ('' === $website) {
                    $website = (string) parse_url($data['url'], PHP_URL_HOST);
                }
            }

            $cover = null;

            if (null !== $coverImage && is_file($coverImage)) {
                $cover = 'cover.'.strtolower(Path::getExtension($coverImage));
                $this->filesystem->copy($coverImage, Path::join($workDir, $cover));
            }

            $booklet = [
                'title' => $title,
                'sectionName' => $this->sectionName,
                'issuedAt' => $this->getToday(),
                'courseCount' => \count($events),
                'cover' => $cover,
                'logo' => $logo,
                'website' => $website,
                'events' => $events,
            ];

            $this->filesystem->dumpFile(Path::join($workDir, 'data.json'), json_encode($booklet, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));

            $this->compile($workDir, 'course_booklet.typ', Path::join($workDir, 'booklet.pdf'), self::BOOKLET_TIMEOUT);

            $this->filesystem->mkdir(\dirname($outputPath));
            $this->filesystem->rename(Path::join($workDir, 'booklet.pdf'), $outputPath, true);
        } finally {
            $this->filesystem->remove($workDir);
        }
    }

    /**
     * The file name for the download, e.g. "Tour_2027-1342_Sustenhorn-3503-m.pdf"
     * (courses: "Kurs_…", general events: "Event_…").
     */
    public function getFilename(CalendarEventsModel $event): string
    {
        $this->framework->initialize();

        $year = $this->framework->getAdapter(Date::class)->parse('Y', (int) $event->startDate);
        $title = $this->htmlDecoder->inputEncodedToPlainText((string) $event->title, true);
        $slug = (new AsciiSlugger('de'))->slug(str_replace(["'", '’'], '', $title))->toString();

        $prefix = match (true) {
            EventPdfDataProvider::isTour($event) => 'Tour',
            EventPdfDataProvider::isCourse($event) => 'Kurs',
            default => 'Event',
        };

        return \sprintf('%s_%s-%s_%s.pdf', $prefix, $year, $event->id, $slug);
    }

    private function createWorkDir(string $prefix): string
    {
        $workDir = Path::join($this->getCacheDir(), 'work', uniqid($prefix.'_', true));
        $this->filesystem->mkdir($workDir);

        return $workDir;
    }

    /**
     * Copies the main template, the layout and the logo into the working directory.
     * Returns the file name of the logo.
     */
    private function copyTemplates(string $workDir, string $template): string
    {
        foreach ([$template, self::LAYOUT] as $file) {
            $this->filesystem->copy(Path::join(self::TEMPLATE_DIR, $file), Path::join($workDir, $file));
        }

        $logo = 'logo.'.strtolower(Path::getExtension($this->getLogoPath()));
        $this->filesystem->copy($this->getLogoPath(), Path::join($workDir, $logo));

        return $logo;
    }

    /**
     * Adds the files of an event to the working directory (portraits, QR codes) and their
     * names to the data. In the course programme each event uses its own subdirectory.
     *
     * @param array<string, mixed> $data
     *
     * @return array<string, mixed>
     */
    private function addEventFiles(array $data, string $workDir, string $subDir, string $logo): array
    {
        $file = static fn (string $name): string => '' === $subDir ? $name : $subDir.'/'.$name;

        $data['logo'] = $logo;
        $data['instructors'] = $this->addPhotos($data['instructors'], $workDir, $file);

        // A QR code for each link and each download
        foreach ($data['links'] as $i => $link) {
            $data['links'][$i]['qr'] = $file(\sprintf('link_qr_%s.svg', $i));
            $this->filesystem->dumpFile(Path::join($workDir, $data['links'][$i]['qr']), $this->getQrCode($link['url']));
        }

        foreach ($data['downloads']['items'] ?? [] as $i => $download) {
            $data['downloads']['items'][$i]['qr'] = $file(\sprintf('download_qr_%s.svg', $i));
            $this->filesystem->dumpFile(Path::join($workDir, $data['downloads']['items'][$i]['qr']), $this->getQrCode($download['url']));
        }

        $data['qr'] = $file('qr.svg');
        $this->filesystem->dumpFile(Path::join($workDir, $data['qr']), $this->getQrCode($data['url']));

        $data['generatedAt'] = $this->getToday();

        return $data;
    }

    /**
     * Copies the portraits into the working directory as square images (250 x 250 px).
     *
     * Like the contact boxes on the detail page, the images are cropped by the Contao image
     * service (resize mode "crop"), which respects the "important part" of the image set in
     * the file manager. The resized images are cached in assets/images like all Contao images.
     * If a portrait cannot be resized, the template shows the initials.
     *
     * @param list<array<string, mixed>> $instructors
     * @param \Closure(string): string   $file        file name in the working directory
     *
     * @return list<array<string, mixed>>
     */
    private function addPhotos(array $instructors, string $workDir, \Closure $file): array
    {
        foreach ($instructors as $i => $instructor) {
            $source = $instructor['photo']['path'] ?? null;
            $instructors[$i]['photo'] = null;

            if (null === $source) {
                continue;
            }

            try {
                $image = $this->studio->createImage($source, [250, 250, ResizeConfiguration::MODE_CROP]);
                $image->createIfDeferred();

                // Absolute path of the resized image. Not getFilePath(): it returns the original image.
                // Without a root dir, getImg() returns the resized image as ImageInterface.
                $resized = $image->getPicture()->getImg()['src']->getPath();
                $filename = $file(\sprintf('portrait_%s.%s', $i, strtolower(Path::getExtension($resized))));

                $this->filesystem->copy($resized, Path::join($workDir, $filename));

                $instructors[$i]['photo'] = $filename;
            } catch (\Throwable $e) {
                // Show the initials instead
                $this->contaoErrorLogger?->error(
                    \sprintf('The portrait "%s" could not be added to the event PDF: %s', $source, $e->getMessage()),
                    ['contao' => new ContaoContext(__METHOD__, ContaoContext::ERROR)],
                );
            }
        }

        return $instructors;
    }

    private function compile(string $workDir, string $template, string $outputPath, int $timeout): void
    {
        $process = new Process(
            [
                $this->typstBinary,
                'compile',
                '--root', $workDir,
                '--font-path', Path::join(self::TEMPLATE_DIR, 'fonts'),
                '--ignore-system-fonts',
                Path::join($workDir, $template),
                $outputPath,
            ],
            $workDir,
            null,
            null,
            $timeout,
        );

        $process->mustRun();
    }

    /**
     * The hash changes when the data, the templates or the logo changes.
     * The date of generation is not part of the hash.
     *
     * @param array<string, mixed> $data
     */
    private function getHash(array $data): string
    {
        return hash('xxh128', implode('|', [
            json_encode($data, JSON_THROW_ON_ERROR),
            hash_file('xxh128', Path::join(self::TEMPLATE_DIR, 'event_pdf.typ')),
            hash_file('xxh128', Path::join(self::TEMPLATE_DIR, self::LAYOUT)),
            hash_file('xxh128', $this->getLogoPath()),
        ]));
    }

    private function getQrCode(string $url): string
    {
        $options = new QROptions([
            'outputType' => QROutputInterface::MARKUP_SVG,
            'outputBase64' => false,
            'svgAddXmlHeader' => true,
            'addQuietzone' => false,
            'drawLightModules' => false,
            'eccLevel' => EccLevel::M,
        ]);

        return (new QRCode($options))->render($url);
    }

    private function getToday(): string
    {
        return $this->framework->getAdapter(Date::class)->parse($this->framework->getAdapter(Config::class)->get('dateFormat'));
    }

    private function getLogoPath(): string
    {
        return Path::makeAbsolute($this->logo, $this->projectDir);
    }

    private function getCacheDir(): string
    {
        return Path::makeAbsolute($this->cacheDir, $this->projectDir);
    }
}
