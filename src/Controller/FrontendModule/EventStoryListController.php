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

namespace Markocupic\SacEventToolBundle\Controller\FrontendModule;

use Contao\CalendarEventsStoryModel;
use Contao\Config;
use Contao\CoreBundle\Controller\FrontendModule\AbstractFrontendModuleController;
use Contao\CoreBundle\Exception\PageNotFoundException;
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\CoreBundle\ServiceAnnotation\FrontendModule;
use Contao\Environment;
use Contao\FilesModel;
use Contao\MemberModel;
use Contao\ModuleModel;
use Contao\PageModel;
use Contao\Pagination;
use Contao\StringUtil;
use Contao\Template;
use Contao\Validator;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * @FrontendModule(EventStoryListController::TYPE, category="sac_event_tool_frontend_modules")
 */
class EventStoryListController extends AbstractFrontendModuleController
{
    public const TYPE = 'event_story_list';

    /**
     * @var CalendarEventsStoryModel
     */
    private $stories;

    /**
     * @var bool
     */
    private $isAjaxRequest;

    /**
     * @var PageModel
     */
    private $page;

    public function __invoke(Request $request, ModuleModel $model, string $section, array $classes = null, PageModel $page = null): Response
    {
        /** @var CalendarEventsStoryModel $calendarEventsStoryModelAdapter */
        $calendarEventsStoryModelAdapter = $this->get('contao.framework')->getAdapter(CalendarEventsStoryModel::class);

        /** @var StringUtil $stringUtilAdapter */
        $stringUtilAdapter = $this->get('contao.framework')->getAdapter(StringUtil::class);

        /** @var Environment $environmentAdapter */
        $environmentAdapter = $this->get('contao.framework')->getAdapter(Environment::class);

        $this->isAjaxRequest = $environmentAdapter->get('isAjaxRequest');

        $this->page = $page;

        $arrIDS = [];
        $arrOptions = ['order' => 'dateAdded DESC'];

        /** @var CalendarEventsStoryModel $objStories */
        $objStories = $calendarEventsStoryModelAdapter->findBy(
            ['tl_calendar_events_story.publishState=?'],
            ['3'],
            $arrOptions
        );

        if (null !== $objStories) {
            while ($objStories->next()) {
                $arrOrganizers = $stringUtilAdapter->deserialize($objStories->organizers, true);

                if (\count(array_intersect($arrOrganizers, $stringUtilAdapter->deserialize($model->story_eventOrganizers, true))) > 0) {
                    $arrIDS[] = $objStories->id;
                }
            }
        }

        $this->stories = $calendarEventsStoryModelAdapter->findMultipleByIds($arrIDS, $arrOptions);

        if (null === $this->stories) {
            // Return empty string
            return new Response('', Response::HTTP_NO_CONTENT);
        }

        // Call the parent method
        return parent::__invoke($request, $model, $section, $classes);
    }

    public static function getSubscribedServices(): array
    {
        $services = parent::getSubscribedServices();

        $services['contao.framework'] = ContaoFramework::class;

        return $services;
    }

    protected function getResponse(Template $template, ModuleModel $model, Request $request): Response|null
    {
        // Get project dir
        $projectDir = $this->getParameter('kernel.project_dir');

        /** @var MemberModel $memberModelModelAdapter */
        $memberModelModelAdapter = $this->get('contao.framework')->getAdapter(MemberModel::class);

        /** @var StringUtil $stringUtilAdapter */
        $stringUtilAdapter = $this->get('contao.framework')->getAdapter(StringUtil::class);

        /** @var Config $configAdapter */
        $configAdapter = $this->get('contao.framework')->getAdapter(Config::class);

        /** @var Validator $validatorAdapter */
        $validatorAdapter = $this->get('contao.framework')->getAdapter(Validator::class);

        /** @var FilesModel $filesModelAdapter */
        $filesModelAdapter = $this->get('contao.framework')->getAdapter(FilesModel::class);

        /** @var Environment $environmentAdapter */
        $environmentAdapter = $this->get('contao.framework')->getAdapter(Environment::class);

        /** @var PageModel $pageModelAdapter */
        $pageModelAdapter = $this->get('contao.framework')->getAdapter(PageModel::class);

        $template->language = $this->page->language;

        $template->isAjaxRequest = $this->isAjaxRequest;

        $objPageModel = null;

        if ($model->jumpTo) {
            $objPageModel = $pageModelAdapter->findByPk($model->jumpTo);
        }

        $arrAllStories = [];
        $arrAllIds = [];

        while ($this->stories->next()) {
            $arrStory = $this->stories->row();
            $arrAllIds[] = $arrStory['id'];
            $objMember = $memberModelModelAdapter->findOneBySacMemberId($arrStory['sacMemberId']);
            $arrStory['authorId'] = $objMember->id;
            $arrStory['authorName'] = null !== $objMember ? $objMember->firstname.' '.$objMember->lastname : $arrStory['authorName'];
            $arrStory['href'] = null !== $objPageModel ? ampersand($objPageModel->getFrontendUrl(($configAdapter->get('useAutoItem') ? '/' : '/items/').$this->stories->id)) : null;

            $multiSRC = $stringUtilAdapter->deserialize($arrStory['multiSRC'], true);

            // Add a random image to the list
            $arrStory['singleSRC'] = null;

            if (!empty($multiSRC) && \is_array($multiSRC)) {
                $k = array_rand($multiSRC);
                $singleSRC = $multiSRC[$k];

                if ($validatorAdapter->isUuid($singleSRC)) {
                    $objFiles = $filesModelAdapter->findByUuid($singleSRC);

                    if (null !== $objFiles) {
                        if (is_file($projectDir.'/'.$objFiles->path)) {
                            $arrStory['singleSRC'] = [
                                'id' => $objFiles->id,
                                'path' => $objFiles->path,
                                'uuid' => $stringUtilAdapter->binToUuid($objFiles->uuid),
                                'name' => $objFiles->name,
                                'singleSRC' => $objFiles->path,
                                'title' => $stringUtilAdapter->specialchars($objFiles->name),
                                'filesModel' => $objFiles->current(),
                            ];
                        }
                    }
                }
            }

            $arrAllStories[] = $arrStory;
        }
        $template->arrAllIds = $arrAllIds;

        $total = \count($arrAllStories);
        $limit = $total;
        $offset = 0;

        // Overall limit
        if ($model->story_limit > 0) {
            $total = min($model->story_limit, $total);
            $limit = $total;
        }

        // Pagination
        if ($model->perPage > 0) {
            $id = 'page_e'.$model->id;

            $page = !empty($request->query->get($id)) ? $request->query->get($id) : 1;

            // Do not index or cache the page if the page number is outside the range
            if ($page < 1 || $page > max(ceil($total / $model->perPage), 1)) {
                throw new PageNotFoundException('Page not found: '.$environmentAdapter->get('uri'));
            }

            $offset = ($page - 1) * $model->perPage;
            $limit = min($model->perPage + $offset, $total);

            /** @var Pagination $objPagination */
            $objPagination = new Pagination($total, $model->perPage, $configAdapter->get('maxPaginationLinks'), $id);
            $template->pagination = $objPagination->generate("\n  ");
        }

        $arrStories = [];

        for ($i = $offset; $i < $limit; ++$i) {
            if (!isset($arrAllStories[$i]) || !\is_array($arrAllStories[$i])) {
                continue;
            }
            $arrStories[] = $arrAllStories[$i];
        }
        $template->stories = $arrStories;

        return $template->getResponse();
    }
}
