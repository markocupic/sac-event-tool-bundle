<?php

/**
 * SAC Event Tool Web Plugin for Contao
 * Copyright (c) 2008-2020 Marko Cupic
 * @package sac-event-tool-bundle
 * @author Marko Cupic m.cupic@gmx.ch, 2017-2020
 * @link https://github.com/markocupic/sac-event-tool-bundle
 */

declare(strict_types=1);

namespace Markocupic\SacEventToolBundle\Services\FrontendAjax;

use Contao\CalendarEventsModel;
use Contao\CalendarEventsStoryModel;
use Contao\Config;
use Contao\Database;
use Contao\Environment;
use Contao\EventOrganizerModel;
use Contao\File;
use Contao\FilesModel;
use Contao\FrontendUser;
use Contao\Input;
use Contao\ModuleModel;
use Contao\PageModel;
use Contao\StringUtil;
use Contao\UserModel;
use Contao\System;
use Contao\Validator;
use Haste\Util\Url;
use Markocupic\SacEventToolBundle\CalendarEventsHelper;
use NotificationCenter\Model\Notification;
use Symfony\Component\HttpFoundation\JsonResponse;
use Contao\CoreBundle\Framework\ContaoFramework;

/**
 * Class FrontendAjax
 * @package Markocupic\SacEventToolBundle\Services\FrontendAjax
 */
class FrontendAjax
{
    /**
     * @var ContaoFramework
     */
    private $framework;

    /**
     * FrontendAjax constructor.
     * @param ContaoFramework $framework
     */
    public function __construct(ContaoFramework $framework)
    {
        $this->framework = $framework;

        // Initialize contao framework
        $this->framework->initialize();
    }

    /**
     * Ajax lazyload for the calendar event list module
     * @return JsonResponse
     * @throws \Exception
     */
    public function getEventData()
    {
        /** @var  CalendarEventsModel $calendarEventsModelAdapter */
        $calendarEventsModelAdapter = $this->framework->getAdapter(CalendarEventsModel::class);

        /** @var  CalendarEventsHelper $calendarEventsHelperAdapter */
        $calendarEventsHelperAdapter = $this->framework->getAdapter(CalendarEventsHelper::class);

        /** @var Input $inputAdapter */
        $inputAdapter = $this->framework->getAdapter(Input::class);

        $arrJSON = [];

        $arrData = json_decode($inputAdapter->post('data'));
        foreach ($arrData as $i => $v)
        {
            // $v[0] is the event id
            $objEvent = $calendarEventsModelAdapter->findByPk($v[0]);
            if ($objEvent !== null)
            {
                // $v[1] fieldname/property
                $strHtml = $calendarEventsHelperAdapter->getEventData($objEvent, $v[1]);
                $arrData[$i][] = $strHtml;
            }
        }

        $arrJSON['status'] = 'success';
        $arrJSON['data'] = $arrData;

        $response = new JsonResponse($arrJSON);
        return $response->send();
    }

