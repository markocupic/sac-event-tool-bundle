<?php

declare(strict_types=1);

/*
 * This file is part of SAC Event Tool Bundle.
 *
 * (c) Marko Cupic <m.cupic@gmx.ch>
 * @license GPL-3.0-or-later
 * For the full copyright and license information,
 * please view the LICENSE file that was distributed with this source code.
 * @link https://github.com/markocupic/sac-event-tool-bundle
 */

namespace Markocupic\SacEventToolBundle\Controller\BackendModule;

use CloudConvert\Exceptions\HttpClientException;
use Code4Nix\UriSigner\UriSigner;
use Codefog\HasteBundle\Form\Form;
use Contao\CalendarEventsModel;
use Contao\Config;
use Contao\Controller;
use Contao\CoreBundle\Controller\AbstractBackendController;
use Contao\CoreBundle\Csrf\ContaoCsrfTokenManager;
use Contao\CoreBundle\Framework\Adapter;
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\CoreBundle\Util\UrlUtil;
use Contao\Email;
use Contao\Events;
use Contao\Input;
use Contao\Message;
use Contao\Model\Collection;
use Contao\StringUtil;
use Contao\System;
use Contao\UserModel;
use Markocupic\SacEventToolBundle\DocxTemplator\DocumentType;
use Markocupic\SacEventToolBundle\DocxTemplator\Exception\TourRapportGeneratorException;
use Markocupic\SacEventToolBundle\DocxTemplator\OutputType;
use Markocupic\SacEventToolBundle\DocxTemplator\TourRapportGenerator;
use Markocupic\SacEventToolBundle\Model\CalendarEventsInstructorInvoiceModel;
use Markocupic\SacEventToolBundle\Model\EventOrganizerModel;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Core\Exception\InvalidCsrfTokenException;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Contracts\Translation\TranslatorInterface;
use Twig\Environment as Twig;

/**
 * Roughly speaking, this Contao backend controller sends the goal report and the
 * billing form to the "Tourenchef" and/or "Administration" via email. The
 * extension generates an email form with the input fields "recipients", "subject"
 * and "text". The recipient input field is automatically filled with the e-mail
 * addresses that were set in the event organizer settings. The billing form and
 * tour report are automatically attached to the message. Both files are converted
 * from docx to PDF using the CloudConvert API before sending.
 *
 * Involved files:
 * vendor/markocupic/sac-event-tool-bundle/templates/backend/tl_calendar_events_member/be_event_participant_email.html.twig
 * vendor/markocupic/sac-event-tool-bundle/templates/Email/EventRegistration/email_event_participant.twig
 * vendor/markocupic/sac-event-tool-bundle/contao/languages/en/default.php
 * vendor/markocupic/sac-event-tool-bundle/public/css/be_stylesheet.css
 */
#[Route('/contao/send_tour_rapport_notification/{rapport_id}/{sid}/{rt}/{action}', name: SendTourRapportNotificationController::class, defaults: ['_scope' => 'backend', '_token_check' => true])]
class SendTourRapportNotificationController extends AbstractBackendController
{
    public const string SESSION_BAG_KEY = 'sacevt_send_tour_notification';

    private string|null $sid = null;

    private Adapter $config;

    private Adapter $controller;

    private Adapter $events;

    private Adapter $message;

    private Adapter $stringUtil;

    private Adapter $system;

    private Adapter $urlUtil;

    public function __construct(
        private readonly ContaoCsrfTokenManager $csrfTokenManager,
        private readonly ContaoFramework $framework,
        #[Autowire(param: 'sacevt.mailer_transports.event_admin')]
        private readonly array $mailerTransport,
        private readonly RequestStack $requestStack,
        private readonly RouterInterface $router,
        private readonly TourRapportGenerator $tourRapportGenerator,
        private readonly TranslatorInterface $translator,
        private readonly Twig $twig,
        private readonly UriSigner $uriSigner,
        private readonly string $sacevtEventTemplateTourInvoice,
        private readonly string $sacevtEventTemplateTourRapport,
        private readonly string $sacevtEventTourInvoiceFileNamePattern,
        private readonly string $sacevtEventTourRapportFileNamePattern,
    ) {
        $this->config = $this->framework->getAdapter(Config::class);
        $this->controller = $this->framework->getAdapter(Controller::class);
        $this->events = $this->framework->getAdapter(Events::class);
        $this->message = $this->framework->getAdapter(Message::class);
        $this->stringUtil = $this->framework->getAdapter(StringUtil::class);
        $this->system = $this->framework->getAdapter(System::class);
        $this->urlUtil = $this->framework->getAdapter(UrlUtil::class);
    }

