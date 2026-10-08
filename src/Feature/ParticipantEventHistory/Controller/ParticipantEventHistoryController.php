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

namespace Markocupic\SacEventToolBundle\Feature\ParticipantEventHistory\Controller;

use Contao\BackendUser;
use Contao\CalendarEventsModel;
use Contao\Config;
use Contao\CoreBundle\Controller\AbstractBackendController;
use Contao\CoreBundle\Exception\AccessDeniedException;
use Contao\CoreBundle\Framework\Adapter;
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\CoreBundle\Monolog\ContaoContext;
use Contao\CoreBundle\Routing\ContentUrlGenerator;
use Contao\Date;
use Contao\PageModel;
use Markocupic\SacEventToolBundle\Config\CourseLevels;
use Markocupic\SacEventToolBundle\Config\EventMountainGuide;
use Markocupic\SacEventToolBundle\Config\EventType;
use Markocupic\SacEventToolBundle\Config\Log;
use Markocupic\SacEventToolBundle\Feature\ParticipantEventHistory\ParticipantEventHistoryQuery;
use Markocupic\SacEventToolBundle\Feature\ParticipantEventHistory\Security\ParticipantEventHistoryVoter;
use Markocupic\SacEventToolBundle\Model\CalendarEventsMemberModel;
use Markocupic\SacEventToolBundle\Util\CalendarEventsUtil;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Exception\ExceptionInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Backend page with the events the participant of a registration has participated in
 * during the last ParticipantEventHistoryQuery::HISTORY_YEARS years.
 *
 * Linked from the operation in the registration list (see ParticipantEventHistoryOperationListener).
 * The access is checked on every request by ParticipantEventHistoryVoter. Every access and
 * every denied access is logged in the Contao system log.
 */
#[Route('/%contao.backend.route_prefix%/participant_event_history/{registrationId}', name: self::class, requirements: ['registrationId' => '\d+'], defaults: ['_scope' => 'backend'])]
class ParticipantEventHistoryController extends AbstractBackendController
{
    private Adapter $calendarEventsMemberModel;

    private Adapter $calendarEventsModel;

    private Adapter $config;

    private Adapter $date;

    private Adapter $pageModel;

    public function __construct(
        private readonly CalendarEventsUtil $calendarEventsUtil,
        private readonly ContaoFramework $framework,
        private readonly ContentUrlGenerator $contentUrlGenerator,
        private readonly CourseLevels $courseLevels,
        private readonly ParticipantEventHistoryQuery $query,
        private readonly RouterInterface $router,
        private readonly Security $security,
        private readonly TranslatorInterface $translator,
        private readonly LoggerInterface|null $contaoGeneralLogger = null,
    ) {
        $this->calendarEventsMemberModel = $this->framework->getAdapter(CalendarEventsMemberModel::class);
        $this->calendarEventsModel = $this->framework->getAdapter(CalendarEventsModel::class);
        $this->config = $this->framework->getAdapter(Config::class);
        $this->date = $this->framework->getAdapter(Date::class);
        $this->pageModel = $this->framework->getAdapter(PageModel::class);
    }

    public function __invoke(Request $request, int $registrationId): Response
    {
        $this->framework->initialize();

        if (!$this->isSameOriginRequest($request)) {
            $this->denyAccess($registrationId, 'request from another site');
        }

        if (!$this->security->isGranted(ParticipantEventHistoryVoter::CAN_VIEW_PARTICIPANT_EVENT_HISTORY_OF_REGISTRATION, $registrationId)) {
            $this->denyAccess($registrationId, 'missing permission or access period expired');
        }

        $registration = $this->calendarEventsMemberModel->findById($registrationId);

        if (null === $registration) {
            $this->denyAccess($registrationId, 'registration not found');
        }

        $sacMemberId = (int) $registration->sacMemberId;
        $rows = [];

        foreach ($this->query->findEventIds($sacMemberId, new \DateTimeImmutable()) as $eventId) {
            $event = $this->calendarEventsModel->findById($eventId);

            if (null === $event) {
                continue;
            }

            $rows[] = $this->getRow($event);
        }

        $name = trim($registration->firstname.' '.$registration->lastname);

        $this->logAccess(Log::PARTICIPANT_EVENT_HISTORY_ACCESS, \sprintf(
            'User "%s" (ID %d) opened the event history of "%s" (SAC member %d) (registration ID %d, event ID %d).',
            $this->getUsername(),
            $this->getUserId(),
            $name,
            $sacMemberId,
            $registrationId,
            (int) $registration->eventId,
        ));

        $years = ParticipantEventHistoryQuery::HISTORY_YEARS;

        return $this->render('@MarkocupicSacEventTool/ParticipantEventHistory/be_participant_event_history.html.twig', [
            'headline' => $this->translator->trans('MSC.participantEventHistoryHeadline', [$years, $name], 'contao_default'),
            'list_title' => $this->translator->trans('MSC.participantEventHistoryTitle', [$years, $name], 'contao_default'),
            'years' => $years,
            'sac_member_id' => $sacMemberId,
            'rows' => $rows,
            'back_url' => $this->router->generate('contao_backend', [
                'do' => 'calendar',
                'table' => 'tl_calendar_events_member',
                'id' => (int) $registration->eventId,
            ]),
        ]);
    }

