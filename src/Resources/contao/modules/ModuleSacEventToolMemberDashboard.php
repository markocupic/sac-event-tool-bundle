<?php

/**
 * SAC Event Tool Web Plugin for Contao
 * Copyright (c) 2008-2019 Marko Cupic
 * @package sac-event-tool-bundle
 * @author Marko Cupic m.cupic@gmx.ch, 2017-2019
 * @link https://github.com/markocupic/sac-event-tool-bundle
 */

namespace Markocupic\SacEventToolBundle;

use Contao\BackendTemplate;
use Contao\CalendarEventsMemberModel;
use Contao\CalendarEventsModel;
use Contao\CalendarEventsStoryModel;
use Contao\Config;
use Contao\Controller;
use Contao\Database;
use Contao\Date;
use Contao\Dbafs;
use Contao\Environment;
use Contao\Events;
use Contao\File;
use Contao\Files;
use Contao\FilesModel;
use Contao\Folder;
use Contao\Frontend;
use Contao\FrontendUser;
use Contao\Input;
use Contao\MemberModel;
use Contao\Message;
use Contao\Module;
use Contao\PageModel;
use Contao\StringUtil;
use Contao\System;
use Contao\UserModel;
use Contao\Validator;
use Haste\Form\Form;
use Haste\Util\Url;
use Markocupic\PhpOffice\PhpWord\MsWordTemplateProcessor;
use Markocupic\CloudconvertBundle\Services\DocxToPdfConversion;
use NotificationCenter\Model\Notification;
use Patchwork\Utf8;
use Contao\CoreBundle\Monolog\ContaoContext;
use Psr\Log\LogLevel;

/**
 * Front end module "registration".
 *
 * @author Leo Feyer <https://github.com/leofeyer>
 */
class ModuleSacEventToolMemberDashboard extends Module
{

    /**
     * Template
     * @var string
     */
    protected $strTemplate = 'mod_sac_event_tool_member_dashboard';

    /**
     * @var
     */
    protected $action;

    /**
     * @var
     */
    protected $objUser;

    /**
     * Display a wildcard in the back end
     *
     * @return string
     */
    public function generate()
    {
        if (TL_MODE == 'BE')
        {
            /** @var BackendTemplate|object $objTemplate */
            $objTemplate = new BackendTemplate('be_wildcard');

            $objTemplate->wildcard = '### ' . Utf8::strtoupper($GLOBALS['TL_LANG']['FMD']['eventToolFrontendUserDashboard'][0]) . ' ###';
            $objTemplate->title = $this->headline;
            $objTemplate->id = $this->id;
            $objTemplate->link = $this->name;
            $objTemplate->href = 'contao/main.php?do=themes&amp;table=tl_module&amp;act=edit&amp;id=' . $this->id;

            return $objTemplate->parse();
        }

        global $objPage;

        // Neither chache nor search page
        $objPage->noSearch = 1;
        $objPage->cache = 0;

        if (!FE_USER_LOGGED_IN)
        {
            Controller::redirect('');
            return '';
        }

        // Logged in FE user object
        $this->objUser = FrontendUser::getInstance();

        if (strlen(Input::get('action')))
        {
            $this->action = Input::get('action');
            $this->strTemplate = 'mod_sac_event_tool_' . $this->action;
        }
        else
        {
            $this->action = 'member_dashboard';
        }

        // Sign out from Event
        if (Input::get('do') === 'unregisterUserFromEvent')
        {
            $this->unregisterUserFromEvent(Input::get('registrationId'), $this->unregisterFromEventNotificationId);
            Controller::redirect(PageModel::findByPk($objPage->id)->getFrontendUrl());
        }

        // Sign out from Event
        if (Input::get('do') === 'rotate-avatar' || Input::get('do') === 'rotate-image')
        {
            $this->rotateImage(Input::get('img'));
            $url = Url::removeQueryString(['img', 'do']);
            Controller::redirect($url);
        }

        // Set the action
        if ($this->action === 'write_event_story')
        {
            if (!strlen(Input::get('eventId')))
            {
                return '';
            }
        }

        // Print course confirmation
        if ($this->action === 'download_course_confirmation')
        {
            if (strlen(Input::get('id')))
            {
                if (FE_USER_LOGGED_IN)
                {
                    $objRegistration = CalendarEventsMemberModel::findByPk(Input::get('id'));
                    if ($objRegistration !== null)
                    {
                        if ($this->objUser->sacMemberId == $objRegistration->sacMemberId)
                        {
                            $objMember = MemberModel::findBySacMemberId($this->objUser->sacMemberId);
                            $startDate = '';
                            $arrDates = array();
                            $courseId = '';
                            $eventTitle = $objRegistration->eventName;

                            $objEvent = $objRegistration->getRelated('eventId');
                            if ($objEvent !== null)
                            {
                                $startDate = Date::parse('Y', $objEvent->startDate);

                                // Build up $arrData;
                                // Get event dates from event object
                                $arrDates = array_map(function ($tstmp) {
                                    return Date::parse('m.d.Y', $tstmp);
                                }, CalendarEventsHelper::getEventTimestamps($objEvent->id));

                                // Course id
                                $courseId = htmlspecialchars(html_entity_decode($objEvent->courseId));

                                // Event title
                                $eventTitle = htmlspecialchars(html_entity_decode($objEvent->title));
                            }

                            // Log
                            System::log(sprintf('New event confirmation download. SAC-User-ID: %s. Event-ID: %s.', $objMember->sacMemberId, $objEvent->id), __FILE__ . ' Line: ' . __LINE__, Config::get('SAC_EVT_LOG_EVENT_CONFIRMATION_DOWNLOAD'));

                            // Create phpWord instance
                            $filenamePattern = str_replace('%%s', '%s', Config::get('SAC_EVT_COURSE_CONFIRMATION_FILE_NAME_PATTERN'));
                            $filename = sprintf($filenamePattern, $objMember->sacMemberId, $objRegistration->id, 'docx');
                            $destFilename = Config::get('SAC_EVT_TEMP_PATH') . '/' . $filename;
                            $objPhpWord = new MsWordTemplateProcessor(Config::get('SAC_EVT_COURSE_CONFIRMATION_TEMPLATE_SRC'), $destFilename);

                            // Replace template vars
                            $objPhpWord->replace('eventDates', implode(', ', $arrDates));
                            $objPhpWord->replace('firstname', htmlspecialchars(html_entity_decode($objMember->firstname)));
                            $objPhpWord->replace('lastname', htmlspecialchars(html_entity_decode($objMember->lastname)));
                            $objPhpWord->replace('memberId', $objMember->sacMemberId);
                            $objPhpWord->replace('eventYear', $startDate);
                            $objPhpWord->replace('eventId', htmlspecialchars(html_entity_decode($objRegistration->eventId)));
                            $objPhpWord->replace('eventName', $eventTitle);
                            $objPhpWord->replace('regId', $objRegistration->id);
                            $objPhpWord->replace('courseId', $courseId);

                            // Generate ms word file and send it to the browser
                            $objPhpWord->generateUncached(false)
                                ->sendToBrowser(false)
                                ->generate();

                            // Generate pdf
                            $objConversion = new DocxToPdfConversion($destFilename, Config::get('cloudconvertApiKey'));
                            $objConversion->sendToBrowser(true)->createUncached(false)->convert();

                            exit();
                        }
                    }
                }
            }
            throw new \Exception('There was an error while trying to generate the course confirmation.');
        }

        return parent::generate();
    }