    public function __invoke(int $rapport_id, string $sid, string $rt, Request $request, string $action = ''): Response
    {
        $this->controller->loadLanguageFile('modules');
        $this->sid = $sid;

        // Will throw an exception if the CSRF token is invalid
        $this->checkIsCsrfTokenValid($rt);

        $this->setRefererIfNotSet($this->system->getReferer());

        // Will redirect back to the referer page if the signed URL is invalid
        $this->checkIsSignedUrlValid($request);

        $invoice = $this->getInvoice($rapport_id);
        $event = $this->prevalidateDocumentGenerationAndGetEvent($invoice);

        if (null === $event) {
            return $this->redirectToRefererPage($request);
        }

        if ($response = $this->handleDownloadActions($action, $invoice)) {
            return $response;
        }

        $biller = $this->getBiller($rapport_id);
        $form = $this->createAndValidateForm($request, $event, $biller);

        if ($form->isSubmitted()) {
            // Generate documents, send email and redirect to the referer page
            return $this->processFormSubmission($request, $form, $invoice, $biller);
        }

        return $this->renderFormView($request, $form, $event, $invoice, $rapport_id, $sid, $rt);
    }

    private function prevalidateDocumentGenerationAndGetEvent(CalendarEventsInstructorInvoiceModel $invoice): CalendarEventsModel|null
    {
        try {
            return $this->tourRapportGenerator->validateAndGetEvent($invoice);
        } catch (TourRapportGeneratorException $e) {
            $this->message->addError($e->getTranslatableText());

            return null;
        }
    }

    private function handleDownloadActions(string $action, CalendarEventsInstructorInvoiceModel $invoice): Response|null
    {
        if ('download_tour_rapport' === $action) {
            return $this->downloadTourRapport($invoice);
        }

        if ('download_invoice' === $action) {
            return $this->downloadInvoice($invoice);
        }

        return null;
    }

    private function renderFormView(Request $request, Form $form, CalendarEventsModel $event, CalendarEventsInstructorInvoiceModel $invoice, int $rapport_id, string $sid, string $rt): Response
    {
        $view = [
            'title' => $this->translator->trans('MOD.calendar.0', [], 'contao_default'),
            'headline' => $this->translator->trans('MSC.evt_strn_title', [], 'contao_default'),
            'request_token' => $rt,
            'event' => $event,
            'back' => $this->getRefererUri($request),
            'form' => $form->generate(),
            'download_tour_rapport_uri' => $this->generateSignedDownloadUri($rapport_id, $sid, $rt, 'download_tour_rapport'),
            'download_invoice_uri' => $this->generateSignedDownloadUri($rapport_id, $sid, $rt, 'download_invoice'),
        ];

        if ($invoice->countNotifications) {
            $message = $this->translator->trans('MSC.evt_strn_multiFormSubmitWarning', [$invoice->countNotifications, date('d.m.Y H:i', (int) $invoice->notificationSentOn)], 'contao_default');
            $this->message->addInfo($message);
        }

        // Add messages to the view The be_main templates will display all message types
        // under the error template var.
        $view['error'] = $this->message->generate();

        return $this->render('@MarkocupicSacEventTool/TourRapport/tour_rapport_notification.twig', $view);
    }

    private function generateSignedDownloadUri(int $rapport_id, string $sid, string $rt, string $action): string
    {
        $uri = $this->router->generate(self::class, ['rapport_id' => $rapport_id, 'sid' => $sid, 'rt' => $rt, 'action' => $action]);

        return $this->uriSigner->sign($uri);
    }

    private function processFormSubmission(Request $request, Form $form, CalendarEventsInstructorInvoiceModel $invoice, UserModel $biller): Response
    {
        $rapportDocument = $this->generateDocument(DocumentType::RAPPORT, $invoice, $this->sacevtEventTemplateTourRapport, $this->sacevtEventTourRapportFileNamePattern, 'Tourrapport', $invoice->id);

        if (null === $rapportDocument) {
            return new RedirectResponse($request->getUri());
        }

        $invoiceDocument = $this->generateDocument(DocumentType::INVOICE, $invoice, $this->sacevtEventTemplateTourInvoice, $this->sacevtEventTourInvoiceFileNamePattern, 'Invoice', $invoice->id);

        if (null === $invoiceDocument) {
            return new RedirectResponse($request->getUri());
        }

        return $this->sendEmailAndRedirect($request, $form, $invoice, $biller, $rapportDocument, $invoiceDocument);
    }

