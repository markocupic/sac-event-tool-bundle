<?php

/**
 * SAC Event Tool Web Plugin for Contao
 * Copyright (c) 2008-2017 Marko Cupic
 * @package sac-event-tool-bundle
 * @author Marko Cupic m.cupic@gmx.ch, 2017
 * @link    https://sac-kurse.kletterkader.com
 */

namespace Markocupic\SacEventToolBundle\ContaoHooks;

use Contao\Dbafs;
use Contao\FilesModel;
use Contao\CalendarEventsStoryModel;
use Contao\CoreBundle\Framework\ContaoFrameworkInterface;
use Contao\Folder;
use Contao\File;
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
     * @var ContaoFrameworkInterface
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
     * @param ContaoFrameworkInterface $framework
     * @param $eventStoriesUploadPath
     */
    public function __construct(ContaoFrameworkInterface $framework)
    {
        $this->framework = $framework;

        $this->eventStoriesUploadPath = SACP_EVENT_STORIES_UPLOAD_PATH;

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

        if ($arrForm['formID'] == 'form-write-event-story-text-and-yt' && FE_USER_LOGGED_IN && $this->input->get('eventId')) {
        	$oEvent = $this->calendarEventsModelAdapter->findByPk($this->input->get('eventId'));
            if ($this->feUser !== null && $oEvent !== null) {
                $objStory = $this->database->prepare('SELECT * FROM tl_calendar_events_story WHERE sacMemberId=? && pid=?')->execute($this->feUser->sacMemberId, $this->input->get('eventId'));
                if ($objStory->numRows) {
                    if ($objWidget->name == 'text') {
                        $objWidget->value = $objStory->text;
                    }

                    if ($objWidget->name == 'youtubeId') {
                        $objWidget->value = $objStory->youtubeId;
                    }
                }
            }
        }
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

        if ($arrForm['formID'] === 'form-write-event-story-text-and-yt' || $arrForm['formID'] === 'form-write-event-story-upload-foto') {

            $oEvent = $this->calendarEventsModelAdapter->findByPk($this->input->get('eventId'));
            if ($this->feUser !== null && $oEvent !== null) {

            	$set = array(
                    'pid' => $this->input->get('eventId'),
                    'sacMemberId' => $this->feUser->sacMemberId,
                    'tstamp' => time(),
                );

            	if($arrSubmitted['youtubeId'])
				{
					$set['youtubeId'] = $this->input->post('youtubeId');
				}
				
				if($arrSubmitted['text'])
				{
					$set['text'] = $this->input->post('text');
				}

                $objStory = $this->database->prepare('SELECT * FROM tl_calendar_events_story WHERE sacMemberId=? && pid=?')->execute($this->feUser->sacMemberId, $this->input->get('eventId'));
                if ($objStory->numRows) {

                    $this->database->prepare('UPDATE tl_calendar_events_story %s WHERE id=?')->set($set)->execute($objStory->id);

                } else {
                    //$set['addedOn'] = time();
                    $this->database->prepare('INSERT INTO tl_calendar_events_story %s')->set($set)->execute();
                }
            }

            
			if($arrForm['formID'] === 'form-write-event-story-upload-foto')
			{
				if (FE_USER_LOGGED_IN) {
					// Manage Fileuploads
					if ($this->input->post('attachfiles')) {
						$eventId = $this->input->get('eventId');
						$objStory = $this->database->prepare('SELECT * FROM tl_calendar_events_story WHERE sacMemberId=? && pid=?')->execute($this->feUser->sacMemberId, $eventId);
						if ($objStory->numRows) {
							$oStoryModel = CalendarEventsStoryModel::findByPk($objStory->id);
							if($oStoryModel !== null)
							{
								$widgetId = $this->input->post('attachfiles');
								$objFile = json_decode($widgetId[0]);
								$arrFiles = $objFile->files;
								$tmpDir = $objFile->addToFile;

								foreach ($arrFiles as $file) {
									$strPath = $this->eventStoriesUploadPath . '/tmp/' . $tmpDir . '/' . $file;
									if (is_file($rootDir . '/' . $strPath)) {
										$objFile = $this->filesModelAdapter->findByPath($strPath);
										if ($objFile !== null) {
											$targetDir = $this->eventStoriesUploadPath . '/' . $objStory->id;
											$fileNewPath = $targetDir . '/' . $objFile->id . '.' . $objFile->extension;
											$oFile = new File($strPath);
											$oFile->resizeTo(1000, 1000, 'proportional');
											// Create folder if it does not exist
											if (!is_dir($rootDir . '/' . $targetDir)) {
												new Folder($targetDir);
											}
											$oFile->copyTo($fileNewPath);
											$oFile->delete();
											Dbafs::addResource($fileNewPath);
											$oFileModel = FilesModel::findByPath($fileNewPath);
											if($oFileModel !== null)
											{
												$multiSRC = StringUtil::deserialize($oStoryModel->multiSRC,true);
												$multiSRC[] = $oFileModel->uuid;
												$oStoryModel->multiSRC = serialize($multiSRC);
												$orderSRC = StringUtil::deserialize($oStoryModel->multiSRC,true);
												$orderSRC[] = $oFileModel->uuid;
												$oStoryModel->orderSRC = serialize($orderSRC);
												$oStoryModel->save();
											}
										}
									}

									// Delete empty tmp folders
									$arrFolders = array(
										$this->eventStoriesUploadPath . '/tmp',
										$this->eventStoriesUploadPath . '/tmp/tmp'
									);
									foreach($arrFolders as $folder)
									{
										$folders = scan($rootDir . '/' . $folder);
										foreach($folders as $dir)
										{
											$objFolder = new Folder($folder . '/' . $dir);
											if($objFolder->isEmpty())
											{
												$objFolder->delete();
											}
										}
									}
								}
							}
						}
					}
				}
			}
        }
    }
}