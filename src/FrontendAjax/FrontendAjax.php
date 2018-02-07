<?php

/**
 * SAC Event Tool Web Plugin for Contao
 * Copyright (c) 2008-2017 Marko Cupic
 * @package sac-event-tool-bundle
 * @author Marko Cupic m.cupic@gmx.ch, 2017-2018
 * @link https://sac-kurse.kletterkader.com
 */

declare(strict_types=1);

namespace Markocupic\SacEventToolBundle\FrontendAjax;

use Contao\CalendarEventsModel;
use Contao\CalendarEventsStoryModel;
use Contao\Database;
use Contao\Environment;
use Contao\File;
use Contao\FilesModel;
use Contao\FrontendUser;
use Contao\Input;
use Contao\ModuleModel;
use Contao\StringUtil;
use Contao\UserModel;
use Contao\Validator;
use NotificationCenter\Model\Notification;
use Symfony\Component\HttpFoundation\JsonResponse;


/**
 * Class FrontendAjax
 * @package Markocupic\SacEventToolBundle\FrontendAjax
 */
class FrontendAjax
{

    /**
     * Course list filter
     */
    public function filterTourList()
    {
        $visibleItems = array();
        $arrIDS = json_decode(Input::post('ids'));
        $arrOrganizers = json_decode(Input::post('organizers'));
        $strSearchterm = trim(Input::post('searchterm'));
        $idTourType = Input::post('tourtype');
        $intStartDate = round(Input::post('startDate'));

        if ($intStartDate < 1)
        {
            if (!Input::post('year') || Input::post('year') < 1)
            {
                $intStartDate = time() - 24 * 60 * 60;
            }
        }

        foreach ($arrIDS as $id)
        {
            $objEvent = CalendarEventsModel::findByPk($id);
            if ($objEvent !== null)
            {
                $filter = false;

                // startDate
                if ($filter === false)
                {
                    if ($objEvent->startDate < $intStartDate)
                    {
                        $filter = true;
                    }
                }


                // tourtype (climbing, ski, via ferrata, etc) multiple=true
                if ($filter === false)
                {
                    if ($idTourType > 0 && !in_array($idTourType, StringUtil::deserialize($objEvent->tourType, true)))
                    {
                        $filter = true;
                    }
                }


                // ogs
                if ($filter === false)
                {
                    if (count(array_intersect($arrOrganizers, StringUtil::deserialize($objEvent->organizers, true))) < 1)
                    {
                        $filter = true;
                    }
                }


                // Textsuche
                if ($filter === false)
                {

                    if ($strSearchterm != '')
                    {

                        $treffer = 0;


                        foreach (explode(' ', $strSearchterm) as $strNeedle)
                        {
                            if ($treffer)
                            {
                                continue;
                            }

                            // Suche nach Namen des Kursleiters
                            $strLeiter = implode(', ', array_map(function ($userId) {
                                return UserModel::findByPk($userId)->name;
                            }, StringUtil::deserialize($objEvent->instructor, true)));

                            if ($treffer == 0)
                            {
                                if ($this->_textSearch($strNeedle, $strLeiter))
                                {
                                    $treffer++;
                                }
                            }

                            if ($treffer == 0)
                            {
                                // Suchbegriff im Titel suchen
                                if ($this->_textSearch($strNeedle, $objEvent->title))
                                {
                                    $treffer++;
                                }
                            }

                            if ($treffer == 0)
                            {
                                // Suchbegriff im Teaser suchen
                                if ($this->_textSearch($strNeedle, $objEvent->teaser))
                                {
                                    $treffer++;
                                }
                            }

                            if ($treffer == 0)
                            {
                                // Suchbegriff im tourDetailText suchen
                                if ($this->_textSearch($strNeedle, $objEvent->tourDetailText))
                                {
                                    $treffer++;
                                }
                            }
                        }


                        if ($treffer < 1)
                        {
                            $filter = true;
                        }
                    }
                }


                // All ok, this item will not be filtered
                if ($filter === false)
                {
                    $visibleItems[] = $objEvent->id;
                }
            }
        }

        $response = new JsonResponse(array('status' => 'success', 'filter' => $visibleItems));
        return $response->send();

    }

    /**
     * Helper method of filterKursliste
     * @param $strNeedle
     * @param $strHaystack
     * @return bool
     */
    private function _textSearch($strNeedle = '', $strHaystack = '')
    {
        if (trim($strNeedle) == '')
        {
            return true;
        }
        else
        {
            if (stripos($strHaystack, trim($strNeedle)) !== false)
            {
                return true;
            }
        }
        return false;
    }

