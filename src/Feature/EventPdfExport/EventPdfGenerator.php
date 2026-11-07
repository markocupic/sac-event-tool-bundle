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
 * Creates the PDF of a tour with Typst (https://typst.app).
 *
 * The data of the tour is written to data.json and compiled together with the template
 * typst/event_pdf.typ. With the cache enabled (sacevt.feature.event_pdf_export.cache), the
 * PDF is cached per event and only compiled again when the data (or the template) has changed.
 *
 * See docs/features/event-pdf-export.md
 */
class EventPdfGenerator
{
    public const string TEMPLATE_DIR = __DIR__.'/typst';

    private const int TIMEOUT = 60;

    public function __construct(
        private readonly ContaoFramework $framework,
        private readonly EventPdfDataProvider $dataProvider,
        private readonly Filesystem $filesystem,
        private readonly HtmlDecoder $htmlDecoder,
        private readonly Studio $studio,
        #[Autowire('%kernel.project_dir%')]
        private readonly string $projectDir,
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
     * Returns the path of the PDF file (from the cache or newly compiled).
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

        $workDir = Path::join($this->getCacheDir(), 'work', uniqid((string) $event->id.'_', true));
        $this->filesystem->mkdir($workDir);

        try {
            $this->filesystem->copy(Path::join(self::TEMPLATE_DIR, 'event_pdf.typ'), Path::join($workDir, 'event_pdf.typ'));

            $logo = $this->getLogoPath();
            $data['logo'] = 'logo.'.strtolower(Path::getExtension($logo));
            $this->filesystem->copy($logo, Path::join($workDir, $data['logo']));

            $data['instructors'] = $this->addPhotos($data['instructors'], $workDir);

            // A QR code for each link and each download
            foreach ($data['links'] as $i => $link) {
                $data['links'][$i]['qr'] = \sprintf('link_qr_%s.svg', $i);
                $this->filesystem->dumpFile(Path::join($workDir, $data['links'][$i]['qr']), $this->getQrCode($link['url']));
            }

            foreach ($data['downloads']['items'] ?? [] as $i => $download) {
                $data['downloads']['items'][$i]['qr'] = \sprintf('download_qr_%s.svg', $i);
                $this->filesystem->dumpFile(Path::join($workDir, $data['downloads']['items'][$i]['qr']), $this->getQrCode($download['url']));
            }

            $data['qr'] = 'qr.svg';
            $this->filesystem->dumpFile(Path::join($workDir, $data['qr']), $this->getQrCode($data['url']));

            $data['generatedAt'] = $this->framework->getAdapter(Date::class)->parse($this->framework->getAdapter(Config::class)->get('dateFormat'));
            $this->filesystem->dumpFile(Path::join($workDir, 'data.json'), json_encode($data, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));

            $this->compile($workDir, Path::join($workDir, 'event.pdf'));

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
     * The file name for the download, e.g. "Tour_2027-1342_Sustenhorn-3503-m.pdf"
     * (general events: "Event_…").
     */
    public function getFilename(CalendarEventsModel $event): string
    {
        $this->framework->initialize();

        $year = $this->framework->getAdapter(Date::class)->parse('Y', (int) $event->startDate);
        $title = $this->htmlDecoder->inputEncodedToPlainText((string) $event->title, true);
        $slug = (new AsciiSlugger('de'))->slug(str_replace(["'", '’'], '', $title))->toString();

        $prefix = EventPdfDataProvider::isTour($event) ? 'Tour' : 'Event';

        return \sprintf('%s_%s-%s_%s.pdf', $prefix, $year, $event->id, $slug);
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
     *
     * @return list<array<string, mixed>>
     */
    private function addPhotos(array $instructors, string $workDir): array
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
                $filename = \sprintf('portrait_%s.%s', $i, strtolower(Path::getExtension($resized)));

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

    private function compile(string $workDir, string $outputPath): void
    {
        $process = new Process(
            [
                $this->typstBinary,
                'compile',
                '--root', $workDir,
                '--font-path', Path::join(self::TEMPLATE_DIR, 'fonts'),
                '--ignore-system-fonts',
                Path::join($workDir, 'event_pdf.typ'),
                $outputPath,
            ],
            $workDir,
            null,
            null,
            self::TIMEOUT,
        );

        $process->mustRun();
    }

    /**
     * The hash changes when the data, the template or the logo changes.
     * The date of generation is not part of the hash.
     *
     * @param array<string, mixed> $data
     */
    private function getHash(array $data): string
    {
        return hash('xxh128', implode('|', [
            json_encode($data, JSON_THROW_ON_ERROR),
            hash_file('xxh128', Path::join(self::TEMPLATE_DIR, 'event_pdf.typ')),
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

    private function getLogoPath(): string
    {
        return Path::makeAbsolute($this->logo, $this->projectDir);
    }

    private function getCacheDir(): string
    {
        return Path::makeAbsolute($this->cacheDir, $this->projectDir);
    }
}
