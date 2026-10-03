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

namespace Markocupic\SacEventToolBundle\Feature\AutoPublishEvents\DataContainer;

use Contao\Config;
use Contao\CoreBundle\DependencyInjection\Attribute\AsCallback;
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\DataContainer;
use Contao\Date;
use Contao\StringUtil;

/**
 * tl_calendar callbacks for the auto publish events settings.
 */
readonly class Calendar
{
    public function __construct(private ContaoFramework $framework)
    {
    }

    /**
     * Shows the state of the last run (read-only).
     *
     * autoPublishEventsExecutedAt and autoPublishEventsExecutedForDate are written by the cron only
     * and are not part of the palette: a back end form must never overwrite them.
     */
    #[AsCallback(table: 'tl_calendar', target: 'fields.autoPublishEventsStatus.input_field')]
    public function renderStatus(DataContainer $dc): string
    {
        $record = $dc->getCurrentRecord() ?? [];

        $label = $GLOBALS['TL_LANG']['tl_calendar']['autoPublishEventsStatus'][0] ?? 'Status';

        return \sprintf(
            '<div class="widget clr"><h3>%s</h3><p>%s</p></div>',
            StringUtil::specialchars($label),
            StringUtil::specialchars($this->getStatusText($record)),
        );
    }

    /**
     * @param array<string, mixed> $record tl_calendar row
     */
    public function getStatusText(array $record): string
    {
        $lang = $GLOBALS['TL_LANG']['tl_calendar'] ?? [];

        $dueDate = (int) ($record['autoPublishEventsDate'] ?? 0);
        $executedAt = (int) ($record['autoPublishEventsExecutedAt'] ?? 0);
        $executedForDate = (int) ($record['autoPublishEventsExecutedForDate'] ?? 0);

        if (0 === $executedAt) {
            return $lang['autoPublishEventsStatusPending'] ?? '';
        }

        $key = $executedForDate === $dueDate ? 'autoPublishEventsStatusExecuted' : 'autoPublishEventsStatusExecutedForOtherDate';

        return \sprintf($lang[$key] ?? '%s %s', $this->formatDatim($executedAt), $this->formatDatim($executedForDate));
    }

    private function formatDatim(int $timestamp): string
    {
        $config = $this->framework->getAdapter(Config::class);
        $date = $this->framework->getAdapter(Date::class);

        return (string) $date->parse($config->get('datimFormat'), $timestamp);
    }
}
