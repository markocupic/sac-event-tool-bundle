<?php

declare(strict_types=1);

/*
 * This file is part of SAC Event Tool Bundle.
 *
 * (c) Marko Cupic 2022 <m.cupic@gmx.ch>
 * @license GPL-3.0-or-later
 * For the full copyright and license information,
 * please view the LICENSE file that was distributed with this source code.
 * @link https://github.com/markocupic/sac-event-tool-bundle
 */

namespace Markocupic\SacEventToolBundle\DataContainer;

use Contao\Backend;
use Contao\BackendTemplate;
use Contao\BackendUser;
use Contao\CalendarEventsMemberModel;
use Contao\CalendarEventsModel;
use Contao\Config;
use Contao\Controller;
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\Database;
use Contao\DataContainer;
use Contao\Date;
use Contao\Environment;
use Contao\EventReleaseLevelPolicyModel;
use Contao\Events;
use Contao\FilesModel;
use Contao\Input;
use Contao\MemberModel;
use Contao\Message;
use Contao\StringUtil;
use Contao\System;
use Contao\UserModel;
use Contao\Validator;
use Doctrine\DBAL\Connection;
use Haste\Form\Form;
use League\Csv\CharsetConverter;
use League\Csv\Writer;
use Markocupic\SacEventToolBundle\CalendarEventsHelper;
use Markocupic\SacEventToolBundle\Config\Bundle;
use Markocupic\SacEventToolBundle\Config\EventSubscriptionLevel;
use NotificationCenter\Model\Notification;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Security\Core\Security;
use Symfony\Contracts\Translation\TranslatorInterface;
use Contao\CoreBundle\ServiceAnnotation\Callback;


class CalendarEventsMember extends Backend
{
    private ContaoFramework $framework;
    private RequestStack $requestStack;
    private Connection $connection;
    private Util $util;
    private TranslatorInterface $translator;
    private Security $security;
    private string $eventAdminName;
    private string $eventAdminEmail;
    private string $locale;

    public function __construct(ContaoFramework $framework, RequestStack $requestStack, Connection $connection, Util $util, TranslatorInterface $translator, Security $security, string $eventAdminName, string $eventAdminEmail, string $locale)
    {
        $this->framework = $framework;
        $this->requestStack = $requestStack;
        $this->connection = $connection;
        $this->util = $util;
        $this->translator = $translator;
        $this->security = $security;
        $this->eventAdminName = $eventAdminName;
        $this->eventAdminEmail = $eventAdminEmail;
        $this->locale = $locale;
    }

    /**
     * Set correct referer.
     *
     * @Callback(table="tl_calendar_events_member", target="config.onload", priority=100)
     */
    public function setCorrectReferer(): void
    {
        $this->util->setCorrectReferer();
    }

    /**
     * Load backend assets.
     *
     * @Callback(table="tl_calendar_events_member", target="config.onload", priority=100)
     */
    public function loadBackendAssets(): void
    {
        $request = $this->requestStack->getCurrentRequest();

        if ('sac_calendar_events_tool' === $request->query->get('do') && '' !== $request->query->get('ref')) {
            $GLOBALS['TL_JAVASCRIPT'][] = Bundle::ASSET_DIR.'/js/backend_member_autocomplete.js';
        }
    }

    /**
     * Show or hide the "send email" button in the global operations section.
     *
     * @Callback(table="tl_calendar_events_member", target="config.onload", priority=100)
     */
    public function showSendEmailButton(DataContainer $dc): void
    {
        $request = $this->requestStack->getCurrentRequest();

        $eventId = $dc->id;

        if (!$eventId || $request->query->has('act')) {
            return;
        }

        // Do only show email buttons in the global operations section
        // if there are registrations
        $regId = $this->connection->fetchOne('SELECT id FROM tl_calendar_events_member WHERE eventId = ?', [$eventId]);

        if (!$regId) {
            unset($GLOBALS['TL_DCA']['tl_calendar_events_member']['list']['global_operations']['sendEmail']);
        } else {
            $GLOBALS['TL_DCA']['tl_calendar_events_member']['list']['global_operations']['sendEmail']['href'] = str_replace('sendEmail', 'sendEmail&id='.$regId.'&eventId='.$eventId, $GLOBALS['TL_DCA']['tl_calendar_events_member']['list']['global_operations']['sendEmail']['href']);
        }
    }

    /**
     * Reset email fields.
     *
     * @Callback(table="tl_calendar_events_member", target="config.onload", priority=100)
     */
    public function resetEmailFields(DataContainer $dc): void
    {
        if (!$dc->id) {
            return;
        }

        $request = $this->requestStack->getCurrentRequest();

        if ('sendEmail' === $request->query->get('call')) {
            // Delete E-Mail fields
            $set = [
                'emailRecipients' => '',
                'emailSubject' => '',
                'emailText' => '',
                'emailSendCopy' => '',
                'addEmailAttachment' => '',
                'emailAttachment' => '',
            ];

            $this->connection->update('tl_calendar_events_member', $set, ['id' => $dc->id]);
        }
    }

