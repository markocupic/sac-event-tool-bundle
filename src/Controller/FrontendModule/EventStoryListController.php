<?php

declare(strict_types=1);

/**
 * SAC Event Tool Web Plugin for Contao
 * Copyright (c) 2008-2020 Marko Cupic
 * @package sac-event-tool-bundle
 * @author Marko Cupic m.cupic@gmx.ch, 2017-2020
 * @link https://github.com/markocupic/sac-event-tool-bundle
 */

namespace Markocupic\SacEventToolBundle\Controller\FrontendModule;

use Contao\CalendarEventsStoryModel;
use Contao\Config;
use Contao\CoreBundle\Controller\FrontendModule\AbstractFrontendModuleController;
use Contao\CoreBundle\Exception\PageNotFoundException;
use Contao\CoreBundle\Framework\ContaoFramework;
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
use Contao\CoreBundle\ServiceAnnotation\FrontendModule;

/**
 * Class EventStoryListController
 * @package Markocupic\SacEventToolBundle\Controller\FrontendModule
 * @FrontendModule("event_story_list", category="sac_event_tool_frontend_modules")
 */
class EventStoryListController extends AbstractFrontendModuleController
{

    /**
     * @var CalendarEventsStoryModel
     */
    protected $stories;

    /**
     * @var bool
     */
    protected $isAjaxRequest;

    /**
     * @param Request $request
     * @param ModuleModel $model
     * @param string $section
     * @param array|null $classes
     * @param PageModel|null $page
     * @return Response
     */
    public function __invoke(Request $request, ModuleModel $model, string $section, array $classes = null, PageModel $page = null): Response
    {
        /** @var  CalendarEventsStoryModel $calendarEventsStoryModelAdapter */
        $calendarEventsStoryModelAdapter = $this->get('contao.framework')->getAdapter(CalendarEventsStoryModel::class);

        /** @var StringUtil $stringUtilAdapter */
        $stringUtilAdapter = $this->get('contao.framework')->getAdapter(StringUtil::class);

        /** @var Environment $environmentAdapter */
        $environmentAdapter = $this->get('contao.framework')->getAdapter(Environment::class);
        $this->isAjaxRequest = $environmentAdapter->get('isAjaxRequest');

        $arrIDS = [];
        $arrOptions = ['order' => 'addedOn DESC'];

        /** @var  CalendarEventsStoryModel $objStories */
        $objStories = $calendarEventsStoryModelAdapter->findBy(
            ['tl_calendar_events_story.publishState=?'],
            ['3'],
            $arrOptions
        );

        if ($objStories !== null)
        {
            while ($objStories->next())
            {
                $arrOrganizers = $stringUtilAdapter->deserialize($objStories->organizers, true);
                if (count(array_intersect($arrOrganizers, $stringUtilAdapter->deserialize($model->story_eventOrganizers, true))) > 0)
                {
                    $arrIDS[] = $objStories->id;
                }
            }
        }

        $this->stories = $calendarEventsStoryModelAdapter->findMultipleByIds($arrIDS, $arrOptions);

        if ($this->stories === null)
        {
            // Return empty string
            return new Response('', Response::HTTP_NO_CONTENT);
        }

        // Call the parent method
        return parent::__invoke($request, $model, $section, $classes);
    }

    /**
     * @return array
     */
    public static function getSubscribedServices(): array
    {
        $services = parent::getSubscribedServices();

        $services['contao.framework'] = ContaoFramework::class;

        return $services;
    }

    /**
     * @param Template $template
     * @param ModuleModel $model
     * @param Request $request
     * @return null|Response
     */
    protected function getResponse(Template $template, ModuleModel $model, Request $request): ?Response
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

        $template->isAjaxRequest = $this->isAjaxRequest;

        $objPageModel = null;
        if ($model->jumpTo)
        {
            $objPageModel = $pageModelAdapter->findByPk($model->jumpTo);
        }

        $arrAllStories = [];
        $arrAllIds = [];
        while ($this->stories->next())
        {
            $arrStory = $this->stories->row();
            $arrAllIds[] = $arrStory['id'];
            $objMember = $memberModelModelAdapter->findOneBySacMemberId($arrStory['sacMemberId']);
            $arrStory['authorId'] = $objMember->id;
            $arrStory['authorName'] = $objMember !== null ? $objMember->firstname . ' ' . $objMember->lastname : $arrStory['authorName'];
            $arrStory['href'] = $objPageModel !== null ? ampersand($objPageModel->getFrontendUrl(($configAdapter->get('useAutoItem') ? '/' : '/items/') . $this->stories->id)) : null;

            $multiSRC = $stringUtilAdapter->deserialize($arrStory['multiSRC'], true);

            // Add a random image to the list
            $arrStory['singleSRC'] = null;
            if (!empty($multiSRC) && is_array($multiSRC))
            {
                $k = array_rand($multiSRC);
                $singleSRC = $multiSRC[$k];
                if ($validatorAdapter->isUuid($singleSRC))
                {
                    $objFiles = $filesModelAdapter->findByUuid($singleSRC);
                    if ($objFiles !== null)
                    {
                        if (is_file($projectDir . '/' . $objFiles->path))
                        {
                            $arrStory['singleSRC'] = [
                                'id'         => $objFiles->id,
                                'path'       => $objFiles->path,
                                'uuid'       => $stringUtilAdapter->binToUuid($objFiles->uuid),
                                'name'       => $objFiles->name,
                                'singleSRC'  => $objFiles->path,
                                'title'      => $stringUtilAdapter->specialchars($objFiles->name),
                                'filesModel' => $objFiles->current(),
                            ];
                        }
                    }
                }
            }

            $arrAllStories[] = $arrStory;
        }
        $template->arrAllIds = $arrAllIds;


        $total = count($arrAllStories);
        $limit = $total;
        $offset = 0;

        // Overall limit
        if ($model->story_limit > 0)
        {
            $total = min($model->story_limit, $total);
            $limit = $total;
        }

        // Pagination
        if ($model->perPage > 0)
        {
            $id = 'page_e' . $model->id;

            $page = (!empty($request->query->get($id))) ? $request->query->get($id) : 1;

            // Do not index or cache the page if the page number is outside the range
            if ($page < 1 || $page > max(ceil($total / $model->perPage), 1))
            {
                throw new PageNotFoundException('Page not found: ' . $environmentAdapter->get('uri'));
            }

            $offset = ($page - 1) * $model->perPage;
            $limit = min($model->perPage + $offset, $total);

            /** @var Pagination $objPagination */
            $objPagination = new Pagination($total, $model->perPage, $configAdapter->get('maxPaginationLinks'), $id);
            $template->pagination = $objPagination->generate("\n  ");
        }

        $arrStories = [];
        for ($i = $offset; $i < $limit; $i++)
        {
            if (!isset($arrAllStories[$i]) || !is_array($arrAllStories[$i]))
            {
                continue;
            }
            $arrStories[] = $arrAllStories[$i];
        }
        $template->stories = $arrStories;

        return $template->getResponse();
    }

}
