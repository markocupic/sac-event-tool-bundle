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

namespace Markocupic\SacEventToolBundle\Controller\ContentElement;

use Contao\CabanneSacModel;
use Contao\ContentModel;
use Contao\Controller;
use Contao\CoreBundle\Controller\ContentElement\AbstractContentElementController;
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\CoreBundle\ServiceAnnotation\ContentElement;
use Contao\FilesModel;
use Contao\PageModel;
use Contao\System;
use Contao\Template;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Class CabanneSacDetailController.
 *
 * @ContentElement("cabanne_sac_detail", category="sac_event_tool_content_elements", template="ce_cabanne_sac_detail")
 */
class CabanneSacDetailController extends AbstractContentElementController
{
    /**
     * @var CabanneSacModel
     */
    protected $objCabanne;

    public function __invoke(Request $request, ContentModel $model, string $section, array $classes = null, PageModel $pageModel = null): Response
    {
        /** @var CabanneSacModel $cabanneSacModelAdapter */
        $cabanneSacModelAdapter = $this->get('contao.framework')->getAdapter(CabanneSacModel::class);

        // Add data to template
        if (null === ($this->objCabanne = $cabanneSacModelAdapter->findByPk($model->cabanneSac))) {
            return new Response('', Response::HTTP_NO_CONTENT);
        }

        return parent::__invoke($request, $model, $section, $classes, $pageModel);
    }

    public static function getSubscribedServices(): array
    {
        $services = parent::getSubscribedServices();
        $services['contao.framework'] = ContaoFramework::class;

        return $services;
    }

    protected function getResponse(Template $template, ContentModel $model, Request $request): ?Response
    {
        /** @var FilesModel $filesModelAdapter */
        $filesModelAdapter = $this->get('contao.framework')->getAdapter(FilesModel::class);

        /** @var Controller $controllerAdapter */
        $controllerAdapter = $this->get('contao.framework')->getAdapter(Controller::class);

        $projectDir = System::getContainer()->getParameter('kernel.project_dir');

        // Add data to template
        $skip = ['id', 'tstamp'];

        foreach ($this->objCabanne->row() as $k => $v) {
            if (!\in_array($k, $skip, true)) {
                $template->$k = $v;
            }
        }
        $objFiles = $filesModelAdapter->findByUuid($this->objCabanne->singleSRC);

        if (null !== $objFiles && is_file($projectDir.'/'.$objFiles->path)) {
            $model->singleSRC = $objFiles->path;
            $controllerAdapter->addImageToTemplate($template, $model->row(), null, 'cabanneDetail', $objFiles);
        }

        // coordsCH1903
        if (!empty($this->objCabanne->coordsCH1903)) {
            if (false !== strpos($this->objCabanne->coordsCH1903, '/')) {
                $template->hasCoords = true;
                $arrCoord = explode('/', $this->objCabanne->coordsCH1903);
                $template->coordsCH1903X = trim($arrCoord[0]);
                $template->coordsCH1903Y = trim($arrCoord[1]);
            }
        }

        return $template->getResponse();
    }
}
