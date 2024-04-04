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

namespace Markocupic\SacEventToolBundle\DataContainer;

use Contao\ArrayUtil;
use Contao\BackendUser;
use Contao\Calendar;
use Contao\CalendarEventsModel;
use Contao\CalendarModel;
use Contao\Config;
use Contao\Controller;
use Contao\CoreBundle\DataContainer\PaletteManipulator;
use Contao\CoreBundle\DependencyInjection\Attribute\AsCallback;
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
use Contao\User;
use Contao\UserGroupModel;
use Contao\UserModel;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;
use League\Csv\CannotInsertRecord;
use League\Csv\CharsetConverter;
use League\Csv\InvalidArgument;
use League\Csv\Writer;
use Markocupic\SacEventToolBundle\CalendarEventsHelper;
use Markocupic\SacEventToolBundle\Config\CourseLevels;
use Markocupic\SacEventToolBundle\Config\EventDurationInfo;
use Markocupic\SacEventToolBundle\Config\EventState;
use Markocupic\SacEventToolBundle\Config\EventType;
use Markocupic\SacEventToolBundle\DataContainer\EventReleaseLevel\EventReleaseLevelUtil;
use Markocupic\SacEventToolBundle\Model\CalendarEventsJourneyModel;
use Markocupic\SacEventToolBundle\Model\EventReleaseLevelPolicyModel;
use Markocupic\SacEventToolBundle\Model\EventReleaseLevelPolicyPackageModel;
use Markocupic\SacEventToolBundle\Model\EventTypeModel;
use Markocupic\SacEventToolBundle\Model\TourDifficultyCategoryModel;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\PasswordHasherFactoryInterface;

class CalendarEvents
{
    // Adapters
    private Adapter $arrayUtil;
    private Adapter $calendarEventsHelper;
    private Adapter $calendarEventsJourneyModel;
    private Adapter $calendarEventsModel;
    private Adapter $calendarModel;
    private Adapter $config;
    private Adapter $controller;
    private Adapter $date;
    private Adapter $filesModel;
    private Adapter $idna;
    private Adapter $image;
    private Adapter $message;
    private Adapter $stringUtil;
    private Adapter $system;
    private Adapter $userModel;

    public function __construct(
        private readonly CourseLevels $courseLevels,
        private readonly Connection $connection,
        private readonly ContaoFramework $framework,
        private readonly EventDurationInfo $eventDurationInfo,
        private readonly EventReleaseLevelUtil $eventReleaseLevelUtil,
        private readonly PasswordHasherFactoryInterface $passwordHasherFactory,
        private readonly RequestStack $requestStack,
        private readonly Security $security,
	    private readonly string $sacevtEventRegistrationConfigEmailAcceptCustomTemplPath,
    ) {
        // Adapters
        $this->arrayUtil = $this->framework->getAdapter(ArrayUtil::class);
        $this->calendarEventsHelper = $this->framework->getAdapter(CalendarEventsHelper::class);
        $this->calendarEventsJourneyModel = $this->framework->getAdapter(CalendarEventsJourneyModel::class);
        $this->calendarEventsModel = $this->framework->getAdapter(CalendarEventsModel::class);
        $this->calendarModel = $this->framework->getAdapter(CalendarModel::class);
        $this->config = $this->framework->getAdapter(Config::class);
        $this->controller = $this->framework->getAdapter(Controller::class);
        $this->date = $this->framework->getAdapter(Date::class);
        $this->filesModel = $this->framework->getAdapter(FilesModel::class);
        $this->idna = $this->framework->getAdapter(Idna::class);
        $this->image = $this->framework->getAdapter(Image::class);
        $this->message = $this->framework->getAdapter(Message::class);
        $this->stringUtil = $this->framework->getAdapter(StringUtil::class);
        $this->system = $this->framework->getAdapter(System::class);
        $this->userModel = $this->framework->getAdapter(UserModel::class);
    }

    /**
     * Set the "on create new" palette.
     */
    #[AsCallback(table: 'tl_calendar_events', target: 'config.onload', priority: 90)]
    public function setPaletteWhenCreatingNew(DataContainer $dc): void
    {
        $request = $this->requestStack->getCurrentRequest();

        if ('edit' === $request->query->get('act')) {
            $objCalendarEventsModel = $this->calendarEventsModel->findByPk($dc->id);

            if (null !== $objCalendarEventsModel) {
                if (0 === (int) $objCalendarEventsModel->tstamp && empty($objCalendarEventsModel->eventType)) {
                    $GLOBALS['TL_DCA']['tl_calendar_events']['palettes']['default'] = 'eventType';
                }
            }
        }
    }

