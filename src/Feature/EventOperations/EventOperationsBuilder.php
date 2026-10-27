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

namespace Markocupic\SacEventToolBundle\Feature\EventOperations;

use Codefog\HasteBundle\UrlParser;
use Contao\CalendarEventsModel;
use Contao\CoreBundle\Csrf\ContaoCsrfTokenManager;
use Contao\CoreBundle\Security\ContaoCorePermissions;
use Contao\CoreBundle\Security\DataContainer\ReadAction;
use Doctrine\DBAL\Connection;
use Markocupic\SacEventToolBundle\Config\EventType;
use Markocupic\SacEventToolBundle\Controller\BackendModule\EventParticipantEmailController;
use Markocupic\SacEventToolBundle\Security\Voter\CalendarEventsInstructorInvoiceVoter;
use Markocupic\SacEventToolBundle\Security\Voter\CalendarEventsVoter;
use Markocupic\SacEventToolBundle\Util\CalendarEventsUtil;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\UriSigner;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Builds the operations of an event for the logged-in back end user. Used by the
 * "my events" dashboard on the back end start page and by the button navbar in
 * the event form, so both show the same operations with the same permissions.
 *
 * Every operation is checked against the permissions of the user:
 * - enabled: the user may execute the operation
 * - denied: the operation exists, but the user is not allowed to execute it (greyed out)
 * - not available: the operation does not exist for this event (e.g. tour report of a course)
 */
class EventOperationsBuilder
{
    public const string OPERATION_EDIT_EVENT = 'edit-event';

    public const string OPERATION_EVENT_LISTING = 'event-listing';

    public const string OPERATION_EVENT_PREVIEW = 'event-preview';

    public const string OPERATION_REGISTRATION_LIST = 'event-registration-list';

    public const string OPERATION_SEND_EMAIL = 'send-email';

    public const string OPERATION_TOUR_REPORT = 'create-or-edit-report';

    public const string OPERATION_PRINT_REPORT = 'print-report';

    /**
     * The operations displayed as icons (and in the dropdown on small screens) in this
     * order, see the template Backend/EventOperations/operations_menu.html.twig. The
     * operation "edit-event" is usually the link of the event title.
     */
    public const array MENU_OPERATIONS = [
        self::OPERATION_EVENT_PREVIEW,
        self::OPERATION_EVENT_LISTING,
        self::OPERATION_REGISTRATION_LIST,
        self::OPERATION_SEND_EMAIL,
        self::OPERATION_TOUR_REPORT,
        self::OPERATION_PRINT_REPORT,
    ];

    private const array TOUR_EVENT_TYPES = [EventType::TOUR, EventType::LAST_MINUTE_TOUR];

    public function __construct(
        private readonly AuthorizationCheckerInterface $authorizationChecker,
        private readonly CalendarEventsUtil $calendarEventsUtil,
        private readonly Connection $connection,
        private readonly ContaoCsrfTokenManager $contaoCsrfTokenManager,
        private readonly RequestStack $requestStack,
        private readonly RouterInterface $router,
        private readonly TranslatorInterface $translator,
        private readonly UriSigner $uriSigner,
        private readonly UrlParser $urlParser,
    ) {
    }

    /**
     * Returns all operations of the event in a fixed order, keyed by their name.
     *
     * @param string $do The back end module the links point to
     *
     * @return array<string, EventOperation>
     */
    public function build(CalendarEventsModel $event, string $do = 'calendar'): array
    {
        // Read access to the event: the same check as in the event list of the calendar
        // (access to the calendar module and to the calendar of the event, tl_user.calendars)
        $canRead = $this->authorizationChecker->isGranted(
            ContaoCorePermissions::DC_PREFIX.'tl_calendar_events',
            new ReadAction('tl_calendar_events', $event->row()),
        );

        $operations = [
            $this->buildEditEvent($event, $do, $canRead),
            $this->buildEventListing($event, $do, $canRead),
            $this->buildEventPreview($event, $canRead),
            $this->buildRegistrationList($event, $do, $canRead),
            $this->buildSendEmail($event, $canRead),
            $this->buildTourReport($event, $do, $canRead),
            $this->buildPrintReport($event, $do, $canRead),
        ];

        $result = [];

        foreach ($operations as $operation) {
            $result[$operation->name] = $operation;
        }

        return $result;
    }

