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

namespace Markocupic\SacEventToolBundle\Tests\Feature\ParticipantEventHistory;

use Doctrine\DBAL\Connection;
use Markocupic\SacEventToolBundle\Feature\ParticipantEventHistory\ParticipantEventHistoryQuery;
use PHPUnit\Framework\TestCase;

final class ParticipantEventHistoryQueryTest extends TestCase
{
    private string $timezone;

    protected function setUp(): void
    {
        parent::setUp();

        $this->timezone = date_default_timezone_get();
        date_default_timezone_set('Europe/Zurich');
    }

    protected function tearDown(): void
    {
        date_default_timezone_set($this->timezone);

        parent::tearDown();
    }

    /**
     * @dataProvider historyStartProvider
     */
    public function testHistoryStart(string $now, string $expected): void
    {
        $this->assertSame($expected, ParticipantEventHistoryQuery::getHistoryStart(new \DateTimeImmutable($now))->format('Y-m-d H:i:s'));
    }

    public static function historyStartProvider(): iterable
    {
        yield 'same date five years ago' => ['2026-10-08 13:20:00', '2021-10-08 00:00:00'];
        yield 'shortly after midnight' => ['2026-10-08 00:00:01', '2021-10-08 00:00:00'];
        yield 'leap day' => ['2028-02-29 10:00:00', '2023-03-01 00:00:00'];
    }

    public function testDoesNotQueryWithoutSacMemberId(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection
            ->expects($this->never())
            ->method('createQueryBuilder')
        ;

        $this->assertSame([], (new ParticipantEventHistoryQuery($connection))->findEventIds(0, new \DateTimeImmutable()));
    }
}
