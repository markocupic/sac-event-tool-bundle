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

namespace Markocupic\SacEventToolBundle\Feature\EventEnclosureProtection;

use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\FilesModel;
use Contao\StringUtil;
use Doctrine\DBAL\Connection;

/**
 * Finds out whether a file is an enclosure (download) of an event.
 *
 * See docs/features/event-enclosure-protection.md
 */
class EventEnclosureFinder
{
    public function __construct(
        private readonly ContaoFramework $framework,
        private readonly Connection $connection,
    ) {
    }

    /**
     * @param string $path the path as in tl_files, e.g. "files/sektion/events/topo.pdf"
     */
    public function isEventEnclosure(string $path): bool
    {
        $this->framework->initialize();

        $file = $this->framework->getAdapter(FilesModel::class)->findByPath($path);

        if (null === $file) {
            return false;
        }

        return self::containsUuid(
            $this->connection->fetchFirstColumn("SELECT enclosure FROM tl_calendar_events WHERE addEnclosure = '1' AND enclosure IS NOT NULL"),
            (string) $file->uuid,
        );
    }

    /**
     * @param list<mixed> $enclosureFields the serialized enclosure fields of the events
     * @param string      $uuid            the binary uuid of the file
     */
    public static function containsUuid(array $enclosureFields, string $uuid): bool
    {
        foreach ($enclosureFields as $enclosureField) {
            if (\in_array($uuid, StringUtil::deserialize($enclosureField, true), true)) {
                return true;
            }
        }

        return false;
    }
}