    /**
     * Reduce filter fields for tour guides and course instructors
     * and
     * Adjust filters depending on event type.
     */
    #[AsCallback(table: 'tl_calendar_events', target: 'config.onload', priority: 80)]
    public function adjustFilterSearchAndSortingBoard(DataContainer $dc): void
    {
        // Reduce filter fields to tour guides and course instructors
        if (!$this->security->isGranted('ROLE_ADMIN')) {
            $allowedFilters = [
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

            // Reduce filter fields for tour guides and course instructors
            foreach (array_keys($GLOBALS['TL_DCA']['tl_calendar_events']['fields']) as $field) {
                if (\in_array($field, $allowedFilters, true)) {
                    continue;
                }

                $GLOBALS['TL_DCA']['tl_calendar_events']['fields'][$field]['filter'] = null;
            }
        }

        // Adjust filters depending on event type
        if ($dc->currentPid) {
            $objCalendar = $this->calendarModel->findByPk($dc->currentPid);

            if (null !== $objCalendar) {
                $arrAllowedEventTypes = $this->stringUtil->deserialize($objCalendar->allowedEventTypes, true);

                if (!\in_array(EventType::TOUR, $arrAllowedEventTypes, true) && !\in_array(EventType::LAST_MINUTE_TOUR, $arrAllowedEventTypes, true)) {
                    $GLOBALS['TL_DCA']['tl_calendar_events']['fields']['tourType']['filter'] = false;
                    $GLOBALS['TL_DCA']['tl_calendar_events']['fields']['tourType']['search'] = false;
                    $GLOBALS['TL_DCA']['tl_calendar_events']['fields']['tourType']['sorting'] = false;
                }

                if (!\in_array(EventType::COURSE, $arrAllowedEventTypes, true)) {
                    $GLOBALS['TL_DCA']['tl_calendar_events']['fields']['courseTypeLevel0']['filter'] = false;
                    $GLOBALS['TL_DCA']['tl_calendar_events']['fields']['courseTypeLevel0']['search'] = false;
                    $GLOBALS['TL_DCA']['tl_calendar_events']['fields']['courseTypeLevel0']['sorting'] = false;

                    $GLOBALS['TL_DCA']['tl_calendar_events']['fields']['courseTypeLevel1']['filter'] = false;
                    $GLOBALS['TL_DCA']['tl_calendar_events']['fields']['courseTypeLevel1']['search'] = false;
                    $GLOBALS['TL_DCA']['tl_calendar_events']['fields']['courseTypeLevel1']['sorting'] = false;

                    $GLOBALS['TL_DCA']['tl_calendar_events']['fields']['courseLevel']['filter'] = false;
                    $GLOBALS['TL_DCA']['tl_calendar_events']['fields']['courseLevel']['search'] = false;
                    $GLOBALS['TL_DCA']['tl_calendar_events']['fields']['courseLevel']['sorting'] = false;
                }
            }
        }
    }

    /**
     * @throws Exception
     */
    #[AsCallback(table: 'tl_calendar_events', target: 'config.onload', priority: 70)]
    public function onloadCallbackDeleteInvalidEvents(DataContainer $dc): void
    {
        $this->connection->executeStatement(
            'DELETE FROM tl_calendar_events WHERE tstamp < ? AND tstamp > ? AND title = ?',
            [time() - 86400, 0, ''],
        );
    }

    /**
     * Set palette for course, tour, tour_report, etc.
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

        $objCalendarEventsModel = $this->calendarEventsModel->findByPk($dc->id);

        if (null === $objCalendarEventsModel) {
            return;
        }

        // Set palette for tour and course
        if (isset($GLOBALS['TL_DCA']['tl_calendar_events']['palettes'][$objCalendarEventsModel->eventType])) {
            $GLOBALS['TL_DCA']['tl_calendar_events']['palettes']['default'] = $GLOBALS['TL_DCA']['tl_calendar_events']['palettes'][$objCalendarEventsModel->eventType];
        }

        // Remove the field "rescheduledEventDate" if the event has not been rescheduled
        if (EventState::STATE_RESCHEDULED !== $objCalendarEventsModel->eventState) {
            $palettes = ['default', 'tour', 'lastMinuteTour', 'course', 'generalEvent', 'tour_report'];

            foreach ($palettes as $palette) {
                PaletteManipulator::create()
                    ->removeField('rescheduledEventDate')
                    ->applyToPalette($palette, 'tl_calendar_events')
                ;
            }
        }

        // Apply a custom palette for the tour report
        if ('writeTourReport' === $request->query->get('call')) {
            $GLOBALS['TL_DCA']['tl_calendar_events']['palettes']['default'] = $GLOBALS['TL_DCA']['tl_calendar_events']['palettes']['tour_report'];
        }
    }

    /**
     * Make a CSV-export of every event of a certain calendar.
     *
     * @throws CannotInsertRecord
     * @throws InvalidArgument
     */
    #[AsCallback(table: 'tl_calendar_events', target: 'config.onload', priority: 40)]
    public function exportCalendar(DataContainer $dc): void
    {
        $request = $this->requestStack->getCurrentRequest();

        if ('onloadCallbackExportCalendar' === $request->query->get('action') && $request->query->get('id') > 0) {
            // Create empty document
            $csv = Writer::createFromString();

            // Set encoding from utf-8 to is0-8859-15 (windows)
            $encoder = (new CharsetConverter())
                ->outputEncoding('iso-8859-15')
            ;

            $csv->addFormatter($encoder);

            // Set delimiter
            $csv->setDelimiter(';');

            // Selected fields
            $arrFields = array_unique(['id', 'title', 'location', 'eventDates', 'eventDurationInDays', 'published', 'organizers', 'mountainguide', 'mainInstructor', 'instructor', 'minMembers', 'maxMembers', 'executionState', 'eventState', 'eventType', 'courseLevel', 'courseTypeLevel0', 'courseTypeLevel1', 'tourType', 'tourTechDifficulty', 'eventReleaseLevel', 'journey', 'teaser', 'tourDetailText', 'requirements']);

            // Insert headline first
            $this->controller->loadLanguageFile('tl_calendar_events');

            $arrHeadline = array_map(
                static fn ($field) => $GLOBALS['TL_LANG']['tl_calendar_events'][$field][0] ?? $field,
                $arrFields
            );

            $csv->insertOne($arrHeadline);

            $objEvent = $this->calendarEventsModel->findBy(
                ['tl_calendar_events.pid = ?'],
                [$request->query->get('id')],
                ['order' => 'tl_calendar_events.startDate ASC']
            );

            if (null !== $objEvent) {
                while ($objEvent->next()) {
                    $arrRow = [];

                    foreach ($arrFields as $field) {
                        if ('eventType' === $field) {
                            $arrRow[] = $GLOBALS['TL_LANG']['MSC'][$objEvent->{$field}] ?? $objEvent->{$field};
                        } elseif ('mainInstructor' === $field) {
                            $objUser = $this->userModel->findByPk($objEvent->{$field});
                            $arrRow[] = null !== $objUser ? html_entity_decode($objUser->lastname.' '.$objUser->firstname) : '';
                        } elseif ('tourTechDifficulty' === $field) {
                            $arrDiff = $this->calendarEventsHelper->getTourTechDifficultiesAsArray($objEvent->current(), false, false);
                            $arrRow[] = implode(' und ', $arrDiff);
                        } elseif ('eventDates' === $field) {
                            $arrTimestamps = $this->calendarEventsHelper->getEventTimestamps($objEvent->current());
                            $arrDates = array_map(
                                static fn ($tstamp) => Date::parse(Config::get('dateFormat'), $tstamp),
                                $arrTimestamps
                            );
                            $arrRow[] = implode(',', $arrDates);
                        } elseif ('eventDurationInDays' === $field) {
                            $arrRow[] = \count($this->calendarEventsHelper->getEventTimestamps($objEvent->current()));
                        } elseif ('organizers' === $field) {
                            $arrOrganizers = $this->calendarEventsHelper->getEventOrganizersAsArray($objEvent->current(), 'title');
                            $arrRow[] = html_entity_decode(implode(',', $arrOrganizers));
                        } elseif ('instructor' === $field) {
                            $arrInstructors = $this->calendarEventsHelper->getInstructorNamesAsArray($objEvent->current(), false, true);
                            $arrRow[] = html_entity_decode(implode(',', $arrInstructors));
                        } elseif ('tourType' === $field) {
                            $arrTourTypes = $this->calendarEventsHelper->getTourTypesAsArray($objEvent->current(), 'title');
                            $arrRow[] = html_entity_decode(implode(',', $arrTourTypes));
                        } elseif ('eventReleaseLevel' === $field) {
                            $objFS = EventReleaseLevelPolicyModel::findByPk($objEvent->{$field});
                            $arrRow[] = null !== $objFS ? $objFS->level : '';
                        } elseif ('journey' === $field) {
                            $objJourney = $this->calendarEventsJourneyModel->findByPk($objEvent->{$field});
                            $arrRow[] = null !== $objJourney ? $objJourney->title : $objEvent->{$field};
                        } elseif ('courseLevel' === $field) {
                            $arrRow[] = $this->courseLevels->get($objEvent->{$field});
                        } elseif ('courseTypeLevel0' === $field) {
                            $arrRow[] = empty($objEvent->{$field}) ? '' : (string) $this->connection->fetchOne('SELECT name FROM tl_course_main_type WHERE id = ?', [$objEvent->{$field}]);
                        } elseif ('courseTypeLevel1' === $field) {
                            $arrRow[] = empty($objEvent->{$field}) ? '' : (string) $this->connection->fetchOne('SELECT name FROM tl_course_sub_type WHERE id = ?', [$objEvent->{$field}]);
                        } elseif ('executionState' === $field) {
                            $arrRow[] = empty($objEvent->{$field}) ? '' : $GLOBALS['TL_LANG']['tl_calendar_events'][$objEvent->{$field}] ?? $objEvent->{$field};
                        } elseif ('eventState' === $field) {
                            $arrRow[] = empty($objEvent->{$field}) ? '' : $GLOBALS['TL_LANG']['tl_calendar_events'][$objEvent->{$field}][0] ?? $objEvent->{$field};
                        } elseif (\in_array($field, ['teaser', 'tourDetailText', 'requirements'], true)) {
                            $arrRow[] = str_replace(['<br>', '<br/>'], [' ', ' '], nl2br((string) $objEvent->{$field}));
                        } else {
                            $arrRow[] = $objEvent->{$field};
                        }
                    }

                    $arrRow = array_map(fn ($strValue) => $this->stringUtil->revertInputEncoding((string) $strValue), $arrRow);

                    $csv->insertOne($arrRow);
                }
            }

            $objCalendar = $this->calendarModel->findByPk($request->query->get('id'));

            $fileName = $this->stringUtil->revertInputEncoding($objCalendar->title).'.csv';
            $fileName = $this->stringUtil->sanitizeFileName($fileName);

            $response = new Response((string) $csv->output($fileName));

            throw new ResponseException($response);
        }
    }

    /**
     * Shift all event dates of a certain calendar by +/- 1 year
     * contao?do=calendar&table=tl_calendar_events&id=21&transformDate=+52weeks.
     *
     * @throws Exception
     */
    #[AsCallback(table: 'tl_calendar_events', target: 'config.onload', priority: 30)]
    public function shiftEventDates(DataContainer $dc): void
    {
        $request = $this->requestStack->getCurrentRequest();

        if ($request->query->get('transformDates')) {
            // $mode may be "+52weeks" or "+1year"
            $mode = $request->query->get('transformDates');

            if (false !== strtotime($mode)) {
                $calendarId = $request->query->get('id');

                $stmt = $this->connection->executeQuery('SELECT * FROM tl_calendar_events WHERE pid = ?', [$calendarId]);

                while (false !== ($row = $stmt->fetchAssociative())) {
                    $set['startTime'] = strtotime($mode, (int) $row['startTime']);
                    $set['endTime'] = strtotime($mode, (int) $row['endTime']);
                    $set['startDate'] = strtotime($mode, (int) $row['startDate']);
                    $set['endDate'] = strtotime($mode, (int) $row['endDate']);

                    if ($row['registrationStartDate'] > 0) {
                        $set['registrationStartDate'] = strtotime($mode, (int) $row['registrationStartDate']);
                    }

                    if ($row['registrationEndDate'] > 0) {
                        $set['registrationEndDate'] = strtotime($mode, (int) $row['registrationEndDate']);
                    }

                    $arrRepeats = $this->stringUtil->deserialize($row['eventDates'], true);
                    $newArrRepeats = [];

                    if (\count($arrRepeats) > 0) {
                        foreach ($arrRepeats as $repeat) {
                            $repeat['new_repeat'] = strtotime($mode, (int) $repeat['new_repeat']);
                            $newArrRepeats[] = $repeat;
                        }

                        $set['eventDates'] = serialize($newArrRepeats);
                    }

                    $this->connection->update('tl_calendar_events', $set, ['id' => $row['id']]);
                }
            }

            // Redirect
            $this->controller->redirect($this->system->getReferer());
        }
    }

    /**
     * Set defaults.
     *
     * @param string        $strTable
     * @param int           $insertId
     * @param array         $set
     * @param DataContainer $dc
     */
    #[AsCallback(table: 'tl_calendar_events', target: 'config.oncreate', priority: 100)]
    public function onCreate(string $strTable, int $insertId, array $set, DataContainer $dc): void
    {
        $user = $this->security->getUser();

        // Set source, add author, set first release level and & set customEventRegistrationConfirmationEmailText on creating new events
        $objEventsModel = $this->calendarEventsModel->findByPk($insertId);

        if (null !== $objEventsModel) {
            // Set source always to "default"
            $objEventsModel->source = 'default';

            // Set logged-in User as author
            $objEventsModel->author = $user->id;
            $objEventsModel->mainInstructor = $user->id;
            $objEventsModel->instructor = serialize([['instructorId' => $user->id]]);

            // Set customEventRegistrationConfirmationEmailText
            $objEventsModel->customEventRegistrationConfirmationEmailText = file_get_contents($this->sacevtEventRegistrationConfigEmailAcceptCustomTemplPath);

            $objEventsModel->save();
        }
    }

    /**
     * @throws \Exception
     */
    #[AsCallback(table: 'tl_calendar_events', target: 'config.oncopy', priority: 100)]
    public function onCopy(int $insertId, DataContainer $dc): void
    {
        $user = $this->security->getUser();

        // Add author and set first release level on creating new events
        $objEventsModel = $this->calendarEventsModel->findByPk($insertId);

        if (null !== $objEventsModel) {
            // Set logged-in user as author
            $objEventsModel->author = $user->id;
            $objEventsModel->save();

            // Set eventReleaseLevel
            if ('' !== $objEventsModel->eventType) {
                $objEventReleaseLevelPolicyModel = EventReleaseLevelPolicyModel::findLowestLevelByEventId($objEventsModel->id);

                if (null !== $objEventReleaseLevelPolicyModel) {
                    $objEventsModel->eventReleaseLevel = $objEventReleaseLevelPolicyModel->id;
                    $objEventsModel->save();
                }
            }
        }
    }

    /**
     * Add a priority of -100
     * This way this callback will be executed after! the legacy callback tl_calendar_events.adjustTime()
     * but before self::adjustRegistrationPeriod (priority: -110).
     *
     * @throws Exception
     */
    #[AsCallback(table: 'tl_calendar_events', target: 'config.onsubmit', priority: -100)]
    public function adjustStartAndEndDate(DataContainer $dc): void
    {
        // Return if there is no active record (override all)
        if (!$dc->activeRecord) {
            return;
        }

        $arrDates = $this->stringUtil->deserialize($dc->activeRecord->eventDates);

        if (!\is_array($arrDates) || empty($arrDates)) {
            return;
        }

        $aNew = [];

        foreach ($arrDates as $v) {
            $objDate = new Date($v['new_repeat']);
            $aNew[$objDate->timestamp] = $objDate->timestamp;
        }

        ksort($aNew);

        $arrDates = [];

        foreach ($aNew as $v) {
            // Save as a timestamp
            $arrDates[] = ['new_repeat' => $v];
        }

        $set = [];
        $startTime = !empty($arrDates[0]['new_repeat']) ? $arrDates[0]['new_repeat'] : 0;
        $endTime = !empty($arrDates[\count($arrDates) - 1]['new_repeat']) ? $arrDates[\count($arrDates) - 1]['new_repeat'] : 0;

        $set['startDate'] = $set['startTime'] = $dc->activeRecord->startDate = $dc->activeRecord->startTime = $startTime;
        $set['endDate'] = $set['endTime'] = $dc->activeRecord->endDate = $dc->activeRecord->endTime = $endTime;

        $this->connection->update('tl_calendar_events', $set, ['id' => $dc->activeRecord->id]);
    }

    /**
     * Add a priority of -110
     * This way this callback will be executed after! the legacy callback tl_calendar_events.adjustTime()
     * and after self::adjustStartAndEndDate (priority: -100).
     *
     * @throws Exception
     */
    #[AsCallback(table: 'tl_calendar_events', target: 'config.onsubmit', priority: -110)]
    public function adjustRegistrationPeriod(DataContainer $dc): void
    {
        // Return if there is no active record (override all)
        if (!$dc->activeRecord) {
            return;
        }

        $row = $this->connection->fetchAssociative('SELECT * FROM tl_calendar_events WHERE id = ?', [$dc->activeRecord->id]);

        if ($row) {
            if ($row['setRegistrationPeriod'] && $row['startDate']) {
                $regEndDate = $row['registrationEndDate'];
                $regStartDate = $row['registrationStartDate'];

                if ($regEndDate > $row['startDate']) {
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

                $this->connection->update('tl_calendar_events', $set, ['id' => $row['id']]);
            }
        }
    }

    /**
     * @throws Exception
     */
    #[AsCallback(table: 'tl_calendar_events', target: 'config.onsubmit', priority: 80)]
    public function adjustEventReleaseLevel(DataContainer $dc): void
    {
        // Return if there is no active record (override all)
        if (!$dc->activeRecord) {
            return;
        }

        if ($dc->activeRecord->eventReleaseLevel) {
            return;
        }

        // Set releaseLevel to level 1
        $eventReleaseLevelModel = EventReleaseLevelPolicyModel::findLowestLevelByEventId($dc->activeRecord->id);

        if (null !== $eventReleaseLevelModel) {
            $set = ['eventReleaseLevel' => $eventReleaseLevelModel->id];
            $dc->activeRecord->eventReleaseLevel = $eventReleaseLevelModel->id;
            $this->connection->update('tl_calendar_events', $set, ['id' => $dc->activeRecord->id]);
        }
    }

    /**
     * @param DataContainer $dc
     *
     * @throws Exception
     */
    #[AsCallback(table: 'tl_calendar_events', target: 'config.onsubmit', priority: 40)]
    public function setTheFilledInReportFormAsDone(DataContainer $dc): void
    {
        $request = $this->requestStack->getCurrentRequest();

        // Set filledInEventReportForm, now the invoice form can be printed in tl_calendar_events_instructor_invoice
        if ('writeTourReport' === $request->query->get('call')) {
            $set = [
                'filledInEventReportForm' => 1,
            ];

            $this->connection->update('tl_calendar_events', $set, ['id' => $dc->activeRecord->id]);
        }
    }

    /**
     * @param DataContainer $dc
     *
     * @throws Exception
     */
    #[AsCallback(table: 'tl_calendar_events', target: 'config.onsubmit', priority: 30)]
    public function setAlias(DataContainer $dc): void
    {
        $set = [
            'alias' => 'event-'.$dc->id,
        ];

        $this->connection->update('tl_calendar_events', $set, ['id' => $dc->activeRecord->id]);
    }

    /**
     * @param DataContainer $dc
     *
     * @throws Exception
     */
    #[AsCallback(table: 'tl_calendar_events', target: 'config.onsubmit', priority: 20)]
    public function setValidEventReleaseLevel(DataContainer $dc): void
    {
        // Set correct eventReleaseLevel
        $objEvent = $this->calendarEventsModel->findByPk($dc->activeRecord->id);

        if (null !== $objEvent) {
            if ('' !== $objEvent->eventType) {
                if ($objEvent->eventReleaseLevel > 0) {
                    $objEventReleaseLevel = EventReleaseLevelPolicyModel::findByPk($objEvent->eventReleaseLevel);

                    if (null !== $objEventReleaseLevel) {
                        $objEventReleaseLevelPackage = EventReleaseLevelPolicyPackageModel::findReleaseLevelPolicyPackageModelByEventId($objEvent->id);
                        // Change eventReleaseLevel when changing eventType...
                        if ($objEventReleaseLevel->pid !== $objEventReleaseLevelPackage->id) {
                            $oEventReleaseLevelModel = EventReleaseLevelPolicyModel::findLowestLevelByEventId($objEvent->id);

                            if (null !== $oEventReleaseLevelModel) {
                                $set = [
                                    'eventReleaseLevel' => $oEventReleaseLevelModel->id,
                                ];

                                $this->connection->update('tl_calendar_events', $set, ['id' => $objEvent->id]);
                            }
                        }
                    }
                } else {
                    // Add eventReleaseLevel when creating a new event...
                    $oEventReleaseLevelModel = EventReleaseLevelPolicyModel::findLowestLevelByEventId($objEvent->id);

                    $set = ['eventReleaseLevel' => $oEventReleaseLevelModel->id];
                    $dc->activeRecord->eventReleaseLevel = $oEventReleaseLevelModel->id;

                    $this->connection->update('tl_calendar_events', $set, [$objEvent->id]);
                }
            }
        }
    }

    /**
     * @throws Exception
     */
    #[AsCallback(table: 'tl_calendar_events', target: 'fields.alias.input_field', priority: 100)]
    public function showFieldValue(DataContainer $dc): string
    {
        $fieldName = $dc->field;

        $strTable = 'tl_calendar_events';

        if (!$dc->activeRecord->id) {
            return '';
        }

        $intId = $dc->activeRecord->id;

        $varFieldValue = $this->connection->fetchOne('SELECT '.$fieldName.' FROM tl_calendar_events WHERE id = ?', [$intId]);

        if (false === $varFieldValue) {
            return '';
        }

        $arrDcaFields = \is_array($GLOBALS['TL_DCA'][$strTable]['fields'] ?? []) ? $GLOBALS['TL_DCA'][$strTable]['fields'] : [];
        $allowedFields = array_unique(array_merge(['id', 'pid', 'sorting', 'tstamp'], array_keys($arrDcaFields)));

        if (!\in_array($fieldName, $allowedFields, true)) {
            return '';
        }

        // Do only show allowed fields
        if ('password' === ($arrDcaFields[$fieldName]['inputType'] ?? false) || ($arrDcaFields[$fieldName]['eval']['doNotShow'] ?? false) || ($arrDcaFields[$fieldName]['eval']['hideInput'] ?? false)) {
            return '********';
        }

        $varFieldValue = $this->stringUtil->deserialize($varFieldValue);

        // Decrypt the value
        if ($arrDcaFields[$fieldName]['eval']['encrypt'] ?? null) {
            $passwordHasherFactory = $this->passwordHasherFactory
                ->getPasswordHasher(User::class)
            ;
            $varFieldValue = $passwordHasherFactory->hash($varFieldValue);
        }

        // Get the field value
        if ('eventState' === $fieldName) {
            $varFieldValue = '' === $varFieldValue ? '---' : $varFieldValue;
        } elseif ('mountainguide' === $fieldName) {
            $varFieldValue = $GLOBALS['TL_LANG'][$strTable]['mountainguide_reference'][(int) $varFieldValue];
        } elseif ('eventDates' === $fieldName) {
            if (!empty($varFieldValue) && \is_array($varFieldValue)) {
                $arrDate = [];

                foreach ($varFieldValue as $arrTstamp) {
                    $arrDate[] = $this->date->parse('D, d.m.Y', $arrTstamp['new_repeat']);
                }
                $varFieldValue = implode('<br>', $arrDate);
            }
        } elseif ('tourProfile' === $fieldName) {
            // Special treatment for tourProfile
            $arrProfile = [];
            $m = 0;

            if (!empty($varFieldValue) && \is_array($varFieldValue)) {
                foreach ($varFieldValue as $profile) {
                    ++$m;

                    if (\count($varFieldValue) > 1) {
                        $pattern = $m.'. Tag &nbsp;&nbsp;&nbsp; Aufstieg: %s m/%s h &nbsp;&nbsp;&nbsp;Abstieg: %s m/%s h';
                    } else {
                        $pattern = 'Aufstieg: %s m/%s h &nbsp;&nbsp;&nbsp;Abstieg: %s m/%s h';
                    }

                    $arrProfile[] = sprintf($pattern, $profile['tourProfileAscentMeters'], $profile['tourProfileAscentTime'], $profile['tourProfileDescentMeters'], $profile['tourProfileDescentTime']);
                }
            }

            if (!empty($arrProfile)) {
                $varFieldValue = implode('<br>', $arrProfile);
            }
        } elseif ('instructor' === $fieldName) {
            // Special treatment for instructor
            $arrInstructors = [];

            foreach ($varFieldValue as $arrInstructor) {
                if ($arrInstructor['instructorId'] > 0) {
                    $objUser = $this->userModel->findByPk($arrInstructor['instructorId']);

                    if (null !== $objUser) {
                        $arrInstructors[] = $objUser->name;
                    }
                }
            }

            if (!empty($arrInstructors)) {
                $varFieldValue = implode('<br>', $arrInstructors);
            }
        } elseif ('tourTechDifficulty' === $fieldName) {
            // Special treatment for tourTechDifficulty
            $arrDiff = [];

            foreach ($varFieldValue as $difficulty) {
                $strDiff = '';

                if (\strlen((string) $difficulty['tourTechDifficultyMin']) && \strlen($difficulty['tourTechDifficultyMax'])) {
                    $strMin = $this->connection->fetchOne('SELECT shortcut FROM tl_tour_difficulty WHERE id = ?', [$difficulty['tourTechDifficultyMin']]);

                    if ($strMin) {
                        $strDiff = $strMin;
                    }

                    $strMax = $this->connection->fetchOne('SELECT shortcut FROM tl_tour_difficulty WHERE id = ?', [$difficulty['tourTechDifficultyMax']]);

                    if ($strMax) {
                        $strDiff .= ' - '.$strMax;
                    }

                    $arrDiff[] = $strDiff;
                } elseif (\strlen((string) $difficulty['tourTechDifficultyMin'])) {
                    $strMin = $this->connection->fetchOne('SELECT shortcut FROM tl_tour_difficulty WHERE id = ?', [$difficulty['tourTechDifficultyMin']]);

                    if ($strMin) {
                        $strDiff = $strMin;
                    }

                    $arrDiff[] = $strDiff;
                }
            }

            if (!empty($arrDiff)) {
                $varFieldValue = implode(', ', $arrDiff);
            }
        } elseif (isset($arrDcaFields[$fieldName]['foreignKey'])) {
            $temp = [];
            $chunks = explode('.', $arrDcaFields[$fieldName]['foreignKey'], 2);

            foreach ((array) $varFieldValue as $v) {
                // Use \Contao\Database::quoteIdentifier instead of Doctrine\DBAL\Connection::quoteIdentifier
                // because only Contao can handle chained foreign keys like this:
                // 'foreignKey' => "tl_user.CONCAT(lastname, ' ', firstname, ', ', city)",
                $keyValue = $this->connection->fetchOne('SELECT '.Database::quoteIdentifier($chunks[1]).' AS value FROM '.$chunks[0].' WHERE id = ?', [$v]);

                if ($keyValue) {
                    $temp[] = $keyValue;
                }
            }

            $varFieldValue = implode(', ', $temp);
        } elseif (($arrDcaFields[$fieldName]['inputType'] ?? null) === 'fileTree') {
            if (\is_array($varFieldValue)) {
                foreach ($varFieldValue as $kk => $vv) {
                    if (($objFile = $this->filesModel->findByUuid($vv)) instanceof FilesModel) {
                        $varFieldValue[$kk] = $objFile->path.' ('.$this->stringUtil->binToUuid($vv).')';
                    } else {
                        $varFieldValue[$kk] = '';
                    }
                }

                $varFieldValue = implode(', ', $varFieldValue);
            } elseif (($objFile = $this->filesModel->findByUuid($varFieldValue)) instanceof FilesModel) {
                $varFieldValue = $objFile->path.' ('.$this->stringUtil->binToUuid($varFieldValue).')';
            } else {
                $varFieldValue = '';
            }
        } elseif (\is_array($varFieldValue)) {
            if (isset($varFieldValue['value'], $varFieldValue['unit']) && 2 === \count($varFieldValue)) {
                $varFieldValue = trim($varFieldValue['value'].', '.$varFieldValue['unit']);
            } else {
                foreach ($varFieldValue as $kk => $vv) {
                    if (\is_array($vv)) {
                        $values = array_values($vv);
                        $varFieldValue[$kk] = array_shift($values).' ('.implode(', ', array_filter($values)).')';
                    }
                }

                if ($this->arrayUtil->isAssoc($varFieldValue)) {
                    foreach ($varFieldValue as $kk => $vv) {
                        $varFieldValue[$kk] = $kk.': '.$vv;
                    }
                }

                $varFieldValue = implode(', ', $varFieldValue);
            }
        } elseif (($arrDcaFields[$fieldName]['eval']['rgxp'] ?? null) === 'date') {
            $varFieldValue = $varFieldValue ? $this->date->parse($this->config->get('dateFormat'), $varFieldValue) : '-';
        } elseif (($arrDcaFields[$fieldName]['eval']['rgxp'] ?? null) === 'time') {
            $varFieldValue = $varFieldValue ? $this->date->parse($this->config->get('timeFormat'), $varFieldValue) : '-';
        } elseif ('tstamp' === $fieldName || ($arrDcaFields[$fieldName]['eval']['rgxp'] ?? null) === 'datim' || \in_array($arrDcaFields[$fieldName]['flag'] ?? null, [DataContainer::SORT_DAY_ASC, DataContainer::SORT_DAY_DESC, DataContainer::SORT_MONTH_ASC, DataContainer::SORT_MONTH_DESC, DataContainer::SORT_YEAR_ASC, DataContainer::SORT_YEAR_DESC], true)) {
            $varFieldValue = $varFieldValue ? $this->date->parse($this->config->get('datimFormat'), $varFieldValue) : '-';
        } elseif (($arrDcaFields[$fieldName]['eval']['isBoolean'] ?? null) || (($arrDcaFields[$fieldName]['inputType'] ?? null) === 'checkbox' && !($arrDcaFields[$fieldName]['eval']['multiple'] ?? null))) {
            $varFieldValue = $varFieldValue ? $GLOBALS['TL_LANG']['MSC']['yes'] : $GLOBALS['TL_LANG']['MSC']['no'];
        } elseif (($arrDcaFields[$fieldName]['eval']['rgxp'] ?? null) === 'email') {
            $varFieldValue = $this->idna->decodeEmail($varFieldValue);
        } elseif (($arrDcaFields[$fieldName]['inputType'] ?? null) === 'textarea' && (($arrDcaFields[$fieldName]['eval']['allowHtml'] ?? null) || ($arrDcaFields[$fieldName]['eval']['preserveTags'] ?? null))) {
            $varFieldValue = $this->stringUtil->specialchars($varFieldValue);
        } elseif (\is_array($arrDcaFields[$fieldName]['reference'] ?? null)) {
            $varFieldValue = isset($arrDcaFields[$fieldName]['reference'][$varFieldValue]) ? (\is_array($arrDcaFields[$fieldName]['reference'][$varFieldValue]) ? $arrDcaFields[$fieldName]['reference'][$varFieldValue][0] : $arrDcaFields[$fieldName]['reference'][$varFieldValue]) : $varFieldValue;
        } elseif (($arrDcaFields[$fieldName]['eval']['isAssociative'] ?? null) || $this->arrayUtil->isAssoc($arrDcaFields[$fieldName]['options'] ?? null)) {
            $varFieldValue = $arrDcaFields[$fieldName]['options'][$varFieldValue] ?? null;
        }

        // Label and help
        if (isset($arrDcaFields[$fieldName]['label'])) {
            $label = $arrDcaFields[$fieldName]['label'][0] ?? $fieldName;
            $help = $arrDcaFields[$fieldName]['label'][1] ?? $fieldName;
        } else {
            $label = isset($GLOBALS['TL_LANG']['MSC'][$fieldName]) && \is_array($GLOBALS['TL_LANG']['MSC'][$fieldName]) ? $GLOBALS['TL_LANG']['MSC'][$fieldName][0] : $GLOBALS['TL_LANG']['MSC'][$fieldName];
            $help = isset($GLOBALS['TL_LANG']['MSC'][$fieldName]) && \is_array($GLOBALS['TL_LANG']['MSC'][$fieldName]) ? $GLOBALS['TL_LANG']['MSC'][$fieldName][1] : $GLOBALS['TL_LANG']['MSC'][$fieldName];
        }

        if (empty($label)) {
            $label = $fieldName;
        }

        if (!empty($help)) {
            $help = '<p class="tl_help tl_tip tl_full_height">'.$help.'</p>';
        }

        return '
<div class="clr readonly">
    <h3><label for="ctrl_title">'.$label.'</label></h3>
    <div class="field-content-box" data-field="'.$this->stringUtil->specialchars($fieldName).'">'.(string) $varFieldValue.'</div>
'.$help.'
</div>';
    }

    #[AsCallback(table: 'tl_calendar_events', target: 'fields.eventDates.load', priority: 100)]
    public function transformTimestampsToDates(string|null $varValue, DataContainer $dc): array
    {
        $arrValues = $this->stringUtil->deserialize($varValue, true);

        if (isset($arrValues[0])) {
            if ($arrValues[0]['new_repeat'] <= 0) {
                // Replace invalid date with empty array
                $arrValues = [];
            }
        } else {
            $arrValues = [];
        }

        return $arrValues;
    }

    #[AsCallback(table: 'tl_calendar_events', target: 'edit.buttons', priority: 100)]
    public function editButtons($arrButtons, $dc)
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
            'SELECT id, CONCAT(name, ", ", city) FROM tl_user WHERE disable = ? ORDER BY name',
            [0],
        );
    }

