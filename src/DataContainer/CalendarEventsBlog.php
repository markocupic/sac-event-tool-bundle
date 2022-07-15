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
use Contao\CalendarEventsBlogModel;
use Contao\CalendarEventsModel;
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
use Markocupic\SacEventToolBundle\Config\EventExecutionState;
use Markocupic\SacEventToolBundle\Download\BinaryFileDownload;
use Markocupic\ZipBundle\Zip\Zip;
use PhpOffice\PhpWord\Exception\CopyFileException;
use PhpOffice\PhpWord\Exception\CreateTemporaryFileException;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Security\Core\Security;

class CalendarEventsBlog
{
    private Security $security;
    private Connection $connection;
    private RequestStack $requestStack;
    private BinaryFileDownload $binaryFileDownload;
    private string $projectDir;
    private string $tempDir;
    private string $eventBlogExportTemplate;
    private string $locale;

    public function __construct(Security $security, Connection $connection, RequestStack $requestStack, BinaryFileDownload $binaryFileDownload, string $projectDir, string $tempDir, string $eventBlogExportTemplate, string $locale)
    {
        $this->security = $security;
        $this->connection = $connection;
        $this->requestStack = $requestStack;
        $this->binaryFileDownload = $binaryFileDownload;
        $this->projectDir = $projectDir;
        $this->tempDir = $tempDir;
        $this->eventBlogExportTemplate = $eventBlogExportTemplate;
        $this->locale = $locale;
    }

    /**
     * @Callback(table="tl_calendar_events_blog", target="config.onload")
     *
     * @throws \Exception
     */
    public function route(): void
    {
        $request = $this->requestStack->getCurrentRequest();

        $id = $request->query->get('id');

        if ($id && 'exportBlog' === $request->query->get('action')) {
            if (null !== ($objBlog = CalendarEventsBlogModel::findByPk($id))) {
                $this->exportBlog($objBlog);
            }
        }
    }

    /**
     * @Callback(table="tl_calendar_events_blog", target="config.onload")
     */
    public function setPalettes(): void
    {
        $user = $this->security->getUser();

        // Overwrite readonly attribute for admins
        if ($user->admin) {
            $fields = ['sacMemberId', 'eventId', 'authorName'];

            foreach ($fields as $field) {
                $GLOBALS['TL_DCA']['tl_calendar_events_blog']['fields'][$field]['eval']['readonly'] = false;
            }
        }
    }

    /**
     * @Callback(table="tl_calendar_events_blog", target="config.onload")
     *
     * @throws \Exception
     */
    public function deleteUnfinishedAndOldEntries(): void
    {
        // Delete old and unpublished blogs
        $limit = time() - 60 * 60 * 24 * 30;

        $this->connection->executeStatement(
            'DELETE FROM tl_calendar_events_blog WHERE tstamp < ? AND publishState < ?',
            [$limit, 3],
        );

        // Delete unfinished blogs older the 14 days
        $limit = time() - 60 * 60 * 24 * 14;

        $this->connection->executeStatement(
            'DELETE FROM tl_calendar_events_blog WHERE tstamp < ? AND text = ? AND youtubeId = ? AND multiSRC = ?',
            [$limit, '', '', null]
        );

        // Keep blogs up to date, if events are renamed f.ex.
        $stmt = $this->connection->executeQuery('SELECT * FROM tl_calendar_events_blog', []);

        while (false !== ($arrBlog = $stmt->fetchAssociative())) {
            $objBlogModel = CalendarEventsBlogModel::findByPk($arrBlog['id']);
            $objEvent = $objBlogModel->getRelated('eventId');

            if (null !== $objEvent) {
                $objBlogModel->eventTitle = $objEvent->title;
                $objBlogModel->substitutionEvent = EventExecutionState::STATE_ADAPTED === $objEvent->executionState && '' !== $objEvent->eventSubstitutionText ? $objEvent->eventSubstitutionText : '';
                $objBlogModel->eventStartDate = $objEvent->startDate;
                $objBlogModel->eventEndDate = $objEvent->endDate;
                $objBlogModel->organizers = $objEvent->organizers;

                $aDates = [];
                $arrDates = StringUtil::deserialize($objEvent->eventDates, true);

                foreach ($arrDates as $arrDate) {
                    $aDates[] = $arrDate['new_repeat'];
                }

                $objBlogModel->eventDates = serialize($aDates);
                $objBlogModel->save();
            }
        }
    }

