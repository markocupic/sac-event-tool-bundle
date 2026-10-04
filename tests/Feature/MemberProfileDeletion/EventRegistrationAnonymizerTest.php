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

use Doctrine\DBAL\Connection;
use Markocupic\SacEventToolBundle\Feature\MemberProfileDeletion\EventRegistrationAnonymizer;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

final class EventRegistrationAnonymizerTest extends TestCase
{
    public function testUnknownOrAlreadyAnonymizedRegistrationIsNotChanged(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection
            ->method('fetchAssociative')
            ->with($this->stringContains('anonymized = 0'), [7])
            ->willReturn(false)
        ;

        $connection
            ->expects($this->never())
            ->method('update')
        ;

        $this->assertFalse((new EventRegistrationAnonymizer($connection))->anonymize(7));
    }

    public function testPersonalDataIsRemoved(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection
            ->method('fetchAssociative')
            ->willReturn(['id' => '7', 'deregistrationCause' => ''])
        ;

        $connection
            ->method('transactional')
            ->willReturnCallback(static fn (\Closure $func) => $func())
        ;

        $connection
            ->expects($this->once())
            ->method('update')
            ->with(
                'tl_calendar_events_member',
                $this->callback(
                    static fn (array $data): bool => 1 === $data['anonymized']
                        && 0 === $data['contaoMemberId']
                        && 0 === $data['sacMemberId']
                        && '' === $data['phone']
                        && '' === $data['gender']
                        && '' === $data['ahvNumber']
                        && null === $data['sectionId']
                        && '' === $data['instructorNotes']
                        && !\array_key_exists('deregistrationCause', $data),
                ),
                ['id' => 7],
            )
        ;

        $this->assertTrue((new EventRegistrationAnonymizer($connection))->anonymize(7));
    }

    public function testVersionsAreDeletedAndTheLogContainsNoPersonalData(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection
            ->method('fetchAssociative')
            ->with($this->logicalNot($this->logicalOr($this->stringContains('firstname'), $this->stringContains('lastname'))))
            ->willReturn(['id' => '7', 'deregistrationCause' => ''])
        ;

        $connection
            ->method('transactional')
            ->willReturnCallback(static fn (\Closure $func) => $func())
        ;

        $connection
            ->expects($this->once())
            ->method('delete')
            ->with('tl_version', ['fromTable' => 'tl_calendar_events_member', 'pid' => 7])
        ;

        $logger = $this->createMock(LoggerInterface::class);
        $logger
            ->expects($this->once())
            ->method('info')
            ->with('Anonymized the event registration with ID 7 and deleted its versions.')
        ;

        (new EventRegistrationAnonymizer($connection, $logger))->anonymize(7);
    }

    public function testDeregistrationCauseIsReplacedIfThereIsOne(): void
    {
        $data = (new EventRegistrationAnonymizer($this->createMock(Connection::class)))->getAnonymizedData(['deregistrationCause' => 'Krank']);

        $this->assertSame(EventRegistrationAnonymizer::ANONYMIZED, $data['deregistrationCause']);
    }

    public function testNoPersonalDataIsLeft(): void
    {
        $data = (new EventRegistrationAnonymizer($this->createMock(Connection::class)))->getAnonymizedData(['deregistrationCause' => '']);

        foreach (['firstname', 'lastname', 'street', 'city'] as $field) {
            $this->assertStringContainsString(EventRegistrationAnonymizer::ANONYMIZED, $data[$field]);
        }

        foreach (['email', 'mobile', 'phone', 'dateOfBirth', 'foodHabits', 'ahvNumber', 'gender'] as $field) {
            $this->assertSame('', $data[$field], $field);
        }
    }
}
