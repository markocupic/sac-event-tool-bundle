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

namespace Markocupic\SacEventToolBundle\Tests\Feature\MemberProfileDeletion;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Markocupic\SacEventToolBundle\Feature\MemberProfileDeletion\EventRegistrationRemover;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

final class EventRegistrationRemoverTest extends TestCase
{
    public function testDeletesRegistrationsAndVersions(): void
    {
        $statements = [];

        $connection = $this->createMock(Connection::class);
        $connection
            ->method('transactional')
            ->willReturnCallback(static fn (\Closure $func) => $func())
        ;
        $connection
            ->method('executeStatement')
            ->willReturnCallback(
                static function (string $sql, array $params, array $types) use (&$statements): int {
                    $statements[] = [$sql, $params, $types];

                    return 2;
                }
            )
        ;

        $logger = $this->createMock(LoggerInterface::class);
        $logger
            ->expects($this->once())
            ->method('info')
            ->with('Deleted 2 event registration(s) of deleted events and their versions: IDs 2, 4.')
        ;

        (new EventRegistrationRemover($connection, $logger))->remove([2, 4]);

        $this->assertCount(2, $statements);
        $this->assertSame('DELETE FROM tl_calendar_events_member WHERE id IN (?)', $statements[0][0]);
        $this->assertSame("DELETE FROM tl_version WHERE fromTable = 'tl_calendar_events_member' AND pid IN (?)", $statements[1][0]);

        foreach ($statements as [, $params, $types]) {
            $this->assertSame([[2, 4]], $params);
            $this->assertSame([ArrayParameterType::INTEGER], $types);
        }
    }

    public function testNothingToDelete(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection
            ->expects($this->never())
            ->method('transactional')
        ;

        $connection
            ->expects($this->never())
            ->method('executeStatement')
        ;

        (new EventRegistrationRemover($connection))->remove([]);
    }
}
