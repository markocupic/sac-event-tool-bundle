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

namespace Markocupic\SacEventToolBundle;

use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;
use Code4Nix\UriSigner\UriSigner;
use Codefog\HasteBundle\UrlParser;
use Contao\Calendar;
use Contao\CalendarEventsModel;
use Contao\Config;
use Contao\ContentModel;
use Contao\Controller;
use Contao\CoreBundle\Util\SymlinkUtil;
use Contao\Database;
use Contao\Date;
use Contao\Events;
use Contao\FilesModel;
use Contao\Folder;
use Contao\FrontendUser;
use Contao\MemberModel;
use Contao\PageModel;
use Contao\StringUtil;
use Contao\System;
use Contao\Template;
use Contao\UserModel;
use Doctrine\DBAL\Connection;
use Markocupic\SacEventToolBundle\Avatar\Avatar;
use Markocupic\SacEventToolBundle\Config\Bundle;
use Markocupic\SacEventToolBundle\Config\CourseLevels;
use Markocupic\SacEventToolBundle\Config\EventState;
use Markocupic\SacEventToolBundle\Config\EventSubscriptionState;
use Markocupic\SacEventToolBundle\Config\EventType;
use Markocupic\SacEventToolBundle\Model\CalendarEventsJourneyModel;
use Markocupic\SacEventToolBundle\Model\CalendarEventsMemberModel;
use Markocupic\SacEventToolBundle\Model\CourseMainTypeModel;
use Markocupic\SacEventToolBundle\Model\CourseSubTypeModel;
use Markocupic\SacEventToolBundle\Model\EventOrganizerModel;
use Markocupic\SacEventToolBundle\Model\EventReleaseLevelPolicyModel;
use Markocupic\SacEventToolBundle\Model\EventTypeModel;
use Markocupic\SacEventToolBundle\Model\TourDifficultyModel;
use Markocupic\SacEventToolBundle\Model\TourTypeModel;
use Symfony\Component\Filesystem\Path;

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

            case 'eventStateIcon':
                $value = static::getEventStateIcon($objEvent);
                break;

            case 'eventStateLabel':
                $value = '' !== $GLOBALS['TL_LANG']['MSC']['calendar_events'][static::getEventState($objEvent)] ? $GLOBALS['TL_LANG']['MSC']['calendar_events'][static::getEventState($objEvent)] : static::getEventState($objEvent);

                if (EventState::STATE_RESCHEDULED === $objEvent->eventState) {
                    $dateFormat = Config::get('dateFormat');
                    $newDate = $objEvent->rescheduledEventDate ? date($dateFormat, (int) $objEvent->rescheduledEventDate) : 'unbest';
                    $value = sprintf($GLOBALS['TL_LANG']['MSC']['calendar_events'][static::getEventState($objEvent)], $newDate);
                }
                break;

            case 'isLastMinuteTour':
                $value = EventType::LAST_MINUTE_TOUR === $objEvent->eventType;
                break;

            case 'isTour':
                $value = EventType::TOUR === $objEvent->eventType;
                break;

            case 'isGeneralEvent':
                $value = EventType::GENERAL_EVENT === $objEvent->eventType;
                break;

            case 'isCourse':
                $value = EventType::COURSE === $objEvent->eventType;
                break;

            case 'bookingCounter':
                $value = static::getBookingCounter($objEvent);
                break;

            case 'bookingCounterAsText':
                $value = static::getBookingCounter($objEvent, true);
                break;

            case 'minMembers':
                $value = $objEvent->minMembers;
                break;

            case 'tourTechDifficultiesAsArray':
                $value = static::getTourTechDifficultiesAsArray($objEvent, false, false);
                break;

            case 'tourTechDifficultiesAsArrayWithExplanation':
                $value = static::getTourTechDifficultiesAsArray($objEvent, false, true);
                break;

            case 'tourTechDifficulties':
                $value = implode(' ', static::getTourTechDifficultiesAsArray($objEvent, true, false));
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

            case 'courseLevelName':
                $value = System::getContainer()->get(CourseLevels::class)->get($objEvent->courseLevel);
                break;

            case 'courseTypeLevel0Name':
                $value = CourseMainTypeModel::findByPk($objEvent->courseTypeLevel0)->name;
                break;

            case 'courseTypeLevel1Name':
                $value = CourseSubTypeModel::findByPk($objEvent->courseTypeLevel1)->name;
                break;

            // inside vue.js templates: eventOrganizerLogos||60
            // The first parameter defines the logo width
            case 'eventOrganizerLogos':
                $intDefaultWidth = 60;
                $width = $arrArgs[1] ?? $intDefaultWidth;
                $strInsertTag = '{{image::%s?width='.$width.'&alt=%s}}';
                $value = static::getEventOrganizersLogoAsHtml($objEvent, $strInsertTag);
                break;

            case 'eventOrganizers':
                $value = implode('<br>', static::getEventOrganizersAsArray($objEvent));
                break;

            case 'mainInstructorContactDataFromDb':
                $value = static::generateMainInstructorContactDataFromDb($objEvent);
                break;

            case 'instructorContactBoxes':
                $value = static::generateInstructorContactBoxes($objEvent);
                break;

            case 'arrTourProfile':
                $value = static::getTourProfileAsArray($objEvent);
                break;

            case 'geoLink':
                $value = $objEvent->geoLink;
                break;

            case 'hasCoords':
                $value = !empty(static::getCoordsCH1903AsArray($objEvent)) ? true : false;
                break;

            case 'coordsCH1903':
                $value = static::getCoordsCH1903AsArray($objEvent);
                break;

            case 'geoLinkUrl':
                $value = static::getGeoLinkUrl($objEvent);
                break;

            case 'linkSacRoutePortal':
                $value = static::getSacRoutePortalLink($objEvent);
                break;

            case 'isPublicTransportEvent':
                $value = false;

                /** @var Connection $connection */
                $connection = System::getContainer()->get('database_connection');

                $idPublicTransportJourney = $connection->fetchOne(
                    'SELECT id from tl_calendar_events_journey WHERE alias = ?',
                    ['public-transport']
                );

                if ($idPublicTransportJourney) {
                    if ((int) $objEvent->journey === (int) $idPublicTransportJourney) {
                        $value = true;
                    }
                }
                break;

            case 'getPublicTransportBadge':
                $value = static::getPublicTransportBadge($objEvent);
                break;

            case 'gallery':
                $value = static::getGallery([
                    'multiSRC' => $objEvent->multiSRC,
                    'orderSRC' => $objEvent->orderSRC,
                    'sortBy' => 'custom',
                    'perRow' => 4,
                    'size' => serialize([400, 400, 'center_center', 'proportional']),
                    'fullsize' => true,
                    'customTpl' => 'content_element/gallery/col_4_with_caption',
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
    public static function generateInstructorContactBoxes(CalendarEventsModel $objEvent): string
    {
        $strHtml = '';
        $objCalendar = $objEvent->getRelated('pid');
        $userPortraitJumpTo = $objCalendar->userPortraitJumpTo;

        $arrInstructors = static::getInstructorsAsArray($objEvent);

        foreach ($arrInstructors as $userId) {
            $strHtml .= '<div class="mb-4 col-6 col-sm-4 col-md-6 col-xl-4"><div class="">';

            $objUser = UserModel::findByPk($userId);
            $avatarManager = System::getContainer()->get(Avatar::class);

            if (null !== $objUser) {
                $parser = System::getContainer()->get('contao.insert_tag.parser');

                // Use a figure and add the title tag, this way Contao will automatically generate the necessary JsonLD tags.
                $figureTag = '{{figure::'.$avatarManager->getAvatarResourcePath($objUser).'?size=18&metadata[title]='.StringUtil::specialchars($objUser->name).'&enableLightbox=0&options[attr][class]=avatar-large&template=image}}';

                $strHtml .= '<div class="image_container portrait">';
                $strHtml .= sprintf('<a href="%s?username=%s" data-title="Leiter Portrait ansehen">', $parser->replace('{{link_url::'.$userPortraitJumpTo.'}}'), UserModel::findByPk($userId)->username);
                $strHtml .= $parser->replace($figureTag);
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
                    $strHtml .= sprintf('<a href="%s?username=%s" data-title="Leiter Portrait ansehen">', $parser->replace('{{link_url::'.$userPortraitJumpTo.'}}'), $objUser->username);
                }

                $strHtml .= sprintf('%s %s%s', $objUser->lastname, $objUser->firstname, $strQuali);

                if (!$objUser->hideInFrontendListings) {
                    $strHtml .= '</a>';
                }

                $frontendUser = System::getContainer()->get('security.helper');

                if ($frontendUser instanceof FrontendUser && !$objUser->hideInFrontendListings) {
                    $arrContact = ['phone', 'mobile', 'email'];

                    foreach ($arrContact as $field) {
                        if ('' !== $objUser->{$field}) {
                            $strHtml .= sprintf('<div class="ce_user_portrait_%s">', $field);
                            $strHtml .= sprintf('<small data-title="%s">%s</small>', $objUser->{$field}, $objUser->{$field});
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
            ->execute($objEvent->id, EventSubscriptionState::SUBSCRIPTION_ACCEPTED)
        ;

        $registrationCount = $objEventsMember->registrationCount;

        // Event canceled
        if (EventState::STATE_CANCELED === $objEvent->eventState) {
            return 'event_status_4';
        }

        // Event deferred
        if (EventState::STATE_RESCHEDULED === $objEvent->eventState) {
            return 'event_status_6';
        }

        // Event is fully booked/instructor has explicitly set the "is fully booked" label in the backend
        if (EventState::STATE_FULLY_BOOKED === $objEvent->eventState) {
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

    /**
     * @throws \Exception
     */
    public static function getEventStateIcon(CalendarEventsModel $objEvent): string
    {
        $strState = static::getEventState($objEvent);
        $strLabel = $GLOBALS['TL_LANG']['MSC']['calendar_events'][$strState] ?? $strState;

        return sprintf(
            '<img src="%s/icons/event_states/%s.svg" title="%s">',
            Bundle::ASSET_DIR,
            $strState,
            $strLabel,
        );
    }

    public static function eventIsFullyBooked(CalendarEventsModel $objEvent): bool
    {
        $objEventsMember = Database::getInstance()
            ->prepare('SELECT * FROM tl_calendar_events_member WHERE eventId = ? AND stateOfSubscription = ?')
            ->execute($objEvent->id, EventSubscriptionState::SUBSCRIPTION_ACCEPTED)
        ;

        $registrationCount = $objEventsMember->numRows;

        if (EventState::STATE_FULLY_BOOKED === $objEvent->eventState || ($objEvent->maxMembers > 0 && $registrationCount >= $objEvent->maxMembers)) {
            return true;
        }

        return false;
    }

    public static function getMainInstructor(CalendarEventsModel $objEvent): UserModel|null
    {
        $objInstructor = Database::getInstance()
            ->prepare('SELECT * FROM tl_calendar_events_instructor WHERE pid = ? AND isMainInstructor = ?')
            ->limit(1)
            ->execute($objEvent->id, 1)
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
        $arrInstructors = static::getInstructorsAsArray($objEvent);
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

                if (true === $blnShowPublishedOnly && ('' !== $objUser->stop && $objUser->stop < time())) {
                    continue;
                }

	            if (true === $blnShowPublishedOnly && ('' !== $objUser->start && $objUser->start > time())) {
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
            Controller::loadLanguageFile('tl_user');
            $strQuali = $GLOBALS['TL_LANG']['tl_user']['refLeiterQualifikation'][(int) $arrQuali[0]] ?? 'undefined';
        }

        return $strQuali;
    }

    public static function getGallery(array $arrData): string
    {
        $arrData['type'] = 'gallery';
        $arrData['tstamp'] = 0;

        if (empty($arrData['perRow'])) {
            $arrData['perRow'] = 4;
        }

        $objModel = new ContentModel();
        $objModel->setRow($arrData);

        return Controller::getContentElement($objModel);
    }

    public static function getEventImagePath(CalendarEventsModel $objEvent): string
    {
        // Get root dir
        $projectDir = System::getContainer()->getParameter('kernel.project_dir');
        System::getContainer()->get('contao.framework')->initialize();

        if ('' !== $objEvent->singleSRC) {
            $objFile = FilesModel::findByUuid($objEvent->singleSRC);

            if (null !== $objFile && is_file($projectDir.'/'.$objFile->path)) {
                return $objFile->path;
            }
        }

        return System::getContainer()->getParameter('sacevt.event.course.fallback_image');
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
        // Typecast is required here, this although PhpCodeSniffer claims the opposite.
        $span = (int) Calendar::calculateSpan(self::getStartDate($objEvent), self::getEndDate($objEvent)) + 1;

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
            return Date::parse($dateFormat, self::getStartDate($objEvent)).($blnAppendEventDuration ? ' ('.self::getEventDuration($objEvent).')' : '').(!$blnInline ? '<br>' : ' ').'<a tabindex="0" class="more-date-infos" data-bs-toggle="tooltip" data-placement="bottom" data-title="Eventdaten: '.implode(', ', $arrDates).'">und weitere</a>';
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

    public static function getPublicTransportBadge(CalendarEventsModel $objEvent): string
    {
        return '<span class="badge badge-sm badge-pill bg-success" data-bs-toggle="tooltip" data-placement="top" data-title="Anreise mit ÖV">ÖV</span>';
    }

    public static function getTourTechDifficultiesAsArray(CalendarEventsModel $objEvent, bool $tooltip = false, bool $explanation = false): array
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
                        $html = '<span class="badge badge-sm badge-pill bg-primary" data-bs-toggle="tooltip" data-placement="top" data-title="Techn. Schwierigkeit: %s">%s</span>';
                        $arrReturn[] = sprintf($html, $strDiffTitle, $strDiff);
                    } elseif ($explanation) {
                        $arrReturn[] = $strDiff.' ('.$strDiffTitle.')';
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
                        $html = '<span class="badge badge-sm badge-pill bg-secondary" data-bs-toggle="tooltip" data-placement="top" data-title="Typ: %s">%s</span>';
                        $arrReturn[] = sprintf($html, $objModel->{'title'}, $objModel->{$field});
                    } else {
                        $arrReturn[] = $objModel->{$field};
                    }
                }
            }
        }

        return $arrReturn;
    }

    public static function getBookingCounter(CalendarEventsModel $objEvent, bool $withoutTooltip = false): string
    {
        $strBadge = '<span class="badge badge-sm badge-pill bg-%s" data-bs-toggle="tooltip" data-placement="top" data-title="%s">%s</span>';

        if ($withoutTooltip) {
            $strBadge = '%2$s (%3$s)'; // only text as output, e.g. 'noch 1 freie Plätze (5/6)`
        }

        $calendarEventsMember = Database::getInstance()
            ->prepare('SELECT * FROM tl_calendar_events_member WHERE eventId = ? && stateOfSubscription = ?')
            ->execute($objEvent->id, EventSubscriptionState::SUBSCRIPTION_ACCEPTED)
        ;

        $memberCount = $calendarEventsMember->numRows;

        if (EventState::STATE_CANCELED === $objEvent->eventState) {
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
                if (EventSubscriptionState::SUBSCRIPTION_NOT_CONFIRMED === $eventsMemberModel->stateOfSubscription) {
                    ++$intNotConfirmed;
                }

                if (EventSubscriptionState::SUBSCRIPTION_ACCEPTED === $eventsMemberModel->stateOfSubscription) {
                    ++$intAccepted;
                }

                if (EventSubscriptionState::SUBSCRIPTION_REFUSED === $eventsMemberModel->stateOfSubscription) {
                    ++$intRefused;
                }

                if (EventSubscriptionState::SUBSCRIPTION_ON_WAITING_LIST === $eventsMemberModel->stateOfSubscription) {
                    ++$intWaitlisted;
                }

                if (EventSubscriptionState::USER_HAS_UNSUBSCRIBED === $eventsMemberModel->stateOfSubscription) {
                    ++$intUnsubscribedUser;
                }
            }

            // Generate the href
            $router = System::getContainer()->get('router');

            $href = $router->generate('contao_backend', [
                'do' => 'calendar',
                'table' => 'tl_calendar_events_member',
                'id' => $objEvent->id,
                'rt' => System::getContainer()->get('contao.csrf.token_manager')->getDefaultTokenValue(),
                'ref' => System::getContainer()->get('request_stack')->getCurrentRequest()->attributes->get('_contao_referer_id'),
            ]);

            if ($intNotConfirmed > 0) {
                $strRegistrationsBadges .= sprintf('<span class="subscription-badge not-confirmed blink" data-title="%s unbeantwortete Anmeldeanfragen" role="button" onclick="window.location.href=\'%s\'">%s</span>', $intNotConfirmed, $href, $intNotConfirmed);
            }

            if ($intAccepted > 0) {
                $strRegistrationsBadges .= sprintf('<span class="subscription-badge accepted" data-title="%s bestätigte Anmeldungen" role="button" onclick="window.location.href=\'%s\'">%s</span>', $intAccepted, $href, $intAccepted);
            }

            if ($intRefused > 0) {
                $strRegistrationsBadges .= sprintf('<span class="subscription-badge refused" data-title="%s abgelehnte Anmeldungen" role="button" onclick="window.location.href=\'%s\'">%s</span>', $intRefused, $href, $intRefused);
            }

            if ($intWaitlisted > 0) {
                $strRegistrationsBadges .= sprintf('<span class="subscription-badge on-waiting-list" data-title="%s Anmeldungen auf Warteliste" role="button" onclick="window.location.href=\'%s\'">%s</span>', $intWaitlisted, $href, $intWaitlisted);
            }

            if ($intUnsubscribedUser > 0) {
                $strRegistrationsBadges .= sprintf('<span class="subscription-badge unsubscribed-user" data-title="%s stornierte Anmeldungen" role="button" onclick="window.location.href=\'%s\'">%s</span>', $intUnsubscribedUser, $href, $intUnsubscribedUser);
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
            ->execute($objEvent->id, $objMember->id, EventSubscriptionState::SUBSCRIPTION_ACCEPTED, 0)
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
        /** @var UriSigner $uriSigner */
        $uriSigner = System::getContainer()->get('code4nix_uri_signer.uri_signer');

        /** @var UrlParser $urlParser */
        $urlParser = System::getContainer()->get(UrlParser::class);

        $eventPreviewUrl = '';

        if ('' !== $objEvent->eventType) {
            $objEventType = EventTypeModel::findOneBy('alias', $objEvent->eventType);

            if (null !== $objEventType) {
                if ($objEventType->previewPage > 0) {
                    $objPage = PageModel::findByPk($objEventType->previewPage);

                    if ($objPage instanceof PageModel) {
                        $params = sprintf('/%s', !empty($objEvent->alias) ? $objEvent->alias : $objEvent->id);

                        $eventPreviewUrl = $urlParser->addQueryString('event_preview=true', $objPage->getAbsoluteUrl($params));
                        $eventPreviewUrl = StringUtil::ampersand($eventPreviewUrl);
                        $eventPreviewUrl = $uriSigner->sign($eventPreviewUrl, 86400);
                    }
                }
            }
        }

        return $eventPreviewUrl;
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

    public static function getEventOrganizersLogoAsHtml(CalendarEventsModel $objEvent, string $strInsertTag = '{{image::%s&alt=%s}}', bool $allowDuplicate = false): array
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

                    $strInsertTag = str_replace('alt=%s', 'alt='.$objOrganizer->title, $strInsertTag);

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

    /**
     * Not needed at the moment.
     */
    public static function getEventQrCode(CalendarEventsModel $objEvent, array $arrOptions = [], bool $blnAbsoluteUrl = true, bool $blnCache = true): string|null
    {
        // Generate QR code folder
        $objFolder = new Folder('system/qrcodes');

        // Symlink
        $projectDir = System::getContainer()->getParameter('kernel.project_dir');
        $webDir = Path::join($projectDir, 'public');
        $relWebDir = Path::makeRelative($webDir, $projectDir); // public

        // Symlink (target: 'system/qrcodes', link: 'public/system/qrcodes')
        SymlinkUtil::symlink($objFolder->path, $relWebDir.'/'.$objFolder->path, $projectDir);

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
            $arrSections[] = $GLOBALS['TL_LANG']['tl_member']['section'][$id] ?? $id;
        }

        return implode(', ', $arrSections);
    }

    public static function getCoordsCH1903AsArray(CalendarEventsModel $objEvent): array
    {
        // coordsCH1903 (format "2600000, 1200000" (CH1903+) or "600000, 200000" (CH1903))
        if (!empty($objEvent->coordsCH1903)) {
            $strCoord = html_entity_decode($objEvent->coordsCH1903);

            // Remove invalid characters (whitespaces, quotes, ...)
            $strCoord = preg_replace('/[^0-9.,]/', '', $strCoord);
            $arrCoord = explode(',', $strCoord);

            if (2 === \count($arrCoord)) {
                return $arrCoord;
            }
        }

        return [];
    }

    public static function getGeoLinkUrl(CalendarEventsModel $objEvent): string|null
    {
        $arrCoord = self::getCoordsCH1903AsArray($objEvent);

        if (!empty($arrCoord)) {
            $strGeoLink = System::getContainer()->getParameter('sacevt.event.geo_link');

            return sprintf($strGeoLink, $arrCoord[0], $arrCoord[1]);
        }

        return null;
    }

    public static function getSacRoutePortalLink(CalendarEventsModel $objEvent): string|null
    {
        if (empty($objEvent->linkSacRoutePortal)) {
            return null;
        }

        $strPortalLink = html_entity_decode($objEvent->linkSacRoutePortal);

        // Validate link
        if (!filter_var($strPortalLink, FILTER_VALIDATE_URL)) {
            return null;
        }

        // Only links from the SAC route portal are allowed
        if (!str_starts_with($strPortalLink, System::getContainer()->getParameter('sacevt.event.sac_route_portal_base_link'))) {
            return null;
        }

        // Check if the SAC route portal base link is not entered
        if ($strPortalLink === System::getContainer()->getParameter('sacevt.event.sac_route_portal_base_link')) {
            return null;
        }

        return $strPortalLink;
    }

    public static function getEventReleaseLevelAsString(CalendarEventsModel $objEvent): string|null
    {
        if (empty($objEvent->id) || empty($objEvent->eventReleaseLevel)) {
            return null;
        }

        $strLevel = null;
        $eventReleaseLevelModel = EventReleaseLevelPolicyModel::findByPk($objEvent->eventReleaseLevel);

        if (null !== $eventReleaseLevelModel) {
            $strLevel = sprintf(
                'FS: %s',
                $eventReleaseLevelModel->level
            );

            if ($eventReleaseLevelModel->level <= 1) {
                $strLevel .= ' Entwurf';
            }
        }

        return $strLevel;
    }
}
