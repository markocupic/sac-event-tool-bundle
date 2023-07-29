<?php

declare(strict_types=1);

/*
 * This file is part of SAC Event Tool Bundle.
 *
 * (c) Marko Cupic 2023 <m.cupic@gmx.ch>
 * @license GPL-3.0-or-later
 * For the full copyright and license information,
 * please view the LICENSE file that was distributed with this source code.
 * @link https://github.com/markocupic/sac-event-tool-bundle
 */

namespace Markocupic\SacEventToolBundle\Controller\FrontendModule;

use Contao\CoreBundle\Controller\FrontendModule\AbstractFrontendModuleController;
use Contao\CoreBundle\DependencyInjection\Attribute\AsFrontendModule;
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\ModuleModel;
use Contao\Template;
use Markocupic\SacEventToolBundle\Model\TourDifficultyCategoryModel;
use Markocupic\SacEventToolBundle\Model\TourDifficultyModel;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

#[AsFrontendModule(TourDifficultyListController::TYPE, category:'sac_event_tool_frontend_modules', template:'mod_tour_difficulty_list')]
class TourDifficultyListController extends AbstractFrontendModuleController
{
    public const TYPE = 'tour_difficulty_list';

    public function __construct(
        private readonly ContaoFramework $framework,
    ) {
    }

    protected function getResponse(Template $template, ModuleModel $model, Request $request): Response
    {
        $arrDifficulty = [];
        $pid = 0;
        $tourDifficultyAdapter = $this->framework->getAdapter(TourDifficultyModel::class);
        $objDifficulty = $tourDifficultyAdapter->findAll(['order' => 'code ASC']);

        if (null !== $objDifficulty) {
            while ($objDifficulty->next()) {
                if ($pid !== $objDifficulty->pid) {
                    $objDifficulty->isCatStart = true;
                    $tourDifficultyCategoryAdapter = $this->framework->getAdapter(TourDifficultyCategoryModel::class);
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
