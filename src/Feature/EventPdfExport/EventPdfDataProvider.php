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

namespace Markocupic\SacEventToolBundle\Feature\EventPdfExport;

use Contao\Calendar;
use Contao\CalendarEventsModel;
use Contao\Config;
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\CoreBundle\Routing\ContentUrlGenerator;
use Contao\CoreBundle\String\HtmlDecoder;
use Contao\Date;
use Contao\FilesModel;
use Contao\PageModel;
use Contao\StringUtil;
use Contao\System;
use Contao\UserModel;
use Markocupic\SacEventToolBundle\Avatar\Avatar;
use Markocupic\SacEventToolBundle\Config\EventMountainGuide;
use Markocupic\SacEventToolBundle\Config\EventState;
use Markocupic\SacEventToolBundle\Config\EventType;
use Markocupic\SacEventToolBundle\Util\CalendarEventsUtil;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Filesystem\Path;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Collects the data of a tour for the PDF export as plain text.
 *
 * The array is written to data.json and read by the Typst template. All texts are
 * plain strings (no HTML, no Typst markup): the template inserts them as text.
 *
 * See docs/features/event-pdf-export.md
 */
class EventPdfDataProvider
{
    /**
     * Event types that can be exported (they use the tour detail template).
     */
    public const array EVENT_TYPES = [
        EventType::TOUR,
        EventType::LAST_MINUTE_TOUR,
        EventType::GENERAL_EVENT,
    ];

    /**
     * Only for these types: difficulty, "suitable for beginners", tour profile and route
     * (like on the detail page).
     */
    public const array TOUR_TYPES = [
        EventType::TOUR,
        EventType::LAST_MINUTE_TOUR,
    ];

    /**
     * Shown below the instructors instead of their contact data (not logged in).
     */
    public const string CONTACT_HINT = 'Die Kontaktangaben der Leitung siehst du, wenn du auf der Website angemeldet bist.';

    /**
     * Alias of the page with the equipment recommendation (link on the detail page).
     */
    public const string EQUIPMENT_PAGE_ALIAS = 'ausruestungslisten';

    /**
     * Shown instead of the downloads (not logged in), like on the detail page.
     */
    public const string DOWNLOADS_HINT = 'Die Downloads siehst du, wenn du auf der Website angemeldet bist.';

    /**
     * States that are shown as a warning below the title.
     */
    private const array ALERT_STATES = [
        EventState::STATE_CANCELED,
        EventState::STATE_RESCHEDULED,
    ];

    public function __construct(
        private readonly ContaoFramework $framework,
        private readonly CalendarEventsUtil $calendarEventsUtil,
        private readonly ContentUrlGenerator $contentUrlGenerator,
        private readonly HtmlDecoder $htmlDecoder,
        private readonly Avatar $avatar,
        #[Autowire('%kernel.project_dir%')]
        private readonly string $projectDir,
        #[Autowire('%sacevt.avatar.female%')]
        private readonly string $sacevtAvatarFemale,
        #[Autowire('%sacevt.avatar.male%')]
        private readonly string $sacevtAvatarMale,
        #[Autowire('%sacevt.avatar.other%')]
        private readonly string $sacevtAvatarOther,
        #[Autowire('%sacevt.section_name%')]
        private readonly string $sectionName,
    ) {
    }

    public function supports(CalendarEventsModel $event): bool
    {
        return $event->published && \in_array($event->eventType, self::EVENT_TYPES, true);
    }

