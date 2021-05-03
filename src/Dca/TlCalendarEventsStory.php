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

namespace Markocupic\SacEventToolBundle\Dca;

use Contao\Backend;
use Contao\CalendarEventsModel;
use Contao\CalendarEventsStoryModel;
use Contao\Config;
use Contao\Database;
use Contao\DataContainer;
use Contao\Environment;
use Contao\Files;
use Contao\FilesModel;
use Contao\Folder;
use Contao\Input;
use Contao\MemberModel;
use Contao\StringUtil;
use Markocupic\PhpOffice\PhpWord\MsWordTemplateProcessor;
use Markocupic\SacEventToolBundle\CalendarEventsHelper;
use Markocupic\ZipBundle\Zip\Zip;

/**
 * Class TlCalendarEventsStory.
 */
class TlCalendarEventsStory extends Backend
{
    /**
     * Import the back end user object.
     */
    public function __construct()
    {
        parent::__construct();
        $this->import('BackendUser', 'User');

        if ('exportArticle' === Input::get('action') && Input::get('id') && null !== CalendarEventsStoryModel::findByPk(Input::get('id'))) {
            $objArticle = CalendarEventsStoryModel::findByPk(Input::get('id'));
            $this->exportArticle($objArticle);
        }
    }

    public function exportArticle(CalendarEventsStoryModel $objArticle): void
    {
        $objEvent = CalendarEventsModel::findByPk($objArticle->eventId);

        if (null === $objEvent) {
            throw new \Exception('Event not found.');
        }

        $templSrc = Config::get('SAC_EVT_TOUR_ARTICLE_EXPORT_TEMPLATE_SRC');

        if (!is_file(TL_ROOT.'/'.$templSrc)) {
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
        $arrInstructors = CalendarEventsHelper::getInstructorNamesAsArray($objEvent);
        $strInstructors = implode(', ', $arrInstructors);

        $objMember = MemberModel::findBySacMemberId($objArticle->sacMemberId);
        $strAuthorEmail = '';

        if (null !== $objMember) {
            $strAuthorEmail = $objMember->email;
        }

        // Event dates
        $arrEventDates = CalendarEventsHelper::getEventTimestamps($objEvent);
        $arrEventDates = array_map(
            static function ($tstamp) {
                return date('Y-m-d', (int) $tstamp);
            },
            $arrEventDates
        );
        $strEventDates = implode('\r\n', $arrEventDates);

        // Do publish in the club magazine
        $strDoPublishClubMagazine = $objArticle->doPublishInClubMagazine ? 'Ja' : 'Nein';

        // Checked by instructor
        $strCheckedByInstructor = $objArticle->checkedByInstructor ? 'Ja' : 'Nein';

        // Backend url
        $strUrlBackend = sprintf(
            '%s/contao?do=sac_calendar_event_stories_tool&act=edit&id=%s',
            Environment::get('url'),
            $objArticle->id
        );

        $options = ['multiline' => true];
        $objPhpWord->replace('doPublishClubMagazine', $strDoPublishClubMagazine, $options);
        $objPhpWord->replace('checkedByInstructor', $strCheckedByInstructor, $options);
        $objPhpWord->replace('title', $objArticle->title, $options);
        $objPhpWord->replace('text', $objArticle->text, $options);
        $objPhpWord->replace('authorName', $objArticle->authorName, $options);
        $objPhpWord->replace('sacMemberId', $objArticle->sacMemberId, $options);
        $objPhpWord->replace('authorEmail', $strAuthorEmail, $options);
        $objPhpWord->replace('addedOn', date('Y-m-d', (int) $objArticle->addedOn), $options);
        $objPhpWord->replace('organizers', $strOrganizers, $options);
        $objPhpWord->replace('instructors', $strInstructors, $options);
        $objPhpWord->replace('eventDates', $strEventDates, $options);
        $objPhpWord->replace('tourWaypoints', $objArticle->tourWaypoints, $options);
        $objPhpWord->replace('tourTechDifficulty', $objArticle->tourTechDifficulty, $options);
        $objPhpWord->replace('tourProfile', $objArticle->tourProfile, $options);
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
                    if (!is_file(TL_ROOT.'/'.$objFiles->path)) {
                        continue;
                    }
                    ++$i;
                    Files::getInstance()->copy($objFiles->path, $imageDir.'/'.$objFiles->name);
                    $options = ['multiline' => false];
                    $objPhpWord->createClone('i');
                    $objPhpWord->addToClone('i', 'i', $i, $options);
                    $objPhpWord->addToClone('i', 'fileName', $objFiles->name, $options);
                    $arrMeta = $this->getMeta($objFiles->current(), 'de');
                    $objPhpWord->addToClone('i', 'photographerName', $arrMeta['photographer'], $options);
                    $objPhpWord->addToClone('i', 'imageCaption', $arrMeta['caption'], $options);
                }
            }
        }

        $zipSrc = sprintf(
            '%s/system/tmp/article_%s_%s.zip',
            TL_ROOT,
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
            ->stripSourcePath(TL_ROOT.'/'.$targetDir)
            ->addDirRecursive(TL_ROOT.'/'.$targetDir)
            ->run($zipSrc)
        ;

        // Send zip archive to browser
        header('Content-Type: application/zip');
        header('Content-Disposition: attachment; filename="'.basename($zipSrc).'"');
        header('Content-Length: '.filesize($zipSrc));
        readfile($zipSrc);
        exit();
    }

    public function getMeta(FilesModel $objFile, string $lang = 'de'): array
    {
        if (null !== $objFile) {
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

    /**
     * Onload Callback
     * setPalette.
     */
    public function setPalettes(): void
    {
        // Overwrite readonly attribute for admins
        if ($this->User->admin) {
            $fields = ['sacMemberId', 'eventId', 'authorName'];

            foreach ($fields as $field) {
                $GLOBALS['TL_DCA']['tl_calendar_events_story']['fields'][$field]['eval']['readonly'] = false;
            }
        }
    }

    /**
     * OnLoad Callback
     * deleteUnfinishedAndOldEntries.
     */
    public function deleteUnfinishedAndOldEntries(): void
    {
        // Delete old and unpublished stories
        $limit = time() - 60 * 60 * 24 * 30;
        Database::getInstance()
            ->prepare('DELETE FROM tl_calendar_events_story WHERE tstamp<? AND publishState<?')
            ->execute($limit, 3)
        ;

        // Delete unfinished stories older the 14 days
        $limit = time() - 60 * 60 * 24 * 14;
        Database::getInstance()
            ->prepare('DELETE FROM tl_calendar_events_story WHERE tstamp<? AND text=? AND youtubeId=? AND multiSRC=?')
            ->execute($limit, '', '', null)
        ;

        // Keep stories up to date, if events are renamed f.ex.
        $objStory = Database::getInstance()
            ->prepare('SELECT * FROM tl_calendar_events_story')
            ->execute()
        ;

        while ($objStory->next()) {
            $objStoryModel = CalendarEventsStoryModel::findByPk($objStory->id);
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
     * @param array  $row
     * @param string $label
     * @param array  $args
     *
     * @return array
     */
    public function addIcon($row, $label, DataContainer $dc, $args)
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
}