    /**
     * Add an image to each record.
     *
     * @Callback(table="tl_calendar_events_blog", target="list.label.label")
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
    private function exportBlog(CalendarEventsBlogModel $objBlog): void
    {
        $objEvent = CalendarEventsModel::findByPk($objBlog->eventId);

        if (null === $objEvent) {
            throw new \Exception('Event not found.');
        }

        if (!is_file($this->projectDir.'/'.$this->eventBlogExportTemplate)) {
            throw new \Exception('Template file not found.');
        }

        // target dir & file
        $targetDir = sprintf('system/tmp/blog_%s_%s', $objBlog->id, time());
        $imageDir = sprintf('%s/images', $targetDir);

        // Create folder
        new Folder($imageDir);

        $targetFile = sprintf('%s/event_blog_%s.docx', $targetDir, $objBlog->id);
        $objPhpWord = new MsWordTemplateProcessor($this->eventBlogExportTemplate, $targetFile);

        // Organizers
        $arrOrganizers = CalendarEventsHelper::getEventOrganizersAsArray($objEvent);
        $strOrganizers = implode(', ', $arrOrganizers);

        // Instructors
        $mainInstructorName = CalendarEventsHelper::getMainInstructorName($objEvent);
        $mainInstructorEmail = '';

        if (null !== ($objInstructor = UserModel::findByPk($objEvent->mainInstructor))) {
            $mainInstructorEmail = $objInstructor->email;
        }

        $objMember = MemberModel::findBySacMemberId($objBlog->sacMemberId);
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
        $strCheckedByInstructor = $objBlog->checkedByInstructor ? 'Ja' : 'Nein';

        // Backend url
        $strUrlBackend = sprintf(
            '%s/contao?do=sac_calendar_events_blog_tool&act=edit&id=%s',
            Environment::get('url'),
            $objBlog->id
        );

        // Key data
        $arrKeyData = [];

        if (!empty($objBlog->tourTechDifficulty)) {
            $arrKeyData[] = $objBlog->tourTechDifficulty;
        }

        if (!empty($objBlog->tourProfile)) {
            $arrKeyData[] = $objBlog->tourProfile;
        }

        // tourTypes
        $arrTourTypes = CalendarEventsHelper::getTourTypesAsArray($objEvent, 'title');

        $options = ['multiline' => true];
        $objPhpWord->replace('checkedByInstructor', $strCheckedByInstructor, $options);
        $objPhpWord->replace('title', $objBlog->title, $options);
        $objPhpWord->replace('text', $objBlog->text, $options);
        $objPhpWord->replace('authorName', $objBlog->authorName, $options);
        $objPhpWord->replace('sacMemberId', $objBlog->sacMemberId, $options);
        $objPhpWord->replace('authorEmail', $strAuthorEmail, $options);
        $objPhpWord->replace('dateAdded', date('Y-m-d', (int) $objBlog->dateAdded), $options);
        $objPhpWord->replace('tourTypes', implode(', ', $arrTourTypes), $options);
        $objPhpWord->replace('organizers', $strOrganizers, $options);
        $objPhpWord->replace('mainInstructorName', $mainInstructorName, $options);
        $objPhpWord->replace('mainInstructorEmail', $mainInstructorEmail, $options);
        $objPhpWord->replace('eventDates', $strEventDates, $options);
        $objPhpWord->replace('tourWaypoints', $objBlog->tourWaypoints, $options);
        $objPhpWord->replace('keyData', implode("\r\n", $arrKeyData), $options);
        $objPhpWord->replace('tourHighlights', $objBlog->tourHighlights, $options);
        $objPhpWord->replace('tourPublicTransportInfo', $objBlog->tourPublicTransportInfo, $options);

        // Footer
        $objPhpWord->replace('eventId', $objEvent->id);
        $objPhpWord->replace('blogId', $objBlog->id);
        $objPhpWord->replace('urlBackend', htmlentities($strUrlBackend));

        // Images
        if (!empty($objBlog->multiSRC)) {
            $arrImages = StringUtil::deserialize($objBlog->multiSRC, true);

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
            '%s/%s/blog_%s_%s.zip',
            $this->projectDir,
            $this->tempDir,
            $objBlog->id,
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
