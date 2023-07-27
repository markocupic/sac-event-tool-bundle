<?php

declare(strict_types=1);

/*
 * This file is part of SAC Event Tool Bundle.
 *
 * (c) Marko Cupic 2023 <m.cupic@gmx.ch>
 * @license GPL-3.0-or-later
 * For the full copyright and license information,
 * please view the LICENSE file that was distributed with this source code.
 * @link https://github.com/markocupic/sac-event-tool-bundle
 */

namespace Markocupic\SacEventToolBundle\Controller\BackendModule;

use Codefog\HasteBundle\Form\Form;
use Contao\BackendTemplate;
use Contao\BackendUser;
use Contao\CalendarEventsModel;
use Contao\Controller;
use Contao\CoreBundle\Exception\AccessDeniedException;
use Contao\CoreBundle\Framework\Adapter;
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\Email;
use Contao\Environment;
use Contao\Events;
use Contao\Message;
use Contao\System;
use Contao\UserModel;
use Contao\Validator;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;
use Markocupic\SacEventToolBundle\CalendarEventsHelper;
use Markocupic\SacEventToolBundle\Config\EventSubscriptionState;
use Markocupic\SacEventToolBundle\Model\CalendarEventsMemberModel;
use Markocupic\SacEventToolBundle\Util\EventRegistrationUtil;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Filesystem\Path;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Core\Security;
use Symfony\Contracts\Translation\TranslatorInterface;
use Twig\Environment as Twig;

/**
 * This controller allows sending emails directly from the participant list.
 *
 * involved files:
 * vendor/markocupic/sac-event-tool-bundle/contao/templates/backend/tl_calendar_events_member/be_send_email_to_participant.html.twig
 * vendor/markocupic/sac-event-tool-bundle/templates/EventRegistration/send_email_to_participant_subject_template.twig
 * vendor/markocupic/sac-event-tool-bundle/templates/EventRegistration/send_email_to_participant_text_template.twig
 * vendor/markocupic/sac-event-tool-bundle/contao/languages/en/default.php
 * vendor/markocupic/sac-event-tool-bundle/public/css/be_stylesheet.css
 */
#[Route('/contao/send_email_to_participant/{event_id}/{sid}/{rt}', name: SendEmailToParticipantController::class, defaults: ['_scope' => 'backend', '_token_check' => true])]
class SendEmailToParticipantController extends AbstractController
{
    public const SESSION_BAG_KEY = 'sacevt_be_send_email';
    public const MAX_FILE_SIZE = 3000000;
    public const ALLOWED_EXTENSIONS = ['csv', 'bmp', 'png', 'svg', 'jpg', 'jpeg', 'tiff', 'doc', 'docx', 'pdf', 'xls', 'xlsx', 'txt', 'zip', 'rtf'];

    private CalendarEventsModel|null $event = null;
    private BackendUser|null $user = null;
    private string|null $sid = null;

    // Adapters
    private Adapter $message;
    private Adapter $controller;
    private Adapter $validator;
    private Adapter $events;
    private Adapter $calendarEventsHelper;
    private Adapter $calendarEventsMember;
    private Adapter $calendarEvents;

    public function __construct(
        private readonly ContaoFramework $framework,
        private readonly RequestStack $requestStack,
        private readonly Security $security,
        private readonly EventRegistrationUtil $eventRegistrationUtil,
        private readonly Connection $connection,
        private readonly TranslatorInterface $translator,
        private readonly Twig $twig,
        private readonly string $sacevtEventAdminEmail,
        private readonly string $sacevtEventAdminName,
    ) {
        $this->environment = $this->framework->getAdapter(Environment::class);
        $this->message = $this->framework->getAdapter(Message::class);
        $this->system = $this->framework->getAdapter(System::class);
        $this->userModel = $this->framework->getAdapter(UserModel::class);
        $this->calendarEvents = $this->framework->getAdapter(CalendarEventsModel::class);
        $this->calendarEventsHelper = $this->framework->getAdapter(CalendarEventsHelper::class);
        $this->calendarEventsMember = $this->framework->getAdapter(CalendarEventsMemberModel::class);
        $this->controller = $this->framework->getAdapter(Controller::class);
        $this->events = $this->framework->getAdapter(Events::class);
        $this->validator = $this->framework->getAdapter(Validator::class);
    }

