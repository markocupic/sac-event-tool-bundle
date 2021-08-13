<?php

declare(strict_types=1);

/*
 * This file is part of SAC Event Tool Bundle.
 *
 * (c) Marko Cupic 2021 <m.cupic@gmx.ch>
 * @license MIT
 * For the full copyright and license information,
 * please view the LICENSE file that was distributed with this source code.
 * @link https://github.com/markocupic/sac-event-tool-bundle
 */

namespace Markocupic\SacEventToolBundle\User\FrontendUser;

use Contao\CalendarEventsMemberModel;
use Contao\CalendarEventsModel;
use Contao\Config;
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\CoreBundle\Monolog\ContaoContext;
use Contao\Database;
use Contao\Date;
use Contao\Folder;
use Contao\MemberModel;
use Contao\Message;
use Contao\System;
use Markocupic\SacEventToolBundle\Config\EventSubscriptionLevel;
use Psr\Log\LogLevel;

/**
 * Class ClearFrontendUserData.
 */
class ClearFrontendUserData
{
    /**
     * @var ContaoFramework
     */
    private $framework;

    /**
     * @var string
     */
    private $projectDir;

    /**
     * ClearFrontendUserData constructor.
     */
    public function __construct(ContaoFramework $framework, string $projectDir)
    {
        $this->framework = $framework;
        $this->projectDir = $projectDir;

        // Initialize contao framework
        $this->framework->initialize();
    }

