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
use Contao\ModuleModel;
use Contao\PageModel;
use Contao\StringUtil;
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

    /** @var  ModuleModel */
    protected $model;

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
        $this->model = $model;

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
        /** @var  StringUtil $stringUtilAdapter */
        $stringUtilAdapter = $this->get('contao.framework')->getAdapter(StringUtil::class);

        // Get filter params from request
        $arrKeys = ['limit', 'calendarIds', 'eventType', 'organizers', 'tourType', 'courseType', 'courseId', 'year', 'dateStart', 'searchterm', 'eventId', 'courseId', 'arrIds'];

        $ApiParam = [];
        foreach ($arrKeys as $key)
        {
            $ApiParam[$key] = $this->getApiParam($key, $request->query->get($key));
        }

        // Get picture Id
        $arrPicture = $stringUtilAdapter->deserialize($model->imgSize, true);
        $pictureId = (isset($arrPicture[2]) && is_numeric($arrPicture[2])) ? $arrPicture[2] : '0';

        $template->arrPartialOpt = [
            'pictureId' => $pictureId,
            'moduleId'  => $model->id,
            'apiParam'  => $ApiParam,
        ];

        return $template->getResponse();
    }

    private function getApiParam($strKey, $value = '')
    {
        /** @var  StringUtil $stringUtilAdapter */
        $stringUtilAdapter = $this->get('contao.framework')->getAdapter(StringUtil::class);

        switch ($strKey)
        {
            case 'organizers':

                if (!empty($value))
                {
                    // The organizers GET param can be transmitted like this: organizers=5
                    if (is_array($value))
                    {
                        $value = implode(',', $value);
                    }
                    elseif (is_numeric($value))
                    {
                        $value = $value;
                    }
                    // Or the organizers GET param can be transmitted like this: organizers=5,7,3
                    elseif (strpos($value, ','))
                    {
                        $value = $value;
                    }
                    else
                    {
                        // Or the organizers GET param can be transmitted like this: organizers[]=5&organizers[]=7&organizers[]=3
                        $value = implode(',', $stringUtilAdapter->deserialize($value, true));
                    }
                }

                break;

            case 'eventType':
                $value = $stringUtilAdapter->deserialize($this->model->eventType, true);
                $value = array_map(function ($el) {
                    return '"' . $el . '"';
                }, $value);
                $value = implode(',', $value);
                break;

            case 'limit';
                $value = $this->model->eventListLimitPerRequest;
                break;

            case 'calendarIds';
                $value = implode(',', $stringUtilAdapter->deserialize($this->model->cal_calendar, true));
                break;

            case 'tourType':
            case 'courseType':
            case 'courseId':
            case 'year':
            case 'dateStart':
            case 'searchterm':
            case 'eventId':
            case 'courseId':
            case 'arrIds':
                break;
        }

        return $value;
    }

}
