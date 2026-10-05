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

namespace Markocupic\SacEventToolBundle\Feature\EventCompletionReminder\Task;

use Contao\CalendarEventsModel;
use Markocupic\SacEventToolBundle\Config\EventType;
use Symfony\Component\DependencyInjection\Attribute\AsTaggedItem;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Tours: the tour report has to be filled in.
 * Not for general events: the back end offers the tour report form only for tours.
 * tl_calendar_events.filledInEventReportForm is set when the report form is submitted.
 */
#[AsTaggedItem(priority: 20)]
class TourReportTask implements PostEventTaskInterface
{
    public const NAME = 'tour_report';

    public function __construct(
        private readonly RouterInterface $router,
        private readonly TranslatorInterface $translator,
    ) {
    }

    public function getName(): string
    {
        return self::NAME;
    }

    public function supports(CalendarEventsModel $event): bool
    {
        return \in_array($event->eventType, [EventType::TOUR, EventType::LAST_MINUTE_TOUR], true);
    }

    public function isOpen(CalendarEventsModel $event): bool
    {
        return !$event->filledInEventReportForm;
    }

    public function getLabel(): string
    {
        return $this->translator->trans('MSC.instructor_post_event_task.'.self::NAME, [], 'contao_default');
    }

    public function getUrl(CalendarEventsModel $event): string
    {
        return $this->router->generate(
            'contao_backend',
            [
                'do' => 'calendar',
                'table' => 'tl_calendar_events',
                'act' => 'edit',
                'call' => 'writeTourReport',
                'id' => $event->id,
            ],
            UrlGeneratorInterface::ABSOLUTE_URL,
        );
    }
}
