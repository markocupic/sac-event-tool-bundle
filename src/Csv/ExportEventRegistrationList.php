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
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Driver\Exception;
use League\Csv\CannotInsertRecord;
use League\Csv\CharsetConverter;
use League\Csv\InvalidArgument;
use League\Csv\Writer;
use Symfony\Component\HttpFoundation\Response;

class ExportEventRegistrationList
{
    private const OUTPUT_ENCODING = 'iso-8859-15';

    private const DELIMITER = ';';

    private Connection $connection;

    private array $fields = ['id', 'stateOfSubscription', 'addedOn', 'carInfo', 'ticketInfo', 'notes', 'instructorNotes', 'bookingType', 'sacMemberId', 'ahvNumber', 'firstname', 'lastname', 'gender', 'dateOfBirth', 'foodHabits', 'street', 'postal', 'city', 'mobile', 'email', 'emergencyPhone', 'emergencyPhoneName', 'hasParticipated'];

    public function __construct(Connection $connection)
    {
        $this->connection = $connection;
    }

    /**
     * @throws Exception
     * @throws \Doctrine\DBAL\Exception
     * @throws CannotInsertRecord
     * @throws InvalidArgument
     */
    public function generate(CalendarEventsModel $event): void
    {
        // Create empty document
        $csv = Writer::createFromString('');

        $encoder = (new CharsetConverter())
            ->outputEncoding(self::OUTPUT_ENCODING)
        ;

        $csv->addFormatter($encoder);

        $csv->setDelimiter(self::DELIMITER);

        $arrFields = $this->fields;

        Controller::loadLanguageFile('tl_calendar_events_member');

        // Insert headline
        $arrHeadline = array_map(
            static fn ($field) => $GLOBALS['TL_LANG']['tl_calendar_events_member'][$field][0] ?? $field,
            $arrFields
        );

        $csv->insertOne($arrHeadline);

        $stmt = $this->connection->executeQuery('SELECT * FROM tl_calendar_events_member WHERE eventId = ? ORDER BY lastname, firstname', [$event->id]);

        while (false !== ($arrRegistration = $stmt->fetchAssociative())) {
            $arrRow = [];

            foreach ($arrFields as $field) {
                $value = html_entity_decode((string) $arrRegistration[$field]);

                if ('stateOfSubscription' === $field) {
                    $arrRow[] = '' !== $GLOBALS['TL_LANG']['tl_calendar_events_member'][$value] ? $GLOBALS['TL_LANG']['tl_calendar_events_member'][$value] : $value;
                } elseif ('gender' === $field) {
                    $arrRow[] = '' !== $GLOBALS['TL_LANG']['MSC'][$value] ? $GLOBALS['TL_LANG']['MSC'][$value] : $value;
                } elseif ('addedOn' === $field) {
                    $arrRow[] = date(Config::get('datimFormat'), (int) $value);
                } elseif ('dateOfBirth' === $field) {
                    $arrRow[] = date(Config::get('dateFormat'), (int) $value);
                } else {
                    $arrRow[] = $value;
                }
            }

            $csv->insertOne($arrRow);
        }

        // Sanitize filename
        $eventTitle = preg_replace('/[^a-zA-Z0-9_-]+/', '_', strtolower($event->title));

        $filenamePattern = str_replace('%%s', '%s', Config::get('SAC_EVT_EVENT_MEMBER_LIST_FILE_NAME_PATTERN'));
        $filename = sprintf($filenamePattern, $eventTitle, 'csv');

        // Output
        $csv->output($filename);

        throw new ResponseException(new Response(''));
    }
}