    /**
     * @return array{date: string, title: string, url: string|null, event_type: string, main_instructor: string, main_instructor_url: string|null, mountain_guide: string, difficulty: string}
     */
    private function getRow(CalendarEventsModel $event): array
    {
        [$mainInstructor, $mainInstructorUrl] = $this->getMainInstructor($event);

        return [
            'date' => $this->getDate($event),
            // Contao stores the title input-encoded (e.g. "&amp;"); Twig escapes it itself
            'title' => html_entity_decode((string) $event->title, ENT_QUOTES | ENT_HTML5, 'UTF-8'),
            'url' => $this->getEventUrl($event),
            'event_type' => '' !== (string) $event->eventType ? $this->translator->trans('MSC.'.$event->eventType.'_short', [], 'contao_default') : '',
            'main_instructor' => $mainInstructor,
            'main_instructor_url' => $mainInstructorUrl,
            'mountain_guide' => $this->getMountainGuide($event),
            'difficulty' => $this->getDifficulty($event),
        ];
    }

    /**
     * "d.m.Y", or "d.m.Y – d.m.Y" if the event lasts several days.
     */
    private function getDate(CalendarEventsModel $event): string
    {
        $dateFormat = (string) $this->config->get('dateFormat');
        $start = $this->date->parse($dateFormat, (int) $event->startDate);
        $end = $this->date->parse($dateFormat, (int) $event->endDate);

        if ((int) $event->endDate <= (int) $event->startDate || $start === $end) {
            return $start;
        }

        return $start.' – '.$end;
    }

    /**
     * Detail page of the event in the front end, null if there is none.
     */
    private function getEventUrl(CalendarEventsModel $event): string|null
    {
        try {
            return $this->contentUrlGenerator->generate($event, [], UrlGeneratorInterface::ABSOLUTE_URL);
        } catch (ExceptionInterface) {
            return null;
        }
    }

    /**
     * Name of the main instructor and the URL of his profile page in the front end
     * (tl_calendar.userPortraitJumpTo). No URL if the user is hidden or disabled or if the
     * calendar has no profile page.
     *
     * @return array{0: string, 1: string|null}
     */
    private function getMainInstructor(CalendarEventsModel $event): array
    {
        $user = $this->calendarEventsUtil->getMainInstructor($event);

        if (null === $user) {
            return ['', null];
        }

        $name = trim($user->firstname.' '.$user->lastname);

        if ($user->hideUser || $user->disable) {
            return [$name, null];
        }

        $calendar = $event->getRelated('pid');
        $profilePage = null !== $calendar && $calendar->userPortraitJumpTo ? $this->pageModel->findById((int) $calendar->userPortraitJumpTo) : null;

        if (null === $profilePage) {
            return [$name, null];
        }

        try {
            $url = $this->contentUrlGenerator->generate($profilePage, [], UrlGeneratorInterface::ABSOLUTE_URL);
        } catch (ExceptionInterface) {
            return [$name, null];
        }

        return [$name, $url.'?getUpcoming=1&username='.rawurlencode((string) $user->username)];
    }

    /**
     * Mountain guide (tours, last minute tours and courses only).
     */
    private function getMountainGuide(CalendarEventsModel $event): string
    {
        if (EventType::GENERAL_EVENT === $event->eventType) {
            return '';
        }

        $key = match ((int) $event->mountainguide) {
            EventMountainGuide::WITH_MOUNTAIN_GUIDE => 'participantEventHistoryWithMountainGuide',
            EventMountainGuide::WITH_MOUNTAIN_GUIDE_OFFER => 'participantEventHistoryWithMountainGuideOffer',
            default => 'participantEventHistoryWithoutMountainGuide',
        };

        return $this->translator->trans('MSC.'.$key, [], 'contao_default');
    }

    /**
     * Technical difficulty of tours (e.g. "WS - ZS, T3"), course level of courses.
     */
    private function getDifficulty(CalendarEventsModel $event): string
    {
        if (EventType::TOUR === $event->eventType || EventType::LAST_MINUTE_TOUR === $event->eventType) {
            return implode(', ', $this->calendarEventsUtil->getTourTechDifficultiesAsArray($event));
        }

        if (EventType::COURSE === $event->eventType && $event->courseLevel && $this->courseLevels->has((int) $event->courseLevel)) {
            return $this->courseLevels->get((int) $event->courseLevel);
        }

        return '';
    }

    /**
     * Prevents other sites from opening the page in the background with the session of a
     * logged-in user (e.g. with an embedded image), which would create misleading log entries.
     * Sec-Fetch-Site is sent by all current browsers: "same-origin" for links in the backend,
     * "none" for URLs typed in or opened from a bookmark. Requests without the header are allowed.
     */
    private function isSameOriginRequest(Request $request): bool
    {
        $site = $request->headers->get('Sec-Fetch-Site');

        return null === $site || \in_array($site, ['same-origin', 'none'], true);
    }

    private function denyAccess(int $registrationId, string $reason): never
    {
        $this->logAccess(Log::PARTICIPANT_EVENT_HISTORY_ACCESS_DENIED, \sprintf(
            'Access to the event history of registration ID %d denied for user "%s" (ID %d): %s.',
            $registrationId,
            $this->getUsername(),
            $this->getUserId(),
            $reason,
        ));

        throw new AccessDeniedException(\sprintf('Access to the event history of registration ID %d denied: %s.', $registrationId, $reason));
    }

    private function logAccess(string $action, string $message): void
    {
        $this->contaoGeneralLogger?->info($message, ['contao' => new ContaoContext(__METHOD__, $action)]);
    }

    private function getUsername(): string
    {
        $user = $this->security->getUser();

        return $user instanceof BackendUser ? (string) $user->username : '';
    }

    private function getUserId(): int
    {
        $user = $this->security->getUser();

        return $user instanceof BackendUser ? (int) $user->id : 0;
    }
}
