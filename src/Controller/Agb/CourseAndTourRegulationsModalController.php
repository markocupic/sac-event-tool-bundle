<?php

declare(strict_types=1);

/*
 * This file is part of SAC Event Tool Bundle.
 *
 * (c) Marko Cupic 2023 <m.cupic@gmx.ch>
 * @license GPL-3.0-or-later
 * For the full copyright and license information,
 * please view the LICENSE file that was distributed with this source code.
 * @link https://github.com/markocupic/sac-event-tool-bundle
 */

namespace Markocupic\SacEventToolBundle\Controller\Agb;

use Contao\CalendarEventsModel;
use Contao\CoreBundle\Framework\Adapter;
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\StringUtil;
use Contao\Validator;
use Doctrine\DBAL\Connection;
use Markocupic\SacEventToolBundle\Config\EventType;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;

class CourseAndTourRegulationsModalController extends AbstractController
{
    private readonly Adapter $stringUtil;
    private readonly Adapter $validator;

    public function __construct(
        private readonly ContaoFramework $framework,
        private readonly Connection $connection,
        private readonly string $projectDir,
    ) {
        $this->stringUtil = $this->framework->getAdapter(StringUtil::class);
        $this->validator = $this->framework->getAdapter(Validator::class);
    }

    public function getModal(CalendarEventsModel $event, string $attrModalId = 'courseAndTourRegulationsModal', string $attrModalTitle = 'course and tour regulations'): Response
    {
        $isDownloadable = false;

        $allowedEventTypes = [
            EventType::TOUR,
            EventType::LAST_MINUTE_TOUR,
            EventType::COURSE,
        ];

        if (\in_array($event->eventType, $allowedEventTypes, true)) {
            $eventOrganizers = $this->stringUtil->deserialize($event->organizers, true);

            if (isset($eventOrganizers[0])) {
                $arrOrganizer = $this->connection->fetchAssociative(
                    'SELECT * FROM tl_event_organizer WHERE id = ?',
                    [
                        $eventOrganizers[0],
                    ]
                );

                if ($arrOrganizer) {
                    if (EventType::TOUR === $event->eventType || EventType::LAST_MINUTE_TOUR === $event->eventType) {
                        $prefix = EventType::TOUR;
                    }

                    if (EventType::COURSE === $event->eventType) {
                        $prefix = EventType::COURSE;
                    }

                    if (!empty($prefix)) {
                        $regulationsExtract = $arrOrganizer[$prefix.'RegulationExtract'] ?? null;

                        if (!empty($regulationsExtract)) {
                            if ($this->validator->isBinaryUuid($arrOrganizer[$prefix.'RegulationSRC'])) {
                                $arrFile = $this->connection->fetchAssociative(
                                    'SELECT * FROM tl_files WHERE uuid = ?',
                                    [
                                        $arrOrganizer[$prefix.'RegulationSRC'],
                                    ]
                                );

                                if ($arrFile) {
                                    if (is_file($this->projectDir.'/'.$arrFile['path'])) {
                                        $isDownloadable = true;
                                    }
                                }
                            }

                            return $this->render(
                                '@MarkocupicSacEventTool/Agb/course_and_tour_regulations_modal.html.twig',
                                [
                                    'event_regulations_file' => true === $isDownloadable ? $arrFile : null,
                                    'event_regulations_extract' => $regulationsExtract,
                                    'attr_modal_id' => $attrModalId,
                                    'attr_modal_title' => $attrModalTitle,
                                ]
                            );
                        }
                    }
                }
            }
        }
    }
}
