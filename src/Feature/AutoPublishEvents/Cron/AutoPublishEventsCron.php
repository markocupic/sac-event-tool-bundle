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

namespace Markocupic\SacEventToolBundle\Feature\AutoPublishEvents\Cron;

use Contao\CoreBundle\DependencyInjection\Attribute\AsCronJob;
use Contao\CoreBundle\Framework\ContaoFramework;
use Markocupic\SacEventToolBundle\Feature\AutoPublishEvents\CalendarRunner;
use Psr\Log\LoggerInterface;

/**
 * For every calendar with a reached due date (date and time) that has not run for this due date yet:
 * promotes the events on the second-highest release level to the highest level and publishes them.
 *
 * Runs every 15 minutes, the due date is therefore executed with a delay of at most 15 minutes.
 *
 * See docs/features/auto-publish-events.md
 */
#[AsCronJob('*/2 * * * *')]
readonly class AutoPublishEventsCron
{
    public function __construct(
        private ContaoFramework $framework,
        private CalendarRunner $calendarRunner,
        private LoggerInterface|null $contaoCronLogger = null,
    ) {
    }

    public function __invoke(): void
    {
        $this->framework->initialize();

        foreach ($this->calendarRunner->findDueCalendarIds(new \DateTimeImmutable()) as $calendarId) {
            // An error in one calendar must not stop the others. The calendar is not marked as executed and is retried in the next run.
            try {
                $result = $this->calendarRunner->run($calendarId);
            } catch (\Throwable $e) {
                $this->contaoCronLogger?->error(\sprintf('Auto publish events cron: calendar ID %d failed: %s', $calendarId, $e->getMessage()));

                continue;
            }

            $this->contaoCronLogger?->info(\sprintf(
                'Auto publish events cron: calendar "%s" (ID %d): %d event(s) published, %d skipped (incl. %d error(s)).',
                $result->calendarTitle,
                $result->calendarId,
                \count($result->getPublished()),
                \count($result->getSkipped()),
                $result->countErrors(),
            ));
        }
    }
}
