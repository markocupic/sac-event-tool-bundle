<?php

/**
 * SAC Event Tool Web Plugin for Contao
 * Copyright (c) 2008-2019 Marko Cupic
 * @package sac-event-tool-bundle
 * @author Marko Cupic m.cupic@gmx.ch, 2017-2019
 * @link https://github.com/markocupic/sac-event-tool-bundle
 */

namespace Markocupic\SacEventToolBundle\ContaoHooks;

use Contao\CalendarEventsStoryModel;
use Contao\Config;
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\Dbafs;
use Contao\File;
use Contao\FilesModel;
use Contao\Folder;
use Contao\FrontendUser;
use Contao\StringUtil;
use Contao\System;
use Contao\Widget;

/**
 * Class ValidateForms
 * @package Markocupic\SacEventToolBundle\ContaoHooks
 */
class ValidateForms
{
    /**
     * @var ContaoFramework
     */
    private $framework;

    /**
     * @var
     */
    private $eventStoriesUploadPath;

    /**
     * @var \Contao\CoreBundle\Framework\Adapter
     */
    private $input;

    /**
     * @var
     */
    private $system;

    /**
     * @var
     */
    private $feUser;

    /**
     * @var
     */
    private $database;

    /**
     * @var
     */
    private $calendarEventsModelAdapter;

    /**
     * @var
     */
    private $userModelAdapter;

    /**
     * @var
     */
    private $filesModelAdapter;

    /**
     * ValidateForms constructor.
     * @param ContaoFramework $framework
     */
    public function __construct(ContaoFramework $framework)
    {
        $this->framework = $framework;

        $this->eventStoriesUploadPath = Config::get('SAC_EVT_EVENT_STORIES_UPLOAD_PATH');

        $this->input = $this->framework->getAdapter(\Contao\Input::class);

        $this->system = $this->framework->getAdapter(\Contao\System::class);

        $this->feUser = $this->system->importStatic('FrontendUser');

        $this->database = $this->system->importStatic('Database');

        $this->calendarEventsModelAdapter = $this->framework->getAdapter(\Contao\CalendarEventsModel::class);

        $this->filesModelAdapter = $this->framework->getAdapter(\Contao\FilesModel::class);

        $this->userModelAdapter = $this->framework->getAdapter(\Contao\UserModel::class);

    }

    /**
     * @param $arrTarget
     */
    public function postUpload($arrTarget)
    {

    }


    /**
     * @param $arrFields
     * @param $formId
     * @param \Form $objForm
     * @return mixed
     */
    public function compileFormFields($arrFields, $formId, $objForm)
    {
        return $arrFields;
    }


    /**
     * @param \Widget $objWidget
     * @param $strForm
     * @param $arrForm
     * @param $objForm
     * @return \Widget
     */
    public function loadFormField(Widget $objWidget, $strForm, $arrForm, $objForm)
    {


        return $objWidget;
    }


    /**
     * @param $objWidget
     * @param $formId
     * @param $arrData
     * @param \Form $objForm
     * @return mixed
     */
    public function validateFormField(Widget $objWidget, $formId, $arrForm, $objForm)
    {

        return $objWidget;
    }


    /**
     * @param $arrSubmitted
     * @param $arrLabels
     * @param $arrFields
     * @param \Form $objForm
     */
    public function prepareFormData($arrSubmitted, $arrLabels, $arrFields, $objForm)
    {

    }


    /**
     * @param $arrSet
     * @param \Form $objForm
     * @return mixed
     */
    public function storeFormData($arrSet, $objForm)
    {
        return $arrSet;
    }


    /**
     * @param $arrSubmitted
     * @param $arrForm
     * @param $arrFiles
     * @param $arrLabels
     * @param \Form $objForm
     */
    public function processFormData($arrSubmitted, $arrForm, $arrFiles, $arrLabels, $objForm)
    {
        // Get root dir
        $rootDir = System::getContainer()->getParameter('kernel.project_dir');


    }
}