    /**
     * @throws \Exception
     */
    public function __invoke(int $event_id, string $sid, string $rt): Response
    {
        // Initialize app
        $this->initialize($event_id, $sid);

        $uriSigner = $this->system->getContainer()->get('uri_signer');

        if (!$uriSigner->check($this->requestStack->getCurrentRequest()->getRequestUri())) {
            $this->message->addError($this->translator->trans('MSC.accessDenied', [], 'contao_default'));
            $this->controller->redirect($this->getBackUri());
        }

        $request = $this->requestStack->getCurrentRequest();

        if ($this->environment->get('isAjaxRequest') && $request->isMethod('POST')) {
            if ('uploadFile' === $request->request->get('action')) {
                return $this->xhrHandleFileUploads();
            }

            if ('initialize' === $request->request->get('action')) {
                return $this->xhrInitialize();
            }

            if ('deleteAttachment' === $request->request->get('action') && $request->request->get('fileId')) {
                return $this->xhrDeleteAttachment($request->request->get('fileId'));
            }

            if ('getTranslation' === $request->request->get('action') && $request->request->get('transKey') && $request->request->get('args')) {
                return $this->xhrGetTranslation($request->request->get('transKey'), $request->request->get('args'));
            }

            throw new AccessDeniedException('Access denied. Please use a valid "action" parameter.');
        }

        $template = new BackendTemplate('be_send_email_to_participant');
        $template->event = $this->event;
        $template->allowed_extensions = self::ALLOWED_EXTENSIONS;
        $template->back = $this->getBackUri();
        $template->form = $this->createAndValidateForm()->generate();
        $template->max_filesize = self::MAX_FILE_SIZE;
        $template->request_token = $rt;

        if ($this->message->hasError()) {
            $template->error = $this->message->generateUnwrapped();
        }

        return new Response($template->parse());
    }

    /**
     * @throws \Exception
     */
    private function initialize(int $eventId, string $sid): void
    {
        // Set a unique security ID (used for the session bag)
        $this->sid = $sid;

        // Get the event
        $this->event = $this->calendarEvents->findByPk($eventId);

        if (null === $this->event) {
            $this->message->error($this->translator->trans('MSC.evt_setp_eventNotFound', [$eventId], 'contao_default'));
            $this->controller->redirect($this->getBackUri());
        }

        // Get the logged in Contao backend user
        $this->user = $this->security->getUser();

        if (!$this->user instanceof BackendUser) {
            throw new \Exception('Access denied! User has to be logged in as a Contao backend user.');
        }
    }