    /**
     * Ajax call
     * Sort pictures of the gallery in the event story module in the member dashboard
     */
    public function sortGallery()
    {
        /** @var  CalendarEventsStoryModel $calendarEventsStoryModelAdapter */
        $calendarEventsStoryModelAdapter = $this->framework->getAdapter(CalendarEventsStoryModel::class);

        /** @var Input $inputAdapter */
        $inputAdapter = $this->framework->getAdapter(Input::class);

        /** @var Database $databaseAdapter */
        $databaseAdapter = $this->framework->getAdapter(Database::class);

        /** @var FrontendUser $frontendUserAdapter */
        $frontendUserAdapter = $this->framework->getAdapter(FrontendUser::class);

        if ($inputAdapter->post('action') !== 'sortGallery' || !$inputAdapter->post('uuids') || !$inputAdapter->post('eventId') || !FE_USER_LOGGED_IN)
        {
            $response = new JsonResponse(array('status' => 'error'));
            return $response->send();
        }

        $objUser = $frontendUserAdapter->getInstance();
        if ($objUser === null)
        {
            $response = new JsonResponse(array('status' => 'error'));
            return $response->send();
        }

        // Save new image order to db
        $objDb = $databaseAdapter->getInstance()->prepare('SELECT * FROM tl_calendar_events_story WHERE sacMemberId=? AND eventId=?')->limit(1)->execute($objUser->sacMemberId, $inputAdapter->post('eventId'));
        if (!$objDb->numRows)
        {
            $response = new JsonResponse(array('status' => 'error'));
            return $response->send();
        }

        $objStory = $calendarEventsStoryModelAdapter->findByPk($objDb->id);
        if ($objStory === null)
        {
            $response = new JsonResponse(array('status' => 'error'));
            return $response->send();
        }

        $arrSorting = json_decode($inputAdapter->post('uuids'));
        $arrSorting = array_map(function ($uuid) {
            /** @var  StringUtil $stringUtilAdapter */
            $stringUtilAdapter = $this->framework->getAdapter(StringUtil::class);
            return $stringUtilAdapter->uuidToBin($uuid);
        }, $arrSorting);

        $objStory->orderSRC = serialize($arrSorting);
        $objStory->save();

        $response = new JsonResponse(array('status' => 'success'));
        return $response->send();
    }

