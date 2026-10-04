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

namespace Markocupic\SacEventToolBundle\Feature\MemberProfileDeletion;

use Contao\CoreBundle\Monolog\ContaoContext;
use Doctrine\DBAL\Connection;
use Markocupic\SacEventToolBundle\Config\Log;
use Psr\Log\LoggerInterface;

/**
 * Removes the personal data of an event registration (tl_calendar_events_member).
 * The registration itself is kept (statistics, invoices), it can no longer be assigned to a person.
 *
 * Also deletes the versions of the registration (tl_version), because they contain the personal data.
 * The log entry contains only the ID of the registration, no personal data.
 *
 * See docs/features/member-profile-deletion.md
 */
class EventRegistrationAnonymizer
{
    public const string ANONYMIZED = '[anonymisiert]';

    public function __construct(
        private readonly Connection $connection,
        private readonly LoggerInterface|null $contaoGeneralLogger = null,
    ) {
    }

    /**
     * @return bool false if the registration does not exist or is already anonymized
     */
    public function anonymize(int $registrationId): bool
    {
        $registration = $this->connection->fetchAssociative(
            'SELECT id, deregistrationCause FROM tl_calendar_events_member WHERE id = ? AND anonymized = 0',
            [$registrationId],
        );

        if (false === $registration) {
            return false;
        }

        $this->connection->transactional(
            function () use ($registration, $registrationId): void {
                $this->connection->update('tl_calendar_events_member', $this->getAnonymizedData($registration), ['id' => $registrationId]);
                $this->connection->delete('tl_version', ['fromTable' => 'tl_calendar_events_member', 'pid' => $registrationId]);
            },
        );

        $this->contaoGeneralLogger?->info(
            \sprintf('Anonymized the event registration with ID %d and deleted its versions.', $registrationId),
            ['contao' => new ContaoContext(__METHOD__, Log::ANONYMIZE_EVENT_REGISTRATION)],
        );

        return true;
    }

    /**
     * @param array<string, mixed> $registration
     *
     * @return array<string, mixed>
     */
    public function getAnonymizedData(array $registration): array
    {
        $data = [
            'tstamp' => time(),
            'anonymized' => 1,
            'contaoMemberId' => 0,
            'sacMemberId' => 0,
            'gender' => '',
            'firstname' => 'Vorname '.self::ANONYMIZED,
            'lastname' => 'Nachname '.self::ANONYMIZED,
            'dateOfBirth' => '',
            'street' => 'Adresse '.self::ANONYMIZED,
            'postal' => '0',
            'city' => 'Ort '.self::ANONYMIZED,
            'email' => '',
            'phone' => '',
            'mobile' => '',
            'sectionId' => null,
            'ahvNumber' => '',
            'emergencyPhone' => '999 99 99',
            'emergencyPhoneName' => self::ANONYMIZED,
            'foodHabits' => '',
            'instructorNotes' => '',
            'notes' => 'Benutzerdaten anonymisiert am '.date('d.m.Y'),
        ];

        // Keep the information that there was a cause, but not the cause itself
        if ('' !== trim((string) $registration['deregistrationCause'])) {
            $data['deregistrationCause'] = self::ANONYMIZED;
        }

        return $data;
    }
}
