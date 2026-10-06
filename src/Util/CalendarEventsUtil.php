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

namespace Markocupic\SacEventToolBundle\Util;

use chillerlan\QRCode\Common\Version;
use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;
use Code4Nix\UriSigner\UriSigner;
use Codefog\HasteBundle\UrlParser;
use Contao\Calendar;
use Contao\CalendarEventsModel;
use Contao\Config;
use Contao\ContentModel;
use Contao\Controller;
use Contao\CoreBundle\Framework\Adapter;
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\CoreBundle\Routing\ContentUrlGenerator;
use Contao\CoreBundle\Util\SymlinkUtil;
use Contao\Date;
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
use Doctrine\DBAL\Types\Types;
use Markocupic\SacEventToolBundle\Avatar\Avatar;
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
use Symfony\Component\Asset\Packages;
use Symfony\Component\DependencyInjection\Attribute\Autoconfigure;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Filesystem\Path;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

#[Autoconfigure(public: true)]
class CalendarEventsUtil
{
    /**
     * Keys of getEventData() that return a part of the start or end date.
     */
    private const array DATE_PARTS = [
        'startDateDay' => ['d', 'startDate'],
        'startDateMonth' => ['M', 'startDate'],
        'startDateYear' => ['y', 'startDate'],
        'endDateDay' => ['d', 'endDate'],
        'endDateMonth' => ['M', 'endDate'],
        'endDateYear' => ['y', 'endDate'],
    ];

    /**
     * Subscription state => [CSS class, title] of the badges in getSubscriptionStateBadges().
     */
    private const array SUBSCRIPTION_STATE_BADGES = [
        EventSubscriptionState::SUBSCRIPTION_NOT_CONFIRMED => ['not-confirmed blink', '%s unbeantwortete Anmeldeanfragen'],
        EventSubscriptionState::SUBSCRIPTION_ACCEPTED => ['accepted', '%s bestätigte Anmeldungen'],
        EventSubscriptionState::SUBSCRIPTION_REFUSED => ['refused', '%s abgelehnte Anmeldungen'],
        EventSubscriptionState::SUBSCRIPTION_ON_WAITING_LIST => ['on-waiting-list', '%s Anmeldungen auf Warteliste'],
        EventSubscriptionState::USER_HAS_UNSUBSCRIBED => ['unsubscribed-user', '%s stornierte Anmeldungen'],
    ];

    public function __construct(private readonly ContaoFramework $framework)
    {
    }