    /**
     * Ajax call
     * Set the publish state of the event story in the event story module in the member dashboard
     */
    public function setPublishState()
    {
        /** @var  CalendarEventsModel $calendarEventsModelAdapter */
        $calendarEventsModelAdapter = $this->framework->getAdapter(CalendarEventsModel::class);

        /** @var  CalendarEventsStoryModel $calendarEventsStoryModelAdapter */
        $calendarEventsStoryModelAdapter = $this->framework->getAdapter(CalendarEventsStoryModel::class);

        /** @var Input $inputAdapter */
        $inputAdapter = $this->framework->getAdapter(Input::class);

        /** @var  StringUtil $stringUtilAdapter */
        $stringUtilAdapter = $this->framework->getAdapter(StringUtil::class);

        /** @var  UserModel $userModelAdapter */
        $userModelAdapter = $this->framework->getAdapter(UserModel::class);

        /** @var  Notification $notificationAdapter */
        $notificationAdapter = $this->framework->getAdapter(Notification::class);

        /** @var  ModuleModel $moduleModelAdapter */
        $moduleModelAdapter = $this->framework->getAdapter(ModuleModel::class);

        /** @var Database $databaseAdapter */
        $databaseAdapter = $this->framework->getAdapter(Database::class);

        /** @var FrontendUser $frontendUserAdapter */
        $frontendUserAdapter = $this->framework->getAdapter(FrontendUser::class);

        /** @var PageModel $pageModelAdapter */
        $pageModelAdapter = $this->framework->getAdapter(PageModel::class);

        /** @var Environment $environmentAdapter */
        $environmentAdapter = $this->framework->getAdapter(Environment::class);

        /** @var Url $urlAdapter */
        $urlAdapter = $this->framework->getAdapter(Url::class);

        /** @var EventOrganizerModel $eventOrganizerModelAdapter */
        $eventOrganizerModelAdapter = $this->framework->getAdapter(EventOrganizerModel::class);

        /** @var Validator $validatorAdapter */
        $validatorAdapter = $this->framework->getAdapter(Validator::class);

        if ($inputAdapter->post('action') !== 'setPublishState' || !$inputAdapter->post('eventId') || !FE_USER_LOGGED_IN)
        {
            $response = new JsonResponse(array('status' => 'error'));
            return $response->send();
        }

        $objUser = $frontendUserAdapter->getInstance();
        if ($objUser === null)
        {
            $response = new JsonResponse(array('status' => 'error'));
            return $response->send();
        }

        // Save new image order to db
        $objDb = $databaseAdapter->getInstance()->prepare('SELECT * FROM tl_calendar_events_story WHERE sacMemberId=? && eventId=? && publishState<?')->limit(1)->execute($objUser->sacMemberId, $inputAdapter->post('eventId'), 3);
        if (!$objDb->numRows)
        {
            $response = new JsonResponse(array('status' => 'error'));
            return $response->send();
        }

        $objStory = $calendarEventsStoryModelAdapter->findByPk($objDb->id);
        if ($objStory === null)
        {
            $response = new JsonResponse(array('status' => 'error'));
            return $response->send();
        }
        // Notify office if there is a new story
        if ($inputAdapter->post('publishState') == 2 && $objStory->publishState < 2 && $inputAdapter->post('moduleId'))
        {
            $objModule = $moduleModelAdapter->findByPk($inputAdapter->post('moduleId'));
            if ($objModule !== null)
            {
                // Use terminal42/notification_center
                $objNotification = $notificationAdapter->findByPk($objModule->notifyOnEventStoryPublishedNotificationId);
            }

            if (null !== $objNotification && null !== $objUser && $inputAdapter->post('eventId') > 0)
            {
                $objEvent = $calendarEventsModelAdapter->findByPk($inputAdapter->post('eventId'));
                $objInstructor = $userModelAdapter->findByPk($objEvent->mainInstructor);
                $instructorName = '';
                $instructorEmail = '';
                if ($objInstructor !== null)
                {
                    $instructorName = $objInstructor->name;
                    $instructorEmail = $objInstructor->email;
                }

                // Generate frontend preview link
                $previewLink = '';
                if ($objModule->eventStoryJumpTo > 0)
                {
                    $objTarget = $pageModelAdapter->findByPk($objModule->eventStoryJumpTo);
                    if ($objTarget !== null)
                    {
                        $previewLink = ampersand($objTarget->getFrontendUrl(Config::get('useAutoItem') ? '/%s' : '/items/%s'));
                        $previewLink = sprintf($previewLink, $objStory->id);
                        $previewLink = $environmentAdapter->get('url') . '/' . $urlAdapter->addQueryString('securityToken=' . $objStory->securityToken, $previewLink);
                    }
                }

                // Notify webmaster
                $arrNotifyEmail = array();
                $arrOrganizers = $stringUtilAdapter->deserialize($objEvent->organizers, true);
                foreach ($arrOrganizers as $orgId)
                {
                    $objEventOrganizer = $eventOrganizerModelAdapter->findByPk($orgId);
                    if ($objEventOrganizer !== null)
                    {
                        $arrUsers = $stringUtilAdapter->deserialize($objEventOrganizer->notifyWebmasterOnNewEventStory, true);
                        foreach ($arrUsers as $userId)
                        {
                            $objWebmaster = $userModelAdapter->findByPk($userId);
                            if ($objWebmaster !== null)
                            {
                                if ($objWebmaster->email != '')
                                {
                                    if ($validatorAdapter->isEmail($objWebmaster->email))
                                    {
                                        $arrNotifyEmail[] = $objWebmaster->email;
                                    }
                                }
                            }
                        }
                    }
                }

                $webmasterEmail = implode(',', $arrNotifyEmail);

                if ($objEvent !== null)
                {
                    $arrTokens = array(
                        'event_title'          => $objEvent->title,
                        'event_id'             => $objEvent->id,
                        'instructor_name'      => $instructorName != '' ? $instructorName : 'keine Angabe',
                        'instructor_email'     => $instructorEmail != '' ? $instructorEmail : 'keine Angabe',
                        'webmaster_email'      => $webmasterEmail != '' ? $webmasterEmail : '',
                        'author_name'          => $objUser->firstname . ' ' . $objUser->lastname,
                        'author_email'         => $objUser->email,
                        'author_sac_member_id' => $objUser->sacMemberId,
                        'hostname'             => $environmentAdapter->get('host'),
                        'story_link_backend'   => $environmentAdapter->get('url') . '/contao?do=sac_calendar_events_stories_tool&act=edit&id=' . $objStory->id,
                        'story_link_frontend'  => $previewLink,
                        'story_title'          => $objStory->title,
                        'story_text'           => $objStory->text,
                    );
                }

                // Send notification
                $objNotification->send($arrTokens, 'de');
            }
        }

        // Save publish state
        $objStory->publishState = $inputAdapter->post('publishState');
        $objStory->save();

        $response = new JsonResponse(array('status' => 'success'));
        return $response->send();
    }