    private function generateDocument(DocumentType $documentType, CalendarEventsInstructorInvoiceModel $invoice, string $template, string $fileNamePattern, string $documentName, int $rapport_id): \SplFileObject|null
    {
        try {
            $file = $this->tourRapportGenerator->generate($documentType, $invoice, OutputType::PDF, $template, $fileNamePattern);
            $this->validateGeneratedFile($file);

            return $file;
        } catch (HttpClientException $e) {
            $this->handleDocumentGenerationError($e, $documentName, true, $rapport_id);

            return null;
        } catch (\Exception $e) {
            $this->handleDocumentGenerationError($e, $documentName, false, $rapport_id);

            return null;
        }
    }

    private function validateGeneratedFile(\SplFileObject $file): void
    {
        if (false === $file->getSize() || 5000 > $file->getSize()) {
            throw new \Exception(\sprintf('File conversion failed. File size of the converted file "%s" is too small. File size: %d bytes!', $file->getFilename(), $file->getSize()));
        }
    }

    private function handleDocumentGenerationError(\Exception $e, string $documentName, bool $isHttpClientException, int $rapport_id): void
    {
        $messageKey = $isHttpClientException ? 'ERR.evt_strn_cloudconvConversionCreditUsedUp' : 'ERR.evt_strn_cloudconvUnexpectedError';
        $message = $this->translator->trans($messageKey, [$documentName], 'contao_default');
        $this->message->addError($message);
        $this->notifyAdminOnError($e, $rapport_id);
    }

    private function sendEmailAndRedirect(Request $request, Form $form, CalendarEventsInstructorInvoiceModel $invoice, UserModel $biller, \SplFileObject $rapportFile, \SplFileObject $invoiceFile): Response
    {
        $recipients = $form->getWidget('recipients')->value;

        try {
            $success = $this->sendEmail($request, $form, $biller, $rapportFile, $invoiceFile);

            if (!$success) {
                throw new \Exception('Sending email failed.');
            }
        } catch (\Exception) {
            $this->addEmailErrorMessage($recipients);

            return new RedirectResponse($request->getUri());
        }

        $this->updateInvoiceAfterSuccessfulSend($invoice);
        $this->addSuccessMessage($recipients, $biller->email);

        return $this->redirectToRefererPage($request);
    }

    private function updateInvoiceAfterSuccessfulSend(CalendarEventsInstructorInvoiceModel $invoice): void
    {
        $invoice->notificationSentOn = time();
        ++$invoice->countNotifications;
        $invoice->save();
    }

    private function addSuccessMessage(string $recipients, string $billerEmail): void
    {
        $message = $this->translator->trans('MSC.evt_strn_successfullySendNotification', [$recipients, $billerEmail], 'contao_default');
        $this->message->addConfirmation($message);
    }

    private function addEmailErrorMessage(string $recipients): void
    {
        $message = $this->translator->trans('ERR.evt_strn_sendNotificationFailed', [$recipients], 'contao_default');
        $this->message->addError($message);
    }

    private function downloadTourRapport(CalendarEventsInstructorInvoiceModel $invoice): BinaryFileResponse
    {
        return $this->tourRapportGenerator
            ->download(
                DocumentType::RAPPORT,
                $invoice,
                OutputType::PDF,
                $this->sacevtEventTemplateTourRapport,
                $this->sacevtEventTourRapportFileNamePattern,
            )
        ;
    }

    private function downloadInvoice(CalendarEventsInstructorInvoiceModel $invoice): BinaryFileResponse
    {
        return $this->tourRapportGenerator
            ->download(
                DocumentType::INVOICE,
                $invoice,
                OutputType::PDF,
                $this->sacevtEventTemplateTourInvoice,
                $this->sacevtEventTourInvoiceFileNamePattern,
            )
        ;
    }

    private function checkIsCsrfTokenValid(string $strToken): void
    {
        $container = $this->system->getContainer();

        if (!$this->csrfTokenManager->isTokenValid(new CsrfToken($container->getParameter('contao.csrf_token_name'), $strToken))) {
            throw new InvalidCsrfTokenException('Invalid CSRF token provided.');
        }
    }

    private function checkIsSignedUrlValid(Request $request): void
    {
        if (!$this->uriSigner->check($request->getRequestUri())) {
            $this->message->addError($this->translator->trans('ERR.evt_strn_linkExpired', [], 'contao_default'));
            $this->redirectToRefererPage($request);
        }
    }

