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

namespace Markocupic\SacEventToolBundle\DataContainer;

use Contao\Backend;
use Contao\CalendarEventsModel;
use Contao\CalendarEventsStoryModel;
use Contao\Config;
use Contao\CoreBundle\ServiceAnnotation\Callback;
use Contao\DataContainer;
use Contao\Environment;
use Contao\Files;
use Contao\FilesModel;
use Contao\Folder;
use Contao\MemberModel;
use Contao\StringUtil;
use Contao\UserModel;
use Doctrine\DBAL\Connection;
use Markocupic\PhpOffice\PhpWord\MsWordTemplateProcessor;
use Markocupic\SacEventToolBundle\CalendarEventsHelper;
use Markocupic\SacEventToolBundle\Download\BinaryFileDownload;
use Markocupic\ZipBundle\Zip\Zip;
use PhpOffice\PhpWord\Exception\CopyFileException;
use PhpOffice\PhpWord\Exception\CreateTemporaryFileException;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Security\Core\Security;

class CalendarEventsStory
{
    private Security $security;
    private Connection $connection;
    private RequestStack $requestStack;
    private BinaryFileDownload $binaryFileDownload;
    private string $projectDir;
    private string $tempDir;
    private string $locale;

    public function __construct(Security $security, Connection $connection, RequestStack $requestStack, BinaryFileDownload $binaryFileDownload, string $projectDir, string $tempDir, string $locale)
    {
        $this->security = $security;
        $this->connection = $connection;
        $this->requestStack = $requestStack;
        $this->binaryFileDownload = $binaryFileDownload;
        $this->projectDir = $projectDir;
        $this->tempDir = $tempDir;
        $this->locale = $locale;
    }

    /**
     * @Callback(table="tl_calendar_events_story", target="config.onload")
     *
     * @throws \Exception
     */
    public function route(): void
    {
        $request = $this->requestStack->getCurrentRequest();

        $id = $request->query->get('id');

        if ($id && 'exportArticle' === $request->query->get('action')) {
            if (null !== ($objArticle = CalendarEventsStoryModel::findByPk($id))) {
                $this->exportArticle($objArticle);
            }
        }
    }

    /**
     * @Callback(table="tl_calendar_events_story", target="config.onload")
     */
    public function setPalettes(): void
    {
        $user = $this->security->getUser();

        // Overwrite readonly attribute for admins
        if ($user->admin) {
            $fields = ['sacMemberId', 'eventId', 'authorName'];

            foreach ($fields as $field) {
                $GLOBALS['TL_DCA']['tl_calendar_events_story']['fields'][$field]['eval']['readonly'] = false;
            }
        }
    }

    /**
     * @Callback(table="tl_calendar_events_story", target="config.onload")
     *
     * @throws \Exception
     */
    public function deleteUnfinishedAndOldEntries(): void
    {
        // Delete old and unpublished stories
        $limit = time() - 60 * 60 * 24 * 30;

        $this->connection->executeStatement(
            'tl_calendar_events_story WHERE tstamp < ? AND publishState < ?',
            [$limit, 3],
        );

        // Delete unfinished stories older the 14 days
        $limit = time() - 60 * 60 * 24 * 14;

        $this->connection->executeStatement(
            'DELETE FROM tl_calendar_events_story WHERE tstamp < ? AND text = ? AND youtubeId = ? AND multiSRC = ?',
            [$limit, '', '', null]
        );

        // Keep stories up to date, if events are renamed f.ex.
        $stmt = $this->connection->executeQuery('SELECT * FROM tl_calendar_events_story', []);

        while (false !== ($arrStory = $stmt->fetchAssociative())) {
            $objStoryModel = CalendarEventsStoryModel::findByPk($arrStory['id']);
            $objEvent = $objStoryModel->getRelated('eventId');

            if (null !== $objEvent) {
                $objStoryModel->eventTitle = $objEvent->title;
                $objStoryModel->substitutionEvent = 'event_adapted' === $objEvent->executionState && '' !== $objEvent->eventSubstitutionText ? $objEvent->eventSubstitutionText : '';
                $objStoryModel->eventStartDate = $objEvent->startDate;
                $objStoryModel->eventEndDate = $objEvent->endDate;
                $objStoryModel->organizers = $objEvent->organizers;

                $aDates = [];
                $arrDates = StringUtil::deserialize($objEvent->eventDates, true);

                foreach ($arrDates as $arrDate) {
                    $aDates[] = $arrDate['new_repeat'];
                }
                
                $objStoryModel->eventDates = serialize($aDates);
                $objStoryModel->save();
            }
        }
    }

