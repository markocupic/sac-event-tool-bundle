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

namespace Markocupic\SacEventToolBundle\Feature\AutoPublishEvents;

use Markocupic\SacEventToolBundle\Util\ReleaseLevel;

/**
 * An event that is promoted from the second-highest to the highest release level and published.
 */
final readonly class Candidate
{
    public function __construct(
        public int $eventId,
        public int $calendarId,
        public string $title,
        public ReleaseLevel $currentLevel,
        public ReleaseLevel $targetLevel,
    ) {
    }
}
