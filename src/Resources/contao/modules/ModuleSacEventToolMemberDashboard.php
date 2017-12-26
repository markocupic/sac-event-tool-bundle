<?php

/**
 * SAC Event Tool Web Plugin for Contao
 * Copyright (c) 2008-2017 Marko Cupic
 * @package sac-event-tool-bundle
 * @author Marko Cupic m.cupic@gmx.ch, 2017
 * @link    https://sac-kurse.kletterkader.com
 */

namespace Markocupic\SacEventToolBundle;

use Contao\CalendarEventsStoryModel;
use Contao\Validator;
use Patchwork\Utf8;
use Contao\Module;
use Contao\Frontend;
use Contao\Message;
use Contao\BackendTemplate;
use Contao\Input;
use Contao\StringUtil;
use Contao\System;
use Contao\Database;
use Contao\Environment;
use Contao\Controller;
use Contao\FrontendUser;
use Contao\Events;
use Contao\CalendarEventsModel;
use Contao\File;
use Contao\FilesModel;
use Contao\PageModel;
use Contao\Date;
use Contao\Config;
use Contao\MemberModel;
use Contao\CalendarEventsMemberModel;
use NotificationCenter\Model\Notification;
use Markocupic\SacEventToolBundle\Services\Pdf\DocxToPdfConversion;
use PhpOffice\PhpWord\TemplateProcessorExtended;
use Haste\Form\Form;

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
                            $objEvent = $objRegistration->getRelated('pid');
                            if ($objEvent === null)
                            {
                                throw new \Exception('Event with ID ' . $objRegistration->pid . ' not found in tl_calendar_events.');
                            }

                            // Log
                            System::log(sprintf('New event confirmation download. SAC-User-ID: %s. Event-ID: %s.', $objMember->sacMemberId, $objEvent->id), __FILE__ . ' Line: ' . __LINE__, Config::get('SAC_EVT_LOG_EVENT_CONFIRMATION_DOWNLOAD'));


                            // Build up $arrData;
                            // Get event dates from event object
                            $arrDates = array_map(function ($tstmp) {
                                return Date::parse('m.d.Y', $tstmp);
                            }, CalendarSacEvents::getEventTimestamps($objEvent->id));

                            // Replace template vars
                            $arrData = array();
                            $arrData[] = array('key' => 'eventDates', 'value' => implode(', ', $arrDates));
                            $arrData[] = array('key' => 'firstname', 'value' => htmlspecialchars(html_entity_decode($objMember->firstname)));
                            $arrData[] = array('key' => 'lastname', 'value' => htmlspecialchars(html_entity_decode($objMember->lastname)));
                            $arrData[] = array('key' => 'memberId', 'value' => $objMember->sacMemberId);
                            $arrData[] = array('key' => 'eventYear', 'value' => Date::parse('Y', $objEvent->startDate));
                            $arrData[] = array('key' => 'eventId', 'value' => htmlspecialchars(html_entity_decode($objRegistration->pid)));
                            $arrData[] = array('key' => 'eventName', 'value' => htmlspecialchars(html_entity_decode($objEvent->title)));
                            $arrData[] = array('key' => 'regId', 'value' => $objRegistration->id);

                            $filename = sprintf(Config::get('SAC_EVT_COURSE_CONFIRMATION_FILE_NAME_PATTERN'), $objMember->sacMemberId, $objEvent->id, 'docx');

                            // Generate docxPhpOffice\PhpWord;
                            TemplateProcessorExtended::create($arrData, Config::get('SAC_EVT_COURSE_CONFIRMATION_TEMPLATE_SRC'), Config::get('SAC_EVT_TEMP_PATH'), $filename, false);

                            // Generate pdf
                            DocxToPdfConversion::convert(Config::get('SAC_EVT_TEMP_PATH') . '/' . $filename, Config::get('SAC_EVT_CLOUDCONVERT_API_KEY'), true);

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

        if($this->objUser->email == '' || !Validator::isEmail($this->objUser->email))
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
        }

        Message::reset();


        switch ($this->action)
        {
            case 'member_dashboard':

                // Load languages
                System::loadLanguageFile('tl_calendar_events_member');

                $this->Template->objUser = $this->objUser;

                $this->Template->avatarForm = $this->generateAvatarForm();

                $this->Template->userProfileForm = $this->generateUserProfileForm();


                // Upcoming events
                $this->Template->arrUpcomingEvents = CalendarEventsMemberModel::findUpcomingEventsByMemberId($this->objUser->id);


                // Past events
                $arrEvents = CalendarEventsMemberModel::findPastEventsByMemberId($this->objUser->id);
                foreach ($arrEvents as $k => $event)
                {

                    $objEvent = \CalendarEventsModel::findByPk($event['id']);
                    $arrEvents[$k]['objEvent'] = $objEvent;
                    $arrEvents[$k]['objStory'] = CalendarEventsStoryModel::findOneBySacMemberIdAndEventId($this->objUser->sacMemberId, $event['id']);

                    $canWriteStory = false;
                    if ($arrEvents[$k]['objStory'] !== null)
                    {
                        $canWriteStory = true;
                    }
                    elseif ($arrEvents[$k]['objStory'] === null && $objEvent->endDate + $this->timeSpanForCreatingNewEventStory * 24 * 60 * 60 > time())
                    {
                        $canWriteStory = true;
                    }

                    // Generate links
                    $arrEvents[$k]['objStoryLink'] = $canWriteStory ? Frontend::addToUrl('action=write_event_story&amp;eventId=' . $event['id']) : '#';
                    $arrEvents[$k]['downloadCourseConfirmationLink'] = Frontend::addToUrl('action=download_course_confirmation&amp;id=' . $event['registrationId']);
                    $arrEvents[$k]['canWriteStory'] = $canWriteStory;
                }

                $this->Template->arrPastEvents = $arrEvents;

                break;

            case 'write_event_story':

                $objEvent = CalendarEventsModel::findByPk(Input::get('eventId'));
                if ($objEvent !== null)
                {

                    $this->Template->eventName = $objEvent->title;
                    $this->Template->eventPeriod = CalendarSacEvents::getEventPeriod($objEvent->id);
                    $objStory = Database::getInstance()->prepare('SELECT * FROM tl_calendar_events_story WHERE sacMemberId=? && pid=?')->execute($this->objUser->sacMemberId, Input::get('eventId'));
                    if ($objStory->numRows)
                    {
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
                                            $images[$objFiles->path] = array
                                            (
                                                'id' => $objFiles->id,
                                                'path' => $objFiles->path,
                                                'uuid' => $objFiles->uuid,
                                                'name' => $objFile->basename,
                                                'singleSRC' => $objFiles->path,
                                                'title' => \StringUtil::specialchars($objFile->basename),
                                                'filesModel' => $objFiles->current(),
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
                        $set = array(
                            'addedOn' => time(),
                            'title' => $objEvent->title,
                            'authorName' => $this->objUser->firstname . ' ' . $this->objUser->lastname,
                            'sacMemberId' => $this->objUser->sacMemberId,
                            'pid' => Input::get('eventId')
                        );
                        Database::getInstance()->prepare('INSERT INTO tl_calendar_events_story %s')->set($set)->execute();
                    }
                }
                break;
        }

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
        if($objEventsMember === null)
        {
            Message::add($errorMsg, 'TL_ERROR', TL_MODE);
            return;
        }

        // Use terminal42/notification_center
        $objNotification = Notification::findByPk($notificationId);

        if (null !== $objNotification && null !== $objEventsMember)
        {
            $objEvent = $objEventsMember->getRelated('pid');
            if ($objEvent !== null)
            {
                $objInstructor = $objEvent->getRelated('mainInstructor');
                if($objEventsMember->stateOfSubscription === 'subscription-refused')
                {
                    $objEventsMember->delete();
                    System::log(sprintf('User with SAC-User-ID %s has unsubscribed himself from event with ID: %s ("%s")', $objEventsMember->sacMemberId, $objEventsMember->pid, $objEventsMember->eventName), __FILE__ . ' Line: ' . __LINE__, Config::get('SAC_EVT_LOG_EVENT_UNSUBSCRIPTION'));
                    return;
                }
                elseif (!$objEvent->allowDeregistration)
                {
                    $errorMsg = $objEvent->allowDeregistration . 'Du kannst dich vom Event "' . $objEvent->title . '" nicht abmelden. Die Anmeldung ist definitiv. Nimm, falls nötig, Kontakt mit dem Event-Organisator auf.';
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

                    $arrTokens = array(
                        'event_name' => $objEvent->title,
                        'instructor_name' => $objInstructor->name,
                        'instructor_email' => $objInstructor->email,
                        'participant_name' => $objEventsMember->firstname . ' ' . $objEventsMember->lastname,
                        'participant_email' => $objEventsMember->email,
                        'event_link_detail' => 'https://' . Environment::get('host') . '/' . Events::generateEventUrl($objEvent),
                        'sac_member_id' => $objEventsMember->sacMemberId != '' ? $objEventsMember->sacMemberId : 'keine'
                    );
                    Message::add('Du hast dich vom Event "' . $objEventsMember->eventName . '" abgemeldet. Der Leiter wurde per E-Mail informiert. Zur Bestätigung findest du in deinem Postfach eine Kopie dieser Nachricht.', 'TL_INFO', TL_MODE);

                    // Log
                    System::log(sprintf('User with SAC-User-ID %s has unsubscribed himself from event with ID: %s ("%s")', $objEventsMember->sacMemberId, $objEventsMember->pid, $objEventsMember->eventName), __FILE__ . ' Line: ' . __LINE__, Config::get('SAC_EVT_LOG_EVENT_UNSUBSCRIPTION'));

                    $objNotification->send($arrTokens, 'de');

                    // Delete from tl_calendar_events_member
                    $objEventsMember->delete();
                    $blnHasError = false;
                }
                else
                {
                    $errorMsg = 'Es ist ein Fehler aufgetreten. Du konntest nicht vom Event "' . $objEvent->title . '" abgemeldet werden. Nimm, falls nötig, Kontakt mit dem Event-Organisator auf.';
                    $blnHasError = true;
                }
            }
        }
        if ($blnHasError)
        {
            Message::add($errorMsg, 'TL_ERROR', TL_MODE);
        }
    }

    /**
     * Generate the avatar upload form
     * @return Form
     */
    protected function generateAvatarForm()
    {

        $objForm = new Form('form-avatar-upload', 'POST', function ($objHaste) {
            return Input::post('FORM_SUBMIT') === $objHaste->getFormId();
        });


        $objForm->setFormActionFromUri(Environment::get('uri'));

        // Now let's add form fields:
        $objForm->addFormField('avatar', array(
            'label' => 'Profilbild',
            'inputType' => 'upload',
            'eval' => array('mandatory' => false)
        ));
        $objForm->addFormField('delete-avatar', array(
            'label' => array('', 'Profilbild l&ouml;schen'),
            'inputType' => 'checkbox',
        ));

        // Let's add  a submit button
        $objForm->addFormField('submit', array(
            'label' => 'Speichern',
            'inputType' => 'submit',
            'extensions' => 'jpg,jpeg'
        ));


        $objWidget = $objForm->getWidget('avatar');
        $objWidget->extensions = 'jpg,jpeg';
        $objWidget->storeFile = true;
        $objWidget->uploadFolder = FilesModel::findByPath(Config::get('SAC_EVT_FE_USER_AVATAR_DIRECTORY'))->uuid;
        $objWidget->addAttribute('accept', '.jpg,.jpeg');

        // Delete avatar
        if (Input::post('FORM_SUBMIT') === 'form-avatar-upload' && Input::post('delete-avatar'))
        {
            $objFile = new File(Config::get('SAC_EVT_FE_USER_AVATAR_DIRECTORY') . '/avatar-' . $this->objUser->id . '.jpeg', true);
            if($objFile->exists())
            {
                $objFile->delete();
            }
        }

        // Standardize name
        if (Input::post('FORM_SUBMIT') === 'form-avatar-upload' && !empty($_FILES['avatar']['tmp_name']))
        {
            $_FILES['avatar']['name'] = 'avatar-' . $this->objUser->id . '.jpeg';
        }
        $objForm->validate();


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
        $objForm->addFormField('vegetarian', array(
            'label' => 'Vegetarier',
            'inputType' => 'select',
            'options' => array('false'=> 'Nein', 'true' => 'Ja'),
            'eval' => array('mandatory' => true)
        ));
        $objForm->addFormField('emergencyPhone', array(
            'label' => 'Notfallnummer',
            'inputType' => 'text',
            'eval' => array('rgxp' => 'phone', 'mandatory' => true)
        ));
        $objForm->addFormField('emergencyPhoneName', array(
            'label' => 'Name des Angeh&ouml;rigen',
            'inputType' => 'text',
            'eval' => array('mandatory' => true)
        ));

        // Let's add  a submit button
        $objForm->addFormField('submit', array(
            'label' => 'Speichern',
            'inputType' => 'submit',
        ));

        // Get form presets from tl_member
        $arrFields = array('emergencyPhone', 'emergencyPhoneName', 'vegetarian');
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


        if ($objForm->validate()) {
            // The model will now contain the changes so you can save it
            $objModel->save();
        }


        return $objForm->generate();
    }


}
