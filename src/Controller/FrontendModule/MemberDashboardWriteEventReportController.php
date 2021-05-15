<?php

declare(strict_types=1);

/*
 * This file is part of SAC Event Tool Bundle.
 *
 * (c) Marko Cupic 2021 <m.cupic@gmx.ch>
 * @license MIT
 * For the full copyright and license information,
 * please view the LICENSE file that was distributed with this source code.
 * @link https://github.com/markocupic/sac-event-tool-bundle
 */

namespace Markocupic\SacEventToolBundle\Controller\FrontendModule;

use Contao\CalendarEventsMemberModel;
use Contao\CalendarEventsModel;
use Contao\CalendarEventsStoryModel;
use Contao\Config;
use Contao\Controller;
use Contao\CoreBundle\Controller\FrontendModule\AbstractFrontendModuleController;
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\CoreBundle\Monolog\ContaoContext;
use Contao\CoreBundle\ServiceAnnotation\FrontendModule;
use Contao\Database;
use Contao\Dbafs;
use Contao\Environment;
use Contao\File;
use Contao\Files;
use Contao\FilesModel;
use Contao\Folder;
use Contao\FrontendUser;
use Contao\Input;
use Contao\Message;
use Contao\ModuleModel;
use Contao\PageModel;
use Contao\StringUtil;
use Contao\System;
use Contao\Template;
use Contao\Validator;
use Haste\Form\Form;
use Haste\Util\Url;
use Markocupic\SacEventToolBundle\CalendarEventsHelper;
use Psr\Log\LogLevel;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;
use Symfony\Component\Security\Core\Security;

