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

namespace Markocupic\SacEventToolBundle;

use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;
use Contao\Calendar;
use Contao\CalendarEventsJourneyModel;
use Contao\CalendarEventsMemberModel;
use Contao\CalendarEventsModel;
use Contao\Config;
use Contao\ContentGallery;
use Contao\ContentModel;
use Contao\Controller;
use Contao\CoreBundle\Util\SymlinkUtil;
use Contao\CourseMainTypeModel;
use Contao\CourseSubTypeModel;
use Contao\Database;
use Contao\Date;
use Contao\EventOrganizerModel;
use Contao\Events;
use Contao\EventTypeModel;
use Contao\FilesModel;
use Contao\Folder;
use Contao\FrontendTemplate;
use Contao\MemberModel;
use Contao\PageModel;
use Contao\StringUtil;
use Contao\System;
use Contao\Template;
use Contao\TourDifficultyModel;
use Contao\TourTypeModel;
use Contao\UserModel;
use Haste\Util\Url;
use Markocupic\SacEventToolBundle\Config\EventSubscriptionLevel;

class CalendarEventsHelper
{
    /**
     * @throws \Exception
     *
     * @return array|bool|CalendarEventsModel|int|mixed|string|null
     */
    public static function getEventData(CalendarEventsModel $objEvent, string $strProperty, Template $objTemplate = null)
    {
        // Load language files
        Controller::loadLanguageFile('tl_calendar_events');
        Controller::loadLanguageFile('default');
        $value = '';

        // Add arguments separated by two pipes -> eventImage||5
        $arrArgs = explode('||', $strProperty);

        switch ($arrArgs[0]) {
            case 'model':
                $value = $objEvent;
                break;

            case 'id':
                $value = $objEvent->id;
                break;

            case 'eventId':
                $value = sprintf('%s-%s', Date::parse('Y', $objEvent->startDate), $objEvent->id);
                break;

            case 'eventTitle':
                $parser = System::getContainer()->get('contao.insert_tag.parser');
                $value = $parser->replace(sprintf('{{event_title::%s}}', $objEvent->id));
                break;

            case 'eventUrl':
                $parser = System::getContainer()->get('contao.insert_tag.parser');
                $value = $parser->replace(sprintf('{{event_url::%s}}', $objEvent->id));
                break;

            case 'tourTypesIds':
                $value = implode('', StringUtil::deserialize($objEvent->tourType, true));
                break;

            case 'tourTypesShortcuts':
                $value = implode(' ', static::getTourTypesAsArray($objEvent, 'shortcut', true));
                break;

            case 'tourTypesTitles':
                $value = implode('<br>', static::getTourTypesAsArray($objEvent, 'title'));
                break;

            case 'startDateDay':
                $value = Date::parse('d', $objEvent->startDate);
                break;

            case 'startDateMonth':
                $value = Date::parse('M', $objEvent->startDate);
                break;

            case 'startDateYear':
                $value = Date::parse('y', $objEvent->startDate);
                break;

            case 'endDateDay':
                $value = Date::parse('d', $objEvent->endDate);
                break;

            case 'endDateMonth':
                $value = Date::parse('M', $objEvent->endDate);
                break;

            case 'endDateYear':
                $value = Date::parse('y', $objEvent->endDate);
                break;

            case 'eventPeriodSmTooltip':
            case 'eventPeriodSm':
                $value = static::getEventPeriod($objEvent, 'd.m.Y', false);
                break;

            case 'eventPeriodLgInline':
                $value = static::getEventPeriod($objEvent, 'D, d.m.Y', false, true, true);
                break;

            case 'eventPeriodLgTooltip':
            case 'eventPeriodLg':
                $value = static::getEventPeriod($objEvent, 'D, d.m.Y', false);
                break;

            case 'eventDuration':
                $value = static::getEventDuration($objEvent);
                break;

            case 'registrationStartDateFormatted':
                $value = Date::parse(Config::get('dateFormat'), $objEvent->registrationStartDate);
                break;

            case 'registrationEndDateFormatted':
                // If registration end time! is set to default --> 23:59 then only show registration end date!
                $endDate = Date::parse(Config::get('dateFormat'), $objEvent->registrationEndDate);

                if (abs($objEvent->registrationEndDate - strtotime($endDate)) === (24 * 3600) - 60) {
                    $formatedEndDate = Date::parse(Config::get('dateFormat'), $objEvent->registrationEndDate);
                } else {
                    $formatedEndDate = Date::parse(Config::get('datimFormat'), $objEvent->registrationEndDate);
                }
                $value = $formatedEndDate;
                break;

            case 'eventState':
                $value = static::getEventState($objEvent);
                break;

            case 'eventStateLabel':
                $value = '' !== $GLOBALS['TL_LANG']['MSC']['calendar_events'][static::getEventState($objEvent)] ? $GLOBALS['TL_LANG']['MSC']['calendar_events'][static::getEventState($objEvent)] : static::getEventState($objEvent);
                break;

            case 'isLastMinuteTour':
                $value = 'lastMinuteTour' === $objEvent->eventType;
                break;

            case 'isTour':
                $value = 'tour' === $objEvent->eventType;
                break;

            case 'isGeneralEvent':
                $value = 'generalEvent' === $objEvent->eventType;
                break;

            case 'isCourse':
                $value = 'course' === $objEvent->eventType;
                break;

            case 'bookingCounter':
                $value = static::getBookingCounter($objEvent);
                break;

            case 'tourTechDifficulties':
                $value = implode(' ', static::getTourTechDifficultiesAsArray($objEvent, true));
                break;

            case 'instructors':
                $value = implode(', ', static::getInstructorNamesAsArray($objEvent));
                break;

            case 'journey':
                $value = null !== CalendarEventsJourneyModel::findByPk($objEvent->journey) ? CalendarEventsJourneyModel::findByPk($objEvent->journey)->title : '';
                break;

            case 'instructorsWithQualification':
                $value = implode(', ', static::getInstructorNamesAsArray($objEvent, true));
                break;

            case 'courseTypeLevel1':
                $value = $objEvent->courseTypeLevel1;
                break;

            case 'eventImagePath':
                $value = static::getEventImagePath($objEvent);
                break;

            case 'eventImage':
                if (isset($arrArgs[1])) {
                    $pictureSize = $arrArgs[1];
                    $src = static::getEventImagePath($objEvent);
                    $parser = System::getContainer()->get('contao.insert_tag.parser');
                    $value = $parser->replace(sprintf('{{picture::%s?size=%s}}', $src, $pictureSize));
                }
                break;

            case 'courseTypeLevel0Name':
                $value = CourseMainTypeModel::findByPk($objEvent->courseTypeLevel0)->name;
                break;

            case 'courseTypeLevel1Name':
                $value = CourseSubTypeModel::findByPk($objEvent->courseTypeLevel1)->name;
                break;

            case 'eventOrganizerLogos':
                $value = implode('', static::getEventOrganizersLogoAsHtml($objEvent, '{{image::%s?width=60}}'));
                break;

            case 'eventOrganizers':
                $value = implode('<br>', static::getEventOrganizersAsArray($objEvent));
                break;

            case 'mainInstructorContactDataFromDb':
                $value = static::generateMainInstructorContactDataFromDb($objEvent);
                break;

            case 'instructorContactBoxes':
                $value = static::generateInstructorContactBoxes($objEvent, (int) $arrArgs[1]);
                break;

            case 'arrTourProfile':
                $value = static::getTourProfileAsArray($objEvent);
                break;

            case 'gallery':
                $value = static::getGallery([
                    'multiSRC' => $objEvent->multiSRC,
                    'orderSRC' => $objEvent->orderSRC,
                    'sortBy' => 'custom',
                    'perRow' => 4,
                    'size' => serialize([400, 400, 'center_center', 'proportional']),
                    'fullsize' => true,
                    'galleryTpl' => 'gallery_bootstrap_col-4',
                ]);
                break;

            default:
                $arrEvent = $objEvent->row();

                if (null !== $objTemplate && isset($objTemplate->{$arrArgs[0]})) {
                    $value = $objTemplate->{$arrArgs[0]};
                } elseif (isset($arrEvent[$arrArgs[0]])) {
                    $value = $arrEvent[$arrArgs[0]];
                } else {
                    $value = '';
                }
        }

        return $value;
    }

