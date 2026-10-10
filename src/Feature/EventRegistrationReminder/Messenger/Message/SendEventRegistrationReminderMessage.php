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

namespace Markocupic\SacEventToolBundle\Feature\EventRegistrationReminder\Messenger\Message;

use Markocupic\SacEventToolBundle\Messenger\Message\LowPriorityMessageInterface;

/**
 * Dispatched by EventRegistrationReminderCron:
 * one message = one reminder to one user (tl_user) for one calendar.
 * Handled by SendEventRegistrationReminderHandler.
 */
readonly class SendEventRegistrationReminderMessage implements LowPriorityMessageInterface
{
    public function __construct(
        private int $userId,
        private int $calendarId,
    ) {
    }

    public function getUserId(): int
    {
        return $this->userId;
    }

    public function getCalendarId(): int
    {
        return $this->calendarId;
    }
}
