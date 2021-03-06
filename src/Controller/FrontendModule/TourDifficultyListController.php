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

use Contao\CoreBundle\Controller\FrontendModule\AbstractFrontendModuleController;
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\CoreBundle\ServiceAnnotation\FrontendModule;
use Contao\ModuleModel;
use Contao\PageModel;
use Contao\Template;
use Contao\TourDifficultyCategoryModel;
use Contao\TourDifficultyModel;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Class TourDifficultyListController.
 *
 * @FrontendModule("tour_difficulty_list", category="sac_event_tool_frontend_modules")
 */
class TourDifficultyListController extends AbstractFrontendModuleController
{
    public function __invoke(Request $request, ModuleModel $model, string $section, array $classes = null, ?PageModel $page = null): Response
    {
        return parent::__invoke($request, $model, $section, $classes, $page);
    }

    public static function getSubscribedServices(): array
    {
        $services = parent::getSubscribedServices();

        $services['contao.framework'] = ContaoFramework::class;

        return $services;
    }

    protected function getResponse(Template $template, ModuleModel $model, Request $request): ?Response
    {
        $arrDifficulty = [];
        $pid = 0;
        $options = ['order' => 'code ASC'];
        $tourDifficultyAdapter = $this->get('contao.framework')->getAdapter(TourDifficultyModel::class);
        $objDifficulty = $tourDifficultyAdapter->findAll($options);

        if (null !== $objDifficulty) {
            while ($objDifficulty->next()) {
                if ($pid !== $objDifficulty->pid) {
                    $objDifficulty->catStart = true;
                    $tourDifficultyCategoryAdapter = $this->get('contao.framework')->getAdapter(TourDifficultyCategoryModel::class);
                    $objDifficultyCategory = $tourDifficultyCategoryAdapter->findByPk($objDifficulty->pid);

                    if (null !== $objDifficultyCategory) {
                        $objDifficulty->catTitle = $objDifficultyCategory->title;
                    }
                }
                $pid = $objDifficulty->pid;
                $arrDifficulty[] = $objDifficulty->row();
            }
        }

        $template->difficulties = $arrDifficulty;

        return $template->getResponse();
    }
}