    /**
     * The contact data of the instructors (phone, mobile, e-mail) is and the downloads are only added
     * with $forMembers (for logged-in members).
     *
     * @return array<string, mixed>
     */
    public function getData(CalendarEventsModel $event, bool $forMembers = false): array
    {
        $this->framework->initialize();

        $util = $this->calendarEventsUtil;
        $isTour = self::isTour($event);
        $tourProfile = $isTour ? StringUtil::deserialize($event->tourProfile, true) : [];

        $kicker = $util->getTourTypesAsArray($event, 'title');

        if (EventType::LAST_MINUTE_TOUR === $event->eventType) {
            array_unshift($kicker, 'Spontantour');
        }

        $kicker[] = $util->getEventDuration($event);

        return [
            'sectionName' => $this->sectionName,
            'eventNumber' => (string) $util->getEventData($event, 'eventId'),
            'title' => $this->plain($event->title),
            'kicker' => array_values(array_filter(array_map($this->plain(...), $kicker))),
            'teaser' => $this->plain($event->teaser),
            'eventState' => $this->getEventState($event),
            'period' => $this->getPeriodLines($event),
            'difficulties' => $isTour ? $this->getDifficulties($event) : [],
            'suitableForBeginners' => $isTour && $event->suitableForBeginners,
            'ascent' => self::sumMeters($tourProfile, 'tourProfileAscentMeters'),
            'descent' => self::sumMeters($tourProfile, 'tourProfileDescentMeters'),
            'ascentTime' => self::sumHours($tourProfile, 'tourProfileAscentTime'),
            'descentTime' => self::sumHours($tourProfile, 'tourProfileDescentTime'),
            'location' => $this->plain($event->location),
            'meetingPoint' => $this->plain($event->meetingPoint),
            'journey' => $this->getJourney($event),
            'organizers' => array_values(array_filter(array_map($this->plain(...), $util->getEventOrganizersAsArray($event)))),
            'instructors' => $this->getInstructors($event, $forMembers),
            'contactHint' => $forMembers ? '' : self::CONTACT_HINT,
            'sections' => $this->getSections($event),
            'links' => $this->getLinks($event),
            'downloads' => $this->getDownloads($event, $forMembers),
            'registration' => $this->getRegistration($event, $forMembers),
            'url' => $this->contentUrlGenerator->generate($event, [], UrlGeneratorInterface::ABSOLUTE_URL),
        ];
    }

    public static function isTour(CalendarEventsModel $event): bool
    {
        return \in_array($event->eventType, self::TOUR_TYPES, true);
    }

    /**
     * Sums up the meters of all days, e.g. 1640 -> "1'640". Returns null if a day has no
     * number (e.g. a free text) or if there is nothing to sum up.
     *
     * @param array<array<string, mixed>> $tourProfile
     */
    public static function sumMeters(array $tourProfile, string $field): string|null
    {
        $sum = 0;
        $hasValue = false;

        foreach ($tourProfile as $day) {
            $value = str_replace(["'", '’', ' ', '.'], '', (string) ($day[$field] ?? ''));

            if ('' === $value) {
                continue;
            }

            if (!ctype_digit($value)) {
                return null;
            }

            $sum += (int) $value;
            $hasValue = true;
        }

        return $hasValue && $sum > 0 ? self::formatNumber($sum) : null;
    }

    /**
     * One row per day for the profile table, e.g.
     * ['day' => 'Tag 1', 'ascent' => "1'150 Hm", 'ascentTime' => '4 h', 'descent' => '', 'descentTime' => ''].
     * Days without any value are left out (like CalendarEventsUtil::getTourProfileAsArray()).
     * "day" is empty for a one-day tour.
     *
     * @param array<array<string, mixed>> $tourProfile
     *
     * @return list<array{day: string, ascent: string, ascentTime: string, descent: string, descentTime: string}>
     */
    public static function formatProfileRows(array $tourProfile): array
    {
        $meters = static function (mixed $value): string {
            $value = trim((string) $value);
            $digits = str_replace(["'", '’', ' '], '', $value);

            if ('' === $value) {
                return '';
            }

            return (ctype_digit($digits) ? self::formatNumber((int) $digits) : $value).' Hm';
        };

        $hours = static fn (mixed $value): string => '' === trim((string) $value) ? '' : trim((string) $value).' h';

        $rows = [];
        $day = 0;

        foreach ($tourProfile as $profile) {
            $row = [
                'day' => '',
                'ascent' => $meters($profile['tourProfileAscentMeters'] ?? ''),
                'ascentTime' => $hours($profile['tourProfileAscentTime'] ?? ''),
                'descent' => $meters($profile['tourProfileDescentMeters'] ?? ''),
                'descentTime' => $hours($profile['tourProfileDescentTime'] ?? ''),
            ];

            if ('' === implode('', $row)) {
                continue;
            }

            $row['day'] = \count($tourProfile) > 1 ? \sprintf('Tag %s', ++$day) : '';
            $rows[] = $row;
        }

        return $rows;
    }

