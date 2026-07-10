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

namespace Markocupic\SacEventToolBundle\Database\SyncMember;

final class SyncLogger
{
    private int $countProcessedRecords = 0;

    private int $duration = 0;

    private array $updateMessages = [];

    private array $insertMessages = [];

    private array $disabledMessages = [];

    private array $messages = [];

    private array $errors = [];

    private \Throwable|null $exception = null;

    public function inkrementCountProcessedRecords(): void
    {
        ++$this->countProcessedRecords;
    }

    public function setException(\Throwable $e): void
    {
        $this->exception = $e;
    }

    public function setDuration(int $duration): void
    {
        $this->duration = $duration;
    }

    public function addDisabledMessage(string $message): void
    {
        $this->disabledMessages[] = $message;
    }

    public function addUpdateMessage(string $message): void
    {
        $this->updateMessages[] = $message;
    }

    public function addInsertMessage(string $message): void
    {
        $this->insertMessages[] = $message;
    }

    public function addMessage(string $message): void
    {
        $this->messages[] = $message;
    }

    public function addError(string $error): void
    {
        $this->errors[] = $error;
    }

    public function getCountProcessedRecords(): int
    {
        return $this->countProcessedRecords;
    }

    public function getException(): \Throwable|null
    {
        return $this->exception;
    }

    public function getDuration(): int
    {
        return $this->duration;
    }

    public function getInsertMessages(): array
    {
        return $this->insertMessages;
    }

    public function getUpdateMessages(): array
    {
        return $this->updateMessages;
    }

    public function getDisabledMessages(): array
    {
        return $this->disabledMessages;
    }

    public function hasMessages(): bool
    {
        return !empty($this->toArray()['messages']);
    }

    public function getMessages(): array
    {
        return $this->messages;
    }

    public function getErrors(): array
    {
        return $this->errors;
    }

    public function toArray(): array
    {
        return [
            'countProcessed' => $this->getCountProcessedRecords(),
            'countInserts' => \count($this->getInsertMessages()),
            'countUpdates' => \count($this->getUpdateMessages()),
            'countDisabled' => \count($this->getDisabledMessages()),
            'inserts' => $this->getInsertMessages(),
            'updates' => $this->getUpdateMessages(),
            'disabled' => $this->getDisabledMessages(),
            'messages' => array_merge($this->getInsertMessages(), $this->getUpdateMessages(), $this->getDisabledMessages()),
            'errors' => $this->getErrors(),
            'duration' => $this->getDuration(),
            'hasError' => !empty($this->getErrors()),
            'exception' => $this->getException()?->getMessage(),
        ];
    }
}