    /**
     * @throws \Exception
     */
    public static function addEventDataToTemplate(FrontendTemplate $objTemplate): void
    {
        $objEvent = CalendarEventsModel::findByPk($objTemplate->id);

        if (null !== $objEvent) {
            $objTemplate->getEventData = (
                static function ($prop) use (&$objEvent, &$objTemplate) {
                    return static::getEventData($objEvent, $prop, $objTemplate);
                }
            );
        }
    }

    public static function generateInstructorContactBoxes(CalendarEventsModel $objEvent, int $jumpTo): string
    {
        $strHtml = '';

        $arrInstructors = static::getInstructorsAsArray($objEvent);

        foreach ($arrInstructors as $userId) {
            $strHtml .= '<div class="mb-4 col-6 col-sm-4 col-md-6 col-xl-4"><div class="">';

            $objUser = UserModel::findByPk($userId);

            if (null !== $objUser) {
                $objPictureTpl = new FrontendTemplate('picture_default');
                $objPictureTpl->setData(generateAvatar($userId, 18));
                $parser = System::getContainer()->get('contao.insert_tag.parser');

                $strHtml .= '<div class="image_container portrait">';
                $strHtml .= sprintf('<a href="%s?username=%s" title="Leiter Portrait ansehen">', $parser->replace('{{link_url::'.$jumpTo.'}}'), UserModel::findByPk($userId)->username);
                $strHtml .= sprintf('<figure class="avatar-large">%s</figure>', $objPictureTpl->parse());
                $strHtml .= '</a></div>';
                // End image

                // Start instructor name
                $strHtml .= '<div class="instructor-name">';
                $strQuali = '';

                if ('' !== static::getMainQualification($objUser)) {
                    $strQuali .= ' ('.static::getMainQualification($objUser).')';
                }

                if (!$objUser->hideInFrontendListings) {
                    $parser = System::getContainer()->get('contao.insert_tag.parser');
                    $strHtml .= sprintf('<a href="%s?username=%s" title="Leiter Portrait ansehen">', $parser->replace('{{link_url::leiter-portrait}}'), $objUser->username);
                }

                $strHtml .= sprintf('%s %s%s', $objUser->lastname, $objUser->firstname, $strQuali);

                if (!$objUser->hideInFrontendListings) {
                    $strHtml .= '</a>';
                }

                if (FE_USER_LOGGED_IN && !$objUser->hideInFrontendListings) {
                    $arrContact = ['phone', 'mobile', 'email'];

                    foreach ($arrContact as $field) {
                        if ('' !== $objUser->{$field}) {
                            $strHtml .= sprintf('<div class="ce_user_portrait_%s">', $field);
                            $strHtml .= sprintf('<small title="%s">%s</small>', $objUser->{$field}, $objUser->{$field});
                            $strHtml .= '</div>';
                        }
                    }
                }
                $strHtml .= '</div>';
                // End instructor name
            }
            $strHtml .= '</div></div>';
        }

        return $strHtml;
    }