    /**
     * Generate the module
     */
    protected function compile()
    {
        if ($this->objUser->email == '' || !Validator::isEmail($this->objUser->email))
        {
            Message::addInfo('Leider wurde f&uuml;r dieses Konto in der Datenbank keine E-Mail-Adresse gefunden. Daher stehen einige Funktionen nur eingeschr&auml;nkt zur Verf&uuml;gung. Bitte hinterlegen Sie auf der Internetseite des Zentralverbands Ihre E-Mail-Adresse.');
        }

        // Get root dir
        $rootDir = System::getContainer()->getParameter('kernel.project_dir');

        Controller::loadLanguageFile('tl_calendar_events_story');

        if (Message::hasInfo())
        {
            $this->Template->hasInfoMessage = true;
            $session = System::getContainer()->get('session')->getFlashBag()->get('contao.FE.info');
            $this->Template->infoMessage = $session[0];
        }

        if (Message::hasError())
        {
            $this->Template->hasErrorMessage = true;
            $session = System::getContainer()->get('session')->getFlashBag()->get('contao.FE.error');
            $this->Template->errorMessage = $session[0];
            $this->Template->errorMessages = $session;
        }

        Message::reset();

        switch ($this->action)
        {
            case 'member_dashboard':
                $this->checkAvatar();

                // Load languages
                System::loadLanguageFile('tl_calendar_events_member');

                $this->Template->objUser = $this->objUser;

                $objUploadFolder = new Folder(Config::get('SAC_EVT_FE_USER_AVATAR_DIRECTORY') . '/' . $this->objUser->id);
                if (!$objUploadFolder->isEmpty())
                {
                    $this->Template->objUser->hasAvatar = true;
                }

                $this->Template->avatarForm = $this->generateAvatarForm();

                $this->Template->userProfileForm = $this->generateUserProfileForm();

                // Upcoming events
                $this->Template->arrUpcomingEvents = CalendarEventsMemberModel::findUpcomingEventsByMemberId($this->objUser->id);

                // Count events
                $eventCounter = array();

                // Past events
                $arrEvents = CalendarEventsMemberModel::findPastEventsByMemberId($this->objUser->id);
                foreach ($arrEvents as $k => $event)
                {
                    $objEvent = \CalendarEventsModel::findByPk($event['id']);
                    $arrEvents[$k]['objEvent'] = $objEvent;

                    $objEventStory = CalendarEventsStoryModel::findOneBySacMemberIdAndEventId($this->objUser->sacMemberId, $event['id']);

                    // $arrEvents[$k]['objStory'] can be null
                    $arrEvents[$k]['objStory'] = $objEventStory;
                    $arrEvents[$k]['canOpenStory'] = false;
                    $arrEvents[$k]['canEditStory'] = false;

                    // Count events
                    if (!isset($eventCounter[$objEvent->eventType]))
                    {
                        $eventCounter[$objEvent->eventType] = 0;
                    }
                    $eventCounter[$objEvent->eventType]++;

                    /**
                     * @todo Do only list stories where user is permitted to edit (publishState == 1)
                     */
                    if ($arrEvents[$k]['objStory'] !== null && $objEvent->endDate + $this->timeSpanForCreatingNewEventStory * 24 * 60 * 60 > time())
                    {
                        $arrEvents[$k]['canEditStory'] = true;
                    }
                    elseif ($arrEvents[$k]['objStory'] === null && $objEvent->endDate + $this->timeSpanForCreatingNewEventStory * 24 * 60 * 60 > time())
                    {
                        $arrEvents[$k]['canOpenStory'] = true;
                    }

                    // Generate links
                    $arrEvents[$k]['objStoryLink'] = ($arrEvents[$k]['objStory'] !== null || $arrEvents[$k]['canEditStory'] || $arrEvents[$k]['canOpenStory']) ? Frontend::addToUrl('action=write_event_story&amp;eventId=' . $event['id']) : '#';
                    $arrEvents[$k]['downloadCourseConfirmationLink'] = Frontend::addToUrl('action=download_course_confirmation&amp;id=' . $event['registrationId']);
                }

                $objNewEventStoryForm = $this->generateCreateNewEventStoryForm();
                $this->Template->newEventStoryForm = $objNewEventStoryForm;

                // Event Stories
                $arrEventStories = array();
                $objEventStory = Database::getInstance()->prepare('SELECT * FROM tl_calendar_events_story WHERE sacMemberId=? ORDER BY eventStartDate DESC')->execute($this->objUser->sacMemberId);
                while ($objEventStory->next())
                {
                    $arrEventStory = $objEventStory->row();

                    // Check if story is still editable
                    if ($objEventStory->eventEndDate + $this->timeSpanForCreatingNewEventStory * 24 * 60 * 60 > time())
                    {
                        if ($objEventStory->publishState == 1)
                        {
                            $arrEventStory['canEditStory'] = true;
                        }
                    }

                    $arrEventStory['date'] = Date::parse(Config::get('dateFormat'), $objEventStory->eventStartDate);

                    // Check if event still exists
                    if (CalendarEventsModel::findByPk($objEventStory->eventId) !== null)
                    {
                        // Overwrite date if event still exists in tl_calendar_events
                        $arrEventStory['date'] = CalendarEventsHelper::getEventPeriod($objEventStory->eventId, Config::get('dateFormat'), false);
                        $arrEventStory['storyLink'] = Frontend::addToUrl('action=write_event_story&amp;eventId=' . $objEventStory->eventId);
                    }
                    $arrEventStories[] = $arrEventStory;
                }

                $this->Template->arrEventStories = $arrEventStories;
                $this->Template->timeSpanForCreatingNewEventStory = $this->timeSpanForCreatingNewEventStory;
                $this->Template->arrPastEvents = $arrEvents;
                $this->Template->eventCounter = $eventCounter;

                break;

            case 'write_event_story':

                $objEvent = CalendarEventsModel::findByPk(Input::get('eventId'));
                if ($objEvent !== null)
                {
                    // Do not allow blogging for old events
                    if ($objEvent->endDate + $this->timeSpanForCreatingNewEventStory * 24 * 60 * 60 < time())
                    {
                        if (null === CalendarEventsStoryModel::findOneBySacMemberIdAndEventId($this->objUser->sacMemberId, $objEvent->id))
                        {
                            Message::addError('F&uuml;r diesen Event kann kein Bericht mehr erstellt werden. Das Eventdatum liegt schon zu lange zur&uuml;ck');
                            Controller::redirect(Controller::getReferer());
                        }
                    }

                    $this->Template->eventName = $objEvent->title;
                    $this->Template->eventPeriod = CalendarEventsHelper::getEventPeriod($objEvent->id);
                    $this->Template->executionState = $objEvent->executionState;
                    $this->Template->eventSubstitutionText = $objEvent->eventSubstitutionText;

                    $objStory = Database::getInstance()->prepare('SELECT * FROM tl_calendar_events_story WHERE sacMemberId=? && eventId=?')->execute($this->objUser->sacMemberId, Input::get('eventId'));
                    if ($objStory->numRows)
                    {
                        $objStoryModel = CalendarEventsStoryModel::findByPk($objStory->id);
                        $this->Template->youtubeId = $objStory->youtubeId;
                        $this->Template->text = $objStory->text;
                        $this->Template->title = $objStory->title;
                        $this->Template->publishState = $objStory->publishState;

                        $images = [];
                        $arrMultiSRC = StringUtil::deserialize($objStory->multiSRC, true);
                        foreach ($arrMultiSRC as $uuid)
                        {
                            if (Validator::isUuid($uuid))
                            {
                                $objFiles = FilesModel::findByUuid($uuid);
                                if ($objFiles !== null)
                                {
                                    if (is_file($rootDir . '/' . $objFiles->path))
                                    {
                                        $objFile = new File($objFiles->path);

                                        if ($objFile->isImage)
                                        {
                                            $arrMeta = StringUtil::deserialize($objFiles->meta, true);
                                            $images[$objFiles->path] = array
                                            (
                                                'id'         => $objFiles->id,
                                                'path'       => $objFiles->path,
                                                'uuid'       => $objFiles->uuid,
                                                'name'       => $objFile->basename,
                                                'singleSRC'  => $objFiles->path,
                                                'title'      => StringUtil::specialchars($objFile->basename),
                                                'filesModel' => $objFiles->current(),
                                                'caption'    => isset($arrMeta['de']['caption']) ? $arrMeta['de']['caption'] : '',
                                                'alt'        => isset($arrMeta['de']['alt']) ? $arrMeta['de']['alt'] : '',
                                            );
                                        }
                                    }
                                }
                            }
                        }

                        // Custom image sorting
                        if ($objStory->orderSRC != '')
                        {
                            $tmp = StringUtil::deserialize($objStory->orderSRC);

                            if (!empty($tmp) && is_array($tmp))
                            {
                                // Remove all values
                                $arrOrder = array_map(function () {
                                }, array_flip($tmp));

                                // Move the matching elements to their position in $arrOrder
                                foreach ($images as $k => $v)
                                {
                                    if (array_key_exists($v['uuid'], $arrOrder))
                                    {
                                        $arrOrder[$v['uuid']] = $v;
                                        unset($images[$k]);
                                    }
                                }

                                // Append the left-over images at the end
                                if (!empty($images))
                                {
                                    $arrOrder = array_merge($arrOrder, array_values($images));
                                }

                                // Remove empty (unreplaced) entries
                                $images = array_values(array_filter($arrOrder));
                                unset($arrOrder);
                            }
                        }
                        $images = array_values($images);

                        $this->Template->images = $images;
                    }
                    else
                    {
                        $aDates = [];
                        $arrDates = \Contao\StringUtil::deserialize($objEvent->eventDates, true);
                        foreach ($arrDates as $arrDate)
                        {
                            $aDates[] = $arrDate['new_repeat'];
                        }

                        $set = array(
                            'title'                 => $objEvent->title,
                            'eventTitle'            => $objEvent->title,
                            'eventSubstitutionText' => ($objEvent->executionState === 'event_adapted' && $objEvent->eventSubstitutionText != '') ? $objEvent->eventSubstitutionText : '',
                            'eventStartDate'        => $objEvent->startDate,
                            'eventEndDate'          => $objEvent->endDate,
                            'organizers'            => $objEvent->organizers,
                            'eventDates'            => serialize($aDates),
                            'authorName'            => $this->objUser->firstname . ' ' . $this->objUser->lastname,
                            'sacMemberId'           => $this->objUser->sacMemberId,
                            'eventId'               => Input::get('eventId'),
                            'tstamp'                => time(),
                            'addedOn'               => time(),
                        );
                        $objInsertStmt = Database::getInstance()->prepare('INSERT INTO tl_calendar_events_story %s')->set($set)->execute();

                        if ($objInsertStmt->affectedRows)
                        {
                            // Add security token
                            $insertId = $objInsertStmt->insertId;
                            $set = array();
                            $set['securityToken'] = md5(rand(100000000, 999999999)) . $insertId;
                            Database::getInstance()->prepare('UPDATE tl_calendar_events_story %s WHERE id=?')->set($set)->execute($insertId);
                        }
                        $objStoryModel = CalendarEventsStoryModel::findByPk($insertId);
                    }

                    // Generate forms
                    $this->Template->objEventStoryTextAndYoutubeForm = $this->generateTextAndYoutubeForm($objStoryModel);
                    $this->Template->objEventStoryImageUploadForm = $this->generatePictureUploadForm($objStoryModel);
                }
                break;

            case 'clear_profile':
                $this->Template->form = $this->generateClearProfileForm();

                break;
        }
    }

