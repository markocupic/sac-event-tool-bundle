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

use Contao\Controller;
use Contao\CoreBundle\Controller\FrontendModule\AbstractFrontendModuleController;
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\Date;
use Contao\Environment;
use Contao\Input;
use Contao\ModuleModel;
use Contao\PageModel;
use Contao\StringUtil;
use Contao\Template;
use Haste\Form\Form;
use Haste\Util\Url;
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
     * @var array
     */
    protected $arrAllowedFields;

    /**
     * @var PageModel
     */
    protected $objPage;

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
        $this->objPage = $page;

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
        // Get filter params from request
        $arrQuery = $request->query->all();
        $arrFilterParam = [];
        if (!empty($arrQuery) && is_array($arrQuery))
        {
            $arrFilterParam = $arrQuery;
        }
        $template->filterParam = base64_encode(serialize($arrFilterParam));

        $template->moduleId = $model->id;
        $template->calendarIds = base64_encode($model->cal_calendar);
        $template->eventTypes = base64_encode($model->eventType);
        $template->limitTotal = $model->cal_limit;
        $template->limitPerRequest = $model->perPage;

        return $template->getResponse();
    }

}