    /**
     * @throws \Exception
     */
    public static function getEventState(CalendarEventsModel $objEvent): string
    {
        $objEventsMember = Database::getInstance()
            ->prepare('SELECT COUNT(id) AS registrationCount FROM tl_calendar_events_member WHERE eventId = ? AND stateOfSubscription = ?')
            ->execute($objEvent->id, EventSubscriptionLevel::SUBSCRIPTION_ACCEPTED)
        ;

        $registrationCount = $objEventsMember->registrationCount;

        // Event canceled
        if ('event_canceled' === $objEvent->eventState) {
            return 'event_status_4';
        }

        // Event deferred
        if ('event_deferred' === $objEvent->eventState) {
            return 'event_status_6';
        }

        // Event is fully booked/instructor has explicitly set the "is fully booked" label in the backend
        if ('event_fully_booked' === $objEvent->eventState) {
            return 'event_status_3';
        }

        // Event is over or booking is no more possible
        if ($objEvent->startDate <= time() || ($objEvent->setRegistrationPeriod && $objEvent->registrationEndDate < time())) {
            return 'event_status_2';
        }

        // Max participant number reached -> waiting list still possible
        if ($objEvent->maxMembers > 0 && $registrationCount >= $objEvent->maxMembers) {
            return 'event_status_8';
        }

        // Booking not possible yet
        if ($objEvent->setRegistrationPeriod && $objEvent->registrationStartDate > time()) {
            return 'event_status_5';
        }

        // If online registration is disabled in the event settings
        if ($objEvent->disableOnlineRegistration) {
            return 'event_status_7';
        }

        return 'event_status_1';
    }

    public static function eventIsFullyBooked(CalendarEventsModel $objEvent): bool
    {
        $objEventsMember = Database::getInstance()
            ->prepare('SELECT * FROM tl_calendar_events_member WHERE eventId = ? AND stateOfSubscription = ?')
            ->execute($objEvent->id, EventSubscriptionLevel::SUBSCRIPTION_ACCEPTED)
        ;

        $registrationCount = $objEventsMember->numRows;

        if ('event_fully_booked' === $objEvent->eventState || ($objEvent->maxMembers > 0 && $registrationCount >= $objEvent->maxMembers)) {
            return true;
        }

        return false;
    }

    public static function getMainInstructor(CalendarEventsModel $objEvent): ?UserModel
    {
        $objInstructor = Database::getInstance()
            ->prepare('SELECT * FROM tl_calendar_events_instructor WHERE pid = ? AND isMainInstructor = ?')
            ->limit(1)
            ->execute($objEvent->id, '1')
        ;

        if ($objInstructor->numRows) {
            return UserModel::findByPk($objInstructor->userId);
        }

        return null;
    }

    public static function getMainInstructorName(CalendarEventsModel $objEvent): string
    {
        $strName = '';

        $objUser = self::getMainInstructor($objEvent);

        if (null !== $objUser) {
            $arrName = [];
            $arrName[] = $objUser->lastname;
            $arrName[] = $objUser->firstname;
            $arrName = array_filter($arrName);
            $strName = implode(' ', $arrName);
        }

        return $strName;
    }