    /**
     * Rotate an image anti clockwise by 90°
     * @param $id
     * @return bool
     * @throws \Exception
     */
    protected function rotateImage($id)
    {
        $angle = 90;

        $objFiles = FilesModel::findById($id);
        if ($objFiles === null)
        {
            return false;
        }

        $src = $objFiles->path;

        $rootDir = System::getContainer()->getParameter('kernel.project_dir');

        if (!file_exists($rootDir . '/' . $src))
        {
            Message::addError(sprintf('File "%s" not found.', $src));
            return false;
        }

        $objFile = new File($src);
        if (!$objFile->isGdImage)
        {
            Message::addError(sprintf('File "%s" could not be rotated because it is not an image.', $src));
            return false;
        }

        if (!function_exists('imagerotate'))
        {
            Message::addError(sprintf('PHP function "%s" is not installed.', 'imagerotate'));
            return false;
        }

        $source = imagecreatefromjpeg($rootDir . '/' . $src);

        //rotate
        $imgTmp = imagerotate($source, $angle, 0);

        // Output
        imagejpeg($imgTmp, $rootDir . '/' . $src);

        imagedestroy($source);
        return true;
    }

    /**
     * @param $registrationId
     * @param $notificationId
     */
    protected function unregisterUserFromEvent($registrationId, $notificationId)
    {
        $blnHasError = true;
        $errorMsg = 'Es ist ein Fehler aufgetreten. Du konntest nicht vom Event abgemeldet werden. Bitte nimm mit dem verantwortlichen Leiter Kontakt auf.';

        $objEventsMember = CalendarEventsMemberModel::findByPk($registrationId);
        if ($objEventsMember === null)
        {
            Message::add($errorMsg, 'TL_ERROR', TL_MODE);
            return;
        }

        // Use terminal42/notification_center
        $objNotification = Notification::findByPk($notificationId);

        if (null !== $objNotification && null !== $objEventsMember)
        {
            $objEvent = $objEventsMember->getRelated('eventId');
            if ($objEvent !== null)
            {
                $objInstructor = $objEvent->getRelated('mainInstructor');
                if ($objEventsMember->stateOfSubscription === 'subscription-refused')
                {
                    $objEventsMember->delete();
                    System::log(sprintf('User with SAC-User-ID %s has unsubscribed himself from event with ID: %s ("%s")', $objEventsMember->sacMemberId, $objEventsMember->eventId, $objEventsMember->eventName), __FILE__ . ' Line: ' . __LINE__, Config::get('SAC_EVT_LOG_EVENT_UNSUBSCRIPTION'));
                    return;
                }
                elseif ($objEventsMember->stateOfSubscription === 'user-has-unsubscribed')
                {
                    $errorMsg = 'Abmeldung fehlgeschlagen! Du hast dich vom Event "' . $objEvent->title . '" bereits abgemeldet.';
                    $blnHasError = true;
                }
                elseif ($objEventsMember->stateOfSubscription === 'subscription-not-confirmed' || $objEventsMember->stateOfSubscription === 'subscription-waitlisted')
                {
                    // allow unregistering if member is not confirmed on the event
                    // allow unregistering if member is waitlisted on the event
                    $blnHasError = false;
                }
                elseif (!$objEvent->allowDeregistration)
                {
                    $errorMsg = $objEvent->allowDeregistration . 'Du kannst dich vom Event "' . $objEvent->title . '" nicht abmelden. Die Anmeldung ist definitiv. Nimm Kontakt mit dem Event-Organisator auf.';
                    $blnHasError = true;
                }
                elseif ($objEvent->startDate < time())
                {
                    $errorMsg = 'Du konntest nicht vom Event "' . $objEvent->title . '" abgemeldet werden, da der Event bereits vorbei ist.';
                    $blnHasError = true;
                }
                elseif ($objEvent->allowDeregistration && ($objEvent->startDate < (time() + $objEvent->deregistrationLimit * 25 * 3600)))
                {
                    $errorMsg = 'Du konntest nicht vom Event "' . $objEvent->title . '" abgemeldet werden, da die Abmeldefrist von ' . $objEvent->deregistrationLimit . ' Tag(en) abgelaufen ist. Nimm, falls nötig, Kontakt mit dem Event-Organisator auf.';
                    $blnHasError = true;
                }
                elseif ($this->objUser->email == '' || !Validator::isEmail($this->objUser->email))
                {
                    $errorMsg = 'Leider wurde f&uuml;r dieses Konto in der Datenbank keine E-Mail-Adresse gefunden. Daher stehen einige Funktionen nur eingeschr&auml;nkt zur Verf&uuml;gung. Bitte hinterlegen Sie auf der Internetseite des Zentralverbands Ihre E-Mail-Adresse.';
                    $blnHasError = true;
                }
                elseif ($objEventsMember->sacMemberId != $this->objUser->sacMemberId)
                {
                    $errorMsg = 'Du hast nicht die nötigen Benutzerrechte um dich vom Event "' . $objEvent->title . '" abzumelden.';
                    $blnHasError = true;
                }
                elseif ($objInstructor !== null)
                {
                    // unregister from event
                    $blnHasError = false;
                }
                else
                {
                    $errorMsg = 'Es ist ein Fehler aufgetreten. Du konntest nicht vom Event "' . $objEvent->title . '" abgemeldet werden. Nimm, falls nötig, Kontakt mit dem Event-Organisator auf.';
                    $blnHasError = true;
                }

                // Unregister from event
                if (!$blnHasError)
                {
                    $objEventsMember->stateOfSubscription = 'user-has-unsubscribed';

                    // Save data record in tl_calendar_events_member
                    $objEventsMember->save();

                    // Load language file
                    Controller::loadLanguageFile('tl_calendar_events_member');

                    $arrTokens = array(
                        'state_of_subscription' => $GLOBALS['TL_LANG']['tl_calendar_events_member'][$objEventsMember->stateOfSubscription],
                        'event_course_id'       => $objEvent->courseId,
                        'event_name'            => $objEvent->title,
                        'event_type'            => $objEvent->eventType,
                        'instructor_name'       => $objInstructor->name,
                        'instructor_email'      => $objInstructor->email,
                        'participant_name'      => $objEventsMember->firstname . ' ' . $objEventsMember->lastname,
                        'participant_email'     => $objEventsMember->email,
                        'event_link_detail'     => Environment::get('url') . '/' . Events::generateEventUrl($objEvent),
                        'sac_member_id'         => $objEventsMember->sacMemberId != '' ? $objEventsMember->sacMemberId : 'keine',
                    );

                    if ($objEvent->registrationGoesTo > 0)
                    {
                        $objUser = UserModel::findByPk($objEvent->registrationGoesTo);
                        if ($objUser !== null)
                        {
                            if ($objUser->email != '')
                            {
                                if (Validator::isEmail($objUser->email))
                                {
                                    $arrTokens['instructor_name'] = $objUser->name;
                                    $arrTokens['instructor_email'] = $objUser->email;
                                }
                            }
                        }
                    }

                    Message::add('Du hast dich vom Event "' . $objEventsMember->eventName . '" abgemeldet. Der Leiter wurde per E-Mail informiert. Zur Bestätigung findest du in deinem Postfach eine Kopie dieser Nachricht.', 'TL_INFO', TL_MODE);

                    // Log
                    System::log(sprintf('User with SAC-User-ID %s has unsubscribed himself from event with ID: %s ("%s")', $objEventsMember->sacMemberId, $objEventsMember->eventId, $objEventsMember->eventName), __FILE__ . ' Line: ' . __LINE__, Config::get('SAC_EVT_LOG_EVENT_UNSUBSCRIPTION'));

                    $objNotification->send($arrTokens, 'de');
                }
            }
        }
        if ($blnHasError)
        {
            Message::add($errorMsg, 'TL_ERROR', TL_MODE);
        }
    }

