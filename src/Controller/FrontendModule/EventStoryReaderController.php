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

namespace Markocupic\SacEventToolBundle\Controller\FrontendModule;

use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;
use Contao\CalendarEventsModel;
use Contao\CalendarEventsStoryModel;
use Contao\Config;
use Contao\CoreBundle\Controller\FrontendModule\AbstractFrontendModuleController;
use Contao\CoreBundle\Exception\PageNotFoundException;
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\CoreBundle\ServiceAnnotation\FrontendModule;
use Contao\CoreBundle\Util\SymlinkUtil;
use Contao\Environment;
use Contao\File;
use Contao\FilesModel;
use Contao\Folder;
use Contao\Input;
use Contao\MemberModel;
use Contao\ModuleModel;
use Contao\PageModel;
use Contao\StringUtil;
use Contao\System;
use Contao\Template;
use Contao\Validator;
use Haste\Util\Url;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;

/**
 * Class EventStoryReaderController.
 *
 * @FrontendModule("event_story_reader", category="sac_event_tool_frontend_modules")
 */
class EventStoryReaderController extends AbstractFrontendModuleController
{
    /**
     * @var RequestStack
     */
    private $requestStack;

    /**
     * @var string
     */
    private $projectDir;

    /**
     * @var CalendarEventsStoryModel
     */
    private $story;

    /**
     * @var bool
     */
    private $isPreviewMode = false;

    /**
     * EventStoryReaderController constructor.
     */
    public function __construct(RequestStack $requestStack, string $projectDir)
    {
        $this->requestStack = $requestStack;
        $this->projectDir = $projectDir;
    }

    public function __invoke(Request $request, ModuleModel $model, string $section, array $classes = null, ?PageModel $page = null): Response
    {
        /** @var CalendarEventsStoryModel $calendarEventsStoryModelAdapter */
        $calendarEventsStoryModelAdapter = $this->get('contao.framework')->getAdapter(CalendarEventsStoryModel::class);

        /** @var Config $configAdapter */
        $configAdapter = $this->get('contao.framework')->getAdapter(Config::class);

        /** @var Environment $environmentAdapter */
        $environmentAdapter = $this->get('contao.framework')->getAdapter(Environment::class);

        /** @var Input $inputAdapter */
        $inputAdapter = $this->get('contao.framework')->getAdapter(Input::class);

        // Set the item from the auto_item parameter
        if (!isset($_GET['items']) && $configAdapter->get('useAutoItem') && isset($_GET['auto_item'])) {
            $inputAdapter->setGet('items', $inputAdapter->get('auto_item'));
        }

        // Do not index or cache the page if no event has been specified
        if ($page && empty($inputAdapter->get('items'))) {
            $page->noSearch = 1;
            $page->cache = 0;

            // Return empty string
            return new Response('', Response::HTTP_NO_CONTENT);
        }

        if (!empty($inputAdapter->get('securityToken'))) {
            $arrColumns = ['tl_calendar_events_story.securityToken=?', 'tl_calendar_events_story.id=?'];
            $arrValues = [$inputAdapter->get('securityToken'), $inputAdapter->get('items')];
            $this->isPreviewMode = true;
        } else {
            $arrColumns = ['tl_calendar_events_story.publishState=?', 'tl_calendar_events_story.id=?'];
            $arrValues = ['3', $inputAdapter->get('items')];
        }

        $this->story = $calendarEventsStoryModelAdapter->findBy($arrColumns, $arrValues);

        if (null === $this->story) {
            throw new PageNotFoundException('Page not found: '.$environmentAdapter->get('uri'));
        }

        // Call the parent method
        return parent::__invoke($request, $model, $section, $classes, $page);
    }

    public static function getSubscribedServices(): array
    {
        $services = parent::getSubscribedServices();

        $services['contao.framework'] = ContaoFramework::class;

        return $services;
    }

