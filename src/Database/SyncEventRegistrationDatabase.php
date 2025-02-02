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
use Symfony\Component\Stopwatch\StopwatchEvent;

#[Route('/_sync', name: self::class)]
class SyncEventRegistrationDatabase extends AbstractController
{
    private const string STOP_WATCH_EVENT = 'update_event_reg_data';
    private array $syncLog = [
        'processed_registrations' => 0,
        'processed_members' => 0,
        'updates' => 0,
        'log' => [],
        'duration' => 0,
        'with_error' => false,
        'exceptions' => [],
    ];

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

        $stopWatchEvent = $this->stopWatchStart();

        $this->sync();

        $duration = round($stopWatchEvent->stop()->getDuration() / 1000);
        $this->syncLog['duration'] = $duration;

        if (null !== $this->contaoGeneralLogger) {
            $strText = sprintf(
                'Successful update of the member data in the event registration table "tl_calendar_events_member": processed: %d, updates: %d, errors: %d, duration: %s.',
                $this->syncLog['processed_registrations'],
                $this->syncLog['updates'],
                \count($this->syncLog['exceptions']),
                $duration.' s',
            );

            $this->contaoGeneralLogger->info($strText);
        }

        return $this->json($this->syncLog);
    }

    public function getSyncLog(): array
    {
        return $this->syncLog;
    }

    private function stopWatchStart(): StopwatchEvent
    {
        return (new Stopwatch())->start(self::STOP_WATCH_EVENT);
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

    private function sync(): void
    {
        $arrContaoFrontendMemberIds = $this->getContaoMemberIds();

        $this->syncLog['processed_members'] = \count($arrContaoFrontendMemberIds);

        $this->connection->beginTransaction();

        try {
            foreach ($arrContaoFrontendMemberIds as $contaoMemberId) {
                $arrContaoMember = $this->connection->fetchAssociative(
                    'SELECT * FROM tl_member WHERE id = ?',
                    [
                        $contaoMemberId,
                    ],
                    [
                        Types::INTEGER,
                    ],
                );

                $arrRegAll = $this->connection->fetchAllAssociative(
                    'SELECT * FROM tl_calendar_events_member WHERE contaoMemberId = ?',
                    [$arrContaoMember['id']],
                    [Types::INTEGER],
                );

                foreach ($arrRegAll as $arrReg) {
                    ++$this->syncLog['processed_registrations'];

                    $set = [
                        'gender' => $arrContaoMember['gender'],
                        'firstname' => $arrContaoMember['firstname'],
                        'lastname' => $arrContaoMember['lastname'],
                        'street' => $arrContaoMember['street'],
                        'postal' => $arrContaoMember['postal'],
                        'city' => $arrContaoMember['city'],
                        'dateOfBirth' => $arrContaoMember['dateOfBirth'],
                        'phone' => $arrContaoMember['phone'],
                        'phoneBusiness' => $arrContaoMember['phoneBusiness'],
                    ];

                    // Do not override these contact data fields with empty values
                    $arrContact = ['email', 'mobile'];

                    foreach ($arrContact as $field) {
                        if (!empty($arrContaoMember[$field])) {
                            $set[$field] = $arrContaoMember[$field];
                        }
                    }

                    $intAffected = $this->connection->update(
                        'tl_calendar_events_member',
                        $set,
                        [
                            'id' => $arrReg['id'],
                        ],
                        [
                            'id' => Types::INTEGER,
                        ],
                    );

                    if (!empty($intAffected)) {
                        ++$this->syncLog['updates'];

                        $this->syncLog['log'][] = sprintf(
                            'Update contact data for event registration ID %d with member %s %s.',
                            $arrReg['id'],
                            $arrContaoMember['firstname'],
                            $arrContaoMember['lastname'],
                        );
                    }
                }
            }

            $this->connection->commit();
        } catch (\Exception $e) {
            $this->connection->rollBack();
            $this->syncLog['with_error'] = true;
            $this->syncLog['exceptions'][] = $e->getMessage();
            $this->contaoErrorLogger->error(sprintf('There has been an error while trying to update contact data of event registration ID %d. Error: %s', $arrReg['id'], $e->getMessage()));
        }
    }
}
