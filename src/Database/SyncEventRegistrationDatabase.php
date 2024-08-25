<?php

declare(strict_types=1);

/*
 * This file is part of SAC Event Tool Bundle.
 *
 * (c) Marko Cupic 2024 <m.cupic@gmx.ch>
 * @license GPL-3.0-or-later
 * For the full copyright and license information,
 * please view the LICENSE file that was distributed with this source code.
 * @link https://github.com/markocupic/sac-event-tool-bundle
 */

namespace Markocupic\SacEventToolBundle\Database;

use Contao\CoreBundle\Controller\AbstractController;
use Contao\CoreBundle\Framework\ContaoFramework;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Types\Types;
use Markocupic\SacEventToolBundle\Config\BookingType;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Stopwatch\Stopwatch;

#[Route('/_sync', name: self::class)]
class SyncEventRegistrationDatabase extends AbstractController
{
    private const STOP_WATCH_EVENT = 'update_event_reg_data';
    private int $affected = 0;
    private array $affectedMembers = [];
    private array $errors = [];

    public function __construct(
        private readonly ContaoFramework $framework,
        private readonly Connection $connection,
        private readonly LoggerInterface|null $contaoGeneralLogger = null,
        private readonly LoggerInterface|null $contaoErrorLogger = null,
    ) {
    }

    public function run(): JsonResponse
    {
        $this->framework->initialize();

        $stopWatchEvent = (new Stopwatch())->start(self::STOP_WATCH_EVENT);

        $arrIDS = $this->getContaoMemberIds();

        foreach ($arrIDS as $contaoMemberId) {
            $this->sync($contaoMemberId);
        }

        $duration = round($stopWatchEvent->stop()->getDuration() / 1000).' s';

        if (null !== $this->contaoGeneralLogger) {
            $strText = sprintf(
                'Successful update of the member data in the event registration table "tl_calendar_events_member": affected rows: %d, duration: %s.',
                $this->affected,
                $duration,
            );
            $this->contaoErrorLogger->error(sprintf('There has been an error while trying to update event registrations from Contao member with ID %d.', 99));

            $this->contaoGeneralLogger->info($strText);
        }

        $json = [
            'affected_members' => $this->affectedMembers,
            'affected_registrations_count' => $this->affected,
            'errors' => $this->errors,
            'duration' => $duration,
        ];

        return $this->json($json);
    }

    private function getContaoMemberIds(): array
    {
        return $this->connection->fetchFirstColumn(
            '
				SELECT
					contaoMemberId
				FROM
					tl_calendar_events_member AS t1
				WHERE
					t1.anonymized = 0
				AND
				    t1.bookingType = :bookingType
				AND
					t1.contaoMemberId = (SELECT id FROM tl_member AS t2 WHERE t2.id = t1.contaoMemberId)
				GROUP BY
					t1.sacMemberId
			',
            [
                'bookingType' => BookingType::ONLINE_FORM,
            ],
            [
                'bookingType' => Types::STRING,
            ],
        );
    }

    private function sync(int $contaoMemberId): void
    {
        $arrMember = $this->connection->fetchAssociative(
            '
					SELECT
						id,gender,firstname,lastname,street,postal,city,dateOfBirth,email,mobile
					FROM
						tl_member
					WHERE
						id = ?
    			',
            [
                $contaoMemberId,
            ],
            [
                Types::INTEGER,
            ],
        );

        try {
            $this->connection->beginTransaction();

            $set = [
                'gender' => $arrMember['gender'],
                'firstname' => $arrMember['firstname'],
                'lastname' => $arrMember['lastname'],
                'street' => $arrMember['street'],
                'postal' => $arrMember['postal'],
                'city' => $arrMember['city'],
                'dateOfBirth' => $arrMember['dateOfBirth'],
                'email' => $arrMember['email'],
                'mobile' => $arrMember['mobile'],
            ];

            $intAffected = $this->connection->update(
                'tl_calendar_events_member',
                $set,
                [
                    'contaoMemberId' => $contaoMemberId,
                ],
                [
                    'contaoMemberId' => Types::INTEGER,
                ],
            );

            if (\is_int($intAffected) && $intAffected > 0) {
                $this->affected += $intAffected;

                $this->affectedMembers[] = [
                    'id' => $arrMember['id'],
                    'firstname' => $arrMember['firstname'],
                    'lastname' => $arrMember['lastname'],
                    'street' => $arrMember['street'],
                    'city' => $arrMember['city'],
                    'affected_rows' => $intAffected,
                ];
            }

            $this->connection->commit();
        } catch (\Exception $e) {
            $this->connection->rollBack();
            $this->errors[] = [
                'member_id' => $arrMember['id'],
                'error_message' => $e->getMessage(),
            ];

            $this->contaoErrorLogger->error(sprintf('There has been an error while trying to update event registrations from Contao member with ID %d.', $arrMember['id']));
        }
    }
}
