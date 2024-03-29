<?php

declare(strict_types=1);

/*
 * This file is part of SAC Event Tool Bundle.
 *
 * (c) Marko Cupic 2024 <m.cupic@gmx.ch>
 * @license GPL-3.0-or-later
 * For the full copyright and license information,
 * please view the LICENSE file that was distributed with this source code.
 * @link https://github.com/markocupic/sac-event-tool-bundle
 */

namespace Markocupic\SacEventToolBundle\EventListener\Contao;

use Contao\BackendUser;
use Contao\CalendarEventsModel;
use Contao\Config;
use Contao\CoreBundle\DependencyInjection\Attribute\AsHook;
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\Email;
use Markocupic\SacEventToolBundle\ContaoScope\ContaoScope;
use Markocupic\SacEventToolBundle\Model\EventReleaseLevelPolicyModel;

#[AsHook('changeEventReleaseLevel', priority: 100)]
class ChangeEventReleaseLevelListener
{
    public function __construct(
        private readonly ContaoFramework $framework,
        private readonly ContaoScope $contaoScope,
    ) {
    }

    /**
     * @throws \Exception
     */
    public function __invoke(CalendarEventsModel $objEvent, string $strDirection): void
    {
        if ($this->contaoScope->isBackend()) {
            $eventReleaseLevelPolicyModelAdapter = $this->framework->getAdapter(EventReleaseLevelPolicyModel::class);
            $backendUserAdapter = $this->framework->getAdapter(BackendUser::class);
            $configAdapter = $this->framework->getAdapter(Config::class);

            $objCalendar = $objEvent->getRelated('pid');

            if (null !== $objCalendar) {
                if ('' !== $objCalendar->adviceOnEventReleaseLevelChange) {
                    $objEventReleaseLevel = $eventReleaseLevelPolicyModelAdapter->findByPk($objEvent->eventReleaseLevel);

                    if (null !== $objEventReleaseLevel) {
                        $objUser = $backendUserAdapter->getInstance();
                        $objEmail = new Email();
                        $objEmail->from = $configAdapter->get('adminEmail');
                        $objEmail->fromName = 'Administrator SAC Pilatus';
                        $objEmail->subject = sprintf('Neue Freigabestufe (%s) für Event %s.', $objEventReleaseLevel->level, $objEvent->title);

                        if ('down' === $strDirection) {
                            $objEmail->text = sprintf("Hallo \nEvent %s wurde soeben durch %s auf Freigabestufe %s (%s) hinuntergestuft. \n\n\nDies ist eine automatische Nachricht mit Ursprung in: %s\n Liebe Grüsse \n\n Administrator SAC Pilatus", $objEvent->title, $objUser->name, $objEventReleaseLevel->level, $objEventReleaseLevel->title, __METHOD__.' LINE: '.__LINE__);
                        } else {
                            $objEmail->text = sprintf("Hallo \nEvent %s wurde soeben durch %s auf Freigabestufe %s (%s) hochgestuft. \n\n\nDies ist eine automatische Nachricht mit Ursprung in: %s\n Liebe Grüsse \n\n Administrator SAC Pilatus", $objEvent->title, $objUser->name, $objEventReleaseLevel->level, $objEventReleaseLevel->title, __METHOD__.' LINE: '.__LINE__);
                        }
                        $objEmail->sendTo($objCalendar->adviceOnEventReleaseLevelChange);
                    }
                }
            }
        }
    }
}
