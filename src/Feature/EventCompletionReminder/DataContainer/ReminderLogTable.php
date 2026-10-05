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

namespace Markocupic\SacEventToolBundle\Feature\EventCompletionReminder\DataContainer;

use Contao\Config;
use Contao\CoreBundle\DependencyInjection\Attribute\AsCallback;
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\DataContainer;
use Contao\Date;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Formats the list columns of the read-only back end module
 * for tl_event_completion_reminder_log.
 */
readonly class ReminderLogTable
{
    public function __construct(
        private Connection $connection,
        private ContaoFramework $framework,
        private TranslatorInterface $translator,
    ) {
    }

    /**
     * Columns (see list.label.fields): sentAt, userId, calendarId, reminderCount, openTaskCount, eventIds, delivered.
     *
     * @param array<string, mixed> $row
     * @param list<string>         $columns
     *
     * @return list<string>
     */
    #[AsCallback(table: 'tl_event_completion_reminder_log', target: 'list.label.label')]
    public function formatColumns(array $row, string $label, DataContainer $dc, array $columns): array
    {
        $this->framework->initialize();

        $dateAdapter = $this->framework->getAdapter(Date::class);
        $configAdapter = $this->framework->getAdapter(Config::class);

        $columns[0] = $dateAdapter->parse($configAdapter->get('datimFormat'), (int) $row['sentAt']);
        $columns[1] = $this->getRecipient((int) $row['userId']);
        $columns[2] = $this->getCalendarTitle((int) $row['calendarId']);
        // 0: entries written before the column existed
        $columns[3] = (int) $row['reminderCount'] > 0 ? (string) (int) $row['reminderCount'] : '–';
        $columns[4] = (string) (int) $row['openTaskCount'];
        $columns[5] = $this->getEventTitles((string) $row['eventIds']);
        $columns[6] = $this->translator->trans($row['delivered'] ? 'MSC.yes' : 'MSC.no', [], 'contao_default');

        return $columns;
    }

    private function getRecipient(int $userId): string
    {
        $user = $this->connection->fetchAssociative('SELECT name, email FROM tl_user WHERE id = ?', [$userId]);

        if (false === $user) {
            return \sprintf('ID %d (gelöscht)', $userId);
        }

        return \sprintf('%s<br><span class="tl_gray">%s</span>', $user['name'], $user['email']);
    }

    private function getCalendarTitle(int $calendarId): string
    {
        $title = $this->connection->fetchOne('SELECT title FROM tl_calendar WHERE id = ?', [$calendarId]);

        return false !== $title ? (string) $title : \sprintf('ID %d (gelöscht)', $calendarId);
    }

    /**
     * Event titles with their ID, one per line. Deleted events are shown by ID only.
     */
    private function getEventTitles(string $eventIds): string
    {
        $ids = array_values(array_filter(array_map('intval', explode(',', $eventIds))));

        if (empty($ids)) {
            return '';
        }

        $titles = $this->connection->fetchAllKeyValue(
            'SELECT id, title FROM tl_calendar_events WHERE id IN (?)',
            [$ids],
            [ArrayParameterType::INTEGER],
        );

        $lines = [];

        foreach ($ids as $id) {
            $lines[] = isset($titles[$id]) ? \sprintf('%s <span class="tl_gray">[%d]</span>', $titles[$id], $id) : \sprintf('<span class="tl_gray">[%d] (gelöscht)</span>', $id);
        }

        return implode('<br>', $lines);
    }
}