    /**
     * Options callback for Multi Column Wizard field tl_calendar_events.tourTechDifficulty.
     *
     * @throws Exception
     */
    public function getTourDifficulties(): array
    {
        $options = [];
        $stmt = $this->connection->executeQuery('SELECT * FROM tl_tour_difficulty ORDER BY pid, code');

        while (false !== ($row = $stmt->fetchAssociative())) {
            $objDiffCat = TourDifficultyCategoryModel::findByPk($row['pid']);

            if (null !== $objDiffCat) {
                if ('' !== $objDiffCat->title) {
                    if (!isset($options[$objDiffCat->title])) {
                        $options[$objDiffCat->title] = [];
                    }

                    $options[$objDiffCat->title][$row['id']] = $row['shortcut'];
                }
            }
        }

        return $options;
    }

    /**
     * Options callback for Multi Column Wizard field tl_calendar_events.instructor.
     *
     * @throws Exception
     */
    public function listInstructors(): array
    {
        $options = [];
        $stmt = $this->connection->executeQuery('SELECT id,firstname,lastname,city FROM tl_user WHERE disable = ? && lastname != ? && firstname != ? ORDER BY lastname', [0, '', '']);

        while (false !== ($row = $stmt->fetchAssociative())) {
            $options[$row['id']] = $row['lastname'].' '.$row['firstname'].', '.$row['city'];
        }

        return $options;
    }