    /**
     * Sums up the times of all days (ascent or descent), e.g. 8.75 -> "8:45", 17 -> "17".
     * Returns null if a time is not a number (e.g. a free text) or if there is nothing to sum up.
     *
     * @param array<array<string, mixed>> $tourProfile
     */
    public static function sumHours(array $tourProfile, string $field): string|null
    {
        $minutes = 0;

        foreach ($tourProfile as $day) {
            $value = str_replace([',', ' ', 'h'], ['.', '', ''], (string) ($day[$field] ?? ''));

            if ('' === $value) {
                continue;
            }

            if (!is_numeric($value)) {
                return null;
            }

            $minutes += (int) round((float) $value * 60);
        }

        if ($minutes <= 0) {
            return null;
        }

        $hours = intdiv($minutes, 60);
        $rest = $minutes % 60;

        return 0 === $rest ? (string) $hours : \sprintf('%d:%02d', $hours, $rest);
    }

    /**
     * Swiss notation with an apostrophe: 1640 -> "1'640".
     */
    public static function formatNumber(int $number): string
    {
        return number_format($number, 0, '.', "'");
    }

    /**
     * "WS" (with title) or "WS – ZS" (range, without title).
     *
     * @param array<array{min: array<string, mixed>, max: array<string, mixed>, isEqual: bool}> $difficulties
     *
     * @return list<array{label: string, title: string}>
     */
    public static function formatDifficulties(array $difficulties): array
    {
        $items = [];

        foreach ($difficulties as $difficulty) {
            $min = $difficulty['min'] ?? [];
            $max = $difficulty['max'] ?? [];

            if (empty($min['shortcut'])) {
                continue;
            }

            if ($difficulty['isEqual'] || empty($max['shortcut'])) {
                $items[] = ['label' => (string) $min['shortcut'], 'title' => (string) ($min['title'] ?? '')];
            } else {
                $items[] = ['label' => $min['shortcut'].' – '.$max['shortcut'], 'title' => ''];
            }
        }

        return $items;
    }

    /**
     * Converts an input encoded value (HTML entities, tags, insert tags) to plain text.
     * Line breaks are kept.
     */
    private function plain(mixed $value): string
    {
        $value = preg_replace('/<br\s*\/?>/i', "\n", (string) $value);

        return trim($this->htmlDecoder->inputEncodedToPlainText($value, true));
    }

    /**
     * Only canceled and rescheduled events show their state (as a warning below the title).
     */
    private function getEventState(CalendarEventsModel $event): string|null
    {
        if (!\in_array($event->eventState, self::ALERT_STATES, true)) {
            return null;
        }

        $label = $this->plain($this->calendarEventsUtil->getEventData($event, 'eventStateLabel'));

        return '' !== $label ? $label : null;
    }

    /**
     * One day: ["Sa, 11.07.2027"]
     * Consecutive days: ["Sa, 11.07. –", "So, 12.07.2027"]
     * Other days: one line per day.
     *
     * @return list<string>
     */
    private function getPeriodLines(CalendarEventsModel $event): array
    {
        $date = $this->framework->getAdapter(Date::class);
        $timestamps = array_map('intval', $this->calendarEventsUtil->getEventTimestamps($event));

        if (empty($timestamps)) {
            return [];
        }

        $start = reset($timestamps);
        $end = end($timestamps);

        if (1 === \count($timestamps)) {
            return [$date->parse('D, d.m.Y', $start)];
        }

        $span = (int) Calendar::calculateSpan($start, $end) + 1;

        if ($span === \count($timestamps)) {
            return [$date->parse('D, d.m.', $start).' –', $date->parse('D, d.m.Y', $end)];
        }

        return array_map(static fn (int $tstamp): string => $date->parse('D, d.m.Y', $tstamp), $timestamps);
    }

    /**
     * @return list<array{label: string, title: string}>
     */
    private function getDifficulties(CalendarEventsModel $event): array
    {
        $items = self::formatDifficulties($this->calendarEventsUtil->getTourTechDifficultiesAsGenericArray($event));

        return array_map(fn (array $item): array => array_map($this->plain(...), $item), $items);
    }

    private function getJourney(CalendarEventsModel $event): string
    {
        $journey = $this->plain($this->calendarEventsUtil->getEventData($event, 'journey'));

        return 'not-specified' === $journey ? '' : $journey;
    }