    /**
     * Reset email fields.
     *
     * @Callback(table="tl_calendar_events_member", target="config.onload", priority=100)
     */
    public function setPermission(DataContainer $dc): void
    {
        $user = $this->security->getUser();

        $request = $this->requestStack->getCurrentRequest();

        // Allow full access only to admins, owners and allowed groups
        if ($user->isAdmin) {
            // Allow
        } elseif (EventReleaseLevelPolicyModel::hasWritePermission($user->id, CURRENT_ID)) {
            // User is allowed to edit table
        } else {
            if (!$request->query->get('act') && $request->query->get('id')) {
                $objEvent = CalendarEventsModel::findByPk($request->query->get('id'));

                if (null !== $objEvent) {
                    $arrAuthors = StringUtil::deserialize($objEvent->author, true);
                    $arrRegistrationGoesTo = StringUtil::deserialize($objEvent->registrationGoesTo, true);

                    if (!\in_array($user->id, $arrAuthors, false) && !\in_array($user->id, $arrRegistrationGoesTo, true)) {
                        $GLOBALS['TL_DCA']['tl_calendar_events_member']['config']['closed'] = true;
                        $GLOBALS['TL_DCA']['tl_calendar_events_member']['config']['notCreatable'] = true;
                        $GLOBALS['TL_DCA']['tl_calendar_events_member']['config']['notEditable'] = true;
                        $GLOBALS['TL_DCA']['tl_calendar_events_member']['config']['notDeletable'] = true;
                        $GLOBALS['TL_DCA']['tl_calendar_events_member']['config']['notCopyable'] = true;

                        unset($GLOBALS['TL_DCA']['tl_calendar_events_member']['list']['global_operations']['all'], $GLOBALS['TL_DCA']['tl_calendar_events_member']['list']['global_operations']['downloadEventMemberList'], $GLOBALS['TL_DCA']['tl_calendar_events_member']['list']['global_operations']['sendEmail'], $GLOBALS['TL_DCA']['tl_calendar_events_member']['list']['operations']['edit'], $GLOBALS['TL_DCA']['tl_calendar_events_member']['list']['operations']['delete'], $GLOBALS['TL_DCA']['tl_calendar_events_member']['list']['operations']['toggleStateOfParticipation']);
                    }
                }
            }

            if ('delete' === $request->query->get('act') || 'toggle' === $request->query->get('act') || 'edit' === $request->query->get('act') || 'select' === $request->query->get('act')) {
                $id = \strlen((string) $request->query->get('id')) ? $request->query->get('id') : CURRENT_ID;

                if ('select' === $request->query->get('act')) {
                    $objEvent = CalendarEventsModel::findByPk($id);
                } else {
                    /** @var CalendarEventsMemberModel $objEvent */
                    if (null !== ($objMember = CalendarEventsMemberModel::findByPk($id))) {
                        /** @var CalendarEventsModel $objEvent */
                        $objEvent = $objMember->getRelated('eventId');
                    }
                }

                if (null !== $objEvent) {
                    $arrAuthors = StringUtil::deserialize($objEvent->author, true);
                    $arrRegistrationGoesTo = StringUtil::deserialize($objEvent->registrationGoesTo, true);

                    if (!\in_array($user->id, $arrAuthors, false) && !\in_array($user->id, $arrRegistrationGoesTo, true)) {
                        $GLOBALS['TL_DCA']['tl_calendar_events_member']['config']['closed'] = true;
                        $GLOBALS['TL_DCA']['tl_calendar_events_member']['config']['notCreatable'] = true;
                        $GLOBALS['TL_DCA']['tl_calendar_events_member']['config']['notEditable'] = true;
                        $GLOBALS['TL_DCA']['tl_calendar_events_member']['config']['notDeletable'] = true;
                        $GLOBALS['TL_DCA']['tl_calendar_events_member']['config']['notCopyable'] = true;

                        Message::addError($this->translator->trans('ERR.accessDenied', [], 'contao_default'));
                        Controller::redirect('contao/main.php?do=sac_calendar_events_tool&table=tl_calendar_events_member&id='.$objEvent->id);
                    }
                }
            }
        }

        // Download the registration list as a docx file
        if ('downloadEventMemberList' === $request->query->get('act')) {
            $objMemberList = System::getContainer()->get('Markocupic\SacEventToolBundle\DocxTemplator\EventMemberList2Docx');
            /** @var CalendarEventsModel $objEvent */
            $objEvent = CalendarEventsModel::findByPk($request->query->get('id'));
            $objMemberList->generate($objEvent, 'docx');
            exit;
        }

        if ('sendEmail' === $request->query->get('call')) {
            // Set Recipient Array for the checkbox list
            $GLOBALS['TL_DCA']['tl_calendar_events_member']['palettes']['default'] = $GLOBALS['TL_DCA']['tl_calendar_events_member']['palettes']['sendEmail'];
            $options = [];

            /**
             * Get the event instructor.
             *
             * @var CalendarEventsModel $objEvent
             */
            $objEvent = CalendarEventsModel::findByPk($request->query->get('eventId'));

            if (null !== $objEvent) {
                $arrGuideIDS = CalendarEventsHelper::getInstructorsAsArray($objEvent, false);

                foreach ($arrGuideIDS as $userId) {
                    /** @var UserModel $objInstructor */
                    $objInstructor = UserModel::findByPk($userId);

                    if (null !== $objInstructor) {
                        if ('' !== $objInstructor->email) {
                            if (Validator::isEmail($objInstructor->email)) {
                                $options['tl_user-'.$objInstructor->id] = $objInstructor->firstname.' '.$objInstructor->lastname.' (Leiter)';
                            }
                        }
                    }
                }
            }

            // Then get event participants
            $objDb = Database::getInstance()->prepare('SELECT * FROM tl_calendar_events_member WHERE eventId=? ORDER BY stateOfSubscription, firstname')->execute(Input::get('eventId'));

            while ($objDb->next()) {
                if (Validator::isEmail($objDb->email)) {
                    $arrSubscriptionStates = $GLOBALS['TL_CONFIG']['SAC-EVENT-TOOL-CONFIG']['MEMBER-SUBSCRIPTION-STATE'];

                    if (empty($arrSubscriptionStates) || !\is_array($arrSubscriptionStates)) {
                        throw new \Exception('$GLOBALS["TL_CONFIG"]["SAC-EVENT-TOOL-CONFIG"]["MEMBER-SUBSCRIPTION-STATE"] not found. Please check the config file.');
                    }

                    $memberState = (string) $objDb->stateOfSubscription;
                    $memberState = \in_array($memberState, $arrSubscriptionStates, true) ? $memberState : EventSubscriptionLevel::SUBSCRIPTION_STATE_UNDEFINED;
                    $strLabel = $GLOBALS['TL_LANG']['tl_calendar_events_member'][$memberState] ?? $memberState;
                    $options['tl_calendar_events_member-'.$objDb->id] = $objDb->firstname.' '.$objDb->lastname.' ('.$strLabel.')';
                }
            }

            $GLOBALS['TL_DCA']['tl_calendar_events_member']['fields']['emailRecipients']['options'] = $options;

            // Send E-Mail
            if ('tl_calendar_events_member' === Input::post('FORM_SUBMIT') && isset($_POST['saveNclose'])) {
                $arrRecipients = [];

                foreach (Input::post('emailRecipients') as $key) {
                    if (false !== strpos($key, 'tl_user-')) {
                        $id = str_replace('tl_user-', '', $key);
                        $objInstructor = UserModel::findByPk($id);

                        if (null !== $objInstructor) {
                            if (Validator::isEmail($objInstructor->email)) {
                                $arrRecipients[] = $objInstructor->email;
                            }
                        }
                    } elseif (false !== strpos($key, 'tl_calendar_events_member-')) {
                        $id = str_replace('tl_calendar_events_member-', '', $key);
                        $objEventMember = CalendarEventsMemberModel::findByPk($id);

                        if (null !== $objEventMember) {
                            if (Validator::isEmail($objEventMember->email)) {
                                $arrRecipients[] = $objEventMember->email;
                            }
                        }
                    }
                }

                // Send e-mail
                if (!Validator::isEmail($this->eventAdminEmail)) {
                    throw new \Exception('Please set a valid email address in parameter sacevt.event_admin_email.');
                }

                $objEmail = Notification::findOneByType('default_email');

                // Use terminal42/notification_center
                if (null !== $objEmail) {
                    // Set token array
                    $arrTokens = [
                        'email_sender_name' => html_entity_decode($this->eventAdminName),
                        'email_sender_email' => $this->eventAdminEmail,
                        'reply_to' => $user->email,
                        'email_subject' => html_entity_decode((string) Input::post('emailSubject')),
                        'email_text' => html_entity_decode(strip_tags((string) Input::post('emailText'))),
                        'attachment_tokens' => null,
                        'recipient_cc' => null,
                        'recipient_bcc' => null,
                        'email_html' => null,
                    ];

                    if (Input::post('emailSendCopy')) {
                        $arrTokens['recipient_bcc'] = $user->email;
                    }

                    $arrFiles = [];

                    // Add attachment
                    if (Input::post('addEmailAttachment')) {
                        if ('' !== Input::post('emailAttachment')) {
                            $arrUUID = explode(',', Input::post('emailAttachment'));

                            if (!empty($arrUUID) && \is_array($arrUUID)) {
                                foreach ($arrUUID as $uuid) {
                                    $objFile = FilesModel::findByUuid($uuid);

                                    if (null !== $objFile) {
                                        if (is_file(TL_ROOT.'/'.$objFile->path)) {
                                            $arrFiles[] = $objFile->path;
                                        }
                                    }
                                }
                            }
                        }
                    }

                    $strAttachments = implode(',', $arrFiles);

                    if ('' !== $strAttachments) {
                        $arrTokens['attachment_tokens'] = $strAttachments;
                    }

                    $arrRecipients = array_unique($arrRecipients);

                    if (\count($arrRecipients) > 0) {
                        $arrTokens['send_to'] = implode(',', $arrRecipients);
                        $objEmail->send($arrTokens, $this->locale);
                    }
                }
            }
        }
    }

