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

namespace Markocupic\SacEventToolBundle\DataContainer;

use Contao\ArrayUtil;
use Contao\Calendar;
use Contao\CalendarEventsModel;
use Contao\CalendarModel;
use Contao\Config;
use Contao\Controller;
use Contao\CoreBundle\DataContainer\PaletteManipulator;
use Contao\CoreBundle\DependencyInjection\Attribute\AsCallback;
use Contao\CoreBundle\Exception\AccessDeniedException;
use Contao\CoreBundle\Exception\ResponseException;
use Contao\CoreBundle\Framework\Adapter;
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\Database;
use Contao\DataContainer;
use Contao\Date;
use Contao\FilesModel;
use Contao\Idna;
use Contao\Image;
use Contao\Message;
use Contao\StringUtil;
use Contao\System;
use Contao\UserModel;
use Contao\Versions;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;
use Doctrine\DBAL\ParameterType;
use Doctrine\DBAL\Types\Types;
use Markocupic\SacEventToolBundle\Config\CourseLevels;
use Markocupic\SacEventToolBundle\Config\EventDurationInfo;
use Markocupic\SacEventToolBundle\Config\EventState;
use Markocupic\SacEventToolBundle\Config\EventType;
use Markocupic\SacEventToolBundle\DataContainer\EventReleaseLevel\EventReleaseLevelChangeNotifier;
use Markocupic\SacEventToolBundle\DataContainer\EventReleaseLevel\EventReleaseLevelUtil;
use Markocupic\SacEventToolBundle\DataContainer\EventReleaseLevel\Exception\EventReleaseLevelTransitionException;
use Markocupic\SacEventToolBundle\Download\CsvDownload;
use Markocupic\SacEventToolBundle\Model\CalendarEventsJourneyModel;
use Markocupic\SacEventToolBundle\Model\EventReleaseLevelPolicyModel;
use Markocupic\SacEventToolBundle\Model\EventReleaseLevelPolicyPackageModel;
use Markocupic\SacEventToolBundle\Model\TourDifficultyCategoryModel;
use Markocupic\SacEventToolBundle\String\Normalizer\SwisstopoLV95Normalizer;
use Markocupic\SacEventToolBundle\String\Validator\DateValidator;
use Markocupic\SacEventToolBundle\String\Validator\SwisstopoLV95Validator;
use Markocupic\SacEventToolBundle\Util\CalendarEventsUtil;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * DCA callbacks for tl_calendar_events: palettes and filters, CSV export, date shift,
 * defaults on create/copy, consistency on submit, field callbacks and the list view.
 *
 * Access checks are in AccessDecision\CalendarEvents.
 */
class CalendarEvents
{
    private const string TABLE = 'tl_calendar_events';

    /**
     * Filters that non-admins can use in the list view.
     */
    private const array FILTERS_FOR_NON_ADMINS = [
        'mountainguide',
        'author',
        'organizers',
        'tourType',
        'journey',
        'eventReleaseLevel',
        'mainInstructor',
        'courseTypeLevel0',
        'startTime',
    ];

    /**
     * Palettes that contain the field "rescheduledEventDate".
     */
    private const array PALETTES = ['default', 'tour', 'lastMinuteTour', 'course', 'generalEvent', 'tour_report'];

    /**
     * Columns of the CSV export (in this order).
     */
    private const array CSV_EXPORT_FIELDS = ['id', 'title', 'location', 'eventDates', 'eventDurationInDays', 'published', 'organizers', 'mountainguide', 'mainInstructor', 'instructor', 'instructorNotes', 'minMembers', 'maxMembers', 'executionState', 'eventState', 'eventType', 'courseLevel', 'courseTypeLevel0', 'courseTypeLevel1', 'tourType', 'tourTechDifficulty', 'eventReleaseLevel', 'journey', 'teaser', 'tourDetailText', 'requirements', 'leistungen'];

    /**
     * Text fields whose line breaks are replaced by spaces in the CSV export.
     */
    private const array CSV_EXPORT_TEXT_FIELDS = ['teaser', 'instructorNotes', 'tourDetailText', 'requirements', 'leistungen'];

    private const array DATE_SORTING_FLAGS = [DataContainer::SORT_DAY_ASC, DataContainer::SORT_DAY_DESC, DataContainer::SORT_MONTH_ASC, DataContainer::SORT_MONTH_DESC, DataContainer::SORT_YEAR_ASC, DataContainer::SORT_YEAR_DESC];

    // Adapters
    private Adapter $arrayUtil;

    private Adapter $calendar;

    private Adapter $calendarEventsJourneyModel;

    private Adapter $calendarEventsModel;

    private Adapter $calendarModel;

    private Adapter $config;

    private Adapter $controller;

    private Adapter $date;

    private Adapter $eventReleaseLevelPolicyModel;

    private Adapter $eventReleaseLevelPolicyPackageModel;

    private Adapter $filesModel;

    private Adapter $idna;

    private Adapter $image;

    private Adapter $message;

    private Adapter $stringUtil;

    private Adapter $system;

    private Adapter $tourDifficultyCategoryModel;

    private Adapter $userModel;

    /**
     * Release level changes of the edit form that have passed the validation, see
     * saveCallbackEventReleaseLevel().
     *
     * @var array<int, array{previousLevelId: int, levelId: int}>
     */
    private array $releaseLevelChanges = [];

    public function __construct(
        private readonly CalendarEventsUtil $calendarEventsUtil,
        private readonly Connection $connection,
        private readonly ContaoFramework $framework,
        private readonly CourseLevels $courseLevels,
        private readonly EventDurationInfo $eventDurationInfo,
        private readonly EventReleaseLevelChangeNotifier $eventReleaseLevelChangeNotifier,
        private readonly EventReleaseLevelUtil $eventReleaseLevelUtil,
        private readonly RequestStack $requestStack,
        private readonly Security $security,
        private readonly TranslatorInterface $translator,
        private readonly string $sacevtEventRegistrationConfigEmailAcceptCustomTemplPath,
    ) {
        // Adapters
        $this->arrayUtil = $this->framework->getAdapter(ArrayUtil::class);
        $this->calendar = $this->framework->getAdapter(Calendar::class);
        $this->calendarEventsJourneyModel = $this->framework->getAdapter(CalendarEventsJourneyModel::class);
        $this->calendarEventsModel = $this->framework->getAdapter(CalendarEventsModel::class);
        $this->calendarModel = $this->framework->getAdapter(CalendarModel::class);
        $this->config = $this->framework->getAdapter(Config::class);
        $this->controller = $this->framework->getAdapter(Controller::class);
        $this->date = $this->framework->getAdapter(Date::class);
        $this->eventReleaseLevelPolicyModel = $this->framework->getAdapter(EventReleaseLevelPolicyModel::class);
        $this->eventReleaseLevelPolicyPackageModel = $this->framework->getAdapter(EventReleaseLevelPolicyPackageModel::class);
        $this->filesModel = $this->framework->getAdapter(FilesModel::class);
        $this->idna = $this->framework->getAdapter(Idna::class);
        $this->image = $this->framework->getAdapter(Image::class);
        $this->message = $this->framework->getAdapter(Message::class);
        $this->stringUtil = $this->framework->getAdapter(StringUtil::class);
        $this->system = $this->framework->getAdapter(System::class);
        $this->tourDifficultyCategoryModel = $this->framework->getAdapter(TourDifficultyCategoryModel::class);
        $this->userModel = $this->framework->getAdapter(UserModel::class);
    }

