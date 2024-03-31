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
use Markocupic\SacEventToolBundle\CalendarEventsHelper;
use Markocupic\SacEventToolBundle\Event\ChangeEventReleaseLevelEvent;
use Markocupic\SacEventToolBundle\Model\EventReleaseLevelPolicyModel;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Routing\RouterInterface;
use Twig\Environment;

#[AsEventListener]
final readonly class ChangeEventReleaseLevelListener
{
    private Adapter $configAdapter;
    private Adapter $eventReleaseLevelPolicyModelAdapter;

    public function __construct(
        private ContaoFramework $framework,
        private ScopeMatcher $scopeMatcher,
        private Security $security,
        private Environment $twig,
        private RouterInterface $router,
    ) {
        $this->framework->initialize();
        $this->configAdapter = $this->framework->getAdapter(Config::class);
        $this->eventReleaseLevelPolicyModelAdapter = $this->framework->getAdapter(EventReleaseLevelPolicyModel::class);
    }

	/**
	 * @param ChangeEventReleaseLevelEvent $event
	 * @return void
	 * @throws \Twig\Error\LoaderError
	 * @throws \Twig\Error\RuntimeError
	 * @throws \Twig\Error\SyntaxError
	 */
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
            $objEmail->replyTo('noreply@sac-pilatus.ch');

            $objEmail->subject = sprintf('Neue Freigabestufe (%s) für Event %s.', $objEventReleaseLevel->level, StringUtil::revertInputEncoding($objEvent->title));
            $objEmail->text = $this->parseEmailText($objEvent, UserModel::findByPk($user->id), $strDirection);

            $objEmail->sendTo($recipients);
        }
    }

	/**
	 * @param CalendarEventsModel $objEvent
	 * @param UserModel $objUser
	 * @param string $strDirection
	 * @return string
	 * @throws \Twig\Error\LoaderError
	 * @throws \Twig\Error\RuntimeError
	 * @throws \Twig\Error\SyntaxError
	 */
    private function parseEmailText(CalendarEventsModel $objEvent, UserModel $objUser, string $strDirection): string
    {
        $eventReleaseLevel = EventReleaseLevelPolicyModel::findByPk($objEvent->eventReleaseLevel);

        if (null === $eventReleaseLevel) {
            throw new \RuntimeException(sprintf('Could not find a event release level for event with ID %d.', $objEvent->id));
        }

        $arrEvent = array_map(static fn ($val) => StringUtil::revertInputEncoding((string) $val), $objEvent->row());

        $objInstructor = CalendarEventsHelper::getMainInstructor($objEvent);

        if (null === $objInstructor) {
            throw new \RuntimeException(sprintf('Could not find a main instructor for event with ID %d.', $objEvent->id));
        }

        return $this->twig->render(
            '@MarkocupicSacEventTool/NotifyOnEventReleaseLevelChange/notify_on_event_release_level_change.twig',
            [
                'user' => $objUser->row(),
                'instructor' => $objInstructor->row(),
                'event' => $arrEvent,
                'event_release_level' => $eventReleaseLevel->row(),
                'action' => 'up' === $strDirection ? 'hochgestuft' : 'hinuntergestuft',
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