    /**
     * Ajax call
     * Remove image from collection in the event story module in the member dashboard
     */
    public function removeImage()
    {
        /** @var  CalendarEventsStoryModel $calendarEventsStoryModelAdapter */
        $calendarEventsStoryModelAdapter = $this->framework->getAdapter(CalendarEventsStoryModel::class);

        /** @var Input $inputAdapter */
        $inputAdapter = $this->framework->getAdapter(Input::class);

        /** @var  StringUtil $stringUtilAdapter */
        $stringUtilAdapter = $this->framework->getAdapter(StringUtil::class);

        /** @var  FilesModel $filesModelAdapter */
        $filesModelAdapter = $this->framework->getAdapter(FilesModel::class);

        /** @var Database $databaseAdapter */
        $databaseAdapter = $this->framework->getAdapter(Database::class);

        /** @var FrontendUser $frontendUserAdapter */
        $frontendUserAdapter = $this->framework->getAdapter(FrontendUser::class);

        /** @var Validator $validatorAdapter */
        $validatorAdapter = $this->framework->getAdapter(Validator::class);

        if (!$inputAdapter->post('eventId') || !$inputAdapter->post('uuid') || !FE_USER_LOGGED_IN)
        {
            $response = new JsonResponse(array('status' => 'error'));
            return $response->send();
        }

        $objUser = $frontendUserAdapter->getInstance();
        if ($objUser === null)
        {
            $response = new JsonResponse(array('status' => 'error'));
            return $response->send();
        }
        // Save new image order to db
        $objDb = $databaseAdapter->getInstance()->prepare('SELECT * FROM tl_calendar_events_story WHERE sacMemberId=? && eventId=? && publishState<?')->limit(1)->execute($objUser->sacMemberId, $inputAdapter->post('eventId'), 3);
        if (!$objDb->numRows)
        {
            $response = new JsonResponse(array('status' => 'error'));
            return $response->send();
        }
        $objStory = $calendarEventsStoryModelAdapter->findByPk($objDb->id);
        if ($objStory === null)
        {
            $response = new JsonResponse(array('status' => 'error'));
            return $response->send();
        }
        $multiSrc = $stringUtilAdapter->deserialize($objStory->multiSRC, true);
        $orderSrc = $stringUtilAdapter->deserialize($objStory->orderSRC, true);

        $uuid = $stringUtilAdapter->uuidToBin($inputAdapter->post('uuid'));

        if (!$validatorAdapter->isUuid($uuid))
        {
            $response = new JsonResponse(array('status' => 'error'));
            return $response->send();
        }

        $key = array_search($uuid, $multiSrc);
        if ($key !== false)
        {
            unset($multiSrc[$key]);
            $multiSrc = array_values($multiSrc);
            $objStory->multiSRC = serialize($multiSrc);
        }

        $key = array_search($uuid, $orderSrc);
        if ($key !== false)
        {
            unset($orderSrc[$key]);
            $orderSrc = array_values($multiSrc);
            $objStory->orderSRC = serialize($orderSrc);
        }

        // Save model
        $objStory->save();

        // Delete image from filesystem and db
        $objFile = $filesModelAdapter->findByUuid($uuid);
        if ($objFile !== null)
        {
            $oFile = new File($objFile->path);
            $oFile->delete();
            $objFile->delete();
        }
        $response = new JsonResponse(array('status' => 'success'));
        return $response->send();
    }

    /**
     * Ajax call
     * Rotate image
     */
    public function rotateImage($fileId)
    {
        /** @var  FilesModel $filesModelAdapter */
        $filesModelAdapter = $this->framework->getAdapter(FilesModel::class);

        // Get the image rotate service
        $objFiles = $filesModelAdapter->findOneById($fileId);
        $objRotateImage = System::getContainer()->get('Markocupic\SacEventToolBundle\Services\Image\RotateImage');
        if ($objRotateImage->rotate($objFiles))
        {
            $json = array('status' => 'success');
        }
        else
        {
            $json = array('status' => 'error');
        }
        $response = new JsonResponse($json);
        return $response->send();
    }