    private function getInvoice(int $id): CalendarEventsInstructorInvoiceModel
    {
        $invoice = $this->framework->getAdapter(CalendarEventsInstructorInvoiceModel::class)->findById($id);

        if (null === $invoice) {
            throw new \InvalidArgumentException('Invoice with ID '.$id.' not found.');
        }

        return $invoice;
    }

    private function getBiller(int $invoiceId): UserModel
    {
        $invoice = $this->getInvoice($invoiceId);

        $user = $this->framework->getAdapter(UserModel::class)->findById($invoice->userPid);

        if (null === $user) {
            throw new \InvalidArgumentException('User with ID '.$invoice->userPid.' not found.');
        }

        return $user;
    }

    private function getRefererUri(Request $request): string
    {
        $arrBag = $this->getSessionBag();

        return $this->urlUtil->makeAbsolute($arrBag['referer'], $request->getSchemeAndHttpHost());
    }

    private function redirectToRefererPage(Request $request): RedirectResponse
    {
        // Get referer url from session
        $url = $this->getRefererUri($request);

        $this->clearSessionBag();

        return $this->redirect($url);
    }

    private function getOrganizers(CalendarEventsModel $event): Collection|null
    {
        $arrIDS = $this->stringUtil->deserialize($event->organizers, true);

        return $this->framework->getAdapter(EventOrganizerModel::class)->findMultipleByIds($arrIDS);
    }

    private function getRecipients(CalendarEventsModel $event): array
    {
        $arrRecipients = [];

        $organizer = $this->getOrganizers($event);

        if (null !== $organizer) {
            $i = 0;

            while ($organizer->next()) {
                if ($organizer->enableRapportNotification) {
                    ++$i;

                    // We let the user enter the recipients manually if the event belongs to more
                    // than one organizer because we don't want an event to be billed multiple times
                    if ($i > 1 && !empty($organizer->eventRapportNotificationRecipients) && !empty($arrRecipients)) {
                        return [];
                    }

                    $arrRecipients = array_filter(array_unique(array_merge($arrRecipients, explode(',', $organizer->eventRapportNotificationRecipients))));
                }
            }
        }

        return array_filter(array_unique($arrRecipients));
    }

    private function createAndValidateForm(Request $request, CalendarEventsModel $event, UserModel $biller): Form
    {
        $form = new Form(
            'send_tour_rapport_notification_form',
            'POST',
        );

        $form->addContaoHiddenFields();

        $form->addFormField('recipients', [
            'label' => $this->translator->trans('MSC.evt_strn_emailRecipients', [], 'contao_default'),
            'inputType' => 'text',
            'eval' => ['rgxp' => 'emails', 'readonly' => false, 'placeholder' => $this->translator->trans('MSC.evt_strn_emailRecipientsPlaceholder', [], 'contao_default'), 'class' => 'clr', 'mandatory' => true],
        ]);

        $form->addFormField('subject', [
            'label' => $this->translator->trans('MSC.evt_strn_emailSubject', [], 'contao_default'),
            'inputType' => 'text',
            'eval' => ['mandatory' => true, 'class' => 'clr'],
        ]);

        $form->addFormField('text', [
            'label' => $this->translator->trans('MSC.evt_strn_emailText', [], 'contao_default'),
            'inputType' => 'textarea',
            'eval' => ['rows' => 20, 'cols' => 80, 'mandatory' => true, 'class' => 'clr'],
        ]);

        $form->addFormField('submit', [
            'label' => $this->translator->trans('MSC.evt_strn_sendEmail', [], 'contao_default'),
            'inputType' => 'submit',
        ]);

        // Sanitize email input
        if ($form->getFormId() === $request->request->get('FORM_SUBMIT')) {
            $input = $this->framework->getAdapter(Input::class);

            $recipients = (string) $input->post('recipients');
            $recipients = str_replace([' ', ';'], ['', ','], $recipients);
            $recipients = trim($recipients, ',');

            $input->setPost('recipients', $recipients);
        }

        if ($form->validate()) {
            $recipients = $form->getWidget('recipients')->value;
            $form->getWidget('recipients')->value = str_replace(' ', '', $recipients);

            $form->setIsSubmitted(true);

            return $form;
        }

        // !important otherwise the docx files will be converted, and Contao will try to
        // send the email
        $form->setIsSubmitted(false);

        // Preset input fields "subject" and "text" with a default text
        if ('send_tour_rapport_notification_form' !== $request->request->get('FORM_SUBMIT')) {
            if (empty($form->getWidget('recipients')->value) && empty($form->getWidget('text')->value) && empty($form->getWidget('subject')->value)) {
                $form->getWidget('recipients')->value = implode(',', $this->getRecipients($event));

                // Render email subject
                $subject = $this->twig->render('@MarkocupicSacEventTool/Email/TourRapport/email_tour_rapport.twig', [
                    'renderEmailSubject' => true,
                    'event' => $event,
                    'instructor' => $biller,
                ]);

                $subject = $this->stringUtil->revertInputEncoding($subject);

                $form->getWidget('subject')->value = $subject;

                // Render email text
                $text = $this->twig->render('@MarkocupicSacEventTool/Email/TourRapport/email_tour_rapport.twig', [
                    'renderEmailText' => true,
                    'event' => $event,
                    'instructor' => $biller,
                    'event_url' => $this->events->generateEventUrl($event, true),
                ]);

                $text = $this->stringUtil->revertInputEncoding($text);

                $form->getWidget('text')->value = $text;
            }
        }

        $this->saveFormInputsToSession($form);
        $this->setFormInputsFromSession($form);

        return $form;
    }