    /**
     * @throws \Exception
     */
    #[AsCallback(table: 'tl_calendar_events', target: 'fields.eventType.options', priority: 70)]
    public function getEventTypes(DataContainer|null $dc): array
    {
        $options = [];
        $user = $this->security->getUser();

        if (!$dc) {
            return $options;
        }

        if (!$dc->id && $dc->currentPid > 0) {
            $objCalendar = $this->calendarModel->findByPk($dc->currentPid);
        } elseif ($dc->id > 0) {
            $objCalendar = $this->calendarEventsModel->findByPk($dc->id)->getRelated('pid');
        }

        $arrAllowedEventTypes = [];

        if (isset($objCalendar) && null !== $user) {
            $arrGroups = $this->stringUtil->deserialize($user->groups, true);

            foreach ($arrGroups as $group) {
                $objGroup = UserGroupModel::findByPk($group);

                if (null !== $objGroup && !empty($objGroup->allowedEventTypes) && \is_array($objGroup->allowedEventTypes)) {
                    $arrAllowedEvtTypes = $this->stringUtil->deserialize($objGroup->allowedEventTypes, true);

                    foreach ($arrAllowedEvtTypes as $eventType) {
                        if (!\in_array($eventType, $arrAllowedEventTypes, false)) {
                            $arrAllowedEventTypes[] = $eventType;
                        }
                    }
                }
            }
        }

        if (isset($objCalendar)) {
            $options = $this->stringUtil->deserialize($objCalendar->allowedEventTypes, true);
        }

        return $options;
    }