    /**
     * @throws \Exception
     */
    private function createAndValidateForm(): Form
    {
        $form = new Form(
            'email_app_form',
            'POST',
        );

        $form->addContaoHiddenFields();

        $form->addFormField('recipients', [
            'label' => $this->translator->trans('MSC.evt_setp_emailRecipients', [], 'contao_default'),
            'inputType' => 'checkbox',
            'options' => $this->getRegistrations(),
            'eval' => ['class' => 'tl_checkbox_container', 'multiple' => true, 'mandatory' => true],
        ]);

        $form->addFormField('subject', [
            'label' => $this->translator->trans('MSC.evt_setp_emailSubject', [], 'contao_default'),
            'inputType' => 'text',
            'eval' => ['mandatory' => true],
        ]);

        $form->addFormField('text', [
            'label' => $this->translator->trans('MSC.evt_setp_emailText', [], 'contao_default'),
            'inputType' => 'textarea',
            'eval' => ['rows' => 20, 'cols' => 80, 'mandatory' => true],
        ]);

        $form->addFormField('submit', [
            'label' => $this->translator->trans('MSC.evt_setp_sendEmail', [], 'contao_default'),
            'inputType' => 'submit',
            'eval' => ['class' => 'tl_submit'],
        ]);

        $request = $this->requestStack->getCurrentRequest();

        if ($form->validate()) {
            $arrEmailRecipients = [];
            $recipients = $form->getWidget('recipients')->value;

            foreach ($recipients as $recipient) {
                $arrRecipient = explode('-', $recipient);

                if ('tl_user' === $arrRecipient[0]) {
                    $arrEmailRecipients[] = $this->userModel->findByPk($arrRecipient[1])->email;
                } else {
                    $arrEmailRecipients[] = $this->calendarEventsMember->findByPk($arrRecipient[1])->email;
                }
            }

            $objEmail = new Email();
            $objEmail->fromName = html_entity_decode((string) $this->sacevtEventAdminName);
            $objEmail->from = $this->sacevtEventAdminEmail;
            $objEmail->replyTo($this->user->email);
            $objEmail->subject = html_entity_decode((string) $request->request->get('subject'));
            $objEmail->text = html_entity_decode((string) $request->request->get('text'));

            // Handle attachments
            $fs = new Filesystem();

            $bag = $this->getSessionBag();
            $files = $bag['attachments'] ?? [];

            foreach ($files as $file) {
                if (is_file($file['storage_path'])) {
                    $filenameNew = \dirname($file['storage_path']).'/'.$file['name'];
                    $fs->rename($file['storage_path'], $filenameNew);
                    $objEmail->attachFile($filenameNew);
                }
            }

            $blnSend = false;

            try {
                $blnSend = $objEmail->sendTo($arrEmailRecipients);
            } catch (\Exception $e) {
                $this->saveFormInputsToSession($form);

                $this->message->addError($this->translator->trans('MSC.evt_setp_sendingEmailFailed', [], 'contao_default'));
                $this->message->addError($e->getMessage());

                $this->controller->reload();
            }

            if ($blnSend) {
                // Delete uploaded attachments from server
                foreach ($files as $file) {
                    if (is_dir(\dirname($file['storage_path']))) {
                        $fs->remove(\dirname($file['storage_path']));
                    }
                }

                // Show a message in the backend
                $msg = $this->translator->trans('MSC.evt_setp_emailSentToEventMembers', [], 'contao_default');
                $this->message->addInfo($msg);

                $this->clearSessionBag();

                // All ok! Redirect user back to the event member list
                $this->controller->redirect($this->getBackUri());
            }

            // Sending email failed! Reload page and show error message.
            $this->saveFormInputsToSession($form);
            $this->message->addError($this->translator->trans('MSC.evt_setp_sendingEmailFailed', [], 'contao_default'));
            $this->controller->reload();
        }

        // Preset input fields "subject" and "text" with text
        if ('email_app_form' !== $request->request->get('FORM_SUBMIT')) {
            if (empty($form->getWidget('text')->value) && empty($form->getWidget('subject')->value)) {
                $form->getWidget('text')->value = $this->twig->render(
                    '@MarkocupicSacEventTool/EventRegistration/send_email_to_participant_text_template.twig',
                    [
                        'event' => $this->event,
                        'user' => UserModel::findByPk($this->user->id),
                        'event_url' => $this->events->generateEventUrl($this->event, true),
                    ]
                );

                $form->getWidget('subject')->value = $this->twig->render(
                    '@MarkocupicSacEventTool/EventRegistration/send_email_to_participant_subject_template.twig',
                    [
                        'event' => $this->event,
                    ]
                );

                $this->saveFormInputsToSession($form);
            }

            $form = $this->setFormInputsFromSession($form);
        }

        return $form;
    }

    private function xhrInitialize(): Response
    {
        $bag = $this->getSessionBag();

        $json = [];
        $json['status'] = 'success';
        $json['attachments'] = $bag['attachments'];

        return new JsonResponse($json);
    }

