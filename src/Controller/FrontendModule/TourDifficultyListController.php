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
use Contao\Model\Collection;
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
        $template->set('difficulties', $this->getTourDifficulties());

        return $template->getResponse();
    }

    private function getTourDifficulties(): array
    {
        $difficultyCollection = $this->fetchDifficulties();

        if (null === $difficultyCollection) {
            return [];
        }

        return $this->buildDifficultiesList($difficultyCollection);
    }

    private function fetchDifficulties(): Collection|null
    {
        $adapter = $this->getContaoAdapter(TourDifficultyModel::class);

        return $adapter->findAll(['order' => 'code ASC']);
    }

    private function buildDifficultiesList(mixed $difficultyCollection): array
    {
        $items = [];
        $previousCategoryId = null;

        while ($difficultyCollection->next()) {
            $difficulty = $difficultyCollection->current();
            $isNewCategory = $this->isNewCategory($previousCategoryId, $difficulty->pid);

            $items[] = $this->buildDifficultyItem($difficulty, $isNewCategory);

            $previousCategoryId = $difficulty->pid;
        }

        return $items;
    }

    private function isNewCategory(int|null $previousCategoryId, int $currentCategoryId): bool
    {
        return $previousCategoryId !== $currentCategoryId;
    }

    private function buildDifficultyItem(TourDifficultyModel $difficulty, bool $isNewCategory): array
    {
        $item = $difficulty->row();
        $item['isCatStart'] = $isNewCategory;

        if ($isNewCategory) {
            $item = $this->addCategoryInformation($item, $difficulty);
        }

        return $item;
    }

    private function addCategoryInformation(array $item, TourDifficultyModel $difficulty): array
    {
        $category = $this->fetchCategory($difficulty->pid);

        if (null !== $category) {
            $item['catTitle'] = $category->title;
        }

        return $item;
    }

    private function fetchCategory(int $categoryId): TourDifficultyCategoryModel|null
    {
        return $this->getContaoAdapter(TourDifficultyCategoryModel::class)->findById($categoryId);
    }
}