    /**
     * Returns an event property for the templates. Options can be added as a query
     * string, e.g. "eventImage?size=5".
     */
    public function getEventData(CalendarEventsModel $objEvent, string $strProperty, Template|null $objTemplate = null): mixed
    {
        $this->framework->initialize();

        $system = $this->getAdapter(System::class);
        $system->loadLanguageFile('tl_calendar_events');
        $system->loadLanguageFile('default');

        [$key, $query] = array_pad(explode('?', $strProperty, 2), 2, '');
        parse_str(html_entity_decode($query), $options);

        // Convert "true" and "false" to booleans
        $options = array_map(
            static fn ($value) => match ($value) {
                'true' => true,
                'false' => false,
                default => $value,
            },
            $options,
        );

        $date = $this->getAdapter(Date::class);
        $config = $this->getAdapter(Config::class);

        if (isset(self::DATE_PARTS[$key])) {
            [$format, $field] = self::DATE_PARTS[$key];

            return $date->parse($format, (int) $objEvent->{$field});
        }

        switch ($key) {
            case 'model':
                return $objEvent;

            case 'id':
                return $objEvent->id;

            case 'eventId':
                return \sprintf('%s-%s', $date->parse('Y', (int) $objEvent->startDate), $objEvent->id);

            case 'eventTitle':
                return StringUtil::revertInputEncoding($objEvent->title);

            case 'eventUrl':
                return $this->getContainer()
                    ->get('contao.routing.content_url_generator')
                    ->generate($objEvent, [], UrlGeneratorInterface::ABSOLUTE_PATH)
                ;

            case 'tourTypesIds':
                return implode('', StringUtil::deserialize($objEvent->tourType, true));

            case 'tourTypesShortcuts':
                return implode(' ', $this->getTourTypesAsArray($objEvent, 'shortcut', true));

            case 'tourTypesTitles':
                return implode('<br>', $this->getTourTypesAsArray($objEvent, 'title'));

            case 'eventPeriodSmTooltip':
            case 'eventPeriodSm':
                return $this->getEventPeriod($objEvent, 'd.m.Y', false);

            case 'eventPeriodLgInline':
                return $this->getEventPeriod($objEvent, 'D, d.m.Y', false, true, true);

            case 'eventPeriodLgTooltip':
            case 'eventPeriodLg':
                return $this->getEventPeriod($objEvent, 'D, d.m.Y', false);

            case 'eventDuration':
                return $this->getEventDuration($objEvent);

            case 'registrationStartDateWithOffsetFormatted':
                return $date->parse($config->get('dateFormat'), (int) $this->getRegistrationStartTime($objEvent));

            case 'registrationStartTimeWithOffsetFormatted':
                return $date->parse($config->get('datimFormat'), (int) $this->getRegistrationStartTime($objEvent));

            case 'registrationEndDateFormatted':
                $endDate = $date->parse($config->get('dateFormat'), (int) $objEvent->registrationEndDate);

                // Only show the date if the registration ends at the default time (23:59)
                if (abs($objEvent->registrationEndDate - strtotime($endDate)) === (24 * 3600) - 60) {
                    return $endDate;
                }

                return $date->parse($config->get('datimFormat'), (int) $objEvent->registrationEndDate);

            case 'eventState':
                return $this->getEventState($objEvent);

            case 'eventStateIcon':
                return $this->getEventStateIcon($objEvent);

            case 'eventStateLabel':
                $eventState = $this->getEventState($objEvent);
                $label = $GLOBALS['TL_LANG']['MSC']['calendar_events'][$eventState] ?? throw new \RuntimeException(\sprintf('Missing translation $GLOBALS[\'TL_LANG\'][\'MSC\'][\'calendar_events\'][\'%s\'].', $eventState));

                if (EventState::STATE_RESCHEDULED === $objEvent->eventState) {
                    $newDate = $objEvent->rescheduledEventDate ? $date->parse($config->get('dateFormat'), (int) $objEvent->rescheduledEventDate) : 'unbest';

                    return \sprintf($label, $newDate);
                }

                return '' !== $label ? $label : $eventState;

            case 'isLastMinuteTour':
                return EventType::LAST_MINUTE_TOUR === $objEvent->eventType;

            case 'isTour':
                return EventType::TOUR === $objEvent->eventType;

            case 'isGeneralEvent':
                return EventType::GENERAL_EVENT === $objEvent->eventType;

            case 'isCourse':
                return EventType::COURSE === $objEvent->eventType;

            case 'bookingCounter':
                return $this->getBookingCounter($objEvent);

            case 'bookingCounterAsText':
                return $this->getBookingCounter($objEvent, true);

            case 'minMembers':
                return $objEvent->minMembers;

            case 'tourTechDifficultiesAsGenericArray':
                return $this->getTourTechDifficultiesAsGenericArray($objEvent);

            case 'tourTechDifficultiesAsArray':
                return $this->getTourTechDifficultiesAsArray($objEvent, false, false);

            case 'tourTechDifficulties':
                return implode(' ', $this->getTourTechDifficultiesAsArray($objEvent, true, false));

            case 'instructorsWithQualification':
            case 'instructors':
                return implode(', ', $this->getInstructorNamesAsArray($objEvent, $options));

            case 'journey':
                $journey = $this->getAdapter(CalendarEventsJourneyModel::class)->findById($objEvent->journey);

                return null !== $journey ? $journey->title : '';

            case 'courseTypeLevel1':
                return $objEvent->courseTypeLevel1;

            case 'eventImagePath':
                return $this->getEventImagePath($objEvent);

            case 'eventImage':
                if (empty($options['size'])) {
                    return '';
                }

                return $this->getContainer()
                    ->get('contao.insert_tag.parser')
                    ->replace(\sprintf('{{picture::%s?size=%s}}', $this->getEventImagePath($objEvent), $options['size']))
                ;

            case 'courseLevelName':
                return $this->getContainer()->get(CourseLevels::class)->get($objEvent->courseLevel);

            case 'courseTypeLevel0Name':
                return $this->getAdapter(CourseMainTypeModel::class)->findById($objEvent->courseTypeLevel0)?->name ?? '';

            case 'courseTypeLevel1Name':
                return $this->getAdapter(CourseSubTypeModel::class)->findById($objEvent->courseTypeLevel1)?->name ?? '';

            // Inside vue.js templates: eventOrganizerLogos?width=60 (logo width)
            case 'eventOrganizerLogos':
                $width = !empty($options['width']) ? $options['width'] : '60';

                return $this->getEventOrganizersLogoAsHtml($objEvent, '{{image::%s?width='.$width.'&alt=%s}}');

            case 'eventOrganizerLogoPaths':
                // "true" has already been converted to a boolean
                $allowDuplicate = true === ($options['allowDuplicate'] ?? false);

                return $this->getEventOrganizerLogoPaths($objEvent, $allowDuplicate);

            case 'eventOrganizerModels':
                return $this->getEventOrganizerModels($objEvent);

            case 'eventOrganizers':
                return implode('<br>', $this->getEventOrganizersAsArray($objEvent));

            case 'mainInstructorContactDataFromDb':
                return $this->generateMainInstructorContactDataFromDb($objEvent, $options);

            case 'instructorContactBoxes':
                return $this->generateInstructorContactBoxes($objEvent, $options);

            case 'arrTourProfile':
                return $this->getTourProfileAsArray($objEvent);

            case 'geoLink':
                return $objEvent->geoLink;

            case 'hasCoords':
                return !empty($this->getCoordsCH1903AsArray($objEvent));

            case 'coordsCH1903':
                return $this->getCoordsCH1903AsArray($objEvent);

            case 'geoLinkUrl':
                return $this->getGeoLinkUrl($objEvent);

            case 'linkSacRoutePortal':
                return $this->getSacRoutePortalLink($objEvent);

            case 'isPublicTransportEvent':
                return $this->isPublicTransportEvent($objEvent);

            case 'getPublicTransportBadge':
                return $this->getPublicTransportBadge();

            case 'isFavoredEvent':
                return $this->isFavoredEvent($objEvent);

            case 'gallery':
                $row = $objEvent->row();
                $row['sortBy'] = 'custom';
                $row['perRow'] = 4;
                $row['size'] = serialize([400, 400, 'center_center', 'proportional']);
                $row['fullsize'] = true;
                $row['customTpl'] = 'content_element/gallery/col_4_with_caption';

                return $this->getGallery($row);
        }

        if (null !== $objTemplate && isset($objTemplate->{$key})) {
            return $objTemplate->{$key};
        }

        return $objEvent->row()[$key] ?? '';
    }

    public function isPublicTransportEvent(CalendarEventsModel $objEvent): bool
    {
        $this->framework->initialize();

        $publicTransportJourneyId = $this->getDatabase()->fetchOne(
            'SELECT id from tl_calendar_events_journey WHERE alias = ?',
            ['public-transport'],
            [Types::STRING],
        );

        return $publicTransportJourneyId && (int) $objEvent->journey === (int) $publicTransportJourneyId;
    }