    /**
     * A new event only shows the field "eventType" until the event type is set.
     */
    #[AsCallback(table: 'tl_calendar_events', target: 'config.onload', priority: 90)]
    public function setPaletteWhenCreatingNew(DataContainer $dc): void
    {
        $request = $this->requestStack->getCurrentRequest();

        if ('edit' !== $request->query->get('act')) {
            return;
        }

        $event = $this->calendarEventsModel->findById($dc->id);

        if (null !== $event && 0 === (int) $event->tstamp && empty($event->eventType)) {
            $GLOBALS['TL_DCA'][self::TABLE]['palettes']['default'] = 'eventType';
        }
    }

    /**
     * Reduce the filter fields for non-admins and hide the filters that do not match
     * the event types of the calendar.
     */
    #[AsCallback(table: 'tl_calendar_events', target: 'config.onload', priority: 80)]
    public function adjustFilterSearchAndSortingBoard(DataContainer $dc): void
    {
        if (!$this->security->isGranted('ROLE_ADMIN')) {
            foreach (array_keys($GLOBALS['TL_DCA'][self::TABLE]['fields']) as $field) {
                if (!\in_array($field, self::FILTERS_FOR_NON_ADMINS, true)) {
                    $GLOBALS['TL_DCA'][self::TABLE]['fields'][$field]['filter'] = null;
                }
            }
        }

        if (!$dc->currentPid) {
            return;
        }

        $calendar = $this->calendarModel->findById($dc->currentPid);

        if (null === $calendar) {
            return;
        }

        $allowedEventTypes = $this->stringUtil->deserialize($calendar->allowedEventTypes, true);

        if (!\in_array(EventType::TOUR, $allowedEventTypes, true) && !\in_array(EventType::LAST_MINUTE_TOUR, $allowedEventTypes, true)) {
            $this->disableFilterSearchAndSorting('tourType');
        }

        if (!\in_array(EventType::COURSE, $allowedEventTypes, true)) {
            $this->disableFilterSearchAndSorting('courseTypeLevel0', 'courseTypeLevel1', 'courseLevel');
        }
    }

    /**
     * @throws Exception
     */
    #[AsCallback(table: 'tl_calendar_events', target: 'config.onload', priority: 70)]
    public function onloadCallbackDeleteInvalidEvents(DataContainer $dc): void
    {
        // Events that have been created more than a day ago, but never saved with a title
        $this->connection->executeStatement(
            'DELETE FROM tl_calendar_events WHERE tstamp < ? AND tstamp > ? AND title = ?',
            [time() - 86400, 0, ''],
        );
    }

    /**
     * Set the palette for the event type (tour, course, etc.) and for the tour report.
     */
    #[AsCallback(table: 'tl_calendar_events', target: 'config.onload', priority: 50)]
    public function setPalettes(DataContainer $dc): void
    {
        $request = $this->requestStack->getCurrentRequest();

        if (!$dc->id) {
            return;
        }

        if ('editAll' === $request->query->get('act') || 'overrideAll' === $request->query->get('act')) {
            return;
        }

        $event = $this->calendarEventsModel->findById($dc->id);

        if (null === $event) {
            return;
        }

        // Palette of the event type
        if (isset($GLOBALS['TL_DCA'][self::TABLE]['palettes'][$event->eventType])) {
            $GLOBALS['TL_DCA'][self::TABLE]['palettes']['default'] = $GLOBALS['TL_DCA'][self::TABLE]['palettes'][$event->eventType];
        }

        // The field "rescheduledEventDate" is only shown for rescheduled events
        if (EventState::STATE_RESCHEDULED !== $event->eventState) {
            foreach (self::PALETTES as $palette) {
                PaletteManipulator::create()
                    ->removeField('rescheduledEventDate')
                    ->applyToPalette($palette, self::TABLE)
                ;
            }
        }

        // Palette of the tour report
        if ('writeTourReport' === $request->query->get('call')) {
            $GLOBALS['TL_DCA'][self::TABLE]['palettes']['default'] = $GLOBALS['TL_DCA'][self::TABLE]['palettes']['tour_report'];
        }
    }

    /**
     * CSV export of the events of a calendar: contao?do=calendar&table=tl_calendar_events&id=<calendar id>&action=onloadCallbackExportCalendar.
     */
    #[AsCallback(table: 'tl_calendar_events', target: 'config.onload', priority: 40)]
    public function exportCalendar(DataContainer $dc): void
    {
        $request = $this->requestStack->getCurrentRequest();

        if ('onloadCallbackExportCalendar' !== $request->query->get('action') || !($request->query->get('id') > 0)) {
            return;
        }

        $csv = new CsvDownload();
        $csv->convertOutputEncoding(CsvDownload::ENCODING_ISO_8859_1);

        // Headline
        $this->controller->loadLanguageFile(self::TABLE);

        $csv->setHeadline(array_map(
            static fn ($field) => $GLOBALS['TL_LANG']['tl_calendar_events'][$field][0] ?? $field,
            self::CSV_EXPORT_FIELDS,
        ));

        $events = $this->calendarEventsModel->findBy(
            ['tl_calendar_events.pid = ?'],
            [$request->query->get('id')],
            ['order' => 'tl_calendar_events.startDate ASC'],
        );

        if (null !== $events) {
            while ($events->next()) {
                $row = [];

                foreach (self::CSV_EXPORT_FIELDS as $field) {
                    $row[] = $this->getCsvValue($events->current(), $field);
                }

                $row = array_map(fn ($value) => $this->stringUtil->revertInputEncoding((string) $value), $row);

                $csv->addRecord($row);
            }
        }

        $calendar = $this->calendarModel->findById($request->query->get('id'));

        $fileName = $this->stringUtil->revertInputEncoding($calendar->title).'.csv';
        $fileName = $this->stringUtil->sanitizeFileName($fileName);

        throw new ResponseException($csv->createStreamedResponse($fileName)->send());
    }

    /**
     * Shift all event dates of a calendar by +/- 52 weeks:
     * contao?do=calendar&table=tl_calendar_events&id=<calendar id>&transformDates=plus52weeks|minus52weeks.
     *
     * @throws Exception
     */
    #[AsCallback(table: 'tl_calendar_events', target: 'config.onload', priority: 30)]
    public function shiftEventDates(DataContainer $dc): void
    {
        $request = $this->requestStack->getCurrentRequest();

        $transform = $request->query->get('transformDates');

        if (!$transform) {
            return;
        }

        if (!\in_array($transform, ['plus52weeks', 'minus52weeks'], true)) {
            throw new \InvalidArgumentException('Invalid transform mode. Use "plus52weeks" or "minus52weeks"!');
        }

        // Only admins see the global operations "plus1year" and "minus1year" (see AccessDecision\CalendarEvents)
        if (!$this->security->isGranted('ROLE_ADMIN')) {
            throw new AccessDeniedException('Only admins are allowed to shift the event dates of a calendar.');
        }

        $modifier = 'plus52weeks' === $transform ? '+52 weeks' : '-52 weeks';

        $rows = $this->connection->fetchAllAssociative('SELECT * FROM tl_calendar_events WHERE pid = ?', [$request->query->get('id')]);

        foreach ($rows as $row) {
            $set = $this->getShiftedDates($row, $modifier);

            // Skip events with invalid event dates
            if (null === $set) {
                continue;
            }

            $affected = $this->connection->update(self::TABLE, $set, ['id' => $row['id']]);

            if ($affected > 0) {
                $versions = new Versions(self::TABLE, $row['id']);
                $versions->create();
            }
        }

        $this->controller->redirect($this->system->getReferer());
    }

    #[AsCallback(table: 'tl_calendar_events', target: 'config.onsubmit')]
    public function validateAutoConfirm(DataContainer $dc): void
    {
        if (!$dc->id) {
            return;
        }

        $record = $dc->getCurrentRecord();

        if (empty($record)) {
            return;
        }

        // Registrations cannot be confirmed automatically if an IBAN is required
        if ($record['autoConfirm'] && $record['addIban']) {
            $this->connection->update(self::TABLE, ['autoConfirm' => 0], ['id' => $dc->id]);
            $this->message->addError($this->translator->trans('ERR.autoConfirm_and_addIban_not_allowed', [], 'contao_default'));
        }
    }

