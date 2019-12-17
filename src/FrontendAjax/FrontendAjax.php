<?php

/**
 * SAC Event Tool Web Plugin for Contao
 * Copyright (c) 2008-2020 Marko Cupic
 * @package sac-event-tool-bundle
 * @author Marko Cupic m.cupic@gmx.ch, 2017-2020
 * @link https://github.com/markocupic/sac-event-tool-bundle
 */

declare(strict_types=1);

namespace Markocupic\SacEventToolBundle\FrontendAjax;

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
use Contao\Validator;
use Haste\Util\Url;
use Markocupic\SacEventToolBundle\CalendarEventsHelper;
use NotificationCenter\Model\Notification;
use Symfony\Component\HttpFoundation\JsonResponse;

/**
 * Class FrontendAjax
 * @package Markocupic\SacEventToolBundle\FrontendAjax
 */
class FrontendAjax
{

    /**
     * Ajax lazyload for the calendar event list module
     * @return JsonResponse
     * @throws \Exception
     */
    public function getEventData()
    {
        $arrJSON = [];

        $arrData = json_decode(Input::post('data'));
        foreach ($arrData as $i => $v)
        {
            // $v[0] is the event id
            $objEvent = CalendarEventsModel::findByPk($v[0]);
            if ($objEvent !== null)
            {
                // $v[1] fieldname/property
                $strHtml = CalendarEventsHelper::getEventData($objEvent, $v[1]);
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
        if (Input::post('action') !== 'sortGallery' || !Input::post('uuids') || !Input::post('eventId') || !FE_USER_LOGGED_IN)
        {
            $response = new JsonResponse(array('status' => 'error'));
            return $response->send();
        }

        $objUser = FrontendUser::getInstance();
        if ($objUser === null)
        {
            $response = new JsonResponse(array('status' => 'error'));
            return $response->send();
        }

        // Save new image order to db
        $objDb = Database::getInstance()->prepare('SELECT * FROM tl_calendar_events_story WHERE sacMemberId=? AND eventId=?')->limit(1)->execute($objUser->sacMemberId, Input::post('eventId'));
        if (!$objDb->numRows)
        {
            $response = new JsonResponse(array('status' => 'error'));
            return $response->send();
        }

        $objStory = CalendarEventsStoryModel::findByPk($objDb->id);
        if ($objStory === null)
        {
            $response = new JsonResponse(array('status' => 'error'));
            return $response->send();
        }

        $arrSorting = json_decode(Input::post('uuids'));
        $arrSorting = array_map(function ($uuid) {
            return StringUtil::uuidToBin($uuid);
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
        if (Input::post('action') !== 'setPublishState' || !Input::post('eventId') || !FE_USER_LOGGED_IN)
        {
            $response = new JsonResponse(array('status' => 'error'));
            return $response->send();
        }

        $objUser = FrontendUser::getInstance();
        if ($objUser === null)
        {
            $response = new JsonResponse(array('status' => 'error'));
            return $response->send();
        }

        // Save new image order to db
        $objDb = Database::getInstance()->prepare('SELECT * FROM tl_calendar_events_story WHERE sacMemberId=? && eventId=? && publishState<?')->limit(1)->execute($objUser->sacMemberId, Input::post('eventId'), 3);
        if (!$objDb->numRows)
        {
            $response = new JsonResponse(array('status' => 'error'));
            return $response->send();
        }

        $objStory = CalendarEventsStoryModel::findByPk($objDb->id);
        if ($objStory === null)
        {
            $response = new JsonResponse(array('status' => 'error'));
            return $response->send();
        }
        // Notify office if there is a new story
        if (Input::post('publishState') == 2 && $objStory->publishState < 2 && Input::post('moduleId'))
        {
            $objModule = ModuleModel::findByPk(Input::post('moduleId'));
            if ($objModule !== null)
            {
                // Use terminal42/notification_center
                $objNotification = Notification::findByPk($objModule->notifyOnEventStoryPublishedNotificationId);
            }

            if (null !== $objNotification && null !== $objUser && Input::post('eventId') > 0)
            {
                $objEvent = CalendarEventsModel::findByPk(Input::post('eventId'));
                $objInstructor = UserModel::findByPk($objEvent->mainInstructor);
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
                    $objTarget = PageModel::findByPk($objModule->eventStoryJumpTo);
                    if ($objTarget !== null)
                    {
                        $previewLink = ampersand($objTarget->getFrontendUrl(Config::get('useAutoItem') ? '/%s' : '/items/%s'));
                        $previewLink = sprintf($previewLink, $objStory->id);
                        $previewLink = Environment::get('url') . '/' . Url::addQueryString('securityToken=' . $objStory->securityToken, $previewLink);
                    }
                }

                // Notify webmaster
                $arrNotifyEmail = array();
                $arrOrganizers = StringUtil::deserialize($objEvent->organizers, true);
                foreach ($arrOrganizers as $orgId)
                {
                    $objEventOrganizer = EventOrganizerModel::findByPk($orgId);
                    if ($objEventOrganizer !== null)
                    {
                        $arrUsers = StringUtil::deserialize($objEventOrganizer->notifyWebmasterOnNewEventStory, true);
                        foreach ($arrUsers as $userId)
                        {
                            $objWebmaster = UserModel::findByPk($userId);
                            if ($objWebmaster !== null)
                            {
                                if ($objWebmaster->email != '')
                                {
                                    if (Validator::isEmail($objWebmaster->email))
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
                        'hostname'             => Environment::get('host'),
                        'story_link_backend'   => Environment::get('url') . '/contao?do=sac_calendar_events_stories_tool&act=edit&id=' . $objStory->id,
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
        $objStory->publishState = Input::post('publishState');
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
        if (!Input::post('eventId') || !Input::post('uuid') || !FE_USER_LOGGED_IN)
        {
            $response = new JsonResponse(array('status' => 'error'));
            return $response->send();
        }

        $objUser = FrontendUser::getInstance();
        if ($objUser === null)
        {
            $response = new JsonResponse(array('status' => 'error'));
            return $response->send();
        }
        // Save new image order to db
        $objDb = Database::getInstance()->prepare('SELECT * FROM tl_calendar_events_story WHERE sacMemberId=? && eventId=? && publishState<?')->limit(1)->execute($objUser->sacMemberId, Input::post('eventId'), 3);
        if (!$objDb->numRows)
        {
            $response = new JsonResponse(array('status' => 'error'));
            return $response->send();
        }
        $objStory = CalendarEventsStoryModel::findByPk($objDb->id);
        if ($objStory === null)
        {
            $response = new JsonResponse(array('status' => 'error'));
            return $response->send();
        }
        $multiSrc = deserialize($objStory->multiSRC, true);
        $orderSrc = deserialize($objStory->orderSRC, true);

        $uuid = StringUtil::uuidToBin(Input::post('uuid'));

        if (!Validator::isUuid($uuid))
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
        $objFile = FilesModel::findByUuid($uuid);
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
        // Get the image rotate service
        $objFiles = FilesModel::findOneById($fileId);
        $objRotateImage = System::getContainer()->get('markocupic.sac_event_tool_bundle.services.image.rotate_image');
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
        if (Input::post('action') === 'getCaption' && Input::post('fileUuid') != '' && FE_USER_LOGGED_IN)
        {
            $objUser = FrontendUser::getInstance();
            if ($objUser === null)
            {
                $response = new JsonResponse(array('status' => 'error'));
                return $response->send();
            }

            $objFile = FilesModel::findByUuid(Input::post('fileUuid'));
            if ($objFile !== null)
            {
                $arrMeta = \StringUtil::deserialize($objFile->meta, true);
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
        if (Input::post('action') === 'setCaption' && Input::post('fileUuid') != '' && FE_USER_LOGGED_IN)
        {
            $objUser = FrontendUser::getInstance();
            if ($objUser === null)
            {
                $response = new JsonResponse(array('status' => 'error'));
                return $response->send();
            }

            $objFile = FilesModel::findByUuid(Input::post('fileUuid'));
            if ($objFile !== null)
            {
                $arrMeta = \StringUtil::deserialize($objFile->meta, true);
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
                $arrMeta['de']['caption'] = Input::post('caption');
                $arrMeta['de']['photographer'] = Input::post('photographer') ?: $objUser->firstname . ' ' . $objUser->lastname;

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
