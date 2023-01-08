<?php

declare(strict_types=1);

/*
 * This file is part of SAC Event Tool Bundle.
 *
 * (c) Marko Cupic 2023 <m.cupic@gmx.ch>
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
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;
use League\Csv\CannotInsertRecord;
use League\Csv\InvalidArgument;
use League\Csv\Reader;
use League\Csv\Writer;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ExportEventRegistrationList
{
    private const DELIMITER = ';';
    private const FIELDS = [
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
        'mobile',
        'email',
        'emergencyPhone',
        'emergencyPhoneName',
        'hasParticipated',
    ];

    private ContaoFramework $framework;
    private Connection $connection;
    private string $sacevtEventMemberListFileNamePattern;

    // Adapters
    private Adapter $configAdapter;
    private Adapter $controllerAdapter;

    public function __construct(ContaoFramework $framework, Connection $connection, string $sacevtEventMemberListFileNamePattern)
    {
        $this->framework = $framework;
        $this->connection = $connection;
        $this->sacevtEventMemberListFileNamePattern = $sacevtEventMemberListFileNamePattern;

        // Adapters
        $this->configAdapter = $this->framework->getAdapter(Config::class);
        $this->controllerAdapter = $this->framework->getAdapter(Controller::class);
    }

    /**
     * @throws CannotInsertRecord
     * @throws InvalidArgument
     * @throws Exception
     */
    public function generate(CalendarEventsModel $event): Response
    {
        // Create empty document
        $csv = Writer::createFromString();

        $csv->setOutputBOM(Reader::BOM_UTF8);
        $csv->setDelimiter(self::DELIMITER);

        // Load translation
        $this->controllerAdapter->loadLanguageFile('tl_calendar_events_member');

        // Insert headline
        $arrHeadline = array_map(
            static fn ($field) => $GLOBALS['TL_LANG']['tl_calendar_events_member'][$field][0] ?? $field,
            self::FIELDS
        );

        $csv->insertOne($arrHeadline);

        $result = $this->connection->executeQuery('SELECT * FROM tl_calendar_events_member WHERE eventId = ? ORDER BY lastname, firstname', [$event->id]);

        while (false !== ($arrRegistration = $result->fetchAssociative())) {
            $arrRow = [];

            foreach (self::FIELDS as $field) {
                $value = html_entity_decode((string) $arrRegistration[$field]);

                if ('stateOfSubscription' === $field) {
                    $arrRow[] = $GLOBALS['TL_LANG']['MSC'][$value] ?? $value;
                } elseif ('gender' === $field) {
                    $arrRow[] = $GLOBALS['TL_LANG']['MSC'][$value] ?? $value;
                } elseif ('dateAdded' === $field) {
                    $arrRow[] = date($this->configAdapter->get('datimFormat'), (int) $value);
                } elseif ('dateOfBirth' === $field) {
                    $arrRow[] = date($this->configAdapter->get('dateFormat'), (int) $value);
                } else {
                    $arrRow[] = $value;
                }
            }

            $csv->insertOne($arrRow);
        }

        // Sanitize event title
        $eventTitle = preg_replace('/[^a-zA-Z0-9_-]+/', '_', strtolower($event->title));

        // Generate the file name
        $filename = sprintf($this->sacevtEventMemberListFileNamePattern, $eventTitle, 'csv');

        // Sent the file to the browser.
        $response = new StreamedResponse(
            static function () use ($csv, $filename): void {
                $csv->output($filename);
            }
        );

        $response->headers->set('Content-Type', 'application/vnd.ms-excel');
        $response->headers->set('Content-Disposition', 'attachment;filename="'.$filename.'"');
        $response->headers->set('Cache-Control', 'max-age=0');

        return $response->send();
    }
}