    public static function generateMainInstructorContactDataFromDb(CalendarEventsModel $objEvent): string
    {
        $arrInstructors = static::getInstructorsAsArray($objEvent, false);
        $objUser = UserModel::findByPk($arrInstructors[0]);

        if (null !== $objUser) {
            $arrContact = [];
            $arrContact[] = sprintf('<strong>%s %s</strong>', $objUser->lastname, $objUser->firstname);
            $arrContact[] = sprintf('Tel.: %s', $objUser->phone);
            $arrContact[] = sprintf('Mobile: %s', $objUser->mobile);
            $arrContact[] = sprintf('E-Mail: %s', $objUser->email);
            $arrContact = array_filter($arrContact);

            return implode(', ', $arrContact);
        }

        return '';
    }

    public static function getInstructorsAsArray(CalendarEventsModel $objEvent, bool $blnShowPublishedOnly = true): array
    {
        $arrInstructors = [];

        // Get all instructors from an event, list mainInstructor first
        $objInstructor = Database::getInstance()
            ->prepare('SELECT * FROM tl_calendar_events_instructor WHERE pid = ? ORDER BY isMainInstructor DESC')
            ->execute($objEvent->id)
        ;

        while ($objInstructor->next()) {
            $objUser = UserModel::findByPk($objInstructor->userId);

            if (null !== $objUser) {
                if (true === $blnShowPublishedOnly && $objUser->disable) {
                    continue;
                }
                $arrInstructors[] = $objUser->id;
            }
        }

        return $arrInstructors;
    }

    public static function getInstructorNamesAsArray(CalendarEventsModel $objEvent, bool $blnAddMainQualification = false, bool $blnShowPublishedOnly = true): array
    {
        $arrInstructors = [];

        $arrUsers = static::getInstructorsAsArray($objEvent, $blnShowPublishedOnly);

        foreach ($arrUsers as $userId) {
            $objUser = UserModel::findByPk($userId);

            if (null !== $objUser) {
                if (true === $blnShowPublishedOnly && $objUser->disable) {
                    continue;
                }

                $strName = trim($objUser->lastname.' '.$objUser->firstname);

                if ($blnAddMainQualification && '' !== static::getMainQualification($objUser)) {
                    $arrInstructors[] = $strName.' ('.static::getMainQualification($objUser).')';
                } else {
                    $arrInstructors[] = $strName;
                }
            }
        }

        return $arrInstructors;
    }

    public static function getMainQualification(UserModel $objUser): string
    {
        $strQuali = '';

        $arrQuali = StringUtil::deserialize($objUser->leiterQualifikation, true);

        if (!empty($arrQuali[0])) {
            $strQuali = $GLOBALS['TL_CONFIG']['SAC-EVENT-TOOL-CONFIG']['leiterQualifikation'][$arrQuali[0]];
        }

        return $strQuali;
    }

    public static function getGallery(array $arrData): string
    {
        $arrData['type'] = 'gallery';
        $arrData['tstamp'] = time();

        if (!isset($arrData['perRow']) || $arrData['perRow'] < 1) {
            $arrData['perRow'] = 1;
        }

        $objModel = new ContentModel();
        $objModel->setRow($arrData);

        $objGallery = new ContentGallery($objModel, 'main');
        $strBuffer = $objGallery->generate();

        $objModel->delete();

        // HOOK: add custom logic
        if (isset($GLOBALS['TL_HOOKS']['getContentElement']) && \is_array($GLOBALS['TL_HOOKS']['getContentElement'])) {
            foreach ($GLOBALS['TL_HOOKS']['getContentElement'] as $callback) {
                $strBuffer = System::importStatic($callback[0])->{$callback[1]}($objModel, $strBuffer, $objGallery);
            }
        }

        return $strBuffer;
    }

    public static function getEventImagePath(CalendarEventsModel $objEvent): string
    {
        // Get root dir
        $rootDir = System::getContainer()->getParameter('kernel.project_dir');
        System::getContainer()->get('contao.framework')->initialize();

        if ('' !== $objEvent->singleSRC) {
            $objFile = FilesModel::findByUuid($objEvent->singleSRC);

            if (null !== $objFile && is_file($rootDir.'/'.$objFile->path)) {
                return $objFile->path;
            }
        }

        return Config::get('SAC_EVT_EVENT_DEFAULT_PREVIEW_IMAGE_SRC');
    }