/**
 * Class MemberDashboardWriteEventReportController.
 *
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

    public function __invoke(Request $request, ModuleModel $model, string $section, array $classes = null, ?PageModel $page = null): Response
    {
        // Get logged in member object
        if (($objUser = $this->get('security.helper')->getUser()) instanceof FrontendUser) {
            $this->objUser = $objUser;
        }

        if (null !== $page) {
            // Neither cache nor search page
            $page->noSearch = 1;
            $page->cache = 0;

            // Set the page object
            $this->objPage = $page;
        }

        // Get the project dir (TL_ROOT is deprecated)
        $this->projectDir = $this->getParameter('kernel.project_dir');

        // Call the parent method
        return parent::__invoke($request, $model, $section, $classes, $page);
    }

    public static function getSubscribedServices(): array
    {
        $services = parent::getSubscribedServices();

        $services['contao.framework'] = ContaoFramework::class;
        $services['security.helper'] = Security::class;

        return $services;
    }

    /**
     * @throws \Exception
     */
    protected function getResponse(Template $template, ModuleModel $model, Request $request): ?Response
    {
        // Do not allow for not authorized users
        if (null === $this->objUser) {
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
        /** @var CalendarEventsMemberModel $calendarEventsMemberModelAdapter */
        $calendarEventsMemberModelAdapter = $this->get('contao.framework')->getAdapter(CalendarEventsMemberModel::class);
        /** @var $calendarEventsStoryModelAdapter */
        $calendarEventsStoryModelAdapter = $this->get('contao.framework')->getAdapter(CalendarEventsStoryModel::class);
        /** @var CalendarEventsHelper $calendarEventsHelperAdapter */
        $calendarEventsHelperAdapter = $this->get('contao.framework')->getAdapter(CalendarEventsHelper::class);
        /** @var Database $databaseAdapter */
        $databaseAdapter = $this->get('contao.framework')->getAdapter(Database::class);
        /** @var Controller $controllerAdapter */
        $controllerAdapter = $this->get('contao.framework')->getAdapter(Controller::class);
        /** @var Input $inputAdapter */
        $inputAdapter = $this->get('contao.framework')->getAdapter(Input::class);
        /** @var StringUtil $stringUtilAdapter */
        $stringUtilAdapter = $this->get('contao.framework')->getAdapter(StringUtil::class);

        // Load language file
        $controllerAdapter->loadLanguageFile('tl_calendar_events_story');

        // Handle messages
        if (empty($this->objUser->email) || !$validatorAdapter->isEmail($this->objUser->email)) {
            $messageAdapter->addInfo('Leider wurde für dieses Konto in der Datenbank keine E-Mail-Adresse gefunden. Daher stehen einige Funktionen nur eingeschränkt zur Verf&uuml;gung. Bitte hinterlegen Sie auf der Internetseite des Zentralverbands Ihre E-Mail-Adresse.');
        }

        $objEvent = $calendarEventsModelAdapter->findByPk($inputAdapter->get('eventId'));

        if (null === $objEvent) {
            $messageAdapter->addError(sprintf('Event mit ID %s nicht gefunden.', $inputAdapter->get('eventId')));
        }

        if (!$messageAdapter->hasError()) {
            // Check if report already exists
            $objReportModel = CalendarEventsStoryModel::findOneBySacMemberIdAndEventId($this->objUser->sacMemberId, $objEvent->id);

            if (null === $objReportModel) {
                if ($objEvent->endDate + $model->timeSpanForCreatingNewEventStory * 24 * 60 * 60 < time()) {
                    // Do not allow blogging for old events
                    $messageAdapter->addError('Für diesen Event kann kein Bericht mehr erstellt werden. Das Eventdatum liegt bereits zu lange zurück.');
                }

                if (!$messageAdapter->hasError()) {
                    $blnAllow = false;
                    $intStartDateMin = $model->timeSpanForCreatingNewEventStory > 0 ? time() - $model->timeSpanForCreatingNewEventStory * 24 * 3600 : time();
                    $arrAllowedEvents = $calendarEventsMemberModelAdapter->findEventsByMemberId($this->objUser->id, [], $intStartDateMin, time(), true);

                    foreach ($arrAllowedEvents as $allowedEvent) {
                        if ((int) $allowedEvent['id'] === (int) $inputAdapter->get('eventId')) {
                            $blnAllow = true;
                            continue;
                        }
                    }

                    // User has not participated on the event neither as guide nor as participant and is not allowed to write a report
                    if (!$blnAllow) {
                        $messageAdapter->addError('Du hast keine Berechtigung für diesen Event einen Bericht zu verfassen');
                    }
                }
            }

            if (!$messageAdapter->hasError()) {
                if (null === $objReportModel) {
                    // Create new
                    $aDates = [];
                    $arrDates = $stringUtilAdapter->deserialize($objEvent->eventDates, true);

                    foreach ($arrDates as $arrDate) {
                        $aDates[] = $arrDate['new_repeat'];
                    }

                    $set = [
                        'title' => $objEvent->title,
                        'eventTitle' => $objEvent->title,
                        'eventSubstitutionText' => 'event_adapted' === $objEvent->executionState && '' !== $objEvent->eventSubstitutionText ? $objEvent->eventSubstitutionText : '',
                        'eventStartDate' => $objEvent->startDate,
                        'eventEndDate' => $objEvent->endDate,
                        'organizers' => $objEvent->organizers,
                        'eventDates' => serialize($aDates),
                        'authorName' => $this->objUser->firstname.' '.$this->objUser->lastname,
                        'sacMemberId' => $this->objUser->sacMemberId,
                        'eventId' => $inputAdapter->get('eventId'),
                        'tstamp' => time(),
                        'addedOn' => time(),
                    ];
                    $objInsertStmt = $databaseAdapter->getInstance()->prepare('INSERT INTO tl_calendar_events_story %s')->set($set)->execute();

                    // Set security token for frontend preview
                    if ($objInsertStmt->affectedRows) {
                        // Add security token
                        $insertId = $objInsertStmt->insertId;
                        $set = [];
                        $set['securityToken'] = md5((string) random_int(100000000, 999999999)).$insertId;
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

                if ($objReportModel->doPublishInClubMagazine) {
                    $this->template->doPublishInClubMagazine = true;
                }

                if ('' !== $objReportModel->tourWaypoints) {
                    $this->template->tourWaypoints = nl2br((string) $objReportModel->tourWaypoints);
                }

                if ('' !== $objReportModel->tourProfile) {
                    $this->template->tourProfile = nl2br((string) $objReportModel->tourProfile);
                }

                if ('' !== $objReportModel->tourTechDifficulty) {
                    $this->template->tourTechDifficulty = nl2br((string) $objReportModel->tourTechDifficulty);
                }

                if ($objReportModel->doPublishInClubMagazine && '' !== $objReportModel->tourHighlights) {
                    $this->template->tourHighlights = nl2br((string) $objReportModel->tourHighlights);
                }

                if ($objReportModel->doPublishInClubMagazine && '' !== $objReportModel->tourPublicTransportInfo) {
                    $this->template->tourPublicTransportInfo = nl2br((string) $objReportModel->tourPublicTransportInfo);
                }

                // Generate forms
                $this->template->objEventStoryTextAndYoutubeForm = $this->generateTextAndYoutubeForm($objReportModel);
                $this->template->objEventStoryImageUploadForm = $this->generatePictureUploadForm($objReportModel, $model);

                // Get the preview link
                $this->template->previewLink = $this->getPreviewLink($objReportModel, $model);
            }
        }

        // Check if all images are labeled with a legend and a photographer name
        if ($objReportModel) {
            if (!$this->validateImageUploads($objReportModel)) {
                $messageAdapter->addInfo('Es fehlen noch eine oder mehrere Bildlegenden oder der Fotografen-Name. Bitte ergänze diese Pflichtangaben, damit der Bericht veröffentlicht werden kann.');
            }
        }

        // Add messages to template
        $this->addMessagesToTemplate();

        return $this->template->getResponse();
    }

    protected function validateImageUploads(CalendarEventsStoryModel $objReportModel): bool
    {
        /** @var StringUtil $stringUtilAdapter */
        $stringUtilAdapter = $this->get('contao.framework')->getAdapter(StringUtil::class);

        /** @var FilesModel $filesModelAdapter */
        $filesModelAdapter = $this->get('contao.framework')->getAdapter(FilesModel::class);

        // Check for a valid photographer name an exiting image legends
        if (!empty($objReportModel->multiSRC) && !empty($stringUtilAdapter->deserialize($objReportModel->multiSRC, true))) {
            $arrUuids = $stringUtilAdapter->deserialize($objReportModel->multiSRC, true);
            $objFiles = $filesModelAdapter->findMultipleByUuids($arrUuids);
            $blnMissingLegend = false;
            $blnMissingPhotographerName = false;

            while ($objFiles->next()) {
                if (null !== $objFiles) {
                    $arrMeta = $stringUtilAdapter->deserialize($objFiles->meta, true);

                    if (!isset($arrMeta['de']['caption']) || '' === $arrMeta['de']['caption']) {
                        $blnMissingLegend = true;
                    }

                    if (!isset($arrMeta['de']['photographer']) || '' === $arrMeta['de']['photographer']) {
                        $blnMissingPhotographerName = true;
                    }
                }
            }

            if ($blnMissingLegend || $blnMissingPhotographerName) {
                return false;
            }
        }

        return true;
    }

    /**
     * Add messages from session to template.
     */
    protected function addMessagesToTemplate(): void
    {
        /** @var System $systemAdapter */
        $systemAdapter = $this->get('contao.framework')->getAdapter(System::class);
        /** @var Message $messageAdapter */
        $messageAdapter = $this->get('contao.framework')->getAdapter(Message::class);

        if ($messageAdapter->hasInfo()) {
            $this->template->hasInfoMessage = true;
            $session = $systemAdapter->getContainer()->get('session')->getFlashBag()->get('contao.FE.info');
            $this->template->infoMessage = $session[0];
        }

        if ($messageAdapter->hasError()) {
            $this->template->hasErrorMessage = true;
            $session = $systemAdapter->getContainer()->get('session')->getFlashBag()->get('contao.FE.error');
            $this->template->errorMessage = $session[0];
            $this->template->errorMessages = $session;
        }

        $messageAdapter->reset();
    }

    /**
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

        $objForm = new Form(
            'form-eventstory-text-and-youtube',
            'POST',
            function ($objHaste) {
                /** @var Input $inputAdapter */
                $inputAdapter = $this->get('contao.framework')->getAdapter(Input::class);

                return $inputAdapter->post('FORM_SUBMIT') === $objHaste->getFormId();
            }
        );

        $url = $environmentAdapter->get('uri');
        $objForm->setFormActionFromUri($url);

        // do publish report in the club magazine
        $objForm->addFormField('doPublishInClubMagazine', [
            'label' => ['', 'Ich stimmer einer eventuellen Veröffentlichung meines Berichts in der Mitgliederzeitschrift zu.'],
            'inputType' => 'checkbox',
            'value' => $objEventStoryModel->doPublishInClubMagazine,
        ]);

        // text
        $objForm->addFormField('text', [
            'label' => 'Touren-/Lager-/Kursbericht (max. 1800 Zeichen)',
            'inputType' => 'textarea',
            'eval' => ['mandatory' => true, 'maxlength' => 1800, 'rows' => 8, 'decodeEntities' => true],
            'value' => html_entity_decode((string) $objEventStoryModel->text),
        ]);

        // youtube id
        $objForm->addFormField('youtubeId',
            [
                'label' => 'Youtube Film-Id',
                'inputType' => 'text',
                'eval' => ['placeholder' => 'z.B. G02hYgT3nGw'],
                'value' => $objEventStoryModel->youtubeId,
            ]
        );

        // tour waypoints
        $eval = ['rows' => 2, 'decodeEntities' => true, 'placeholder' => 'z.B. Engelberg 1000m - Herrenrüti 1083 m - Galtiberg 1800 m - Einstieg 2000 m'];

        if ($objEventStoryModel->doPublishInClubMagazine) {
            $eval['mandatory'] = true;
        }
        $objForm->addFormField(
                'tourWaypoints',
                [
                    'label' => 'Tourenstationen mit Höhenangaben',
                    'inputType' => 'textarea',
                    'eval' => $eval,
                    'value' => html_entity_decode((string) $this->getTourWaypoints($objEventStoryModel)),
                ]
            );

        // tour profile
        $eval = ['rows' => 2, 'decodeEntities' => true, 'placeholder' => 'z.B. Aufst: 1500 Hm/8 h, Abst: 1500 Hm/3 h'];

        if ($objEventStoryModel->doPublishInClubMagazine) {
            $eval['mandatory'] = true;
        }
        $objForm->addFormField(
                'tourProfile',
                [
                    'label' => 'Höhenmeter und Zeitangabe pro Tag',
                    'inputType' => 'textarea',
                    'eval' => $eval,
                    'value' => html_entity_decode((string) $this->getTourProfile($objEventStoryModel)),
                ]
            );

        // tour difficulties
        $eval = ['rows' => 2, 'decodeEntities' => true];

        if ($objEventStoryModel->doPublishInClubMagazine) {
            $eval['mandatory'] = true;
        }

        $objForm->addFormField('tourTechDifficulty', [
            'label' => 'Technische Schwierigkeiten',
            'inputType' => 'textarea',
            'eval' => $eval,
            'value' => html_entity_decode((string) $this->getTourTechDifficulties($objEventStoryModel)),
        ]);

        // tour highlights (not mandatory)
        $eval = ['class' => 'publish-clubmagazine-field', 'rows' => 2, 'decodeEntities' => true];

        $objForm->addFormField('tourHighlights', [
            'label' => 'Highlights/Bemerkungen (max. 3 Sätze)',
            'inputType' => 'textarea',
            'eval' => $eval,
            'value' => html_entity_decode((string) $objEventStoryModel->tourHighlights),
        ]);

        // tour public transport info
        $eval = ['class' => 'publish-clubmagazine-field', 'rows' => 2, 'decodeEntities' => true];

        if ($objEventStoryModel->doPublishInClubMagazine) {
            $eval['mandatory'] = true;
        }
        $objForm->addFormField('tourPublicTransportInfo', [
            'label' => 'Mögliche ÖV-Verbindung',
            'inputType' => 'textarea',
            'eval' => $eval,
            'value' => html_entity_decode((string) $objEventStoryModel->tourPublicTransportInfo),
        ]);

        // Let's add  a submit button
        $objForm->addFormField('submit', [
            'label' => 'Änderungen speichern',
            'inputType' => 'submit',
        ]);

        // Bind model
        $objForm->bindModel($objEventStoryModel);

        // validate() also checks whether the form has been submitted
        if ($objForm->validate() && $inputAdapter->post('FORM_SUBMIT') === $objForm->getFormId()) {
            $objEventStoryModel->addedOn = time();
            $objEventStoryModel->text = htmlspecialchars((string) $objForm->getWidget('text')->value);
            $objEventStoryModel->youtubeId = $objForm->getWidget('youtubeId')->value;
            $objEventStoryModel->tourWaypoints = htmlspecialchars((string) $objForm->getWidget('tourWaypoints')->value);
            $objEventStoryModel->tourProfile = htmlspecialchars((string) $objForm->getWidget('tourProfile')->value);
            $objEventStoryModel->tourTechDifficulty = htmlspecialchars((string) $objForm->getWidget('tourTechDifficulty')->value);
            $objEventStoryModel->tourHighlights = htmlspecialchars((string) $objForm->getWidget('tourHighlights')->value);
            $objEventStoryModel->tourPublicTransportInfo = htmlspecialchars((string) $objForm->getWidget('tourPublicTransportInfo')->value);

            $objEventStoryModel->save();

            $hasErrors = false;

            // Check mandatory fields
            if ('' === $objForm->getWidget('text')->value) {
                $objForm->getWidget('text')
                    ->addError('Bitte schreibe etwas zur Tour.')
                ;
                $hasErrors = true;
            }

            if ($objEventStoryModel->doPublishInClubMagazine) {
                if ('' === $objEventStoryModel->tourWaypoints) {
                    $objForm->getWidget('tourWaypoints')
                        ->addError('Bitte ergänze die Tourenstationen mit den Höhenangaben.')
                    ;
                    $hasErrors = true;
                }

                if ('' === $objEventStoryModel->tourProfile) {
                    $objForm->getWidget('tourProfile')
                        ->addError('Bitte ergänzen Sie das Tourenprofil und mache Angaben zu den Höhenmetern und der Zeit.')
                    ;
                    $hasErrors = true;
                }

                if ('' === $objEventStoryModel->tourTechDifficulty) {
                    $objForm->getWidget('tourTechDifficulty')
                        ->addError('Bitte mache Angaben zu den technischen Schwierigkeiten der Tour.')
                    ;
                    $hasErrors = true;
                }

                if ('' === $objEventStoryModel->tourPublicTransportInfo) {
                    $objForm->getWidget('tourPublicTransportInfo')
                        ->addError('Bitte mache Angaben zu den möglichen ÖV-Verbindungen der Tour.')
                    ;
                    $hasErrors = true;
                }
            }

            // Reload page
            if (!$hasErrors) {
                $controllerAdapter->reload();
            }
        }

        // Add some Vue.js attributes to the form widgets
        $this->addVueAttributesToFormWidget($objForm);

        return $objForm->generate();
    }

    protected function addVueAttributesToFormWidget(Form $objForm): void
    {
        $objForm->getWidget('text')->addAttribute('v-model', 'ctrl_text.value');
        $objForm->getWidget('text')->addAttribute('v-on:keyup', 'onKeyUp("ctrl_text")');
    }

    protected function getTourProfile(CalendarEventsStoryModel $objEventStoryModel): string
    {
        $calendarEventsHelperAdapter = $this->get('contao.framework')->getAdapter(CalendarEventsHelper::class);
        $calendarEventsModelAdapter = $this->get('contao.framework')->getAdapter(CalendarEventsModel::class);

        if (!empty($objEventStoryModel->tourProfile)) {
            return $objEventStoryModel->tourProfile;
        }
        $objEvent = $calendarEventsModelAdapter->findByPk($objEventStoryModel->eventId);

        if (null !== $objEvent) {
            $arrData = $calendarEventsHelperAdapter->getTourProfileAsArray($objEvent);

            return implode("\r\n", $arrData);
        }

        return '';
    }

    protected function getTourWaypoints(CalendarEventsStoryModel $objEventStoryModel): string
    {
        $calendarEventsModelAdapter = $this->get('contao.framework')->getAdapter(CalendarEventsModel::class);

        if (!empty($objEventStoryModel->tourWaypoints)) {
            return $objEventStoryModel->tourWaypoints;
        }
        $objEvent = $calendarEventsModelAdapter->findByPk($objEventStoryModel->eventId);

        if (null !== $objEvent) {
            return !empty($objEvent->tourDetailText) ? $objEvent->tourDetailText : '';
        }

        return '';
    }

    protected function getTourTechDifficulties(CalendarEventsStoryModel $objEventStoryModel): string
    {
        $calendarEventsHelperAdapter = $this->get('contao.framework')->getAdapter(CalendarEventsHelper::class);
        $calendarEventsModelAdapter = $this->get('contao.framework')->getAdapter(CalendarEventsModel::class);

        if (!empty($objEventStoryModel->tourTechDifficulty)) {
            return $objEventStoryModel->tourTechDifficulty;
        }
        $objEvent = $calendarEventsModelAdapter->findByPk($objEventStoryModel->eventId);

        if (null !== $objEvent) {
            $arrData = $calendarEventsHelperAdapter->getTourTechDifficultiesAsArray($objEvent);

            if (empty($arrData)) {
                return 'keine Angabe';
            }

            return implode("\r\n", $arrData);
        }

        return '';
    }

    /**
     * @throws \Exception
     *
     * @return string
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
        /** @var Config $configAdapter */
        $configAdapter = $this->get('contao.framework')->getAdapter(Config::class);

        // Set max image widht and height
        if ((int) $moduleModel->eventStoryMaxImageWidth > 0) {
            $configAdapter->set('imageWidth', (int) $moduleModel->eventStoryMaxImageWidth);
        }

        if ((int) $moduleModel->eventStoryMaxImageHeight > 0) {
            $configAdapter->set('imageHeight', (int) $moduleModel->eventStoryMaxImageHeight);
        }

        $objUploadFolder = null;

        if ('' !== $moduleModel->eventStoryUploadFolder) {
            if ($validatorAdapter->isBinaryUuid($moduleModel->eventStoryUploadFolder)) {
                $objFilesModel = $filesModelAdapter->findByUuid($moduleModel->eventStoryUploadFolder);

                if (null !== $objFilesModel) {
                    $objUploadFolder = new Folder($objFilesModel->path.'/'.$objEventStoryModel->id);
                    $dbafsAdapter->addResource($objFilesModel->path.'/'.$objEventStoryModel->id);
                }
            }
        }

        if (null === $objUploadFolder) {
            $messageAdapter->addError('Uploadverzeichnis nicht gefunden.');

            return;
        }

        $objForm = new Form(
            'form-eventstory-picture-upload',
            'POST',
            function ($objHaste) {
                /** @var Input $inputAdapter */
                $inputAdapter = $this->get('contao.framework')->getAdapter(Input::class);

                return $inputAdapter->post('FORM_SUBMIT') === $objHaste->getFormId();
            }
        );

        $url = $environmentAdapter->get('uri');
        $objForm->setFormActionFromUri($url);

        // Add some fields
        $objForm->addFormField('fileupload', [
            'label' => 'Bildupload',
            'inputType' => 'fineUploader',
            'eval' => ['extensions' => 'jpg,jpeg',
                'storeFile' => true,
                'addToDbafs' => true,
                'isGallery' => false,
                'directUpload' => false,
                'multiple' => true,
                'useHomeDir' => false,
                'uploadFolder' => $objUploadFolder->path,
                'mandatory' => true,
            ],
        ]);

        // Let's add  a submit button
        $objForm->addFormField('submit', [
            'label' => 'Bildupload starten',
            'inputType' => 'submit',
        ]);

        // Add attributes
        $objWidgetFileupload = $objForm->getWidget('fileupload');
        $objWidgetFileupload->addAttribute('accept', '.jpg, .jpeg');
        $objWidgetFileupload->storeFile = true;

        // validate() also checks whether the form has been submitted
        if ($objForm->validate() && $inputAdapter->post('FORM_SUBMIT') === $objForm->getFormId()) {
            if (!empty($_SESSION['FILES']) && \is_array($_SESSION['FILES'])) {
                foreach ($_SESSION['FILES'] as $file) {
                    $uuid = $file['uuid'];

                    if ($validatorAdapter->isStringUuid($uuid)) {
                        $binUuid = $stringUtilAdapter->uuidToBin($uuid);
                        $objModel = $filesModelAdapter->findByUuid($binUuid);

                        if (null !== $objModel) {
                            $objFile = new File($objModel->path);

                            if ($objFile->isImage) {
                                // Rename file
                                $newFilename = sprintf('event-story-%s-img-%s.%s', $objEventStoryModel->id, $objModel->id, strtolower($objFile->extension));
                                $newPath = $objUploadFolder->path.'/'.$newFilename;
                                $filesAdapter->getInstance()->rename($objFile->path, $newPath);
                                $objModel->path = $newPath;
                                $objModel->name = basename($newPath);
                                $objModel->tstamp = time();
                                $objModel->save();
                                $dbafsAdapter->updateFolderHashes($objUploadFolder->path);

                                if (is_file($this->projectDir.'/'.$newPath)) {
                                    $oFileModel = $filesModelAdapter->findByPath($newPath);

                                    if (null !== $oFileModel) {
                                        // Add photographer name to meta field
                                        if (null !== $this->objUser) {
                                            $arrMeta = $stringUtilAdapter->deserialize($oFileModel->meta, true);

                                            if (!isset($arrMeta[$this->objPage->language])) {
                                                $arrMeta[$this->objPage->language] = [
                                                    'title' => '',
                                                    'alt' => '',
                                                    'link' => '',
                                                    'caption' => '',
                                                    'photographer' => '',
                                                ];
                                            }
                                            $arrMeta[$this->objPage->language]['photographer'] = $this->objUser->firstname.' '.$this->objUser->lastname;
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

            if (!$objWidgetFileupload->hasErrors()) {
                // Reload page
                $controllerAdapter->reload();
            }
        }

        unset($_SESSION['FILES']);

        return $objForm->generate();
    }

    /**
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

        foreach ($arrMultiSRC as $uuid) {
            if ($validatorAdapter->isUuid($uuid)) {
                $objFiles = $filesModelAdapter->findByUuid($uuid);

                if (null !== $objFiles) {
                    if (is_file($this->projectDir.'/'.$objFiles->path)) {
                        $objFile = new File($objFiles->path);

                        if ($objFile->isImage) {
                            $arrMeta = $stringUtilAdapter->deserialize($objFiles->meta, true);
                            $images[$objFiles->path] = [
                                'id' => $objFiles->id,
                                'path' => $objFiles->path,
                                'uuid' => $objFiles->uuid,
                                'name' => $objFile->basename,
                                'singleSRC' => $objFiles->path,
                                'title' => $stringUtilAdapter->specialchars($objFile->basename),
                                'filesModel' => $objFiles->current(),
                                'caption' => $arrMeta['de']['caption'] ?? '',
                                'photographer' => $arrMeta['de']['photographer'] ?? '',
                                'alt' => $arrMeta['de']['alt'] ?? '',
                            ];
                        }
                    }
                }
            }
        }

        // Custom image sorting
        if ('' !== $objStory->orderSRC) {
            $tmp = $stringUtilAdapter->deserialize($objStory->orderSRC);

            if (!empty($tmp) && \is_array($tmp)) {
                // Remove all values
                $arrOrder = array_map(
                    static function (): void {
                    },
                    array_flip($tmp)
                );

                // Move the matching elements to their position in $arrOrder
                foreach ($images as $k => $v) {
                    if (\array_key_exists($v['uuid'], $arrOrder)) {
                        $arrOrder[$v['uuid']] = $v;
                        unset($images[$k]);
                    }
                }

                // Append the left-over images at the end
                if (!empty($images)) {
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
     * @param CalendarEventsStoryModel $objStory
     * @param ModuleModel $objModule
     * @return string
     */
    protected function getPreviewLink(CalendarEventsStoryModel $objStory, ModuleModel $objModule): string
    {
        /** @var PageModel $pageModelAdapter */
        $pageModelAdapter = $this->get('contao.framework')->getAdapter(PageModel::class);

        /** @var Config $configAdapter */
        $configAdapter = $this->get('contao.framework')->getAdapter(Config::class);

        /** @var Environment $environmentAdapterAdapter */
        $environmentAdapter = $this->get('contao.framework')->getAdapter(Environment::class);

        /** @var Url $urlAdapter */
        $urlAdapter = $this->get('contao.framework')->getAdapter(Url::class);


        // Generate frontend preview link
        $previewLink = '';

        if ($objModule->eventStoryJumpTo > 0) {
            $objTarget = $pageModelAdapter->findByPk($objModule->eventStoryJumpTo);

            if (null !== $objTarget) {
                $previewLink = ampersand($objTarget->getFrontendUrl($configAdapter->get('useAutoItem') ? '/%s' : '/items/%s'));
                $previewLink = sprintf($previewLink, $objStory->id);
                $previewLink = $environmentAdapter->get('url').'/'.$urlAdapter->addQueryString('securityToken='.$objStory->securityToken, $previewLink);
            }
        }

        return $previewLink;
    }
}
