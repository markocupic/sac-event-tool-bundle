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

namespace Markocupic\SacEventToolBundle\Feature\InstructorPostEventTaskReminder\Task;

use Contao\CalendarEventsModel;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

/**
 * A task an instructor (or registration coordinator) has to complete after an event.
 *
 * Every task is a building block: add a new class implementing this interface
 * and it is picked up automatically by the TaskEvaluator. No other code has to be touched.
 *
 * The order in the notification is defined via #[AsTaggedItem(priority: ...)]
 * on the implementing class (higher priority first).
 *
 * Filters that apply to all tasks (published, canceled/rescheduled, completion period, lookback)
 * are NOT the concern of a task. They are applied centrally by the OpenTaskProvider.
 */
#[AutoconfigureTag('sacevt.instructor_post_event_task')]
interface PostEventTaskInterface
{
    public const TAG = 'sacevt.instructor_post_event_task';

    /**
     * Stable key, e.g. "tour_report".
     */
    public function getName(): string;

    /**
     * Does this task apply to the event at all (e.g. depending on the event type)?
     */
    public function supports(CalendarEventsModel $event): bool;

    /**
     * Is the task still open?
     * Only called if supports() returned true.
     */
    public function isOpen(CalendarEventsModel $event): bool;

    /**
     * Text shown in the to-do list, e.g. "Tourenbericht ausfüllen".
     */
    public function getLabel(): string;

    /**
     * Absolute link to the backend page where the task can be completed.
     */
    public function getUrl(CalendarEventsModel $event): string;
}
