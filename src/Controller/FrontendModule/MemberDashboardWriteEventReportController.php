<?php

declare(strict_types=1);

/**
 * SAC Event Tool Web Plugin for Contao
 * Copyright (c) 2008-2020 Marko Cupic
 * @package sac-event-tool-bundle
 * @author Marko Cupic m.cupic@gmx.ch, 2017-2020
 * @link https://github.com/markocupic/sac-event-tool-bundle
 */

namespace Markocupic\SacEventToolBundle\Controller\FrontendModule;

use Contao\CalendarEventsMemberModel;
use Contao\CalendarEventsModel;
use Contao\CalendarEventsStoryModel;
use Contao\Config;
use Contao\Controller;
use Contao\Database;
use Contao\Dbafs;
use Contao\Environment;
use Contao\File;
use Contao\Files;
use Contao\FilesModel;
use Contao\Folder;
use Contao\FrontendUser;
use Contao\Input;
use Contao\Template;
use Contao\Message;
use Contao\PageModel;
use Contao\StringUtil;
use Contao\System;
use Contao\Validator;
use Haste\Form\Form;
use Contao\ModuleModel;
use Contao\CoreBundle\Monolog\ContaoContext;
use Psr\Log\LogLevel;
use Contao\CoreBundle\Controller\FrontendModule\AbstractFrontendModuleController;
use Contao\CoreBundle\Framework\ContaoFramework;
use Markocupic\SacEventToolBundle\CalendarEventsHelper;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;
use Symfony\Component\Security\Core\Security;
use Contao\CoreBundle\ServiceAnnotation\FrontendModule;

/**
 * Class MemberDashboardWriteEventReportController
 * @package Markocupic\SacEventToolBundle\Controller\FrontendModule
 * @FrontendModule("member_dashboard_write_event_report", category="sac_event_tool_frontend_modules")
 */
class MemberDashboardWriteEventReportController extends AbstractFrontendModuleController
{

    /**
     * @var string
     */
    protected $projectDir;

    /**
     * @var FrontendUser
     */
    protected $objUser;

    /**
     * @var Template
     */
    protected $template;

    /**
     * @var PageModel
     */
    protected $objPage;

    /**
     * @param Request $request
     * @param ModuleModel $model
     * @param string $section
     * @param array|null $classes
     * @param PageModel|null $page
     * @return Response
     */
    public function __invoke(Request $request, ModuleModel $model, string $section, array $classes = null, PageModel $page = null): Response
    {
        // Get logged in member object
        if (($objUser = $this->get('security.helper')->getUser()) instanceof FrontendUser)
        {
            $this->objUser = $objUser;
        }

        if ($page !== null)
        {
            // Neither cache nor search page
            $page->noSearch = 1;
            $page->cache = 0;

            // Set the page object
            $this->objPage = $page;
        }

        // Get the project dir (TL_ROOT is deprecated)
        $this->projectDir = $this->getParameter('kernel.project_dir');

        // Call the parent method
        return parent::__invoke($request, $model, $section, $classes);
    }

    /**
     * @return array
     */
    public static function getSubscribedServices(): array
    {
        $services = parent::getSubscribedServices();

        $services['contao.framework'] = ContaoFramework::class;
        $services['security.helper'] = Security::class;

        return $services;
    }