    /**
     * Export registration list as CSV file.
     *
     * @Callback(table="tl_calendar_events_member", target="config.onload", priority=100)
     */
    public function exportMemberList(DataContainer $dc): void
    {
        if ('onloadCallbackExportMemberlist' === Input::get('action') && Input::get('id') > 0) {
            // Create empty document
            $csv = Writer::createFromString('');

            // Set encoding from utf-8 to iso-8859-15 (windows)
            $encoder = (new CharsetConverter())
                ->outputEncoding('iso-8859-15')
            ;
            $csv->addFormatter($encoder);

            // Set delimiter
            $csv->setDelimiter(';');

            // Selected fields
            $arrFields = ['id', 'stateOfSubscription', 'addedOn', 'carInfo', 'ticketInfo', 'notes', 'instructorNotes', 'bookingType', 'sacMemberId', 'ahvNumber', 'firstname', 'lastname', 'gender', 'dateOfBirth', 'foodHabits', 'street', 'postal', 'city', 'mobile', 'email', 'emergencyPhone', 'emergencyPhoneName', 'hasParticipated'];

            // Insert headline first
            Controller::loadLanguageFile('tl_calendar_events_member');

            $arrHeadline = array_map(
                static fn ($field) => $GLOBALS['TL_LANG']['tl_calendar_events_member'][$field][0] ?? $field,
                $arrFields
            );
            $csv->insertOne($arrHeadline);

            $objEvent = CalendarEventsModel::findByPk(Input::get('id'));
            $objEventMember = Database::getInstance()
                ->prepare('SELECT * FROM tl_calendar_events_member WHERE eventId=? ORDER BY lastname, firstname')
                ->execute(Input::get('id'))
            ;

            while ($objEventMember->next()) {
                $arrRow = [];

                foreach ($arrFields as $field) {
                    $value = html_entity_decode((string) $objEventMember->{$field});

                    if ('stateOfSubscription' === $field) {
                        $arrRow[] = '' !== $GLOBALS['TL_LANG']['tl_calendar_events_member'][$value] ? $GLOBALS['TL_LANG']['tl_calendar_events_member'][$value] : $value;
                    } elseif ('gender' === $field) {
                        $arrRow[] = '' !== $GLOBALS['TL_LANG']['MSC'][$value] ? $GLOBALS['TL_LANG']['MSC'][$value] : $value;
                    } elseif ('addedOn' === $field) {
                        $arrRow[] = Date::parse(Config::get('datimFormat'), $value);
                    } elseif ('dateOfBirth' === $field) {
                        $arrRow[] = Date::parse(Config::get('dateFormat'), $value);
                    } else {
                        $arrRow[] = $value;
                    }
                }
                $csv->insertOne($arrRow);
            }

            // Sanitize filename
            $filename = $name = preg_replace('/[^a-zA-Z0-9_-]+/', '_', strtolower($objEvent->title)).'.csv';

            $csv->output($filename);
            exit;
        }
    }

