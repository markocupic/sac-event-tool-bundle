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

namespace Markocupic\SacEventToolBundle\Tests\Database\SyncMember;

use Markocupic\SacEventToolBundle\Database\SyncMember\SyncLogger;
use PHPUnit\Framework\TestCase;

class SyncLoggerTest extends TestCase
{
    public function testInitialStateIsEmpty(): void
    {
        $logger = new SyncLogger();

        $this->assertSame(0, $logger->getCountProcessedRecords());
        $this->assertSame(0, $logger->getDuration());
        $this->assertSame([], $logger->getInsertMessages());
        $this->assertSame([], $logger->getUpdateMessages());
        $this->assertSame([], $logger->getDisabledMessages());
        $this->assertSame([], $logger->getMessages());
        $this->assertSame([], $logger->getErrors());
        $this->assertNull($logger->getException());
        $this->assertFalse($logger->hasMessages());

        $array = $logger->toArray();
        $this->assertFalse($array['hasError']);
        $this->assertNull($array['exception']);
    }

    public function testIncrementCountProcessedRecords(): void
    {
        $logger = new SyncLogger();

        $logger->inkrementCountProcessedRecords();
        $logger->inkrementCountProcessedRecords();
        $logger->inkrementCountProcessedRecords();

        $this->assertSame(3, $logger->getCountProcessedRecords());
    }

    public function testMessagesAreCollectedAndCountedInToArray(): void
    {
        $logger = new SyncLogger();

        $logger->addInsertMessage('inserted 1');
        $logger->addInsertMessage('inserted 2');
        $logger->addUpdateMessage('updated 1');
        $logger->addDisabledMessage('disabled 1');

        $this->assertSame(['inserted 1', 'inserted 2'], $logger->getInsertMessages());
        $this->assertSame(['updated 1'], $logger->getUpdateMessages());
        $this->assertSame(['disabled 1'], $logger->getDisabledMessages());

        $array = $logger->toArray();
        $this->assertSame(2, $array['countInserts']);
        $this->assertSame(1, $array['countUpdates']);
        $this->assertSame(1, $array['countDisabled']);
        $this->assertSame(
            ['inserted 1', 'inserted 2', 'updated 1', 'disabled 1'],
            $array['messages'],
        );
    }

    public function testErrorsSetTheHasErrorFlag(): void
    {
        $logger = new SyncLogger();

        $logger->addError('boom');

        $this->assertSame(['boom'], $logger->getErrors());
        $this->assertTrue($logger->toArray()['hasError']);
    }

    public function testExceptionMessageIsExposedInToArray(): void
    {
        $logger = new SyncLogger();
        $exception = new \RuntimeException('sync failed');

        $logger->setException($exception);

        $this->assertSame($exception, $logger->getException());
        $this->assertSame('sync failed', $logger->toArray()['exception']);
    }

    public function testDurationIsStored(): void
    {
        $logger = new SyncLogger();

        $logger->setDuration(42);

        $this->assertSame(42, $logger->getDuration());
        $this->assertSame(42, $logger->toArray()['duration']);
    }

    public function testHasMessagesReflectsInsertUpdateAndDisabledMessages(): void
    {
        $logger = new SyncLogger();
        $this->assertFalse($logger->hasMessages());

        $logger->addUpdateMessage('updated');

        $this->assertTrue($logger->hasMessages());
    }

    public function testPlainMessagesAreKeptSeparateFromHasMessages(): void
    {
        $logger = new SyncLogger();

        // addMessage() feeds a separate bucket that is NOT part of hasMessages()/toArray()['messages'].
        $logger->addMessage('some note');

        $this->assertSame(['some note'], $logger->getMessages());
        $this->assertFalse($logger->hasMessages());
        $this->assertSame([], $logger->toArray()['messages']);
    }

    public function testToArrayExposesAllExpectedKeys(): void
    {
        $logger = new SyncLogger();

        $expectedKeys = [
            'countProcessed', 'countInserts', 'countUpdates', 'countDisabled',
            'inserts', 'updates', 'disabled', 'messages', 'errors',
            'duration', 'hasError', 'exception',
        ];

        $this->assertSame($expectedKeys, array_keys($logger->toArray()));
    }
}