    private function xhrDeleteAttachment(string $fileId): Response
    {
        $json = [];

        $bag = $this->getSessionBag();

        foreach ($bag['attachments'] as $index => $attachment) {
            if ($attachment['file_id'] === $fileId) {
                $storagePath = \dirname($attachment['storage_path']);
                unset($bag['attachments'][$index]);
                $json['deleted_source'] = $storagePath;

                if (is_dir($storagePath)) {
                    $fs = new Filesystem();
                    $fs->remove($storagePath);
                }

                $json['status'] = 'success';

                break;
            }
        }

        $bag['attachments'] = array_values($bag['attachments']);
        $this->setSessionBag($bag);

        $json['action'] = 'deleteAttachment';
        $json['attachments'] = $bag['attachments'];

        return new JsonResponse($json);
    }

    private function xhrHandleFileUploads(): Response
    {
        $hasError = false;
        $json = [];
        $json['status'] = 'error';

        $request = $this->requestStack->getCurrentRequest();

        if ($request->files->has('file')) {
            /** @var UploadedFile $uploadedFile */
            $uploadedFile = $request->files->get('file');

            $arrFile['name'] = $uploadedFile->getClientOriginalName();
            $arrFile['tmp_name'] = $uploadedFile->getBasename();
            $arrFile['size'] = $uploadedFile->getSize();
            $arrFile['type'] = $uploadedFile->getType();
            $arrFile['error'] = $uploadedFile->getError();
            $arrFile['extension'] = $uploadedFile->getClientOriginalExtension();
            $arrFile['file_id'] = uniqid();
            $arrFile['storage_path'] = Path::canonicalize(sys_get_temp_dir().'/'.$arrFile['file_id'].'/'.uniqid());

            if ($uploadedFile->getError()) {
                $hasError = true;
                $json['message'] = $uploadedFile->getErrorMessage();
            }

            if (!$hasError && $uploadedFile->getSize() > self::MAX_FILE_SIZE) {
                $hasError = true;
                $json['message'] = $this->translator->trans('MSC.evt_setp_maxFilesizeExceeded', [self::MAX_FILE_SIZE], 'contao_default');
            }

            if (!$hasError && !\in_array(strtolower($uploadedFile->getClientOriginalExtension()), self::ALLOWED_EXTENSIONS, true)) {
                $hasError = true;
                $json['message'] = $this->translator->trans('MSC.evt_setp_notAllowedFileExtension', [implode(',', self::ALLOWED_EXTENSIONS)], 'contao_default');
            }

            if (!$hasError) {
                $fs = new Filesystem();
                $fs->mkdir(\dirname($arrFile['storage_path']));

                try {
                    // Move file to the temp dir
                    if ($uploadedFile->move(\dirname($arrFile['storage_path']), basename($arrFile['storage_path']))) {
                        $json['status'] = 'success';
                        $json['message'] = $this->translator->trans('MSC.evt_setp_fileUploadedSuccessful', [implode(',', self::ALLOWED_EXTENSIONS)], 'contao_default');

                        // Save new upload to the session
                        $bag = $this->getSessionBag();
                        $bag['attachments'][] = $arrFile;
                        $this->setSessionBag($bag);
                    } else {
                        throw new \Exception($this->translator->trans('MSC.evt_setp_fileNotSubmitted', [], 'contao_default'));
                    }
                } catch (\Exception $e) {
                    $json['status'] = 'error';
                    $json['message'] = $e->getMessage();
                }
            }
        } else {
            $json['message'] = $this->translator->trans('MSC.evt_setp_fileNotSubmitted', [], 'contao_default');
        }

        $bag = $this->getSessionBag();

        $json['attachments'] = $bag['attachments'];

        return new JsonResponse($json);
    }

    /**
     * @param $args
     */
    private function xhrGetTranslation(string $transKey, $args): Response
    {
        $args = json_decode($args);
        $json = [];
        $json['status'] = 'success';
        $json['translation'] = $this->translator->trans($transKey, $args, 'contao_default');

        return new JsonResponse($json);
    }

    private function saveFormInputsToSession(Form $form): void
    {
        $bag = $this->getSessionBag();
        $bag['recipients'] = $form->getWidget('recipients')->value;
        $bag['subject'] = $form->getWidget('subject')->value;
        $bag['text'] = $form->getWidget('text')->value;

        $this->setSessionBag($bag);
    }