    /**
     * Ajax call
     * Get caption and the photographer of an image in the event story module in the member dashboard
     */
    public function getCaption()
    {
        /** @var Input $inputAdapter */
        $inputAdapter = $this->framework->getAdapter(Input::class);

        /** @var  StringUtil $stringUtilAdapter */
        $stringUtilAdapter = $this->framework->getAdapter(StringUtil::class);

        /** @var  FilesModel $filesModelAdapter */
        $filesModelAdapter = $this->framework->getAdapter(FilesModel::class);

        /** @var FrontendUser $frontendUserAdapter */
        $frontendUserAdapter = $this->framework->getAdapter(FrontendUser::class);

        if ($inputAdapter->post('action') === 'getCaption' && $inputAdapter->post('fileUuid') != '' && FE_USER_LOGGED_IN)
        {
            $objUser = $frontendUserAdapter->getInstance();
            if ($objUser === null)
            {
                $response = new JsonResponse(array('status' => 'error'));
                return $response->send();
            }

            $objFile = $filesModelAdapter->findByUuid($inputAdapter->post('fileUuid'));
            if ($objFile !== null)
            {
                $arrMeta = $stringUtilAdapter->deserialize($objFile->meta, true);
                if (!isset($arrMeta['de']['caption']))
                {
                    $caption = '';
                }
                else
                {
                    $caption = $arrMeta['de']['caption'];
                }

                if (!isset($arrMeta['de']['photographer']))
                {
                    $photographer = $objUser->firstname . ' ' . $objUser->lastname;
                }
                else
                {
                    $photographer = $arrMeta['de']['photographer'];
                    if ($photographer === '')
                    {
                        $photographer = $objUser->firstname . ' ' . $objUser->lastname;
                    }
                }

                $response = new JsonResponse(array(
                    'status'       => 'success',
                    'caption'      => html_entity_decode($caption),
                    'photographer' => $photographer,
                ));
                return $response->send();
            }
        }
        $response = new JsonResponse(array('status' => 'error'));
        return $response->send();
    }

    /**
     * Ajax call
     * Set caption of an image in the event story module in the member dashboard
     */
    public function setCaption()
    {
        /** @var Input $inputAdapter */
        $inputAdapter = $this->framework->getAdapter(Input::class);

        /** @var  StringUtil $stringUtilAdapter */
        $stringUtilAdapter = $this->framework->getAdapter(StringUtil::class);

        /** @var  FilesModel $filesModelAdapter */
        $filesModelAdapter = $this->framework->getAdapter(FilesModel::class);

        /** @var FrontendUser $frontendUserAdapter */
        $frontendUserAdapter = $this->framework->getAdapter(FrontendUser::class);

        if ($inputAdapter->post('action') === 'setCaption' && $inputAdapter->post('fileUuid') != '' && FE_USER_LOGGED_IN)
        {
            $objUser = $frontendUserAdapter->getInstance();
            if ($objUser === null)
            {
                $response = new JsonResponse(array('status' => 'error'));
                return $response->send();
            }

            $objFile = $filesModelAdapter->findByUuid($inputAdapter->post('fileUuid'));
            if ($objFile !== null)
            {
                $arrMeta = $stringUtilAdapter->deserialize($objFile->meta, true);
                if (!isset($arrMeta['de']))
                {
                    $arrMeta['de'] = array(
                        'title'        => '',
                        'alt'          => '',
                        'link'         => '',
                        'caption'      => '',
                        'photographer' => '',
                    );
                }
                $arrMeta['de']['caption'] = $inputAdapter->post('caption');
                $arrMeta['de']['photographer'] = $inputAdapter->post('photographer') ?: $objUser->firstname . ' ' . $objUser->lastname;

                $objFile->meta = serialize($arrMeta);
                $objFile->save();
                $response = new JsonResponse(array(
                    'status' => 'success',
                ));
                return $response->send();
            }
        }
        $response = new JsonResponse(array('status' => 'error'));
        return $response->send();
    }
}
