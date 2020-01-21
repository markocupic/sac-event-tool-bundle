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

use Contao\CoreBundle\Controller\FrontendModule\AbstractFrontendModuleController;
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\ModuleModel;
use Contao\PageModel;
use Contao\Template;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Contao\CoreBundle\ServiceAnnotation\FrontendModule;

/**
 * Class EventListController
 * @package Markocupic\SacEventToolBundle\Controller\FrontendModule
 */
class EventListController extends AbstractFrontendModuleController
{

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
        // Call the parent method
        return parent::__invoke($request, $model, $section, $classes);
    }

    /**
     * @return array
     */
    public static function getSubscribedServices(): array
    {
        $services = parent::getSubscribedServices();

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
        // Get filter params from request
        $arrQuery = $request->query->all();
        $arrFilterParam = [];
        if (!empty($arrQuery) && is_array($arrQuery))
        {
            $arrFilterParam = $arrQuery;
        }

        $template->arrPartialOpt = [
            'filterParam'     => base64_encode(serialize($arrFilterParam)),
            'imgSize'         => base64_encode($model->imgSize),
            'moduleId'        => $model->id,
            'calendarIds'     => base64_encode($model->cal_calendar),
            'eventTypes'      => base64_encode($model->eventType),
            'limitTotal'      => $model->eventListLimitTotal,
            'limitPerRequest' => $model->eventListLimitPerRequest,
        ];

        return $template->getResponse();
    }

}