    protected function generateCreateNewEventStoryForm()
    {
        $rootDir = System::getContainer()->getParameter('kernel.project_dir');

        $objForm = new Form('form-create-new-event-story', 'POST', function ($objHaste) {
            return Input::post('FORM_SUBMIT') === $objHaste->getFormId();
        });

        $objForm->setFormActionFromUri(Environment::get('uri'));

        $arrOptions = array();
        $arrEvents = CalendarEventsMemberModel::findPastEventsByMemberId2($this->objUser->id, $this->timeSpanForCreatingNewEventStory);
        if (is_array($arrEvents) && !empty($arrEvents))
        {
            foreach ($arrEvents as $event)
            {
                if ($event['objEvent'] !== null)
                {
                    $objEvent = $event['objEvent'];
                    $arrOptions[$event['id']] = $objEvent->title;
                }
            }
        }

        // Now let's add form fields:
        $objForm->addFormField('event', array(
            'label'     => 'Tourenbericht zu einem Event erstellen',
            'inputType' => 'select',
            'options'   => $arrOptions,
            'eval'      => array('mandatory' => true),
        ));

        // Let's add  a submit button
        $objForm->addFormField('submit', array(
            'label'     => 'Weiter',
            'inputType' => 'submit',
        ));

        if ($objForm->validate())
        {
            // Reload page after uploads
            if (Input::post('FORM_SUBMIT') === 'form-create-new-event-story')
            {
                $objWidget = $objForm->getWidget('event');
                Controller::redirect(Frontend::addToUrl('action=write_event_story&amp;eventId=' . $objWidget->value));
            }
        }

        return $objForm->generate();
    }

