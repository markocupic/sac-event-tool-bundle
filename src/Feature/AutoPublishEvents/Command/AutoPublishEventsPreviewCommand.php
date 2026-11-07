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

namespace Markocupic\SacEventToolBundle\Feature\AutoPublishEvents\Command;

use Contao\CoreBundle\Framework\ContaoFramework;
use Markocupic\SacEventToolBundle\Feature\AutoPublishEvents\CalendarRunner;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Lists all calendars with the auto publish enabled and a due date in the future (sorted by due date)
 * and shows which events would be promoted and published on the due date, based on the current state
 * of the events. Changes nothing.
 *
 * Publishing is done by the cron (AutoPublishEventsCron) or manually with
 * sacevt:auto-publish-events:run (AutoPublishEventsRunCommand).
 *
 * Usage:
 *   php vendor/bin/contao-console sacevt:auto-publish-events:preview
 *
 * See docs/features/auto-publish-events.md
 */
#[AsCommand(
    name: 'sacevt:auto-publish-events:preview',
    description: 'Shows which events will be promoted to the highest release level and published on the due date of each calendar. Changes nothing.',
)]
class AutoPublishEventsPreviewCommand extends Command
{
    public function __construct(
        private readonly ContaoFramework $framework,
        private readonly CalendarRunner $calendarRunner,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $this->framework->initialize();

        $io->title('Events automatisch veröffentlichen: Vorschau');

        $calendarIds = $this->calendarRunner->findUpcomingCalendarIds(new \DateTimeImmutable());

        if ([] === $calendarIds) {
            $io->text('Kein Kalender mit einem Stichtag in der Zukunft.');

            return Command::SUCCESS;
        }

        foreach ($calendarIds as $calendarId) {
            PublishResultPrinter::print($io, $this->calendarRunner->run($calendarId, true));
        }

        $io->note('Massgebend ist der Stand der Events am Stichtag. Events, die bis dahin noch hoch- oder herabgestuft werden, sind in dieser Vorschau nicht berücksichtigt.');

        return Command::SUCCESS;
    }
}
