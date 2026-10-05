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

namespace Markocupic\SacEventToolBundle\DataContainer\AccessDecision;

use Contao\CalendarEventsModel;
use Contao\CoreBundle\DataContainer\DataContainerOperation;
use Contao\CoreBundle\DataContainer\PaletteManipulator;
use Contao\CoreBundle\DependencyInjection\Attribute\AsCallback;
use Contao\CoreBundle\Exception\AccessDeniedException;
use Contao\CoreBundle\Framework\Adapter;
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\DataContainer;
use Markocupic\SacEventToolBundle\Config\BookingType;
use Markocupic\SacEventToolBundle\Config\EventSubscriptionState;
use Markocupic\SacEventToolBundle\Config\EventType;
use Markocupic\SacEventToolBundle\Model\CalendarEventsMemberModel;
use Markocupic\SacEventToolBundle\Security\Voter\CalendarEventsInstructorInvoiceVoter;
use Markocupic\SacEventToolBundle\Security\Voter\CalendarEventsVoter;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Access checks for the event registrations (tl_calendar_events_member) in the backend:
 * - checkPermission(): non-admins may only work on the registrations of events they administer
 * - makeFieldsReadonly(): the personal data of online registrations cannot be changed
 * - setGlobalOperations(): only shows the global operations the user is allowed to use
 * - editButton(), deleteButton(): disable the operations the user is not allowed to use
 */
class CalendarEventsMember
{
    public const string TABLE = 'tl_calendar_events_member';

    /**
     * Fields of online registrations that cannot be changed by non-admins.
     */
    private const array READONLY_FIELDS_OF_ONLINE_REGISTRATIONS = [
        'sacMemberId',
        'gender',
        'firstname',
        'lastname',
        'street',
        'postal',
        'city',
        'phone',
        'mobile',
        'dateOfBirth',
        'email',
        'ahvNumber',
        'emergencyPhone',
        'emergencyPhoneName',
        'notes',
        'ticketInfo',
        'foodHabits',
        'dateAdded',
        'agb',
        'hasAcceptedPrivacyRules',
        'hasLeadClimbingEducation',
        'dateOfLeadClimbingEducation',
        'sectionId',
    ];

    /**
     * Event types with a tour report and an instructor invoice.
     */
    private const array TOUR_EVENT_TYPES = [EventType::TOUR, EventType::LAST_MINUTE_TOUR];

    // Adapters
    private Adapter $calendarEvents;

    private Adapter $calendarEventsMemberModel;

    public function __construct(
        private readonly ContaoFramework $framework,
        private readonly RequestStack $requestStack,
        private readonly Security $security,
    ) {
        // Adapters
        $this->calendarEvents = $this->framework->getAdapter(CalendarEventsModel::class);
        $this->calendarEventsMemberModel = $this->framework->getAdapter(CalendarEventsMemberModel::class);
    }

    /**
     * Non-admins get no permissions by default. The users who administer the
     * registrations of the event get the permissions they need.
     *
     * @throws \Exception
     */
    #[AsCallback(table: 'tl_calendar_events_member', target: 'config.onload', priority: 100)]
    public function checkPermission(DataContainer $dc): void
    {
        $request = $this->requestStack->getCurrentRequest();

        if ($this->security->isGranted('ROLE_ADMIN')) {
            return;
        }

        $this->setPermissions(closed: true, notCreatable: true, notEditable: true, notDeletable: true);

        // List view: $dc->id is the event id
        if (!$request->query->has('act') && $request->query->has('id')) {
            if ($this->canAdministerRegistrations($dc->id)) {
                $this->setPermissions(closed: false, notCreatable: false, notEditable: false, notDeletable: false);
            } else {
                unset($GLOBALS['TL_DCA'][self::TABLE]['list']['operations']['toggleParticipationState']);
            }
        }

        if (!$request->query->has('act')) {
            return;
        }

        // Prevent deep link hacking attempts (the user types the url manually to perform
        // a certain action). Allowed are: show, create, edit, toggle and delete. Not
        // allowed are: select, editAll, deleteAll, copyAll and overrideAll.
        $act = $request->query->get('act');

        $isAllowed = match ($act) {
            'show' => true,
            'create' => $this->allowCreate($dc),
            'edit' => $this->allowEdit($dc),
            'toggle' => $this->allowToggleParticipation($dc),
            'delete' => $this->allowDelete($dc),
            default => false,
        };

        if (!$isAllowed) {
            throw new AccessDeniedException(\sprintf('Not enough permissions to perform the "%s" action on the current event.', $act));
        }
    }