    /**
     * Generate the avatar upload form
     * @return Form
     */
    protected function generateAvatarForm()
    {
        $rootDir = System::getContainer()->getParameter('kernel.project_dir');

        $objForm = new Form('form-avatar-upload', 'POST', function ($objHaste) {
            return Input::post('FORM_SUBMIT') === $objHaste->getFormId();
        });

        $objForm->setFormActionFromUri(Environment::get('uri'));

        // Now let's add form fields:
        $objForm->addFormField('avatar', array(
            'label'     => 'Profilbild hochladen',
            'inputType' => 'upload',
            'eval'      => array('class' => 'custom-input-file', 'mandatory' => false),
        ));
        $objForm->addFormField('delete-avatar', array(
            'label'     => array('', 'Profilbild l&ouml;schen'),
            'inputType' => 'checkbox',
        ));

        // Let's add  a submit button
        $objForm->addFormField('submit', array(
            'label'     => 'Speichern',
            'inputType' => 'submit',
        ));

        // Create the folder if it not exists
        $objUploadFolder = new Folder(Config::get('SAC_EVT_FE_USER_AVATAR_DIRECTORY') . '/' . $this->objUser->id);
        Dbafs::addResource($objUploadFolder->path);

        $objWidget = $objForm->getWidget('avatar');
        $objWidget->extensions = 'jpg,jpeg,png,gif,svg';
        $objWidget->storeFile = true;
        $objWidget->uploadFolder = FilesModel::findByPath($objUploadFolder->path)->uuid;
        $objWidget->addAttribute('accept', '.jpg,.jpeg,.png,.gif,.svg');

        // Delete avatar
        if (Input::post('FORM_SUBMIT') === 'form-avatar-upload' && Input::post('delete-avatar'))
        {
            $objUploadFolder->purge();
            $oMember = MemberModel::findByPk($this->objUser->id);
            if ($oMember !== null)
            {
                $oMember->avatar = '';
                $oMember->save();
            }
        }

        // Standardize name
        if (Input::post('FORM_SUBMIT') === 'form-avatar-upload' && !empty($_FILES['avatar']['tmp_name']))
        {
            $objUploadFolder->purge();
            $objFile = new File($_FILES['avatar']['name']);
            $_FILES['avatar']['name'] = 'avatar-' . $this->objUser->id . '.' . strtolower($objFile->extension);

            // Move uploaded file so we can save the avatar uuid in tl_member.avatar
            move_uploaded_file($_FILES['avatar']['tmp_name'], TL_ROOT . '/' . $objUploadFolder->path . '/' . $_FILES['avatar']['name']);
            Dbafs::addResource($objUploadFolder->path . '/' . $_FILES['avatar']['name']);
            $fileModel = FilesModel::findByPath($objUploadFolder->path . '/' . $_FILES['avatar']['name']);
            $oMember = MemberModel::findByPk($this->objUser->id);
            if ($oMember !== null)
            {
                $oMember->avatar = $fileModel->uuid;
                $oMember->save();
            }
        }

        if ($objForm->validate())
        {
            // Reload page after uploads
            if (Input::post('FORM_SUBMIT') === 'form-avatar-upload')
            {
                Controller::reload();
            }
        }

        return $objForm->generate();
    }

