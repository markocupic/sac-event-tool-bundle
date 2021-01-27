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

namespace Markocupic\SacEventToolBundle\Controller\ContentElement;

use Contao\ContentModel;
use Contao\Controller;
use Contao\CoreBundle\Controller\ContentElement\AbstractContentElementController;
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\CoreBundle\ServiceAnnotation\ContentElement;
use Contao\Database;
use Contao\FilesModel;
use Contao\PageModel;
use Contao\System;
use Contao\Template;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Class CabanneSacListController.
 *
 * @ContentElement("cabanne_sac_list", category="sac_event_tool_content_elements", template="ce_cabanne_sac_list")
 */
class CabanneSacListController extends AbstractContentElementController
{
    public function __invoke(Request $request, ContentModel $model, string $section, array $classes = null, ?PageModel $pageModel = null): Response
    {
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

        /** @var Database $databaseAdapter */
        $databaseAdapter = $this->get('contao.framework')->getAdapter(Database::class);

        /** @var Controller $controllerAdapter */
        $controllerAdapter = $this->get('contao.framework')->getAdapter(Controller::class);

        $projectDir = System::getContainer()->getParameter('kernel.project_dir');

        // Add data to template
        $objDb = $databaseAdapter->getInstance()->prepare('SELECT * FROM tl_cabanne_sac WHERE id=?')->execute($model->cabanneSac);

        if ($objDb->numRows) {
            $skip = ['id', 'tstamp', 'singleSRC'];

            foreach ($objDb->fetchAssoc() as $k => $v) {
                if (!\in_array($k, $skip, true)) {
                    $template->$k = $v;
                }
            }
        }

        $objFiles = $filesModelAdapter->findByUuid($model->singleSRC);

        if (null !== $objFiles && is_file($projectDir.'/'.$objFiles->path)) {
            $model->singleSRC = $objFiles->path;
            $controllerAdapter->addImageToTemplate($template, $model->row(), null, 'cabanne_sac_list', $objFiles);
        }

        return $template->getResponse();
    }
}