    /**
     * @param array{includeDisabled?: bool, includeHidden?: bool} $options
     */
    public function generateInstructorContactBoxes(CalendarEventsModel $objEvent, array $options): string
    {
        $this->framework->initialize();

        $options = $this->resolveInstructorOptions($options);

        $calendar = $objEvent->getRelated('pid');

        if (null === $calendar) {
            throw new \RuntimeException(\sprintf('The calendar of the event with ID %s does not exist.', $objEvent->id));
        }

        $avatarManager = $this->getContainer()->get(Avatar::class);
        $userPortraitPage = $this->getAdapter(PageModel::class)->findById($calendar->userPortraitJumpTo);

        if (null === $userPortraitPage) {
            throw new \Exception('Page model not found.');
        }

        $contentUrlGenerator = $this->getContainer()->get('contao.routing.content_url_generator');
        $userModel = $this->getAdapter(UserModel::class);
        $items = [];

        foreach ($this->getInstructorsAsArray($objEvent, $options) as $userId) {
            $user = $userModel->findById($userId);

            if (null === $user || $user->hideUser) {
                continue;
            }

            $mainQualification = $this->getMainQualification($user);

            $instructor = $user->row();
            $instructor['href'] = $contentUrlGenerator->generate($userPortraitPage).'?getUpcoming=1&username='.$user->username;
            $instructor['has_link'] = true;
            $instructor['avatar_path'] = $avatarManager->getAvatarResourcePath($user);
            $instructor['main_qualification'] = !empty($mainQualification) ? $mainQualification : '';
            $instructor['contact_options'] = [];

            foreach (['phone', 'mobile', 'email'] as $field) {
                if ('' !== $user->{$field}) {
                    $instructor['contact_options'][$field] = $user->{$field};
                }
            }

            $items[] = $instructor;
        }

        return $this->getContainer()->get('twig')->render('@MarkocupicSacEventTool/Calendar/instructor_contact_boxes.html.twig', ['instructors' => $items]);
    }

    public function getEventState(CalendarEventsModel $objEvent): string
    {
        $this->framework->initialize();

        // Event canceled
        if (EventState::STATE_CANCELED === $objEvent->eventState) {
            return 'event_status_4';
        }

        // Event deferred
        if (EventState::STATE_RESCHEDULED === $objEvent->eventState) {
            return 'event_status_6';
        }

        // The instructor has explicitly set the "is fully booked" label in the backend
        if (EventState::STATE_FULLY_BOOKED === $objEvent->eventState) {
            return 'event_status_3';
        }

        // Event is over or booking is no more possible
        if ($objEvent->startDate <= time() || $objEvent->endDate <= time() || ($objEvent->setRegistrationPeriod && $objEvent->registrationEndDate < time())) {
            return 'event_status_2';
        }

        // Max participant number reached -> waiting list still possible
        if ($objEvent->maxMembers > 0 && $this->countAcceptedRegistrations($objEvent) >= $objEvent->maxMembers) {
            return 'event_status_8';
        }

        // Online registration is disabled in the event settings
        if ($objEvent->disableOnlineRegistration) {
            return 'event_status_7';
        }

        // Booking not possible yet
        $registrationStartTime = $this->getRegistrationStartTime($objEvent);

        if ($objEvent->setRegistrationPeriod && $registrationStartTime > time()) {
            return 'event_status_5';
        }

        return 'event_status_1';
    }

    public function getEventStateIcon(CalendarEventsModel $objEvent): string
    {
        $this->framework->initialize();

        $eventState = $this->getEventState($objEvent);

        /** @var Packages $packages */
        $packages = $this->getContainer()->get('assets.packages');

        return \sprintf(
            '<img src="%s" title="%s">',
            $packages->getUrl("icons/event_states/$eventState.svg", 'markocupic_sac_event_tool'),
            $GLOBALS['TL_LANG']['MSC']['calendar_events'][$eventState] ?? $eventState,
        );
    }

    public function eventIsFullyBooked(CalendarEventsModel $objEvent): bool
    {
        $this->framework->initialize();

        if (EventState::STATE_FULLY_BOOKED === $objEvent->eventState) {
            return true;
        }

        return $objEvent->maxMembers > 0 && $this->countAcceptedRegistrations($objEvent) >= $objEvent->maxMembers;
    }

    public function getMainInstructor(CalendarEventsModel $objEvent): UserModel|null
    {
        $this->framework->initialize();

        $userId = $this->getDatabase()->fetchOne(
            'SELECT userId FROM tl_calendar_events_instructor WHERE pid = ? AND isMainInstructor = ?',
            [$objEvent->id, 1],
            [Types::INTEGER, Types::INTEGER],
        );

        if (false === $userId) {
            return null;
        }

        return $this->getAdapter(UserModel::class)->findById($userId);
    }

    public function getMainInstructorName(CalendarEventsModel $objEvent): string
    {
        $this->framework->initialize();

        $user = $this->getMainInstructor($objEvent);

        if (null === $user) {
            return '';
        }

        return implode(' ', array_filter([$user->lastname, $user->firstname]));
    }

    /**
     * @param array{includeDisabled?: bool, includeHidden?: bool} $options
     */
    public function generateMainInstructorContactDataFromDb(CalendarEventsModel $objEvent, array $options = []): string
    {
        $this->framework->initialize();

        $options = $this->resolveInstructorOptions($options);

        $instructorIds = $this->getInstructorsAsArray($objEvent, $options);

        if (empty($instructorIds)) {
            return '';
        }

        $user = $this->getAdapter(UserModel::class)->findById($instructorIds[0]);

        if (null === $user) {
            return '';
        }

        $contact = [\sprintf('<strong>%s %s</strong>', $user->lastname, $user->firstname)];

        if ('' !== $user->phone) {
            $contact[] = \sprintf('Tel.: %s', $user->phone);
        }

        if ('' !== $user->mobile) {
            $contact[] = \sprintf('Mobile.: %s', $user->mobile);
        }

        if ('' !== $user->email) {
            $contact[] = \sprintf('E-Mail: %s', StringUtil::specialcharsUrl(StringUtil::encodeEmail($user->email)));
        }

        return implode(', ', $contact);
    }