    /**
     * Defaults of a new event: source, author, main instructor and the text of the
     * custom registration confirmation email.
     */
    #[AsCallback(table: 'tl_calendar_events', target: 'config.oncreate', priority: 100)]
    public function onCreate(string $strTable, int $insertId, array $set, DataContainer $dc): void
    {
        $user = $this->security->getUser();

        $event = $this->calendarEventsModel->findById($insertId);

        if (null === $event) {
            return;
        }

        $event->source = 'default';
        $event->author = $user->id;
        $event->mainInstructor = $user->id;
        $event->instructor = serialize([['instructorId' => $user->id]]);
        $event->customEventRegistrationConfirmationEmailText = file_get_contents($this->sacevtEventRegistrationConfigEmailAcceptCustomTemplPath);
        $event->save();

        // The entry in tl_calendar_events_instructor makes the event appear in the "My
        // Events Dashboard" in the backend.
        $this->connection->insert('tl_calendar_events_instructor', [
            'pid' => $insertId,
            'userId' => $user->id,
            'isMainInstructor' => 1,
            'tstamp' => time(),
        ]);
    }

    /**
     * A copied event gets the current user as author and the first release level.
     *
     * @throws \Exception
     */
    #[AsCallback(table: 'tl_calendar_events', target: 'config.oncopy', priority: 100)]
    public function onCopy(int $insertId, DataContainer $dc): void
    {
        $user = $this->security->getUser();

        $event = $this->calendarEventsModel->findById($insertId);

        if (null === $event) {
            return;
        }

        $event->author = $user->id;
        $event->alias = 'event-'.$event->id;
        $event->save();

        if ('' === $event->eventType) {
            return;
        }

        $firstLevel = $this->eventReleaseLevelPolicyModel->findMinLevelByEventId($event->id);

        if (null !== $firstLevel) {
            $event->eventReleaseLevel = $firstLevel->id;
            $event->save();
        }
    }

    /**
     * Set startDate/startTime and endDate/endTime from the event dates.
     *
     * Priority -100: runs after the legacy callback tl_calendar_events.adjustTime()
     * and before self::adjustRegistrationPeriod() (priority -110).
     *
     * @throws Exception
     */
    #[AsCallback(table: 'tl_calendar_events', target: 'config.onsubmit', priority: -100)]
    public function adjustStartAndEndDate(DataContainer $dc): void
    {
        // No active record in the overrideAll mode
        if (!$dc->activeRecord) {
            return;
        }

        $eventDates = $this->connection->fetchOne('SELECT eventDates FROM tl_calendar_events WHERE id = ?', [$dc->id]);

        $repeats = $this->stringUtil->deserialize($eventDates, true);

        if (empty($repeats)) {
            return;
        }

        $timestamps = [];

        foreach ($repeats as $repeat) {
            $timestamp = $repeat['new_repeat'] ?? null;

            if (!DateValidator::isValidTimestamp($timestamp)) {
                $this->message->addError($this->translator->trans('ERR.eventDatesInvalid', [$dc->id], 'contao_default'));
                continue;
            }

            $timestamps[] = (int) $timestamp;
        }

        $timestamps = array_unique($timestamps);

        sort($timestamps);

        $firstTimestamp = array_first($timestamps);
        $lastTimestamp = array_last($timestamps);

        $startTime = $firstTimestamp > 0 ? $firstTimestamp : 0;
        $endTime = $lastTimestamp > 0 ? $lastTimestamp : 0;

        $set = [
            'startDate' => $startTime,
            'startTime' => $startTime,
            'endDate' => $endTime,
            'endTime' => $endTime,
        ];

        $affected = $this->connection->update(self::TABLE, $set, ['id' => $dc->activeRecord->id]);

        if ($affected > 0) {
            DataContainer::clearCurrentRecordCache($dc->id, self::TABLE);
        }
    }

    /**
     * The registration period must end before the event starts.
     *
     * Priority -110: runs after the legacy callback tl_calendar_events.adjustTime()
     * and after self::adjustStartAndEndDate() (priority -100).
     *
     * @throws Exception
     */
    #[AsCallback(table: 'tl_calendar_events', target: 'config.onsubmit', priority: -110)]
    public function adjustRegistrationPeriod(DataContainer $dc): void
    {
        // No active record in the overrideAll mode
        if (!$dc->activeRecord) {
            return;
        }

        $row = $this->connection->fetchAssociative('SELECT * FROM tl_calendar_events WHERE id = ?', [$dc->activeRecord->id]);

        if (!$row || !$row['setRegistrationPeriod'] || !$row['startDate']) {
            return;
        }

        $regEndDate = $row['registrationEndDate'];
        $regStartDate = $row['registrationStartDate'];

        if ($regEndDate > $row['startDate']) {
            // End of the day before the event starts
            $regEndDate = strtotime(date('Y-m-d', (int) $row['startDate']).' +1 day') - 1;
            $this->message->addInfo($GLOBALS['TL_LANG']['MSC']['patchedEndDatePleaseCheck']);
        }

        if ($regStartDate > $regEndDate) {
            $regStartDate = $regEndDate - 86400;
            $this->message->addInfo($GLOBALS['TL_LANG']['MSC']['patchedStartDatePleaseCheck']);
        }

        $set = [
            'registrationStartDate' => $regStartDate,
            'registrationEndDate' => $regEndDate,
        ];

        $dc->activeRecord->registrationStartDate = $regStartDate;
        $dc->activeRecord->registrationEndDate = $regEndDate;

        $this->connection->update(self::TABLE, $set, ['id' => $row['id']]);
    }

    /**
     * Events without release level get the first release level.
     *
     * @throws Exception
     */
    #[AsCallback(table: 'tl_calendar_events', target: 'config.onsubmit', priority: 80)]
    public function adjustEventReleaseLevel(DataContainer $dc): void
    {
        // No active record in the overrideAll mode
        if (!$dc->activeRecord) {
            return;
        }

        if ($dc->activeRecord->eventReleaseLevel) {
            return;
        }

        $firstLevel = $this->eventReleaseLevelPolicyModel->findMinLevelByEventId($dc->activeRecord->id);

        if (null !== $firstLevel) {
            $dc->activeRecord->eventReleaseLevel = $firstLevel->id;
            $this->connection->update(self::TABLE, ['eventReleaseLevel' => $firstLevel->id], ['id' => $dc->activeRecord->id]);
        }
    }

    /**
     * After the tour report has been saved, the invoice form can be printed in
     * tl_calendar_events_instructor_invoice.
     *
     * @throws Exception
     */
    #[AsCallback(table: 'tl_calendar_events', target: 'config.onsubmit', priority: 40)]
    public function setTheFilledInReportFormAsDone(DataContainer $dc): void
    {
        $request = $this->requestStack->getCurrentRequest();

        if ('writeTourReport' === $request->query->get('call')) {
            $this->connection->update(self::TABLE, ['filledInEventReportForm' => 1], ['id' => $dc->activeRecord->id]);
        }
    }

    /**
     * @throws Exception
     */
    #[AsCallback(table: 'tl_calendar_events', target: 'config.onsubmit', priority: 30)]
    public function setAlias(DataContainer $dc): void
    {
        $this->connection->update(self::TABLE, ['alias' => 'event-'.$dc->id], ['id' => $dc->activeRecord->id]);
    }

