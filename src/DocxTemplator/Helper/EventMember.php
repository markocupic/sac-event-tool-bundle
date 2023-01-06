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

namespace Markocupic\SacEventToolBundle\DocxTemplator\Helper;

use Contao\CalendarEventsModel;
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\Date;
use Contao\MemberModel;
use Contao\Model\Collection;
use Contao\UserModel;
use Markocupic\PhpOffice\PhpWord\MsWordTemplateProcessor;
use Markocupic\SacEventToolBundle\CalendarEventsHelper;
use Markocupic\SacEventToolBundle\Model\CalendarEventsMemberModel;

class EventMember
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
     * EventMember constructor.
     */
    public function __construct(ContaoFramework $framework, string $projectDir)
    {
        $this->framework = $framework;
        $this->projectDir = $projectDir;

        // Initialize contao framework
        $this->framework->initialize();
    }

    public function setEventMemberData(MsWordTemplateProcessor $objPhpWord, CalendarEventsModel $objEvent, Collection|null $objEventMember): void
    {
        // Set adapters
        /** @var UserModel $userModelAdapter */
        $userModelAdapter = $this->framework->getAdapter(UserModel::class);
        /** @var MemberModel $memberModelAdapter */
        $memberModelAdapter = $this->framework->getAdapter(MemberModel::class);
        /** @var $dateAdapter */
        $dateAdapter = $this->framework->getAdapter(Date::class);
        /** @var CalendarEventsHelper $calendarEventsHelperAdapter */
        $calendarEventsHelperAdapter = $this->framework->getAdapter(CalendarEventsHelper::class);

        $i = 0;

        // TL
        $arrInstructors = $calendarEventsHelperAdapter->getInstructorsAsArray($objEvent, false);

        if (!empty($arrInstructors) && \is_array($arrInstructors)) {
            foreach ($arrInstructors as $userId) {
                $objUserModel = $userModelAdapter->findByPk($userId);
                $strMemberInSection = '';

                if (null !== $objUserModel) {
                    // Check club membership
                    $isMember = false;
                    $objMember = $memberModelAdapter->findOneBySacMemberId($objUserModel->sacMemberId);

                    if (null !== $objMember) {
                        if ($objMember->isSacMember && !$objMember->disable) {
                            $isMember = true;
                        }
                        $strMemberInSection = '('.CalendarEventsHelper::getSectionMembershipAsString($objMember).')';
                    }
                    // Keep this var empty
                    $transportInfo = '';

                    // Phone
                    $mobile = '' !== $objUserModel->mobile ? $objUserModel->mobile : '----';

                    ++$i;

                    // Clone row
                    $objPhpWord->createClone('i');

                    // Push data to clone
                    $objPhpWord->addToClone('i', 'i', $i, ['multiline' => false]);
                    $objPhpWord->addToClone('i', 'role', 'TL', ['multiline' => false]);
                    $objPhpWord->addToClone('i', 'firstname', $this->prepareString((string) $objUserModel->firstname), ['multiline' => false]);
                    $objPhpWord->addToClone('i', 'lastname', $this->prepareString((string) $objUserModel->lastname), ['multiline' => false]);
                    $objPhpWord->addToClone('i', 'sacMemberId', 'Mitgl. No. '.$objUserModel->sacMemberId, ['multiline' => false]);
                    $objPhpWord->addToClone('i', 'memberInSection', $strMemberInSection, ['multiline' => false]);
                    $objPhpWord->addToClone('i', 'isNotSacMember', $isMember ? ' ' : '!inaktiv/kein Mitglied', ['multiline' => false]);
                    $objPhpWord->addToClone('i', 'street', $this->prepareString((string) $objUserModel->street), ['multiline' => false]);
                    $objPhpWord->addToClone('i', 'postal', $this->prepareString((string) $objUserModel->postal), ['multiline' => false]);
                    $objPhpWord->addToClone('i', 'city', $this->prepareString((string) $objUserModel->city), ['multiline' => false]);

                    // Fallback for emergency phone & -name
                    $emergencyPhone = $objUserModel->emergencyPhone;

                    if (empty($emergencyPhone) && null !== $objMember) {
                        $emergencyPhone = $objMember->emergencyPhone;
                    }

                    $emergencyPhoneName = $objUserModel->emergencyPhoneName;

                    if (empty($emergencyPhoneName) && null !== $objMember) {
                        $emergencyPhoneName = $objMember->emergencyPhoneName;
                    }

                    $objPhpWord->addToClone('i', 'emergencyPhone', $this->prepareString((string) $emergencyPhone), ['multiline' => false]);
                    $objPhpWord->addToClone('i', 'emergencyPhoneName', $this->prepareString((string) $emergencyPhoneName), ['multiline' => false]);
                    $objPhpWord->addToClone('i', 'mobile', $this->prepareString($mobile), ['multiline' => false]);
                    $objPhpWord->addToClone('i', 'email', $this->prepareString($objUserModel->email), ['multiline' => false]);
                    $objPhpWord->addToClone('i', 'transportInfo', $this->prepareString($transportInfo), ['multiline' => false]);
                    $objPhpWord->addToClone('i', 'dateOfBirth', '' !== $objUserModel->dateOfBirth ? $dateAdapter->parse('Y', $objUserModel->dateOfBirth) : '', ['multiline' => false]);
                }
            }
        }

        // TN
        if (null !== $objEventMember) {
            while ($objEventMember->next()) {
                ++$i;

                // Check club membership
                $strIsActiveMember = '!inaktiv/keinMitglied';
                $strMemberInSection = '';

                if (!empty($objEventMember->sacMemberId)) {
                    $objMemberModel = $memberModelAdapter->findOneBySacMemberId($objEventMember->sacMemberId);

                    if (null !== $objMemberModel) {
                        if ($objMemberModel->isSacMember && !$objMemberModel->disable) {
                            $strIsActiveMember = ' ';
                        }
                        $strMemberInSection = '('.CalendarEventsHelper::getSectionMembershipAsString($objMemberModel).')';
                    }
                }

                $transportInfo = '';

                if (\strlen($objEventMember->carInfo)) {
                    if ((int) $objEventMember->carInfo > 0) {
                        $transportInfo .= sprintf(' Auto mit %s Plätzen', $objEventMember->carInfo);
                    }
                }

                // GA, Halbtax, Tageskarte
                if (\strlen($objEventMember->ticketInfo)) {
                    $transportInfo .= sprintf(' Ticket: Mit %s', $objEventMember->ticketInfo);
                }

                // Phone
                $mobile = '' !== $objEventMember->mobile ? $objEventMember->mobile : '----';
                // Clone row
                $objPhpWord->createClone('i');

                // Push data to clone
                $objPhpWord->addToClone('i', 'i', $i, ['multiline' => false]);
                $objPhpWord->addToClone('i', 'role', 'TN', ['multiline' => false]);
                $objPhpWord->addToClone('i', 'firstname', $this->prepareString((string) $objEventMember->firstname), ['multiline' => false]);
                $objPhpWord->addToClone('i', 'lastname', $this->prepareString((string) $objEventMember->lastname), ['multiline' => false]);
                $objPhpWord->addToClone('i', 'sacMemberId', 'Mitgl. No. '.$objEventMember->sacMemberId, ['multiline' => false]);
                $objPhpWord->addToClone('i', 'memberInSection', $strMemberInSection, ['multiline' => false]);
                $objPhpWord->addToClone('i', 'isNotSacMember', $strIsActiveMember, ['multiline' => false]);
                $objPhpWord->addToClone('i', 'street', $this->prepareString((string) $objEventMember->street), ['multiline' => false]);
                $objPhpWord->addToClone('i', 'postal', $this->prepareString((string) $objEventMember->postal), ['multiline' => false]);
                $objPhpWord->addToClone('i', 'city', $this->prepareString((string) $objEventMember->city), ['multiline' => false]);
                $objPhpWord->addToClone('i', 'mobile', $this->prepareString($mobile), ['multiline' => false]);
                $objPhpWord->addToClone('i', 'emergencyPhone', $this->prepareString((string) $objEventMember->emergencyPhone), ['multiline' => false]);
                $objPhpWord->addToClone('i', 'emergencyPhoneName', $this->prepareString((string) $objEventMember->emergencyPhoneName), ['multiline' => false]);
                $objPhpWord->addToClone('i', 'email', $this->prepareString((string) $objEventMember->email), ['multiline' => false]);
                $objPhpWord->addToClone('i', 'transportInfo', $this->prepareString($transportInfo), ['multiline' => false]);
                $objPhpWord->addToClone('i', 'dateOfBirth', '' !== $objEventMember->dateOfBirth ? $dateAdapter->parse('Y', $objEventMember->dateOfBirth) : '', ['multiline' => false]);
            }
        }

        // Event instructors
        $aInstructors = $calendarEventsHelperAdapter->getInstructorsAsArray($objEvent, false);

        $arrInstructors = array_map(
            function ($id) {
                $userModelAdapter = $this->framework->getAdapter(UserModel::class);

                $objUser = $userModelAdapter->findByPk($id);

                if (null !== $objUser) {
                    return $objUser->name;
                }
            },
            $aInstructors
        );
        $objPhpWord->replace('eventInstructors', $this->prepareString(implode(', ', $arrInstructors)));

        // Event Id
        $objPhpWord->replace('eventId', $objEvent->id);
    }

    /**
     * @param $objEvent
     */
    public function getParticipatedEventMembers($objEvent): Collection|null
    {
        /** @var CalendarEventsMemberModel $calendarEventsMemberModelAdapter */
        $calendarEventsMemberModelAdapter = $this->framework->getAdapter(CalendarEventsMemberModel::class);

        return $calendarEventsMemberModelAdapter->findBy(
            ['tl_calendar_events_member.eventId=?', 'tl_calendar_events_member.hasParticipated=?'],
            [$objEvent->id, '1']
        );
    }

    protected function prepareString(string $string = ''): string
    {
        if (null === $string) {
            return '';
        }

        return htmlspecialchars(html_entity_decode((string) $string));
    }
}
