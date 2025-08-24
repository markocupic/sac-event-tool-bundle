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

namespace Markocupic\SacEventToolBundle\DataContainer\EventDispatcher;

use Contao\CoreBundle\DependencyInjection\Attribute\AsCallback;
use Contao\DataContainer;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Types\Types;
use Markocupic\SacEventToolBundle\Event\DataContainer\ContaoPostUpdateEvent;
use Markocupic\SacEventToolBundle\Event\DataContainer\ContaoPreUpdateEvent;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * This service makes it possible to monitor changes to Contao tables that were
 * made in the Contao backend. Use ContaoPreUpdateEvent or ContaoPostUpdateEvent
 * listeners to compare data records before the change and after the change.
 */
class ContaoUpdateEventDispatcher
{
    private string|null $tableName = null;

    private int|null $recordId = null;

    private array $preUpdateRecord = [];

    public function __construct(
        private readonly Connection $connection,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly RequestStack $requestStack,
    ) {
    }

    /**
     * Dispatch the Contao-Pre-Update-Event. We use a very low priority to ensure that
     * the callbacks are triggered as late as possible.
     *
     * @param array $updatedFields the modifications to be applied to the record
     */
    #[AsCallback(table: 'tl_calendar_events', target: 'config.onbeforesubmit', priority: -99999)]
    #[AsCallback(table: 'tl_calendar_events_member', target: 'config.onbeforesubmit', priority: -99999)]
    public function dispatchPreUpdateEvent(array $updatedFields, DataContainer $dc): array
    {
        $this->initializeUpdateContext($dc);

        $this->preUpdateRecord = $this->fetchRecord($this->tableName, $this->recordId);

        $event = new ContaoPreUpdateEvent(
            $this->tableName,
            $this->recordId,
            $this->preUpdateRecord,
            $updatedFields,
            $this->requestStack->getCurrentRequest(),
        );

        $this->eventDispatcher->dispatch($event);

        return $event->getUpdatedFields();
    }

    /**
     * Dispatch the Contao-Post-Update-Event. We use a very low priority to ensure
     * that the callbacks are triggered as late as possible.
     */
    #[AsCallback(table: 'tl_calendar_events', target: 'config.onsubmit', priority: -99999)]
    #[AsCallback(table: 'tl_calendar_events_member', target: 'config.onsubmit', priority: -99999)]
    public function dispatchPostUpdateEvent(DataContainer $dc): void
    {
        if (!$this->validatePreUpdateState($dc)) {
            // Stop here if the config.onbeforesubmit callback has not been triggered because
            // of an empty form submit.
            return;
        }

        $postUpdateRecord = $this->fetchRecord($this->tableName, $this->recordId);
        $diffData = array_diff_assoc($postUpdateRecord, $this->preUpdateRecord);

        $event = new ContaoPostUpdateEvent(
            $this->tableName,
            $this->recordId,
            $this->preUpdateRecord,
            $postUpdateRecord,
            $diffData,
            $this->requestStack->getCurrentRequest(),
        );

        $this->eventDispatcher->dispatch($event);
    }

    protected function fetchRecord(string $tableName, int $recordId): array
    {
        return $this->connection->fetchAssociative(
            \sprintf('SELECT * FROM %s WHERE id = ?', $tableName),
            [$recordId],
            [Types::INTEGER],
        );
    }

    /**
     * Validates that the config.onbeforesubmit callback has been triggered and the
     * pre-update state is consistent and valid.
     */
    protected function validatePreUpdateState(DataContainer $dc): bool
    {
        if ($this->tableName !== $dc->table || $this->recordId !== (int) $dc->id || 0 === $this->recordId) {
            return false;
        }

        return true;
    }

    /**
     * Initializes the update context by setting the table name and record ID.
     */
    protected function initializeUpdateContext(DataContainer $dc): void
    {
        $this->recordId = (int) $dc->id;
        $this->tableName = $dc->table;
    }
}
