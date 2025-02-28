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

namespace Markocupic\SacEventToolBundle\Event\DataContainer;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Contracts\EventDispatcher\Event;

class ContaoPostUpdateEvent extends Event
{
    public function __construct(
        private readonly string $tableName,
        private readonly int $recordId,
        private readonly array $preUpdateRecord,
        private readonly array $postUpdateRecord,
        private readonly array $diffData,
        private readonly Request $request,
    ) {
    }

    public function getTableName(): string
    {
        return $this->tableName;
    }

    public function getRecordId(): int
    {
        return $this->recordId;
    }

    public function getPreUpdateRecord(): array
    {
        return $this->preUpdateRecord;
    }

    public function getPostUpdateRecord(): array
    {
        return $this->postUpdateRecord;
    }

    public function getDiffData(): array
    {
        return $this->diffData;
    }

    public function getRequest(): Request
    {
        return $this->request;
    }
}