    /**
     * @param Template $template
     * @param ModuleModel $model
     * @param Request $request
     * @return null|Response
     * @throws \Exception
     */
    protected function getResponse(Template $template, ModuleModel $model, Request $request): ?Response
    {
        // Do not allow for not authorized users
        if ($this->objUser === null)
        {
            throw new UnauthorizedHttpException('Not authorized. Please log in as frontend user.');
        }

        $this->template = $template;

        // Set adapters
        /** @var Message $messageAdapter */
        $messageAdapter = $this->get('contao.framework')->getAdapter(Message::class);
        /** @var Validator $validatorAdapter */
        $validatorAdapter = $this->get('contao.framework')->getAdapter(Validator::class);
        /** @var CalendarEventsModel $calendarEventsModelAdapter */
        $calendarEventsModelAdapter = $this->get('contao.framework')->getAdapter(CalendarEventsModel::class);
        /** @var  CalendarEventsMemberModel $calendarEventsMemberModelAdapter */
        $calendarEventsMemberModelAdapter = $this->get('contao.framework')->getAdapter(CalendarEventsMemberModel::class);
        /** @var  $calendarEventsStoryModelAdapter */
        $calendarEventsStoryModelAdapter = $this->get('contao.framework')->getAdapter(CalendarEventsStoryModel::class);
        /** @var  CalendarEventsHelper $calendarEventsHelperAdapter */
        $calendarEventsHelperAdapter = $this->get('contao.framework')->getAdapter(CalendarEventsHelper::class);
        /** @var  Database $databaseAdapter */
        $databaseAdapter = $this->get('contao.framework')->getAdapter(Database::class);
        /** @var  Controller $controllerAdapter */
        $controllerAdapter = $this->get('contao.framework')->getAdapter(Controller::class);
        /** @var  Input $inputAdapter */
        $inputAdapter = $this->get('contao.framework')->getAdapter(Input::class);
        /** @var  StringUtil $stringUtilAdapter */
        $stringUtilAdapter = $this->get('contao.framework')->getAdapter(StringUtil::class);

        // Load language file
        $controllerAdapter->loadLanguageFile('tl_calendar_events_story');

        // Handle messages
        if ($this->objUser->email == '' || !$validatorAdapter->isEmail($this->objUser->email))
        {
            $messageAdapter->addInfo('Leider wurde für dieses Konto in der Datenbank keine E-Mail-Adresse gefunden. Daher stehen einige Funktionen nur eingeschränkt zur Verf&uuml;gung. Bitte hinterlegen Sie auf der Internetseite des Zentralverbands Ihre E-Mail-Adresse.');
        }

        $objEvent = $calendarEventsModelAdapter->findByPk($inputAdapter->get('eventId'));

        if ($objEvent === null)
        {
            $messageAdapter->addError(sprintf('Event mit ID %s nicht gefunden.', $inputAdapter->get('eventId')));
        }

        if (!$messageAdapter->hasError())
        {
            // Check if report already exists
            $objReportModel = CalendarEventsStoryModel::findOneBySacMemberIdAndEventId($this->objUser->sacMemberId, $objEvent->id);

            if ($objReportModel === null)
            {
                if ($objEvent->endDate + $model->timeSpanForCreatingNewEventStory * 24 * 60 * 60 < time())
                {
                    // Do not allow blogging for old events
                    $messageAdapter->addError('Für diesen Event kann kein Bericht mehr erstellt werden. Das Eventdatum liegt bereits zu lange zurück.');
                }

                if (!$messageAdapter->hasError())
                {
                    $blnAllow = false;
                    $intStartDateMin = $model->timeSpanForCreatingNewEventStory > 0 ? time() - $model->timeSpanForCreatingNewEventStory * 24 * 3600 : time();
                    $arrAllowedEvents = $calendarEventsMemberModelAdapter->findEventsByMemberId($this->objUser->id, [], $intStartDateMin, time(), true);
                    foreach ($arrAllowedEvents as $allowedEvent)
                    {
                        if ($allowedEvent['id'] == $inputAdapter->get('eventId'))
                        {
                            $blnAllow = true;
                            continue;
                        }
                    }

                    // User has not participated on the event neither as guide nor as participant and is not allowed to write a report
                    if (!$blnAllow)
                    {
                        $messageAdapter->addError('Du hast keine Berechtigung für diesen Event einen Bericht zu verfassen');
                    }
                }
            }

            if (!$messageAdapter->hasError())
            {
                if ($objReportModel === null)
                {
                    // Create new
                    $aDates = [];
                    $arrDates = $stringUtilAdapter->deserialize($objEvent->eventDates, true);
                    foreach ($arrDates as $arrDate)
                    {
                        $aDates[] = $arrDate['new_repeat'];
                    }

                    $set = [
                        'title'                 => $objEvent->title,
                        'eventTitle'            => $objEvent->title,
                        'eventSubstitutionText' => ($objEvent->executionState === 'event_adapted' && $objEvent->eventSubstitutionText != '') ? $objEvent->eventSubstitutionText : '',
                        'eventStartDate'        => $objEvent->startDate,
                        'eventEndDate'          => $objEvent->endDate,
                        'organizers'            => $objEvent->organizers,
                        'eventDates'            => serialize($aDates),
                        'authorName'            => $this->objUser->firstname . ' ' . $this->objUser->lastname,
                        'sacMemberId'           => $this->objUser->sacMemberId,
                        'eventId'               => $inputAdapter->get('eventId'),
                        'tstamp'                => time(),
                        'addedOn'               => time(),
                    ];
                    $objInsertStmt = $databaseAdapter->getInstance()->prepare('INSERT INTO tl_calendar_events_story %s')->set($set)->execute();

                    // Set security token for frontend preview
                    if ($objInsertStmt->affectedRows)
                    {
                        // Add security token
                        $insertId = $objInsertStmt->insertId;
                        $set = [];
                        $set['securityToken'] = (string) md5((string) rand(100000000, 999999999)) . $insertId;
                        $databaseAdapter->getInstance()->prepare('UPDATE tl_calendar_events_story %s WHERE id=?')->set($set)->execute($insertId);
                    }
                    $objReportModel = $calendarEventsStoryModelAdapter->findByPk($insertId);
                }

                $this->template->eventName = $objEvent->title;
                $this->template->executionState = $objEvent->executionState;
                $this->template->eventSubstitutionText = $objEvent->eventSubstitutionText;

                $this->template->youtubeId = $objReportModel->youtubeId;
                $this->template->text = $objReportModel->text;
                $this->template->title = $objReportModel->title;
                $this->template->publishState = $objReportModel->publishState;

                $this->template->eventPeriod = $calendarEventsHelperAdapter->getEventPeriod($objEvent);

                // Get the gallery
                $this->template->images = $this->getGalleryImages($objReportModel);

                // Generate forms
                $this->template->objEventStoryTextAndYoutubeForm = $this->generateTextAndYoutubeForm($objReportModel);
                $this->template->objEventStoryImageUploadForm = $this->generatePictureUploadForm($objReportModel, $model);
            }
        }

        // Add messages to template
        $this->addMessagesToTemplate();

        return $this->template->getResponse();
    }