    /**
     * Like the contact boxes on the detail page: hidden users are left out.
     * Phone, mobile and e-mail only with $forMembers.
     * The portrait is the avatar of the user. Without an own avatar (default avatar) the
     * template shows the initials.
     *
     * @return list<array{name: string, initials: string, qualification: string, contact: list<string>, photo: array{path: string, mtime: int}|null}>
     */
    private function getInstructors(CalendarEventsModel $event, bool $forMembers): array
    {
        $userModel = $this->framework->getAdapter(UserModel::class);
        $instructors = [];

        foreach ($this->calendarEventsUtil->getInstructorsAsArray($event, ['includeHidden' => false]) as $userId) {
            $user = $userModel->findById($userId);

            if (null === $user) {
                continue;
            }

            $contact = [];

            foreach (['phone' => 'Tel. %s', 'mobile' => 'Mobile %s', 'email' => '%s'] as $field => $format) {
                if ($forMembers && '' !== trim((string) $user->{$field})) {
                    $contact[] = \sprintf($format, $this->plain($user->{$field}));
                }
            }

            $instructors[] = [
                'name' => $this->plain(trim($user->lastname.' '.$user->firstname)),
                'initials' => mb_strtoupper(mb_substr($this->plain($user->firstname), 0, 1).mb_substr($this->plain($user->lastname), 0, 1)),
                'qualification' => $this->plain($this->calendarEventsUtil->getMainQualification($user)),
                'contact' => $contact,
                'photo' => $this->getPhoto($user),
            ];
        }

        return $instructors;
    }

    /**
     * The own avatar of the user (not a default avatar). The modification time is part of
     * the data, so a new photo leads to a new PDF.
     *
     * @return array{path: string, mtime: int}|null
     */
    private function getPhoto(UserModel $user): array|null
    {
        $path = $this->avatar->getAvatarResourcePath($user, true);

        $defaults = array_map(
            fn (string $default): string => Path::makeAbsolute($default, $this->projectDir),
            [$this->sacevtAvatarFemale, $this->sacevtAvatarMale, $this->sacevtAvatarOther],
        );

        if (!is_file($path) || \in_array(Path::canonicalize($path), $defaults, true)) {
            return null;
        }

        return ['path' => $path, 'mtime' => (int) filemtime($path)];
    }

    /**
     * The numbered sections in the main column. Same titles as on the detail page.
     *
     * @return list<array{title: string, text: string}>
     */
    private function getSections(CalendarEventsModel $event): array
    {
        $price = [];

        if (EventMountainGuide::WITH_MOUNTAIN_GUIDE_OFFER === (int) $event->mountainguide) {
            $price[] = 'Mit Bergführer/in als Bergführerangebot';
        }

        if (EventMountainGuide::WITH_MOUNTAIN_GUIDE === (int) $event->mountainguide) {
            $price[] = 'Mit Bergführer/in';
        }

        $price[] = $this->plain($event->leistungen);

        $isTour = self::isTour($event);

        // Tours: "Route/Details" and "Profil", general events: "Details" (like on the detail page)
        $sections = [
            'Route/Details' => $isTour ? $this->plain($event->tourDetailText) : '',
            'Details' => EventType::GENERAL_EVENT === $event->eventType ? $this->plain($event->generalEventDetailText) : '',
            'Profil' => $isTour ? implode("\n", array_map($this->plain(...), $this->calendarEventsUtil->getTourProfileAsArray($event))) : '',
            'Voraussetzungen/Anforderungen' => $this->plain($event->requirements),
            'Benötigtes Material' => $this->plain($event->equipment),
            'Leistungen/Preis' => implode("\n", array_filter($price)),
            'Sonstiges' => $this->plain($event->miscellaneous),
        ];

        $items = [];

        foreach ($sections as $title => $text) {
            if ('' === $text) {
                continue;
            }

            $item = ['title' => $title, 'text' => $text];

            // The template shows the profile as a table (one row per day, with arrows)
            if ('Profil' === $title) {
                $item['profile'] = self::formatProfileRows(StringUtil::deserialize($event->tourProfile, true));
            }

            $items[] = $item;
        }

        return $items;
    }

