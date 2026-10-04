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

namespace Markocupic\SacEventToolBundle\Feature\MemberProfileDeletion;

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
use Symfony\Component\Filesystem\Path;

/**
 * Deletes the profile of a member (tl_member):
 * - refused if the member is on the booking list of an upcoming event
 * - deletes the registrations of the member for events that no longer exist (including their versions)
 * - anonymizes the other registrations of the member
 *   (registrations are found by the Contao member ID or the SAC member ID)
 * - deletes the avatar directory
 * - deletes the member (frontend only, in the back end Contao deletes the record itself).
 *
 * See docs/features/member-profile-deletion.md
 */
class MemberProfileDeletion
{
    public function __construct(
        private readonly ContaoFramework $framework,
        private readonly Connection $connection,
        private readonly EventRegistrationAnonymizer $eventRegistrationAnonymizer,
        private readonly EventRegistrationRemover $eventRegistrationRemover,
        private readonly string $projectDir,
        private readonly string $sacevtUserFrontendAvatarDir,
        private readonly LoggerInterface|null $contaoGeneralLogger = null,
    ) {
    }

    /**
     * Removes the personal data of the member, but not the member itself.
     *
     * @param bool $force true: also if the member is on the booking list of an upcoming event
     *
     * @return bool false if the member does not exist or is on the booking list of an upcoming event
     *              (the reasons are added as Contao error messages)
     */
    public function clearMemberProfile(int $memberId, bool $force = false): bool
    {
        $this->framework->initialize();

        $member = $this->framework->getAdapter(MemberModel::class)->findById($memberId);

        if (null === $member) {
            return false;
        }

        if (!$force) {
            $errors = $this->getUpcomingEventErrors($memberId);

            if ([] !== $errors) {
                foreach ($errors as $error) {
                    $this->framework->getAdapter(Message::class)->addError($error);
                }

                return false;
            }
        }

        $sacMemberId = (int) $member->sacMemberId;

        // Without event there is nothing to keep (the tour history only shows existing events)
        $this->eventRegistrationRemover->remove($this->findRegistrationIdsOfDeletedEvents($memberId, $sacMemberId));

        foreach ($this->findRegistrationIdsOfExistingEvents($memberId, $sacMemberId) as $registrationId) {
            $this->eventRegistrationAnonymizer->anonymize($registrationId);
        }

        $this->deleteAvatarDirectory($memberId);

        return true;
    }

    /**
     * Clears the profile and deletes the member. Used by the frontend module "delete profile".
     *
     * @return bool false if the profile could not be cleared (see clearMemberProfile())
     */
    public function deleteMember(int $memberId): bool
    {
        if (!$this->clearMemberProfile($memberId)) {
            return false;
        }

        $member = $this->framework->getAdapter(MemberModel::class)->findById($memberId);

        if (null === $member) {
            return false;
        }

        $this->contaoGeneralLogger?->info(
            \sprintf('Member with ID %d (%s %s) has been deleted.', $member->id, $member->firstname, $member->lastname),
            ['contao' => new ContaoContext(__METHOD__, Log::DELETE_FRONTEND_USER)],
        );

        $member->delete();

        return true;
    }

    public function deleteAvatarDirectory(int $memberId): void
    {
        $this->framework->initialize();

        $directory = Path::join($this->sacevtUserFrontendAvatarDir, (string) $memberId);

        if (!is_dir(Path::join($this->projectDir, $directory))) {
            return;
        }

        // Contao\Folder also removes the folder from the file manager (DBAFS)
        $folder = new Folder($directory);
        $folder->purge();
        $folder->delete();

        $this->contaoGeneralLogger?->info(
            \sprintf('Deleted avatar directory "%s" for member with ID %d.', $directory, $memberId),
            ['contao' => new ContaoContext(__METHOD__, Log::DELETE_FRONTEND_USER_AVATAR_DIRECTORY)],
        );
    }

    /**
     * Registrations of the member for events that still exist and that are not yet anonymized.
     * Upcoming events with an active registration block the deletion before (see getUpcomingEventErrors()).
     *
     * @return list<int>
     */
    public function findRegistrationIdsOfExistingEvents(int $memberId, int $sacMemberId): array
    {
        return $this->findRegistrationIds(
            $memberId,
            $sacMemberId,
            'r.anonymized = 0 AND EXISTS (SELECT 1 FROM tl_calendar_events AS e WHERE e.id = r.eventId)',
        );
    }

    /**
     * Registrations of the member for events that no longer exist.
     *
     * @return list<int>
     */
    public function findRegistrationIdsOfDeletedEvents(int $memberId, int $sacMemberId): array
    {
        return $this->findRegistrationIds(
            $memberId,
            $sacMemberId,
            'NOT EXISTS (SELECT 1 FROM tl_calendar_events AS e WHERE e.id = r.eventId)',
        );
    }

    /**
     * One error message per upcoming event the member is on the booking list (every state except "refused").
     *
     * @return list<string>
     */
    public function getUpcomingEventErrors(int $memberId): array
    {
        $registrationAdapter = $this->framework->getAdapter(CalendarEventsMemberModel::class);
        $errors = [];

        foreach ($registrationAdapter->findUpcomingEventsByMemberId($memberId) as $upcomingEvent) {
            if (null === $upcomingEvent['registrationId'] || null === $upcomingEvent['eventModel']) {
                continue;
            }

            $registration = $registrationAdapter->findById($upcomingEvent['registrationId']);

            if (null === $registration || EventSubscriptionState::SUBSCRIPTION_REFUSED === $registration->stateOfSubscription) {
                continue;
            }

            $event = $upcomingEvent['eventModel'];

            $errors[] = \sprintf(
                'Dein Profil kann nicht gelöscht werden, weil du beim Event "%s [%s]" vom %s auf der Buchungsliste stehst. Bitte melde dich zuerst vom Event ab oder nimm gegebenenfalls mit dem Leiter Kontakt auf.',
                $event->title,
                $registration->stateOfSubscription,
                $this->framework->getAdapter(Date::class)->parse($this->framework->getAdapter(Config::class)->get('dateFormat'), $event->startDate),
            );
        }

        return $errors;
    }

    /**
     * Registrations of the member are found by the Contao member ID or by the SAC member ID.
     *
     * @return list<int>
     */
    private function findRegistrationIds(int $memberId, int $sacMemberId, string $condition): array
    {
        $ids = $this->connection->fetchFirstColumn(
            'SELECT r.id FROM tl_calendar_events_member AS r WHERE (r.contaoMemberId = ? OR (? > 0 AND r.sacMemberId = ?)) AND '.$condition.' ORDER BY r.id',
            [$memberId, $sacMemberId, (string) $sacMemberId],
        );

        return array_map(intval(...), $ids);
    }
}
