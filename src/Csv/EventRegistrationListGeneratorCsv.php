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
use Markocupic\SacEventToolBundle\Download\CsvDownload;
use Markocupic\SacEventToolBundle\Model\CalendarEventsMemberModel;
use Markocupic\SacEventToolBundle\String\Formatter\PhoneNumberFormatter;
use Markocupic\SacEventToolBundle\Util\AgeGroupUtil;
use Markocupic\SacEventToolBundle\Util\CalendarEventsUtil;
use Markocupic\SacEventToolBundle\Util\EventRegistrationUtil;
use Symfony\Component\HttpFoundation\StreamedResponse;

class EventRegistrationListGeneratorCsv
{
    private const array FIELDS = [
        'role',
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

    public function generate(CalendarEventsModel $event): StreamedResponse
    {
        $csv = new CsvDownload();
        $csv->setOutputBOM(CsvDownload::BOM_UTF8);

        $eventTimestamps = $this->calendarEventsUtil->getEventTimestamps($event);

        // Insert headline
        $csv->setHeadline($this->buildHeadlineRow($eventTimestamps));

        // Insert instructor rows
        $instructors = $this->fetchInstructors($event->id);

        foreach ($instructors as $instructor) {
            $csv->addRecord($this->buildInstructorRow($instructor, $event));
        }

        // Insert registration rows
        $registrations = $this->fetchRegistrations($event->id);

        foreach ($registrations as $registration) {
            $csv->addRecord($this->buildRegistrationRow($registration, $eventTimestamps));
        }

        // Sanitize event title
        $eventTitle = preg_replace('/[^a-zA-Z0-9_-]+/', '_', strtolower($event->title));
        $filename = \sprintf($this->sacevtEventMemberListFileNamePattern, $eventTitle, 'csv');

        return $csv->createResponse($filename);
    }

    private function buildHeadlineRow(array $eventTimestamps): array
    {
        $this->controllerAdapter->loadLanguageFile('tl_calendar_events_member');

        $headline = [];

        foreach (self::FIELDS as $field) {
            $headline[$field] = match ($field) {
                'role' => 'Rolle',
                default => $GLOBALS['TL_LANG']['tl_calendar_events_member'][$field][0] ?? $field,
            };
        }

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

    private function fetchInstructors(int $eventId): array
    {
        $model = CalendarEventsModel::findById($eventId);
        if (null === $model) {
            return [];
        }

        $ids = $this->calendarEventsUtil->getInstructorsAsArray($model);

        if (empty($ids)) {
            return [];
        }

        return $this->connection->fetchAllAssociative(
            'SELECT * FROM tl_user WHERE id IN (:ids)',
            ['ids' => implode(',', array_map('intval', $ids))],
        );
    }

    private function buildInstructorRow(array $dataInstructor, CalendarEventsModel $eventsModel): array
    {
        $dataMember = $this->connection->fetchAssociative('SELECT * FROM tl_member WHERE sacMemberId = ?', [$dataInstructor['sacMemberId'] ?? '']);

        if (false === $dataMember) {
            return [];
        }

        $row = [];

        foreach (self::FIELDS as $field) {
            $value = match ($field) {
                'role' => 'TL',
                'ahvNumber', 'emergencyPhone', 'emergencyPhoneName' => (string) $dataMember[$field] ?? '' ,
                'sacMemberId', 'firstname', 'lastname', 'gender', 'dateOfBirth', 'street', 'postal', 'city', 'phone', 'mobile', 'email' => $dataInstructor[$field],
                'J+S/Jugend' => AgeGroupUtil::getAgeGroup((int) $dataInstructor['dateOfBirth'], (int) Date::parse('Y', $eventsModel->startDate)),
                default => '',
            };

            $row[] = $this->formatFieldValue($field, $value, null);
        }

        $eventTimestamps = $this->calendarEventsUtil->getEventTimestamps($eventsModel);

        // Add event dates! See:
        // https://github.com/jonasmueller1/sac-pilatus-website/issues/203
        return array_merge($row, array_fill(0, \count($eventTimestamps), ''));
    }

    private function buildRegistrationRow(array $dataRegistration, array $eventTimestamps): array
    {
        $row = [];

        $registration = $this->calendarEventsMemberAdapter->findById($dataRegistration['id']);

        foreach (self::FIELDS as $field) {
            $value = match ($field) {
                'role' => 'TN',
                'dateAdded' => Date::parse($this->configAdapter->get('datimFormat'), $dataRegistration[$field] ?? ''),
                'J+S/Jugend' => null === $registration ? '' : $this->eventRegistrationUtil->getAgeGroup($registration),
                default => $dataRegistration[$field] ?? '',
            };
            $row[] = $this->formatFieldValue($field, $value, $registration);
        }

        // Add event dates! See:
        // https://github.com/jonasmueller1/sac-pilatus-website/issues/203
        return array_merge($row, array_fill(0, \count($eventTimestamps), ''));
    }

    private function formatFieldValue(string $field, mixed $value, CalendarEventsMemberModel|null $registration = null): string
    {
        $value = html_entity_decode((string) $value);

        return match ($field) {
            'phone', 'mobile', 'emergencyPhone' => '' === $value ? '' : 'T: '.PhoneNumberFormatter::format($value),
            'stateOfSubscription', 'gender' => $GLOBALS['TL_LANG']['MSC'][$value] ?? $value,
            'dateOfBirth' => date($this->configAdapter->get('dateFormat'), (int) $value),
            default => $value,
        };
    }
}
