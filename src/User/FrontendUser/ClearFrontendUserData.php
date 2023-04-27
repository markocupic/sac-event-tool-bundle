<?php

declare(strict_types=1);

/*
 * This file is part of SAC Event Tool Bundle.
 *
 * (c) Marko Cupic 2023 <m.cupic@gmx.ch>
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
use Markocupic\SacEventToolBundle\Config\EventSubscriptionLevel;
use Markocupic\SacEventToolBundle\Config\Log;
use Markocupic\SacEventToolBundle\Model\CalendarEventsMemberModel;
use Psr\Log\LoggerInterface;

class ClearFrontendUserData
{
    public function __construct(
        private readonly ContaoFramework $framework,
        private readonly string $projectDir,
        private readonly string $sacevtUserFrontendAvatarDir,
        private readonly LoggerInterface|null $contaoGeneralLogger = null,
    ) {
    }

    /**
     * Anonymize orphaned entries in tl_calendar_events_member.
     */
    public function anonymizeOrphanedCalendarEventsMemberDataRecords(): void
    {
        $this->framework->initialize();

        /** @var CalendarEventsMemberModel $calendarEventsMemberModelAdapter */
        $calendarEventsMemberModelAdapter = $this->framework->getAdapter(CalendarEventsMemberModel::class);

        /** @var Config $configAdapter */
        $configAdapter = $this->framework->getAdapter(Config::class);

        /** @var MemberModel $memberModelAdapter */
        $memberModelAdapter = $this->framework->getAdapter(MemberModel::class);

        $objEventsMember = $calendarEventsMemberModelAdapter->findAll();

        while ($objEventsMember->next()) {
            if ($objEventsMember->contaoMemberId > 0 || $objEventsMember->sacMemberId > 0) {
                $blnFound = false;

                if ($objEventsMember->contaoMemberId > 0) {
                    if (null !== $memberModelAdapter->findByPk($objEventsMember->contaoMemberId)) {
                        $blnFound = true;
                    }
                }

                if ($objEventsMember->sacMemberId > 0) {
                    if (null !== $memberModelAdapter->findOneBySacMemberId($objEventsMember->sacMemberId)) {
                        $blnFound = true;
                    }
                }

                if (!$blnFound) {
                    $message = sprintf(
                        'Could not assign a frontend user to the registration with ID %s (%s %s [%s]) and the event with ID %s "%s".',
                        $objEventsMember->id,
                        $objEventsMember->firstname,
                        $objEventsMember->lastname,
                        $objEventsMember->sacMemberId,
                        $objEventsMember->eventId,
                        $objEventsMember->eventName,
                    );

                    $this->contaoGeneralLogger?->info(
                        $message,
                        ['contao' => new ContaoContext(__FILE__.' Line: '.__LINE__, 'EVENT_MEMBER_NOT_FOUND')],
                    );

                    // Notify admin
                    if (!empty($configAdapter->get('adminEmail'))) {
                        mail(
                            $configAdapter->get('adminEmail'),
                            'Unbekannter Teilnehmer in Event '.$objEventsMember->eventName,
                            $message.' In '.__FILE__.' LINE: '.__LINE__
                        );
                    }

                    /*
                     * @todo: Currently disabled because event registrations has been erroneously anonymized.
                     */
                    //$this->anonymizeEventRegistration($objEventsMember->current());
                }
            }
        }
    }

    public function anonymizeEventRegistration(CalendarEventsMemberModel $objCalendarEventsMember): bool
    {
        $this->framework->initialize();

        /** @var Date $dateAdapter */
        $dateAdapter = $this->framework->getAdapter(Date::class);

        if (!$objCalendarEventsMember->anonymized) {
            $this->contaoGeneralLogger?->info(
                sprintf(
                    'Anonymized tl_calendar_events_member.id=%s. Firstname: %s, Lastname: %s (%s)"',
                    $objCalendarEventsMember->id,
                    $objCalendarEventsMember->firstname,
                    $objCalendarEventsMember->lastname,
                    $objCalendarEventsMember->sacMemberId
                ),
                ['contao' => new ContaoContext(__METHOD__, 'ANONYMIZED_CALENDAR_EVENTS_MEMBER_DATA')],
            );

            $objCalendarEventsMember->firstname = 'Vorname [anonymisiert]';
            $objCalendarEventsMember->lastname = 'Nachname [anonymisiert]';
            $objCalendarEventsMember->email = '';
            $objCalendarEventsMember->sacMemberId = '';
            $objCalendarEventsMember->street = 'Adresse [anonymisiert]';
            $objCalendarEventsMember->postal = '0';
            $objCalendarEventsMember->city = 'Ort [anonymisiert]';
            $objCalendarEventsMember->mobile = '';
            $objCalendarEventsMember->foodHabits = '';
            $objCalendarEventsMember->dateOfBirth = '';
            $objCalendarEventsMember->contaoMemberId = 0;
            $objCalendarEventsMember->notes = 'Benutzerdaten anonymisiert am '.$dateAdapter->parse('d.m.Y', time());
            $objCalendarEventsMember->emergencyPhone = '999 99 99';
            $objCalendarEventsMember->emergencyPhoneName = ' [anonymisiert]';
            $objCalendarEventsMember->anonymized = '1';
            $objCalendarEventsMember->save();

            return true;
        }

        return false;
    }

    public function disableLogin(int $memberId): void
    {
        $this->framework->initialize();

        /** @var MemberModel $memberModelAdapter */
        $memberModelAdapter = $this->framework->getAdapter(MemberModel::class);

        $objMember = $memberModelAdapter->findByPk($memberId);

        if (null !== $objMember) {
            $this->contaoGeneralLogger?->info(
                sprintf(
                    'Login for member with ID:%s [%s] has been deactivated.',
                    $objMember->id,
                    $objMember->sacMemberId
                ),
                ['contao' => new ContaoContext(__METHOD__, Log::DISABLE_FRONTEND_USER_LOGIN)]
            );

            $objMember->login = '';
            $objMember->password = '';
            $objMember->save();
        }
    }

    public function deleteFrontendAccount(int $memberId): void
    {
        $this->framework->initialize();

        /** @var MemberModel $memberModelAdapter */
        $memberModelAdapter = $this->framework->getAdapter(MemberModel::class);

        $objMember = $memberModelAdapter->findByPk($memberId);

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

        /** @var CalendarEventsMemberModel $calendarEventsMemberModelAdapter */
        $calendarEventsMemberModelAdapter = $this->framework->getAdapter(CalendarEventsMemberModel::class);

        /** @var MemberModel $memberModelAdapter */
        $memberModelAdapter = $this->framework->getAdapter(MemberModel::class);

        /** @var Message $messageAdapter */
        $messageAdapter = $this->framework->getAdapter(Message::class);

        /** @var Date $dateAdapter */
        $dateAdapter = $this->framework->getAdapter(Date::class);

        /** @var Config $configAdapter */
        $configAdapter = $this->framework->getAdapter(Config::class);

        $arrEventsMember = [];
        $arrErrorMsg = [];
        $blnHasError = false;
        $objMember = $memberModelAdapter->findByPk($memberId);

        if (null !== $objMember) {
            // Upcoming events
            $arrEvents = $calendarEventsMemberModelAdapter->findUpcomingEventsByMemberId($objMember->id);

            foreach ($arrEvents as $arrEvent) {
                $objEventsMember = $calendarEventsMemberModelAdapter->findByPk($arrEvent['registrationId']);

                if (null !== $objEventsMember) {
                    if (null !== $arrEvent['eventModel']) {
                        $objEvent = $arrEvent['eventModel'];

                        if ($blnForceClearing) {
                            continue;
                        }

                        if (EventSubscriptionLevel::SUBSCRIPTION_REFUSED === $objEventsMember->stateOfSubscription) {
                            continue;
                        }

                        $arrErrorMsg[] = sprintf(
                            'Dein Profil kann nicht gelöscht werden, weil du beim Event "%s [%s]" vom %s auf der Buchungsliste stehst. Bitte melde dich zuerst vom Event ab oder nimm gegebenenfalls mit dem Leiter Kontakt auf.',
                            $objEvent->title,
                            $objEventsMember->stateOfSubscription,
                            $dateAdapter->parse($configAdapter->get('dateFormat'), $objEvent->startDate),
                        );

                        $blnHasError = true;
                    }
                }
            }

            // Past events
            $arrEvents = $calendarEventsMemberModelAdapter->findPastEventsByMemberId($objMember->id);

            foreach ($arrEvents as $arrEvent) {
                $objEventsMember = $calendarEventsMemberModelAdapter->findByPk($arrEvent['registrationId']);

                if (null !== $objEventsMember) {
                    $arrEventsMember[] = $objEventsMember->id;
                }
            }

            if ($blnHasError) {
                foreach ($arrErrorMsg as $errorMsg) {
                    $messageAdapter->add($errorMsg, 'TL_ERROR');
                }

                return false;
            }

            // Anonymize entries from tl_calendar_events_member
            foreach ($arrEventsMember as $eventsMemberId) {
                $objEventsMember = $calendarEventsMemberModelAdapter->findByPk($eventsMemberId);

                if (null !== $objEventsMember) {
                    $this->anonymizeEventRegistration($objEventsMember);
                }
            }

            // Delete avatar directory
            $this->deleteAvatarDirectory($memberId);

            return true;
        }

        return false;
    }

    /**
     * @throws \Exception
     */
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