    /**
     * Generate the avatar upload form
     * @return Form
     */
    protected function generateUserProfileForm()
    {
        $objForm = new Form('form-user-profile', 'POST', function ($objHaste) {
            return Input::post('FORM_SUBMIT') === $objHaste->getFormId();
        });

        $objForm->setFormActionFromUri(Environment::get('uri'));

        // Now let's add form fields:
        $objForm->addFormField('emergencyPhone', array(
            'label'     => 'Notfallnummer',
            'inputType' => 'text',
            'eval'      => array('rgxp' => 'phone', 'mandatory' => true),
        ));
        $objForm->addFormField('emergencyPhoneName', array(
            'label'     => 'Name und Bezug des Angeh&ouml;rigen',
            'inputType' => 'text',
            'eval'      => array('mandatory' => true),
        ));
        $objForm->addFormField('foodHabits', array(
            'label'     => 'Essgewohnheiten (Vegetarier, Laktoseintoleranz, etc.)',
            'inputType' => 'text',
            'eval'      => array('mandatory' => false),
        ));

        // Let's add  a submit button
        $objForm->addFormField('submit', array(
            'label'     => 'Speichern',
            'inputType' => 'submit',
        ));

        // Get form presets from tl_member
        $arrFields = array('emergencyPhone', 'emergencyPhoneName', 'foodHabits');
        foreach ($arrFields as $field)
        {
            $objWidget = $objForm->getWidget($field);
            if ($objWidget->value == '')
            {
                $objWidget = $objForm->getWidget($field);
                $objWidget->value = $this->objUser->{$field};
            }
        }

        // Bind form to the MemberModel
        $objModel = MemberModel::findByPk($this->objUser->id);
        $objForm->bindModel($objModel);

        if ($objForm->validate())
        {
            // The model will now contain the changes so you can save it
            $objModel->save();
        }

        return $objForm->generate();
    }

    /**
     * Generate the clear profile form
     * @return Form
     */
    protected function generateClearProfileForm()
    {
        $objForm = new Form('form-clear-profile', 'POST', function ($objHaste) {
            return Input::post('FORM_SUBMIT') === $objHaste->getFormId();
        });

        $objForm->setFormActionFromUri(Environment::get('uri'));

        // Now let's add form fields:
        // Now let's add form fields:
        $objForm->addFormField('deleteProfile', array(
            'label'     => array('Profil löschen', ''),
            'inputType' => 'select',
            'options'   => array('false' => 'Nein', 'true' => 'Ja'),
        ));

        $objForm->addFormField('sacMemberId', array(
            'label'     => array('SAC-Mitgliedernummer', ''),
            'inputType' => 'text',
        ));

        // Let's add  a submit button
        $objForm->addFormField('submit', array(
            'label'     => 'Profil unwiederkehrlich löschen',
            'inputType' => 'submit',
        ));

        if ($objForm->validate())
        {
            if (Input::post('FORM_SUBMIT') === 'form-clear-profile')
            {
                $blnError = false;
                if (Input::post('deleteProfile') !== 'true')
                {
                    $blnError = true;
                    $objFormField1 = $objForm->getWidget('deleteProfile');
                    $objFormField1->addError('Falsche Eingabe. Das Profil konnte nicht gelöscht werden.');
                }
                if (Input::post('sacMemberId') != $this->objUser->sacMemberId)
                {
                    $blnError = true;
                    $objFormField2 = $objForm->getWidget('sacMemberId');
                    $objFormField2->addError('Das Profil konnte nicht gelöscht werden. Die Mitgliedernummer ist falsch.');
                }

                if (!$blnError)
                {
                    // Clear account
                    ClearPersonalMemberData::clearMemberProfile($this->objUser->id);
                    ClearPersonalMemberData::disableLogin($this->objUser->id);
                    ClearPersonalMemberData::deleteFrontendAccount($this->objUser->id);
                    Controller::reload();
                }
            }
        }

        return $objForm->generate();
    }

