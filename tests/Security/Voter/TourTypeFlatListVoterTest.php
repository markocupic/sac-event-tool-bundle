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

use Contao\CoreBundle\Security\DataContainer\CreateAction;
use Contao\CoreBundle\Security\DataContainer\DeleteAction;
use Contao\CoreBundle\Security\DataContainer\UpdateAction;
use Markocupic\SacEventToolBundle\Security\Voter\TourTypeFlatListVoter;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\VoterInterface;

final class TourTypeFlatListVoterTest extends TestCase
{
    private const ATTRIBUTE = 'contao_dc.tl_tour_type';

    public function testSupportsOnlyCreateAndUpdateActionsOfTheTable(): void
    {
        $voter = new TourTypeFlatListVoter();

        $this->assertTrue($voter->supportsAttribute(self::ATTRIBUTE));
        $this->assertFalse($voter->supportsAttribute('contao_dc.tl_calendar'));
        $this->assertTrue($voter->supportsType(CreateAction::class));
        $this->assertTrue($voter->supportsType(UpdateAction::class));
        $this->assertFalse($voter->supportsType(DeleteAction::class));
    }

    public function testRecordsOnTheRootLevelAreAllowed(): void
    {
        $this->assertSame(VoterInterface::ACCESS_ABSTAIN, $this->vote(new CreateAction('tl_tour_type', ['pid' => 0, 'sorting' => 128])));
        $this->assertSame(VoterInterface::ACCESS_ABSTAIN, $this->vote(new UpdateAction('tl_tour_type', ['id' => 2, 'pid' => 0], ['pid' => 0, 'sorting' => 256])));
    }

    public function testActionsWithoutNewPidAreIgnored(): void
    {
        $this->assertSame(VoterInterface::ACCESS_ABSTAIN, $this->vote(new CreateAction('tl_tour_type')));
        $this->assertSame(VoterInterface::ACCESS_ABSTAIN, $this->vote(new UpdateAction('tl_tour_type', ['id' => 2, 'pid' => 0], ['title' => 'Skitour'])));
    }

    public function testNestingIsDenied(): void
    {
        $this->assertSame(VoterInterface::ACCESS_DENIED, $this->vote(new CreateAction('tl_tour_type', ['pid' => 3, 'sorting' => 0])));
        $this->assertSame(VoterInterface::ACCESS_DENIED, $this->vote(new UpdateAction('tl_tour_type', ['id' => 2, 'pid' => 0], ['pid' => 3, 'sorting' => 0])));
    }

    public function testOtherAttributesAreIgnored(): void
    {
        $voter = new TourTypeFlatListVoter();
        $action = new CreateAction('tl_calendar', ['pid' => 3]);

        $this->assertSame(VoterInterface::ACCESS_ABSTAIN, $voter->vote($this->createMock(TokenInterface::class), $action, ['contao_dc.tl_calendar']));
    }

    private function vote(CreateAction|UpdateAction $action): int
    {
        return (new TourTypeFlatListVoter())->vote($this->createMock(TokenInterface::class), $action, [self::ATTRIBUTE]);
    }
}