    /**
     * @throws \Exception
     */
    public static function getEventPeriod(CalendarEventsModel $objEvent, string $dateFormat = '', bool $blnAppendEventDuration = true, bool $blnTooltip = true, bool $blnInline = false): string
    {
        if (empty($dateFormat)) {
            $dateFormat = Config::get('dateFormat');
        }

        $dateFormatShortened = $dateFormat;

        if ('d.m.Y' === $dateFormat) {
            $dateFormatShortened = 'd.m.';
        }

        $eventDuration = \count(self::getEventTimestamps($objEvent));
        $span = Calendar::calculateSpan(self::getStartDate($objEvent), self::getEndDate($objEvent)) + 1;

        if (1 === $eventDuration) {
            return Date::parse($dateFormat, self::getStartDate($objEvent)).($blnAppendEventDuration ? ' ('.self::getEventDuration($objEvent).')' : '');
        }

        if ($span === $eventDuration) {
            // von bis
            return Date::parse($dateFormatShortened, self::getStartDate($objEvent)).' - '.Date::parse($dateFormat, self::getEndDate($objEvent)).($blnAppendEventDuration ? ' ('.self::getEventDuration($objEvent).')' : '');
        }

        $arrDates = [];
        $dates = self::getEventTimestamps($objEvent);

        foreach ($dates as $date) {
            $arrDates[] = Date::parse($dateFormat, $date);
        }

        if ($blnTooltip) {
            return Date::parse($dateFormat, self::getStartDate($objEvent)).($blnAppendEventDuration ? ' ('.self::getEventDuration($objEvent).')' : '').(!$blnInline ? '<br>' : ' ').'<a tabindex="0" class="more-date-infos" data-toggle="tooltip" data-placement="bottom" title="Eventdaten: '.implode(', ', $arrDates).'">und weitere</a>';
        }

        $dateString = '';

        foreach (self::getEventTimestamps($objEvent) as $tstamp) {
            $dateString .= sprintf('<time datetime="%s">%s</time>', Date::parse('Y-m-d', $tstamp), Date::parse('D, d.m.Y', $tstamp));
        }
        $dateString .= $blnAppendEventDuration ? sprintf('<time>(%s)</time>', self::getEventDuration($objEvent)) : '';

        return $dateString;
    }

    public static function getBookingPeriod(int $id, string $dateFormatStart = '', string $dateFormatEnd = ''): string
    {
        $objEvent = CalendarEventsModel::findByPk($id);

        if (null === $objEvent) {
            return '';
        }

        if (!$objEvent->setRegistrationPeriod) {
            return '';
        }

        if ('' === $dateFormatStart) {
            $dateFormatStart = Config::get('dateFormat');
        }

        if ('' === $dateFormatEnd) {
            $dateFormatEnd = Config::get('dateFormat');
        }

        return Date::parse($dateFormatStart, $objEvent->registrationStartDate).' - '.Date::parse($dateFormatEnd, $objEvent->registrationEndDate);
    }

    public static function getEventTimestamps(CalendarEventsModel $objEvent): array
    {
        $arrRepeats = [];

        $arrDates = StringUtil::deserialize($objEvent->eventDates, true);

        foreach ($arrDates as $v) {
            $arrRepeats[] = $v['new_repeat'];
        }

        return $arrRepeats;
    }

    public static function getStartDate(CalendarEventsModel $objEvent): int
    {
        $arrDates = StringUtil::deserialize($objEvent->eventDates);

        if (!\is_array($arrDates) || empty($arrDates)) {
            return 0;
        }

        return (int) $arrDates[0]['new_repeat'];
    }

    public static function getEndDate(CalendarEventsModel $objEvent): int
    {
        $arrDates = StringUtil::deserialize($objEvent->eventDates);

        if (!\is_array($arrDates) || empty($arrDates)) {
            return 0;
        }

        return (int) $arrDates[\count($arrDates) - 1]['new_repeat'];
    }

    /**
     * @throws \Exception
     */
    public static function getEventDuration(CalendarEventsModel $objEvent): string
    {
        $arrDates = StringUtil::deserialize($objEvent->eventDates);

        if ('' !== $objEvent->durationInfo) {
            return (string) $objEvent->durationInfo;
        }

        if (!empty($arrDates) && \is_array($arrDates)) {
            return sprintf('%s Tage', \count($arrDates));
        }

        return '';
    }

