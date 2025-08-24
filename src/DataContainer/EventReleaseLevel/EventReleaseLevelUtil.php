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

namespace Markocupic\SacEventToolBundle\DataContainer\EventReleaseLevel;

use Contao\CalendarEventsModel;
use Contao\Config;
use Contao\CoreBundle\Framework\Adapter;
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\Date;
use Contao\Message;
use Markocupic\SacEventToolBundle\Event\PublishEventEvent;
use Markocupic\SacEventToolBundle\Model\EventReleaseLevelPolicyModel;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Contracts\Translation\TranslatorInterface;

class EventReleaseLevelUtil
{
    private Adapter $config;

    private Adapter $date;

    private Adapter $message;

    public function __construct(
        private readonly ContaoFramework $framework,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly RequestStack $requestStack,
        private readonly Security $security,
        private readonly TranslatorInterface $translator,
    ) {
        $this->config = $this->framework->getAdapter(Config::class);
        $this->message = $this->framework->getAdapter(Message::class);
        $this->date = $this->framework->getAdapter(Date::class);
    }

    public function hasValidEventReleaseLevel(CalendarEventsModel $objEvent, int $eventReleaseLevelId): bool
    {
        $maxEventReleaseModel = EventReleaseLevelPolicyModel::findMaxLevelByEventId($objEvent->id);

        if (0 === $eventReleaseLevelId && null === $maxEventReleaseModel) {
            return true;
        }

        if (0 === $eventReleaseLevelId && null !== $maxEventReleaseModel) {
            return false;
        }

        $eventReleaseModel = EventReleaseLevelPolicyModel::findById($eventReleaseLevelId);

        return $maxEventReleaseModel->pid === $eventReleaseModel->pid;
    }

    public function publishOrUnpublishEventDependingOnEventReleaseLevel(CalendarEventsModel $objEvent, int $targetEventReleaseLevelId): int
    {
        $calendar = $objEvent->getRelated('pid');

        $except = \sprintf(
            'Could not find the parent calendar for event "%s" (ID: %d).',
            $objEvent->title,
            $objEvent->id,
        );

        if (null === $calendar) {
            throw new \Exception($except);
        }

        $targetEventReleaseModel = EventReleaseLevelPolicyModel::findById($targetEventReleaseLevelId);
        $minEventReleaseModel = EventReleaseLevelPolicyModel::findMinLevelByEventId($objEvent->id);

        if (!$this->hasValidEventReleaseLevel($objEvent, $targetEventReleaseLevelId)) {
            if (null === $minEventReleaseModel) {
                // If we have no event release level policy package assigned to the event, we set
                // the event release level to 0 (undefined).
                $objEvent->eventReleaseLevel = 0;
            } else {
                // Set the lowest possible event release level.
                $objEvent->eventReleaseLevel = $minEventReleaseModel->id;
            }

            // Un-publish event because the event release level is invalid or 0.
            $objEvent->published = 0;

            if ($objEvent->isModified()) {
                $objEvent->tstamp = time();
                $objEvent->save();
            }

            $msg = $this->translator->trans(
                'ERR.selectedEventReleaseLevelIsNotCompatibleWithTheEventType',
                [
                    $objEvent->title,
                    $objEvent->id,
                    null !== $targetEventReleaseModel ? 'FS '.$targetEventReleaseModel->level : 'undefined',
                ],
                'contao_default',
            );

            $this->message->addError($msg);

            return $objEvent->eventReleaseLevel;
        }

        // Accept 0, if we have no event release level policy package assigned to the event.
        if (0 === $targetEventReleaseLevelId) {
            $objEvent->published = 0;

            if ($objEvent->isModified()) {
                $objEvent->tstamp = time();
                $objEvent->save();
            }

            return $targetEventReleaseLevelId;
        }

        $maxEventReleaseModel = EventReleaseLevelPolicyModel::findMaxLevelByEventId($objEvent->id);

        if (null === $maxEventReleaseModel) {
            $except = \sprintf(
                'Could not determine the highest event release level for the event "%s" (ID: %d).',
                $objEvent->title,
                $objEvent->id,
            );

            throw new \RuntimeException($except);
        }

        if ($maxEventReleaseModel->id === $targetEventReleaseLevelId) {
            // Do not allow non-admins to shift the event release level to the top level.
            if (
                !$this->security->isGranted('ROLE_ADMIN')
                && $calendar->enableMaxEventReleaseLevelProtection
                && time() < $calendar->maxEventReleaseLevelTimeLimit
            ) {
                $objEvent->published = 0;

                if ($objEvent->isModified()) {
                    $objEvent->save();
                }

                $msg = $this->translator->trans(
                    'ERR.pushingToHighestEventReleaseLevelNotAllowedBeforeDate',
                    [
                        $objEvent->title,
                        $objEvent->id,
                        $this->date->parse($this->config->get('datimFormat'), $calendar->maxEventReleaseLevelTimeLimit),
                        $targetEventReleaseModel->level,
                    ],
                    'contao_default',
                );

                $this->message->addError($msg);

                return $objEvent->eventReleaseLevel;
            }

            if (!$objEvent->published) {
                $objEvent->published = 1;

                $msg = $this->translator->trans('MSC.publishedEvent', [$objEvent->id], 'contao_default');
                $this->message->addInfo($msg);

                // Dispatch PublishEventEvent
                $event = new PublishEventEvent($this->requestStack->getCurrentRequest(), $objEvent);
                $this->eventDispatcher->dispatch($event);
            }
        } else {
            if ($objEvent->published) {
                $objEvent->published = 0;

                $msg = $this->translator->trans('MSC.unpublishedEvent', [$objEvent->id], 'contao_default');
                $this->message->addInfo($msg);
            }
        }

        if ($objEvent->isModified()) {
            $objEvent->tstamp = time();
            $objEvent->save();
        }

        return $targetEventReleaseLevelId;
    }
}