    /**
     * @throws \Exception
     */
    private function checkAvatar()
    {
        $rootDir = System::getContainer()->getParameter('kernel.project_dir');

        // Check for valid avatar
        $oMember = MemberModel::findByPk($this->objUser->id);
        if ($oMember !== null)
        {
            if ($oMember->avatar != '')
            {
                $objFile = FilesModel::findByUuid($oMember->avatar);
                if ($objFile === null)
                {
                    $hasError = true;
                }
                if (!is_file($rootDir . '/' . $objFile->path))
                {
                    $hasError = true;
                }
                if ($hasError)
                {
                    $oMember->avatar = '';
                    $oMember->save();
                    $objUploadFolder = new Folder(Config::get('SAC_EVT_FE_USER_AVATAR_DIRECTORY') . '/' . $this->objUser->id);
                    if ($objUploadFolder !== null)
                    {
                        $objUploadFolder->purge();
                        $objUploadFolder->delete();
                    }
                }
            }
        }
    }

    /**
     * @param $objEventStoryModel
     */
    protected function generateTextAndYoutubeForm($objEventStoryModel)
    {
        $rootDir = System::getContainer()->getParameter('kernel.project_dir');

        $objForm = new Form('form-eventstory-text-and-youtube', 'POST', function ($objHaste) {
            return Input::post('FORM_SUBMIT') === $objHaste->getFormId();
        });

        $url = Environment::get('uri');
        $objForm->setFormActionFromUri($url);

        // Add some fields
        $objForm->addFormField('text', array(
            'label'     => 'Touren-/Lager-/Kursbericht',
            'inputType' => 'textarea',
            'eval'      => array('decodeEntities' => true),
            'value'     => html_entity_decode($objEventStoryModel->text)

        ));

        // Add some fields
        $objForm->addFormField('youtubeId', array(
            'label'     => 'Youtube Film-Id',
            'inputType' => 'text',
            'eval'      => array(),
            'value'     => $objEventStoryModel->youtubeId
        ));

        // Let's add  a submit button
        $objForm->addFormField('submit', array(
            'label'     => 'absenden',
            'inputType' => 'submit',
        ));

        // Add attributes
        $objWidgetYt = $objForm->getWidget('youtubeId');
        $objWidgetText = $objForm->getWidget('text');

        $objWidgetYt->addAttribute('placeholder', 'z.B. G02hYgT3nGw');

        // Bind model
        $objForm->bindModel($objEventStoryModel);

        $hasError = true;

        // validate() also checks whether the form has been submitted
        if ($objForm->validate() && Input::post('FORM_SUBMIT') === $objForm->getFormId())
        {
            $hasError = false;
        }

        if (!$hasError)
        {
            $objEventStoryModel->addedOn = time();
            $objEventStoryModel->text = htmlspecialchars($objWidgetText->value);
            $objEventStoryModel->youtubeId = $objWidgetYt->value;
            $objEventStoryModel->save();
            // Reload page
            $this->reload();
        }

        return $objForm->generate();
    }