    public static function getTourTechDifficultiesAsArray(CalendarEventsModel $objEvent, bool $tooltip = false): array
    {
        $arrReturn = [];

        $arrValues = StringUtil::deserialize($objEvent->tourTechDifficulty, true);

        if (!empty($arrValues)) {
            foreach ($arrValues as $difficulty) {
                $strDiff = '';
                $strDiffTitle = '';

                if (\strlen($difficulty['tourTechDifficultyMin']) && \strlen($difficulty['tourTechDifficultyMax'])) {
                    $objDiff = TourDifficultyModel::findByPk((int) $difficulty['tourTechDifficultyMin']);

                    if (null !== $objDiff) {
                        $strDiff = $objDiff->shortcut;
                        $strDiffTitle = $objDiff->title;
                    }

                    $objDiff = TourDifficultyModel::findByPk((int) $difficulty['tourTechDifficultyMax']);

                    if (null !== $objDiff) {
                        $max = $objDiff->shortcut;
                        $strDiff .= ' - '.$max;
                        $strDiffTitle .= ' - '.$objDiff->title;
                    }
                } elseif (\strlen($difficulty['tourTechDifficultyMin'])) {
                    $objDiff = TourDifficultyModel::findByPk((int) $difficulty['tourTechDifficultyMin']);

                    if (null !== $objDiff) {
                        $strDiff = $objDiff->shortcut;
                        $strDiffTitle = $objDiff->title;
                    }
                }

                if ('' !== $strDiff) {
                    if ($tooltip) {
                        $html = '<span class="badge badge-pill bg-primary" data-toggle="tooltip" data-placement="top" title="Techn. Schwierigkeit: %s">%s</span>';
                        $arrReturn[] = sprintf($html, $strDiffTitle, $strDiff);
                    } else {
                        $arrReturn[] = $strDiff;
                    }
                }
            }
        }

        return $arrReturn;
    }

    public static function getTourTypesAsArray(CalendarEventsModel $objEvent, string $field = 'shortcut', bool $tooltip = false): array
    {
        $arrReturn = [];

        $arrValues = StringUtil::deserialize($objEvent->tourType, true);

        if (!empty($arrValues) && \is_array($arrValues)) {
            foreach ($arrValues as $id) {
                $objModel = TourTypeModel::findByPk($id);

                if (null !== $objModel) {
                    if ($tooltip) {
                        $html = '<span class="badge badge-pill bg-secondary" data-toggle="tooltip" data-placement="top" title="Typ: %s">%s</span>';
                        $arrReturn[] = sprintf($html, $objModel->{'title'}, $objModel->{$field});
                    } else {
                        $arrReturn[] = $objModel->{$field};
                    }
                }
            }
        }

        return $arrReturn;
    }

    public static function getBookingCounter(CalendarEventsModel $objEvent): string
    {
        $strBadge = '<span class="badge badge-pill bg-%s" data-toggle="tooltip" data-placement="top" title="%s">%s</span>';

        $calendarEventsMember = Database::getInstance()
            ->prepare('SELECT * FROM tl_calendar_events_member WHERE eventId = ? && stateOfSubscription = ?')
            ->execute($objEvent->id, EventSubscriptionLevel::SUBSCRIPTION_ACCEPTED)
        ;

        $memberCount = $calendarEventsMember->numRows;

        if ('event_canceled' === $objEvent->eventState) {
            // Event canceled
            return '';
        }

        if ($objEvent->addMinAndMaxMembers && $objEvent->maxMembers > 0) {
            if ($memberCount >= $objEvent->maxMembers) {
                // Event fully booked
                return sprintf($strBadge, 'dark', 'ausgebucht', $memberCount.'/'.$objEvent->maxMembers);
            }

            // Free places
            return sprintf($strBadge, 'dark', sprintf('noch %s freie Plätze', $objEvent->maxMembers - $memberCount), $memberCount.'/'.$objEvent->maxMembers);
        }
        // There is no booking limit. Show registered members
        return sprintf($strBadge, 'dark', $memberCount.' bestätigte Plätze', $memberCount.'/?');

        return '';
    }

