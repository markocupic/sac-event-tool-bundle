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

namespace Markocupic\SacEventToolBundle\Feature\EventRegistrationCleanup\Cron;

use Contao\CoreBundle\DependencyInjection\Attribute\AsCronJob;
use Markocupic\SacEventToolBundle\Feature\EventRegistrationCleanup\EventRegistrationCleanup;

/**
 * Daily at 06:30, after the member sync (05:01) and the event registration sync (04:40).
 *
 * See docs/features/event-registration-cleanup.md
 */
#[AsCronJob('30 6 * * *')]
readonly class EventRegistrationCleanupCron
{
    public function __construct(private EventRegistrationCleanup $eventRegistrationCleanup)
    {
    }

    public function __invoke(): void
    {
        $this->eventRegistrationCleanup->run();
    }
}
