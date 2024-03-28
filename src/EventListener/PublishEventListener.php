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
use Markocupic\SacEventToolBundle\Event\PublishEventEvent;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

#[AsEventListener]
final readonly class PublishEventListener
{
    private Adapter $configAdapter;

    public function __construct(
        private ContaoFramework $framework,
        private ScopeMatcher $scopeMatcher,
        private Security $security,
    ) {
        $this->framework->initialize();
        $this->configAdapter = $this->framework->getAdapter(Config::class);
    }

    public function __invoke(PublishEventEvent $event): void
    {
        $request = $event->getRequest();
        $objCalendarEvent = $event->getEvent();

        if ($this->scopeMatcher->isBackendRequest($request)) {
            $objCalendar = $objCalendarEvent->getRelated('pid');

            if (null !== $objCalendar) {
                $recipients = $objCalendar->notifyOnEventReleaseLevelChange;

                if (!empty($recipients)) {
                    $user = $this->security->getUser();

                    if (!$user instanceof BackendUser) {
                        return;
                    }

                    $objEmail = new Email();
                    $objEmail->from = $this->configAdapter->get('adminEmail');
                    $objEmail->fromName = 'Administrator SAC Pilatus';
                    $objEmail->subject = sprintf('Event %s wurde veröffentlicht', $objCalendarEvent->title);

                    $strText = "Hallo \nEvent %s wurde soeben durch %s veröffentlicht. \n\n";
                    $strText .= "Dies ist eine automatische Nachricht mit Ursprung in: %s\n Liebe Grüsse\n\nAdministrator SAC Pilatus";

                    $objEmail->text = sprintf($strText, $objCalendarEvent->title, $user->name, __METHOD__.' LINE: '.__LINE__);

                    $objEmail->sendTo($objCalendar->notifyOnEventReleaseLevelChange);
                }
            }
        }
    }
}
