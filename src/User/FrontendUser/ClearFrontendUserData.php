<?php

declare(strict_types=1);

/**
 * SAC Event Tool Web Plugin for Contao
 * Copyright (c) 2008-2020 Marko Cupic
 * @package sac-event-tool-bundle
 * @author Marko Cupic m.cupic@gmx.ch, 2017-2020
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
use Psr\Log\LogLevel;

/**
 * Class ClearFrontendUserData
 * @package Markocupic\SacEventToolBundle\User\FrontendUser
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
     * @param ContaoFramework $framework
     * @param string $projectDir
     */
    public function __construct(ContaoFramework $framework, string $projectDir)
    {
        $this->framework = $framework;
        $this->projectDir = $projectDir;

        // Initialize contao framework
        $this->framework->initialize();
    }

    /**
     * Anonymize orphaned entries in tl_calendar_events_member
     */
    public function anonymizeOrphanedCalendarEventsMemberDataRecords(): void
    {
        /** @var  CalendarEventsMemberModel $calendarEventsMemberModelAdapter */
        $calendarEventsMemberModelAdapter = $this->framework->getAdapter(CalendarEventsMemberModel::class);

        /** @var  MemberModel $memberModelAdapter */
        $memberModelAdapter = $this->framework->getAdapter(MemberModel::class);

        $objEventsMember = $calendarEventsMemberModelAdapter->findAll();
        while ($objEventsMember->next())
        {
            if ($objEventsMember->contaoMemberId > 0 || $objEventsMember->sacMemberId > 0)
            {
                $blnFound = false;
                if ($objEventsMember->contaoMemberId > 0)
                {
                    if ($memberModelAdapter->findByPk($objEventsMember->contaoMemberId) !== null)
                    {
                        $blnFound = true;
                    }
                }
                if ($objEventsMember->sacMemberId > 0)
                {
                    if ($memberModelAdapter->findBySacMemberId($objEventsMember->sacMemberId) !== null)
                    {
                        $blnFound = true;
                    }
                }
                if (!$blnFound)
                {
                    $this->anonymizeCalendarEventsMemberDataRecord($objEventsMember);
                }
            }
        }
    }

    /**
     * @param CalendarEventsMemberModel $objCalendarEventsMember
     * @return bool
     */
    public function anonymizeCalendarEventsMemberDataRecord(CalendarEventsMemberModel $objCalendarEventsMember): bool
    {

        /** @var  Date $dateAdapter */
        $dateAdapter = $this->framework->getAdapter(Date::class);

        if ($objCalendarEventsMember !== null)
        {
            if ($objCalendarEventsMember !== null)
            {
                if (!$objCalendarEventsMember->anonymized)
                {
                    // Log
                    $container = System::getContainer();
                    $logger = $container->get('monolog.logger.contao');
                    $logger->log(LogLevel::INFO, sprintf('Anonymized tl_calendar_events_member.id=%s. Firstname: %s, Lastname: %s (%s)"', $objCalendarEventsMember->id, $objCalendarEventsMember->firstname, $objCalendarEventsMember->lastname, $objCalendarEventsMember->sacMemberId), array('contao' => new ContaoContext(__FILE__ . ' Line: ' . __LINE__, 'ANONYMIZED_CALENDAR_EVENTS_MEMBER_DATA')));

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
                    $objCalendarEventsMember->notes = 'Benutzerdaten anonymisiert am ' . $dateAdapter->parse('d.m.Y', time());
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

    /**
     * @param int $memberId
     */
    public function disableLogin(int $memberId): void
    {
        /** @var  MemberModel $memberModelAdapter */
        $memberModelAdapter = $this->framework->getAdapter(MemberModel::class);

        /** @var  MemberModel $configAdapter */
        $configAdapter = $this->framework->getAdapter(Config::class);

        $objMember = $memberModelAdapter->findByPk($memberId);
        if ($objMember !== null)
        {
            // Log
            $container = System::getContainer();
            $logger = $container->get('monolog.logger.contao');
            $logger->log(LogLevel::INFO, sprintf('Login for member with ID:%s has been deactivated.', $objMember->id), array('contao' => new ContaoContext(__FILE__ . ' Line: ' . __LINE__, $configAdapter->get('DISABLE_MEMBER_LOGIN'))));

            $objMember->login = '';
            $objMember->password = '';
            $objMember->save();
        }
    }

    /**
     * @param int $memberId
     */
    public function deleteFrontendAccount(int $memberId): void
    {
        /** @var  MemberModel $memberModelAdapter */
        $memberModelAdapter = $this->framework->getAdapter(MemberModel::class);

        /** @var  MemberModel $configAdapter */
        $configAdapter = $this->framework->getAdapter(Config::class);

        $objMember = $memberModelAdapter->findByPk($memberId);
        if ($objMember !== null)
        {
            // Log
            $logger = System::getContainer()->get('monolog.logger.contao');
            $strText = sprintf('Member with ID:%s has been deleted.', $objMember->id);
            $logger->log(LogLevel::INFO, $strText, array('contao' => new ContaoContext(__METHOD__, $configAdapter->get('DELETE_MEMBER'))));

            $objMember->delete();
        }
    }

    /**
     * @param int $memberId
     * @param bool $blnForceClearing
     * @return bool
     * @throws \Exception
     */
    public function clearMemberProfile(int $memberId, bool $blnForceClearing = false): bool
    {
        /** @var  CalendarEventsMemberModel $calendarEventsMemberModelAdapter */
        $calendarEventsMemberModelAdapter = $this->framework->getAdapter(CalendarEventsMemberModel::class);

        /** @var  MemberModel $memberModelAdapter */
        $memberModelAdapter = $this->framework->getAdapter(MemberModel::class);

        /** @var  Message $messageAdapter */
        $messageAdapter = $this->framework->getAdapter(Message::class);

        /** @var  Date $dateAdapter */
        $dateAdapter = $this->framework->getAdapter(Date::class);

        /** @var  MemberModel $configAdapter */
        $configAdapter = $this->framework->getAdapter(Config::class);

        $arrEventsMember = array();
        $blnHasError = false;
        $objMember = $memberModelAdapter->findByPk($memberId);
        if ($objMember !== null)
        {
            // Upcoming events
            $arrEvents = $calendarEventsMemberModelAdapter->findUpcomingEventsByMemberId($objMember->id);
            foreach ($arrEvents as $arrEvent)
            {
                $objEventsMember = $calendarEventsMemberModelAdapter->findByPk($arrEvent['registrationId']);
                if ($objEventsMember !== null)
                {
                    if ($arrEvent['eventModel'] !== null)
                    {
                        $objEvent = $arrEvent['eventModel'];
                        if ($blnForceClearing)
                        {
                            continue;
                        }
                        elseif ($objEventsMember->stateOfSubscription === 'subscription-refused')
                        {
                            continue;
                        }
                        else
                        {
                            $arrErrorMsg[] = sprintf('Dein Profil kann nicht gelöscht werden, weil du beim Event "%s [%s]" vom %s auf der Buchungsliste stehst. Bitte melde dich zuerst vom Event ab oder nimm gegebenenfalls mit dem Leiter Kontakt auf.', $objEvent->title, $objEventsMember->stateOfSubscription, $dateAdapter->parse($configAdapter->get('dateFormat'), $objEvent->startDate));
                            $blnHasError = true;
                        }
                    }
                }
            }

            // Past events
            $arrEvents = $calendarEventsMemberModelAdapter->findPastEventsByMemberId($objMember->id);
            foreach ($arrEvents as $arrEvent)
            {
                $objEventsMember = $calendarEventsMemberModelAdapter->findByPk($arrEvent['registrationId']);

                if ($objEventsMember !== null)
                {
                    $arrEventsMember[] = $objEventsMember->id;
                }
            }

            if ($blnHasError)
            {
                foreach ($arrErrorMsg as $errorMsg)
                {
                    $messageAdapter->add($errorMsg, 'TL_ERROR', TL_MODE);
                }
                return false;
            }
            else
            {
                // Anonymize entries from tl_calendar_events_member
                foreach ($arrEventsMember as $eventsMemberId)
                {
                    $objEventsMember = $calendarEventsMemberModelAdapter->findByPk($eventsMemberId);
                    if ($objEventsMember !== null)
                    {
                        $this->anonymizeCalendarEventsMemberDataRecord($objEventsMember);
                    }
                }
                // Delete avatar directory
                $this->deleteAvatarDirectory($memberId);

                return true;
            }
        }
        return false;
    }

    /**
     * @param int $memberId
     * @throws \Exception
     */
    public function deleteAvatarDirectory(int $memberId): void
    {
        /** @var  MemberModel $configAdapter */
        $configAdapter = $this->framework->getAdapter(Config::class);

        $strAvatarDir = $configAdapter->get('SAC_EVT_FE_USER_AVATAR_DIRECTORY');
        if (is_dir($this->projectDir . '/' . $strAvatarDir . '/' . $memberId))
        {
            $strDir = $strAvatarDir . '/' . $memberId;
            $objDir = new Folder($strDir);
            if ($objDir !== null)
            {
                // Log
                $logger = System::getContainer()->get('monolog.logger.contao');
                $strText = sprintf('Deleted avatar directory "%s" for member with ID:%s.', $strDir, $memberId);
                $logger->log(LogLevel::INFO, $strText, array('contao' => new ContaoContext(__METHOD__, 'DELETED_AVATAR_DIRECTORY')));

                $objDir->purge();
                $objDir->delete();
            }
        }
    }

    /**
     * @param int $memberId
     * @return array
     */
    private function findUpcomingEventsByMemberId(int $memberId): array
    {
        /** @var  Database $databaseAdapter */
        $databaseAdapter = $this->framework->getAdapter(Database::class);

        /** @var  CalendarEventsModel $calendarEventsModelAdapter */
        $calendarEventsModelAdapter = $this->framework->getAdapter(CalendarEventsModel::class);

        /** @var  MemberModel $memberModelAdapter */
        $memberModelAdapter = $this->framework->getAdapter(MemberModel::class);

        /** @var  CalendarEventsMemberModel $calendarEventsMemberModelAdapter */
        $calendarEventsMemberModelAdapter = $this->framework->getAdapter(CalendarEventsMemberModel::class);

        $arrEvents = array();
        $objMember = $memberModelAdapter->findByPk($memberId);

        if ($objMember !== null)
        {
            $objEvents = $databaseAdapter->getInstance()->prepare('SELECT * FROM tl_calendar_events WHERE endDate>? ORDER BY startDate')->execute(time());
            while ($objEvents->next())
            {
                $objJoinedEvents = $databaseAdapter->getInstance()->prepare('SELECT * FROM tl_calendar_events_member WHERE sacMemberId=? AND eventId=?')->limit(1)->execute($objMember->sacMemberId, $objEvents->id);
                if ($objJoinedEvents->numRows)
                {
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
