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

namespace Markocupic\SacEventToolBundle\Feature\EventRegistrationCleanup\Command;

use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\StringUtil;
use Markocupic\SacEventToolBundle\Feature\EventRegistrationCleanup\EventRegistrationCleanup;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Usage:
 *   php vendor/bin/contao-console sacevt:event-registration:cleanup --dry-run # only list, change nothing
 *   php vendor/bin/contao-console sacevt:event-registration:cleanup # like the cron
 *
 * See docs/features/event-registration-cleanup.md
 */
#[AsCommand(
    name: 'sacevt:event-registration:cleanup',
    description: 'Deletes registrations of deleted events and anonymizes registrations of deleted members.',
)]
class EventRegistrationCleanupCommand extends Command
{
    private const array HEADERS = ['ID', 'Event-ID', 'Event', 'Name', 'SAC-Nr.', 'Contao-Mitglied'];

    public function __construct(
        private readonly ContaoFramework $framework,
        private readonly EventRegistrationCleanup $eventRegistrationCleanup,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('dry-run', null, InputOption::VALUE_NONE, 'Only list the registrations, change nothing.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $dryRun = (bool) $input->getOption('dry-run');

        $this->framework->initialize();

        $result = $this->eventRegistrationCleanup->run($dryRun);

        $io->title($dryRun ? 'Bereinigung der Event-Anmeldungen (Dry-Run, es wird nichts geändert)' : 'Bereinigung der Event-Anmeldungen');

        $io->section(\sprintf('%s: Anmeldungen zu gelöschten Events (%d)', $dryRun ? 'Würden gelöscht' : 'Gelöscht', \count($result['deleted'])));
        $this->printRegistrations($io, $result['deleted']);

        $io->section(\sprintf('%s: Anmeldungen gelöschter Mitglieder (%d)', $dryRun ? 'Würden anonymisiert' : 'Anonymisiert', \count($result['anonymized'])));
        $this->printRegistrations($io, $result['anonymized']);

        return Command::SUCCESS;
    }

    /**
     * @param list<array<string, mixed>> $registrations
     */
    private function printRegistrations(SymfonyStyle $io, array $registrations): void
    {
        if ([] === $registrations) {
            $io->text('Keine.');

            return;
        }

        $io->table(self::HEADERS, array_map(
            static fn (array $registration): array => [
                $registration['id'],
                $registration['eventId'],
                StringUtil::revertInputEncoding((string) $registration['eventName']),
                trim($registration['firstname'].' '.$registration['lastname']),
                $registration['sacMemberId'],
                $registration['contaoMemberId'],
            ],
            $registrations,
        ));
    }
}