    /**
     * Add messages from session to template
     */
    protected function addMessagesToTemplate(): void
    {
        /** @var System $systemAdapter */
        $systemAdapter = $this->get('contao.framework')->getAdapter(System::class);
        /** @var Message $messageAdapter */
        $messageAdapter = $this->get('contao.framework')->getAdapter(Message::class);

        if ($messageAdapter->hasInfo())
        {
            $this->template->hasInfoMessage = true;
            $session = $systemAdapter->getContainer()->get('session')->getFlashBag()->get('contao.FE.info');
            $this->template->infoMessage = $session[0];
        }

        if ($messageAdapter->hasError())
        {
            $this->template->hasErrorMessage = true;
            $session = $systemAdapter->getContainer()->get('session')->getFlashBag()->get('contao.FE.error');
            $this->template->errorMessage = $session[0];
            $this->template->errorMessages = $session;
        }

        $messageAdapter->reset();
    }

    /**
     * @param CalendarEventsStoryModel $objEventStoryModel
     * @return string
     */
    protected function generateTextAndYoutubeForm(CalendarEventsStoryModel $objEventStoryModel)
    {
        // Set adapters
        /** @var Environment $environmentAdapter */
        $environmentAdapter = $this->get('contao.framework')->getAdapter(Environment::class);
        /** @var Controller $controllerAdapter */
        $controllerAdapter = $this->get('contao.framework')->getAdapter(Controller::class);
        /** @var Input $inputAdapter */
        $inputAdapter = $this->get('contao.framework')->getAdapter(Input::class);

        $objForm = new Form('form-eventstory-text-and-youtube', 'POST', function ($objHaste) {
            /** @var Input $inputAdapter */
            $inputAdapter = $this->get('contao.framework')->getAdapter(Input::class);
            return $inputAdapter->post('FORM_SUBMIT') === $objHaste->getFormId();
        });

        $url = $environmentAdapter->get('uri');
        $objForm->setFormActionFromUri($url);

        // Add some fields
        $objForm->addFormField('text', [
            'label'     => 'Touren-/Lager-/Kursbericht',
            'inputType' => 'textarea',
            'eval'      => ['decodeEntities' => true],
            'value'     => html_entity_decode((string) $objEventStoryModel->text)

        ]);

        // Add some fields
        $objForm->addFormField('youtubeId', [
            'label'     => 'Youtube Film-Id',
            'inputType' => 'text',
            'eval'      => [],
            'value'     => $objEventStoryModel->youtubeId
        ]);

        // Let's add  a submit button
        $objForm->addFormField('submit', [
            'label'     => 'absenden',
            'inputType' => 'submit',
        ]);

        // Add attributes
        $objWidgetYt = $objForm->getWidget('youtubeId');
        $objWidgetText = $objForm->getWidget('text');

        $objWidgetYt->addAttribute('placeholder', 'z.B. G02hYgT3nGw');

        // Bind model
        $objForm->bindModel($objEventStoryModel);

        // validate() also checks whether the form has been submitted
        if ($objForm->validate() && $inputAdapter->post('FORM_SUBMIT') === $objForm->getFormId())
        {
            $objEventStoryModel->addedOn = time();
            $objEventStoryModel->text = htmlspecialchars($objWidgetText->value);
            $objEventStoryModel->youtubeId = $objWidgetYt->value;
            $objEventStoryModel->save();

            // Reload page
            $controllerAdapter->reload();
        }

        return $objForm->generate();
    }