    /**
     * Returns the ids of the instructors, the main instructor first.
     *
     * @param array{includeDisabled?: bool, includeHidden?: bool} $options
     */
    public function getInstructorsAsArray(CalendarEventsModel $objEvent, array $options = []): array
    {
        $this->framework->initialize();

        $options = $this->resolveInstructorOptions($options);

        $userIds = $this->getDatabase()->fetchFirstColumn(
            'SELECT userId FROM tl_calendar_events_instructor WHERE pid = ? ORDER BY isMainInstructor DESC',
            [$objEvent->id],
            [Types::INTEGER],
        );

        $userModel = $this->getAdapter(UserModel::class);
        $instructorIds = [];

        foreach ($userIds as $userId) {
            $user = $userModel->findById($userId);

            if (null === $user) {
                continue;
            }

            if (!$options['includeDisabled'] && $this->isUserDisabled($user)) {
                continue;
            }

            if (!$options['includeHidden'] && $user->hideUser) {
                continue;
            }

            $instructorIds[] = $user->id;
        }

        return $instructorIds;
    }

    /**
     * @param array{includeDisabled?: bool, includeHidden?: bool, addMainQualification?: bool} $options
     */
    public function getInstructorNamesAsArray(CalendarEventsModel $objEvent, array $options = []): array
    {
        $this->framework->initialize();

        $options = $this->resolveInstructorOptions($options, true);

        $userIds = $this->getInstructorsAsArray($objEvent, [
            'includeDisabled' => $options['includeDisabled'],
            'includeHidden' => $options['includeHidden'],
        ]);

        $userModel = $this->getAdapter(UserModel::class);
        $names = [];

        foreach ($userIds as $userId) {
            $user = $userModel->findById($userId);

            if (null === $user) {
                continue;
            }

            $name = trim($user->lastname.' '.$user->firstname);
            $mainQualification = $options['addMainQualification'] ? $this->getMainQualification($user) : '';

            $names[] = '' !== $mainQualification ? $name.' ('.$mainQualification.')' : $name;
        }

        return $names;
    }

    public function getMainQualification(UserModel $objUser): string
    {
        $this->framework->initialize();

        $qualifications = StringUtil::deserialize($objUser->leiterQualifikation, true);

        if (empty($qualifications[0])) {
            return '';
        }

        $this->getAdapter(System::class)->loadLanguageFile('tl_user');

        return $GLOBALS['TL_LANG']['tl_user']['refLeiterQualifikation'][(int) $qualifications[0]] ?? 'undefined';
    }

    public function getGallery(array $arrData): string
    {
        $this->framework->initialize();

        $arrData['type'] = 'gallery';
        $arrData['invisible'] = false;
        $arrData['tstamp'] = 1; // Must be set otherwise the gallery will be treated as a hidden content element

        if (empty($arrData['perRow'])) {
            $arrData['perRow'] = 4;
        }

        $contentModel = new ContentModel();
        $contentModel->setRow($arrData);

        return $this->getAdapter(Controller::class)->getContentElement($contentModel);
    }

    public function getEventImagePath(CalendarEventsModel $objEvent): string
    {
        $this->framework->initialize();

        $fallbackImage = $this->getContainer()->getParameter('sacevt.event.course.fallback_image');

        if (empty($objEvent->singleSRC)) {
            return $fallbackImage;
        }

        $file = $this->getAdapter(FilesModel::class)->findByUuid($objEvent->singleSRC);

        if (null === $file || !is_file(Path::join($this->getProjectDir(), $file->path))) {
            return $fallbackImage;
        }

        return $file->path;
    }

    public function getEventPeriod(CalendarEventsModel $objEvent, string $dateFormat = '', bool $blnAppendEventDuration = true, bool $blnTooltip = true, bool $blnInline = false): string
    {
        $this->framework->initialize();

        $date = $this->getAdapter(Date::class);

        if (empty($dateFormat)) {
            $dateFormat = $this->getAdapter(Config::class)->get('dateFormat');
        }

        $timestamps = $this->getEventTimestamps($objEvent);
        $startTstamp = $this->getStartTstamp($objEvent);
        $endTstamp = $this->getEndTstamp($objEvent);

        // Calendar::calculateSpan() returns a float
        $span = (int) Calendar::calculateSpan($startTstamp, $endTstamp) + 1;

        $strEventDuration = $blnAppendEventDuration ? ' ('.$this->getEventDuration($objEvent).')' : '';

        // One day
        if (1 === \count($timestamps)) {
            return $date->parse($dateFormat, $startTstamp).$strEventDuration;
        }

        // Consecutive days
        if ($span === \count($timestamps)) {
            $dateFormatShortened = 'd.m.Y' === $dateFormat ? 'd.m.' : $dateFormat;

            return $date->parse($dateFormatShortened, $startTstamp).' - '.$date->parse($dateFormat, $endTstamp).$strEventDuration;
        }

        // Non-consecutive days
        if ($blnTooltip) {
            $dates = array_map(static fn ($tstamp) => $date->parse($dateFormat, (int) $tstamp), $timestamps);
            $strTooltip = '<a tabindex="0" class="more-date-infos" data-controller="sacevt--frontend--bs-tooltip" data-bs-tooltip-title="Eventdaten: '.StringUtil::specialchars(implode(', ', $dates)).'" data-bs-tooltip-placement="bottom">und weitere</a>';

            return $date->parse($dateFormat, $startTstamp).$strEventDuration.($blnInline ? ' ' : '<br>').$strTooltip;
        }

        $dateString = '';

        foreach ($timestamps as $tstamp) {
            $dateString .= \sprintf('<time datetime="%s">%s</time>', StringUtil::specialchars($date->parse('Y-m-d', (int) $tstamp)), $date->parse('D, d.m.Y', (int) $tstamp));
        }

        if ($blnAppendEventDuration) {
            $dateString .= \sprintf('<time>(%s)</time>', $this->getEventDuration($objEvent));
        }

        return $dateString;
    }