    public static function getEventStateOfSubscriptionBadgesString(CalendarEventsModel $objEvent): string
    {
        $strRegistrationsBadges = '';
        $intNotConfirmed = 0;
        $intAccepted = 0;
        $intRefused = 0;
        $intWaitlisted = 0;
        $intUnsubscribedUser = 0;

        $eventsMemberModel = CalendarEventsMemberModel::findByEventId($objEvent->id);

        if (null !== $eventsMemberModel) {
            while ($eventsMemberModel->next()) {
                if (EventSubscriptionLevel::SUBSCRIPTION_NOT_CONFIRMED === $eventsMemberModel->stateOfSubscription) {
                    ++$intNotConfirmed;
                }

                if (EventSubscriptionLevel::SUBSCRIPTION_ACCEPTED === $eventsMemberModel->stateOfSubscription) {
                    ++$intAccepted;
                }

                if (EventSubscriptionLevel::SUBSCRIPTION_REFUSED === $eventsMemberModel->stateOfSubscription) {
                    ++$intRefused;
                }

                if (EventSubscriptionLevel::SUBSCRIPTION_WAITLISTED === $eventsMemberModel->stateOfSubscription) {
                    ++$intWaitlisted;
                }

                if (EventSubscriptionLevel::USER_HAS_UNSUBSCRIBED === $eventsMemberModel->stateOfSubscription) {
                    ++$intUnsubscribedUser;
                }
            }
            $refererId = System::getContainer()->get('request_stack')->getCurrentRequest()->get('_contao_referer_id');

            $href = sprintf("'contao?do=sac_calendar_events_tool&table=tl_calendar_events_member&id=%s&rt=%s&ref=%s'", $objEvent->id, REQUEST_TOKEN, $refererId);

            if ($intNotConfirmed > 0) {
                $strRegistrationsBadges .= sprintf('<span class="subscription-badge not-confirmed blink" title="%s unbestätigte Anmeldungen" role="button" onclick="window.location.href=%s">%s</span>', $intNotConfirmed, $href, $intNotConfirmed);
            }

            if ($intAccepted > 0) {
                $strRegistrationsBadges .= sprintf('<span class="subscription-badge accepted" title="%s bestätigte Anmeldungen" role="button" onclick="window.location.href=%s">%s</span>', $intAccepted, $href, $intAccepted);
            }

            if ($intRefused > 0) {
                $strRegistrationsBadges .= sprintf('<span class="subscription-badge refused" title="%s abgelehnte Anmeldungen" role="button" onclick="window.location.href=%s">%s</span>', $intRefused, $href, $intRefused);
            }

            if ($intWaitlisted > 0) {
                $strRegistrationsBadges .= sprintf('<span class="subscription-badge waitlisted" title="%s Anmeldungen auf Warteliste" role="button" onclick="window.location.href=%s">%s</span>', $intWaitlisted, $href, $intWaitlisted);
            }

            if ($intUnsubscribedUser > 0) {
                $strRegistrationsBadges .= sprintf('<span class="subscription-badge unsubscribed-user" title="%s Abgemeldete Teilnehmer" role="button" onclick="window.location.href=%s">%s</span>', $intUnsubscribedUser, $href, $intUnsubscribedUser);
            }
        }

        return $strRegistrationsBadges;
    }

    public static function getEventOrganizersAsArray(CalendarEventsModel $objEvent, string $field = 'title'): array
    {
        $arrReturn = [];

        $arrValues = StringUtil::deserialize($objEvent->organizers, true);

        if (!empty($arrValues) && \is_array($arrValues)) {
            foreach ($arrValues as $id) {
                $objModel = EventOrganizerModel::findByPk($id);

                if (null !== $objModel) {
                    $arrReturn[] = $objModel->{$field};
                }
            }
        }

        return $arrReturn;
    }

    /**
     * Test if the member has already made another booking at the same time.
     */
    public static function areBookingDatesOccupied(CalendarEventsModel $objEvent, MemberModel $objMember): bool
    {
        $arrEventDates = [];
        $arrEventRepeats = StringUtil::deserialize($objEvent->eventDates, true);

        if (!empty($arrEventRepeats) && \is_array($arrEventRepeats)) {
            foreach ($arrEventRepeats as $eventRepeat) {
                if (isset($eventRepeat['new_repeat']) && !empty($eventRepeat['new_repeat'])) {
                    $arrEventDates[] = $eventRepeat['new_repeat'];
                }
            }
        }

        // Get all future events of the member
        $objMemberEvents = Database::getInstance()
            ->prepare('SELECT * FROM tl_calendar_events_member WHERE eventId != ? AND contaoMemberId = ? AND stateOfSubscription = ? AND hasParticipated = ?')
            ->execute($objEvent->id, $objMember->id, EventSubscriptionLevel::SUBSCRIPTION_ACCEPTED, '')
        ;

        while ($objMemberEvents->next()) {
            $objMemberEvent = CalendarEventsModel::findByPk($objMemberEvents->eventId);

            if (null !== $objMemberEvent) {
                $arrRepeats = StringUtil::deserialize($objMemberEvent->eventDates, true);

                if (!empty($arrRepeats) && \is_array($arrRepeats)) {
                    foreach ($arrRepeats as $repeat) {
                        if (isset($repeat['new_repeat']) && !empty($repeat['new_repeat'])) {
                            if (\in_array($repeat['new_repeat'], $arrEventDates, false)) {
                                // This date is already occupied (do not allow booking)
                                return true;
                            }
                        }
                    }
                }
            }
        }

        return false;
    }

    public static function generateEventPreviewUrl(CalendarEventsModel $objEvent): string
    {
        $strUrl = '';

        if ('' !== $objEvent->eventType) {
            $objEventType = EventTypeModel::findByAlias($objEvent->eventType);

            if (null !== $objEventType) {
                if ($objEventType->previewPage > 0) {
                    $objPage = PageModel::findByPk($objEventType->previewPage);

                    if ($objPage instanceof PageModel) {
                        $params = (Config::get('useAutoItem') ? '/' : '/events/').($objEvent->alias ?: $objEvent->id);
                        $strUrl = StringUtil::ampersand($objPage->getFrontendUrl($params));
                        $strUrl = Url::addQueryString('mode=eventPreview', $strUrl);
                        $strUrl = Url::addQueryString('eventToken='.$objEvent->eventToken, $strUrl);
                    }
                }
            }
        }

        return $strUrl;
    }