    /**
     * Anonymize orphaned entries in tl_calendar_events_member.
     */
    public function anonymizeOrphanedCalendarEventsMemberDataRecords(): void
    {
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
                    $container = System::getContainer();
                    $logger = $container->get('monolog.logger.contao');
                    $message = sprintf(
                        'Teilnehmer %s %s mit ID %s [%s] am Event mit ID %s [%s] konnte nicht in tl_member gefunden werden."',
                        $objCalendarEventsMember->firstname,
                        $objCalendarEventsMember->lastname,
                        $objCalendarEventsMember->id,
                        $objCalendarEventsMember->sacMemberId,
                        $objCalendarEventsMember->eventId,
                        $objCalendarEventsMember->eventName,
                    );

                    $logger->log(LogLevel::INFO, $message, ['contao' => new ContaoContext(__FILE__.' Line: '.__LINE__, 'EVENT_MEMBER_NOT_FOUND')]);
                    // Notify admin
                    if (!empty($configAdapter->get('adminEmail'))) {
                        mail($configAdapter->get('adminEmail'), 'Unbekannter Teilnehmer in Event '.$objCalendarEventsMember->eventName, $message.' In '.__FILE__.' LINE: '.__LINE__);
                    }

                    /*
                     * @todo Teilnehmer werden unbeabsichtig anonymisiert
                     */
                    //$this->anonymizeCalendarEventsMemberDataRecord($objEventsMember->current());
                }
            }
        }
    }

    public function anonymizeCalendarEventsMemberDataRecord(CalendarEventsMemberModel $objCalendarEventsMember): bool
    {
        /** @var Date $dateAdapter */
        $dateAdapter = $this->framework->getAdapter(Date::class);

        if (null !== $objCalendarEventsMember) {
            if (null !== $objCalendarEventsMember) {
                if (!$objCalendarEventsMember->anonymized) {
                    // Log
                    $container = System::getContainer();
                    $logger = $container->get('monolog.logger.contao');
                    $logger->log(LogLevel::INFO, sprintf('Anonymized tl_calendar_events_member.id=%s. Firstname: %s, Lastname: %s (%s)"', $objCalendarEventsMember->id, $objCalendarEventsMember->firstname, $objCalendarEventsMember->lastname, $objCalendarEventsMember->sacMemberId), ['contao' => new ContaoContext(__FILE__.' Line: '.__LINE__, 'ANONYMIZED_CALENDAR_EVENTS_MEMBER_DATA')]);

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
                }

                return true;
            }
        }

        return false;
    }

    public function disableLogin(int $memberId): void
    {
        /** @var MemberModel $memberModelAdapter */
        $memberModelAdapter = $this->framework->getAdapter(MemberModel::class);

        /** @var MemberModel $configAdapter */
        $configAdapter = $this->framework->getAdapter(Config::class);

        $objMember = $memberModelAdapter->findByPk($memberId);

        if (null !== $objMember) {
            // Log
            $container = System::getContainer();
            $logger = $container->get('monolog.logger.contao');
            $logger->log(
                LogLevel::INFO,
                sprintf(
                    'Login for member with ID:%s [%s] has been deactivated.',
                    $objMember->id,
                    $objMember->sacMemberId
                ),
                ['contao' => new ContaoContext(__FILE__.' Line: '.__LINE__, $configAdapter->get('DISABLE_MEMBER_LOGIN'))]
            );

            $objMember->login = '';
            $objMember->password = '';
            $objMember->save();
        }
    }

    public function deleteFrontendAccount(int $memberId): void
    {
        /** @var MemberModel $memberModelAdapter */
        $memberModelAdapter = $this->framework->getAdapter(MemberModel::class);

        /** @var MemberModel $configAdapter */
        $configAdapter = $this->framework->getAdapter(Config::class);

        $objMember = $memberModelAdapter->findByPk($memberId);

        if (null !== $objMember) {
            // Log
            $logger = System::getContainer()->get('monolog.logger.contao');
            $strText = sprintf('Member with ID:%s has been deleted.', $objMember->id);
            $logger->log(LogLevel::INFO, $strText, ['contao' => new ContaoContext(__METHOD__, $configAdapter->get('DELETE_MEMBER'))]);

            $objMember->delete();
        }
    }

    /**
     * @throws \Exception
     */
    public function clearMemberProfile(int $memberId, bool $blnForceClearing = false): bool
    {
        /** @var CalendarEventsMemberModel $calendarEventsMemberModelAdapter */
        $calendarEventsMemberModelAdapter = $this->framework->getAdapter(CalendarEventsMemberModel::class);

        /** @var MemberModel $memberModelAdapter */
        $memberModelAdapter = $this->framework->getAdapter(MemberModel::class);

        /** @var Message $messageAdapter */
        $messageAdapter = $this->framework->getAdapter(Message::class);

        /** @var Date $dateAdapter */
        $dateAdapter = $this->framework->getAdapter(Date::class);

        /** @var MemberModel $configAdapter */
        $configAdapter = $this->framework->getAdapter(Config::class);

        $arrEventsMember = [];
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

                        $arrErrorMsg[] = sprintf('Dein Profil kann nicht gelöscht werden, weil du beim Event "%s [%s]" vom %s auf der Buchungsliste stehst. Bitte melde dich zuerst vom Event ab oder nimm gegebenenfalls mit dem Leiter Kontakt auf.', $objEvent->title, $objEventsMember->stateOfSubscription, $dateAdapter->parse($configAdapter->get('dateFormat'), $objEvent->startDate));
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
                    $messageAdapter->add($errorMsg, 'TL_ERROR', TL_MODE);
                }

                return false;
            }

            // Anonymize entries from tl_calendar_events_member
            foreach ($arrEventsMember as $eventsMemberId) {
                $objEventsMember = $calendarEventsMemberModelAdapter->findByPk($eventsMemberId);

                if (null !== $objEventsMember) {
                    $this->anonymizeCalendarEventsMemberDataRecord($objEventsMember);
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
        /** @var MemberModel $configAdapter */
        $configAdapter = $this->framework->getAdapter(Config::class);

        $strAvatarDir = $configAdapter->get('SAC_EVT_FE_USER_AVATAR_DIRECTORY');

        if (is_dir($this->projectDir.'/'.$strAvatarDir.'/'.$memberId)) {
            $strDir = $strAvatarDir.'/'.$memberId;
            $objDir = new Folder($strDir);

            if (null !== $objDir) {
                // Log
                $logger = System::getContainer()->get('monolog.logger.contao');
                $strText = sprintf('Deleted avatar directory "%s" for member with ID:%s.', $strDir, $memberId);
                $logger->log(LogLevel::INFO, $strText, ['contao' => new ContaoContext(__METHOD__, 'DELETED_AVATAR_DIRECTORY')]);

                $objDir->purge();
                $objDir->delete();
            }
        }
    }

    private function findUpcomingEventsByMemberId(int $memberId): array
    {
        /** @var Database $databaseAdapter */
        $databaseAdapter = $this->framework->getAdapter(Database::class);

        /** @var CalendarEventsModel $calendarEventsModelAdapter */
        $calendarEventsModelAdapter = $this->framework->getAdapter(CalendarEventsModel::class);

        /** @var MemberModel $memberModelAdapter */
        $memberModelAdapter = $this->framework->getAdapter(MemberModel::class);

        /** @var CalendarEventsMemberModel $calendarEventsMemberModelAdapter */
        $calendarEventsMemberModelAdapter = $this->framework->getAdapter(CalendarEventsMemberModel::class);

        $arrEvents = [];
        $objMember = $memberModelAdapter->findByPk($memberId);

        if (null !== $objMember) {
            $objEvents = $databaseAdapter->getInstance()->prepare('SELECT * FROM tl_calendar_events WHERE endDate>? ORDER BY startDate')->execute(time());

            while ($objEvents->next()) {
                $objJoinedEvents = $databaseAdapter->getInstance()->prepare('SELECT * FROM tl_calendar_events_member WHERE sacMemberId=? AND eventId=?')->limit(1)->execute($objMember->sacMemberId, $objEvents->id);

                if ($objJoinedEvents->numRows) {
                    $arr = $objEvents->row();
                    $objEventsModel = $calendarEventsModelAdapter->findByPk($objEvents->id);
                    $arr['id'] = $objEvents->id;
                    $arr['eventModel'] = $objEventsModel;
                    $arr['registrationId'] = $objJoinedEvents->id;
                    $arr['eventRegistrationModel'] = $calendarEventsMemberModelAdapter->findByPk($objJoinedEvents->id);
                    $arrEvents[] = $arr;
                }
            }
        }

        return $arrEvents;
    }
}