    public function getBookingPeriod(int $id, string $dateFormatStart = '', string $dateFormatEnd = ''): string
    {
        $this->framework->initialize();

        $event = $this->getAdapter(CalendarEventsModel::class)->findById($id);

        if (null === $event || !$event->setRegistrationPeriod) {
            return '';
        }

        $config = $this->getAdapter(Config::class);
        $date = $this->getAdapter(Date::class);

        if ('' === $dateFormatStart) {
            $dateFormatStart = $config->get('dateFormat');
        }

        if ('' === $dateFormatEnd) {
            $dateFormatEnd = $config->get('dateFormat');
        }

        return $date->parse($dateFormatStart, (int) $this->getRegistrationStartTime($event)).' - '.$date->parse($dateFormatEnd, (int) $event->registrationEndDate);
    }

    public function getEventTimestamps(CalendarEventsModel $objEvent): array
    {
        $this->framework->initialize();

        $timestamps = [];

        foreach (StringUtil::deserialize($objEvent->eventDates, true) as $eventDate) {
            $timestamps[] = $eventDate['new_repeat'];
        }

        return $timestamps;
    }

    public function getStartTstamp(CalendarEventsModel $objEvent): int
    {
        $this->framework->initialize();

        $eventDates = StringUtil::deserialize($objEvent->eventDates);

        if (!\is_array($eventDates) || empty($eventDates)) {
            return 0;
        }

        return (int) $eventDates[0]['new_repeat'];
    }

    public function getEndTstamp(CalendarEventsModel $objEvent): int
    {
        $this->framework->initialize();

        $eventDates = StringUtil::deserialize($objEvent->eventDates);

        if (!\is_array($eventDates) || empty($eventDates)) {
            return 0;
        }

        return (int) $eventDates[\count($eventDates) - 1]['new_repeat'];
    }

    public function getEventDuration(CalendarEventsModel $objEvent): string
    {
        $this->framework->initialize();

        if ('' !== $objEvent->durationInfo) {
            return (string) $objEvent->durationInfo;
        }

        $eventDates = StringUtil::deserialize($objEvent->eventDates);

        if (!empty($eventDates) && \is_array($eventDates)) {
            return \sprintf('%s Tage', \count($eventDates));
        }

        return '';
    }

    public function getPublicTransportBadge(): string
    {
        return '<span class="badge badge-sm badge-pill bg-success" data-controller="sacevt--frontend--bs-tooltip" data-bs-tooltip-title="Anreise mit ÖV" data-bs-tooltip-placement="top">ÖV</span>';
    }

    public function getTourTechDifficultiesAsArray(CalendarEventsModel $objEvent, bool $tooltip = false, bool $withTitle = false): array
    {
        $this->framework->initialize();

        $tourDifficultyModel = $this->getAdapter(TourDifficultyModel::class);
        $difficulties = [];

        foreach (StringUtil::deserialize($objEvent->tourTechDifficulty, true) as $difficulty) {
            $shortcut = '';
            $title = '';

            if (\strlen($difficulty['tourTechDifficultyMin'])) {
                $min = $tourDifficultyModel->findById((int) $difficulty['tourTechDifficultyMin']);

                if (null !== $min) {
                    $shortcut = $min->shortcut;
                    $title = $min->title;
                }

                // Difficulty range, e.g. "T3 - T4"
                if (\strlen($difficulty['tourTechDifficultyMax'])) {
                    $max = $tourDifficultyModel->findById((int) $difficulty['tourTechDifficultyMax']);

                    if (null !== $max) {
                        $shortcut .= ' - '.$max->shortcut;
                        $title .= ' - '.$max->title;
                    }
                }
            }

            if ('' === $shortcut) {
                continue;
            }

            if ($tooltip) {
                $html = '<span class="badge badge-sm badge-pill bg-primary" data-controller="sacevt--frontend--bs-tooltip" data-bs-tooltip-title="Techn. Schwierigkeit: %s" data-bs-tooltip-placement="top">%s</span>';
                $difficulties[] = \sprintf($html, StringUtil::specialchars($title), $shortcut);
            } elseif ($withTitle) {
                $difficulties[] = $shortcut.' ('.$title.')';
            } else {
                $difficulties[] = $shortcut;
            }
        }

        return $difficulties;
    }

    public function getTourTechDifficultiesAsGenericArray(CalendarEventsModel $objEvent): array
    {
        $this->framework->initialize();

        $tourDifficultyModel = $this->getAdapter(TourDifficultyModel::class);
        $difficulties = [];

        foreach (StringUtil::deserialize($objEvent->tourTechDifficulty, true) as $difficulty) {
            $min = [];
            $max = [];
            $isEqual = false;

            if (\strlen($difficulty['tourTechDifficultyMin'])) {
                $minModel = $tourDifficultyModel->findById((int) $difficulty['tourTechDifficultyMin']);
                $maxModel = $tourDifficultyModel->findById((int) $difficulty['tourTechDifficultyMax']);

                $min = $this->getTourDifficultyAsArray($minModel);
                $max = $this->getTourDifficultyAsArray($maxModel);
                $isEqual = null !== $minModel && $minModel === $maxModel;
            }

            $difficulties[] = [
                'min' => $min,
                'max' => $max,
                'isEqual' => $isEqual,
            ];
        }

        return $difficulties;
    }

    public function getTourTypesAsArray(CalendarEventsModel $objEvent, string $field = 'shortcut', bool $tooltip = false): array
    {
        $this->framework->initialize();

        $tourTypeModel = $this->getAdapter(TourTypeModel::class);
        $tourTypes = [];

        foreach (StringUtil::deserialize($objEvent->tourType, true) as $id) {
            $tourType = $tourTypeModel->findById($id);

            if (null === $tourType) {
                continue;
            }

            if ($tooltip) {
                $html = '<span class="badge badge-sm badge-pill bg-secondary" data-controller="sacevt--frontend--bs-tooltip" data-bs-tooltip-title="Typ: %s" data-bs-tooltip-placement="top">%s</span>';
                $tourTypes[] = \sprintf($html, StringUtil::specialchars($tourType->title), $tourType->{$field});
            } else {
                $tourTypes[] = $tourType->{$field};
            }
        }

        return $tourTypes;
    }