    public static function getTourProfileAsArray(CalendarEventsModel $objEvent): array
    {
        $arrProfile = [];

        if (!empty($objEvent->tourProfile) && \is_array(StringUtil::deserialize($objEvent->tourProfile))) {
            $m = 0;
            $arrTourProfile = StringUtil::deserialize($objEvent->tourProfile, true);

            foreach ($arrTourProfile as $profile) {
                if (empty($profile['tourProfileAscentMeters']) && empty($profile['tourProfileAscentTime']) && empty($profile['tourProfileDescentMeters']) && empty($profile['tourProfileDescentTime'])) {
                    continue;
                }

                ++$m;

                $arrAsc = [];
                $arrDesc = [];

                if (\count($arrTourProfile) > 1) {
                    $strProfile = sprintf('%s. Tag: ', $m);
                } else {
                    $strProfile = '';
                }

                if ('' !== $profile['tourProfileAscentMeters']) {
                    $arrAsc[] = sprintf('%s Hm', $profile['tourProfileAscentMeters']);
                }

                if ('' !== $profile['tourProfileAscentTime']) {
                    $arrAsc[] = sprintf('%s h', $profile['tourProfileAscentTime']);
                }

                if ('' !== $profile['tourProfileDescentMeters']) {
                    $arrDesc[] = sprintf('%s Hm', $profile['tourProfileDescentMeters']);
                }

                if ('' !== $profile['tourProfileDescentTime']) {
                    $arrDesc[] = sprintf('%s h', $profile['tourProfileDescentTime']);
                }

                if (\count($arrAsc) > 0) {
                    $strProfile .= 'Aufst: '.implode('/', $arrAsc);
                }

                if (\count($arrDesc) > 0) {
                    $strProfile .= ('' !== $strProfile ? ', ' : '').'Abst: '.implode('/', $arrDesc);
                }

                $arrProfile[] = $strProfile;
            }
        }

        return $arrProfile;
    }

    public static function getEventOrganizersLogoAsHtml(CalendarEventsModel $objEvent, string $strInsertTag = '{{image::%s}}', bool $allowDuplicate = false): array
    {
        $arrHtml = [];
        $arrUuids = [];

        $arrOrganizers = StringUtil::deserialize($objEvent->organizers, true);

        foreach ($arrOrganizers as $orgId) {
            $objOrganizer = EventOrganizerModel::findByPk($orgId);

            if (null !== $objOrganizer) {
                if ($objOrganizer->addLogo && '' !== $objOrganizer->singleSRC) {
                    if (\in_array($objOrganizer->singleSRC, $arrUuids, false) && !$allowDuplicate) {
                        continue;
                    }

                    $arrUuids[] = $objOrganizer->singleSRC;
                    $parser = System::getContainer()->get('contao.insert_tag.parser');

                    $strLogo = $parser->replace(sprintf($strInsertTag, StringUtil::binToUuid($objOrganizer->singleSRC)));

                    if ('' !== $strLogo) {
                        $arrHtml[] = $strLogo;
                    }
                }
            }
        }

        return $arrHtml;
    }

    public static function getEventQrCode(CalendarEventsModel $objEvent, array $arrOptions = [], bool $blnAbsoluteUrl = true, bool $blnCache = true): ?string
    {
        // Generate QR code folder
        $objFolder = new Folder('system/qrcodes');

        // Symlink
        $rootDir = System::getContainer()->getParameter('kernel.project_dir');
        SymlinkUtil::symlink($objFolder->path, 'web/'.$objFolder->path, $rootDir);

        // Generate path
        $filepath = sprintf($objFolder->path.'/'.'eventQRcode_%s.png', $objEvent->id);

        // Defaults
        $opt = [
            'version' => 5,
            'scale' => 4,
            'outputType' => QRCode::OUTPUT_IMAGE_PNG,
            'eccLevel' => QRCode::ECC_L,
            'cachefile' => $filepath,
        ];

        if (!$blnCache) {
            unset($opt['cachefile']);
        }

        $options = new QROptions(array_merge($opt, $arrOptions));

        // Get event reader url
        $url = Events::generateEventUrl($objEvent, $blnAbsoluteUrl);

        // Generate QR and return the image path
        if ((new QRCode($options))->render($url, $filepath)) {
            return $filepath;
        }

        return null;
    }

    public static function getSectionMembershipAsString(MemberModel $objMember): string
    {
        Controller::loadLanguageFile('tl_member');
        $arrSections = [];
        $sections = StringUtil::deserialize($objMember->sectionId, true);

        foreach ($sections as $id) {
            $arrSections[] = $GLOBALS['TL_LANG']['tl_member']['section'][$id] ?: $id;
        }

        return implode(', ', $arrSections);
    }
}
