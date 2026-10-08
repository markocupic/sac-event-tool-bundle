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

namespace Markocupic\SacEventToolBundle\Tests\EventReleaseLevel;

use Contao\TestCase\ContaoTestCase;
use Markocupic\SacEventToolBundle\EventReleaseLevel\EventReleaseLevelPermissionRules;
use Markocupic\SacEventToolBundle\Model\EventReleaseLevelPolicyModel;

final class EventReleaseLevelPermissionRulesTest extends ContaoTestCase
{
    public function testGrantsTheFlagToTheParties(): void
    {
        $level = $this->createLevel([
            1 => ['parties' => ['event_author', 'event_instructors'], 'group' => '', 'flags' => ['can_write_event']],
        ]);

        $rules = new EventReleaseLevelPermissionRules();

        $this->assertTrue($rules->isGranted($level, 'can_write_event', static fn (string $party): bool => 'event_instructors' === $party, static fn (): bool => false));
        $this->assertFalse($rules->isGranted($level, 'can_write_event', static fn (string $party): bool => 'registration_coordinator' === $party, static fn (): bool => false));
        $this->assertFalse($rules->isGranted($level, 'can_delete_event', static fn (): bool => true, static fn (): bool => true));
    }

    public function testGrantsTheFlagToTheMembersOfTheGroup(): void
    {
        $level = $this->createLevel([
            1 => ['parties' => [], 'group' => '3', 'flags' => ['can_cut_event']],
        ]);

        $rules = new EventReleaseLevelPermissionRules();

        $this->assertTrue($rules->isGranted($level, 'can_cut_event', static fn (): bool => false, static fn (int $groupId): bool => 3 === $groupId));
        $this->assertFalse($rules->isGranted($level, 'can_cut_event', static fn (): bool => false, static fn (int $groupId): bool => 4 === $groupId));
    }

    public function testOnlyChecksThePartiesOfTheRulesWithTheFlag(): void
    {
        $level = $this->createLevel([
            1 => ['parties' => ['event_instructors'], 'group' => '', 'flags' => ['can_delete_event']],
            2 => ['parties' => ['event_author'], 'group' => '', 'flags' => ['can_write_event']],
        ]);

        $checkedParties = [];

        (new EventReleaseLevelPermissionRules())->isGranted(
            $level,
            'can_write_event',
            static function (string $party) use (&$checkedParties): bool {
                $checkedParties[] = $party;

                return false;
            },
            static fn (): bool => false,
        );

        $this->assertSame(['event_author'], $checkedParties);
    }

    public function testDeniesAccessWithoutRules(): void
    {
        $rules = new EventReleaseLevelPermissionRules();

        $this->assertFalse($rules->isGranted($this->createLevel([], 1), 'can_write_event', static fn (): bool => true, static fn (): bool => true));
        $this->assertFalse($rules->isGranted($this->createLevel(null, 2), 'can_write_event', static fn (): bool => true, static fn (): bool => true));
        $this->assertFalse($rules->isGranted($this->createLevel(['invalid'], 3), 'can_write_event', static fn (): bool => true, static fn (): bool => true));
    }

    private function createLevel(array|null $rules, int $id = 1): EventReleaseLevelPolicyModel
    {
        return $this->mockClassWithProperties(EventReleaseLevelPolicyModel::class, [
            'id' => $id,
            'permissionRules' => null === $rules ? null : serialize($rules),
        ]);
    }
}
