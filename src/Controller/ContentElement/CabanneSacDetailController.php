<?php

/**
 * SAC Event Tool Web Plugin for Contao
 * Copyright (c) 2008-2020 Marko Cupic
 * @package sac-event-tool-bundle
 * @author Marko Cupic m.cupic@gmx.ch, 2017-2020
 * @link https://github.com/markocupic/sac-event-tool-bundle
 */

namespace Markocupic\SacEventToolBundle\Controller\ContentElement;

use Contao\ContentModel;
use Contao\Controller;
use Contao\CoreBundle\Controller\ContentElement\AbstractContentElementController;
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\Database;
use Contao\FilesModel;
use Contao\System;
use Contao\Template;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Contao\CoreBundle\ServiceAnnotation\ContentElement;

/**
 * Class CabanneSacDetailController
 * @package Markocupic\SacEventToolBundle\Controller\ContentElement
 * @ContentElement("cabanne_sac_detail", category="sac_event_tool_content_elements", template="ce_user_portrait")
 */
class CabanneSacDetailController extends AbstractContentElementController
{

    /**
     * @param Request $request
     * @param ContentModel $model
     * @param string $section
     * @param array|null $classes
     * @return Response
     */
    public function __invoke(Request $request, ContentModel $model, string $section, array $classes = null): Response
    {
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
     * @param ContentModel $model
     * @param Request $request
     * @return null|Response
     */
    protected function getResponse(Template $template, ContentModel $model, Request $request): ?Response
    {
        /** @var  FilesModel $filesModelAdapter */
        $filesModelAdapter = $this->get('contao.framework')->getAdapter(FilesModel::class);

        /** @var  Database $databaseAdapter */
        $databaseAdapter = $this->get('contao.framework')->getAdapter(Database::class);

        /** @var Controller $controllerAdapter */
        $controllerAdapter = $this->get('contao.framework')->getAdapter(Controller::class);

        $projectDir = System::getContainer()->getParameter('kernel.project_dir');

        // Add data to template
        $objDb = $databaseAdapter->getInstance()->prepare('SELECT * FROM tl_cabanne_sac WHERE id=?')->execute($model->cabanneSac);
        if ($objDb->numRows)
        {
            $skip = array('id', 'tstamp');
            foreach ($objDb->fetchAssoc() as $k => $v)
            {
                if (!in_array($k, $skip))
                {
                    $template->$k = $v;
                }
            }
        }
        $objFiles = $filesModelAdapter->findByUuid($objDb->singleSRC);

        if ($objFiles !== null && is_file($projectDir . '/' . $objFiles->path))
        {
            $model->singleSRC = $objFiles->path;
            $controllerAdapter->addImageToTemplate($template, $model->row(), null, 'cabanneDetail', $objFiles);
        }

        // coordsCH1903
        if (strpos($model->coordsCH1903, '/') !== false)
        {
            $arrCoord = explode('/', $model->coordsCH1903);
            $template->coordsCH1903X = trim($arrCoord[0]);
            $template->coordsCH1903Y = trim($arrCoord[1]);
        }
        return $template->getResponse();
    }

}
