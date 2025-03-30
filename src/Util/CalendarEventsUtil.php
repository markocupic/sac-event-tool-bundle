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
use Contao\FilesModel;
use Contao\Folder;
use Contao\FrontendUser;
use Contao\MemberModel;
use Contao\PageModel;
use Contao\StringUtil;
use Contao\System;
use Contao\Template;
use Contao\UserModel;
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
readonly class CalendarEventsUtil
{
    public function __construct(
        private ContaoFramework $framework,
    ) {
    }

    /**
     * @param Template|null $objTemplate
     */
    public function getEventData(CalendarEventsModel $objEvent, string $strProperty, Template|null $objTemplate = null): mixed
    {
        $this->framework->initialize();

        // Load language files
        $this->getAdapter(System::class)->loadLanguageFile('tl_calendar_events');
        $this->getAdapter(System::class)->loadLanguageFile('default');

        $value = '';

        // Add arguments with a query string eventImage?size=5
        $parts = explode('?', $strProperty, 2);
        $key = $parts[0];
        parse_str(html_entity_decode($parts[1] ?? ''), $options);

        // Handle false and true
        $options = array_map(
            static function ($v) {
                return match ($v) {
                    'true' => true,
                    'false' => false,
                    default => $v,
                };
            },
            $options,
        );

        switch ($key) {
            case 'model':
                $value = $objEvent;
                break;

            case 'id':
                $value = $objEvent->id;
                break;

            case 'eventId':
                $value = sprintf('%s-%s', date('Y', (int) $objEvent->startDate), $objEvent->id);
                break;

            case 'eventTitle':
                $value = StringUtil::revertInputEncoding($objEvent->title);
                break;

            case 'eventUrl':
                $blnAbsolute = false;

                $value = $this->getContainer()
                    ->get('contao.routing.content_url_generator')
                    ->generate(
                        $objEvent,
                        [],
                        $blnAbsolute ? UrlGeneratorInterface::ABSOLUTE_URL : UrlGeneratorInterface::ABSOLUTE_PATH,
                    )
                ;
                break;

            case 'tourTypesIds':
                $value = implode('', StringUtil::deserialize($objEvent->tourType, true));
                break;

            case 'tourTypesShortcuts':
                $value = implode(' ', $this->getTourTypesAsArray($objEvent, 'shortcut', true));
                break;

            case 'tourTypesTitles':
                $value = implode('<br>', $this->getTourTypesAsArray($objEvent, 'title'));
                break;

            case 'startDateDay':
                $value = date('d', (int) $objEvent->startDate);
                break;

            case 'startDateMonth':
                $value = date('M', (int) $objEvent->startDate);
                break;

            case 'startDateYear':
                $value = date('y', (int) $objEvent->startDate);
                break;

            case 'endDateDay':
                $value = date('d', (int) $objEvent->endDate);
                break;

            case 'endDateMonth':
                $value = date('M', (int) $objEvent->endDate);
                break;

            case 'endDateYear':
                $value = date('y', (int) $objEvent->endDate);
                break;

            case 'eventPeriodSmTooltip':
            case 'eventPeriodSm':
                $value = $this->getEventPeriod($objEvent, 'd.m.Y', false);
                break;

            case 'eventPeriodLgInline':
                $value = $this->getEventPeriod($objEvent, 'D, d.m.Y', false, true, true);
                break;

            case 'eventPeriodLgTooltip':
            case 'eventPeriodLg':
                $value = $this->getEventPeriod($objEvent, 'D, d.m.Y', false);
                break;

            case 'eventDuration':
                $value = $this->getEventDuration($objEvent);
                break;

            case 'registrationStartDateWithOffsetFormatted':
                $regStartTime = $objEvent->registrationStartDate + $this->getContainer()->getParameter('sacevt.event_registration.config.reg_start_time_offset');
                $value = date($this->getAdapter(Config::class)->get('dateFormat'), (int) $regStartTime);
                break;

            case 'registrationStartTimeWithOffsetFormatted':
                $regStartTime = $objEvent->registrationStartDate + $this->getContainer()->getParameter('sacevt.event_registration.config.reg_start_time_offset');
                $value = date($this->getAdapter(Config::class)->get('datimFormat'), (int) $regStartTime);
                break;

            case 'registrationEndDateFormatted':
                // If registration end time! is set to default --> 23:59 then only show registration end date!
                $endDate = date($this->getAdapter(Config::class)->get('dateFormat'), (int) $objEvent->registrationEndDate);

                if (abs($objEvent->registrationEndDate - strtotime($endDate)) === (24 * 3600) - 60) {
                    $formatedEndDate = date($this->getAdapter(Config::class)->get('dateFormat'), (int) $objEvent->registrationEndDate);
                } else {
                    $formatedEndDate = date($this->getAdapter(Config::class)->get('datimFormat'), (int) $objEvent->registrationEndDate);
                }
                $value = $formatedEndDate;
                break;

            case 'eventState':
                $value = $this->getEventState($objEvent);
                break;

            case 'eventStateIcon':
                $value = $this->getEventStateIcon($objEvent);
                break;

            case 'eventStateLabel':
                $value = '' !== $GLOBALS['TL_LANG']['MSC']['calendar_events'][$this->getEventState($objEvent)] ? $GLOBALS['TL_LANG']['MSC']['calendar_events'][$this->getEventState($objEvent)] : $this->getEventState($objEvent);

                if (EventState::STATE_RESCHEDULED === $objEvent->eventState) {
                    $dateFormat = $this->getAdapter(Config::class)->get('dateFormat');
                    $newDate = $objEvent->rescheduledEventDate ? date($dateFormat, (int) $objEvent->rescheduledEventDate) : 'unbest';
                    $value = sprintf($GLOBALS['TL_LANG']['MSC']['calendar_events'][$this->getEventState($objEvent)], $newDate);
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
                $value = $this->getBookingCounter($objEvent);
                break;

            case 'bookingCounterAsText':
                $value = $this->getBookingCounter($objEvent, true);
                break;

            case 'minMembers':
                $value = $objEvent->minMembers;
                break;

            case 'tourTechDifficultiesAsArray':
                $value = $this->getTourTechDifficultiesAsArray($objEvent, false, false);
                break;

            case 'tourTechDifficultiesAsArrayWithExplanation':
                $value = $this->getTourTechDifficultiesAsArray($objEvent, false, true);
                break;

            case 'tourTechDifficulties':
                $value = implode(' ', $this->getTourTechDifficultiesAsArray($objEvent, true, false));
                break;

            case 'instructorsWithQualification':
            case 'instructors':
                $value = implode(', ', $this->getInstructorNamesAsArray($objEvent, $options));
                break;

            case 'journey':
                $adapter = $this->getAdapter(CalendarEventsJourneyModel::class);
                $value = null !== $adapter->findByPk($objEvent->journey) ? $adapter->findByPk($objEvent->journey)->title : '';
                break;

            case 'courseTypeLevel1':
                $value = $objEvent->courseTypeLevel1;
                break;

            case 'eventImagePath':
                $value = $this->getEventImagePath($objEvent);
                break;

            case 'eventImage':
                if (!empty($options['size'])) {
                    $pictureSize = $options['size'];
                    $src = $this->getEventImagePath($objEvent);
                    $parser = $this->getContainer()->get('contao.insert_tag.parser');
                    $value = $parser->replace(sprintf('{{picture::%s?size=%s}}', $src, $pictureSize));
                }
                break;

            case 'courseLevelName':
                $value = $this->getContainer()->get(CourseLevels::class)->get($objEvent->courseLevel);
                break;

            case 'courseTypeLevel0Name':
                $adapter = $this->getAdapter(CourseMainTypeModel::class);
                $value = $adapter->findByPk($objEvent->courseTypeLevel0)?->name ?? '';
                break;

            case 'courseTypeLevel1Name':
                $adapter = $this->getAdapter(CourseSubTypeModel::class);
                $value = $adapter->findByPk($objEvent->courseTypeLevel1)?->name ?? '';
                break;

            // inside vue.js templates: eventOrganizerLogos?width=60
            // The first parameter defines the logo width
            case 'eventOrganizerLogos':
                $width = !empty($options['width']) ? $options['width'] : '60';
                $strInsertTag = '{{image::%s?width='.$width.'&alt=%s}}';
                $value = $this->getEventOrganizersLogoAsHtml($objEvent, $strInsertTag);
                break;

            case 'eventOrganizerLogoPaths':
                $allowDuplicate = !empty($options['allowDuplicate']) && 'true' === $options['allowDuplicate'];
                $value = $this->getEventOrganizerLogoPaths($objEvent, $allowDuplicate);
                break;

            case 'eventOrganizers':
                $value = implode('<br>', $this->getEventOrganizersAsArray($objEvent));
                break;

            case 'mainInstructorContactDataFromDb':
                $value = $this->generateMainInstructorContactDataFromDb($objEvent, $options);
                break;

            case 'instructorContactBoxes':
                $value = $this->generateInstructorContactBoxes($objEvent, $options);
                break;

            case 'arrTourProfile':
                $value = $this->getTourProfileAsArray($objEvent);
                break;

            case 'geoLink':
                $value = $objEvent->geoLink;
                break;

            case 'hasCoords':
                $value = !empty($this->getCoordsCH1903AsArray($objEvent));
                break;

            case 'coordsCH1903':
                $value = $this->getCoordsCH1903AsArray($objEvent);
                break;

            case 'geoLinkUrl':
                $value = $this->getGeoLinkUrl($objEvent);
                break;

            case 'linkSacRoutePortal':
                $value = $this->getSacRoutePortalLink($objEvent);
                break;

            case 'isPublicTransportEvent':
                $value = $this->isPublicTransportEvent($objEvent);
                break;

            case 'getPublicTransportBadge':
                $value = $this->getPublicTransportBadge();
                break;

            case 'isFavoredEvent':
                $value = $this->isFavoredEvent($objEvent);
                break;

            case 'gallery':
                $rowEvent = $objEvent->row();
                $rowEvent['sortBy'] = 'custom';
                $rowEvent['perRow'] = 4;
                $rowEvent['size'] = serialize([400, 400, 'center_center', 'proportional']);
                $rowEvent['fullsize'] = true;
                $rowEvent['customTpl'] = 'content_element/gallery/col_4_with_caption';

                $value = $this->getGallery($rowEvent);
                break;

            default:
                $arrEvent = $objEvent->row();

                if (null !== $objTemplate && isset($objTemplate->{$key})) {
                    $value = $objTemplate->{$key};
                } elseif (isset($arrEvent[$key])) {
                    $value = $arrEvent[$key];
                } else {
                    $value = '';
                }
        }

        return $value;
    }

    public function isPublicTransportEvent(CalendarEventsModel $objEvent): bool
    {
        $this->framework->initialize();

        $isPublicTransport = false;

        $database = $this->getContainer()->get('database_connection');

        $idPublicTransportJourney = $database->fetchOne(
            'SELECT id from tl_calendar_events_journey WHERE alias = ?',
            [
                'public-transport',
            ],
            [
                Types::STRING,
            ],
        );

        if ($idPublicTransportJourney) {
            if ((int) $objEvent->journey === (int) $idPublicTransportJourney) {
                $isPublicTransport = true;
            }
        }

        return $isPublicTransport;
    }

    /**
     * @param array{includeDisabled?: bool, includeHidden?: bool} $options
     */
    public function generateInstructorContactBoxes(CalendarEventsModel $objEvent, array $options): string
    {
        $this->framework->initialize();

        $resolver = new OptionsResolver();
        $resolver->setDefaults([
            'includeDisabled' => false,
            'includeHidden' => true,
        ]);
        $resolver->setAllowedValues('includeDisabled', [true, false]);
        $resolver->setAllowedValues('includeHidden', [true, false]);
        $options = $resolver->resolve($options);

        $objCalendar = $objEvent->getRelated('pid');

        $avatarManager = $this->getContainer()->get(Avatar::class);

        $objPage = $this->getAdapter(PageModel::class)->findByPk($objCalendar->userPortraitJumpTo);

        if (null === $objPage) {
            throw new \Exception('Page model not found.');
        }

        $arrInstructors = $this->getInstructorsAsArray($objEvent, $options);
        $arrItems = [];

        foreach ($arrInstructors as $userId) {
            $objUser = $this->getAdapter(UserModel::class)->findByPk($userId);

            $contentUrlGenerator = $this->getContainer()->get('contao.routing.content_url_generator');

            if (null === $objUser || $objUser->hideUser) {
                continue;
            }

            $arrInstructor = $objUser->row();
            $arrInstructor['href'] = $contentUrlGenerator->generate($objPage).'?getUpcoming=1&username='.$objUser->username;
            $arrInstructor['has_link'] = true;

            $arrInstructor['avatar_path'] = $avatarManager->getAvatarResourcePath($objUser);
            $arrInstructor['main_qualification'] = !empty($this->getMainQualification($objUser)) ? $this->getMainQualification($objUser) : '';
            $arrInstructor['contact_options'] = [];

            $arrContact = ['phone', 'mobile', 'email'];

            foreach ($arrContact as $field) {
                if ('' === $objUser->{$field}) {
                    continue;
                }

                $arrInstructor['contact_options'][$field] = $objUser->{$field};
            }

            $arrItems[] = $arrInstructor;
        }

        $twig = $this->getContainer()->get('twig');

        return $twig->render('@MarkocupicSacEventTool/Calendar/instructor_contact_boxes.html.twig', ['instructors' => $arrItems]);
    }

    public function getEventState(CalendarEventsModel $objEvent): string
    {
        $this->framework->initialize();

        $database = $this->getContainer()->get('database_connection');

        $registrationCount = $database->fetchOne(
            'SELECT COUNT(id) FROM tl_calendar_events_member WHERE eventId = ? AND stateOfSubscription = ?',
            [
                $objEvent->id,
                EventSubscriptionState::SUBSCRIPTION_ACCEPTED,
            ],
            [
                Types::INTEGER,
                Types::STRING,
            ],
        );

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
        if ($objEvent->startDate <= time() || $objEvent->endDate <= time() || ($objEvent->setRegistrationPeriod && $objEvent->registrationEndDate < time())) {
            return 'event_status_2';
        }

        // Max participant number reached -> waiting list still possible
        if ($objEvent->maxMembers > 0 && $registrationCount >= $objEvent->maxMembers) {
            return 'event_status_8';
        }

        // If online registration is disabled in the event settings
        if ($objEvent->disableOnlineRegistration) {
            return 'event_status_7';
        }

        // Booking not possible yet
        $regStartTime = $objEvent->registrationStartDate + $this->getContainer()->getParameter('sacevt.event_registration.config.reg_start_time_offset');

        if ($objEvent->setRegistrationPeriod && $regStartTime > time()) {
            return 'event_status_5';
        }

        return 'event_status_1';
    }

    public function getEventStateIcon(CalendarEventsModel $objEvent): string
    {
        $this->framework->initialize();

        $strState = $this->getEventState($objEvent);
        $strLabel = $GLOBALS['TL_LANG']['MSC']['calendar_events'][$strState] ?? $strState;

        /** @var Packages $packages */
        $packages = $this->getContainer()->get('assets.packages');

        return sprintf(
            '<img src="%s" title="%s">',
            $packages->getUrl("icons/event_states/$strState.svg", 'markocupic_sac_event_tool'),
            $strLabel,
        );
    }

    public function eventIsFullyBooked(CalendarEventsModel $objEvent): bool
    {
        $this->framework->initialize();

        $database = $this->getContainer()->get('database_connection');

        $registrationCount = $database->fetchOne(
            'SELECT COUNT(id) FROM tl_calendar_events_member WHERE eventId = ? AND stateOfSubscription = ?',
            [
                $objEvent->id,
                EventSubscriptionState::SUBSCRIPTION_ACCEPTED,
            ],
            [
                Types::INTEGER,
                Types::STRING,
            ],
        );

        if (EventState::STATE_FULLY_BOOKED === $objEvent->eventState || ($objEvent->maxMembers > 0 && $registrationCount >= $objEvent->maxMembers)) {
            return true;
        }

        return false;
    }

    public function getMainInstructor(CalendarEventsModel $objEvent): UserModel|null
    {
        $this->framework->initialize();

        $database = $this->getContainer()->get('database_connection');

        $id = $database->fetchOne(
            'SELECT userId FROM tl_calendar_events_instructor WHERE pid = ? AND isMainInstructor = ?',
            [
                $objEvent->id,
                1,
            ],
            [
                Types::INTEGER,
                Types::INTEGER,
            ]
        );

        if (false === $id) {
            return null;
        }

        return $this->getAdapter(UserModel::class)?->findByPk($id);
    }

    public function getMainInstructorName(CalendarEventsModel $objEvent): string
    {
        $this->framework->initialize();

        $objUser = $this->getMainInstructor($objEvent);

        if (null === $objUser) {
            return '';
        }

        return implode(' ', array_filter([$objUser->lastname, $objUser->firstname]));
    }

    /**
     * @param array{includeDisabled?: bool, includeHidden?: bool} $options
     */
    public function generateMainInstructorContactDataFromDb(CalendarEventsModel $objEvent, array $options = []): string
    {
        $this->framework->initialize();

        $resolver = new OptionsResolver();
        $resolver->setDefaults([
            'includeDisabled' => false,
            'includeHidden' => true,
        ]);
        $resolver->setAllowedValues('includeDisabled', [true, false]);
        $resolver->setAllowedValues('includeHidden', [true, false]);
        $options = $resolver->resolve($options);

        $arrInstructors = $this->getInstructorsAsArray($objEvent, $options);
        $objUser = $this->getAdapter(UserModel::class)->findByPk($arrInstructors[0]);

        if (null === $objUser) {
            return '';
        }

        $arrContact = [];
        $arrContact[] = sprintf('<strong>%s %s</strong>', $objUser->lastname, $objUser->firstname);

        if ('' !== $objUser->phone) {
            $arrContact[] = sprintf('Tel.: %s', $objUser->phone);
        }

        if ('' !== $objUser->mobile) {
            $arrContact[] = sprintf('Mobile.: %s', $objUser->mobile);
        }

        if ('' !== $objUser->email) {
            $arrContact[] = sprintf('E-Mail: %s', $objUser->email);
        }

        $arrContact = array_filter($arrContact);

        return implode(', ', $arrContact);
    }

    /**
     * @param array{includeDisabled?: bool, includeHidden?: bool} $options
     */
    public function getInstructorsAsArray(CalendarEventsModel $objEvent, array $options = []): array
    {
        $this->framework->initialize();

        $resolver = new OptionsResolver();
        $resolver->setDefaults([
            'includeDisabled' => false,
            'includeHidden' => true,
        ]);
        $resolver->setAllowedValues('includeDisabled', [true, false]);
        $resolver->setAllowedValues('includeHidden', [true, false]);
        $options = $resolver->resolve($options);

        $arrInstructors = [];

        $database = $this->getContainer()->get('database_connection');

        // Get all instructors from a specific event, list the mainInstructor first
        $userIds = $database->fetchFirstColumn(
            'SELECT userId FROM tl_calendar_events_instructor WHERE pid = ? ORDER BY isMainInstructor DESC',
            [
                $objEvent->id,
            ],
            [
                Types::INTEGER,
            ],
        );

        $userAdapter = $this->getAdapter(UserModel::class);

        foreach ($userIds as $userId) {
            $objUser = $userAdapter->findByPk($userId);

            if (null === $objUser) {
                continue;
            }

            if (false === $options['includeDisabled'] && $objUser->disable) {
                continue;
            }

            if (false === $options['includeDisabled'] && ('' !== $objUser->stop && $objUser->stop < time())) {
                continue;
            }

            if (false === $options['includeDisabled'] && ('' !== $objUser->start && $objUser->start > time())) {
                continue;
            }

            if (false === $options['includeHidden'] && $objUser->hideUser) {
                continue;
            }

            $arrInstructors[] = $objUser->id;
        }

        return $arrInstructors;
    }

    /**
     * @param array{includeDisabled?: bool, includeHidden?: bool, addMainQualification?: bool} $options
     */
    public function getInstructorNamesAsArray(CalendarEventsModel $objEvent, array $options = []): array
    {
        $this->framework->initialize();

        $resolver = new OptionsResolver();
        $resolver->setDefaults([
            'includeDisabled' => false,
            'includeHidden' => true,
            'addMainQualification' => false,
        ]);
        $resolver->setAllowedValues('includeDisabled', [true, false]);
        $resolver->setAllowedValues('includeHidden', [true, false]);
        $resolver->setAllowedValues('addMainQualification', [true, false]);
        $options = $resolver->resolve($options);

        $arrInstructors = [];

        $arrUsers = $this->getInstructorsAsArray(
            $objEvent,
            [
                'includeDisabled' => $options['includeDisabled'],
                'includeHidden' => $options['includeHidden'],
            ],
        );

        foreach ($arrUsers as $userId) {
            $objUser = $this->getAdapter(UserModel::class)->findByPk($userId);

            if (null === $objUser) {
                continue;
            }

            $strName = trim($objUser->lastname.' '.$objUser->firstname);

            if (true === $options['addMainQualification'] && '' !== $this->getMainQualification($objUser)) {
                $arrInstructors[] = $strName.' ('.$this->getMainQualification($objUser).')';
            } else {
                $arrInstructors[] = $strName;
            }
        }

        return $arrInstructors;
    }

    public function getMainQualification(UserModel $objUser): string
    {
        $this->framework->initialize();

        $strQuali = '';

        $arrQuali = StringUtil::deserialize($objUser->leiterQualifikation, true);

        if (!empty($arrQuali[0])) {
            $this->getAdapter(System::class)->loadLanguageFile('tl_user');
            $strQuali = $GLOBALS['TL_LANG']['tl_user']['refLeiterQualifikation'][(int) $arrQuali[0]] ?? 'undefined';
        }

        return $strQuali;
    }

    public function getGallery(array $arrData): string
    {
        $this->framework->initialize();

        $arrData['type'] = 'gallery';
        $arrData['tstamp'] = 0;

        if (empty($arrData['perRow'])) {
            $arrData['perRow'] = 4;
        }

        $objModel = new ContentModel();
        $objModel->setRow($arrData);

        return $this->getAdapter(Controller::class)->getContentElement($objModel);
    }

    public function getEventImagePath(CalendarEventsModel $objEvent): string
    {
        $this->framework->initialize();

        $fallbackImage = $this->getContainer()->getParameter('sacevt.event.course.fallback_image');

        if (empty($objEvent->singleSRC)) {
            return $fallbackImage;
        }

        $objFile = $this->getAdapter(FilesModel::class)->findByUuid($objEvent->singleSRC);

        if (null === $objFile) {
            return $fallbackImage;
        }

        $path = Path::join($this->getProjectDir(), $objFile->path);

        if (!is_file($path)) {
            return $fallbackImage;
        }

        return $objFile->path;
    }

    public function getEventPeriod(CalendarEventsModel $objEvent, string $dateFormat = '', bool $blnAppendEventDuration = true, bool $blnTooltip = true, bool $blnInline = false): string
    {
        $this->framework->initialize();

        if (empty($dateFormat)) {
            $dateFormat = $this->getAdapter(Config::class)->get('dateFormat');
        }

        $dateFormatShortened = $dateFormat;

        if ('d.m.Y' === $dateFormat) {
            $dateFormatShortened = 'd.m.';
        }

        $eventDuration = \count($this->getEventTimestamps($objEvent));

        // Typecast is required here, this although PhpCodeSniffer claims the opposite.
        // Calendar::calculateSpan() returns "double" not "integer"
        $span = (int) Calendar::calculateSpan($this->getStartTstamp($objEvent), $this->getEndTstamp($objEvent)) + 1;

        if (1 === $eventDuration) {
            $strEventDuration = $blnAppendEventDuration ? ' ('.$this->getEventDuration($objEvent).')' : '';

            return date($dateFormat, $this->getStartTstamp($objEvent)).$strEventDuration;
        }

        if ($span === $eventDuration) {
            $strEventDuration = $blnAppendEventDuration ? ' ('.$this->getEventDuration($objEvent).')' : '';

            return date($dateFormatShortened, $this->getStartTstamp($objEvent)).' - '.date($dateFormat, $this->getEndTstamp($objEvent)).$strEventDuration;
        }

        $arrDates = [];
        $dates = $this->getEventTimestamps($objEvent);

        foreach ($dates as $date) {
            $arrDates[] = date($dateFormat, (int) $date);
        }

        if ($blnTooltip) {
            $strEventDuration = $blnAppendEventDuration ? ' ('.$this->getEventDuration($objEvent).')' : '';
            $strTooltip = '<a tabindex="0" class="more-date-infos" data-bs-toggle="tooltip" data-placement="bottom" data-title="Eventdaten: '.implode(', ', $arrDates).'">und weitere</a>';

            return date($dateFormat, $this->getStartTstamp($objEvent)).$strEventDuration.(!$blnInline ? '<br>' : ' ').$strTooltip;
        }

        $dateString = '';

        foreach ($this->getEventTimestamps($objEvent) as $tstamp) {
            $dateString .= sprintf('<time datetime="%s">%s</time>', date('Y-m-d', (int) $tstamp), date('D, d.m.Y', (int) $tstamp));
        }
        $dateString .= $blnAppendEventDuration ? sprintf('<time>(%s)</time>', $this->getEventDuration($objEvent)) : '';

        return $dateString;
    }

    public function getBookingPeriod(int $id, string $dateFormatStart = '', string $dateFormatEnd = ''): string
    {
        $this->framework->initialize();

        $objEvent = $this->getAdapter(CalendarEventsModel::class)->findByPk($id);

        if (null === $objEvent) {
            return '';
        }

        if (!$objEvent->setRegistrationPeriod) {
            return '';
        }

        if ('' === $dateFormatStart) {
            $dateFormatStart = $this->getAdapter(Config::class)->get('dateFormat');
        }

        if ('' === $dateFormatEnd) {
            $dateFormatEnd = $this->getAdapter(Config::class)->get('dateFormat');
        }

        $regStartTime = $objEvent->registrationStartDate + $this->getContainer()->getParameter('sacevt.event_registration.config.reg_start_time_offset');

        return date($dateFormatStart, (int) $regStartTime).' - '.date($dateFormatEnd, (int) $objEvent->registrationEndDate);
    }

    public function getEventTimestamps(CalendarEventsModel $objEvent): array
    {
        $this->framework->initialize();

        $arrRepeats = [];

        $arrDates = StringUtil::deserialize($objEvent->eventDates, true);

        foreach ($arrDates as $v) {
            $arrRepeats[] = $v['new_repeat'];
        }

        return $arrRepeats;
    }

    public function getStartTstamp(CalendarEventsModel $objEvent): int
    {
        $this->framework->initialize();

        $arrDates = StringUtil::deserialize($objEvent->eventDates);

        if (!\is_array($arrDates) || empty($arrDates)) {
            return 0;
        }

        return (int) $arrDates[0]['new_repeat'];
    }

    public function getEndTstamp(CalendarEventsModel $objEvent): int
    {
        $this->framework->initialize();

        $arrDates = StringUtil::deserialize($objEvent->eventDates);

        if (!\is_array($arrDates) || empty($arrDates)) {
            return 0;
        }

        return (int) $arrDates[\count($arrDates) - 1]['new_repeat'];
    }

    public function getEventDuration(CalendarEventsModel $objEvent): string
    {
        $this->framework->initialize();

        $arrDates = StringUtil::deserialize($objEvent->eventDates);

        if ('' !== $objEvent->durationInfo) {
            return (string) $objEvent->durationInfo;
        }

        if (!empty($arrDates) && \is_array($arrDates)) {
            return sprintf('%s Tage', \count($arrDates));
        }

        return '';
    }

    public function getPublicTransportBadge(): string
    {
        return '<span class="badge badge-sm badge-pill bg-success" data-bs-toggle="tooltip" data-placement="top" data-title="Anreise mit ÖV">ÖV</span>';
    }

    public function getTourTechDifficultiesAsArray(CalendarEventsModel $objEvent, bool $tooltip = false, bool $explanation = false): array
    {
        $this->framework->initialize();

        $arrReturn = [];

        $arrValues = StringUtil::deserialize($objEvent->tourTechDifficulty, true);

        if (empty($arrValues)) {
            return $arrReturn;
        }

        foreach ($arrValues as $difficulty) {
            $strDiff = '';
            $strDiffTitle = '';

            if (\strlen($difficulty['tourTechDifficultyMin']) && \strlen($difficulty['tourTechDifficultyMax'])) {
                $objDiff = $this->getAdapter(TourDifficultyModel::class)->findByPk((int) $difficulty['tourTechDifficultyMin']);

                if (null !== $objDiff) {
                    $strDiff = $objDiff->shortcut;
                    $strDiffTitle = $objDiff->title;
                }

                $objDiff = $this->getAdapter(TourDifficultyModel::class)->findByPk((int) $difficulty['tourTechDifficultyMax']);

                if (null !== $objDiff) {
                    $max = $objDiff->shortcut;
                    $strDiff .= ' - '.$max;
                    $strDiffTitle .= ' - '.$objDiff->title;
                }
            } elseif (\strlen($difficulty['tourTechDifficultyMin'])) {
                $objDiff = $this->getAdapter(TourDifficultyModel::class)->findByPk((int) $difficulty['tourTechDifficultyMin']);

                if (null !== $objDiff) {
                    $strDiff = $objDiff->shortcut;
                    $strDiffTitle = $objDiff->title;
                }
            }

            if ('' === $strDiff) {
                continue;
            }

            if ($tooltip) {
                $html = '<span class="badge badge-sm badge-pill bg-primary" data-bs-toggle="tooltip" data-placement="top" data-title="Techn. Schwierigkeit: %s">%s</span>';
                $arrReturn[] = sprintf($html, $strDiffTitle, $strDiff);
            } elseif ($explanation) {
                $arrReturn[] = $strDiff.' ('.$strDiffTitle.')';
            } else {
                $arrReturn[] = $strDiff;
            }
        }

        return $arrReturn;
    }

    public function getTourTypesAsArray(CalendarEventsModel $objEvent, string $field = 'shortcut', bool $tooltip = false): array
    {
        $this->framework->initialize();

        $arrTourTypes = [];

        $arrValues = StringUtil::deserialize($objEvent->tourType, true);

        if (empty($arrValues)) {
            return $arrTourTypes;
        }

        foreach ($arrValues as $id) {
            $objTourType = $this->getAdapter(TourTypeModel::class)->findByPk($id);

            if (null === $objTourType) {
                continue;
            }

            if ($tooltip) {
                $html = '<span class="badge badge-sm badge-pill bg-secondary" data-bs-toggle="tooltip" data-placement="top" data-title="Typ: %s">%s</span>';
                $arrTourTypes[] = sprintf($html, $objTourType->title, $objTourType->{$field});
            } else {
                $arrTourTypes[] = $objTourType->{$field};
            }
        }

        return $arrTourTypes;
    }

    public function getBookingCounter(CalendarEventsModel $objEvent, bool $withoutTooltip = false): string
    {
        $this->framework->initialize();

        $strBadge = '<span class="badge badge-sm badge-pill bg-%s" data-bs-toggle="tooltip" data-placement="top" data-title="%s">%s</span>';

        if ($withoutTooltip) {
            $strBadge = '%2$s (%3$s)'; // only text as output, e.g. 'noch 1 freie Plätze (5/6)`
        }

        $database = $this->getContainer()->get('database_connection');

        $registrationCount = $database->fetchOne(
            'SELECT COUNT(id) FROM tl_calendar_events_member WHERE eventId = ? && stateOfSubscription = ?',
            [
                $objEvent->id,
                EventSubscriptionState::SUBSCRIPTION_ACCEPTED,
            ],
            [
                Types::INTEGER,
                Types::STRING,
            ],
        );

        if (EventState::STATE_CANCELED === $objEvent->eventState) {
            // Event canceled
            return '';
        }

        if ($objEvent->addMinAndMaxMembers && $objEvent->maxMembers > 0) {
            if ($registrationCount >= $objEvent->maxMembers) {
                // Event fully booked
                return sprintf($strBadge, 'dark', 'ausgebucht', $registrationCount.'/'.$objEvent->maxMembers);
            }

            // Free places available
            return sprintf($strBadge, 'dark', sprintf('noch %s freie Plätze', $objEvent->maxMembers - $registrationCount), $registrationCount.'/'.$objEvent->maxMembers);
        }

        // There is no booking limit. Show registered members
        return sprintf($strBadge, 'dark', $registrationCount.' bestätigte Plätze', $registrationCount.'/?');
    }

    public function getEventStateOfSubscriptionBadgesString(CalendarEventsModel $objEvent): string
    {
        $this->framework->initialize();

        $strRegistrationsBadges = '';
        $intNotConfirmed = 0;
        $intAccepted = 0;
        $intRefused = 0;
        $intWaitlisted = 0;
        $intUnsubscribedUser = 0;

        $eventsMemberModel = $this->getAdapter(CalendarEventsMemberModel::class)->findByEventId($objEvent->id);

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
            $router = $this->getContainer()->get('router');

            $href = $router->generate('contao_backend', [
                'do' => 'calendar',
                'table' => 'tl_calendar_events_member',
                'id' => $objEvent->id,
                'rt' => $this->getContainer()->get('contao.csrf.token_manager')->getDefaultTokenValue(),
                'ref' => $this->getContainer()->get('request_stack')->getCurrentRequest()->attributes->get('_contao_referer_id'),
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

    public function getEventOrganizersAsArray(CalendarEventsModel $objEvent, string $field = 'title'): array
    {
        $this->framework->initialize();

        $arrReturn = [];

        $arrValues = StringUtil::deserialize($objEvent->organizers, true);

        if (empty($arrValues)) {
            return $arrReturn;
        }

        foreach ($arrValues as $id) {
            $objModel = $this->getAdapter(EventOrganizerModel::class)->findByPk($id);

            if (null === $objModel) {
                continue;
            }

            $arrReturn[] = $objModel->{$field};
        }

        return $arrReturn;
    }

    /**
     * Test if the member has already made another booking at the same time.
     */
    public function areBookingDatesOccupied(CalendarEventsModel $objEvent, MemberModel $objMember): bool
    {
        $this->framework->initialize();

        $arrEventDates = [];
        $arrEventRepeats = StringUtil::deserialize($objEvent->eventDates, true);

        if (!empty($arrEventRepeats) && \is_array($arrEventRepeats)) {
            foreach ($arrEventRepeats as $eventRepeat) {
                if (!empty($eventRepeat['new_repeat'])) {
                    $arrEventDates[] = $eventRepeat['new_repeat'];
                }
            }
        }

        $database = $this->getContainer()->get('database_connection');

        // Get all upcoming events of the member
        $arrRegistrations = $database->fetchAllAssociative(
            'SELECT * FROM tl_calendar_events_member WHERE eventId != ? AND contaoMemberId = ? AND stateOfSubscription = ? AND hasParticipated = ?',
            [
                $objEvent->id,
                $objMember->id,
                EventSubscriptionState::SUBSCRIPTION_ACCEPTED,
                0,
            ],
            [
                Types::INTEGER,
                Types::INTEGER,
                Types::STRING,
                Types::INTEGER,
            ],
        );

        foreach ($arrRegistrations as $arrRegistration) {
            $objEvent = $this->getAdapter(CalendarEventsModel::class)->findByPk($arrRegistration['eventId']);

            if (null === $objEvent) {
                continue;
            }

            $arrRepeats = StringUtil::deserialize($objEvent->eventDates, true);

            if (empty($arrRepeats) || !\is_array($arrRepeats)) {
                continue;
            }

            foreach ($arrRepeats as $repeat) {
                if (empty($repeat['new_repeat'])) {
                    continue;
                }

                if (\in_array($repeat['new_repeat'], $arrEventDates, false)) {
                    // This date is already occupied (do not allow booking)
                    return true;
                }
            }
        }

        return false;
    }

    public function generateEventPreviewUrl(CalendarEventsModel $objEvent): string
    {
        $this->framework->initialize();

        /** @var UriSigner $uriSigner */
        $uriSigner = $this->getContainer()->get('code4nix_uri_signer.uri_signer');

        /** @var UrlParser $urlParser */
        $urlParser = $this->getContainer()->get(UrlParser::class);

        $eventPreviewUrl = '';

        if ('' === $objEvent->eventType) {
            return $eventPreviewUrl;
        }

        $objEventType = $this->getAdapter(EventTypeModel::class)->findOneBy('alias', $objEvent->eventType);

        if (null === $objEventType || !$objEventType->previewPage) {
            return $eventPreviewUrl;
        }

        $objPage = $this->getAdapter(PageModel::class)->findByPk($objEventType->previewPage);

        if (!$objPage instanceof PageModel) {
            return $eventPreviewUrl;
        }

        $params = sprintf('/%s', !empty($objEvent->alias) ? $objEvent->alias : $objEvent->id);

        $eventPreviewUrl = $urlParser->addQueryString('event_preview=true', $objPage->getAbsoluteUrl($params));
        $eventPreviewUrl = StringUtil::ampersand($eventPreviewUrl);

        return $uriSigner->sign($eventPreviewUrl, 86400);
    }

    public function getTourProfileAsArray(CalendarEventsModel $objEvent): array
    {
        $this->framework->initialize();

        $arrProfile = [];

        if (empty($objEvent->tourProfile) || !\is_array(StringUtil::deserialize($objEvent->tourProfile))) {
            return $arrProfile;
        }

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

        return $arrProfile;
    }

    public function getEventOrganizersLogoAsHtml(CalendarEventsModel $objEvent, string $strInsertTag = '{{image::%s&alt=%s}}', bool $allowDuplicate = false): array
    {
        $this->framework->initialize();

        $arrHtml = [];
        $arrUuids = [];

        $arrOrganizers = StringUtil::deserialize($objEvent->organizers, true);

        foreach ($arrOrganizers as $orgId) {
            $objOrganizer = $this->getAdapter(EventOrganizerModel::class)->findByPk($orgId);

            if (null === $objOrganizer) {
                continue;
            }

            if (!$objOrganizer->addLogo || empty($objOrganizer->singleSRC)) {
                continue;
            }

            if (\in_array($objOrganizer->singleSRC, $arrUuids, false) && !$allowDuplicate) {
                continue;
            }

            $strInsertTag = str_replace('alt=%s', 'alt='.$objOrganizer->title, $strInsertTag);

            $arrUuids[] = $objOrganizer->singleSRC;
            $parser = $this->getContainer()->get('contao.insert_tag.parser');

            $strLogo = $parser->replace(sprintf($strInsertTag, StringUtil::binToUuid($objOrganizer->singleSRC)));

            if ('' !== $strLogo) {
                $arrHtml[] = $strLogo;
            }
        }

        return $arrHtml;
    }

    public function getEventOrganizerLogoPaths(CalendarEventsModel $objEvent, bool $allowDuplicate = false): array
    {
        $this->framework->initialize();

        $arrPaths = [];

        $arrOrganizers = StringUtil::deserialize($objEvent->organizers, true);

        foreach ($arrOrganizers as $orgId) {
            $objOrganizer = $this->getAdapter(EventOrganizerModel::class)->findByPk($orgId);

            if (null === $objOrganizer) {
                continue;
            }

            if (!$objOrganizer->addLogo || empty($objOrganizer->singleSRC)) {
                continue;
            }

            $objFiles = $this->getAdapter(FilesModel::class)->findByUuid($objOrganizer->singleSRC);

            $path = Path::join($this->getProjectDir(), $objFiles->path);

            if (null === $objFiles || !is_file($path)) {
                continue;
            }

            $arrPaths[] = $path;
        }

        return $allowDuplicate ? $arrPaths : array_unique($arrPaths);
    }

    public function getEventQrCode(CalendarEventsModel $objEvent, array $arrOptions = [], bool $blnAbsoluteUrl = true, bool $blnCache = true): string|null
    {
        $this->framework->initialize();

        // Generate QR code folder
        $objFolder = new Folder('system/qrcodes');

        // Symlink
        $webDir = Path::join($this->getProjectDir(), 'public');
        $relWebDir = Path::makeRelative($webDir, $this->getProjectDir()); // public

        // Symlink (target: 'system/qrcodes', link: 'public/system/qrcodes')
        SymlinkUtil::symlink($objFolder->path, Path::join($relWebDir, $objFolder->path), $this->getProjectDir());

        // Generate path
        $filepath = sprintf($objFolder->path.'/'.'eventQRcode_%s.png', $objEvent->id);

        // Defaults
        $opt = [
            'version' => Version::AUTO,
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
        /** @var ContentUrlGenerator $contentUrlGenerator */
        $contentUrlGenerator = $this->getContainer()->get('contao.routing.content_url_generator');

        $url = $contentUrlGenerator->generate($objEvent, [], $blnAbsoluteUrl ? UrlGeneratorInterface::ABSOLUTE_URL : UrlGeneratorInterface::ABSOLUTE_PATH);

        // Generate QR and return the image path
        if ((new QRCode($options))->render($url, $filepath)) {
            return $filepath;
        }

        return null;
    }

    public function getSectionMembershipAsString(MemberModel $objMember): string
    {
        $this->framework->initialize();

        $this->getAdapter(System::class)->loadLanguageFile('tl_member');
        $arrSections = [];
        $sections = StringUtil::deserialize($objMember->sectionId, true);

        foreach ($sections as $id) {
            $arrSections[] = $GLOBALS['TL_LANG']['tl_member']['section'][$id] ?? $id;
        }

        return implode(', ', $arrSections);
    }

    public function getCoordsCH1903AsArray(CalendarEventsModel $objEvent): array
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

    public function getGeoLinkUrl(CalendarEventsModel $objEvent): string|null
    {
        $this->framework->initialize();

        $arrCoord = $this->getCoordsCH1903AsArray($objEvent);

        if (!empty($arrCoord)) {
            $strGeoLink = $this->getContainer()->getParameter('sacevt.event.geo_link');

            return sprintf($strGeoLink, $arrCoord[0], $arrCoord[1]);
        }

        return null;
    }

    public function getSacRoutePortalLink(CalendarEventsModel $objEvent): string|null
    {
        $this->framework->initialize();

        if (empty($objEvent->linkSacRoutePortal)) {
            return null;
        }

        $strPortalLink = html_entity_decode($objEvent->linkSacRoutePortal);

        // Validate link
        if (!filter_var($strPortalLink, FILTER_VALIDATE_URL)) {
            return null;
        }

        // Only links from the SAC route portal are allowed
        if (!str_starts_with($strPortalLink, $this->getContainer()->getParameter('sacevt.event.sac_route_portal_base_link'))) {
            return null;
        }

        // Check if the SAC route portal base link is not entered
        if ($strPortalLink === $this->getContainer()->getParameter('sacevt.event.sac_route_portal_base_link')) {
            return null;
        }

        return $strPortalLink;
    }

    public function getEventReleaseLevelAsString(CalendarEventsModel $objEvent): string|null
    {
        $this->framework->initialize();

        if (empty($objEvent->id) || empty($objEvent->eventReleaseLevel)) {
            return null;
        }

        $strLevel = null;
        $eventReleaseLevelModel = $this->getAdapter(EventReleaseLevelPolicyModel::class)->findByPk($objEvent->eventReleaseLevel);

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

    public function isFavoredEvent(CalendarEventsModel $objEvent): bool
    {
        $this->framework->initialize();

        $user = $this->getContainer()->get('security.helper')->getUser();

        if (!$user instanceof FrontendUser) {
            return false;
        }

        $database = $this->getContainer()->get('database_connection');

        return false !== $database->fetchOne(
            'SELECT id FROM tl_favored_events WHERE eventId = ? AND memberId = ?',
            [
                $objEvent->id,
                $user->id,
            ],
            [
                Types::INTEGER,
                Types::INTEGER,
            ],
        );
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
