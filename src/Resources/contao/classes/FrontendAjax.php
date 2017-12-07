<?php

/**
 * SAC Event Tool Web Plugin for Contao
 * Copyright (c) 2008-2017 Marko Cupic
 * @package sac-event-tool-bundle
 * @author Marko Cupic m.cupic@gmx.ch, 2017
 * @link    https://sac-kurse.kletterkader.com
 */

namespace Markocupic\SacEventToolBundle;

use Contao\Input;
use Contao\CalendarEventsModel;
use Contao\StringUtil;
use Contao\UserModel;
use Contao\Environment;
use Contao\CalendarEventsStoryModel;
use Contao\Validator;
use Contao\Database;
use Contao\FrontendUser;
use Contao\File;
use Contao\FilesModel;
use Contao\Email;


/**
 * Class FrontendAjax
 * @package Markocupic\SacEventToolBundle
 */
class FrontendAjax
{
    /**
     * FrontendAjax constructor.
     */
    public function __construct()
    {
        //
    }


    public function __call($strMethod, $args)
    {
        return;
    }

    public function generateAjax()
    {
        // xhrAction=filterKursliste
        // xhrAction=sortGallery
        // xhrAction=setPublishState
        // xhrAction=removeImage
        // xhrAction=setPublishState

        // GET xhrAction param from GET or POST
        $xhrAction = null;
        if (Input::post('xhrAction'))
        {
            $xhrAction = Input::post('xhrAction');
        }
        elseif (Input::get('xhrAction'))
        {
            $xhrAction = Input::get('xhrAction');
        }


        if (Environment::get('isAjaxRequest') && $xhrAction != '')
        {
            $this->{$xhrAction}();
            exit;
        }
    }

    /**
     * Course list filter
     */
    protected function filterTourList()
    {
        $visibleItems = array();
        $arrIDS = json_decode(Input::post('ids'));
        $arrOGS = json_decode(Input::post('ogs'));
        $strSuchbegriff = trim(Input::post('suchbegriff'));
        $idTourType = Input::post('tourtype');
        $intStartDate = round(Input::post('startDate'));

        if ($intStartDate < 1)
        {
            if (!Input::get('year') || Input::get('year') < 1)
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
        echo json_encode(array('filter' => $visibleItems));
        exit;
    }


    /**
     * Course list filter
     */
    protected function filterCourseList()
    {
        $visibleItems = array();
        $arrIDS = json_decode(Input::post('ids'));
        $arrOGS = json_decode(Input::post('ogs'));
        $strSuchbegriff = trim(Input::post('suchbegriff'));
        $idKursart = Input::post('kursart');
        $intStartDate = round(Input::post('startDate'));

        if ($intStartDate < 1)
        {
            if (!Input::get('year') || Input::get('year') < 1)
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
        echo json_encode(array('filter' => $visibleItems));
        exit;
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
    protected function sortGallery()
    {

        if (!Input::post('uuids') || !Input::get('eventId') || !FE_USER_LOGGED_IN)
        {
            return;
        }

        $objUser = FrontendUser::getInstance();
        if ($objUser === null)
        {
            return;
        }

        // Save new image order to db
        $objDb = Database::getInstance()->prepare('SELECT * FROM tl_calendar_events_story WHERE sacMemberId=? AND pid=?')->limit(1)->execute($objUser->sacMemberId, Input::get('eventId'));
        if (!$objDb->numRows)
        {
            return;
        }
        $objStory = CalendarEventsStoryModel::findByPk($objDb->id);
        if ($objStory === null)
        {
            return;
        }
        $arrSorting = json_decode(Input::post('uuids'));
        $arrSorting = array_map(function ($uuid) {
            return StringUtil::uuidToBin($uuid);
        }, $arrSorting);

        $objStory->orderSRC = serialize($arrSorting);
        $objStory->save();
    }


    /**
     * Ajax call
     * Set the publish state of the event story in the event story module in the member dashboard
     */
    protected function setPublishState()
    {
        if (!Input::post('publishState') || !Input::get('eventId') || !FE_USER_LOGGED_IN)
        {
            return;
        }

        $objUser = FrontendUser::getInstance();
        if ($objUser === null)
        {
            return;
        }

        // Save new image order to db
        $objDb = Database::getInstance()->prepare('SELECT * FROM tl_calendar_events_story WHERE sacMemberId=? && pid=? && publishState<?')->limit(1)->execute($objUser->sacMemberId, Input::get('eventId'), 3);
        if (!$objDb->numRows)
        {
            return;
        }
        $objStory = CalendarEventsStoryModel::findByPk($objDb->id);
        if ($objStory === null)
        {
            return;
        }

        $objStory->publishState = Input::post('publishState');
        $objStory->save();
    }

    /**
     * Ajax call
     * Remove image from collection in the event story module in the member dashboard
     */
    protected function removeImage()
    {
        if (!Input::get('eventId') || !Input::post('uuid') || !FE_USER_LOGGED_IN)
        {
            return;
        }

        $objUser = FrontendUser::getInstance();
        if ($objUser === null)
        {
            return;
        }
        // Save new image order to db
        $objDb = Database::getInstance()->prepare('SELECT * FROM tl_calendar_events_story WHERE sacMemberId=? && pid=? && publishState<?')->limit(1)->execute($objUser->sacMemberId, Input::get('eventId'), 3);
        if (!$objDb->numRows)
        {
            return;
        }
        $objStory = CalendarEventsStoryModel::findByPk($objDb->id);
        if ($objStory === null)
        {
            return;
        }
        $multiSrc = deserialize($objStory->multiSRC, true);
        $orderSrc = deserialize($objStory->orderSRC, true);

        $uuid = StringUtil::uuidToBin(Input::post('uuid'));

        if (!Validator::isUuid($uuid))
        {
            return;
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
        return;
    }
}