    /**
     * Make input fields readonly, if the registration is of type 'onlineForm'.
     */
    #[AsCallback(table: 'tl_calendar_events_member', target: 'config.onload', priority: 110)]
    public function makeFieldsReadonly(DataContainer $dc): void
    {
        if ($this->security->isGranted('ROLE_ADMIN')) {
            return;
        }

        // Only the edit form shows input fields ($dc->id is the registration id)
        if ('edit' !== $this->requestStack->getCurrentRequest()->query->get('act') || !$dc->id) {
            return;
        }

        $registration = $this->calendarEventsMemberModel->findById($dc->id);

        if (null === $registration || BookingType::ONLINE_FORM !== $registration->bookingType) {
            return;
        }

        foreach (self::READONLY_FIELDS_OF_ONLINE_REGISTRATIONS as $fieldName) {
            $GLOBALS['TL_DCA'][self::TABLE]['fields'][$fieldName]['eval']['readonly'] = true;

            // A checkbox cannot be readonly, so let's transform it into a text input field.
            if ('checkbox' === ($GLOBALS['TL_DCA'][self::TABLE]['fields'][$fieldName]['inputType'] ?? '')) {
                $GLOBALS['TL_DCA'][self::TABLE]['fields'][$fieldName]['inputType'] = 'text';
                $GLOBALS['TL_DCA'][self::TABLE]['fields'][$fieldName]['eval']['tl_class'] = 'w50';
            }
        }

        // The checkbox "hasLeadClimbingEducation" is now a text field and cannot open its
        // subpalette anymore. So move the field of the subpalette right after it.
        PaletteManipulator::create()
            ->removeField('dateOfLeadClimbingEducation')
            ->applyToSubpalette('hasLeadClimbingEducation', self::TABLE)
        ;

        PaletteManipulator::create()
            ->addField('dateOfLeadClimbingEducation', 'hasLeadClimbingEducation', PaletteManipulator::POSITION_AFTER)
            ->applyToPalette('default', self::TABLE)
        ;
    }

    /**
     * Generate the hrefs of the global operations "writeTourReport" and
     * "printInstructorInvoice" and remove the global operations the user is not
     * allowed to use.
     */
    #[AsCallback(table: 'tl_calendar_events_member', target: 'config.onload', priority: 120)]
    public function setGlobalOperations(DataContainer $dc): void
    {
        $request = $this->requestStack->getCurrentRequest();

        if ($request->query->has('act')) {
            return;
        }

        $globalOperations = &$GLOBALS['TL_DCA'][self::TABLE]['list']['global_operations'];

        // Generally do not allow selectAll to non-admins.
        if (!$this->security->isGranted('ROLE_ADMIN')) {
            unset($globalOperations['all']);
        }

        if (!$this->canAdministerRegistrations($dc->id)) {
            unset(
                $globalOperations['sendEmail'],
                $globalOperations['downloadEventRegistrationListCsv'],
                $globalOperations['downloadEventRegistrationListDocx'],
            );
        }

        $allowTourReport = false;
        $allowInstructorInvoice = false;

        $eventId = $request->query->get('id', 0);

        $event = $this->calendarEvents->findById($eventId);

        if (null !== $event) {
            $isTour = \in_array($event->eventType, self::TOUR_EVENT_TYPES, true);

            if ($this->security->isGranted(CalendarEventsVoter::CAN_WRITE_EVENT, $event->id) && $isTour) {
                $globalOperations['writeTourReport']['href'] = \sprintf($globalOperations['writeTourReport']['href'], $eventId);
                $allowTourReport = true;
            }

            if ($this->security->isGranted(CalendarEventsInstructorInvoiceVoter::HAS_ACCESS, $event) && $isTour) {
                $globalOperations['printInstructorInvoice']['href'] = \sprintf($globalOperations['printInstructorInvoice']['href'], $eventId);
                $allowInstructorInvoice = true;
            }
        }

        if (!$allowTourReport) {
            unset($globalOperations['writeTourReport']);
        }

        if (!$allowInstructorInvoice) {
            unset($globalOperations['printInstructorInvoice']);
        }
    }