    /**
     * Add an image to each record.
     *
     * @Callback(table="tl_calendar_events_story", target="list.label.label")
     *
     * @param array $row
     */
    public function addIcon($row, string $label, DataContainer $dc, array $args): array
    {
        $image = 'member';
        $disabled = false;

        if ('3' !== $row['publishState']) {
            $image .= '_';
            $disabled = true;
        }

        $args[0] = sprintf('<div class="list_icon_new" style="background-image:url(\'%ssystem/themes/%s/icons/%s.svg\')" data-icon="%s.svg" data-icon-disabled="%s.svg">&nbsp;</div>', TL_ASSETS_URL, Backend::getTheme(), $image, $disabled ? $image : rtrim($image, '_'), rtrim($image, '_').'_');

        return $args;
    }

    /**
     * @throws CopyFileException
     * @throws CreateTemporaryFileException
     */
    private function exportArticle(CalendarEventsStoryModel $objArticle): void
    {
        $objEvent = CalendarEventsModel::findByPk($objArticle->eventId);

        if (null === $objEvent) {
            throw new \Exception('Event not found.');
        }

        $templSrc = Config::get('SAC_EVT_TOUR_ARTICLE_EXPORT_TEMPLATE_SRC');

        if (!is_file($this->projectDir.'/'.$templSrc)) {
            throw new \Exception('Template file not found.');
        }

        // target dir & file
        $targetDir = sprintf('system/tmp/article_%s_%s', $objArticle->id, time());
        $imageDir = sprintf('%s/images', $targetDir);

        // Create folder
        new Folder($imageDir);

        $targetFile = sprintf('%s/event_article_%s.docx', $targetDir, $objArticle->id);
        $objPhpWord = new MsWordTemplateProcessor($templSrc, $targetFile);

        // Organizers
        $arrOrganizers = CalendarEventsHelper::getEventOrganizersAsArray($objEvent);
        $strOrganizers = implode(', ', $arrOrganizers);

        // Instructors
        $mainInstructorName = CalendarEventsHelper::getMainInstructorName($objEvent);
        $mainInstructorEmail = '';

        if (null !== ($objInstructor = UserModel::findByPk($objEvent->mainInstructor))) {
            $mainInstructorEmail = $objInstructor->email;
        }

        $objMember = MemberModel::findBySacMemberId($objArticle->sacMemberId);
        $strAuthorEmail = '';

        if (null !== $objMember) {
            $strAuthorEmail = $objMember->email;
        }

        // Event dates
        $arrEventDates = CalendarEventsHelper::getEventTimestamps($objEvent);
        $arrEventDates = array_map(
            static fn ($tstamp) => date('Y-m-d', (int) $tstamp),
            $arrEventDates
        );
        $strEventDates = implode("\r\n", $arrEventDates);

        // Checked by instructor
        $strCheckedByInstructor = $objArticle->checkedByInstructor ? 'Ja' : 'Nein';

        // Backend url
        $strUrlBackend = sprintf(
            '%s/contao?do=sac_calendar_event_stories_tool&act=edit&id=%s',
            Environment::get('url'),
            $objArticle->id
        );

        // Key data
        $arrKeyData = [];

        if (!empty($objArticle->tourTechDifficulty)) {
            $arrKeyData[] = $objArticle->tourTechDifficulty;
        }

        if (!empty($objArticle->tourProfile)) {
            $arrKeyData[] = $objArticle->tourProfile;
        }

        // tourTypes
        $arrTourTypes = CalendarEventsHelper::getTourTypesAsArray($objEvent, 'title');

        $options = ['multiline' => true];
        $objPhpWord->replace('checkedByInstructor', $strCheckedByInstructor, $options);
        $objPhpWord->replace('title', $objArticle->title, $options);
        $objPhpWord->replace('text', $objArticle->text, $options);
        $objPhpWord->replace('authorName', $objArticle->authorName, $options);
        $objPhpWord->replace('sacMemberId', $objArticle->sacMemberId, $options);
        $objPhpWord->replace('authorEmail', $strAuthorEmail, $options);
        $objPhpWord->replace('addedOn', date('Y-m-d', (int) $objArticle->addedOn), $options);
        $objPhpWord->replace('tourTypes', implode(', ', $arrTourTypes), $options);
        $objPhpWord->replace('organizers', $strOrganizers, $options);
        $objPhpWord->replace('mainInstructorName', $mainInstructorName, $options);
        $objPhpWord->replace('mainInstructorEmail', $mainInstructorEmail, $options);
        $objPhpWord->replace('eventDates', $strEventDates, $options);
        $objPhpWord->replace('tourWaypoints', $objArticle->tourWaypoints, $options);
        $objPhpWord->replace('keyData', implode("\r\n", $arrKeyData), $options);
        $objPhpWord->replace('tourHighlights', $objArticle->tourHighlights, $options);
        $objPhpWord->replace('tourPublicTransportInfo', $objArticle->tourPublicTransportInfo, $options);

        // Footer
        $objPhpWord->replace('eventId', $objEvent->id);
        $objPhpWord->replace('articleId', $objArticle->id);
        $objPhpWord->replace('urlBackend', htmlentities($strUrlBackend));

        // Images
        if (!empty($objArticle->multiSRC)) {
            $arrImages = StringUtil::deserialize($objArticle->multiSRC, true);

            if (!empty($arrImages)) {
                $objFiles = FilesModel::findMultipleByUuids($arrImages);
                $i = 0;

                while ($objFiles->next()) {
                    if (!is_file($this->projectDir.'/'.$objFiles->path)) {
                        continue;
                    }

                    ++$i;

                    Files::getInstance()->copy($objFiles->path, $imageDir.'/'.$objFiles->name);

                    $options = ['multiline' => false];

                    $objPhpWord->createClone('i');
                    $objPhpWord->addToClone('i', 'i', $i, $options);
                    $objPhpWord->addToClone('i', 'fileName', $objFiles->name, $options);

                    $arrMeta = $this->getMeta($objFiles->current(), $this->locale);
                    $objPhpWord->addToClone('i', 'photographerName', $arrMeta['photographer'], $options);
                    $objPhpWord->addToClone('i', 'imageCaption', $arrMeta['caption'], $options);
                }
            }
        }

        $zipSrc = sprintf(
            '%s/%s/article_%s_%s.zip',
            $this->projectDir,
            $this->tempDir,
            $objArticle->id,
            time()
        );

        // Generate docx and save it in system/tmp/...
        $objPhpWord->sendToBrowser(false)
            ->generateUncached(true)
            ->generate()
        ;

        // Zip archive
        (new Zip())
            ->ignoreDotFiles(false)
            ->stripSourcePath($this->projectDir.'/'.$targetDir)
            ->addDirRecursive($this->projectDir.'/'.$targetDir)
            ->run($zipSrc)
        ;

        $this->binaryFileDownload->sendFileToBrowser($zipSrc, basename($zipSrc));
    }

    private function getMeta(FilesModel $objFile, string $lang = 'en'): array
    {
        $arrMeta = StringUtil::deserialize($objFile->meta, true);

        if (!isset($arrMeta[$lang]['caption'])) {
            $arrMeta[$lang]['caption'] = '';
        }

        if (!isset($arrMeta[$lang]['photographer'])) {
            $arrMeta[$lang]['photographer'] = '';
        }

        return $arrMeta[$lang];
    }
}