    /**
     * The release level must belong to the release level package of the event type.
     *
     * @throws Exception
     */
    #[AsCallback(table: 'tl_calendar_events', target: 'config.onsubmit', priority: 20)]
    public function setValidEventReleaseLevel(DataContainer $dc): void
    {
        $event = $this->calendarEventsModel->findById($dc->activeRecord->id);

        if (null === $event || '' === $event->eventType) {
            return;
        }

        if ($event->eventReleaseLevel > 0) {
            $currentLevel = $this->eventReleaseLevelPolicyModel->findById($event->eventReleaseLevel);

            if (null === $currentLevel) {
                return;
            }

            $package = $this->eventReleaseLevelPolicyPackageModel->findReleaseLevelPolicyPackageModelByEventId($event->id);

            // The event type has been changed: use the first release level of the new package
            if ($currentLevel->pid !== $package->id) {
                $firstLevel = $this->eventReleaseLevelPolicyModel->findMinLevelByEventId($event->id);

                if (null !== $firstLevel) {
                    $this->connection->update(self::TABLE, ['eventReleaseLevel' => $firstLevel->id], ['id' => $event->id]);
                }
            }

            return;
        }

        // New event: set the first release level
        $firstLevel = $this->eventReleaseLevelPolicyModel->findMinLevelByEventId($event->id);

        if (null === $firstLevel) {
            return;
        }

        $dc->activeRecord->eventReleaseLevel = $firstLevel->id;

        $this->connection->update(self::TABLE, ['eventReleaseLevel' => $firstLevel->id], ['id' => $event->id]);
    }

    /**
     * Only shows the value of the field instead of the form widget. Is used if the
     * field cannot be edited because the release level (FS) is too high (see
     * AccessDecision\CalendarEvents).
     *
     * @throws Exception
     */
    #[AsCallback(table: 'tl_calendar_events', target: 'fields.alias.input_field', priority: 100)]
    public function showFieldValue(DataContainer $dc): string
    {
        $fieldName = $dc->field;

        // Do not show the field in the overrideAll mode
        if (!$dc->activeRecord->id) {
            return '';
        }

        $value = $this->connection->fetchOne('SELECT '.$fieldName.' FROM tl_calendar_events WHERE id = ?', [$dc->activeRecord->id]);

        if (false === $value) {
            return '';
        }

        $dcaFields = \is_array($GLOBALS['TL_DCA'][self::TABLE]['fields'] ?? []) ? $GLOBALS['TL_DCA'][self::TABLE]['fields'] : [];
        $allowedFields = array_unique(array_merge(['id', 'pid', 'sorting', 'tstamp'], array_keys($dcaFields)));

        if (!\in_array($fieldName, $allowedFields, true)) {
            return '';
        }

        $dcaField = $dcaFields[$fieldName] ?? [];

        [$label, $help] = $this->getFieldLabel($fieldName, $dcaField);

        if ('' !== $help) {
            $help = '<p class="tl_help tl_tip tl_full_height">'.$help.'</p>';
        }

        // Hidden and encrypted values are never shown
        if (($dcaField['eval']['hideInput'] ?? false) || ($dcaField['eval']['encrypt'] ?? false)) {
            $value = '********';
        } else {
            $value = $this->formatFieldValue($fieldName, $this->stringUtil->deserialize($value), $dcaField);
        }

        $markup = '<div class="clr readonly">
			<h3><label>%s</label></h3>
    		<div class="field-content-box" data-field="%s">%s</div>
			%s
		</div>';

        return \sprintf($markup, $label, $this->stringUtil->specialchars($fieldName), $value, $help);
    }

    #[AsCallback(table: 'tl_calendar_events', target: 'fields.eventDates.load', priority: 100)]
    public function sanitizeEventDates(string|null $varValue, DataContainer $dc): string
    {
        $repeats = $this->stringUtil->deserialize($varValue, true);

        $firstTimestamp = $repeats[0]['new_repeat'] ?? null;

        if (null === $firstTimestamp || $firstTimestamp < 0 || !DateValidator::isValidTimestamp($firstTimestamp)) {
            $repeats = [];
        }

        return serialize($repeats);
    }

    /**
     * The tour report form can only be saved (and closed).
     */
    #[AsCallback(table: 'tl_calendar_events', target: 'edit.buttons', priority: 100)]
    public function editButtons(array $arrButtons, DataContainer $dc): array
    {
        $request = $this->requestStack->getCurrentRequest();

        if ('writeTourReport' === $request->query->get('call')) {
            unset($arrButtons['saveNcreate'], $arrButtons['saveNduplicate'], $arrButtons['saveNedit']);
        }

        return $arrButtons;
    }

    #[AsCallback(table: 'tl_calendar_events', target: 'fields.durationInfo.options', priority: 100)]
    public function getEventDuration(): array
    {
        return array_keys($this->eventDurationInfo->getAll());
    }

    /**
     * @throws Exception
     */
    #[AsCallback(table: 'tl_calendar_events', target: 'fields.organizers.options', priority: 90)]
    public function getEventOrganizers(): array
    {
        return $this->connection->fetchAllKeyValue('SELECT id,title FROM tl_event_organizer ORDER BY sorting');
    }

    /**
     * @throws Exception
     */
    #[AsCallback(table: 'tl_calendar_events', target: 'fields.courseTypeLevel0.options', priority: 80)]
    public function getCourseSuperCategory(): array
    {
        return $this->connection->fetchAllKeyValue('SELECT id,name FROM tl_course_main_type ORDER BY code');
    }

    #[AsCallback(table: 'tl_calendar_events', target: 'fields.registrationGoesTo.options', priority: 90)]
    public function getBackendUsers(): array
    {
        return $this->connection->fetchAllKeyValue(
            'SELECT id, CONCAT(name, ", ", city) FROM tl_user WHERE disable = 0 AND (stop = "" OR stop > ?) ORDER BY name',
            [time()],
            [Types::INTEGER],
        );
    }

    /**
     * Options callback for the Multi Column Wizard field tl_calendar_events.tourTechDifficulty,
     * grouped by difficulty category.
     *
     * @throws Exception
     */
    public function getTourDifficulties(): array
    {
        $options = [];
        $result = $this->connection->executeQuery('SELECT * FROM tl_tour_difficulty ORDER BY pid, code');

        while (false !== ($row = $result->fetchAssociative())) {
            $category = $this->tourDifficultyCategoryModel->findById($row['pid']);

            if (null === $category || '' === $category->title) {
                continue;
            }

            $options[$category->title] ??= [];
            $options[$category->title][$row['id']] = $row['shortcut'];
        }

        return $options;
    }

    /**
     * Options callback for the Multi Column Wizard field tl_calendar_events.instructor.
     *
     * @throws Exception
     */
    public function listInstructors(): array
    {
        $options = [];
        $result = $this->connection->executeQuery(
            'SELECT id,firstname,lastname,city FROM tl_user WHERE disable = 0 AND (stop = "" OR stop > ?) && lastname != "" && firstname != "" ORDER BY lastname',
            [time()],
            [Types::INTEGER],
        );

        while (false !== ($row = $result->fetchAssociative())) {
            $options[$row['id']] = $row['lastname'].' '.$row['firstname'].', '.$row['city'];
        }

        return $options;
    }

    /**
     * The event types allowed in the calendar.
     *
     * @throws \Exception
     */
    #[AsCallback(table: 'tl_calendar_events', target: 'fields.eventType.options', priority: 70)]
    public function getEventTypes(DataContainer|null $dc): array
    {
        if (!$dc) {
            return [];
        }

        $calendar = null;

        if (!$dc->id && $dc->currentPid > 0) {
            $calendar = $this->calendarModel->findById($dc->currentPid);
        } elseif ($dc->id > 0) {
            $calendar = $this->calendarEventsModel->findById($dc->id)?->getRelated('pid');
        }

        if (null === $calendar) {
            return [];
        }

        return $this->stringUtil->deserialize($calendar->allowedEventTypes, true);
    }