    private function buildEditEvent(CalendarEventsModel $event, string $do, bool $canRead): EventOperation
    {
        // Users without write access see the field values only (see AccessDecision\CalendarEvents::restrictEdit())
        return $this->createOperation(
            name: self::OPERATION_EDIT_EVENT,
            state: $canRead ? EventOperationState::Enabled : EventOperationState::Denied,
            label: 'bhs_dashb_opLabel_editEvent',
            title: 'bhs_dashb_editEvent',
            iconClass: 'fa-solid fa-fw fa-pen-to-square',
            href: fn (): string => $this->backendUrl(['do' => $do, 'table' => 'tl_calendar_events', 'act' => 'edit', 'id' => $event->id]),
            primary: false,
            accessKey: null,
        );
    }

    private function buildEventListing(CalendarEventsModel $event, string $do, bool $canRead): EventOperation
    {
        return $this->createOperation(
            name: self::OPERATION_EVENT_LISTING,
            state: $canRead ? EventOperationState::Enabled : EventOperationState::Denied,
            label: 'bhs_dashb_opLabel_eventListing',
            title: 'bhs_dashb_eventListing',
            iconClass: 'fa-solid fa-fw fa-list-tree',
            href: fn (): string => $this->backendUrl(['do' => $do, 'table' => 'tl_calendar_events', 'id' => $event->pid]),
        );
    }

    private function buildEventPreview(CalendarEventsModel $event, bool $canRead): EventOperation
    {
        $previewUrl = $canRead ? $this->calendarEventsUtil->generateEventPreviewUrl($event) : '';

        $state = match (true) {
            !$canRead => EventOperationState::Denied,
            '' === $previewUrl => EventOperationState::NotAvailable,
            default => EventOperationState::Enabled,
        };

        return $this->createOperation(
            name: self::OPERATION_EVENT_PREVIEW,
            state: $state,
            label: 'bhs_dashb_opLabel_livePreview',
            title: 'bhs_dashb_livePreview',
            iconClass: 'fa-solid fa-fw fa-presentation-screen',
            href: static fn (): string => $previewUrl,
            notAvailableTitle: 'bhs_dashb_livePreviewDisabled',
            accessKey: 'p',
            linkAttributes: ['data-turbo' => 'false', 'rel' => 'noopener', 'target' => '_blank'],
        );
    }

    /**
     * Everyone with read access to the event may read the registrations. Only users who
     * administer the registrations may edit them (see AccessDecision\CalendarEventsMember).
     */
    private function buildRegistrationList(CalendarEventsModel $event, string $do, bool $canRead): EventOperation
    {
        return $this->createOperation(
            name: self::OPERATION_REGISTRATION_LIST,
            state: $canRead ? EventOperationState::Enabled : EventOperationState::Denied,
            label: 'bhs_dashb_opLabel_registrationList',
            title: 'bhs_dashb_registrationList',
            iconClass: 'fa-solid fa-fw fa-people-group',
            href: fn (): string => $this->backendUrl(['do' => $do, 'table' => 'tl_calendar_events_member', 'id' => $event->id]),
            accessKey: 'm',
        );
    }

    /**
     * Same permission as in EventParticipantEmailController.
     */
    private function buildSendEmail(CalendarEventsModel $event, bool $canRead): EventOperation
    {
        $hasRegistrations = false !== $this->connection->fetchOne('SELECT id FROM tl_calendar_events_member WHERE pid = ? LIMIT 1', [(int) $event->id]);

        $state = match (true) {
            !$hasRegistrations => EventOperationState::NotAvailable,
            !$canRead, !$this->authorizationChecker->isGranted(CalendarEventsVoter::CAN_ADMINISTER_EVENT_REGISTRATIONS, (int) $event->id) => EventOperationState::Denied,
            default => EventOperationState::Enabled,
        };

        return $this->createOperation(
            name: self::OPERATION_SEND_EMAIL,
            state: $state,
            label: 'bhs_dashb_opLabel_sendEmail',
            title: 'bhs_dashb_sendEmail',
            iconClass: 'fa-solid fa-fw fa-at',
            href: fn (): string => $this->sendEmailUrl($event),
            notAvailableTitle: 'bhs_dashb_sendEmailDisabled',
            primary: true,
        );
    }