    public function getBookingCounter(CalendarEventsModel $objEvent, bool $withoutTooltip = false): string
    {
        $this->framework->initialize();

        if (EventState::STATE_CANCELED === $objEvent->eventState) {
            return '';
        }

        $badge = '<span class="badge badge-sm badge-pill bg-%s" data-controller="sacevt--frontend--bs-tooltip" data-bs-tooltip-title="%s" data-bs-tooltip-placement="top">%s</span>';

        if ($withoutTooltip) {
            // Text only, e.g. "noch 1 freie Plätze (5/6)"
            $badge = '%2$s (%3$s)';
        }

        $registrationCount = $this->countAcceptedRegistrations($objEvent);

        if ($objEvent->addMinAndMaxMembers && $objEvent->maxMembers > 0) {
            if ($registrationCount >= $objEvent->maxMembers) {
                return \sprintf($badge, 'dark', 'ausgebucht', $registrationCount.'/'.$objEvent->maxMembers);
            }

            return \sprintf($badge, 'dark', \sprintf('noch %s freie Plätze', StringUtil::specialchars($objEvent->maxMembers - $registrationCount)), $registrationCount.'/'.$objEvent->maxMembers);
        }

        // There is no booking limit. Show the registered members.
        return \sprintf($badge, 'dark', $registrationCount.' bestätigte Plätze', $registrationCount.'/?');
    }

    public function getSubscriptionStateBadges(CalendarEventsModel $objEvent): string
    {
        $this->framework->initialize();

        $registrations = $this->getAdapter(CalendarEventsMemberModel::class)->findByEventId($objEvent->id);

        if (null === $registrations) {
            return '';
        }

        $counts = array_fill_keys(array_keys(self::SUBSCRIPTION_STATE_BADGES), 0);

        while ($registrations->next()) {
            if (isset($counts[$registrations->stateOfSubscription])) {
                ++$counts[$registrations->stateOfSubscription];
            }
        }

        $href = $this->getContainer()->get('router')->generate('contao_backend', [
            'do' => 'calendar',
            'table' => 'tl_calendar_events_member',
            'id' => $objEvent->id,
            'rt' => $this->getContainer()->get('contao.csrf.token_manager')->getDefaultTokenValue(),
            'ref' => $this->getContainer()->get('request_stack')->getCurrentRequest()->attributes->get('_contao_referer_id'),
        ]);

        $href = StringUtil::specialcharsUrl(StringUtil::ampersand($href));
        $badges = '';

        foreach (self::SUBSCRIPTION_STATE_BADGES as $state => [$cssClass, $title]) {
            if ($counts[$state] > 0) {
                $badges .= \sprintf('<span class="subscription-badge %s" data-title="%s" role="button" onclick="window.location.href=\'%s\'">%s</span>', $cssClass, \sprintf($title, $counts[$state]), $href, $counts[$state]);
            }
        }

        return $badges;
    }

    public function getEventOrganizerModels(CalendarEventsModel $objEvent): array
    {
        $this->framework->initialize();

        $eventOrganizerModel = $this->getAdapter(EventOrganizerModel::class);
        $organizers = [];

        foreach (StringUtil::deserialize($objEvent->organizers, true) as $id) {
            $organizer = $eventOrganizerModel->findById($id);

            if (null !== $organizer) {
                $organizers[] = $organizer;
            }
        }

        return $organizers;
    }

    public function getEventOrganizersAsArray(CalendarEventsModel $objEvent, string $field = 'title'): array
    {
        $this->framework->initialize();

        return array_map(static fn ($organizer) => $organizer->{$field}, $this->getEventOrganizerModels($objEvent));
    }

    /**
     * Test if the member has already made another booking at the same time.
     */
    public function areBookingDatesOccupied(CalendarEventsModel $objEvent, MemberModel $objMember): bool
    {
        $this->framework->initialize();

        $eventDates = $this->getNonEmptyRepeats($objEvent);

        // The other events the member is registered for and has not participated yet
        $registrations = $this->getDatabase()->fetchAllAssociative(
            'SELECT * FROM tl_calendar_events_member WHERE eventId != ? AND contaoMemberId = ? AND stateOfSubscription = ? AND hasParticipated = ?',
            [$objEvent->id, $objMember->id, EventSubscriptionState::SUBSCRIPTION_ACCEPTED, 0],
            [Types::INTEGER, Types::INTEGER, Types::STRING, Types::INTEGER],
        );

        $calendarEventsModel = $this->getAdapter(CalendarEventsModel::class);

        foreach ($registrations as $registration) {
            $otherEvent = $calendarEventsModel->findById($registration['eventId']);

            if (null === $otherEvent) {
                continue;
            }

            foreach ($this->getNonEmptyRepeats($otherEvent) as $repeat) {
                if (\in_array($repeat, $eventDates, false)) {
                    return true;
                }
            }
        }

        return false;
    }

    public function generateEventPreviewUrl(CalendarEventsModel $objEvent): string
    {
        $this->framework->initialize();

        if ('' === $objEvent->eventType) {
            return '';
        }

        $eventType = $this->getAdapter(EventTypeModel::class)->findOneBy('alias', $objEvent->eventType);

        if (null === $eventType || !$eventType->previewPage) {
            return '';
        }

        $previewPage = $this->getAdapter(PageModel::class)->findById($eventType->previewPage);

        if (!$previewPage instanceof PageModel) {
            return '';
        }

        /** @var UrlParser $urlParser */
        $urlParser = $this->getContainer()->get(UrlParser::class);

        /** @var UriSigner $uriSigner */
        $uriSigner = $this->getContainer()->get('code4nix_uri_signer.uri_signer');

        $params = \sprintf('/%s', !empty($objEvent->alias) ? $objEvent->alias : $objEvent->id);

        $eventPreviewUrl = $urlParser->addQueryString('event_preview=true', $previewPage->getAbsoluteUrl($params));
        $eventPreviewUrl = StringUtil::specialcharsUrl(StringUtil::ampersand($eventPreviewUrl));

        return $uriSigner->sign($eventPreviewUrl, 86400);
    }

