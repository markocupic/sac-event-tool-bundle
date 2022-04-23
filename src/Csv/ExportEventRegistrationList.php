<?php

declare(strict_types=1);

/*
 * This file is part of SAC Event Tool Bundle.
 *
 * (c) Marko Cupic 2022 <m.cupic@gmx.ch>
 * @license GPL-3.0-or-later
 * For the full copyright and license information,
 * please view the LICENSE file that was distributed with this source code.
 * @link https://github.com/markocupic/sac-event-tool-bundle
 */

namespace Markocupic\SacEventToolBundle\Csv;

use Contao\CalendarEventsModel;
use Contao\Config;
use Contao\Controller;
use Contao\CoreBundle\Exception\ResponseException;
use Contao\CoreBundle\Framework\Adapter;
use Contao\CoreBundle\Framework\ContaoFramework;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;
use League\Csv\CannotInsertRecord;
use League\Csv\InvalidArgument;
use League\Csv\Reader;
use League\Csv\Writer;
use Symfony\Component\HttpFoundation\Response;

class ExportEventRegistrationList
{
    private const DELIMITER = ';';
    private const FIELDS = [
        'id',
        'stateOfSubscription',
        'addedOn',
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
    private string $eventMemberListFileNamePattern;

    // Adapters
    private Adapter $configAdapter;
    private Adapter $controllerAdapter;

    public function __construct(ContaoFramework $framework, Connection $connection, string $eventMemberListFileNamePattern)
    {
        $this->framework = $framework;
        $this->connection = $connection;
        $this->eventMemberListFileNamePattern = $eventMemberListFileNamePattern;

        // Adapters
        $this->configAdapter = $this->framework->getAdapter(Config::class);
        $this->controllerAdapter = $this->framework->getAdapter(Controller::class);
    }

    /**
     * @throws CannotInsertRecord
     * @throws InvalidArgument
     * @throws Exception
     */
    public function generate(CalendarEventsModel $event): void
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
                } elseif ('addedOn' === $field) {
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
        $filename = sprintf($this->eventMemberListFileNamePattern, $eventTitle, 'csv');

        // Output
        $csv->output($filename);

        throw new ResponseException(new Response(''));
    }
}