    /**
     * Delete orphaned records.
     *
     * @Callback(table="tl_calendar_events_member", target="config.onload", priority=100)
     */
    public function reviseTable(): void
    {
        $reload = false;

        // Delete orphaned records
        $objStmt = Database::getInstance()->prepare('SELECT id FROM tl_calendar_events_member AS em WHERE em.sacMemberId > ? AND em.tstamp > ? AND NOT EXISTS (SELECT * FROM tl_member AS m WHERE em.sacMemberId = m.sacMemberId)')->execute(0, 0);

        if ($objStmt->numRows) {
            $arrIDS = $objStmt->fetchEach('id');
            $objStmt2 = Database::getInstance()->execute('DELETE FROM tl_calendar_events_member WHERE id IN('.implode(',', $arrIDS).')');

            if ($objStmt2->affectedRows > 0) {
                $reload = true;
            }
        }

        // Delete event members without sacMemberId that are not related to an event
        $objStmt = Database::getInstance()->prepare('SELECT id FROM tl_calendar_events_member AS em WHERE (em.sacMemberId < ? OR em.sacMemberId = ?) AND tstamp > ? AND NOT EXISTS (SELECT * FROM tl_calendar_events AS e WHERE em.eventId = e.id)')->execute(1, '', 0);

        if ($objStmt->numRows) {
            $arrIDS = $objStmt->fetchEach('id');
            $objStmt2 = Database::getInstance()->execute('DELETE FROM tl_calendar_events_member WHERE id IN('.implode(',', $arrIDS).')');

            if ($objStmt2->affectedRows > 0) {
                $reload = true;
            }
        }

        if ($reload) {
            Controller::reload();
        }
    }

    /**
     * List SAC sections.
     *
     * @Callback(table="tl_calendar_events_member", target="fields.sectionIds.options")
     */
    public function listSections(): array
    {
        $arrOptions = [];

        $objDb = Database::getInstance()->execute('SELECT * FROM tl_sac_section');

        while ($objDb->next()) {
            $arrOptions[$objDb->sectionId] = $objDb->name;
        }

        return $arrOptions;
    }

    /**
     * @Callback(table="tl_calendar_events_member", target="config.onload", priority=100)
     *
     * ???This method is used when an instructor signs in a member manually.
     */
    public function setContaoMemberIdFromSacMemberId(DataContainer $dc): void
    {
        $objDb = Database::getInstance()->prepare('SELECT * FROM tl_calendar_events_member WHERE tl_calendar_events_member.contaoMemberId < ? AND tl_calendar_events_member.sacMemberId > ? AND tl_calendar_events_member.sacMemberId IN (SELECT sacMemberId FROM tl_member)')->execute(1, 0);

        while ($objDb->next()) {
            $objMemberModel = MemberModel::findOneBySacMemberId($objDb->sacMemberId);

            if (null !== $objMemberModel) {
                $set = [
                    'contaoMemberId' => $objMemberModel->id,
                    'gender' => $objMemberModel->gender,
                    'firstname' => $objMemberModel->firstname,
                    'lastname' => $objMemberModel->lastname,
                    'street' => $objMemberModel->street,
                    'email' => $objMemberModel->email,
                    'postal' => $objMemberModel->postal,
                    'city' => $objMemberModel->city,
                    'mobile' => $objMemberModel->mobile,
                ];
                Database::getInstance()->prepare('UPDATE tl_calendar_events_member %s WHERE id=?')->set($set)->execute($objDb->id);
            }
        }
    }

    /**
     * Set correct sac member and contao member id.
     *
     * @Callback(table="tl_calendar_events_member", target="fields.sacMemberId.save")
     *
     * @param $varValue
     */
    public function setCorrectSacAndContaoMemberId($varValue, DataContainer $dc)
    {
        // Set correct contaoMemberId if there is a sacMemberId
        $objEventMemberModel = CalendarEventsMemberModel::findByPk($dc->id);

        if (null !== $objEventMemberModel) {
            if ('' !== $varValue) {
                $objMemberModel = MemberModel::findOneBySacMemberId($varValue);

                if (null !== $objMemberModel) {
                    $set = [
                        'contaoMemberId' => (int) $objMemberModel->id,
                    ];
                    Database::getInstance()->prepare('UPDATE tl_calendar_events_member %s WHERE id=?')->set($set)->execute($dc->id);
                } else {
                    $varValue = '';
                    $set = [
                        'sacMemberId' => '',
                        'contaoMemberId' => 0,
                    ];
                    Database::getInstance()->prepare('UPDATE tl_calendar_events_member %s WHERE id=?')->set($set)->execute($dc->id);
                }
            } else {
                $set = [
                    'sacMemberId' => '',
                    'contaoMemberId' => 0,
                ];
                Database::getInstance()->prepare('UPDATE tl_calendar_events_member %s WHERE id=?')->set($set)->execute($dc->id);
            }
        }

        return $varValue;
    }

