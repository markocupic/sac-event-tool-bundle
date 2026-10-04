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

namespace Markocupic\SacEventToolBundle\Controller\FrontendModule\EventRegistration;

use Contao\CalendarEventsModel;
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\MemberModel;
use Contao\UserModel;
use Contao\Validator;
use Markocupic\SacEventToolBundle\Config\EventState;
use Markocupic\SacEventToolBundle\Controller\FrontendModule\Exception\EventRegistrationException;
use Markocupic\SacEventToolBundle\Model\EventReleaseLevelPolicyModel;
use Markocupic\SacEventToolBundle\Util\CalendarEventsUtil;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Checks whether a member may register for an event (published, release level, registration period, ...).
 */
class EventRegistrationEligibility
{
    public function __construct(
        private readonly CalendarEventsUtil $calendarEventsUtil,
        private readonly ContaoFramework $framework,
        #[Autowire('%sacevt.event_registration.config.reg_start_time_offset%')]
        private readonly int $regStartTimeOffset,
    ) {
    }

    /**
     * @throws EventRegistrationException if the member may not register for the event
     */
    public function check(CalendarEventsModel $eventModel, MemberModel $memberModel): void
    {
        if (!$eventModel->published) {
            throw new EventRegistrationException('You can not subscribe to the current event because it is not published.', EventRegistrationException::LEVEL_ERROR, 'ERR.evt_reg_eventNotPublishedYet', []);
        }

        $eventReleaseLevelPolicy = $this->framework->getAdapter(EventReleaseLevelPolicyModel::class)->findOneByEventId($eventModel->id);

        if (null === $eventReleaseLevelPolicy || !$eventReleaseLevelPolicy->allowRegistration) {
            throw new EventRegistrationException('The event release level policy does not allow you to register for this event.', EventRegistrationException::LEVEL_ERROR, 'ERR.evt_reg_eventReleaseLevelPolicyDoesNotAllowRegistrations', [$eventModel->title]);
        }

        if ($eventModel->disableOnlineRegistration) {
            throw new EventRegistrationException('Online registration has been disabled for this event.', EventRegistrationException::LEVEL_INFO, 'ERR.evt_reg_onlineRegDisabled', []);
        }

        if (EventState::STATE_FULLY_BOOKED === $eventModel->eventState) {
            throw new EventRegistrationException('The event you are trying to register for is already fully booked.', EventRegistrationException::LEVEL_INFO, 'ERR.evt_reg_eventFullyBooked', []);
        }

        if (EventState::STATE_CANCELED === $eventModel->eventState) {
            throw new EventRegistrationException('The event you are trying to register for has been canceled.', EventRegistrationException::LEVEL_INFO, 'ERR.evt_reg_eventCanceled', []);
        }

        if (EventState::STATE_RESCHEDULED === $eventModel->eventState) {
            throw new EventRegistrationException('The event you are trying to register for has been deferred.', EventRegistrationException::LEVEL_INFO, 'ERR.evt_reg_eventDeferred', []);
        }

        $now = $this->getCurrentTimestamp();
        $registrationStart = (int) $eventModel->registrationStartDate + $this->regStartTimeOffset;

        if ($eventModel->setRegistrationPeriod && $registrationStart > $now) {
            throw new EventRegistrationException('Subscribing for the event is not possible yet.', EventRegistrationException::LEVEL_INFO, 'ERR.evt_reg_registrationPossibleOn', [$eventModel->title, date('d.m.Y H:i', $registrationStart)]);
        }

        if ($eventModel->setRegistrationPeriod && $eventModel->registrationEndDate < $now) {
            $strEndDate = date('d.m.Y', (int) $eventModel->registrationEndDate);
            $strEndTime = date('H:i', (int) $eventModel->registrationEndDate);

            throw new EventRegistrationException('The registration deadline for this event has expired.', EventRegistrationException::LEVEL_INFO, 'ERR.evt_reg_registrationDeadlineExpired', [$strEndDate, $strEndTime]);
        }

        if (!$eventModel->setRegistrationPeriod && $now + 86400 > $eventModel->startDate) {
            throw new EventRegistrationException('If no registration time has been set, online registration is only possible up to 24 h before the event start date.', EventRegistrationException::LEVEL_INFO, 'ERR.evt_reg_registrationPossible24HoursBeforeEventStart', []);
        }

        if (true === $this->calendarEventsUtil->areBookingDatesOccupied($eventModel, $memberModel)) {
            throw new EventRegistrationException('You can not subscribe because you are already registered for another event at the same time.', EventRegistrationException::LEVEL_INFO, 'ERR.evt_reg_eventDateOverlapError', []);
        }

        $mainInstructorModel = $this->framework->getAdapter(UserModel::class)->findById($eventModel->mainInstructor);

        if (null === $mainInstructorModel) {
            throw new EventRegistrationException('You can not register for this event because there is no main instructor assigned to the event.', EventRegistrationException::LEVEL_INFO, 'ERR.evt_reg_mainInstructorNotFound', [$eventModel->mainInstructor]);
        }

        if (empty($mainInstructorModel->email) || !Validator::isEmail($mainInstructorModel->email)) {
            throw new EventRegistrationException('You can not register for the event because the main instructor has an invalid email address.', EventRegistrationException::LEVEL_ERROR, 'ERR.evt_reg_mainInstructorsEmailAddrNotFound', [$eventModel->mainInstructor]);
        }

        if (!Validator::isEmail($memberModel->email)) {
            throw new EventRegistrationException('You can not subscribe to this event because of an invalid or not existent email address.', EventRegistrationException::LEVEL_INFO, 'ERR.evt_reg_membersEmailAddrNotFound', []);
        }
    }

    /**
     * Test seam: returns the current UNIX timestamp.
     */
    protected function getCurrentTimestamp(): int
    {
        return time();
    }
}