    /**
     * @throws \Exception
     */
    protected function getResponse(Template $template, ModuleModel $model, Request $request): ?Response
    {
        /** @var MemberModel $memberModelModelAdapter */
        $memberModelModelAdapter = $this->get('contao.framework')->getAdapter(MemberModel::class);

        /** @var StringUtil $stringUtilAdapter */
        $stringUtilAdapter = $this->get('contao.framework')->getAdapter(StringUtil::class);

        /** @var Validator $validatorAdapter */
        $validatorAdapter = $this->get('contao.framework')->getAdapter(Validator::class);

        /** @var FilesModel $filesModelAdapter */
        $filesModelAdapter = $this->get('contao.framework')->getAdapter(FilesModel::class);

        /** @var CalendarEventsModel $calendarEventsModelAdapter */
        $calendarEventsModelAdapter = $this->get('contao.framework')->getAdapter(CalendarEventsModel::class);

        /** @var Url $urlAdapter */
        $urlAdapter = $this->get('contao.framework')->getAdapter(Url::class);

        // Get root dir
        $rootDir = System::getContainer()->getParameter('kernel.project_dir');

        // Set data
        $template->setData($this->story->row());

        // Set title as headline
        $template->headline = $this->story->title;

        // Fallback if author is no more findable in tl_member
        $objAuthor = $memberModelModelAdapter->findOneBySacMemberId($this->story->sacMemberId);
        $template->authorName = null !== $objAuthor ? $objAuthor->firstname.' '.$objAuthor->lastname : $this->story->authorName;

        // !!! $objEvent can be NULL, if the related event no more exists
        $objEvent = $calendarEventsModelAdapter->findByPk($this->story->eventId);
        $template->objEvent = $objEvent;

        // Add qrcode, if it is not preview mode
        $request = $this->requestStack->getCurrentRequest();

        if(!$this->isPreviewMode) {

            if ($request->query->has('referer')) {
                $url = base64_decode($request->query->get('referer', ''), true);
                $url = $urlAdapter->addQueryString('showEventStory='.$this->story->id, $url);
            } else {
                $url = $urlAdapter->addQueryString('showEventStory='.$this->story->id);
            }

            if ('' !== $url) {
                if (null !== ($qrCodePath = $this->getQrCodeFromUrl($url))) {
                    $template->qrCodePath = $qrCodePath;
                    $template->directLink = $url;
                }
            }
        }

        // Add gallery
        $images = [];
        $arrMultiSRC = $stringUtilAdapter->deserialize($this->story->multiSRC, true);

        foreach ($arrMultiSRC as $uuid) {
            if ($validatorAdapter->isUuid($uuid)) {
                $objFiles = $filesModelAdapter->findByUuid($uuid);

                if (null !== $objFiles) {
                    if (is_file($rootDir.'/'.$objFiles->path)) {
                        /** @var File $objFile */
                        $objFile = new File($objFiles->path);

                        if ($objFile->isImage) {
                            $arrMeta = $stringUtilAdapter->deserialize($objFiles->meta, true);
                            $title = '';
                            $alt = '';
                            $caption = '';
                            $photographer = '';

                            if (isset($arrMeta['de'])) {
                                $title = $arrMeta['de']['title'];
                                $alt = $arrMeta['de']['alt'];
                                $caption = $arrMeta['de']['caption'];
                                $photographer = $arrMeta['de']['photographer'];
                            }

                            $arrFigureCaption = [];

                            if ('' !== $caption) {
                                $arrFigureCaption[] = $caption;
                            }

                            if ('' !== $photographer) {
                                $arrFigureCaption[] = '(Foto: '.$photographer.')';
                            }
                            $strFigureCaption = implode(', ', $arrFigureCaption);

                            $linkTitle = '';
                            $linkTitle .= '' !== $caption ? $caption : '';
                            $linkTitle .= '' !== $photographer ? ' (Foto: '.$photographer.')' : '';

                            $images[$objFiles->path] = [
                                'id' => $objFiles->id,
                                'path' => $objFiles->path,
                                'uuid' => $objFiles->uuid,
                                'name' => $objFile->basename,
                                'singleSRC' => $objFiles->path,
                                'filesModel' => $objFiles->current(),
                                'caption' => $stringUtilAdapter->specialchars($caption),
                                'alt' => $stringUtilAdapter->specialchars($alt),
                                'title' => $stringUtilAdapter->specialchars($title),
                                'photographer' => $stringUtilAdapter->specialchars($photographer),
                                'strFigureCaption' => $stringUtilAdapter->specialchars($strFigureCaption),
                                'linkTitle' => $stringUtilAdapter->specialchars($linkTitle),
                            ];
                        }
                    }
                }
            }
        }

        // Custom image sorting
        if ('' !== $this->story->orderSRC) {
            $tmp = $stringUtilAdapter->deserialize($this->story->orderSRC);

            if (!empty($tmp) && \is_array($tmp)) {
                // Remove all values
                $arrOrder = array_map(
                    static function (): void {
                    },
                    array_flip($tmp)
                );

                // Move the matching elements to their position in $arrOrder
                foreach ($images as $k => $v) {
                    if (\array_key_exists($v['uuid'], $arrOrder)) {
                        $arrOrder[$v['uuid']] = $v;
                        unset($images[$k]);
                    }
                }

                // Append the left-over images at the end
                if (!empty($images)) {
                    $arrOrder = array_merge($arrOrder, array_values($images));
                }

                // Remove empty (unreplaced) entries
                $images = array_values(array_filter($arrOrder));
                unset($arrOrder);
            }
        }
        $images = array_values($images);

        $template->images = \count($images) ? $images : null;

        // Add youtube movie
        $template->youtubeId = '' !== $this->story->youtubeId ? $this->story->youtubeId : null;

        return $template->getResponse();
    }

    private function getQrCodeFromUrl(string $url): ?string
    {
        // Generate QR code folder
        $objFolder = new Folder('system/eventstoryqrcodes');

        // Symlink
        SymlinkUtil::symlink($objFolder->path, 'web/'.$objFolder->path, $this->projectDir);

        // Generate path
        $filepath = sprintf($objFolder->path.'/'.'eventStoryQRcode_%s.png', md5($url));

        // Defaults
        $opt = [
            'version' => 5,
            'scale' => 4,
            'outputType' => QRCode::OUTPUT_IMAGE_PNG,
            'eccLevel' => QRCode::ECC_L,
            'cachefile' => $filepath,
        ];

        if (!$blnCache && isset($opt['cachefile'])) {
            unset($opt['cachefile']);
        }

        $options = new QROptions($opt);

        // Generate QR and return the image path
        if ((new QRCode($options))->render($url, $filepath)) {
            return $filepath;
        }

        return null;
    }
}
