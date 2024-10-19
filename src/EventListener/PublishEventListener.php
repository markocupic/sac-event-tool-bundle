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
use Contao\CalendarEventsModel;
use Contao\Config;
use Contao\CoreBundle\Framework\Adapter;
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\CoreBundle\Routing\ScopeMatcher;
use Contao\Email;
use Contao\StringUtil;
use Contao\UserModel;
use Markocupic\SacEventToolBundle\Event\PublishEventEvent;
use Markocupic\SacEventToolBundle\Model\EventReleaseLevelPolicyModel;
use Markocupic\SacEventToolBundle\Util\CalendarEventsUtil;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Routing\RouterInterface;
use Twig\Environment;
use Twig\Error\LoaderError;
use Twig\Error\RuntimeError;
use Twig\Error\SyntaxError;

#[AsEventListener]
final readonly class PublishEventListener
{
    private Adapter $configAdapter;

    public function __construct(
        private ContaoFramework $framework,
        private ScopeMatcher $scopeMatcher,
        private Security $security,
        private Environment $twig,
        private RouterInterface $router,
    ) {
        $this->framework->initialize();
        $this->configAdapter = $this->framework->getAdapter(Config::class);
    }

    /**
     * @throws LoaderError
     * @throws RuntimeError
     * @throws SyntaxError
     */
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
                    $objEmail->replyTo('noreply@sac-pilatus.ch');

                    $objEmail->subject = sprintf('Event %s wurde veröffentlicht', StringUtil::revertInputEncoding($objCalendarEvent->title));
                    $objEmail->text = $this->parseEmailText($objCalendarEvent, UserModel::findByPk($user->id));

                    $objEmail->sendTo($objCalendar->notifyOnEventReleaseLevelChange);
                }
            }
        }
    }

    /**
     * @throws LoaderError
     * @throws RuntimeError
     * @throws SyntaxError
     */
    private function parseEmailText(CalendarEventsModel $objEvent, UserModel $objUser): string
    {
        $eventReleaseLevel = EventReleaseLevelPolicyModel::findByPk($objEvent->eventReleaseLevel);

        if (null === $eventReleaseLevel) {
            throw new \RuntimeException(sprintf('Could not find a event release level for event with ID %d.', $objEvent->id));
        }

        $arrEvent = array_map(static fn ($val) => StringUtil::revertInputEncoding((string) $val), $objEvent->row());

        $objInstructor = CalendarEventsUtil::getMainInstructor($objEvent);

        if (null === $objInstructor) {
            throw new \RuntimeException(sprintf('Could not find a main instructor for event with ID %d.', $objEvent->id));
        }

        return $this->twig->render(
            '@MarkocupicSacEventTool/NotifyOnEventReleaseLevelChange/notify_on_event_publish.twig',
            [
                'user' => $objUser->row(),
                'instructor' => $objInstructor->row(),
                'event' => $arrEvent,
                'event_release_level' => $eventReleaseLevel->row(),
                'event_link' => $this->router->generate(
                    'contao_backend',
                    [
                        'do' => 'calendar',
                        'table' => CalendarEventsModel::getTable(),
                        'id' => $objEvent->id,
                        'act' => 'edit',
                    ],
                    UrlGeneratorInterface::ABSOLUTE_URL,
                ),
            ]
        );
    }
}
