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

namespace Markocupic\SacEventToolBundle\Tests\Feature\EventEnclosureProtection;

use Markocupic\SacEventToolBundle\Feature\EventEnclosureProtection\EventEnclosureFinder;
use PHPUnit\Framework\TestCase;

final class EventEnclosureFinderTest extends TestCase
{
    public function testFindsTheUuidInTheEnclosuresOfAnEvent(): void
    {
        $topo = random_bytes(16);
        $map = random_bytes(16);

        $fields = [
            serialize([random_bytes(16)]),
            serialize([$map, $topo]),
        ];

        $this->assertTrue(EventEnclosureFinder::containsUuid($fields, $topo));
    }

    public function testIgnoresOtherFilesAndEmptyFields(): void
    {
        $fields = [
            serialize([random_bytes(16)]),
            '',
            null,
        ];

        $this->assertFalse(EventEnclosureFinder::containsUuid($fields, random_bytes(16)));
        $this->assertFalse(EventEnclosureFinder::containsUuid([], random_bytes(16)));
    }
}
