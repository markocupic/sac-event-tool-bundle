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

namespace Markocupic\SacEventToolBundle\Tests\Feature\AutoPublishEvents;

use Contao\CoreBundle\Cache\EntityCacheTags;
use Contao\Versions;
use Doctrine\DBAL\Connection;
use Markocupic\SacEventToolBundle\Feature\AutoPublishEvents\Candidate;
use Markocupic\SacEventToolBundle\Feature\AutoPublishEvents\EventPublisher;
use Markocupic\SacEventToolBundle\Util\ReleaseLevel;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Routing\RouterInterface;

final class EventPublisherTest extends TestCase
{
    public function testPromotesPublishesAndCreatesAVersion(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection
            ->expects($this->once())
            ->method('update')
            ->with(
                'tl_calendar_events',
                $this->callback(static fn (array $data): bool => 14 === $data['eventReleaseLevel'] && 1 === $data['published'] && $data['tstamp'] > 0),
                ['id' => 5, 'eventReleaseLevel' => 13, 'published' => 0],
            )
            ->willReturn(1)
        ;

        $versions = $this->createMock(Versions::class);
        $versions
            ->expects($this->once())
            ->method('initialize')
        ;

        $versions
            ->expects($this->once())
            ->method('create')
        ;

        $entityCacheTags = $this->createMock(EntityCacheTags::class);
        $entityCacheTags
            ->expects($this->once())
            ->method('invalidateTagsFor')
            ->with(['contao.db.tl_calendar_events.5', 'contao.db.tl_calendar.7'])
        ;

        $this->assertTrue($this->createPublisher($connection, $entityCacheTags, $versions)->publish($this->candidate()));
    }

    public function testDoesNothingIfTheEventChangedInTheMeantime(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection
            ->method('update')
            ->willReturn(0)
        ;

        $versions = $this->createMock(Versions::class);
        $versions
            ->expects($this->never())
            ->method('create')
        ;

        $entityCacheTags = $this->createMock(EntityCacheTags::class);
        $entityCacheTags
            ->expects($this->never())
            ->method('invalidateTagsFor')
        ;

        $this->assertFalse($this->createPublisher($connection, $entityCacheTags, $versions)->publish($this->candidate()));
    }

    private function createPublisher(Connection $connection, EntityCacheTags $entityCacheTags, Versions $versions): EventPublisher
    {
        $publisher = $this->getMockBuilder(EventPublisher::class)
            ->setConstructorArgs([$connection, $entityCacheTags, $this->createMock(RouterInterface::class)])
            ->onlyMethods(['createVersions'])
            ->getMock()
        ;

        $publisher
            ->method('createVersions')
            ->with(5)
            ->willReturn($versions)
        ;

        return $publisher;
    }

    private function candidate(): Candidate
    {
        return new Candidate(5, 7, 'Skitour Pilatus', new ReleaseLevel(13, 3, 'FS 3'), new ReleaseLevel(14, 4, 'FS 4'));
    }
}