    private function setFormInputsFromSession(Form $form): Form
    {
        $bag = $this->getSessionBag();

        $form->getWidget('recipients')->value = $bag['recipients'];
        $form->getWidget('subject')->value = $bag['subject'];
        $form->getWidget('text')->value = $bag['text'];

        return $form;
    }

    private function getBackUri(): string
    {
        return $this->system->getContainer()
            ->get('router')
            ->generate('contao_backend', ['do' => 'sac_calendar_events_tool', 'table' => 'tl_calendar_events_member', 'id' => $this->event->id, 'rt' => $this->requestStack->getCurrentRequest()->attributes->get('rt')])
        ;
    }

    /**
     * @throws Exception
     *
     * @return array
     */
    private function getRegistrations()
    {
        $options = [];

        // Get the instructors first
        $arrGuideIDS = $this->calendarEventsHelper->getInstructorsAsArray($this->event, false);

        foreach ($arrGuideIDS as $userId) {
            $objInstructor = UserModel::findByPk($userId);

            if (null !== $objInstructor) {
                if (!empty($objInstructor->email)) {
                    if ($this->validator->isEmail($objInstructor->email)) {
                        $options['tl_user-'.$objInstructor->id] = sprintf('<strong>%s %s (Leiter)</strong>', $objInstructor->firstname, $objInstructor->lastname);
                    }
                }
            }
        }

        // Then get the event participants
        $stmt = $this->connection->executeQuery('SELECT * FROM tl_calendar_events_member WHERE eventId = ? ORDER BY stateOfSubscription, firstname', [$this->event->id]);

        while (false !== ($arrReg = $stmt->fetchAssociative())) {
            if ($this->validator->isEmail($arrReg['email'])) {
                $arrSubscriptionStates = EventSubscriptionState::ALL;
                $registrationModel = $this->calendarEventsMember->findByPk($arrReg['id']);
                $icon = $this->eventRegistrationUtil->getSubscriptionStateIcon($registrationModel);

                $regState = (string) $arrReg['stateOfSubscription'];
                $regState = \in_array($regState, $arrSubscriptionStates, true) ? $regState : EventSubscriptionState::SUBSCRIPTION_STATE_UNDEFINED;
                $strLabel = $GLOBALS['TL_LANG']['MSC'][$regState] ?? $regState;

                $options['tl_calendar_events_member-'.$arrReg['id']] = sprintf('%s %s %s (%s)', $icon, $arrReg['firstname'], $arrReg['lastname'], $strLabel);
            }
        }

        return $options;
    }

    private function getSessionBag(): array
    {
        $session = $this->requestStack->getCurrentRequest()->getSession();
        $bagAll = $session->get(self::SESSION_BAG_KEY, []);

        if (!isset($bagAll[$this->sid])) {
            $bagAll[$this->sid] = [
                'attachments' => [],
                'recipients' => [],
                'subject' => '',
                'text' => '',
            ];
            $session->set(self::SESSION_BAG_KEY, $bagAll);
        }

        return $bagAll[$this->sid];
    }

    private function setSessionBag(array $arrBag): void
    {
        $session = $this->requestStack->getCurrentRequest()->getSession();

        $bagAll = $session->get(self::SESSION_BAG_KEY, []);

        if (!isset($bagAll[$this->sid])) {
            // First create a bag, if there isn't already one!
            $this->getSessionBag();
            $bagAll = $session->get(self::SESSION_BAG_KEY, []);
        }

        $bagAll[$this->sid] = $arrBag;
        $session->set(self::SESSION_BAG_KEY, $bagAll);
    }

    private function clearSessionBag(): void
    {
        $session = $this->requestStack->getCurrentRequest()->getSession();

        $bagAll = $session->get(self::SESSION_BAG_KEY);
        unset($bagAll[$this->sid]);
        $bagAll = array_values($bagAll);

        $session->set(self::SESSION_BAG_KEY, $bagAll);
    }
}
