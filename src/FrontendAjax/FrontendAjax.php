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

use Contao\Input;
use Contao\CalendarEventsModel;
use Contao\StringUtil;
use Contao\UserModel;
use Contao\CalendarEventsStoryModel;
use Contao\Validator;
use Contao\Database;
use Contao\FrontendUser;
use Contao\File;
use Contao\FilesModel;
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
        $arrOGS = json_decode(Input::post('ogs'));
        $strSuchbegriff = trim(Input::post('suchbegriff'));
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
                    if (count(array_intersect($arrOGS, StringUtil::deserialize($objEvent->organizers, true))) < 1)
                    {
                        $filter = true;
                    }
                }


                // Textsuche
                if ($filter === false)
                {

                    if ($strSuchbegriff != '')
                    {

                        $treffer = 0;

                        // Suche nach Namen des Kursleiters
                        $strLeiter = implode(', ', array_map(function ($userId) {
                            return UserModel::findByPk($userId)->name;
                        }, StringUtil::deserialize($objEvent->instructor, true)));

                        if ($treffer == 0)
                        {
                            if ($this->_textSearch($strSuchbegriff, $strLeiter))
                            {
                                $treffer++;
                            }
                        }

                        if ($treffer == 0)
                        {
                            // Suchbegriff im Titel suchen
                            if ($this->_textSearch($strSuchbegriff, $objEvent->title))
                            {
                                $treffer++;
                            }
                        }

                        if ($treffer == 0)
                        {
                            // Suchbegriff im Teaser suchen
                            if ($this->_textSearch($strSuchbegriff, $objEvent->teaser))
                            {
                                $treffer++;
                            }
                        }

                        if ($treffer == 0)
                        {
                            // Suchbegriff im tourDetailText suchen
                            if ($this->_textSearch($strSuchbegriff, $objEvent->tourDetailText))
                            {
                                $treffer++;
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
     * Course list filter
     */
    public function filterCourseList()
    {
        $visibleItems = array();
        $arrIDS = json_decode(Input::post('ids'));
        $arrOGS = json_decode(Input::post('ogs'));
        $strSuchbegriff = trim(Input::post('suchbegriff'));
        $idKursart = Input::post('kursart');
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


                // kursart
                if ($filter === false)
                {
                    if ($idKursart > 0 && !in_array($idKursart,
                            StringUtil::deserialize($objEvent->courseTypeLevel1, true))
                    )
                    {
                        $filter = true;
                    }
                }


                // ogs
                if ($filter === false)
                {
                    if (count(array_intersect($arrOGS, StringUtil::deserialize($objEvent->organizers, true))) < 1)
                    {
                        $filter = true;
                    }
                }


                // Textsuche
                if ($filter === false)
                {

                    if ($strSuchbegriff != '')
                    {

                        $treffer = 0;

                        // Suche nach Namen des Kursleiters
                        $strLeiter = implode(', ', array_map(function ($userId) {
                            return UserModel::findByPk($userId)->name;
                        }, StringUtil::deserialize($objEvent->instructor, true)));

                        if ($treffer == 0)
                        {
                            if ($this->_textSearch($strSuchbegriff, $strLeiter))
                            {
                                $treffer++;
                            }
                        }

                        if ($treffer == 0)
                        {
                            // Suchbegriff im Titel suchen
                            if ($this->_textSearch($strSuchbegriff, $objEvent->title))
                            {
                                $treffer++;
                            }
                        }

                        if ($treffer == 0)
                        {
                            // Suchbegriff im Teaser suchen
                            if ($this->_textSearch($strSuchbegriff, $objEvent->teaser))
                            {
                                $treffer++;
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
}