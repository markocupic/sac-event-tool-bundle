<?php

declare(strict_types=1);

/*
 * This file is part of SAC Event Tool Bundle.
 *
 * (c) Marko Cupic 2024 <m.cupic@gmx.ch>
 * @license GPL-3.0-or-later
 * For the full copyright and license information,
 * please view the LICENSE file that was distributed with this source code.
 * @link https://github.com/markocupic/sac-event-tool-bundle
 */

namespace Markocupic\SacEventToolBundle\DocxTemplator;

use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;
use Contao\CalendarEventsModel;
use Contao\Controller;
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\Events;
use Contao\StringUtil;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Types\Types;
use Markocupic\CloudconvertBundle\Conversion\ConvertFile;
use Markocupic\PhpOffice\PhpWord\MsWordTemplateProcessor;
use Markocupic\SacEventToolBundle\CalendarEventsHelper;
use Markocupic\SacEventToolBundle\Config\EventType;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Filesystem\Path;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

class TourListGenerator extends AbstractController
{
    private const TEMPLATE = 'vendor/markocupic/sac-event-tool-bundle/contao/templates/docx/tour_listing_booklet.docx';
    private const TEASER_LENGTH = 220;

    public function __construct(
        private readonly Connection $connection,
        private readonly ContaoFramework $framework,
        private readonly ConvertFile $convertFile,
        private string $projectDir,
    ) {
        $this->framework->initialize();
    }

    #[Route('/$arrIds/download_status/{uuid}', name: self::class.'_send_download_status', defaults: ['_scope' => 'frontend', '_token_check' => false])]
    public function sendDownloadStatusAction(Request $request, string $uuid): JsonResponse
    {
        $json = ['status' => 'busy'];

        if (is_file($this->projectDir.'/system/tmp/.'.base64_encode($uuid))) {
            $json = ['status' => 'ready'];
        }

        return new JsonResponse($json);
    }

    public function generate(array $arrIds, string $outputFormat = 'docx'): \SplFileObject
    {
        $arrIds = array_filter(array_unique(array_map('intval', $arrIds)));

        // Prevent hacking attempts
        if (\count($arrIds)) {
            $arrIdsChecked = $this->connection->fetchFirstColumn(
                'SELECT id FROM tl_calendar_events WHERE (eventType = ? OR eventType = ? OR eventType = ?) AND id IN('.implode(',', $arrIds).') AND published = ?',
                [EventType::GENERAL_EVENT, EventType::TOUR, EventType::LAST_MINUTE_TOUR, true],
                [Types::STRING, Types::STRING, Types::STRING, Types::BOOLEAN],
            );

            $arrIds = array_intersect($arrIds, $arrIdsChecked);
        }

        // Store the generated file in system/tmp
        $filename = md5(microtime().random_bytes(3));

        $templateProcessor = new MsWordTemplateProcessor($this->projectDir.'/'.self::TEMPLATE, $this->projectDir.'/system/tmp/'.$filename.'.docx');

        //$this->addTourTypeSection($templateProcessor);
        //$this->addTourTechDiffSection($templateProcessor);
        $this->addTourListSection($templateProcessor, $arrIds);

        $splFileObject = $templateProcessor->generate();

        if ('pdf' === $outputFormat) {
            // Use the CloudConvert bundle to convert docx to pdf
            $splFileObject = $this->convertFile
                ->file($splFileObject->getRealPath())
                ->uncached(true)
                ->convertTo('pdf')
            ;
        }

        return $splFileObject;
    }

    protected function prepareString(string $string = ''): string
    {
        return htmlspecialchars(StringUtil::revertInputEncoding($string));
    }

    protected function addTourTypeSection(MsWordTemplateProcessor $templateProcessor): void
    {
        $arrData = $this->connection->fetchAllAssociative('SELECT * FROM tl_tour_type');

        $countRows = \count($arrData);
        $templateProcessor->cloneRow('tourTypeShortcut_', $countRows);

        $i = 0;

        foreach ($arrData as $row) {
            ++$i;
            $templateProcessor->setValue('tourTypeShortcut_#'.$i, $this->prepareString($row['shortcut']));
            $templateProcessor->setValue('tourTypeTitle_#'.$i, $this->prepareString($row['title']));
        }
    }

    protected function addTourTechDiffSection(MsWordTemplateProcessor $templateProcessor): void
    {
        $arrData = $this->connection->fetchAllAssociative('SELECT * FROM tl_tour_difficulty ORDER BY pid, sorting');

        $countRows = \count($arrData);
        $templateProcessor->cloneRow('diffShortcut_', $countRows);

        $i = 0;

        foreach ($arrData as $row) {
            ++$i;
            $templateProcessor->setValue('diffShortcut_#'.$i, $this->prepareString($row['shortcut']));
            $templateProcessor->setValue('diffTitle_#'.$i, $this->prepareString($row['title']));
            $templateProcessor->setValue('diffDescription_#'.$i, $this->prepareString($row['description']));
        }
    }

