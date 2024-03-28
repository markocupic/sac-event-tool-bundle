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

namespace Markocupic\SacEventToolBundle\EventListener;

use Contao\BackendUser;
use Contao\Config;
use Contao\CoreBundle\Framework\Adapter;
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\CoreBundle\Routing\ScopeMatcher;
use Contao\Email;
use Markocupic\SacEventToolBundle\Event\ChangeEventReleaseLevelEvent;
use Markocupic\SacEventToolBundle\Model\EventReleaseLevelPolicyModel;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

#[AsEventListener]
final readonly class ChangeEventReleaseLevelListener
{
    private Adapter $configAdapter;
    private Adapter $eventReleaseLevelPolicyModelAdapter;

    public function __construct(
        private ContaoFramework $framework,
        private ScopeMatcher $scopeMatcher,
        private Security $security,
    ) {
        $this->framework->initialize();
        $this->configAdapter = $this->framework->getAdapter(Config::class);
        $this->eventReleaseLevelPolicyModelAdapter = $this->framework->getAdapter(EventReleaseLevelPolicyModel::class);
    }

    public function __invoke(ChangeEventReleaseLevelEvent $event): void
    {
        $strDirection = $event->getDirection();
        $objEvent = $event->getEvent();
        $request = $event->getRequest();

        if ($this->scopeMatcher->isBackendRequest($request)) {
            $user = $this->security->getUser();

            if (!$user instanceof BackendUser) {
                return;
            }

            $objCalendar = $objEvent->getRelated('pid');

            if (null === $objCalendar) {
                return;
            }

            $recipients = $objCalendar->notifyOnEventReleaseLevelChange;

            if (empty($recipients)) {
                return;
            }

            $objEventReleaseLevel = $this->eventReleaseLevelPolicyModelAdapter->findByPk($objEvent->eventReleaseLevel);

            if (null === $objEventReleaseLevel) {
                return;
            }

            $objEmail = new Email();
            $objEmail->from = $this->configAdapter->get('adminEmail');
            $objEmail->fromName = 'Administrator SAC Pilatus';
            $objEmail->subject = sprintf('Neue Freigabestufe (%s) für Event %s.', $objEventReleaseLevel->level, $objEvent->title);

            if ('down' === $strDirection) {
                $objEmail->text = sprintf("Hallo \nEvent %s wurde soeben durch %s auf Freigabestufe %s (%s) hinuntergestuft. \n\n\nDies ist eine automatische Nachricht mit Ursprung in: %s\n Liebe Grüsse \n\n Administrator SAC Pilatus", $objEvent->title, $user->name, $objEventReleaseLevel->level, $objEventReleaseLevel->title, __METHOD__.' LINE: '.__LINE__);
            } else {
                $objEmail->text = sprintf("Hallo \nEvent %s wurde soeben durch %s auf Freigabestufe %s (%s) hochgestuft. \n\n\nDies ist eine automatische Nachricht mit Ursprung in: %s\n Liebe Grüsse \n\n Administrator SAC Pilatus", $objEvent->title, $user->name, $objEventReleaseLevel->level, $objEventReleaseLevel->title, __METHOD__.' LINE: '.__LINE__);
            }

            $objEmail->sendTo($recipients);
        }
    }
}