    private function sendEmail(Request $request, Form $form, UserModel $biller, \SplFileObject $rapportFile, \SplFileObject $invoiceFile): bool
    {
        $objEmail = new Email();
        // Set the correct transport
        $objEmail->addHeader('X-Transport', $this->mailerTransport['transport_name']);
        $objEmail->fromName = html_entity_decode($this->mailerTransport['sender_name']);
        $objEmail->from = $this->mailerTransport['sender_email'];
        $objEmail->replyTo($biller->email);

        $objEmail->subject = html_entity_decode((string) $request->request->get('subject'));
        $objEmail->text = html_entity_decode((string) $request->request->get('text'));

        $objEmail->attachFile($rapportFile->getRealPath());
        $objEmail->attachFile($invoiceFile->getRealPath());

        $objEmail->sendCc($biller->email);

        $arrRecipients = explode(',', $form->getWidget('recipients')->value);
        $arrRecipients = array_filter(array_unique($arrRecipients));

        try {
            $blnSend = $objEmail->sendTo(...$arrRecipients);
        } catch (\Exception) {
            $blnSend = false;
        }

        return $blnSend;
    }

    private function saveFormInputsToSession(Form $form): void
    {
        $bag = $this->getSessionBag();
        $bag['recipients'] = $form->getWidget('recipients')->value;
        $bag['subject'] = $form->getWidget('subject')->value;
        $bag['text'] = $form->getWidget('text')->value;

        $this->setSessionBag($bag);
    }

    private function setFormInputsFromSession(Form $form): void
    {
        $bag = $this->getSessionBag();

        $form->getWidget('recipients')->value = $bag['recipients'];
        $form->getWidget('subject')->value = $bag['subject'];
        $form->getWidget('text')->value = $bag['text'];
    }

    private function setRefererIfNotSet(string $strUri): void
    {
        $arrBag = $this->getSessionBag();

        if (!empty($arrBag['referer'])) {
            return;
        }

        $arrBag['referer'] = $strUri;

        $this->setSessionBag($arrBag);
    }

    private function getSessionBag(): array
    {
        $session = $this->requestStack->getCurrentRequest()->getSession();
        $bagAll = $session->get(self::SESSION_BAG_KEY, []);

        if (!isset($bagAll[$this->sid])) {
            $bagAll[$this->sid] = [
                'referer' => $this->system->getReferer(),
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
            // First, create a session bag if there isn't already one!
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

    private function notifyAdminOnError(\Exception $e, int $rapport_id): void
    {
        $adminName = $this->config->get('adminName') ?? 'Administrator';
        $adminEmail = $this->config->get('adminEmail');

        if ($adminEmail && $adminName) {
            $email = new Email();
            $email->subject = 'Could not send tour report notification due to an error.';
            $email->text = implode("\r\n\r\n", [
                'Backend User: '.$this->getUser()->getUserIdentifier(),
                'Rapport ID: '.$rapport_id,
                'Error message: '.$e->getMessage(),
                'Instance of: '.$e::class,
                'Code: '.$e->getCode(),
                'Line: '.$e->getLine(),
                'Stack trace: '."\r\n".$e->getTraceAsString(),
            ]);
            $email->fromName = $adminName;
            $email->from = $adminEmail;
            $email->sendTo($adminEmail);
        }
    }
}
