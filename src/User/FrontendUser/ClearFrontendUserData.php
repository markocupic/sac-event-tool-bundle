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

namespace Markocupic\SacEventToolBundle\User\FrontendUser;

use Contao\Config;
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\CoreBundle\Monolog\ContaoContext;
use Contao\Date;
use Contao\Folder;
use Contao\MemberModel;
use Contao\Message;
use Doctrine\DBAL\Connection;
use Markocupic\SacEventToolBundle\Config\EventSubscriptionState;
use Markocupic\SacEventToolBundle\Config\Log;
use Markocupic\SacEventToolBundle\Model\CalendarEventsMemberModel;
use Psr\Log\LoggerInterface;

readonly class ClearFrontendUserData
{
    public function __construct(
        private ContaoFramework $framework,
        private Connection $connection,
        private string $projectDir,
        private string $sacevtUserFrontendAvatarDir,
        private LoggerInterface|null $contaoGeneralLogger = null,
    ) {
    }

    /**
     * Anonymize orphaned records in tl_calendar_events_member.
     */
    public function anonymizeOrphanedEventRegistrations(): void
    {
        $this->framework->initialize();

        $arrRegistrations = $this->connection->fetchAllAssociative('SELECT * FROM tl_calendar_events_member');

        foreach ($arrRegistrations as $registration) {
            // Important!!! Do nothing if the participant was entered manually without an sacMemberId or member ID (tl_member)
            if (empty($registration['contaoMemberId']) && empty($registration['sacMemberId'])) {
                continue;
            }

            if ($registration['contaoMemberId'] > 0) {
                if (null !== $this->framework->getAdapter(MemberModel::class)->findByPk($registration['contaoMemberId'])) {
                    continue;
                }
            }

            if ($registration['sacMemberId'] > 0) {
                if (null !== $this->framework->getAdapter(MemberModel::class)->findOneBySacMemberId($registration['sacMemberId'])) {
                    continue;
                }
            }

            $message = sprintf(
                'Could not assign a frontend user to the registration with ID %s (%s %s [%s]) and the event with ID %s "%s" in %s:%d.',
                $registration['id'],
                $registration['firstname'],
                $registration['lastname'],
                $registration['sacMemberId'],
                $registration['eventId'],
                $registration['eventName'],
                __METHOD__,
                __LINE__,
            );

            $this->contaoGeneralLogger?->info($message);

            // Notify admin
            $adminEmail = $this->framework->getAdapter(Config::class)->get('adminEmail');

            if (!empty($adminEmail)) {
                $subject = sprintf(
                    'Unknown event registration found Reg-ID: %d, Event "%s" Event-ID: %d',
                    $registration['id'],
                    $registration['eventName'],
                    $registration['eventId'],
                );

                mail($adminEmail, $subject, $message);
            }

            /*
             * @todo: Currently disabled because event registrations has been erroneously anonymized.
             */
            // $objEventRegistration = $this->framework->getAdapter(CalendarEventsMemberModel::class)->findByPk($registration['id']);
            // $this->anonymizeEventRegistration($objEventRegistration);
        }
    }

    public function anonymizeEventRegistration(CalendarEventsMemberModel $objEventRegistration): bool
    {
        $this->framework->initialize();

        if ($objEventRegistration->anonymized) {
            return false;
        }

        $this->contaoGeneralLogger?->info(
            sprintf(
                'Anonymized tl_calendar_events_member.id=%s. Firstname: %s, Lastname: %s (%s)"',
                $objEventRegistration->id,
                $objEventRegistration->firstname,
                $objEventRegistration->lastname,
                $objEventRegistration->sacMemberId
            ),
            ['contao' => new ContaoContext(__METHOD__, 'ANONYMIZED_CALENDAR_EVENTS_MEMBER_DATA')],
        );

        $objEventRegistration->firstname = 'Vorname [anonymisiert]';
        $objEventRegistration->lastname = 'Nachname [anonymisiert]';
        $objEventRegistration->email = '';
        $objEventRegistration->sacMemberId = '';
        $objEventRegistration->street = 'Adresse [anonymisiert]';
        $objEventRegistration->postal = '0';
        $objEventRegistration->city = 'Ort [anonymisiert]';
        $objEventRegistration->mobile = '';
        $objEventRegistration->foodHabits = '';
        $objEventRegistration->dateOfBirth = '';
        $objEventRegistration->contaoMemberId = 0;
        $objEventRegistration->notes = 'Benutzerdaten anonymisiert am '.date('d.m.Y', time());
        $objEventRegistration->emergencyPhone = '999 99 99';
        $objEventRegistration->emergencyPhoneName = ' [anonymisiert]';
        $objEventRegistration->anonymized = 1;
        $objEventRegistration->save();

        return true;
    }

    public function disableLogin(int $memberId): void
    {
        $this->framework->initialize();

        $objMember = $this->framework->getAdapter(MemberModel::class)->findByPk($memberId);

        if (null !== $objMember) {
            $this->contaoGeneralLogger?->info(
                sprintf(
                    'Login for member with ID:%s [%s] has been deactivated.',
                    $objMember->id,
                    $objMember->sacMemberId
                ),
                ['contao' => new ContaoContext(__METHOD__, Log::DISABLE_FRONTEND_USER_LOGIN)]
            );

            $objMember->login = 0;
            $objMember->password = '';
            $objMember->save();
        }
    }

    public function deleteFrontendAccount(int $memberId): void
    {
        $this->framework->initialize();

        $objMember = $this->framework->getAdapter(MemberModel::class)->findByPk($memberId);

        if (null !== $objMember) {
            $this->contaoGeneralLogger?->info(
                sprintf(
                    'Member with ID %s (%s %s) has been deleted.',
                    $objMember->id,
                    $objMember->firstname,
                    $objMember->lastname,
                ),
                ['contao' => new ContaoContext(__METHOD__, Log::DELETE_FRONTEND_USER)],
            );

            $objMember->delete();
        }
    }

    /**
     * @throws \Exception
     */
    public function clearMemberProfile(int $memberId, bool $blnForceClearing = false): bool
    {
        $this->framework->initialize();

        $arrEventsMember = [];
        $arrErrorMsg = [];
        $blnHasError = false;
        $objMember = $this->framework->getAdapter(MemberModel::class)->findByPk($memberId);

        if (null !== $objMember) {
            // Upcoming events
            $arrEvents = $this->framework->getAdapter(CalendarEventsMemberModel::class)->findUpcomingEventsByMemberId($objMember->id);

            foreach ($arrEvents as $arrEvent) {
                $objEventsMember = $this->framework->getAdapter(CalendarEventsMemberModel::class)->findByPk($arrEvent['registrationId']);

                if (null === $objEventsMember) {
                    continue;
                }

                if (null === $arrEvent['eventModel']) {
                    continue;
                }

                $objEvent = $arrEvent['eventModel'];

                if ($blnForceClearing) {
                    continue;
                }

                if (EventSubscriptionState::SUBSCRIPTION_REFUSED === $objEventsMember->stateOfSubscription) {
                    continue;
                }

                $arrErrorMsg[] = sprintf(
                    'Dein Profil kann nicht gelöscht werden, weil du beim Event "%s [%s]" vom %s auf der Buchungsliste stehst. Bitte melde dich zuerst vom Event ab oder nimm gegebenenfalls mit dem Leiter Kontakt auf.',
                    $objEvent->title,
                    $objEventsMember->stateOfSubscription,
                    $this->framework->getAdapter(Date::class)->parse($this->framework->getAdapter(Config::class)->get('dateFormat'), $objEvent->startDate),
                );

                $blnHasError = true;
            }

            // Past events
            $arrEvents = $this->framework->getAdapter(CalendarEventsMemberModel::class)->findPastEventsByMemberId($objMember->id, [], false, false);

            foreach ($arrEvents as $arrEvent) {
                $objEventsMember = $this->framework->getAdapter(CalendarEventsMemberModel::class)->findByPk($arrEvent['registrationId']);

                if (null !== $objEventsMember) {
                    $arrEventsMember[] = $objEventsMember->id;
                }
            }

            if ($blnHasError) {
                foreach ($arrErrorMsg as $errorMsg) {
                    $this->framework->getAdapter(Message::class)->addError($errorMsg);
                }

                return false;
            }

            // Anonymize entries from tl_calendar_events_member
            foreach ($arrEventsMember as $eventsMemberId) {
                $objEventsMember = $this->framework->getAdapter(CalendarEventsMemberModel::class)->findByPk($eventsMemberId);

                if (null === $objEventsMember) {
                    continue;
                }

                $this->anonymizeEventRegistration($objEventsMember);
            }

            // Delete avatar directory
            $this->deleteAvatarDirectory($memberId);

            return true;
        }

        return false;
    }

    public function deleteAvatarDirectory(int $memberId): void
    {
        $this->framework->initialize();

        if (is_dir($this->projectDir.'/'.$this->sacevtUserFrontendAvatarDir.'/'.$memberId)) {
            $strDir = $this->sacevtUserFrontendAvatarDir.'/'.$memberId;
            $objDir = new Folder($strDir);

            $this->contaoGeneralLogger?->info(
                sprintf(
                    'Deleted avatar directory "%s" for member with ID %s.',
                    $strDir,
                    $memberId,
                ),
                ['contao' => new ContaoContext(__METHOD__, Log::DELETE_FRONTEND_USER_AVATAR_DIRECTORY)],
            );

            $objDir->purge();
            $objDir->delete();
        }
    }
}