    public function getTourProfileAsArray(CalendarEventsModel $objEvent): array
    {
        $this->framework->initialize();

        $tourProfile = StringUtil::deserialize($objEvent->tourProfile);

        if (empty($objEvent->tourProfile) || !\is_array($tourProfile)) {
            return [];
        }

        $profiles = [];
        $day = 0;

        foreach ($tourProfile as $profile) {
            if (empty($profile['tourProfileAscentMeters']) && empty($profile['tourProfileAscentTime']) && empty($profile['tourProfileDescentMeters']) && empty($profile['tourProfileDescentTime'])) {
                continue;
            }

            ++$day;

            $ascent = [];
            $descent = [];

            if ('' !== $profile['tourProfileAscentMeters']) {
                $ascent[] = \sprintf('%s Hm', $profile['tourProfileAscentMeters']);
            }

            if ('' !== $profile['tourProfileAscentTime']) {
                $ascent[] = \sprintf('%s h', $profile['tourProfileAscentTime']);
            }

            if ('' !== $profile['tourProfileDescentMeters']) {
                $descent[] = \sprintf('%s Hm', $profile['tourProfileDescentMeters']);
            }

            if ('' !== $profile['tourProfileDescentTime']) {
                $descent[] = \sprintf('%s h', $profile['tourProfileDescentTime']);
            }

            $strProfile = \count($tourProfile) > 1 ? \sprintf('%s. Tag: ', $day) : '';

            if (!empty($ascent)) {
                $strProfile .= 'Aufst: '.implode('/', $ascent);
            }

            if (!empty($descent)) {
                $strProfile .= ('' !== $strProfile ? ', ' : '').'Abst: '.implode('/', $descent);
            }

            $profiles[] = $strProfile;
        }

        return $profiles;
    }

    public function getEventOrganizersLogoAsHtml(CalendarEventsModel $objEvent, string $strInsertTag = '{{image::%s&alt=%s}}', bool $allowDuplicate = false): array
    {
        $this->framework->initialize();

        $logos = [];
        $uuids = [];

        foreach ($this->getEventOrganizerModels($objEvent) as $organizer) {
            if (!$organizer->addLogo || empty($organizer->singleSRC)) {
                continue;
            }

            if (!$allowDuplicate && \in_array($organizer->singleSRC, $uuids, false)) {
                continue;
            }

            $uuids[] = $organizer->singleSRC;

            // The image insert tag url-decodes its parameters
            $insertTag = \sprintf(str_replace('alt=%s', 'alt=%2$s', $strInsertTag), StringUtil::binToUuid($organizer->singleSRC), rawurlencode((string) $organizer->title));
            $logo = $this->getContainer()->get('contao.insert_tag.parser')->replace($insertTag);

            if ('' !== $logo) {
                $logos[] = $logo;
            }
        }

        return $logos;
    }

    public function getEventOrganizerLogoPaths(CalendarEventsModel $objEvent, bool $allowDuplicate = false): array
    {
        $this->framework->initialize();

        $filesModel = $this->getAdapter(FilesModel::class);
        $paths = [];

        foreach ($this->getEventOrganizerModels($objEvent) as $organizer) {
            if (!$organizer->addLogo || empty($organizer->singleSRC)) {
                continue;
            }

            $file = $filesModel->findByUuid($organizer->singleSRC);

            if (null === $file) {
                throw new \RuntimeException(\sprintf('The logo of the event organizer "%s" (ID %s) does not exist in the file manager.', $organizer->title, $organizer->id));
            }

            $path = Path::join($this->getProjectDir(), $file->path);

            if (!is_file($path)) {
                continue;
            }

            $paths[] = $path;
        }

        return $allowDuplicate ? $paths : array_unique($paths);
    }

    public function getEventQrCode(CalendarEventsModel $objEvent, array $arrOptions = [], bool $blnAbsoluteUrl = true, bool $blnCache = true): string|null
    {
        $this->framework->initialize();

        $projectDir = $this->getProjectDir();
        $folder = new Folder('system/qrcodes');

        // Symlink (target: "system/qrcodes", link: "public/system/qrcodes")
        $relWebDir = Path::makeRelative(Path::join($projectDir, 'public'), $projectDir);
        SymlinkUtil::symlink($folder->path, Path::join($relWebDir, $folder->path), $projectDir);

        $filepath = \sprintf($folder->path.'/eventQRcode_%s.png', $objEvent->id);

        $defaults = [
            'version' => Version::AUTO,
            'scale' => 4,
            'outputType' => QRCode::OUTPUT_IMAGE_PNG,
            'eccLevel' => QRCode::ECC_L,
        ];

        if ($blnCache) {
            $defaults['cachefile'] = $filepath;
        }

        $options = new QROptions(array_merge($defaults, $arrOptions));

        /** @var ContentUrlGenerator $contentUrlGenerator */
        $contentUrlGenerator = $this->getContainer()->get('contao.routing.content_url_generator');
        $url = $contentUrlGenerator->generate($objEvent, [], $blnAbsoluteUrl ? UrlGeneratorInterface::ABSOLUTE_URL : UrlGeneratorInterface::ABSOLUTE_PATH);

        // Generate the QR code and return the image path
        if ((new QRCode($options))->render($url, $filepath)) {
            return $filepath;
        }

        return null;
    }

    public function getSectionMembershipAsString(MemberModel $objMember): string
    {
        $this->framework->initialize();

        $this->getAdapter(System::class)->loadLanguageFile('tl_member');

        $sections = [];

        foreach (StringUtil::deserialize($objMember->sectionId, true) as $id) {
            $sections[] = $GLOBALS['TL_LANG']['tl_member']['section'][$id] ?? $id;
        }

        return implode(', ', $sections);
    }

