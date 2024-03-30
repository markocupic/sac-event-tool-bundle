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

namespace Markocupic\SacEventToolBundle\DataContainer\AccessDescision;

use Contao\Backend;
use Contao\CalendarEventsModel;
use Contao\CoreBundle\DependencyInjection\Attribute\AsCallback;
use Contao\CoreBundle\Exception\AccessDeniedException;
use Contao\CoreBundle\Framework\Adapter;
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\DataContainer;
use Contao\Image;
use Contao\StringUtil;
use Doctrine\DBAL\Connection;
use Markocupic\SacEventToolBundle\Config\BookingType;
use Markocupic\SacEventToolBundle\Config\EventType;
use Markocupic\SacEventToolBundle\Security\Voter\CalendarEventsVoter;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\RequestStack;

class CalendarEventsMember
{
    public const TABLE = 'tl_calendar_events_member';

    // Adapters
    private Adapter $backend;
    private Adapter $calendarEvents;
    private Adapter $image;
    private Adapter $stringUtil;

    public function __construct(
        private readonly Connection $connection,
        private readonly ContaoFramework $framework,
        private readonly RequestStack $requestStack,
        private readonly Security $security,
    ) {
        // Adapters
        $this->image = $this->framework->getAdapter(Image::class);
        $this->backend = $this->framework->getAdapter(Backend::class);
        $this->calendarEvents = $this->framework->getAdapter(CalendarEventsModel::class);
        $this->stringUtil = $this->framework->getAdapter(StringUtil::class);
    }

    /**
     * @throws \Exception
     */
    #[AsCallback(table: 'tl_calendar_events_member', target: 'config.onload', priority: 100)]
    public function checkPermission(DataContainer $dc): void
    {
        $request = $this->requestStack->getCurrentRequest();

        if ($this->security->isGranted('ROLE_ADMIN')) {
            return;
        }

        // WHAT IS THE PHILOSOPHY?
        // Don't grant anything from scratch, but make exceptions for qualified users.
        $GLOBALS['TL_DCA']['tl_calendar_events_member']['config']['closed'] = true;
        $GLOBALS['TL_DCA']['tl_calendar_events_member']['config']['notCreatable'] = true;
        $GLOBALS['TL_DCA']['tl_calendar_events_member']['config']['notEditable'] = true;
        $GLOBALS['TL_DCA']['tl_calendar_events_member']['config']['notDeletable'] = true;

        if (!$request->query->has('act') && $request->query->has('id')) {
            if ($this->security->isGranted(CalendarEventsVoter::CAN_ADMINISTER_EVENT_REGISTRATIONS, $dc->id)) {
                $GLOBALS['TL_DCA']['tl_calendar_events_member']['config']['closed'] = false;
                $GLOBALS['TL_DCA']['tl_calendar_events_member']['config']['notCreatable'] = false;
                $GLOBALS['TL_DCA']['tl_calendar_events_member']['config']['notEditable'] = false;
                $GLOBALS['TL_DCA']['tl_calendar_events_member']['config']['notDeletable'] = false;
            }

            if (!$this->security->isGranted(CalendarEventsVoter::CAN_ADMINISTER_EVENT_REGISTRATIONS, $dc->id)) {
                unset($GLOBALS['TL_DCA']['tl_calendar_events_member']['list']['operations']['toggleParticipationState']);
            }
        }

        // This should prevent deep link hacking attempts
        // (the user types the url manually to perform a certain action).
        if ($request->query->has('act')) {
            $action = $request->query->get('act');

            $blnAllow = false;

            // Allow: show, create, edit, toggle, delete
            // Do not allow: select, editAll, deleteAll, copyAll, overrideAll
            switch ($action) {
                case 'show':
                    $blnAllow = true;
                    break;
                case 'create':
                    if ($this->security->isGranted(CalendarEventsVoter::CAN_WRITE_EVENT, $dc->id)) {
                        $blnAllow = true;
                        $GLOBALS['TL_DCA']['tl_calendar_events_member']['config']['notCreatable'] = false;
                        $GLOBALS['TL_DCA']['tl_calendar_events_member']['config']['closed'] = false;
                    }
                    break;
                case 'edit':
                case 'toggle':
                    if ($this->security->isGranted(CalendarEventsVoter::CAN_WRITE_EVENT, $dc->getCurrentRecord()['eventId'])) {
                        $GLOBALS['TL_DCA']['tl_calendar_events_member']['config']['notEditable'] = false;
                        $blnAllow = true;
                    }
                    break;
                case 'delete':
                    if ($this->security->isGranted(CalendarEventsVoter::CAN_ADMINISTER_EVENT_REGISTRATIONS, $dc->getCurrentRecord()['eventId'])) {
                        $regId = $request->query->get('id');

                        $bookingType = $this->connection->fetchOne(
                            'SELECT bookingType FROM tl_calendar_events_member WHERE id = ?',
                            [$regId],
                        );

                        if (BookingType::MANUALLY === $bookingType) {
                            $GLOBALS['TL_DCA']['tl_calendar_events_member']['config']['notDeletable'] = false;
                            $blnAllow = true;
                        }
                    }
                    break;

                default:
                    //Do not allow: select, editAll, deleteAll, copyAll, overrideAll
            }

            if (!$blnAllow) {
                throw new AccessDeniedException(sprintf('Not enough permissions to perform the "%s" action on the current event.', $action));
            }
        }
    }