    /**
     * @throws Exception
     */
    #[AsCallback(table: 'tl_calendar_events', target: 'fields.courseTypeLevel1.options', priority: 60)]
    public function getCourseSubCategory(DataContainer $dc): array
    {
        $options = [];

        $courseTypeLevel0 = $this->connection->fetchOne('SELECT courseTypeLevel0 FROM tl_calendar_events WHERE id = ?', [$dc->id]);

        if (!$courseTypeLevel0) {
            return $options;
        }

        $result = $this->connection->executeQuery('SELECT * FROM tl_course_sub_type WHERE pid = ? ORDER BY pid, code', [$courseTypeLevel0]);

        while (false !== ($row = $result->fetchAssociative())) {
            $options[$row['id']] = $row['code'].' '.$row['name'];
        }

        return $options;
    }

    /**
     * Only the release levels of the release level system of the event type
     * (tl_event_type.levelAccessPermissionPackage), grouped by release level package.
     * This applies to admins too.
     *
     * - act=edit|editAll: the event that is being edited
     * - act=overrideAll: the form is shown for all selected events at once
     *   ($dc->id is 0), so the release levels of all selected events are shown.
     *   When saving, the options are determined for each event separately.
     *
     * @throws Exception
     */
    #[AsCallback(table: 'tl_calendar_events', target: 'fields.eventReleaseLevel.options', priority: 50)]
    public function getEventReleaseLevels(DataContainer $dc): array
    {
        $act = $this->requestStack->getCurrentRequest()->query->get('act');

        // The filter panel of the list view uses the foreignKey of the field, as it
        // cannot handle grouped options.
        if (!$act || 'select' === $act) {
            return [];
        }

        $eventIds = $dc->id ? [$dc->id] : ($this->requestStack->getSession()->get('CURRENT')['IDS'] ?? []);
        $packageIds = [];

        foreach ((array) $eventIds as $eventId) {
            $package = $this->eventReleaseLevelPolicyPackageModel->findReleaseLevelPolicyPackageModelByEventId($eventId);

            if (null !== $package) {
                $packageIds[(int) $package->id] = (int) $package->id;
            }
        }

        $options = [];

        foreach ($packageIds as $packageId) {
            $options = array_replace_recursive($options, $this->getReleaseLevelOptions($packageId));
        }

        return $options;
    }

    /**
     * Multi Column Wizard columnsCallback for tl_calendar_events.eventDates.
     */
    public function listFixedDates(): array
    {
        return [
            'new_repeat' => [
                'label' => null,
                'exclude' => true,
                'inputType' => 'text',
                'default' => time(),
                'eval' => ['rgxp' => 'date', 'datepicker' => true, 'doNotCopy' => false, 'style' => 'width:100px', 'tl_class' => 'hidelabel wizard'],
            ],
        ];
    }

    #[AsCallback(table: 'tl_calendar_events', target: 'list.sorting.child_record', priority: 100)]
    public function childRecordCallback(array $arrRow): string
    {
        $span = $this->calendar->calculateSpan($arrRow['startTime'], $arrRow['endTime']);
        $event = $this->calendarEventsModel->findById($arrRow['id']);

        $date = $this->formatEventDate($arrRow, (int) $span);

        // Published icon
        if ($arrRow['published']) {
            $icon = $this->image->getHtml('visible.svg', $GLOBALS['TL_LANG']['MSC']['published'], 'title="'.$GLOBALS['TL_LANG']['MSC']['published'].'"');
        } else {
            $icon = $this->image->getHtml('invisible.svg', $GLOBALS['TL_LANG']['MSC']['unpublished'], 'title="'.$GLOBALS['TL_LANG']['MSC']['unpublished'].'"');
        }

        // Main instructor
        $strAuthor = '';
        $mainInstructor = $this->userModel->findById($arrRow['mainInstructor']);

        if (null !== $mainInstructor) {
            $strAuthor = ' <span style="color:#b3b3b3;padding-left:3px">[Hauptleiter: '.$this->stringUtil->specialchars($mainInstructor->name).']</span><br>';
        }

        // Registration badges
        $strRegistrations = $this->calendarEventsUtil->getSubscriptionStateBadges($event);

        if ('' !== $strRegistrations) {
            $strRegistrations = '<br>'.$strRegistrations;
        }

        // Release level
        $strLevel = '';
        $currentLevel = $this->eventReleaseLevelPolicyModel->findById($arrRow['eventReleaseLevel']);

        if (null !== $currentLevel) {
            $strLevel = \sprintf(
                '<span class="release-level-%d text-decoration-underline" title="Freigabestufe: %s">FS: %s</span> ',
                $this->stringUtil->specialchars($currentLevel->level),
                $this->stringUtil->specialchars($currentLevel->title),
                $currentLevel->level,
            );
        }

        return \sprintf(
            '<div class="tl_content_left">%s %s%s <span style="color:#999;padding-left:3px">[%s]</span>%s%s</div>',
            $icon,
            $strLevel,
            $this->stringUtil->specialchars($arrRow['title']),
            $date,
            $strAuthor,
            $strRegistrations,
        );
    }

    /**
     * Return the Swisstopo coordinates in a more human‑readable format, e.g. "2'600'000, 2'000'000".
     */
    #[AsCallback(table: 'tl_calendar_events', target: 'fields.coordsCH1903.load', priority: 100)]
    public function formatSwisstopoCoords(string $value, DataContainer $dc): string
    {
        if ('' === $value) {
            return '';
        }

        if (!SwisstopoLV95Validator::isValid($value)) {
            return $value;
        }

        [$east, $north] = explode(',', $value);

        $east = number_format((float) $east, 0, '.', "'");
        $north = number_format((float) $north, 0, '.', "'");

        return $east.', '.$north;
    }

    #[AsCallback(table: 'tl_calendar_events', target: 'fields.coordsCH1903.save', priority: 100)]
    public function validateSwisstopoCoords(string $input, DataContainer $dc): string
    {
        $coords = preg_replace('/\s+/', '', $input);

        if ('' === $coords) {
            return '';
        }

        $coords = SwisstopoLV95Normalizer::normalize($coords);

        if (SwisstopoLV95Validator::isValid($coords)) {
            return $coords;
        }

        return '';
    }

    /**
     * - Event date fields cannot be empty
     * - Must contain one or more valid dates
     * - Must be correctly sorted
     * - Formatted dates are converted to unix timestamps.
     */
    #[AsCallback(table: 'tl_calendar_events', target: 'fields.eventDates.save', priority: 100)]
    public function validateEventDates(string $varValue, DataContainer $dc): string
    {
        $repeats = $this->stringUtil->deserialize($varValue, true);

        if (empty($repeats)) {
            throw new \Exception($this->translator->trans('ERR.eventDatesCannotBeEmpty', [], 'contao_default'));
        }

        foreach ($repeats as $k => $item) {
            $value = $item['new_repeat'] ?? null;

            if (DateValidator::isValidDate($value, $this->config->get('dateFormat'))) {
                // Convert the date string to a timestamp
                $repeats[$k]['new_repeat'] = new Date($value, $this->config->get('dateFormat'))->tstamp;
            } elseif (DateValidator::isValidTimestamp($value)) {
                $repeats[$k]['new_repeat'] = (int) $value;
            } else {
                throw new \Exception($this->translator->trans('ERR.eventDatesInvalid', [], 'contao_default'));
            }

            // The timestamp must be > 0
            if ($repeats[$k]['new_repeat'] <= 0) {
                throw new \Exception($this->translator->trans('ERR.eventDatesInvalid', [], 'contao_default'));
            }
        }

        // The dates must be sorted in ascending order
        $previousTstamp = 0;

        foreach ($repeats as $item) {
            if ($previousTstamp >= $item['new_repeat']) {
                throw new \Exception($this->translator->trans('ERR.eventDatesNotCorrectlySorted', [], 'contao_default'));
            }

            $previousTstamp = $item['new_repeat'];
        }

        return serialize($repeats);
    }