    /**
     * @param CalendarEventsStoryModel $objEventStoryModel
     * @param ModuleModel $moduleModel
     * @return string
     * @throws \Exception
     */
    protected function generatePictureUploadForm(CalendarEventsStoryModel $objEventStoryModel, ModuleModel $moduleModel)
    {
        // Set adapters
        /** @var Environment $environmentAdapter */
        $environmentAdapter = $this->get('contao.framework')->getAdapter(Environment::class);
        /** @var Controller $controllerAdapter */
        $controllerAdapter = $this->get('contao.framework')->getAdapter(Controller::class);
        /** @var Input $inputAdapter */
        $inputAdapter = $this->get('contao.framework')->getAdapter(Input::class);
        /** @var Validator $validatorAdapter */
        $validatorAdapter = $this->get('contao.framework')->getAdapter(Validator::class);
        /** @var Files $filesAdapter */
        $filesAdapter = $this->get('contao.framework')->getAdapter(Files::class);
        /** @var StringUtil $stringUtilAdapter */
        $stringUtilAdapter = $this->get('contao.framework')->getAdapter(StringUtil::class);
        /** @var FilesModel $filesModelAdapter */
        $filesModelAdapter = $this->get('contao.framework')->getAdapter(FilesModel::class);
        /** @var Dbafs $dbafsAdapter */
        $dbafsAdapter = $this->get('contao.framework')->getAdapter(Dbafs::class);
        /** @var Message $messageAdapter */
        $messageAdapter = $this->get('contao.framework')->getAdapter(Message::class);

        $objUploadFolder = null;
        if ($moduleModel->eventStoryUploadFolder != '')
        {
            if ($validatorAdapter->isBinaryUuid($moduleModel->eventStoryUploadFolder))
            {
                $objFilesModel = $filesModelAdapter->findByUuid($moduleModel->eventStoryUploadFolder);
                if ($objFilesModel !== null)
                {
                    $objUploadFolder = new Folder($objFilesModel->path . '/' . $objEventStoryModel->id);
                    $dbafsAdapter->addResource($objFilesModel->path . '/' . $objEventStoryModel->id);
                }
            }
        }

        if ($objUploadFolder === null)
        {
            $messageAdapter->addError('Uploadverzeichnis nicht gefunden.');
            return;
        }

        $objForm = new Form('form-eventstory-picture-upload', 'POST', function ($objHaste) {
            /** @var Input $inputAdapter */
            $inputAdapter = $this->get('contao.framework')->getAdapter(Input::class);
            return $inputAdapter->post('FORM_SUBMIT') === $objHaste->getFormId();
        });

        $url = $environmentAdapter->get('uri');
        $objForm->setFormActionFromUri($url);

        // Add some fields
        $objForm->addFormField('fileupload', [
            'label'     => 'Bildupload',
            'inputType' => 'fineUploader',
            'eval'      => ['extensions'   => 'jpg,jpeg',
                            'storeFile'    => true,
                            'addToDbafs'   => true,
                            'isGallery'    => false,
                            'directUpload' => false,
                            'multiple'     => true,
                            'useHomeDir'   => false,
                            'uploadFolder' => $objUploadFolder->path,
                            'mandatory'    => true
            ],
        ]);

        // Let's add  a submit button
        $objForm->addFormField('submit', [
            'label'     => 'upload starten',
            'inputType' => 'submit',
        ]);

        // Add attributes
        $objWidgetFileupload = $objForm->getWidget('fileupload');
        $objWidgetFileupload->addAttribute('accept', '.jpg, .jpeg');
        $objWidgetFileupload->storeFile = true;

        // validate() also checks whether the form has been submitted
        if ($objForm->validate() && $inputAdapter->post('FORM_SUBMIT') === $objForm->getFormId())
        {
            if (is_array($_SESSION['FILES']) && !empty($_SESSION['FILES']))
            {
                foreach ($_SESSION['FILES'] as $k => $file)
                {
                    $uuid = $file['uuid'];
                    if ($validatorAdapter->isStringUuid($uuid))
                    {
                        $binUuid = $stringUtilAdapter->uuidToBin($uuid);
                        $objModel = $filesModelAdapter->findByUuid($binUuid);

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
                                $filesAdapter->getInstance()->rename($objFile->path, $newPath);
                                $objModel->path = $newPath;
                                $objModel->name = basename($newPath);
                                $objModel->tstamp = time();
                                $objModel->save();
                                $dbafsAdapter->updateFolderHashes($objUploadFolder->path);

                                if (is_file($this->projectDir . '/' . $newPath))
                                {
                                    $oFileModel = $filesModelAdapter->findByPath($newPath);
                                    if ($oFileModel !== null)
                                    {
                                        // Add photographer name to meta field
                                        if ($this->objUser !== null)
                                        {
                                            $arrMeta = $stringUtilAdapter->deserialize($oFileModel->meta, true);
                                            if (!isset($arrMeta[$this->objPage->language]))
                                            {
                                                $arrMeta[$this->objPage->language] = [
                                                    'title'        => '',
                                                    'alt'          => '',
                                                    'link'         => '',
                                                    'caption'      => '',
                                                    'photographer' => '',
                                                ];
                                            }
                                            $arrMeta[$this->objPage->language]['photographer'] = $this->objUser->firstname . ' ' . $this->objUser->lastname;
                                            $oFileModel->meta = serialize($arrMeta);
                                            $oFileModel->save();
                                        }

                                        // Save gallery data to tl_calendar_events_story
                                        $multiSRC = $stringUtilAdapter->deserialize($objEventStoryModel->multiSRC, true);
                                        $multiSRC[] = $oFileModel->uuid;
                                        $objEventStoryModel->multiSRC = serialize($multiSRC);
                                        $orderSRC = $stringUtilAdapter->deserialize($objEventStoryModel->multiSRC, true);
                                        $orderSRC[] = $oFileModel->uuid;
                                        $objEventStoryModel->orderSRC = serialize($orderSRC);
                                        $objEventStoryModel->save();
                                    }

                                    // Log
                                    $strText = sprintf('User with username %s has uploadad a new picture ("%s").', $this->objUser->username, $objModel->path);
                                    $logger = System::getContainer()->get('monolog.logger.contao');
                                    $logger->log(LogLevel::INFO, $strText, ['contao' => new ContaoContext(__METHOD__, 'EVENT STORY PICTURE UPLOAD')]);
                                }
                            }
                        }
                    }
                }
            }