    protected function addTourListSection(MsWordTemplateProcessor $templateProcessor, array $arrIds): void
    {
        Controller::loadLanguageFile(CalendarEventsModel::getTable());

        // Count results
        $templateProcessor->setValue('count_results', 0 === \count($arrIds) ? 'keine' : (string) \count($arrIds), 1);

        $templateProcessor->cloneBlock('BLOCK_EVENT', \count($arrIds), true, true);
        $index_outer = 0;

        foreach ($arrIds as $eventId) {
            ++$index_outer;

            $event = CalendarEventsModel::findByPk($eventId);

            // Push data to clone
            $templateProcessor->setValue('id_#'.$index_outer, $this->prepareString((string) $event->id), 1);

            // event id
            $templateProcessor->setValue('event_id_#'.$index_outer, CalendarEventsHelper::getEventData($event, 'eventId'), 1);

            // title
            $templateProcessor->setValue('title_#'.$index_outer, $this->prepareString((string) $event->title), 1);

            // teaser
            $templateProcessor->setValue('teaser_#'.$index_outer, $this->prepareString((string) StringUtil::substr($event->teaser, self::TEASER_LENGTH)), 1);

            // date span
            $strDateSpan = CalendarEventsHelper::getEventPeriod($event, 'D, d.m.Y', true, false, true);
            $templateProcessor->setValue('date_span_#'.$index_outer, $this->prepareString((string) strip_tags($strDateSpan)), 1);

            // tour type
            $strTourType = implode(', ', CalendarEventsHelper::getTourTypesAsArray($event));
            $templateProcessor->setValue('tour_type_#'.$index_outer, $this->prepareString((string) strip_tags($strTourType)), 1);

            // tour tech difficulty
            $strTechDiff = implode(', ', CalendarEventsHelper::getTourTechDifficultiesAsArray($event));
            $templateProcessor->setValue('tech_diff_#'.$index_outer, $this->prepareString((string) strip_tags($strTechDiff)), 1);

            $arrMoreDetails = [];

            // More details: is beginner tour
            $isBeginner = $event->suitableForBeginners;

            if ($isBeginner) {
                $arrMoreDetails[] = 'Einsteiger-Tour';
            }

            // More details: event with montainguide
            if (!empty($event->mountainguide)) {
                $arrMoreDetails[] = $GLOBALS['TL_LANG']['tl_calendar_events']['mountainguide_reference'][$event->mountainguide];
            }

            $templateProcessor->setValue('more_details_#'.$index_outer, implode(',    ', $arrMoreDetails), 1);

            // tour guide
            $strMainInnstructor = implode(', ', CalendarEventsHelper::getInstructorNamesAsArray($event, false, true));
            $templateProcessor->setValue('tour_guide_#'.$index_outer, $this->prepareString(' '.$strMainInnstructor.' '), 1);

            // public transport event
            $isPublicTransport = CalendarEventsHelper::isPublicTransportEvent($event);

            if ($isPublicTransport) {
                $path = $this->projectDir.'/vendor/markocupic/sac-event-tool-bundle/public/icons/tour_booklet/oev-tour-badge.png';
                $templateProcessor->setImageValue('oev_img_#'.$index_outer, $path);
            } else {
                $templateProcessor->setValue('oev_img_#'.$index_outer.':50:50', '');
            }

            // organizer icons
            $arrOrgLogoPaths = CalendarEventsHelper::getEventOrganizerLogoPaths($event);

            for ($i = 0; $i < 5; ++$i) {
                if (isset($arrOrgLogoPaths[$i])) {
                    $pathinfo = pathinfo($arrOrgLogoPaths[$i]);
                    $dirname = $pathinfo['dirname'];
                    $filename = $pathinfo['filename'];
                    $pngPath = Path::join($dirname, '/png/'.$filename.'.png');
                    $templateProcessor->setImageValue('org_img_'.$i.'_#'.$index_outer, $pngPath);
                } else {
                    $templateProcessor->setValue('org_img_'.$i.'_#'.$index_outer.':40:40', '');
                }
            }

            // qr_code
            $path = $this->generateQRCode($event);
            $templateProcessor->setImageValue('qr_img_#'.$index_outer, $path);
        }
    }

    private function generateQRCode(CalendarEventsModel $event, array $arrOptions = [], bool $blnCache = true): string
    {
        $fs = new Filesystem();
        $fs->mkdir($this->projectDir.'/system/tmp/qrcodes');

        // Generate path
        $filepath = sprintf($this->projectDir.'/system/tmp/qrcodes/event_booklet_event_qrcode_%s.png', $event->id);

        // Defaults
        $opt = [
            'version' => 4,
            'scale' => 4,
            'outputType' => QRCode::OUTPUT_IMAGE_PNG,
            'eccLevel' => QRCode::ECC_L,
            'cachefile' => $filepath,
        ];

        if (!$blnCache) {
            unset($opt['cachefile']);
        }

        $options = new QROptions(array_merge($opt, $arrOptions));

        // Get event reader url
        $url = Events::generateEventUrl($event, true);

        // Generate QR and return the image path
        if ((new QRCode($options))->render($url, $filepath)) {
            return $filepath;
        }

        throw new \Exception(sprintf('Could not generate QR code from url "%s".', $url));
    }
}