    /**
     * @Callback(table="tl_calendar_events_member", target="fields.stateOfSubscription.save")
     *
     * @param $varValue
     *
     * @return mixed|string|null
     */
    public function saveCallbackStateOfSubscription($varValue, DataContainer $dc)
    {
        $objEventMemberModel = CalendarEventsMemberModel::findByPk($dc->id);

        if (null !== $objEventMemberModel) {
            // Retrieve the event id from CURRENT_ID if we have a new entry.
            $eventId = !empty($objEventMemberModel->eventId) ? $objEventMemberModel->eventId : CURRENT_ID;
            $objEvent = CalendarEventsModel::findByPk($eventId);

            if (null !== $objEvent && $objEventMemberModel->stateOfSubscription !== $varValue) {
                // Check if member has already booked at the same time
                $objMember = MemberModel::findOneBySacMemberId($objEventMemberModel->sacMemberId);

                // Do not allow the maximum number of participants to be exceeded.
                if (EventSubscriptionLevel::SUBSCRIPTION_ACCEPTED === $varValue) {
                    if (!CalendarEventsMemberModel::canAcceptSubscription($objEventMemberModel, $objEvent)) {
                        $varValue = EventSubscriptionLevel::SUBSCRIPTION_WAITLISTED;
                    }
                }

                if (EventSubscriptionLevel::SUBSCRIPTION_ACCEPTED === $varValue && null !== $objMember && !$objEventMemberModel->allowMultiSignUp && CalendarEventsHelper::areBookingDatesOccupied($objEvent, $objMember)) {
                    $_SESSION['addError'] = 'Es ist ein Fehler aufgetreten. Der Teilnehmer kann nicht angemeldet werden, weil er zu dieser Zeit bereits an einem anderen Event bestätigt wurde. Wenn Sie das trotzdem erlauben möchten, dann setzen Sie das Flag "Mehrfachbuchung zulassen".';
                    $varValue = $objEventMemberModel->stateOfSubscription;
                } elseif (Validator::isEmail($objEventMemberModel->email)) {
                    // Use terminal42/notification_center
                    $objNotification = Notification::findOneByType('onchange_state_of_subscription');

                    if (null !== $objNotification) {
                        $arrTokens = [
                            'participant_state_of_subscription' => html_entity_decode((string) $GLOBALS['TL_LANG']['tl_calendar_events_member'][$varValue]),
                            'event_name' => html_entity_decode($objEvent->title),
                            'participant_uuid' => $objEventMemberModel->uuid,
                            'participant_name' => html_entity_decode($objEventMemberModel->firstname.' '.$objEventMemberModel->lastname),
                            'participant_email' => $objEventMemberModel->email,
                            'event_link_detail' => 'https://'.Environment::get('host').'/'.Events::generateEventUrl($objEvent),
                        ];
                        $objNotification->send($arrTokens, $this->locale);
                    }
                }
            }
        }

        return $varValue;
    }

    /**
     * @Callback(table="tl_calendar_events_member", target="config.onsubmit")
     */
    public function onsubmitCallback(DataContainer $dc): void
    {
        // Set correct contaoMemberId if there is a sacMemberId
        $objEventMemberModel = CalendarEventsMemberModel::findByPk($dc->id);

        if (null !== $objEventMemberModel) {
            // Set correct addedOn timestamp
            if (!$objEventMemberModel->addedOn) {
                $set = [
                    'addedOn' => time(),
                ];

                Database::getInstance()->prepare('UPDATE tl_calendar_events_member %s WHERE id=?')->set($set)->execute($dc->id);
            }

            $eventId = $objEventMemberModel->eventId > 0 ? $objEventMemberModel->eventId : CURRENT_ID;

            $objEventModel = CalendarEventsModel::findByPk($eventId);

            if (null !== $objEventModel) {
                // Set correct event title and eventId
                $set = [
                    'eventName' => $objEventModel->title,
                    'eventId' => $eventId,
                ];
                Database::getInstance()->prepare('UPDATE tl_calendar_events_member %s WHERE id=?')->set($set)->execute($dc->id);
            }
        }
    }

    /**
     * @Callback(table="tl_calendar_events_member", target="config.onload")
     */
    public function setStateOfSubscription(DataContainer $dc): void
    {
        $request = $this->requestStack->getCurrentRequest();

        if (!$dc->id || !$request->query->has('act')) {
            return;
        }

        $objEventMemberModel = CalendarEventsMemberModel::findByPk($dc->id);

        if (null === $objEventMemberModel) {
            throw new \Exception(sprintf('Registration with ID %s not found.', $dc->id));
        }

        // start session
        session_start();

        if ('refuseWithEmail' === Input::get('call')) {
            // Show another palette
            $GLOBALS['TL_DCA']['tl_calendar_events_member']['palettes']['default'] = $GLOBALS['TL_DCA']['tl_calendar_events_member']['palettes']['refuseWithEmail'];

            return;
        }

        if ('acceptWithEmail' === Input::get('call')) {
            // Show another palette
            $GLOBALS['TL_DCA']['tl_calendar_events_member']['palettes']['default'] = $GLOBALS['TL_DCA']['tl_calendar_events_member']['palettes']['acceptWithEmail'];

            return;
        }

        if ('addToWaitlist' === Input::get('call')) {
            // Show another palette
            $GLOBALS['TL_DCA']['tl_calendar_events_member']['palettes']['default'] = $GLOBALS['TL_DCA']['tl_calendar_events_member']['palettes']['addToWaitlist'];

            return;
        }

        if (isset($_SESSION['addError'])) {
            Message::addError($_SESSION['addError']);
            unset($_SESSION['addError']);
        }

        if (isset($_SESSION['addInfo'])) {
            Message::addConfirmation($_SESSION['addInfo']);
            unset($_SESSION['addInfo']);
        }

        if (isset($_POST['refuseWithEmail'])) {
            // Show another palette
            Controller::redirect(Backend::addToUrl('call=refuseWithEmail'));
        }

        if (isset($_POST['acceptWithEmail'])) {
            $blnAllow = true;

            $objEvent = CalendarEventsModel::findByPk($objEventMemberModel->eventId);

            if (null !== $objEventMemberModel && null !== $objEvent && !CalendarEventsMemberModel::canAcceptSubscription($objEventMemberModel, $objEvent)) {
                $blnAllow = false;
            }

            if ($blnAllow) {
                // Show another palette
                Controller::redirect(Backend::addToUrl('call=acceptWithEmail'));
            } else {
                $_SESSION['addError'] = 'Dem Teilnehmer kann die Teilnahme am Event nicht bestätigt werden, da die maximale Teilnehmerzahl bereits erreicht wurde.';
            }
        }

        if (isset($_POST['addToWaitlist'])) {
            // Show another palette
            Controller::redirect(Backend::addToUrl('call=addToWaitlist'));
        }

        if (isset($_POST['refuseWithoutEmail'])) {
            $objEventMemberModel = CalendarEventsMemberModel::findByPk(Input::get('id'));

            if (null !== $objEventMemberModel) {
                $set = ['stateOfSubscription' => EventSubscriptionLevel::SUBSCRIPTION_REFUSED];
                Database::getInstance()->prepare('UPDATE tl_calendar_events_member %s WHERE id=?')->set($set)->execute(Input::get('id'));
                $_SESSION['addInfo'] = 'Dem Benutzer wurde ohne E-Mail die Teilnahme am Event verweigert. Er muss jedoch noch manuell darüber informiert werden.';
            }

            Controller::reload();
        }

        if (isset($_POST['acceptWithoutEmail'])) {
            $objEventMemberModel = CalendarEventsMemberModel::findByPk(Input::get('id'));

            if (null !== $objEventMemberModel) {
                $set = ['stateOfSubscription' => EventSubscriptionLevel::SUBSCRIPTION_ACCEPTED];
                Database::getInstance()->prepare('UPDATE tl_calendar_events_member %s WHERE id=?')->set($set)->execute(Input::get('id'));
                $_SESSION['addInfo'] = 'Der Benutzer wurde ohne E-Mail zum Event zugelassen und muss darüber noch manuell informiert werden.';
            }

            Controller::reload();
        }

        if (isset($_POST['addToWaitlist'])) {
            $objEventMemberModel = CalendarEventsMemberModel::findByPk(Input::get('id'));

            if (null !== $objEventMemberModel) {
                $set = ['stateOfSubscription' => EventSubscriptionLevel::SUBSCRIPTION_WAITLISTED];
                Database::getInstance()->prepare('UPDATE tl_calendar_events_member %s WHERE id=?')->set($set)->execute(Input::get('id'));
                $_SESSION['addInfo'] = 'Der Benutzer wurde ohne E-Mail auf die Warteliste gesetzt und muss darüber noch manuell informiert werden.';
            }

            Controller::reload();
        }
    }

