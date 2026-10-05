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

namespace Markocupic\SacEventToolBundle\DataContainer;

use Contao\CoreBundle\DependencyInjection\Attribute\AsCallback;
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\DataContainer;
use Contao\StringUtil;
use Doctrine\DBAL\Connection;
use Symfony\Contracts\Translation\TranslatorInterface;

class EventReleaseLevelPolicy
{
    public function __construct(
        private readonly TranslatorInterface $translator,
        private readonly Connection $connection,
        private readonly ContaoFramework $framework,
    ) {
    }

    #[AsCallback(table: 'tl_event_release_level_policy', target: 'list.sorting.child_record', priority: 100)]
    public function listReleaseLevels(array $row): string
    {
        $title = $this->framework->getAdapter(StringUtil::class)->specialchars($row['title']);

        return '<div class="tl_content_left"><span class="level">'.$this->translator->trans('MSC.level', [], 'contao_default').': '.$row['level'].'</span> '.$title."</div>\n";
    }

    /**
     * Every level may only exist once per release level system (also secured by the unique index pid,level)
     * and the levels of a system must be consecutive, starting at 1 (1, 2, 3, ...).
     *
     * The manual upgrade/downgrade in AccessDecision\CalendarEvents relies on consecutive levels (level + 1, level - 1).
     */
    #[AsCallback(table: 'tl_event_release_level_policy', target: 'fields.level.save')]
    public function validateLevel(mixed $varValue, DataContainer $dc): mixed
    {
        if (null === $varValue || '' === $varValue) {
            return $varValue;
        }

        $otherLevels = $this->connection->fetchFirstColumn(
            'SELECT level FROM tl_event_release_level_policy WHERE pid = ? AND id != ? AND level IS NOT NULL',
            [$this->getPid($dc), (int) $dc->id],
        );

        $this->checkLevel((int) $varValue, array_map(intval(...), $otherLevels));

        return $varValue;
    }

    /**
     * The title only needs to be unique within a release level system. There is no database index on purpose:
     * new records are inserted with an empty title before the form is saved.
     */
    #[AsCallback(table: 'tl_event_release_level_policy', target: 'fields.title.save')]
    public function validateTitle(mixed $varValue, DataContainer $dc): mixed
    {
        $count = (int) $this->connection->fetchOne(
            'SELECT COUNT(*) FROM tl_event_release_level_policy WHERE pid = ? AND id != ? AND title = ?',
            [$this->getPid($dc), (int) $dc->id, (string) $varValue],
        );

        if ($count > 0) {
            throw new \RuntimeException($this->translator->trans('tl_event_release_level_policy.errTitleExists', [(string) $varValue], 'contao_tl_event_release_level_policy'));
        }

        return $varValue;
    }

    /**
     * @param list<int> $otherLevels levels of the other records of the same release level system
     *
     * @throws \RuntimeException
     */
    public function checkLevel(int $level, array $otherLevels): void
    {
        if (\in_array($level, $otherLevels, true)) {
            throw new \RuntimeException($this->translator->trans('tl_event_release_level_policy.errLevelExists', [$level], 'contao_tl_event_release_level_policy'));
        }

        $levels = [...$otherLevels, $level];
        sort($levels);

        if ($levels !== range(1, \count($levels))) {
            throw new \RuntimeException($this->translator->trans('tl_event_release_level_policy.errLevelGap', [$level, implode(', ', $levels)], 'contao_tl_event_release_level_policy'));
        }
    }

    private function getPid(DataContainer $dc): int
    {
        return (int) ($dc->getCurrentRecord()['pid'] ?? 0);
    }
}
