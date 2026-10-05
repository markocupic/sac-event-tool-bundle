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

namespace Markocupic\SacEventToolBundle\Feature\EventFeedback\Cron;

use Contao\CoreBundle\DependencyInjection\Attribute\AsCronJob;
use Contao\CoreBundle\Framework\ContaoFramework;
use Markocupic\SacEventToolBundle\Feature\EventFeedback\EventFeedbackHelper;
use Markocupic\SacEventToolBundle\Feature\EventFeedback\FeedbackReminder;
use Markocupic\SacEventToolBundle\Feature\EventFeedback\Messenger\Message\SendEventFeedbackReminderMessage;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Stopwatch\Stopwatch;

/**
 * Every minute: removes obsolete feedback requests, claims the due ones and dispatches
 * one SendEventFeedbackReminderMessage per request.
 *
 * A request is due when its sending date plus the delay of the calendar configuration
 * ("send_reminder_execution_delay") has been reached. The delay gives the instructor time
 * to undo a participation confirmation before the first e-mail goes out.
 *
 * See docs/features/event-feedback.md
 */
#[AsCronJob('* * * * *')]
readonly class SendFeedbackRemindersCron
{
    private const string STOP_WATCH_EVENT = 'event_feedback_reminder_cron';

    public function __construct(
        private ContaoFramework $framework,
        private EventFeedbackHelper $eventFeedbackHelper,
        private FeedbackReminder $feedbackReminder,
        private MessageBusInterface $messageBus,
        private LoggerInterface|null $contaoCronLogger,
    ) {
    }

    public function __invoke(): void
    {
        $stopwatchEvent = (new Stopwatch())->start(self::STOP_WATCH_EVENT);

        $this->framework->initialize();

        $now = time();
        $this->feedbackReminder->deleteObsolete($now);

        $count = 0;

        foreach ($this->feedbackReminder->findPending($now) as $row) {
            $config = $this->eventFeedbackHelper->getConfigurationByName((string) $row['configuration']);
            $delay = (int) ($config['send_reminder_execution_delay'] ?? 0);

            if ((int) $row['executionDate'] > $now - $delay) {
                continue;
            }

            // Claim first, so the next cron run does not dispatch it again
            if (!$this->feedbackReminder->claim((int) $row['id'], $now)) {
                continue;
            }

            ++$count;
            $this->messageBus->dispatch(new SendEventFeedbackReminderMessage((int) $row['id']));
        }

        if ($count < 1) {
            return; // The cron runs every minute: log only runs that dispatched something
        }

        $this->contaoCronLogger?->info(\sprintf(
            'Event feedback reminder cron: dispatched %d message(s) in %s s.',
            $count,
            round($stopwatchEvent->stop()->getDuration() / 1000, 2),
        ));
    }
}
