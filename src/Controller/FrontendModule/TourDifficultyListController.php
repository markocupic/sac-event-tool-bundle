<?php

declare(strict_types=1);

/*
 * This file is part of SAC Event Tool Bundle.
 *
 * (c) Marko Cupic <m.cupic@gmx.ch>
 * @license GPL-3.0-or-later
 * For the full copyright and license information,
 * please view the LICENSE file that was distributed with this source code.
 * @link https://github.com/markocupic/sac-event-tool-bundle
 */

namespace Markocupic\SacEventToolBundle\Controller\FrontendModule;

use Contao\CoreBundle\Controller\FrontendModule\AbstractFrontendModuleController;
use Contao\CoreBundle\DependencyInjection\Attribute\AsFrontendModule;
use Contao\CoreBundle\Twig\FragmentTemplate;
use Contao\ModuleModel;
use Markocupic\SacEventToolBundle\Model\TourDifficultyCategoryModel;
use Markocupic\SacEventToolBundle\Model\TourDifficultyModel;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

#[AsFrontendModule(TourDifficultyListController::TYPE, category: 'sac_event_tool_frontend_modules')]
class TourDifficultyListController extends AbstractFrontendModuleController
{
    public const string TYPE = 'tour_difficulty_list';

    protected function getResponse(FragmentTemplate $template, ModuleModel $model, Request $request): Response
    {
        $template->set('difficulties', $this->getTourDifficulties(['order' => 'code ASC']));

        return $template->getResponse();
    }

    private function getTourDifficulties(array $options = []): array
    {
        $items = [];
        $currentPid = 0;

        $adapter = $this->getContaoAdapter(TourDifficultyModel::class);
        $objDifficulty = $adapter->findAll($options);

        if (null === $objDifficulty) {
            return $items;
        }

        while ($objDifficulty->next()) {
            $item = $objDifficulty->row();
            $item['isCatStart'] = false;
            if ($currentPid !== $objDifficulty->pid) {
                $item['isCatStart'] = true;

                $objDifficultyCategory = $this->getCategory($objDifficulty->current());

                if (null !== $objDifficultyCategory) {
                    $item['catTitle'] = $objDifficultyCategory->title;
                }
            }

            $currentPid = $objDifficulty->pid;
            $items[] = $item;
        }

        return $items;
    }

    private function getCategory(TourDifficultyModel $objDifficulty): TourDifficultyCategoryModel|null
    {
        return $this->getContaoAdapter(TourDifficultyCategoryModel::class)->findById($objDifficulty->pid);
    }
}
