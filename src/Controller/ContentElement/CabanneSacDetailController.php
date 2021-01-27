<?php

declare(strict_types=1);

/**
 * SAC Event Tool Web Plugin for Contao
 * Copyright (c) 2008-2020 Marko Cupic
 * @package sac-event-tool-bundle
 * @author Marko Cupic m.cupic@gmx.ch, 2017-2020
 * @link https://github.com/markocupic/sac-event-tool-bundle
 */

namespace Markocupic\SacEventToolBundle\Controller\ContentElement;

use Contao\CabanneSacModel;
use Contao\ContentModel;
use Contao\Controller;
use Contao\CoreBundle\Controller\ContentElement\AbstractContentElementController;
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\FilesModel;
use Contao\PageModel;
use Contao\System;
use Contao\Template;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Contao\CoreBundle\ServiceAnnotation\ContentElement;

/**
 * Class CabanneSacDetailController
 * @package Markocupic\SacEventToolBundle\Controller\ContentElement
 * @ContentElement("cabanne_sac_detail", category="sac_event_tool_content_elements", template="ce_cabanne_sac_detail")
 */
class CabanneSacDetailController extends AbstractContentElementController
{
    /** @var  CabanneSacModel */
    protected $objCabanne;

    /**
     * @param Request $request
     * @param ContentModel $model
     * @param string $section
     * @param array|null $classes
     * @param PageModel|null $pageModel
     * @return Response
     */
    public function __invoke(Request $request, ContentModel $model, string $section, array $classes = null, ?PageModel $pageModel = null): Response
    {
        /** @var CabanneSacModel $cabanneSacModelAdapter */
        $cabanneSacModelAdapter = $this->get('contao.framework')->getAdapter(CabanneSacModel::class);

        // Add data to template
        if (null === ($this->objCabanne = $cabanneSacModelAdapter->findByPk($model->cabanneSac)))
        {
            return new Response('', Response::HTTP_NO_CONTENT);
        }

        return parent::__invoke($request, $model, $section, $classes, $pageModel);
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
     * @param ContentModel $model
     * @param Request $request
     * @return null|Response
     */
    protected function getResponse(Template $template, ContentModel $model, Request $request): ?Response
    {
        /** @var  FilesModel $filesModelAdapter */
        $filesModelAdapter = $this->get('contao.framework')->getAdapter(FilesModel::class);

        /** @var Controller $controllerAdapter */
        $controllerAdapter = $this->get('contao.framework')->getAdapter(Controller::class);

        $projectDir = System::getContainer()->getParameter('kernel.project_dir');

        // Add data to template
        $skip = ['id', 'tstamp'];
        foreach ($this->objCabanne->row() as $k => $v)
        {
            if (!in_array($k, $skip,true))
            {
                $template->$k = $v;
            }
        }
        $objFiles = $filesModelAdapter->findByUuid($this->objCabanne->singleSRC);

        if ($objFiles !== null && is_file($projectDir . '/' . $objFiles->path))
        {
            $model->singleSRC = $objFiles->path;
            $controllerAdapter->addImageToTemplate($template, $model->row(), null, 'cabanneDetail', $objFiles);
        }

        // coordsCH1903
        if (!empty($this->objCabanne->coordsCH1903))
        {
            if (strpos($this->objCabanne->coordsCH1903, '/') !== false)
            {
                $template->hasCoords = true;
                $arrCoord = explode('/', $this->objCabanne->coordsCH1903);
                $template->coordsCH1903X = trim($arrCoord[0]);
                $template->coordsCH1903Y = trim($arrCoord[1]);
            }
        }

        return $template->getResponse();
    }

}
