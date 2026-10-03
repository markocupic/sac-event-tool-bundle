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

namespace Markocupic\SacEventToolBundle\Feature\EventReminder;

/**
 * An accepted event registration (tl_calendar_events_member), reduced to what the reminder needs.
 */
final readonly class Participant
{
    public function __construct(
        public string $firstname,
        public string $lastname,
        public string $email,
    ) {
    }

    public function getName(): string
    {
        return trim($this->firstname.' '.$this->lastname);
    }
}