            if (!$objWidgetFileupload->hasErrors())
            {
                // Reload page
                $controllerAdapter->reload();
            }
        }

        unset($_SESSION['FILES']);

        return $objForm->generate();
    }

    /**
     * @param CalendarEventsStoryModel $objStory
     * @return array
     * @throws \Exception
     */
    protected function getGalleryImages(CalendarEventsStoryModel $objStory): array
    {
        // Set adapters
        /** @var Validator $validatorAdapter */
        $validatorAdapter = $this->get('contao.framework')->getAdapter(Validator::class);
        /** @var StringUtil $stringUtilAdapter */
        $stringUtilAdapter = $this->get('contao.framework')->getAdapter(StringUtil::class);
        /** @var FilesModel $filesModelAdapter */
        $filesModelAdapter = $this->get('contao.framework')->getAdapter(FilesModel::class);

        $images = [];
        $arrMultiSRC = $stringUtilAdapter->deserialize($objStory->multiSRC, true);
        foreach ($arrMultiSRC as $uuid)
        {
            if ($validatorAdapter->isUuid($uuid))
            {
                $objFiles = $filesModelAdapter->findByUuid($uuid);
                if ($objFiles !== null)
                {
                    if (is_file($this->projectDir . '/' . $objFiles->path))
                    {
                        $objFile = new File($objFiles->path);

                        if ($objFile->isImage)
                        {
                            $arrMeta = $stringUtilAdapter->deserialize($objFiles->meta, true);
                            $images[$objFiles->path] = [
                                'id'         => $objFiles->id,
                                'path'       => $objFiles->path,
                                'uuid'       => $objFiles->uuid,
                                'name'       => $objFile->basename,
                                'singleSRC'  => $objFiles->path,
                                'title'      => $stringUtilAdapter->specialchars($objFile->basename),
                                'filesModel' => $objFiles->current(),
                                'caption'    => isset($arrMeta['de']['caption']) ? $arrMeta['de']['caption'] : '',
                                'alt'        => isset($arrMeta['de']['alt']) ? $arrMeta['de']['alt'] : '',
                            ];
                        }
                    }
                }
            }
        }

        // Custom image sorting
        if ($objStory->orderSRC != '')
        {
            $tmp = $stringUtilAdapter->deserialize($objStory->orderSRC);

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

        return array_values($images);
    }

    /**
     * Resize an uploaded image if necessary
     * @param string $strImage
     * @return bool
     * @throws \Exception
     */
    protected function resizeUploadedImage(string $strImage): bool
    {
        // Set adapters
        /** @var Config $configAdapter */
        $configAdapter = $this->get('contao.framework')->getAdapter(Config::class);

        // If there is no limitation
        if ($configAdapter->get('maxImageWidth') < 1)
        {
            return false;
        }

        $objFile = new File($strImage);

        // Return if file is not an image
        if (!$objFile->isSvgImage && !$objFile->isGdImage)
        {
            return false;
        }
        $arrImageSize = $objFile->imageSize;

        // The image is too big to be handled by the GD library
        if ($objFile->isGdImage && ($arrImageSize[0] > $configAdapter->get('gdMaxImgWidth') || $arrImageSize[1] > $configAdapter->get('gdMaxImgHeight')))
        {
            // Log
            $strText = 'File "' . $strImage . '" is too big to be resized automatically';
            $logger = System::getContainer()->get('monolog.logger.contao');
            $logger->log(LogLevel::INFO, $strText, ['contao' => new ContaoContext(__METHOD__, TL_FILES)]);

            return false;
        }

        $blnResize = false;

        // The image exceeds the maximum image width
        if ($arrImageSize[0] > $configAdapter->get('maxImageWidth'))
        {
            $blnResize = true;
            $intWidth = $configAdapter->get('maxImageWidth');
            $intHeight = round($configAdapter->get('maxImageWidth') * $arrImageSize[1] / $arrImageSize[0]);
            $arrImageSize = [$intWidth, $intHeight];
        }

        // The image exceeds the maximum image height
        if ($arrImageSize[1] > $configAdapter->get('maxImageWidth'))
        {
            $blnResize = true;
            $intWidth = round($configAdapter->get('maxImageWidth') * $arrImageSize[0] / $arrImageSize[1]);
            $intHeight = $configAdapter->get('maxImageWidth');
            $arrImageSize = [$intWidth, $intHeight];
        }

        // Resized successfully
        if ($blnResize)
        {
            System::getContainer()
                ->get('contao.image.image_factory')
                ->create($this->projectDir . '/' . $strImage, [$arrImageSize[0], $arrImageSize[1]], $this->projectDir . '/' . $strImage);

            $this->blnHasResized = true;

            return true;
        }

        return false;
    }

}
