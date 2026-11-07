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
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Promotes the events to the highest release level and publishes them now, like the cron
 * (AutoPublishEventsCron) does on the due date.
 *
 * - Without option: all calendars whose due date has been reached and whose run is still pending.
 * - With --calendar=<ID>: this calendar only, even before its due date. The run counts as the run
 *   for the current due date, the cron does not run this calendar again on the due date.
 *
 * The command first shows a preview and asks for confirmation (skipped with --no-interaction).
 * Published and skipped events are logged in the Contao system log like in the cron.
 *
 * Usage:
 *   php vendor/bin/contao-console sacevt:auto-publish-events:run
 *   php vendor/bin/contao-console sacevt:auto-publish-events:run --calendar=12
 *   php vendor/bin/contao-console sacevt:auto-publish-events:run --calendar=12 --no-interaction
 *
 * See docs/features/auto-publish-events.md
 */
#[AsCommand(
    name: 'sacevt:auto-publish-events:run',
    description: 'Promotes the events to the highest release level and publishes them now (all due calendars or one calendar with --calendar).',
)]
class AutoPublishEventsRunCommand extends Command
{
    public function __construct(
        private readonly ContaoFramework $framework,
        private readonly CalendarRunner $calendarRunner,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('calendar', 'c', InputOption::VALUE_REQUIRED, 'ID of a calendar: run this calendar now, even before its due date');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $this->framework->initialize();

        $io->title('Events automatisch veröffentlichen');

        $calendarId = $input->getOption('calendar');

        if (null !== $calendarId) {
            if (!ctype_digit((string) $calendarId) || 0 === (int) $calendarId) {
                $io->error('Die Option --calendar erwartet eine Kalender-ID.');

                return Command::INVALID;
            }

            if (!$this->calendarRunner->isPending((int) $calendarId)) {
                $io->error(\sprintf('Kalender ID %d kann nicht ausgeführt werden: Die Funktion ist nicht aktiviert, es ist kein Stichtag gesetzt oder der Lauf für diesen Stichtag ist bereits erfolgt. Für einen erneuten Lauf den Stichtag im Backend ändern.', $calendarId));

                return Command::FAILURE;
            }

            $calendarIds = [(int) $calendarId];
        } else {
            $calendarIds = $this->calendarRunner->findDueCalendarIds(new \DateTimeImmutable());

            if ([] === $calendarIds) {
                $io->text('Kein Kalender ist fällig. Mit --calendar=<ID> lässt sich ein Kalender vor dem Stichtag ausführen.');

                return Command::SUCCESS;
            }
        }

        // Preview (dry run) before changing anything
        $hasFutureDueDate = false;

        foreach ($calendarIds as $id) {
            $preview = $this->calendarRunner->run($id, true);
            $hasFutureDueDate = $hasFutureDueDate || $preview->dueDate > time();

            PublishResultPrinter::print($io, $preview);
        }

        if ($hasFutureDueDate) {
            $io->note('Der Stichtag liegt noch in der Zukunft. Dieser Lauf gilt als Lauf für den Stichtag: Der Cron führt den Kalender am Stichtag nicht mehr aus.');
        }

        if ($input->isInteractive() && !$io->confirm('Sollen diese Events jetzt hochgestuft und veröffentlicht werden?', false)) {
            $io->text('Abgebrochen. Es wurde nichts geändert.');

            return Command::SUCCESS;
        }

        $io->title('Ergebnis');

        $hasErrors = false;

        foreach ($calendarIds as $id) {
            // An error in one calendar must not stop the others (as in the cron)
            try {
                $result = $this->calendarRunner->run($id);
            } catch (\Throwable $e) {
                $io->error(\sprintf('Kalender ID %d: %s', $id, $e->getMessage()));
                $hasErrors = true;

                continue;
            }

            PublishResultPrinter::print($io, $result);

            if ($result->countErrors() > 0) {
                $hasErrors = true;
            }
        }

        if ($hasErrors) {
            $io->warning('Es sind Fehler aufgetreten. Details im Contao-System-Log.');

            return Command::FAILURE;
        }

        $io->success('Fertig. Veröffentlichte und übersprungene Events sind im Contao-System-Log protokolliert.');

        return Command::SUCCESS;
    }
}