    /**
     * The links of the detail page: map (if the event has coordinates), route on the SAC route
     * portal and the equipment recommendation (published page with the alias "ausruestungslisten").
     *
     * @return list<array{title: string, url: string}>
     */
    private function getLinks(CalendarEventsModel $event): array
    {
        $links = [];

        // The detail page shows the map in an iframe (embed view). The PDF links to the full map.
        $geoLink = $this->calendarEventsUtil->getGeoLinkUrl($event);

        if (null !== $geoLink) {
            $links[] = ['title' => 'Karte auf map.geo.admin.ch', 'url' => str_replace('/embed.html', '/index.html', $geoLink)];
        }

        $routePortal = $this->calendarEventsUtil->getSacRoutePortalLink($event);

        if (null !== $routePortal) {
            $links[] = ['title' => 'Route auf SAC-Tourenportal', 'url' => $routePortal];
        }

        // findPublishedByIdOrAlias() returns a collection, current() is its first page
        $equipmentPage = $this->framework->getAdapter(PageModel::class)->findPublishedByIdOrAlias(self::EQUIPMENT_PAGE_ALIAS)?->current();

        if (null !== $equipmentPage) {
            $links[] = [
                'title' => 'Ausrüstungsempfehlung',
                'url' => $this->contentUrlGenerator->generate($equipmentPage, [], UrlGeneratorInterface::ABSOLUTE_URL),
            ];
        }

        return $links;
    }

    /**
     * The enclosures of the event (only for members, like on the detail page). The link is the
     * download link of the detail page: <event url>?file=<path>.
     *
     * @return array{items: list<array{title: string, info: string, url: string}>, hint: string}|null
     */
    private function getDownloads(CalendarEventsModel $event, bool $forMembers): array|null
    {
        if (!$event->addEnclosure) {
            return null;
        }

        $uuids = StringUtil::deserialize($event->enclosure, true);
        $files = empty($uuids) ? null : $this->framework->getAdapter(FilesModel::class)->findMultipleByUuids($uuids);

        if (null === $files) {
            return null;
        }

        $allowedDownload = StringUtil::trimsplit(',', strtolower((string) $this->framework->getAdapter(Config::class)->get('allowedDownload')));
        $eventUrl = $this->contentUrlGenerator->generate($event, [], UrlGeneratorInterface::ABSOLUTE_URL);
        $items = [];

        foreach ($files as $file) {
            $path = Path::join($this->projectDir, $file->path);

            if ('file' !== $file->type || !is_file($path) || !\in_array(strtolower((string) $file->extension), $allowedDownload, true)) {
                continue;
            }

            $title = $this->plain($file->getMetadata('de')?->getTitle() ?? '');

            $items[] = [
                'title' => '' !== $title ? $title : $file->name,
                'info' => \sprintf('%s, %s', strtoupper((string) $file->extension), System::getReadableSize((int) filesize($path))),
                'url' => $eventUrl.(str_contains($eventUrl, '?') ? '&' : '?').'file='.System::urlEncode($file->path),
            ];
        }

        if (empty($items)) {
            return null;
        }

        return $forMembers ? ['items' => $items, 'hint' => ''] : ['items' => [], 'hint' => self::DOWNLOADS_HINT];
    }

    /**
     * @return array{title: string, lines: list<string>}
     */
    private function getRegistration(CalendarEventsModel $event, bool $forMembers): array
    {
        $util = $this->calendarEventsUtil;
        $lines = [];

        if ($event->disableOnlineRegistration) {
            $lines[] = 'Keine Online-Anmeldung.';
        } elseif ($event->setRegistrationPeriod) {
            $lines[] = \sprintf(
                'Online-Anmeldung vom %s bis zum %s.',
                $util->getEventData($event, 'registrationStartTimeWithOffsetFormatted'),
                $util->getEventData($event, 'registrationEndDateFormatted'),
            );
        } else {
            $lines[] = 'Online-Anmeldung';
        }

        $lines[] = $this->plain($event->bookingDetails);

        if ($forMembers && $event->generateMainInstructorContactDataFromDb) {
            $lines[] = $this->plain(html_entity_decode($util->generateMainInstructorContactDataFromDb($event, ['includeHidden' => false]), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        }

        if (!$event->disableOnlineRegistration) {
            $lines[] = $event->allowDeregistration
                ? \sprintf('Online-Abmeldung mit einer Frist von %s Tagen möglich.', (int) $event->deregistrationLimit)
                : 'Keine Online-Abmeldung.';
            $lines[] = 'Es können ggf. Stornierungsgebühren anfallen (siehe Leistungen/Preis).';
        }

        return [
            'title' => 'Anmeldung und Abmeldung',
            'lines' => array_values(array_filter($lines)),
        ];
    }
}