    /**
     * Format "2600000, 1200000" (CH1903+) or "600000, 200000" (CH1903).
     */
    public function getCoordsCH1903AsArray(CalendarEventsModel $objEvent): array
    {
        if (empty($objEvent->coordsCH1903)) {
            return [];
        }

        // Remove invalid characters (whitespaces, quotes, ...)
        $coords = explode(',', preg_replace('/[^0-9.,]/', '', html_entity_decode($objEvent->coordsCH1903)));

        return 2 === \count($coords) ? $coords : [];
    }

    public function getGeoLinkUrl(CalendarEventsModel $objEvent): string|null
    {
        $this->framework->initialize();

        $coords = $this->getCoordsCH1903AsArray($objEvent);

        if (empty($coords)) {
            return null;
        }

        return \sprintf($this->getContainer()->getParameter('sacevt.event.geo_link'), $coords[0], $coords[1]);
    }

    /**
     * Only links to a route of the SAC route portal are allowed.
     */
    public function getSacRoutePortalLink(CalendarEventsModel $objEvent): string|null
    {
        $this->framework->initialize();

        if (empty($objEvent->linkSacRoutePortal)) {
            return null;
        }

        $portalLink = html_entity_decode($objEvent->linkSacRoutePortal);

        if (!filter_var($portalLink, FILTER_VALIDATE_URL)) {
            return null;
        }

        $baseLink = $this->getContainer()->getParameter('sacevt.event.sac_route_portal_base_link');

        if (!str_starts_with($portalLink, $baseLink) || $portalLink === $baseLink) {
            return null;
        }

        return $portalLink;
    }

    public function getEventReleaseLevelAsString(CalendarEventsModel $objEvent): string|null
    {
        $this->framework->initialize();

        if (empty($objEvent->id) || empty($objEvent->eventReleaseLevel)) {
            return null;
        }

        $releaseLevel = $this->getAdapter(EventReleaseLevelPolicyModel::class)->findById($objEvent->eventReleaseLevel);

        if (null === $releaseLevel) {
            return null;
        }

        $strLevel = \sprintf('FS: %s', $releaseLevel->level);

        if ($releaseLevel->level <= 1) {
            $strLevel .= ' Entwurf';
        }

        return $strLevel;
    }

    public function isFavoredEvent(CalendarEventsModel $objEvent): bool
    {
        $this->framework->initialize();

        $user = $this->getContainer()->get('security.helper')->getUser();

        if (!$user instanceof FrontendUser) {
            return false;
        }

        return false !== $this->getDatabase()->fetchOne(
            'SELECT id FROM tl_favored_events WHERE eventId = ? AND memberId = ?',
            [$objEvent->id, $user->id],
            [Types::INTEGER, Types::INTEGER],
        );
    }

    /**
     * @param array<string, mixed> $options
     *
     * @return array{includeDisabled: bool, includeHidden: bool, addMainQualification?: bool}
     */
    private function resolveInstructorOptions(array $options, bool $withMainQualification = false): array
    {
        $resolver = new OptionsResolver();
        $resolver->setDefaults([
            'includeDisabled' => false,
            'includeHidden' => true,
        ]);
        $resolver->setAllowedValues('includeDisabled', [true, false]);
        $resolver->setAllowedValues('includeHidden', [true, false]);

        if ($withMainQualification) {
            $resolver->setDefault('addMainQualification', false);
            $resolver->setAllowedValues('addMainQualification', [true, false]);
        }

        return $resolver->resolve($options);
    }

    /**
     * Disabled users and users whose account is not active (start/stop).
     */
    private function isUserDisabled(UserModel $user): bool
    {
        return $user->disable
            || ('' !== $user->stop && $user->stop < time())
            || ('' !== $user->start && $user->start > time());
    }

    private function countAcceptedRegistrations(CalendarEventsModel $event): mixed
    {
        return $this->getDatabase()->fetchOne(
            'SELECT COUNT(id) FROM tl_calendar_events_member WHERE eventId = ? AND stateOfSubscription = ?',
            [$event->id, EventSubscriptionState::SUBSCRIPTION_ACCEPTED],
            [Types::INTEGER, Types::STRING],
        );
    }

    /**
     * The registration starts with an offset (see sacevt.event_registration.config.reg_start_time_offset).
     */
    private function getRegistrationStartTime(CalendarEventsModel $event): mixed
    {
        return $event->registrationStartDate + $this->getContainer()->getParameter('sacevt.event_registration.config.reg_start_time_offset');
    }

    /**
     * @return list<mixed>
     */
    private function getNonEmptyRepeats(CalendarEventsModel $event): array
    {
        $repeats = [];

        foreach (StringUtil::deserialize($event->eventDates, true) as $eventDate) {
            if (!empty($eventDate['new_repeat'])) {
                $repeats[] = $eventDate['new_repeat'];
            }
        }

        return $repeats;
    }

    /**
     * @return array<string, mixed>
     */
    private function getTourDifficultyAsArray(TourDifficultyModel|null $tourDifficulty): array
    {
        if (null === $tourDifficulty) {
            return [];
        }

        $stringUtil = $this->getAdapter(StringUtil::class);
        $category = $tourDifficulty->getRelated('pid');

        return [
            'id' => $tourDifficulty->id,
            'shortcut' => $stringUtil->revertInputEncoding($tourDifficulty->shortcut),
            'title' => $stringUtil->revertInputEncoding($tourDifficulty->title),
            'description' => $stringUtil->revertInputEncoding($tourDifficulty->description),
            'category' => [
                'id' => $category?->id,
                'title' => $stringUtil->revertInputEncoding($category?->title),
            ],
        ];
    }

    private function getDatabase(): Connection
    {
        return $this->getContainer()->get('database_connection');
    }

    private function getProjectDir(): string
    {
        return $this->getContainer()->getParameter('kernel.project_dir');
    }

    private function getContainer(): ContainerInterface
    {
        return $this->getAdapter(System::class)->getContainer();
    }

    private function getAdapter(string $class): Adapter
    {
        return $this->framework->getAdapter($class);
    }
}