    /**
     * @throws Exception
     */
    #[AsCallback(table: 'tl_calendar_events', target: 'fields.courseTypeLevel1.options', priority: 60)]
    public function getCourseSubCategory(DataContainer $dc): array
    {
        $options = [];

        $eventId = $this->connection->fetchOne(
            'SELECT courseTypeLevel0 FROM tl_calendar_events WHERE id = ?',
            [$dc->id],
        );

        if ($eventId) {
            $stmt = $this->connection->executeQuery(
                'SELECT * FROM tl_course_sub_type WHERE pid = ? ORDER BY pid, code',
                [$eventId],
            );

            while (false !== ($row = $stmt->fetchAssociative())) {
                $options[$row['id']] = $row['code'].' '.$row['name'];
            }
        }

        return $options;
    }

    /**
     * @throws Exception
     * @throws \Exception
     */
    #[AsCallback(table: 'tl_calendar_events', target: 'fields.eventReleaseLevel.options', priority: 50)]
    public function getEventReleaseLevels(DataContainer $dc): array
    {
        // Use $GLOBALS['TL_DCA']['tl_calendar_events']['fields']['eventReleaseLevel']['foreignKey']
        // for the filter panel instead of the options callback
        $referringMethod = debug_backtrace()[2]['function'];

        if ('panel' === $referringMethod) {
            return [];
        }

        $options = [];

        $user = $this->security->getUser();
        $arrAllowedEventTypes = [];

        if ($user instanceof BackendUser) {
            if (!$this->security->isGranted('ROLE_ADMIN')) {
                $arrGroups = $this->stringUtil->deserialize($user->groups, true);

                foreach ($arrGroups as $group) {
                    $objGroup = UserGroupModel::findByPk($group);

                    if (null !== $objGroup) {
                        $arrEventTypes = $this->stringUtil->deserialize($objGroup->allowedEventTypes, true);

                        foreach ($arrEventTypes as $eventType) {
                            if (!\in_array($eventType, $arrAllowedEventTypes, false)) {
                                $arrAllowedEventTypes[] = $eventType;
                            }
                        }
                    }
                }

                foreach ($arrAllowedEventTypes as $eventType) {
                    $objEventType = EventTypeModel::findByPk($eventType);

                    if (null !== $objEventType) {
                        $objEventReleasePackage = EventReleaseLevelPolicyPackageModel::findByPk($objEventType->levelAccessPermissionPackage);

                        if (null !== $objEventReleasePackage) {
                            $stmt = $this->connection->executeQuery('SELECT * FROM tl_event_release_level_policy WHERE pid = ? ORDER BY level', [$objEventReleasePackage->id]);

                            while (false !== ($rowEventReleaseLevels = $stmt->fetchAssociative())) {
                                $options[EventReleaseLevelPolicyModel::findByPk($rowEventReleaseLevels['id'])->getRelated('pid')->title][$rowEventReleaseLevels['id']] = $rowEventReleaseLevels['title'];
                            }
                        }
                    }
                }
            } else {
                $stmt = $this->connection->executeQuery('SELECT * FROM tl_event_release_level_policy ORDER BY pid,level');

                while (false !== ($rowEventReleaseLevels = $stmt->fetchAssociative())) {
                    $options[EventReleaseLevelPolicyModel::findByPk($rowEventReleaseLevels['id'])->getRelated('pid')->title][$rowEventReleaseLevels['id']] = $rowEventReleaseLevels['title'];
                }
            }
        }

        return $options;
    }

