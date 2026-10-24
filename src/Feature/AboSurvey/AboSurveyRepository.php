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

namespace Markocupic\SacEventToolBundle\Feature\AboSurvey;

use Contao\FrontendUser;
use Doctrine\DBAL\Connection;

/**
 * Reads and writes the entries of the abo survey (tl_abo_survey).
 * A member has at most one entry per year (unique index sacMemberId,year).
 */
final readonly class AboSurveyRepository
{
    public const string TABLE = 'tl_abo_survey';

    public function __construct(private Connection $connection)
    {
    }

    public function findOneBySacMemberIdAndYear(int $sacMemberId, int $year): array|null
    {
        $row = $this->connection->fetchAssociative(
            'SELECT * FROM '.self::TABLE.' WHERE sacMemberId = ? AND year = ?',
            [$sacMemberId, $year],
        );

        return false === $row ? null : $row;
    }

    /**
     * Titles of all groups (tl_event_organizer.title) in the order of the back end.
     * The survey stores the title, not the id (tl_abo_survey.eventOrganizer).
     *
     * @return array<string>
     */
    public function findEventOrganizerTitles(): array
    {
        return $this->connection->fetchFirstColumn('SELECT title FROM tl_event_organizer ORDER BY sorting');
    }

    /**
     * Creates the entry or updates the existing entry of the member for this year.
     * The name and the contact data are always taken from the member profile (tl_member).
     */
    public function save(FrontendUser $user, int $year, string $eventOrganizer, string $previousAboType, string $aboType, string $notice = ''): void
    {
        $set = [
            'tstamp' => time(),
            'year' => $year,
            'eventOrganizer' => $eventOrganizer,
            'previousAboType' => $previousAboType,
            'aboType' => $aboType,
            'sacMemberId' => (int) $user->sacMemberId,
            'firstname' => (string) $user->firstname,
            'lastname' => (string) $user->lastname,
            'email' => (string) $user->email,
            'street' => (string) $user->street,
            'postal' => (string) $user->postal,
            'city' => (string) $user->city,
            'notice' => $notice,
        ];

        $existing = $this->findOneBySacMemberIdAndYear((int) $user->sacMemberId, $year);

        if (null !== $existing) {
            $this->connection->update(self::TABLE, $set, ['id' => $existing['id']]);

            return;
        }

        $set['dateAdded'] = time();

        $this->connection->insert(self::TABLE, $set);
    }
}
