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

namespace Markocupic\SacEventToolBundle\Tests\Security\Voter;

use Contao\CoreBundle\Security\DataContainer\DeleteAction;
use Contao\CoreBundle\Security\DataContainer\UpdateAction;
use Doctrine\DBAL\Connection;
use Markocupic\SacEventToolBundle\Security\Voter\EventReleaseLevelPolicyDeleteVoter;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\VoterInterface;

final class EventReleaseLevelPolicyDeleteVoterTest extends TestCase
{
    private const ATTRIBUTE = 'contao_dc.tl_event_release_level_policy';

    /**
     * Release level system 3 with the levels 1, 2, 3 (record IDs 31, 32, 33) and a new, unsaved record 34.
     */
    private const RECORDS = [
        31 => ['pid' => '3', 'level' => '1'],
        32 => ['pid' => '3', 'level' => '2'],
        33 => ['pid' => '3', 'level' => '3'],
        34 => ['pid' => '3', 'level' => null],
    ];

    public function testSupportsOnlyDeleteActionsOfTheTable(): void
    {
        $voter = new EventReleaseLevelPolicyDeleteVoter($this->createMock(Connection::class));

        $this->assertTrue($voter->supportsAttribute(self::ATTRIBUTE));
        $this->assertFalse($voter->supportsAttribute('contao_dc.tl_calendar'));
        $this->assertTrue($voter->supportsType(DeleteAction::class));
        $this->assertFalse($voter->supportsType(UpdateAction::class));
    }

    public function testHighestLevelMayBeDeleted(): void
    {
        $this->assertSame(VoterInterface::ACCESS_ABSTAIN, $this->vote(33));
    }

    public function testLowerLevelsMayNotBeDeleted(): void
    {
        $this->assertSame(VoterInterface::ACCESS_DENIED, $this->vote(32));
        $this->assertSame(VoterInterface::ACCESS_DENIED, $this->vote(31));
    }

    public function testRecordWithoutLevelMayBeDeleted(): void
    {
        $this->assertSame(VoterInterface::ACCESS_ABSTAIN, $this->vote(34));
    }

    public function testOtherAttributesAreIgnored(): void
    {
        $voter = new EventReleaseLevelPolicyDeleteVoter($this->createConnection());
        $action = new DeleteAction('tl_calendar', ['id' => 31]);

        $this->assertSame(VoterInterface::ACCESS_ABSTAIN, $voter->vote($this->createMock(TokenInterface::class), $action, ['contao_dc.tl_calendar']));
    }

    private function vote(int $id): int
    {
        $voter = new EventReleaseLevelPolicyDeleteVoter($this->createConnection());
        $action = new DeleteAction('tl_event_release_level_policy', ['id' => $id]);

        return $voter->vote($this->createMock(TokenInterface::class), $action, [self::ATTRIBUTE]);
    }

    private function createConnection(): Connection
    {
        $connection = $this->createMock(Connection::class);
        $connection
            ->method('fetchAssociative')
            ->willReturnCallback(static fn (string $sql, array $params): array|false => self::RECORDS[$params[0]] ?? false)
        ;

        $connection
            ->method('fetchOne')
            ->willReturn('3')
        ;

        return $connection;
    }
}