    /**
     * Same permission as the global operation "writeTourReport" of tl_calendar_events_member.
     */
    private function buildTourReport(CalendarEventsModel $event, string $do, bool $canRead): EventOperation
    {
        $state = match (true) {
            !$this->isTour($event) => EventOperationState::NotAvailable,
            !$canRead, !$this->authorizationChecker->isGranted(CalendarEventsVoter::CAN_WRITE_EVENT, (int) $event->id) => EventOperationState::Denied,
            default => EventOperationState::Enabled,
        };

        $hasReport = (bool) $event->filledInEventReportForm;

        return $this->createOperation(
            name: self::OPERATION_TOUR_REPORT,
            state: $state,
            label: $hasReport ? 'bhs_dashb_opLabel_editReport' : 'bhs_dashb_opLabel_createReport',
            title: $hasReport ? 'bhs_dashb_editReport' : 'bhs_dashb_createReport',
            iconClass: 'fa-solid fa-fw fa-comment-pen',
            href: fn (): string => $this->backendUrl(['do' => $do, 'table' => 'tl_calendar_events', 'act' => 'edit', 'call' => 'writeTourReport', 'id' => $event->id]),
            notAvailableTitle: 'bhs_dashb_createReportDisabled',
            primary: true,
            iconModifier: $hasReport ? 'green' : 'red',
            accessKey: 'r',
        );
    }

    /**
     * Same permission as the global operation "printInstructorInvoice" of tl_calendar_events_member.
     */
    private function buildPrintReport(CalendarEventsModel $event, string $do, bool $canRead): EventOperation
    {
        $state = match (true) {
            !$this->isTour($event) => EventOperationState::NotAvailable,
            !$canRead, !$this->authorizationChecker->isGranted(CalendarEventsInstructorInvoiceVoter::HAS_ACCESS, $event) => EventOperationState::Denied,
            default => EventOperationState::Enabled,
        };

        return $this->createOperation(
            name: self::OPERATION_PRINT_REPORT,
            state: $state,
            label: 'bhs_dashb_opLabel_printReport',
            title: 'bhs_dashb_printReport',
            iconClass: 'fa-solid fa-fw fa-print',
            href: fn (): string => $this->backendUrl(['do' => $do, 'table' => 'tl_calendar_events_instructor_invoice', 'id' => $event->id]),
            notAvailableTitle: 'bhs_dashb_printReportDisabled',
            accessKey: 'i',
        );
    }

    /**
     * @param \Closure(): string    $href           Only called if the operation is enabled
     * @param array<string, string> $linkAttributes
     */
    private function createOperation(
        string $name,
        EventOperationState $state,
        string $label,
        string $title,
        string $iconClass,
        \Closure $href,
        string|null $notAvailableTitle = null,
        bool $primary = false,
        string|null $iconModifier = null,
        string|null $accessKey = null,
        array $linkAttributes = ['data-turbo' => 'false'],
    ): EventOperation {
        $label = $this->trans($label);

        $title = match ($state) {
            EventOperationState::Enabled => $this->trans($title),
            EventOperationState::Denied => $this->trans('bhs_dashb_opDenied', [$label]),
            EventOperationState::NotAvailable => $this->trans($notAvailableTitle ?? 'bhs_dashb_opNotAvailable', [$label]),
        };

        return new EventOperation(
            name: $name,
            state: $state,
            label: $label,
            title: $title,
            iconClass: $iconClass,
            href: EventOperationState::Enabled === $state ? $href() : null,
            primary: $primary,
            iconModifier: $iconModifier,
            accessKey: $accessKey,
            linkAttributes: $linkAttributes,
        );
    }

    private function isTour(CalendarEventsModel $event): bool
    {
        return \in_array($event->eventType, self::TOUR_EVENT_TYPES, true);
    }

    /**
     * @param array<string, int|string|null> $params
     */
    private function backendUrl(array $params): string
    {
        $params['rt'] = $this->contaoCsrfTokenManager->getDefaultTokenValue();

        $refId = $this->requestStack->getCurrentRequest()?->attributes->get('_contao_referer_id');

        if (null !== $refId) {
            $params['ref'] = $refId;
        }

        return $this->router->generate('contao_backend', $params);
    }

    private function sendEmailUrl(CalendarEventsModel $event): string
    {
        $url = $this->router->generate(EventParticipantEmailController::class);

        $url = $this->urlParser->addQueryString('eventId='.$event->id, $url);
        $url = $this->urlParser->addQueryString('rt='.$this->contaoCsrfTokenManager->getDefaultTokenValue(), $url);
        $url = $this->urlParser->addQueryString('sid='.uniqid(), $url);

        $request = $this->requestStack->getCurrentRequest();

        if (null !== $request) {
            $url = $this->urlParser->addQueryString('back='.rawurlencode($request->getRequestUri()), $url);
        }

        return $this->uriSigner->sign($url);
    }

    /**
     * @param list<string> $params
     */
    private function trans(string $key, array $params = []): string
    {
        return $this->translator->trans('MSC.'.$key, $params, 'contao_default');
    }
}
