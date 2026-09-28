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

namespace Markocupic\SacEventToolBundle\Tests\EventReminder;

use Markocupic\SacEventToolBundle\EventReminder\Person;
use Markocupic\SacEventToolBundle\EventReminder\RecipientResolver;
use PHPUnit\Framework\TestCase;

final class RecipientResolverTest extends TestCase
{
    private RecipientResolver $resolver;

    protected function setUp(): void
    {
        $this->resolver = new RecipientResolver();
    }

    // --- main instructor ---------------------------------------------------

    public function testFlaggedMainInstructorWins(): void
    {
        $anna = $this->person(1, 'Anna', 'anna@example.org');
        $beat = $this->person(2, 'Beat', 'beat@example.org');

        $this->assertSame($beat, $this->resolver->getMainInstructor([$anna, $beat], $beat));
    }

    public function testFirstInstructorIsMainInstructorIfNoneIsFlagged(): void
    {
        $anna = $this->person(1, 'Anna', 'anna@example.org');
        $beat = $this->person(2, 'Beat', 'beat@example.org');

        $this->assertSame($anna, $this->resolver->getMainInstructor([$anna, $beat], null));
    }

    public function testFlaggedMainInstructorWithoutEmailFallsBackToFirstInstructorWithEmail(): void
    {
        $noMail = $this->person(1, 'Ohne Mail', '');
        $beat = $this->person(2, 'Beat', 'beat@example.org');

        $this->assertSame($beat, $this->resolver->getMainInstructor([$noMail, $beat], $noMail));
    }

    public function testNoMainInstructorWithoutInstructors(): void
    {
        $this->assertNull($this->resolver->getMainInstructor([], null));
    }

    // --- co-instructors ----------------------------------------------------

    public function testCoInstructorsExcludeMainInstructor(): void
    {
        $anna = $this->person(1, 'Anna', 'anna@example.org');
        $beat = $this->person(2, 'Beat', 'beat@example.org');
        $cleo = $this->person(3, 'Cleo', 'cleo@example.org');

        $this->assertSame([$anna, $cleo], $this->resolver->getCoInstructors([$anna, $beat, $cleo], $beat));
    }

    public function testAllInstructorsAreCoInstructorsWithoutMainInstructor(): void
    {
        $anna = $this->person(1, 'Anna', 'anna@example.org');
        $beat = $this->person(2, 'Beat', 'beat@example.org');

        $this->assertSame([$anna, $beat], $this->resolver->getCoInstructors([$anna, $beat], null));
    }

    // --- contact -----------------------------------------------------------

    public function testRegistrationCoordinatorIsContact(): void
    {
        $main = $this->person(1, 'Anna', 'anna@example.org');
        $coordinator = $this->person(9, 'Koordinator', 'koordinator@example.org');

        $this->assertSame($coordinator, $this->resolver->getContact($main, $coordinator));
    }

    public function testMainInstructorIsContactWithoutCoordinator(): void
    {
        $main = $this->person(1, 'Anna', 'anna@example.org');

        $this->assertSame($main, $this->resolver->getContact($main, null));
    }

    public function testCoordinatorWithoutEmailIsIgnored(): void
    {
        $main = $this->person(1, 'Anna', 'anna@example.org');
        $coordinator = $this->person(9, 'Koordinator', '');

        $this->assertSame($main, $this->resolver->getContact($main, $coordinator));
    }

    // --- resolve -----------------------------------------------------------

    public function testToCcBccAreDisjoint(): void
    {
        $anna = $this->person(1, 'Anna', 'anna@example.org');
        $beat = $this->person(2, 'Beat', 'beat@example.org');
        $cleo = $this->person(3, 'Cleo', 'cleo@example.org');

        $recipients = $this->resolver->resolve($beat, [$anna, $beat, $cleo], ['p1@example.org', 'p2@example.org']);

        $this->assertSame('beat@example.org', $recipients->to);
        $this->assertSame(['anna@example.org', 'cleo@example.org'], $recipients->cc);
        $this->assertSame(['p1@example.org', 'p2@example.org'], $recipients->bcc);
    }

    public function testCoordinatorAsContactIsNotInCcAndAllInstructorsAre(): void
    {
        $anna = $this->person(1, 'Anna', 'anna@example.org');
        $beat = $this->person(2, 'Beat', 'beat@example.org');
        $coordinator = $this->person(9, 'Koordinator', 'koordinator@example.org');

        $recipients = $this->resolver->resolve($coordinator, [$anna, $beat], []);

        $this->assertSame('koordinator@example.org', $recipients->to);
        $this->assertSame(['anna@example.org', 'beat@example.org'], $recipients->cc);
        $this->assertSame([], $recipients->bcc);
    }

    public function testCoordinatorWhoIsAlsoInstructorAppearsOnlyInTo(): void
    {
        $anna = $this->person(1, 'Anna', 'anna@example.org');
        $beat = $this->person(2, 'Beat', 'beat@example.org');

        $recipients = $this->resolver->resolve($beat, [$anna, $beat], []);

        $this->assertSame('beat@example.org', $recipients->to);
        $this->assertSame(['anna@example.org'], $recipients->cc);
    }

    public function testParticipantWhoIsAlsoInstructorIsNotInBcc(): void
    {
        $anna = $this->person(1, 'Anna', 'anna@example.org');
        $beat = $this->person(2, 'Beat', 'beat@example.org');

        $recipients = $this->resolver->resolve($beat, [$anna, $beat], ['anna@example.org', 'beat@example.org', 'p1@example.org']);

        $this->assertSame(['p1@example.org'], $recipients->bcc);
    }

    public function testEmailsAreNormalizedAndDeduplicated(): void
    {
        $anna = $this->person(1, 'Anna', ' Anna@Example.org ');
        $beat = $this->person(2, 'Beat', 'BEAT@example.org');

        $recipients = $this->resolver->resolve($beat, [$anna, $beat], ['P1@example.org', 'p1@example.org ', '', 'p2@example.org']);

        $this->assertSame('beat@example.org', $recipients->to);
        $this->assertSame(['anna@example.org'], $recipients->cc);
        $this->assertSame(['p1@example.org', 'p2@example.org'], $recipients->bcc);
        $this->assertSame('p1@example.org,p2@example.org', $recipients->getBccAsString());
    }

    public function testNoContactGivesEmptyToAndAllInstructorsInCc(): void
    {
        $anna = $this->person(1, 'Anna', 'anna@example.org');

        $recipients = $this->resolver->resolve(null, [$anna], ['p1@example.org']);

        $this->assertSame('', $recipients->to);
        $this->assertSame(['anna@example.org'], $recipients->cc);
        $this->assertSame(['p1@example.org'], $recipients->bcc);
    }

    private function person(int $id, string $name, string $email): Person
    {
        return new Person($id, $name, $email);
    }
}
