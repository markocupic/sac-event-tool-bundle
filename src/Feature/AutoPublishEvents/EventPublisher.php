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

use Contao\CoreBundle\Cache\EntityCacheTags;
use Contao\Versions;
use Doctrine\DBAL\Connection;
use Symfony\Component\Routing\RouterInterface;

/**
 * Promotes one event to the highest release level and publishes it.
 *
 * EventReleaseLevelUtil::shiftEventReleaseLevel() is not used on purpose: it needs a request
 * (ChangeEventReleaseLevelEvent, PublishEventEvent), writes back end messages and sends
 * e-mails via listeners that only work in the back end. The cron has none of these.
 * Decided on 2026-10-03: no notifications, only the Contao system log (written by CalendarRunner).
 *
 * The Contao framework must be initialized (Versions).
 */
class EventPublisher
{
    public const string VERSION_USERNAME = 'Auto Publish Events';

    public function __construct(
        private readonly Connection $connection,
        private readonly EntityCacheTags $entityCacheTags,
        private readonly RouterInterface $router,
    ) {
    }

    /**
     * Returns false if the event was changed in the meantime (other release level or already published).
     */
    public function publish(Candidate $candidate): bool
    {
        $versions = $this->createVersions($candidate->eventId);

        // Creates the initial version if the event has none yet, so the old state can be restored
        $versions->initialize();

        // The WHERE clause makes sure that only the expected state is changed
        $affectedRows = $this->connection->update(
            'tl_calendar_events',
            [
                'eventReleaseLevel' => $candidate->targetLevel->id,
                'published' => 1,
                'tstamp' => time(),
            ],
            [
                'id' => $candidate->eventId,
                'eventReleaseLevel' => $candidate->currentLevel->id,
                'published' => 0,
            ],
        );

        if (0 === (int) $affectedRows) {
            return false;
        }

        $versions->create();

        // Same tags as DataContainer::invalidateCacheTags() when an event is saved in the back end
        $this->entityCacheTags->invalidateTagsFor([
            'contao.db.tl_calendar_events.'.$candidate->eventId,
            'contao.db.tl_calendar.'.$candidate->calendarId,
        ]);

        return true;
    }

    /**
     * There is no logged-in user in the cron: username, user ID and edit URL must be set.
     */
    protected function createVersions(int $eventId): Versions
    {
        $editUrl = $this->router->generate('contao_backend', [
            'do' => 'calendar',
            'table' => 'tl_calendar_events',
            'id' => $eventId,
            'act' => 'edit',
        ]);

        $versions = new Versions('tl_calendar_events', $eventId);
        $versions->setUsername(self::VERSION_USERNAME);
        $versions->setUserId(0);
        $versions->setEditUrl(ltrim($editUrl, '/'));

        return $versions;
    }
}
