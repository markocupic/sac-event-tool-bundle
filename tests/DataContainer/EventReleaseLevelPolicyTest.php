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

namespace Markocupic\SacEventToolBundle\Tests\DataContainer;

use Contao\DataContainer;
use Contao\TestCase\ContaoTestCase;
use Doctrine\DBAL\Connection;
use Markocupic\SacEventToolBundle\DataContainer\EventReleaseLevelPolicy;
use Symfony\Contracts\Translation\TranslatorInterface;

final class EventReleaseLevelPolicyTest extends ContaoTestCase
{
    /**
     * @dataProvider validLevelsProvider
     *
     * @param list<int> $otherLevels
     */
    public function testValidLevels(int $level, array $otherLevels): void
    {
        $this->createCallback()->checkLevel($level, $otherLevels);

        $this->addToAssertionCount(1);
    }

    public static function validLevelsProvider(): iterable
    {
        yield 'first level of a new system' => [1, []];
        yield 'next level' => [4, [1, 2, 3]];
        yield 'fills a gap' => [2, [1, 3]];
        yield 'edit without change' => [3, [1, 2]];
    }

    /**
     * @dataProvider invalidLevelsProvider
     *
     * @param list<int> $otherLevels
     */
    public function testInvalidLevels(int $level, array $otherLevels, string $expectedError): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage($expectedError);

        $this->createCallback()->checkLevel($level, $otherLevels);
    }

    public static function invalidLevelsProvider(): iterable
    {
        yield 'duplicate level' => [2, [1, 2, 3], 'errLevelExists'];
        yield 'first level is not 1' => [2, [], 'errLevelGap'];
        yield 'gap after the highest level' => [5, [1, 2, 3], 'errLevelGap'];
        yield 'moving a level creates a gap' => [4, [1, 3], 'errLevelGap'];
    }

    public function testValidateLevelQueriesTheOtherLevelsOfTheSameSystem(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection
            ->expects($this->once())
            ->method('fetchFirstColumn')
            ->with($this->stringContains('pid = ? AND id != ? AND level IS NOT NULL'), [3, 12])
            ->willReturn(['1', '2'])
        ;

        $this->assertSame('3', $this->createCallback($connection)->validateLevel('3', $this->createDataContainer()));
    }

    public function testValidateLevelIgnoresEmptyValues(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection
            ->expects($this->never())
            ->method('fetchFirstColumn')
        ;

        $this->assertNull($this->createCallback($connection)->validateLevel(null, $this->createDataContainer()));
    }

    public function testDuplicateTitleInTheSameSystemIsRejected(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection
            ->method('fetchOne')
            ->with($this->stringContains('title = ?'), [3, 12, 'FS 1: erfasst'])
            ->willReturn(1)
        ;

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('errTitleExists');

        $this->createCallback($connection)->validateTitle('FS 1: erfasst', $this->createDataContainer());
    }

    public function testUniqueTitleIsAccepted(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection
            ->method('fetchOne')
            ->willReturn(0)
        ;

        $this->assertSame('FS 1: erfasst', $this->createCallback($connection)->validateTitle('FS 1: erfasst', $this->createDataContainer()));
    }

    private function createCallback(Connection|null $connection = null): EventReleaseLevelPolicy
    {
        // Returns the translation key, so the tests can check which error was thrown
        $translator = $this->createMock(TranslatorInterface::class);
        $translator
            ->method('trans')
            ->willReturnCallback(static fn (string $id): string => $id)
        ;

        return new EventReleaseLevelPolicy($translator, $connection ?? $this->createMock(Connection::class), $this->mockContaoFramework());
    }

    private function createDataContainer(): DataContainer
    {
        $dc = $this->mockClassWithProperties(DataContainer::class, ['id' => 12]);
        $dc
            ->method('getCurrentRecord')
            ->willReturn(['id' => 12, 'pid' => 3])
        ;

        return $dc;
    }
}
