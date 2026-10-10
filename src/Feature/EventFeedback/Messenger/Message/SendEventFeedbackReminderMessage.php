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

namespace Markocupic\SacEventToolBundle\Feature\EventFeedback\Messenger\Message;

use Markocupic\SacEventToolBundle\Messenger\Message\LowPriorityMessageInterface;

/**
 * Dispatched by SendFeedbackRemindersCron: one message = one feedback request
 * (tl_event_feedback_reminder.id). Handled by SendEventFeedbackReminderHandler.
 */
readonly class SendEventFeedbackReminderMessage implements LowPriorityMessageInterface
{
    public function __construct(private int $reminderId)
    {
    }

    public function getReminderId(): int
    {
        return $this->reminderId;
    }
}