    /**
     * Multi Column Wizard columnsCallback listFixedDates().
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
        $span = Calendar::calculateSpan($arrRow['startTime'], $arrRow['endTime']);
        $objEvent = $this->calendarEventsModel->findByPk($arrRow['id']);

        if ($span > 0) {
            $date = $this->date->parse($this->config->get(($arrRow['addTime'] ? 'datimFormat' : 'dateFormat')), $arrRow['startTime']).' – '.$this->date->parse($this->config->get(($arrRow['addTime'] ? 'datimFormat' : 'dateFormat')), $arrRow['endTime']);
        } elseif ((int) $arrRow['startTime'] === (int) $arrRow['endTime']) {
            $date = $this->date->parse($this->config->get('dateFormat'), $arrRow['startTime']).($arrRow['addTime'] ? ' '.$this->date->parse($this->config->get('timeFormat'), $arrRow['startTime']) : '');
        } else {
            $date = $this->date->parse($this->config->get('dateFormat'), $arrRow['startTime']).($arrRow['addTime'] ? ' '.$this->date->parse($this->config->get('timeFormat'), $arrRow['startTime']).' – '.$this->date->parse($this->config->get('timeFormat'), $arrRow['endTime']) : '');
        }

        // Add icon
        if ($arrRow['published']) {
            $icon = $this->image->getHtml('visible.svg', $GLOBALS['TL_LANG']['MSC']['published'], 'title="'.$GLOBALS['TL_LANG']['MSC']['published'].'"');
        } else {
            $icon = $this->image->getHtml('invisible.svg', $GLOBALS['TL_LANG']['MSC']['unpublished'], 'title="'.$GLOBALS['TL_LANG']['MSC']['unpublished'].'"');
        }

        // Add main instructor
        $strAuthor = '';
        $objUser = $this->userModel->findByPk($arrRow['mainInstructor']);

        if (null !== $objUser) {
            $strAuthor = ' <span style="color:#b3b3b3;padding-left:3px">[Hauptleiter: '.$objUser->name.']</span><br>';
        }

        $strRegistrations = $this->calendarEventsHelper->getEventStateOfSubscriptionBadgesString($objEvent);

        if ('' !== $strRegistrations) {
            $strRegistrations = '<br>'.$strRegistrations;
        }

        // Add event release level
        $strLevel = '';
        $eventReleaseLevelModel = EventReleaseLevelPolicyModel::findByPk($arrRow['eventReleaseLevel']);

        if (null !== $eventReleaseLevelModel) {
            $strLevel = sprintf(
                '<span class="release-level-%s text-decoration-underline" title="Freigabestufe: %s">FS: %s</span> ',
                $eventReleaseLevelModel->level,
                $eventReleaseLevelModel->title,
                $eventReleaseLevelModel->level,
            );
        }

        return sprintf(
            '<div class="tl_content_left">%s %s%s <span style="color:#999;padding-left:3px">[%s]</span>%s%s</div>',
            $icon,
            $strLevel,
            $arrRow['title'],
            $date,
            $strAuthor,
            $strRegistrations,
        );
    }

    /**
     * Update main instructor (the first instructor in the list is the main instructor).
     *
     * @throws Exception
     */
    #[AsCallback(table: 'tl_calendar_events', target: 'fields.instructor.save', priority: 100)]
    public function setMainInstructor(string|null $varValue, DataContainer $dc): string|null
    {
        if ($dc->id > 0) {
            $arrInstructors = $this->stringUtil->deserialize($varValue, true);

            // Use a child table to store instructors
            // Delete instructor
            $this->connection->delete('tl_calendar_events_instructor', ['pid' => $dc->id]);

            $i = 0;

            foreach ($arrInstructors as $arrInstructor) {
                // Rebuild instructor table
                $set = [
                    'pid' => $dc->id,
                    'userId' => $arrInstructor['instructorId'],
                    'tstamp' => time(),
                    'isMainInstructor' => $i < 1 ? 1 : 0,
                ];

                $this->connection->insert('tl_calendar_events_instructor', $set);

                ++$i;
            }
            // End child insert

            if (\count($arrInstructors) > 0) {
                $intInstructor = $arrInstructors[0]['instructorId'];

                if (null !== $this->userModel->findByPk($intInstructor)) {
                    $set = ['mainInstructor' => $intInstructor];

                    $this->connection->update('tl_calendar_events', $set, ['id' => $dc->id]);

                    return $varValue;
                }
            }

            $set = ['mainInstructor' => 0];

            $this->connection->update('tl_calendar_events', $set, ['id' => $dc->id]);
        }

        return $varValue;
    }