    /**
     * Generate href for $GLOBALS['TL_DCA']['tl_calendar_events_member']['list']['global_operations']['writeTourReport']
     * Generate href for $GLOBALS['TL_DCA']['tl_calendar_events_member']['list']['global_operations']['printInstructorInvoice'].
     */
    #[AsCallback(table: 'tl_calendar_events_member', target: 'config.onload', priority: 100)]
    public function setGlobalOperations(DataContainer $dc): void
    {
        $request = $this->requestStack->getCurrentRequest();

        if ($request->query->has('act')) {
            return;
        }

        $user = $this->security->getUser();

        // Generally do not allow selectAll to non-admins.
	    if(!$this->security->isGranted('ROLE_ADMIN')){
		    unset($GLOBALS['TL_DCA']['tl_calendar_events_member']['list']['global_operations']['all']);
	    }

        if (!$this->security->isGranted(CalendarEventsVoter::CAN_ADMINISTER_EVENT_REGISTRATIONS, $dc->id)) {
            unset(
                $GLOBALS['TL_DCA']['tl_calendar_events_member']['list']['global_operations']['sendEmail'],
                $GLOBALS['TL_DCA']['tl_calendar_events_member']['list']['global_operations']['downloadEventRegistrationListCsv'],
                $GLOBALS['TL_DCA']['tl_calendar_events_member']['list']['global_operations']['downloadEventRegistrationListDocx'],
            );
        }

        $blnAllowTourReportButton = false;
        $blnAllowInstructorInvoiceButton = false;

        $eventId = $request->query->get('id');
        $objEvent = $this->calendarEvents->findByPk($eventId);

        if (null !== $objEvent) {
            // Check if backend user is allowed
            if ($this->security->isGranted(CalendarEventsVoter::CAN_WRITE_EVENT, $objEvent->id) || $objEvent->registrationGoesTo === $user->id) {
                if (EventType::TOUR === $objEvent->eventType || EventType::LAST_MINUTE_TOUR === $objEvent->eventType) {
                    $href = $GLOBALS['TL_DCA']['tl_calendar_events_member']['list']['global_operations']['writeTourReport']['href'];
                    $GLOBALS['TL_DCA']['tl_calendar_events_member']['list']['global_operations']['writeTourReport']['href'] = sprintf($href, $eventId);
                    $blnAllowTourReportButton = true;

                    $href = $GLOBALS['TL_DCA']['tl_calendar_events_member']['list']['global_operations']['printInstructorInvoice']['href'];
                    $GLOBALS['TL_DCA']['tl_calendar_events_member']['list']['global_operations']['printInstructorInvoice']['href'] = sprintf($href, $eventId);
                    $blnAllowInstructorInvoiceButton = true;
                }
            }
        }

        if (!$blnAllowTourReportButton) {
            unset($GLOBALS['TL_DCA']['tl_calendar_events_member']['list']['global_operations']['writeTourReport']);
        }

        if (!$blnAllowInstructorInvoiceButton) {
            unset($GLOBALS['TL_DCA']['tl_calendar_events_member']['list']['global_operations']['printInstructorInvoice']);
        }
    }

    /**
     * Return the delete user button.
     *
     * @param array       $row
     * @param string|null $href
     * @param string      $label
     * @param string      $title
     * @param string|null $icon
     * @param string      $attributes
     *
     * @return string
     */
    #[AsCallback(table: 'tl_calendar_events_member', target: 'list.operations.edit.button', priority: 100)]
    public function editButton(array $row, string|null $href, string $label, string $title, string|null $icon, string $attributes): string
    {
        $blnAllow = false;

        if ($this->security->isGranted('ROLE_ADMIN')) {
            $blnAllow = true;
        }

        if ($this->security->isGranted(CalendarEventsVoter::CAN_WRITE_EVENT, $row['eventId'])) {
            $blnAllow = true;
        }

        if (!$blnAllow) {
            $icon = str_replace('.svg', '--disabled.svg', $icon);

            return $this->image->getHtml($icon).' ';
        }

        return '<a href="'.$this->backend->addToUrl($href.'&amp;id='.$row['id']).'" title="'.$this->stringUtil->specialchars($title).'"'.$attributes.'>'.$this->image->getHtml($icon, $label).'</a> ';
    }

    /**
     * Return the delete user button.
     *
     * @param array       $row
     * @param string|null $href
     * @param string      $label
     * @param string      $title
     * @param string|null $icon
     * @param string      $attributes
     *
     * @return string
     */
    #[AsCallback(table: 'tl_calendar_events_member', target: 'list.operations.delete.button', priority: 100)]
    public function deleteButton(array $row, string|null $href, string $label, string $title, string|null $icon, string $attributes): string
    {
        $blnAllow = false;

        if ($this->security->isGranted('ROLE_ADMIN')) {
            $blnAllow = true;
        }

        if ($this->security->isGranted(CalendarEventsVoter::CAN_DELETE_EVENT, $row['eventId'])) {
            if (BookingType::MANUALLY === $row['bookingType'] ?? null) {
                $blnAllow = true;
            }
        }

        if (!$blnAllow) {
            $icon = str_replace('.svg', '--disabled.svg', $icon);

            return $this->image->getHtml($icon).' ';
        }

        return '<a href="'.$this->backend->addToUrl($href.'&amp;id='.$row['id']).'" title="'.$this->stringUtil->specialchars($title).'"'.$attributes.'>'.$this->image->getHtml($icon, $label).'</a> ';
    }
}