    /**
     * Sync tl_calendar_events_instructor and tl_calendar_events.mainInstructor with the
     * instructors of the event. The first instructor in the list is the main instructor.
     *
     * @throws Exception
     */
    #[AsCallback(table: 'tl_calendar_events', target: 'fields.instructor.save', priority: 100)]
    public function setMainInstructor(string|null $varValue, DataContainer $dc): string|null
    {
        if (!$dc->id) {
            return $varValue;
        }

        $instructors = $this->stringUtil->deserialize($varValue, true);
        $instructorIds = array_column($instructors, 'instructorId');
        $instructorIds = !empty($instructorIds) ? array_map('intval', $instructorIds) : $instructorIds;

        // Remove the instructors that are no longer in the list
        if (!empty($instructorIds)) {
            $this->connection->executeStatement(
                'DELETE FROM tl_calendar_events_instructor WHERE pid = ? AND userId NOT IN (?)',
                [$dc->id, $instructorIds],
                [ParameterType::INTEGER, ArrayParameterType::INTEGER],
            );
        } else {
            $this->connection->delete('tl_calendar_events_instructor', ['pid' => (int) $dc->id]);
        }

        // Insert or update the instructors (unique key userid_pid on tl_calendar_events_instructor)
        foreach ($instructorIds as $i => $userId) {
            $sql = '
            INSERT INTO tl_calendar_events_instructor (pid, userId, tstamp, isMainInstructor)
            VALUES (?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
                tstamp = VALUES(tstamp),
                isMainInstructor = VALUES(isMainInstructor)
             ';

            $this->connection->executeStatement($sql, [
                (int) $dc->id,
                (int) $userId,
                time(),
                0 === $i ? 1 : 0,
            ]);
        }

        $this->connection->update(self::TABLE, ['mainInstructor' => $instructorIds[0] ?? 0], ['id' => $dc->id]);

        return $varValue;
    }

    /**
     * Only validates the transition. Contao saves the new release level together with
     * the other fields, if all fields of the form are valid (see
     * syncPublishedWithEventReleaseLevel() for the rest).
     *
     * Contao calls the save callback on every submit, even if the value has not changed.
     */
    #[AsCallback(table: 'tl_calendar_events', target: 'fields.eventReleaseLevel.save', priority: 90)]
    public function saveCallbackEventReleaseLevel(int $targetEventReleaseLevelId, DataContainer $dc): int
    {
        $event = $this->calendarEventsModel->findById($dc->id);

        if (null === $event) {
            return $targetEventReleaseLevelId;
        }

        try {
            $this->eventReleaseLevelUtil->validateEventReleaseLevelTransition($event, $targetEventReleaseLevelId);
        } catch (EventReleaseLevelTransitionException $e) {
            $this->message->add($this->translator->trans($e->getTranslatableText(), $e->getParams(), 'contao_default'), $e->getErrorLevel());

            // Keep the current release level
            return (int) $event->eventReleaseLevel;
        }

        if ((int) $event->eventReleaseLevel !== $targetEventReleaseLevelId) {
            $this->releaseLevelChanges[(int) $event->id] = [
                'previousLevelId' => (int) $event->eventReleaseLevel,
                'levelId' => $targetEventReleaseLevelId,
            ];
        }

        return $targetEventReleaseLevelId;
    }

    /**
     * The release level has been changed in the edit form (edit, editAll, overrideAll)
     * and saved: Publish the event on the highest level, unpublish it on the other levels.
     *
     * The onsubmit callback is only called if all fields of the form are valid. In the
     * modes editAll and overrideAll, the changes can still be rolled back, so the
     * notifications are sent at the end of the request (see EventReleaseLevelChangeNotifier).
     *
     * Runs after setValidEventReleaseLevel(), which may have reset the release level.
     *
     * @throws Exception
     */
    #[AsCallback(table: 'tl_calendar_events', target: 'config.onsubmit', priority: 10)]
    public function syncPublishedWithEventReleaseLevel(DataContainer $dc): void
    {
        $eventId = (int) $dc->id;
        $change = $this->releaseLevelChanges[$eventId] ?? null;

        unset($this->releaseLevelChanges[$eventId]);

        // The release level has not been changed in the form
        if (null === $change || 0 === $change['previousLevelId']) {
            return;
        }

        $row = $this->connection->fetchAssociative('SELECT eventReleaseLevel, published FROM tl_calendar_events WHERE id = ?', [$eventId], [Types::INTEGER]);

        // The release level has been reset in the meantime (see setValidEventReleaseLevel())
        if (false === $row || (int) $row['eventReleaseLevel'] !== $change['levelId']) {
            return;
        }

        $maxLevel = $this->eventReleaseLevelPolicyModel->findMaxLevelByEventId($eventId);
        $published = null !== $maxLevel && (int) $maxLevel->id === $change['levelId'];

        $this->connection->update(self::TABLE, ['published' => $published ? 1 : 0], ['id' => $eventId]);

        $this->eventReleaseLevelChangeNotifier->deferNotification($eventId, $change['previousLevelId'], $change['levelId'], (bool) $row['published']);
    }

    /**
     * Don't allow tourTechDifficultyMax to be equal to tourTechDifficultyMin.
     */
    #[AsCallback(table: 'tl_calendar_events', target: 'fields.tourTechDifficulty.save', priority: 90)]
    public function setCorrectTourTechDifficulty(string $value, DataContainer $dc): string
    {
        $difficulties = $this->stringUtil->deserialize($value, true);
        $hasUpdate = false;

        foreach ($difficulties as $i => $difficulty) {
            if (isset($difficulty['tourTechDifficultyMin'], $difficulty['tourTechDifficultyMax']) && $difficulty['tourTechDifficultyMin'] === $difficulty['tourTechDifficultyMax']) {
                $difficulties[$i]['tourTechDifficultyMax'] = '';
                $hasUpdate = true;
            }
        }

        return $hasUpdate ? serialize($difficulties) : $value;
    }

    /**
     * Save the event type immediately and set the first release level if the event
     * has no valid release level yet.
     *
     * @throws \Exception
     */
    #[AsCallback(table: 'tl_calendar_events', target: 'fields.eventType.save', priority: 80)]
    public function saveCallbackEventType(string $strEventType, DataContainer $dc, int|null $intId = null): string
    {
        if ('' === $strEventType) {
            return $strEventType;
        }

        $event = $this->calendarEventsModel->findById($dc->activeRecord->id > 0 ? $dc->activeRecord->id : $intId);

        if (null === $event) {
            throw new \Exception('Event not found.');
        }

        // Important: Without the event type, no release level can be assigned
        $event->eventType = $strEventType;
        $event->save();

        if (null === $this->eventReleaseLevelPolicyModel->findById($event->eventReleaseLevel)) {
            $firstLevel = $this->eventReleaseLevelPolicyModel->findMinLevelByEventId($event->id);

            if (null !== $firstLevel) {
                $event->eventReleaseLevel = $firstLevel->id;
                $event->save();
            }
        }

        return $strEventType;
    }

    /**
     * @throws \Exception
     */
    #[AsCallback(table: 'tl_calendar_events', target: 'fields.maxMembers.save', priority: 100)]
    public function validateMaxMembers(string $value, DataContainer $dc, int|null $intId = null): string
    {
        $request = $this->requestStack->getCurrentRequest();

        if (!$request->request->get('addMinAndMaxMembers')) {
            return $value;
        }

        if (!$request->request->has('minMembers')) {
            return $value;
        }

        $minMembers = (int) $request->request->get('minMembers');

        if ($value < $minMembers) {
            throw new \Exception($this->translator->trans('ERR.maxMembersShouldNotBeLessThanMinMembers', [], 'contao_default'));
        }

        return $value;
    }

    private function disableFilterSearchAndSorting(string ...$fields): void
    {
        foreach ($fields as $field) {
            $GLOBALS['TL_DCA'][self::TABLE]['fields'][$field]['filter'] = false;
            $GLOBALS['TL_DCA'][self::TABLE]['fields'][$field]['search'] = false;
            $GLOBALS['TL_DCA'][self::TABLE]['fields'][$field]['sorting'] = false;
        }
    }

    /**
     * Value of a column in the CSV export.
     */
    private function getCsvValue(CalendarEventsModel $event, string $field): mixed
    {
        $value = $event->{$field};

        switch ($field) {
            case 'eventType':
                return $GLOBALS['TL_LANG']['MSC'][$value] ?? $value;

            case 'mainInstructor':
                $user = $this->userModel->findById($value);

                return null !== $user ? html_entity_decode($user->lastname.' '.$user->firstname) : '';

            case 'tourTechDifficulty':
                return implode(' und ', $this->calendarEventsUtil->getTourTechDifficultiesAsArray($event, false, false));

            case 'eventDates':
                $dates = array_map(
                    fn ($tstamp) => $this->date->parse($this->config->get('dateFormat'), $tstamp),
                    $this->calendarEventsUtil->getEventTimestamps($event),
                );

                return implode(',', $dates);

            case 'eventDurationInDays':
                return \count($this->calendarEventsUtil->getEventTimestamps($event));

            case 'organizers':
                return html_entity_decode(implode(',', $this->calendarEventsUtil->getEventOrganizersAsArray($event, 'title')));

            case 'instructor':
                return html_entity_decode(implode(',', $this->calendarEventsUtil->getInstructorNamesAsArray($event)));

            case 'tourType':
                if (EventType::COURSE === $event->eventType) {
                    return '';
                }

                return html_entity_decode(implode(',', $this->calendarEventsUtil->getTourTypesAsArray($event, 'title')));

            case 'eventReleaseLevel':
                $releaseLevel = $this->eventReleaseLevelPolicyModel->findById($value);

                return null !== $releaseLevel ? $releaseLevel->level : '';

            case 'journey':
                $journey = $this->calendarEventsJourneyModel->findById($value);

                return null !== $journey ? $journey->title : $value;

            case 'courseLevel':
                if (EventType::COURSE !== $event->eventType || !\is_int($value) || !$this->courseLevels->has($value)) {
                    return '';
                }

                return $this->courseLevels->get($value);

            case 'courseTypeLevel0':
                if (EventType::COURSE !== $event->eventType || empty($value)) {
                    return '';
                }

                return (string) $this->connection->fetchOne('SELECT name FROM tl_course_main_type WHERE id = ?', [$value]);

            case 'courseTypeLevel1':
                if (EventType::COURSE !== $event->eventType || empty($value)) {
                    return '';
                }

                return (string) $this->connection->fetchOne('SELECT name FROM tl_course_sub_type WHERE id = ?', [$value]);

            case 'executionState':
                return empty($value) ? '' : $GLOBALS['TL_LANG']['tl_calendar_events'][$value] ?? $value;

            case 'eventState':
                return empty($value) ? '' : $GLOBALS['TL_LANG']['tl_calendar_events'][$value][0] ?? $value;

            default:
                if (\in_array($field, self::CSV_EXPORT_TEXT_FIELDS, true)) {
                    return str_replace(['<br>', '<br/>', '<br />', '{{br}}'], [' ', ' ', ' ', ' '], nl2br((string) $value));
                }

                return $value;
        }
    }

    /**
     * Shifted dates of an event or null, if the event has invalid event dates.
     */
    private function getShiftedDates(array $row, string $modifier): array|null
    {
        $set = [];

        $set['startTime'] = strtotime($modifier, (int) $row['startTime']);
        $set['endTime'] = strtotime($modifier, (int) $row['endTime']);
        $set['startDate'] = strtotime($modifier, (int) $row['startDate']);
        $set['endDate'] = strtotime($modifier, (int) $row['endDate']);

        if ($row['registrationStartDate'] > 0) {
            $set['registrationStartDate'] = strtotime($modifier, (int) $row['registrationStartDate']);
        }

        if ($row['registrationEndDate'] > 0) {
            $set['registrationEndDate'] = strtotime($modifier, (int) $row['registrationEndDate']);
        }

        $repeats = [];

        foreach ($this->stringUtil->deserialize($row['eventDates'], true) as $repeat) {
            $repeat['new_repeat'] = strtotime($modifier, (int) $repeat['new_repeat']);

            if (!DateValidator::isValidTimestamp($repeat['new_repeat'])) {
                $this->message->addError($this->translator->trans('ERR.eventDatesInvalid', [$row['id']], 'contao_default'));

                return null;
            }

            $repeats[] = $repeat;
        }

        if ([] !== $repeats) {
            $set['eventDates'] = serialize($repeats);
        }

        return $set;
    }

    /**
     * Label and help text of a field: from the DCA, tl_calendar_events or MSC (in this
     * order). Falls back to the field name.
     *
     * @return array{0: string, 1: string}
     */
    private function getFieldLabel(string $fieldName, array $dcaField): array
    {
        $labels = $dcaField['label'] ?? $GLOBALS['TL_LANG'][self::TABLE][$fieldName] ?? $GLOBALS['TL_LANG']['MSC'][$fieldName] ?? [];
        $labels = \is_array($labels) ? $labels : [$labels];

        $label = (string) ($labels[0] ?? '');
        $help = (string) ($labels[1] ?? '');

        return ['' !== $label ? $label : $fieldName, $help];
    }

    /**
     * Human-readable value of a field for showFieldValue().
     */
    private function formatFieldValue(string $fieldName, mixed $value, array $dcaField): mixed
    {
        if ('eventState' === $fieldName) {
            return '' === $value ? '---' : $value;
        }

        if ('mountainguide' === $fieldName) {
            return $GLOBALS['TL_LANG'][self::TABLE]['mountainguide_reference'][(int) $value];
        }

        if ('eventDates' === $fieldName) {
            return $this->formatEventDates($value);
        }

        if ('tourProfile' === $fieldName) {
            return $this->formatTourProfile($value);
        }

        if ('instructor' === $fieldName) {
            return $this->formatInstructors($value);
        }

        if ('tourTechDifficulty' === $fieldName) {
            return $this->formatTourTechDifficulties($value);
        }

        if (isset($dcaField['foreignKey'])) {
            return $this->formatForeignKeyValue($value, $dcaField['foreignKey']);
        }

        if (($dcaField['inputType'] ?? null) === 'fileTree') {
            return $this->formatFileTreeValue($value);
        }

        if (\is_array($value)) {
            return $this->formatArrayValue($value);
        }

        $rgxp = $dcaField['eval']['rgxp'] ?? null;

        if ('date' === $rgxp) {
            return $value ? $this->date->parse($this->config->get('dateFormat'), $value) : '-';
        }

        if ('time' === $rgxp) {
            return $value ? $this->date->parse($this->config->get('timeFormat'), $value) : '-';
        }

        if ('tstamp' === $fieldName || 'datim' === $rgxp || \in_array($dcaField['flag'] ?? null, self::DATE_SORTING_FLAGS, true)) {
            return $value ? $this->date->parse($this->config->get('datimFormat'), $value) : '-';
        }

        if (($dcaField['eval']['isBoolean'] ?? null) || (($dcaField['inputType'] ?? null) === 'checkbox' && !($dcaField['eval']['multiple'] ?? null))) {
            return $value ? $GLOBALS['TL_LANG']['MSC']['yes'] : $GLOBALS['TL_LANG']['MSC']['no'];
        }

        if ('email' === $rgxp) {
            return $this->idna->decodeEmail($value);
        }

        if (($dcaField['inputType'] ?? null) === 'textarea' && (($dcaField['eval']['allowHtml'] ?? null) || ($dcaField['eval']['preserveTags'] ?? null))) {
            return $this->stringUtil->specialchars($value);
        }

        if (\is_array($dcaField['reference'] ?? null)) {
            if (!isset($dcaField['reference'][$value])) {
                return $value;
            }

            return \is_array($dcaField['reference'][$value]) ? $dcaField['reference'][$value][0] : $dcaField['reference'][$value];
        }

        if (($dcaField['eval']['isAssociative'] ?? null) || $this->arrayUtil->isAssoc($dcaField['options'] ?? null)) {
            return $dcaField['options'][$value] ?? null;
        }

        return $value;
    }

    private function formatEventDates(mixed $value): mixed
    {
        if (empty($value) || !\is_array($value)) {
            return $value;
        }

        $dates = [];

        foreach ($value as $repeat) {
            $dates[] = $this->date->parse('D, d.m.Y', $repeat['new_repeat']);
        }

        return implode('<br>', $dates);
    }

    private function formatTourProfile(mixed $value): mixed
    {
        $profiles = [];
        $day = 0;

        if (!empty($value) && \is_array($value)) {
            foreach ($value as $profile) {
                ++$day;

                if (\count($value) > 1) {
                    $pattern = $day.'. Tag &nbsp;&nbsp;&nbsp; Aufstieg: %s m/%s h &nbsp;&nbsp;&nbsp;Abstieg: %s m/%s h';
                } else {
                    $pattern = 'Aufstieg: %s m/%s h &nbsp;&nbsp;&nbsp;Abstieg: %s m/%s h';
                }

                $profiles[] = \sprintf($pattern, $profile['tourProfileAscentMeters'], $profile['tourProfileAscentTime'], $profile['tourProfileDescentMeters'], $profile['tourProfileDescentTime']);
            }
        }

        return empty($profiles) ? $value : implode('<br>', $profiles);
    }

    private function formatInstructors(mixed $value): mixed
    {
        $names = [];

        foreach ($value as $instructor) {
            if ($instructor['instructorId'] > 0) {
                $user = $this->userModel->findById($instructor['instructorId']);

                if (null !== $user) {
                    $names[] = $user->name;
                }
            }
        }

        return empty($names) ? $value : implode('<br>', $names);
    }

    private function formatTourTechDifficulties(mixed $value): mixed
    {
        $difficulties = [];

        foreach ($value as $difficulty) {
            $strDiff = '';

            if (\strlen((string) $difficulty['tourTechDifficultyMin']) && \strlen($difficulty['tourTechDifficultyMax'])) {
                $strMin = $this->getTourDifficultyShortcut($difficulty['tourTechDifficultyMin']);

                if ($strMin) {
                    $strDiff = $strMin;
                }

                $strMax = $this->getTourDifficultyShortcut($difficulty['tourTechDifficultyMax']);

                if ($strMax) {
                    $strDiff .= ' - '.$strMax;
                }

                $difficulties[] = $strDiff;
            } elseif (\strlen((string) $difficulty['tourTechDifficultyMin'])) {
                $strMin = $this->getTourDifficultyShortcut($difficulty['tourTechDifficultyMin']);

                if ($strMin) {
                    $strDiff = $strMin;
                }

                $difficulties[] = $strDiff;
            }
        }

        return empty($difficulties) ? $value : implode(', ', $difficulties);
    }

    private function getTourDifficultyShortcut(mixed $id): mixed
    {
        return $this->connection->fetchOne('SELECT shortcut FROM tl_tour_difficulty WHERE id = ?', [$id]);
    }

    private function formatForeignKeyValue(mixed $value, string $foreignKey): string
    {
        $values = [];
        [$table, $column] = explode('.', $foreignKey, 2);

        foreach ((array) $value as $id) {
            // Use \Contao\Database::quoteIdentifier instead of
            // Doctrine\DBAL\Connection::quoteIdentifier because only Contao can handle
            // chained foreign keys like this: 'foreignKey' => "tl_user.CONCAT(lastname, ' ',
            // firstname, ', ', city)",
            $keyValue = $this->connection->fetchOne('SELECT '.Database::quoteIdentifier($column).' AS value FROM '.$table.' WHERE id = ?', [$id]);

            if ($keyValue) {
                $values[] = $keyValue;
            }
        }

        return implode(', ', $values);
    }

    private function formatFileTreeValue(mixed $value): string
    {
        if (\is_array($value)) {
            foreach ($value as $key => $uuid) {
                $value[$key] = $this->getFilePathWithUuid($uuid);
            }

            return implode(', ', $value);
        }

        return $this->getFilePathWithUuid($value);
    }

    private function getFilePathWithUuid(mixed $uuid): string
    {
        $file = $this->filesModel->findByUuid($uuid);

        if (!$file instanceof FilesModel) {
            return '';
        }

        return $file->path.' ('.$this->stringUtil->binToUuid($uuid).')';
    }

    private function formatArrayValue(array $value): string
    {
        // inputUnit
        if (isset($value['value'], $value['unit']) && 2 === \count($value)) {
            return trim($value['value'].', '.$value['unit']);
        }

        foreach ($value as $key => $item) {
            if (\is_array($item)) {
                $values = array_values($item);
                $value[$key] = array_shift($values).' ('.implode(', ', array_filter($values)).')';
            }
        }

        if ($this->arrayUtil->isAssoc($value)) {
            foreach ($value as $key => $item) {
                $value[$key] = $key.': '.$item;
            }
        }

        return implode(', ', $value);
    }

    /**
     * Date (and time) of the event in the list view.
     */
    private function formatEventDate(array $row, int $span): string
    {
        if ($span > 0) {
            $format = $this->config->get($row['addTime'] ? 'datimFormat' : 'dateFormat');

            return $this->date->parse($format, $row['startTime']).' – '.$this->date->parse($this->config->get($row['addTime'] ? 'datimFormat' : 'dateFormat'), $row['endTime']);
        }

        $date = $this->date->parse($this->config->get('dateFormat'), $row['startTime']);

        if (!$row['addTime']) {
            return $date;
        }

        if ((int) $row['startTime'] === (int) $row['endTime']) {
            return $date.' '.$this->date->parse($this->config->get('timeFormat'), $row['startTime']);
        }

        return $date.' '.$this->date->parse($this->config->get('timeFormat'), $row['startTime']).' – '.$this->date->parse($this->config->get('timeFormat'), $row['endTime']);
    }

    /**
     * Release levels of a package, grouped by the title of the package:
     * [package title => [release level id => release level title]].
     *
     * @throws Exception
     */
    private function getReleaseLevelOptions(int $packageId): array
    {
        $rows = $this->connection->fetchAllAssociative(
            'SELECT l.id, l.title, p.title AS packageTitle FROM tl_event_release_level_policy l INNER JOIN tl_event_release_level_policy_package p ON p.id = l.pid WHERE l.pid = ? ORDER BY l.level',
            [$packageId],
            [Types::INTEGER],
        );

        $options = [];

        foreach ($rows as $row) {
            $options[$row['packageTitle']][$row['id']] = $row['title'];
        }

        return $options;
    }
}
