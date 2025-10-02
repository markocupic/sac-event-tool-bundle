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

namespace Markocupic\SacEventToolBundle\Csv;

use Contao\CalendarEventsModel;
use Contao\Config;
use Contao\Controller;
use Contao\CoreBundle\Framework\Adapter;
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\Date;
use Doctrine\DBAL\Connection;
use League\Csv\Bom;
use League\Csv\Writer;
use Markocupic\SacEventToolBundle\Model\CalendarEventsMemberModel;
use Markocupic\SacEventToolBundle\Util\CalendarEventsUtil;
use Markocupic\SacEventToolBundle\Util\EventRegistrationUtil;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\HttpFoundation\Response;

class EventRegistrationListGeneratorCsv
{
    private const string DELIMITER = ';';

    private const array FIELDS = [
        'id',
        'stateOfSubscription',
        'dateAdded',
        'carInfo',
        'ticketInfo',
        'notes',
        'instructorNotes',
        'bookingType',
        'sacMemberId',
        'ahvNumber',
        'firstname',
        'lastname',
        'gender',
        'dateOfBirth',
        'foodHabits',
        'street',
        'postal',
        'city',
        'phone',
        'mobile',
        'email',
        'emergencyPhone',
        'emergencyPhoneName',
        'hasParticipated',
        'J+S/Jugend',
    ];

    // Adapters
    private Adapter $calendarEventsMemberAdapter;

    private Adapter $configAdapter;

    private Adapter $controllerAdapter;

    private Adapter $dateAdapter;

    public function __construct(
        private readonly CalendarEventsUtil $calendarEventsUtil,
        private readonly ContaoFramework $framework,
        private readonly Connection $connection,
        private readonly EventRegistrationUtil $eventRegistrationUtil,
        private readonly string $sacevtEventMemberListFileNamePattern,
    ) {
        // Adapters
        $this->calendarEventsMemberAdapter = $this->framework->getAdapter(CalendarEventsMemberModel::class);
        $this->configAdapter = $this->framework->getAdapter(Config::class);
        $this->controllerAdapter = $this->framework->getAdapter(Controller::class);
        $this->dateAdapter = $this->framework->getAdapter(Date::class);
    }

    public function generate(CalendarEventsModel $event): Response
    {
        $csv = $this->createCsvWriter();
        $eventTimestamps = $this->calendarEventsUtil->getEventTimestamps($event);

        // Insert headline
        $csv->insertOne($this->buildHeadlineRow($eventTimestamps));

        // Insert registration rows
        $registrations = $this->fetchRegistrations($event->id);

        foreach ($registrations as $registration) {
            $csv->insertOne($this->buildRegistrationRow($registration, $eventTimestamps));
        }

        return $this->createCsvResponse($csv, $event->title);
    }

    private function createCsvWriter(): Writer
    {
        $csv = Writer::createFromString();
        $csv->setOutputBOM(Bom::Utf8);
        $csv->setDelimiter(self::DELIMITER);

        return $csv;
    }

    private function buildHeadlineRow(array $eventTimestamps): array
    {
        $this->controllerAdapter->loadLanguageFile('tl_calendar_events_member');

        $headline = array_map(
            static fn ($field) => $GLOBALS['TL_LANG']['tl_calendar_events_member'][$field][0] ?? $field,
            self::FIELDS,
        );

        // Add event dates! See:
        // https://github.com/jonasmueller1/sac-pilatus-website/issues/203
        foreach ($eventTimestamps as $eventTimestamp) {
            $headline[] = $this->dateAdapter->parse('d.m.', $eventTimestamp);
        }

        return $headline;
    }

    private function fetchRegistrations(int $eventId): array
    {
        return $this->connection->fetchAllAssociative(
            'SELECT * FROM tl_calendar_events_member WHERE eventId = ? ORDER BY lastname, firstname',
            [$eventId],
        );
    }

    private function buildRegistrationRow(array $dataRegistration, array $eventTimestamps): array
    {
        $row = [];

        $registration = $this->calendarEventsMemberAdapter->findById($dataRegistration['id']);

        foreach (self::FIELDS as $field) {
            $row[] = $this->formatFieldValue($field, $dataRegistration[$field] ?? '', $registration);
        }

        // Add event dates! See:
        // https://github.com/jonasmueller1/sac-pilatus-website/issues/203
        return array_merge($row, array_fill(0, \count($eventTimestamps), ''));
    }

    private function formatFieldValue(string $field, mixed $value, CalendarEventsMemberModel $registration): string
    {
        $value = html_entity_decode((string) $value);

        return match ($field) {
            'stateOfSubscription', 'gender' => $GLOBALS['TL_LANG']['MSC'][$value] ?? $value,
            'dateAdded' => date($this->configAdapter->get('datimFormat'), (int) $value),
            'dateOfBirth' => date($this->configAdapter->get('dateFormat'), (int) $value),
            'J+S/Jugend' => null === $registration ? '' : $this->eventRegistrationUtil->getAgeGroup($registration),
            default => $value,
        };
    }

    private function createCsvResponse(Writer $csv, string $eventTitle): Response
    {
        // Sanitize event title
        $eventTitle = preg_replace('/[^a-zA-Z0-9_-]+/', '_', strtolower($eventTitle));
        $filename = \sprintf($this->sacevtEventMemberListFileNamePattern, $eventTitle, 'csv');

        $response = new Response($csv->toString());
        $disposition = HeaderUtils::makeDisposition(
            HeaderUtils::DISPOSITION_ATTACHMENT,
            $filename,
        );
        $response->headers->set('Content-Disposition', $disposition);

        return $response;
    }
}