    /**
     * Course list filter
     */
    public function filterCourseList()
    {
        $visibleItems = array();
        $arrIDS = json_decode(Input::post('ids'));
        $arrOrganizers = json_decode(Input::post('organizers'));
        $strSearchterm = trim(Input::post('searchterm'));
        $courseTypeId = Input::post('courseType');
        $intStartDate = round(Input::post('startDate'));


        if ($intStartDate < 1)
        {
            if (!Input::post('year') || Input::post('year') < 1)
            {
                $intStartDate = (int)time() - (24 * 60 * 60);
            }
        }

        foreach ($arrIDS as $id)
        {
            $objEvent = CalendarEventsModel::findByPk($id);
            if ($objEvent !== null)
            {
                $filter = false;

                // startDate
                if ($filter === false)
                {
                    if ($objEvent->startDate < $intStartDate)
                    {
                        $filter = true;
                    }
                }


                // courseType
                if ($filter === false)
                {
                    if ($courseTypeId > 0 && !in_array($courseTypeId, StringUtil::deserialize($objEvent->courseTypeLevel1, true)))
                    {
                        $filter = true;
                    }
                }


                // organizers
                if ($filter === false)
                {
                    if (count(array_intersect($arrOrganizers, StringUtil::deserialize($objEvent->organizers, true))) < 1)
                    {
                        $filter = true;
                    }
                }


                // Textsuche
                if ($filter === false)
                {

                    if ($strSearchterm != '')
                    {
                        $treffer = 0;

                        foreach (explode(' ', $strSearchterm) as $strNeedle)
                        {
                            if ($treffer)
                            {
                                continue;
                            }


                            // Suche nach Namen des Kursleiters
                            $strLeiter = implode(', ', array_map(function ($userId) {
                                return UserModel::findByPk($userId)->name;
                            }, StringUtil::deserialize($objEvent->instructor, true)));

                            if ($treffer == 0)
                            {
                                if ($this->_textSearch($strNeedle, $strLeiter))
                                {
                                    $treffer++;
                                }
                            }

                            if ($treffer == 0)
                            {
                                // Suchbegriff im Titel suchen
                                if ($this->_textSearch($strNeedle, $objEvent->title))
                                {
                                    $treffer++;
                                }
                            }

                            if ($treffer == 0)
                            {
                                // Suchbegriff im Teaser suchen
                                if ($this->_textSearch($strNeedle, $objEvent->teaser))
                                {
                                    $treffer++;
                                }
                            }
                        }


                        if ($treffer < 1)
                        {
                            $filter = true;
                        }

                    }
                }


                // All ok, this item will not be filtered
                if ($filter === false)
                {
                    $visibleItems[] = $objEvent->id;
                }
            }
        }

        $response = new JsonResponse(array('status' => 'success', 'filter' => $visibleItems));
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
        $objDb = Database::getInstance()->prepare('SELECT * FROM tl_calendar_events_story WHERE sacMemberId=? AND pid=?')->limit(1)->execute($objUser->sacMemberId, Input::post('eventId'));
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
        $objDb = Database::getInstance()->prepare('SELECT * FROM tl_calendar_events_story WHERE sacMemberId=? && pid=? && publishState<?')->limit(1)->execute($objUser->sacMemberId, Input::post('eventId'), 3);
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

                if ($objEvent !== null)
                {
                    $arrTokens = array(
                        'event_title'          => $objEvent->title,
                        'event_id'             => $objEvent->id,
                        'instructor_name'      => $instructorName != '' ? $instructorName : 'keine Angabe',
                        'instructor_email'     => $instructorEmail != '' ? $instructorEmail : 'keine Angabe',
                        'author_name'          => $objUser->firstname . ' ' . $objUser->lastname,
                        'author_email'         => $objUser->email,
                        'author_sac_member_id' => $objUser->sacMemberId,
                        'hostname'             => Environment::get('host'),
                        'story_link'           => Environment::get('url') . '/contao?do=sac_calendar_events_stories_tool&act=edit&id=' . $objStory->id,
                        'story_title'          => $objStory->title,
                        'story_text'           => $objStory->text,
                    );
                }

                // Send notification
                $objNotification->send($arrTokens, 'de');
            }
        }


        // Save publis state
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
        $objDb = Database::getInstance()->prepare('SELECT * FROM tl_calendar_events_story WHERE sacMemberId=? && pid=? && publishState<?')->limit(1)->execute($objUser->sacMemberId, Input::post('eventId'), 3);
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
     * Get caption of an image in the event story module in the member dashboard
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

                $response = new JsonResponse(array(
                    'status'  => 'success',
                    'caption' => html_entity_decode($caption),
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
                        'title'   => '',
                        'alt'     => '',
                        'link'    => '',
                        'caption' => '',
                    );
                }
                $arrMeta['de']['caption'] = Input::post('caption');
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