    /**
     * Publish or un-publish events if eventReleaseLevel has reached the highest/lowest level.
     *
     * @throws \Exception
     */
    #[AsCallback(table: 'tl_calendar_events', target: 'fields.eventReleaseLevel.save', priority: 90)]
    public function saveCallbackEventReleaseLevel(int $targetEventReleaseLevelId, DataContainer $dc): int
    {
        return $this->eventReleaseLevelUtil->publishOrUnpublishEventDependingOnEventReleaseLevel((int) $dc->activeRecord->id, $targetEventReleaseLevelId);
    }

    /**
     * Don't allow tourTechDifficultyMax to be equal to tourTechDifficultyMin.
     *
     * @param $value
     * @param DataContainer $dc
     */
    #[AsCallback(table: 'tl_calendar_events', target: 'fields.tourTechDifficulty.save', priority: 90)]
    public function setCorrectTourTechDifficulty(string $value, DataContainer $dc): string
    {
        $arrValue = $this->stringUtil->deserialize($value, true);
        $hasUpdate = false;

        if (!empty($arrValue)) {
            foreach ($arrValue as $i => $tourTechDiff) {
                if (isset($tourTechDiff['tourTechDifficultyMin'], $tourTechDiff['tourTechDifficultyMax']) && $tourTechDiff['tourTechDifficultyMin'] === $tourTechDiff['tourTechDifficultyMax']) {
                    $arrValue[$i]['tourTechDifficultyMax'] = '';
                    $hasUpdate = true;
                }
            }

            if ($hasUpdate) {
                return serialize($arrValue);
            }
        }

        return $value;
    }

    /**
     * @throws \Exception
     */
    #[AsCallback(table: 'tl_calendar_events', target: 'fields.eventType.save', priority: 80)]
    public function saveCallbackEventType(string $strEventType, DataContainer $dc, int $intId = null): string
    {
        if ('' !== $strEventType) {
            if ($dc->activeRecord->id > 0) {
                $objEvent = $this->calendarEventsModel->findByPk($dc->activeRecord->id);
            } else {
                $objEvent = $this->calendarEventsModel->findByPk($intId);
            }

            if (null === $objEvent) {
                throw new \Exception('Event not found.');
            }

            // !important, because if the eventType is not saved, no eventReleaseLevel can be assigned
            $objEvent->eventType = $strEventType;
            $objEvent->save();

            if (null === EventReleaseLevelPolicyModel::findByPk($objEvent->eventReleaseLevel)) {
                $objEventReleaseModel = EventReleaseLevelPolicyModel::findLowestLevelByEventId($objEvent->id);

                if (null !== $objEventReleaseModel) {
                    $objEvent->eventReleaseLevel = $objEventReleaseModel->id;
                    $objEvent->save();
                }
            }
        }

        return $strEventType;
    }
}
