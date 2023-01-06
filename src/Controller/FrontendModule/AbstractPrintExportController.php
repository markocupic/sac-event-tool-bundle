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

use Contao\CalendarEventsModel;
use Contao\CoreBundle\Controller\FrontendModule\AbstractFrontendModuleController;
use Contao\CoreBundle\Framework\ContaoFramework;
use Markocupic\SacEventToolBundle\Model\EventReleaseLevelPolicyModel;

/**
 * This is the base class for:
 * JahresprogrammExportController and PilatusExport2021Controller.
 */
abstract class AbstractPrintExportController extends AbstractFrontendModuleController
{
    private ContaoFramework $framework;

    public function __construct(ContaoFramework $framework)
    {
        $this->framework = $framework;
    }

    public function hasValidReleaseLevel(CalendarEventsModel $objEvent, int $minLevel = null): bool
    {
        /** @var EventReleaseLevelPolicyModel $eventReleaseLevelPolicyModelAdapter */
        $eventReleaseLevelPolicyModelAdapter = $this->framework->getAdapter(EventReleaseLevelPolicyModel::class);

        if ($objEvent->published) {
            return true;
        }

        if (null !== $objEvent) {
            if ($objEvent->eventReleaseLevel > 0) {
                $objEventReleaseLevel = $eventReleaseLevelPolicyModelAdapter->findByPk($objEvent->eventReleaseLevel);

                if (null !== $objEventReleaseLevel) {
                    if (null === $minLevel) {
                        /** @var EventReleaseLevelPolicyModel $nextLevelModel */
                        $nextLevelModel = $eventReleaseLevelPolicyModelAdapter->findNextLevel($objEvent->eventReleaseLevel);
                        $lastLevelModel = $eventReleaseLevelPolicyModelAdapter->findLastLevelByEventId($objEvent->id);

                        if (null !== $nextLevelModel && null !== $lastLevelModel) {
                            if ($nextLevelModel->id === $lastLevelModel->id) {
                                return true;
                            }
                        }
                    } else {
                        if ($objEventReleaseLevel->level >= $minLevel) {
                            return true;
                        }
                    }
                }
            }
        }

        return false;
    }

    /**
     * Replace chars.
     */
    public function searchAndReplace(string $strValue = ''): string
    {
        if (!empty($strValue)) {
            $arrReplace = [
                // Replace (at) with @
                '(at)' => '@',
                '&#40;at&#41;' => '@',
            ];

            foreach ($arrReplace as $k => $v) {
                $strValue = str_replace($k, $v, $strValue);
            }
        }

        return $strValue;
    }
}