    /**
     * Disable the edit button, if the user does not administer the registrations of the event.
     */
    #[AsCallback(table: 'tl_calendar_events_member', target: 'list.operations.edit.button', priority: 100)]
    public function editButton(DataContainerOperation $operation): void
    {
        $row = $operation->getRecord();

        if (!$this->security->isGranted('ROLE_ADMIN') && !$this->canAdministerRegistrations($row['eventId'])) {
            $operation->disable();
        }
    }

    /**
     * Disable the delete button. Only manual registrations can be deleted.
     */
    #[AsCallback(table: 'tl_calendar_events_member', target: 'list.operations.delete.button', priority: 100)]
    public function deleteButton(DataContainerOperation $operation): void
    {
        $row = $operation->getRecord();

        $isAllowed = $this->security->isGranted('ROLE_ADMIN') || ($this->canAdministerRegistrations($row['eventId']) && BookingType::MANUALLY === ($row['bookingType'] ?? null));

        if (!$isAllowed) {
            $operation->disable();
        }
    }

    /**
     * act=create: $dc->id is the event id.
     */
    private function allowCreate(DataContainer $dc): bool
    {
        if (!$this->canAdministerRegistrations($dc->id)) {
            return false;
        }

        $GLOBALS['TL_DCA'][self::TABLE]['config']['notCreatable'] = false;
        $GLOBALS['TL_DCA'][self::TABLE]['config']['closed'] = false;

        return true;
    }

    private function allowEdit(DataContainer $dc): bool
    {
        $registration = $dc->getCurrentRecord();

        if (null === $registration || !$this->canAdministerRegistrations($registration['eventId'])) {
            return false;
        }

        $GLOBALS['TL_DCA'][self::TABLE]['config']['notEditable'] = false;

        return true;
    }

    /**
     * act=toggle&field=hasParticipated: Confirming the participation (0 → 1) is only
     * allowed for accepted registrations and registrations on the waiting list.
     * Removing it (1 → 0) is always allowed.
     */
    private function allowToggleParticipation(DataContainer $dc): bool
    {
        $request = $this->requestStack->getCurrentRequest();

        if ('hasParticipated' !== $request->query->get('field')) {
            return false;
        }

        $registration = $dc->getCurrentRecord();

        if (null === $registration) {
            return false;
        }

        $isConfirmationAllowed = $registration['hasParticipated'] || \in_array($registration['stateOfSubscription'] ?? '', EventSubscriptionState::PARTICIPATION_CONFIRMATION_ALLOWED, true);

        if (!$isConfirmationAllowed || !$this->canAdministerRegistrations($registration['eventId'])) {
            return false;
        }

        $GLOBALS['TL_DCA'][self::TABLE]['config']['notEditable'] = false;

        return true;
    }

    /**
     * act=delete: Only manual registrations can be deleted.
     */
    private function allowDelete(DataContainer $dc): bool
    {
        $registration = $dc->getCurrentRecord();

        if (null === $registration || !$this->canAdministerRegistrations($registration['eventId'])) {
            return false;
        }

        if (BookingType::MANUALLY !== ($registration['bookingType'] ?? null)) {
            return false;
        }

        $GLOBALS['TL_DCA'][self::TABLE]['config']['notDeletable'] = false;

        return true;
    }

    private function canAdministerRegistrations(mixed $eventId): bool
    {
        return $this->security->isGranted(CalendarEventsVoter::CAN_ADMINISTER_EVENT_REGISTRATIONS, $eventId);
    }

    private function setPermissions(bool $closed, bool $notCreatable, bool $notEditable, bool $notDeletable): void
    {
        $GLOBALS['TL_DCA'][self::TABLE]['config']['closed'] = $closed;
        $GLOBALS['TL_DCA'][self::TABLE]['config']['notCreatable'] = $notCreatable;
        $GLOBALS['TL_DCA'][self::TABLE]['config']['notEditable'] = $notEditable;
        $GLOBALS['TL_DCA'][self::TABLE]['config']['notDeletable'] = $notDeletable;
    }
}