    /**
     * @Callback(table="tl_calendar_events_member", target="config.onload")
     */
    public function setGlobalOperations(DataContainer $dc): void
    {
        /** @var BackendUser $user */
        $user = $this->security->getUser();

        // Remove edit_all (mehrere bearbeiten) button
        if (!$user->admin) {
            unset($GLOBALS['TL_DCA']['tl_calendar_events_member']['list']['global_operations']['all']);
        }

        // Generate href for $GLOBALS['TL_DCA']['tl_calendar_events_member']['list']['global_operations']['writeTourReport']
        // Generate href for  $GLOBALS['TL_DCA']['tl_calendar_events_member']['list']['global_operations']['printInstructorInvoice']

        $blnAllowTourReportButton = false;
        $blnAllowInstructorInvoiceButton = false;

        // Get the refererId
        $refererId = System::getContainer()->get('request_stack')->getCurrentRequest()->get('_contao_referer_id');

        // Get the backend module name
        $module = Input::get('do');

        $eventId = Input::get('id');
        $objEvent = CalendarEventsModel::findByPk($eventId);

        if (null !== $objEvent) {
            // Check if backend user is allowed
            if (EventReleaseLevelPolicyModel::hasWritePermission($user->id, $objEvent->id) || $objEvent->registrationGoesTo === $user->id) {
                if ('tour' === $objEvent->eventType || 'lastMinuteTour' === $objEvent->eventType) {
                    $url = sprintf('contao?do=sac_calendar_events_tool&table=tl_calendar_events&id=%s&act=edit&call=writeTourReport&rt=%s&ref=%s', $eventId, REQUEST_TOKEN, $refererId);
                    $GLOBALS['TL_DCA']['tl_calendar_events_member']['list']['global_operations']['writeTourReport']['href'] = $url;
                    $blnAllowTourReportButton = true;

                    $url = sprintf('contao?do=%s&table=tl_calendar_events_instructor_invoice&id=%s&rt=%s&ref=%s', $module, Input::get('id'), REQUEST_TOKEN, $refererId);
                    $GLOBALS['TL_DCA']['tl_calendar_events_member']['list']['global_operations']['printInstructorInvoice']['href'] = $url;
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
     * Add an icon to each record.
     *
     * @Callback(table="tl_calendar_events_member", target="list.label.label")
     */
    public function addIcon(array $row, string $label, DataContainer $dc, array $args): array
    {
        $icon = 'icons/'.$row['stateOfSubscription'].'.svg';
        $args[0] = sprintf('<div><img src="%s/%s" alt="%s" width="16" height=16"></div>', Bundle::ASSET_DIR, $icon, $row['stateOfSubscription']);

        return $args;
    }

    /**
     * @Callback(table="tl_calendar_events_member", target="fields.dashboard.input_field")
     */
    public function parseDashboard(DataContainer $dc): string
    {
        $objEventMemberModel = CalendarEventsMemberModel::findByPk($dc->id);

        if (null !== $objEventMemberModel) {
            $objTemplate = new BackendTemplate('be_calendar_events_registration_dashboard');
            $objTemplate->objRegistration = $objEventMemberModel;
            $objTemplate->stateOfSubscription = $objEventMemberModel->stateOfSubscription;
            $objEvent = CalendarEventsModel::findByPk($objEventMemberModel->eventId);

            if (null !== $objEvent) {
                $objTemplate->objEvent = $objEvent;

                if (!$objEventMemberModel->hasParticipated && '' !== $objEventMemberModel->email) {
                    if (Validator::isEmail($objEventMemberModel->email)) {
                        $objTemplate->showEmailButtons = true;
                    }
                }

                return $objTemplate->parse();
            }
        }

        return '';
    }

    /**
     * @Callback(table="tl_calendar_events_member", target="fields.refuseWithEmail.input_field")
     * @Callback(table="tl_calendar_events_member", target="fields.acceptWithEmail.input_field")
     * @Callback(table="tl_calendar_events_member", target="fields.addToWaitlist.input_field")
     *
     * @throws \Exception
     */
    public function inputFieldCallbackNotifyMemberAboutSubscriptionState(DataContainer $dc): string
    {
        /** @var BackendUser $user */
        $user = $this->security->getUser();

        // Start session
        session_start();

        // Build action array first
        $arrActions = [
            'acceptWithEmail' => [
                'formId' => 'subscription-accepted-form',
                'headline' => 'Zusage zum Event',
                'stateOfSubscription' => EventSubscriptionLevel::SUBSCRIPTION_ACCEPTED,
                'sessionInfoText' => 'Dem Benutzer wurde mit einer E-Mail eine Zusage für diesen Event versandt.',
                'emailTemplate' => 'be_email_templ_accept_registration',
                'emailSubject' => 'Zusage für %s',
            ],
            'addToWaitlist' => [
                'formId' => 'subscription-waitlisted-form',
                'headline' => 'Auf Warteliste setzen',
                'stateOfSubscription' => EventSubscriptionLevel::SUBSCRIPTION_WAITLISTED,
                'sessionInfoText' => 'Dem Benutzer wurde auf die Warteliste gesetzt und mit einer E-Mail darüber informiert.',
                'emailTemplate' => 'be_email_templ_added_to_waitlist',
                'emailSubject' => 'Auf Warteliste für %s',
            ],
            'refuseWithEmail' => [
                'formId' => 'subscription-refused-form',
                'headline' => 'Absage mitteilen',
                'stateOfSubscription' => EventSubscriptionLevel::SUBSCRIPTION_REFUSED,
                'sessionInfoText' => 'Dem Benutzer wurde mit einer E-Mail eine Absage versandt.',
                'emailTemplate' => 'be_email_templ_refuse_registration',
                'emailSubject' => 'Absage für %s',
            ],
        ];

        if (empty(Input::get('call')) || !\is_array($arrActions[Input::get('call')]) || empty($arrActions[Input::get('call')])) {
            $_SESSION['addInfo'] = 'Es ist ein Fehler aufgetreten.';
            Controller::redirect('contao?do=sac_calendar_events_tool&table=tl_calendar_events_member&id='.Input::get('id').'&act=edit&rt='.Input::get('rt'));
        }

        // Set action array
        $arrAction = $arrActions[Input::get('call')];

        // Generate form fields
        $objForm = new Form(
            $arrAction['formId'],
            'POST',
            static fn ($objHaste) => Input::post('FORM_SUBMIT') === $objHaste->getFormId()
        );
        // Now let's add form fields:
        $objForm->addFormField('subject', [
            'label' => 'Betreff',
            'inputType' => 'text',
            'eval' => ['mandatory' => true],
        ]);
        $objForm->addFormField('text', [
            'label' => 'Nachricht',
            'inputType' => 'textarea',
            'eval' => ['rows' => 20, 'cols' => 80, 'mandatory' => true],
        ]);
        $objForm->addFormField('submit', [
            'label' => 'Nachricht absenden',
            'inputType' => 'submit',
        ]);

        // Send notification
        if ('tl_calendar_events_member' === Input::post('FORM_SUBMIT')) {
            if ('' !== Input::post('subject') && '' !== Input::post('text')) {
                $objEventMemberModel = CalendarEventsMemberModel::findByPk($dc->id);

                if (null !== $objEventMemberModel) {
                    if (!Validator::isEmail($this->eventAdminEmail)) {
                        throw new \Exception('Please set a valid email address in parameter sacevt.event_admin_email.');
                    }
                    $objEmail = Notification::findOneByType('default_email');

                    // Use terminal42/notification_center
                    if (null !== $objEmail) {
                        // Set token array
                        $arrTokens = [
                            'email_sender_name' => html_entity_decode(html_entity_decode((string) $this->eventAdminName)),
                            'email_sender_email' => $this->eventAdminEmail,
                            'send_to' => $objEventMemberModel->email,
                            'reply_to' => $user->email,
                            'email_subject' => html_entity_decode((string) Input::post('subject')),
                            'email_text' => html_entity_decode(strip_tags((string) Input::post('text'))),
                            'attachment_tokens' => null,
                            'recipient_cc' => null,
                            'recipient_bcc' => null,
                            'email_html' => null,
                        ];

                        // Check if member has already booked at the same time
                        $objMember = MemberModel::findOneBySacMemberId($objEventMemberModel->sacMemberId);
                        $objEvent = CalendarEventsModel::findByPk($objEventMemberModel->eventId);

                        if ('acceptWithEmail' === Input::get('call') && null !== $objMember && !$objEventMemberModel->allowMultiSignUp && null !== $objEvent && CalendarEventsHelper::areBookingDatesOccupied($objEvent, $objMember)) {
                            $_SESSION['addError'] = 'Es ist ein Fehler aufgetreten. Der Teilnehmer kann nicht angemeldet werden, weil er zu dieser Zeit bereits an einem anderen Event bestätigt wurde. Wenn Sie das trotzdem erlauben möchten, dann setzen Sie das Flag "Mehrfachbuchung zulassen".';
                        } elseif ('acceptWithEmail' === Input::get('call') && null !== $objEventMemberModel && null !== $objEvent && !CalendarEventsMemberModel::canAcceptSubscription($objEventMemberModel, $objEvent)) {
                            $_SESSION['addError'] = 'Es ist ein Fehler aufgetreten. Da die maximale Teilnehmerzahl bereits erreicht ist, kann für den Teilnehmer die Teilnahme am Event nicht bestätigt werden.';
                        } // Send email
                        elseif (Validator::isEmail($objEventMemberModel->email)) {
                            $objEmail->send($arrTokens, $this->locale);
                            $set = ['stateOfSubscription' => $arrAction['stateOfSubscription']];
                            Database::getInstance()->prepare('UPDATE tl_calendar_events_member %s WHERE id=?')->set($set)->execute(Input::get('id'));
                            $_SESSION['addInfo'] = $arrAction['sessionInfoText'];
                        } else {
                            $_SESSION['addInfo'] = 'Es ist ein Fehler aufgetreten. Überprüfen Sie die E-Mail-Adressen. Dem Teilnehmer konnte keine E-Mail versandt werden.';
                        }
                    }
                }
                Controller::redirect('contao?do=sac_calendar_events_tool&table=tl_calendar_events_member&id='.Input::get('id').'&act=edit&rt='.Input::get('rt'));
            } else {
                // Add value to ffields
                if ('' !== Input::post('subject')) {
                    $objForm->getWidget('subject')->value = Input::post('subject');
                }

                if ('' !== Input::post('text')) {
                    $objForm->getWidget('text')->value = strip_tags(Input::post('text'));
                }

                // Generate template
                $objTemplate = new BackendTemplate('be_calendar_events_registration_email');
                $objTemplate->headline = $arrAction['headline'];
                $objTemplate->form = $objForm;

                return $objTemplate->parse();
            }
        } else { // Prefill form
            // Get the registration object
            $objEventMemberModel = CalendarEventsMemberModel::findByPk($dc->id);

            if (null !== $objEventMemberModel) {
                /** @var CalendarEventsModel $objEvent */
                $objEvent = $objEventMemberModel->getRelated('eventId');

                // Get event dates as a comma separated string
                $eventDates = CalendarEventsHelper::getEventTimestamps($objEvent);
                $strDates = implode(', ', array_map(
                    static fn ($tstamp) => Date::parse(Config::get('dateFormat'), $tstamp),
                    $eventDates
                ));

                // Build token array
                $arrTokens = [
                    'participantFirstname' => $objEventMemberModel->firstname,
                    'participantLastname' => $objEventMemberModel->lastname,
                    'participant_uuid' => $objEventMemberModel->uuid,
                    'eventName' => $objEvent->title,
                    'eventIban' => $objEvent->addIban ? $objEvent->iban : '',
                    'eventIbanBeneficiary' => $objEvent->addIban ? $objEvent->ibanBeneficiary : '',
                    'courseId' => $objEvent->courseId,
                    'eventType' => $objEvent->eventType,
                    'eventUrl' => Events::generateEventUrl($objEvent, true),
                    'eventDates' => $strDates,
                    'instructorName' => $user->name,
                    'instructorFirstname' => $user->firstname,
                    'instructorLastname' => $user->lastname,
                    'instructorPhone' => $user->phone,
                    'instructorMobile' => $user->mobile,
                    'instructorStreet' => $user->street,
                    'instructorPostal' => $user->postal,
                    'instructorCity' => $user->city,
                    'instructorEmail' => $user->email,
                ];

                if ('acceptWithEmail' === Input::get('call') && $objEvent->customizeEventRegistrationConfirmationEmailText && '' !== $objEvent->customEventRegistrationConfirmationEmailText) {
                    // Only for acceptWithEmail!!!
                    // Replace tags for custom notification set in the events settings (tags can be used case insensitive!)
                    $emailBodyText = $objEvent->customEventRegistrationConfirmationEmailText;

                    foreach ($arrTokens as $k => $v) {
                        $strPattern = '/##'.$k.'##/i';
                        $emailBodyText = preg_replace($strPattern, $v, $emailBodyText);
                    }
                    $emailBodyText = strip_tags($emailBodyText);
                } else {
                    // Build email text from template
                    $objEmailTemplate = new BackendTemplate($arrAction['emailTemplate']);

                    foreach ($arrTokens as $k => $v) {
                        $objEmailTemplate->{$k} = $v;
                    }
                    $emailBodyText = strip_tags($objEmailTemplate->parse());
                }

                // Get event type
                $eventType = \strlen((string) $GLOBALS['TL_LANG']['MSC'][$objEvent->eventType]) ? $GLOBALS['TL_LANG']['MSC'][$objEvent->eventType].': ' : 'Event: ';

                // Add value to ffields
                $objForm->getWidget('subject')->value = sprintf($arrAction['emailSubject'], $eventType.$objEvent->title);
                $objForm->getWidget('text')->value = $emailBodyText;

                // Generate template
                $objTemplate = new BackendTemplate('be_calendar_events_registration_email');
                $objTemplate->headline = $arrAction['headline'];
                $objTemplate->form = $objForm;

                return $objTemplate->parse();
            }

            return '';
        }
    }

    /**
     * @Callback(table="tl_calendar_events_member", target="list.global_operations.backToEventSettings.button")
     *
     * @param $href
     * @param $label
     * @param $title
     * @param $class
     * @param $attributes
     * @param $table
     *
     * @return string
     */
    public function buttonCbBackToEventSettings(?string $href, string $label, string $title, string $class, string $attributes, string $table)
    {
        $href = StringUtil::ampersand('contao?do=sac_calendar_events_tool&table=tl_calendar_events&id=%s&act=edit&rt=%s&ref=%s');
        $eventId = Input::get('id');
        $refererId = System::getContainer()->get('request_stack')->getCurrentRequest()->get('_contao_referer_id');
        $href = sprintf($href, $eventId, REQUEST_TOKEN, $refererId);

        return sprintf(' <a href="%s" class="%s" title="%s" %s>%s</a>', $href, $class, $title, $attributes, $label);
    }

    /**
     * @Callback(table="tl_calendar_events_member", target="edit.buttons")
     *
     * @param $arrButtons
     *
     * @return mixed
     */
    public function buttonsCallback($arrButtons, DataContainer $dc)
    {
        // Remove all buttons
        if ('refuseWithEmail' === Input::get('call') || 'acceptWithEmail' === Input::get('call') || 'addToWaitlist' === Input::get('call')) {
            $arrButtons = [];
        }

        if ('sendEmail' === Input::get('call')) {
            $arrButtons['saveNclose'] = '<button type="submit" name="saveNclose" id="saveNclose" class="tl_submit" accesskey="c">E-Mail absenden</button>';
            unset($arrButtons['save']);
        }

        unset($arrButtons['saveNback'], $arrButtons['saveNduplicate'], $arrButtons['saveNcreate']);

        return $arrButtons;
    }
}
