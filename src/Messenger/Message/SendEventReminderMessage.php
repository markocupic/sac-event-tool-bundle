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

namespace Markocupic\SacEventToolBundle\Messenger\Message;

use Contao\CoreBundle\Messenger\Message\LowPriorityMessageInterface;

/**
 * Dispatched by EventReminderCron, one message per event.
 * Handled by SendEventReminderHandler; a failed delivery throws
 * and is retried by the Symfony Messenger retry strategy.
 */
readonly class SendEventReminderMessage implements LowPriorityMessageInterface
{
    public function __construct(private int $eventId)
    {
    }

    public function getEventId(): int
    {
        return $this->eventId;
    }
}
