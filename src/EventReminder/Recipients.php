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

namespace Markocupic\SacEventToolBundle\EventReminder;

/**
 * Recipients of one event reminder email. All addresses are lowercased, trimmed and unique
 * across the three fields, so nobody receives the email twice.
 */
final readonly class Recipients
{
    /**
     * @param list<string> $cc
     * @param list<string> $bcc
     */
    public function __construct(
        public string $to,
        public array $cc,
        public array $bcc,
    ) {
    }

    public function getCcAsString(): string
    {
        return implode(',', $this->cc);
    }

    public function getBccAsString(): string
    {
        return implode(',', $this->bcc);
    }
}
