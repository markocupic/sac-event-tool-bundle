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

namespace Markocupic\SacEventToolBundle\Tests\Util;

use Contao\TestCase\ContaoTestCase;
use Doctrine\DBAL\Connection;
use Markocupic\SacEventToolBundle\Model\EventReleaseLevelPolicyModel;
use Markocupic\SacEventToolBundle\Security\Voter\CalendarEventsVoter;
use Markocupic\SacEventToolBundle\Util\EventReleaseLevelPolicyUtil;
use Markocupic\SacEventToolBundle\Util\ReleaseLevel;

/**
 * The sorting (highest level first) is done by the SQL query and is not covered here.
 */
final class EventReleaseLevelPolicyUtilTest extends ContaoTestCase
{
    public function testGetLevelsByEventTypeMapsTheRowsOfTheReleaseLevelSystem(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection
            ->expects($this->once())
            ->method('fetchAllAssociative')
            ->with($this->stringContains('tl_event_type'), ['tour'])
            ->willReturn([
                ['id' => '14', 'level' => '5', 'title' => 'FS 5: publiziert'],
                ['id' => '12', 'level' => '2', 'title' => 'FS 2: geprüft'],
                ['id' => '11', 'level' => '1', 'title' => 'FS 1: erfasst'],
            ])
        ;

        $this->assertEquals(
            [
                new ReleaseLevel(14, 5, 'FS 5: publiziert'),
                new ReleaseLevel(12, 2, 'FS 2: geprüft'),
                new ReleaseLevel(11, 1, 'FS 1: erfasst'),
            ],
            $this->createUtil($connection)->getLevelsByEventType('tour'),
        );
    }

    public function testGetLevelsByEventTypeReturnsAnEmptyListWithoutReleaseLevelSystem(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection
            ->method('fetchAllAssociative')
            ->willReturn([])
        ;

        $this->assertSame([], $this->createUtil($connection)->getLevelsByEventType('generalEvent'));
    }

    private function createUtil(Connection $connection): EventReleaseLevelPolicyUtil
    {
        return new EventReleaseLevelPolicyUtil(
            $this->mockContaoFramework([EventReleaseLevelPolicyModel::class => $this->mockAdapter(['findById'])]),
            $this->createMock(CalendarEventsVoter::class),
            $connection,
        );
    }
}