    /**
     * @param $objEventStoryModel
     * @return string|void
     * @throws \Exception
     */
    protected function generatePictureUploadForm($objEventStoryModel)
    {
        global $objPage;

        $rootDir = System::getContainer()->getParameter('kernel.project_dir');

        if ($this->eventStoryUploadFolder != '')
        {
            if (Validator::isBinaryUuid($this->eventStoryUploadFolder))
            {
                $objFilesModel = FilesModel::findByUuid($this->eventStoryUploadFolder);
                if ($objFilesModel !== null)
                {
                    $objUploadFolder = new Folder($objFilesModel->path . '/' . $objEventStoryModel->id);
                    Dbafs::addResource($objFilesModel->path . '/' . $objEventStoryModel->id);
                }
            }
        }

        if ($objUploadFolder === null)
        {
            return;
        }

        $objForm = new Form('form-eventstory-picture-upload', 'POST', function ($objHaste) {
            return Input::post('FORM_SUBMIT') === $objHaste->getFormId();
        });

        $url = Environment::get('uri');
        $objForm->setFormActionFromUri($url);

        // Add some fields
        $objForm->addFormField('fileupload', array(
            'label'     => 'Bildupload',
            'inputType' => 'fineUploader',
            'eval'      => array('extensions'   => 'jpg,jpeg',
                                 'storeFile'    => true,
                                 'addToDbafs'   => true,
                                 'isGallery'    => false,
                                 'directUpload' => false,
                                 'multiple'     => true,
                                 'useHomeDir'   => false,
                                 'uploadFolder' => $objUploadFolder->path,
                                 'mandatory'    => true
            ),
        ));

        // Let's add  a submit button
        $objForm->addFormField('submit', array(
            'label'     => 'upload starten',
            'inputType' => 'submit',
        ));

        // Add attributes
        $objWidgetFileupload = $objForm->getWidget('fileupload');
        $objWidgetFileupload->addAttribute('accept', '.jpg, .jpeg');
        $objWidgetFileupload->storeFile = true;

        // Overwrite uploader template
        if ($this->eventStoryCustomUploaderTpl !== '')
        {
            //$objWidgetFileupload->template = $this->eventStoryCustomUploaderTpl;
        }

        // validate() also checks whether the form has been submitted
        if ($objForm->validate() && Input::post('FORM_SUBMIT') === $objForm->getFormId())
        {
            if (is_array($_SESSION['FILES']) && !empty($_SESSION['FILES']))
            {
                foreach ($_SESSION['FILES'] as $k => $file)
                {
                    $uuid = $file['uuid'];
                    if (Validator::isStringUuid($uuid))
                    {
                        $binUuid = StringUtil::uuidToBin($uuid);
                        $objModel = FilesModel::findByUuid($binUuid);

                        if ($objModel !== null)
                        {
                            $objFile = new File($objModel->path);
                            if ($objFile->isImage)
                            {
                                // Resize image
                                $this->resizeUploadedImage($objModel->path);

                                // Rename file
                                $newFilename = sprintf('event-story-%s-img-%s.%s', $objEventStoryModel->id, $objModel->id, strtolower($objFile->extension));
                                $newPath = $objUploadFolder->path . '/' . $newFilename;
                                Files::getInstance()->rename($objFile->path, $newPath);
                                $objModel->path = $newPath;
                                $objModel->name = basename($newPath);
                                $objModel->tstamp = time();
                                $objModel->save();
                                Dbafs::updateFolderHashes($objUploadFolder->path);

                                if (is_file($rootDir . '/' . $newPath))
                                {
                                    $oFile = new File($newPath);
                                    $oFileModel = FilesModel::findByPath($newPath);
                                    if ($oFileModel !== null)
                                    {
                                        // Add Photographer name to meta field
                                        if ($this->objUser !== null)
                                        {
                                            $arrMeta = \StringUtil::deserialize($oFileModel->meta, true);
                                            if (!isset($arrMeta[$objPage->language]))
                                            {
                                                $arrMeta[$objPage->language] = array(
                                                    'title'        => '',
                                                    'alt'          => '',
                                                    'link'         => '',
                                                    'caption'      => '',
                                                    'photographer' => '',
                                                );
                                            }
                                            $arrMeta[$objPage->language]['photographer'] = $this->objUser->firstname . ' ' . $this->objUser->lastname;
                                            $oFileModel->meta = serialize($arrMeta);
                                            $oFileModel->save();
                                        }

                                        // Save gallery data to tl_calendar_events_story
                                        $multiSRC = StringUtil::deserialize($objEventStoryModel->multiSRC, true);
                                        $multiSRC[] = $oFileModel->uuid;
                                        $objEventStoryModel->multiSRC = serialize($multiSRC);
                                        $orderSRC = StringUtil::deserialize($objEventStoryModel->multiSRC, true);
                                        $orderSRC[] = $oFileModel->uuid;
                                        $objEventStoryModel->orderSRC = serialize($orderSRC);
                                        $objEventStoryModel->save();
                                    }

                                    // Log
                                    $strText = sprintf('User with username %s has uploadad a new picture ("%s").', $this->objUser->username, $objModel->path);
                                    $logger = System::getContainer()->get('monolog.logger.contao');
                                    $logger->log(LogLevel::INFO, $strText, array('contao' => new ContaoContext(__METHOD__, 'EVENT STORY PICTURE UPLOAD')));
                                }
                            }
                        }
                    }
                }
            }

            if (!$objWidgetFileupload->hasErrors())
            {
                // Reload page
                $this->reload();
            }
        }

        unset($_SESSION['FILES']);

        return $objForm->generate();
    }

    /**
     * Resize an uploaded image if necessary
     *
     * @param string $strImage
     *
     * @return boolean
     */
    public function resizeUploadedImage($strImage)
    {
        // The feature is disabled
        if (Config::get('maxImageWidth') < 1)
        {
            return false;
        }

        $objFile = new File($strImage);

        // Not an image
        if (!$objFile->isSvgImage && !$objFile->isGdImage)
        {
            return false;
        }
        $arrImageSize = $objFile->imageSize;

        // The image is too big to be handled by the GD library
        if ($objFile->isGdImage && ($arrImageSize[0] > Config::get('gdMaxImgWidth') || $arrImageSize[1] > Config::get('gdMaxImgHeight')))
        {
            // Log
            $strText = 'File "' . $strImage . '" is too big to be resized automatically';
            $logger = System::getContainer()->get('monolog.logger.contao');
            $logger->log(LogLevel::INFO, $strText, array('contao' => new ContaoContext(__METHOD__, TL_FILES)));

            return false;
        }

        $blnResize = false;

        // The image exceeds the maximum image width
        if ($arrImageSize[0] > Config::get('maxImageWidth'))
        {
            $blnResize = true;
            $intWidth = Config::get('maxImageWidth');
            $intHeight = round(Config::get('maxImageWidth') * $arrImageSize[1] / $arrImageSize[0]);
            $arrImageSize = array($intWidth, $intHeight);
        }

        // The image exceeds the maximum image height
        if ($arrImageSize[1] > Config::get('maxImageWidth'))
        {
            $blnResize = true;
            $intWidth = round(Config::get('maxImageWidth') * $arrImageSize[0] / $arrImageSize[1]);
            $intHeight = Config::get('maxImageWidth');
            $arrImageSize = array($intWidth, $intHeight);
        }

        // Resized successfully
        if ($blnResize)
        {
            System::getContainer()
                ->get('contao.image.image_factory')
                ->create(TL_ROOT . '/' . $strImage, array($arrImageSize[0], $arrImageSize[1]), TL_ROOT . '/' . $strImage);

            $this->blnHasResized = true;

            return true;
        }

        return false;
    }
}
