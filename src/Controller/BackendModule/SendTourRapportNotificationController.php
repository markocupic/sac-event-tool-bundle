<?php

declare(strict_types=1);

/*
 * This file is part of SAC Event Tool Bundle.
 *
 * (c) Marko Cupic 2024 <m.cupic@gmx.ch>
 * @license GPL-3.0-or-later
 * For the full copyright and license information,
 * please view the LICENSE file that was distributed with this source code.
 * @link https://github.com/markocupic/sac-event-tool-bundle
 */

namespace Markocupic\SacEventToolBundle\Controller\BackendModule;

use CloudConvert\Exceptions\HttpClientException;
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
use Markocupic\SacEventToolBundle\DocxTemplator\TourRapportGenerator;
use Markocupic\SacEventToolBundle\Model\CalendarEventsInstructorInvoiceModel;
use Markocupic\SacEventToolBundle\Model\EventOrganizerModel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Core\Exception\InvalidCsrfTokenException;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Contracts\Translation\TranslatorInterface;
use Twig\Environment as Twig;

/**
 * Roughly speaking, this Contao backend controller sends the goal report and the billing form to the "Tourenchef" and/or "Administration" via email.
 * The extension generates an email form with the input fields "recipients", "subject" and "text".
 * The recipient input field is automatically filled with the e-mail addresses that were set in the event organizer settings.
 * The billing form and tour report are automatically attached to the message.
 * Both files are converted from docx to pdf using the CloudConvert API before sending.
 *
 * involved files:
 * vendor/markocupic/sac-event-tool-bundle/contao/templates/backend/tl_calendar_events_member/be_event_participant_email.html.twig
 * vendor/markocupic/sac-event-tool-bundle/templates/EventRegistration/event_participant_email_subject_template.twig
 * vendor/markocupic/sac-event-tool-bundle/templates/EventRegistration/event_participant_email_text_template.twig
 * vendor/markocupic/sac-event-tool-bundle/contao/languages/en/default.php
 * vendor/markocupic/sac-event-tool-bundle/public/css/be_stylesheet.css
 */
#[Route('/contao/send_tour_rapport_notification/{rapport_id}/{sid}/{rt}/{action}', name: SendTourRapportNotificationController::class, defaults: ['_scope' => 'backend', '_token_check' => true])]
class SendTourRapportNotificationController extends AbstractBackendController
{
    public const SESSION_BAG_KEY = 'sacevt_send_tour_notification';

    private string|null $sid = null;
    private Adapter $stringUtil;
    private Adapter $urlUtil;
    private Adapter $controller;
    private Adapter $message;
    private Adapter $system;
    private Adapter $events;
    private Adapter $config;

    public function __construct(
        private readonly ContaoFramework $framework,
        private readonly ContaoCsrfTokenManager $csrfTokenManager,
        private readonly TourRapportGenerator $tourRapportGenerator,
        private readonly RequestStack $requestStack,
        private readonly TranslatorInterface $translator,
        private readonly Twig $twig,
        private readonly string $sacevtEventTemplateTourRapport,
        private readonly string $sacevtEventTemplateTourInvoice,
        private readonly string $sacevtEventTourInvoiceFileNamePattern,
        private readonly string $sacevtEventTourRapportFileNamePattern,
        private readonly string $sacevtEventAdminEmail,
        private readonly string $sacevtEventAdminName,
    ) {
        $this->stringUtil = $this->framework->getAdapter(StringUtil::class);
        $this->urlUtil = $this->framework->getAdapter(UrlUtil::class);
        $this->controller = $this->framework->getAdapter(Controller::class);
        $this->message = $this->framework->getAdapter(Message::class);
        $this->system = $this->framework->getAdapter(System::class);
        $this->events = $this->framework->getAdapter(Events::class);
        $this->config = $this->framework->getAdapter(Config::class);
    }

    public function __invoke(int $rapport_id, string $sid, string $rt, Request $request, string $action = ''): Response
    {
        $this->framework->initialize();
        $this->sid = $sid;
        $uriSigner = $this->system->getContainer()->get('code4nix_uri_signer.uri_signer');
        $router = $this->system->getContainer()->get('router');

        // Do some checks
        $this->checkIsCsrfTokenValid($rt);
        $this->checkIsSignedUrlValid($request, $uriSigner);
        $this->setRefererIfNotSet($this->system->getReferer());

        $invoice = $this->getInvoice($rapport_id);
        $event = $this->getEvent($rapport_id);
        $biller = $this->getBiller($rapport_id);

        if ('download_tour_rapport' === $action) {
            return $this->downloadTourRapport($invoice);
        }

        if ('download_invoice' === $action) {
            return $this->downloadInvoice($invoice);
        }

        $form = $this->createAndValidateForm($request, $event, $biller);

        if (true !== $form->isSubmitted()) {
            // Display the email form
            $view = [];

			$view['headline'] = $this->translator->trans('MSC.evt_strn_title',[], 'contao_default');
            $view['request_token'] = $rt;
            $view['event'] = $event;
            $view['back'] = $this->getBackUri($request);
            $view['form'] = $form->generate();
            $view['download_tour_rapport_uri'] = $uriSigner->sign($router->generate(self::class, ['rapport_id' => $rapport_id, 'sid' => $sid, 'rt' => $rt, 'action' => 'download_tour_rapport']));
            $view['download_invoice_uri'] = $uriSigner->sign($router->generate(self::class, ['rapport_id' => $rapport_id, 'sid' => $sid, 'rt' => $rt, 'action' => 'download_invoice']));

            if ($invoice->countNotifications) {
                // Protect the user from submitting the form multiple times.
                $view['info'] = $this->translator->trans('MSC.evt_strn_multiFormSubmitWarning', [$invoice->countNotifications, date('d.m.Y H:i', (int) $invoice->notificationSentOn)], 'contao_default');
            }

            return $this->render('@MarkocupicSacEventTool/CalendarEventsInstructorInvoice/be_send_tour_rapport_notification.html.twig', $view);
        }

        // Form inputs have passed validation:
        // I. Generate tour report file and convert from docx to pdf using the Cloudconvert API.
        // II. Generate tour invoice file and convert from docx to pdf using the Cloudconvert API.
        // III. Send notification via email.
        // IV. Redirect back to the referer page

        // I. Generate tour report file and convert from docx to pdf using the Cloudconvert API.
        try {
            $rapportFile = $this->tourRapportGenerator
                ->generate(
                    'rapport',
                    $invoice,
                    TourRapportGenerator::OUTPUT_TYPE_PDF,
                    $this->sacevtEventTemplateTourRapport,
                    $this->sacevtEventTourRapportFileNamePattern,
                )
            ;

            if (false === $rapportFile->getSize() || 5000 > $rapportFile->getSize()) {
                throw new \Exception(sprintf('File conversion failed. File size of the converted file "%s" is too small. File size: %d bytes!', $rapportFile->getFilename(), $rapportFile->getSize()));
            }
        } catch (HttpClientException $e) {
            $pdfConversionError = $this->translator->trans('ERR.evt_strn_cloudconvConversionCreditUsedUp', ['Tourrapport'], 'contao_default');
            $this->notifyAdminOnError($e, $rapport_id);
        } catch (\Exception $e) {
            $pdfConversionError = $this->translator->trans('ERR.evt_strn_cloudconvUnexpectedError', ['Tourrapport'], 'contao_default');
            $this->notifyAdminOnError($e, $rapport_id);
        }

        if (!empty($pdfConversionError)) {
            $this->message->addError($pdfConversionError);

            // IV. Redirect back to the referer page
            return $this->redirectBackToRefererPage($request);
        }

        // II. Generate tour invoice file and convert from docx to pdf using the Cloudconvert API.
        try {
            $invoiceFile = $this->tourRapportGenerator
                ->generate(
                    'invoice',
                    $invoice,
                    TourRapportGenerator::OUTPUT_TYPE_PDF,
                    $this->sacevtEventTemplateTourInvoice,
                    $this->sacevtEventTourInvoiceFileNamePattern,
                )
            ;

            if (false === $invoiceFile->getSize() || 5000 > $invoiceFile->getSize()) {
                throw new \Exception(sprintf('File conversion failed. File size of the converted file "%s" is too small. File size: %d bytes!', $invoiceFile->getFilename(), $invoiceFile->getSize()));
            }
        } catch (HttpClientException $e) {
            $pdfConversionError = $this->translator->trans('ERR.evt_strn_cloudconvConversionCreditUsedUp', ['Vergütungsformular'], 'contao_default');
            $this->notifyAdminOnError($e, $rapport_id);
        } catch (\Exception $e) {
            $pdfConversionError = $this->translator->trans('ERR.evt_strn_cloudconvUnexpectedError', ['Vergütungsformular'], 'contao_default');
            $this->notifyAdminOnError($e, $rapport_id);
        }

        if (!empty($pdfConversionError)) {
            // If docx to pdf conversion fails...
            $this->message->addError($pdfConversionError);

            // IV. Redirect back to the referer page
            return $this->redirectBackToRefererPage($request);
        }

        $strRecipients = $form->getWidget('recipients')->value;

        // III. Send email
        try {
            $blnSend = $this->sendEmail($request, $form, $biller, $rapportFile, $invoiceFile);
        } catch (\Exception $e) {
            $msg = $this->translator->trans('ERR.evt_strn_sendNotificationFailed', [$strRecipients], 'contao_default');
            $this->message->addError($msg);

            // IV. Redirect back to the referer page
            return $this->redirectBackToRefererPage($request);
        }

        if ($blnSend) {
            // Everything ok...
            $invoice->notificationSentOn = time();
            ++$invoice->countNotifications;
            $invoice->save();

            $msg = $this->translator->trans('MSC.evt_strn_successfullySendNotification', [$strRecipients, $biller->email], 'contao_default');
            $this->message->addConfirmation($msg);
        } else {
            // If sending email fails...
            $msg = $this->translator->trans('ERR.evt_strn_sendNotificationFailed', [$strRecipients], 'contao_default');
            $this->message->addError($msg);
        }

        // IV. Redirect back to the referer page
        return $this->redirectBackToRefererPage($request);
    }

    protected function downloadTourRapport(CalendarEventsInstructorInvoiceModel $invoice): BinaryFileResponse
    {
        return $this->tourRapportGenerator
            ->download(
                'rapport',
                $invoice,
                TourRapportGenerator::OUTPUT_TYPE_PDF,
                $this->sacevtEventTemplateTourRapport,
                $this->sacevtEventTourRapportFileNamePattern,
            )
        ;
    }

    protected function downloadInvoice(CalendarEventsInstructorInvoiceModel $invoice): BinaryFileResponse
    {
        return $this->tourRapportGenerator
            ->download(
                'invoice',
                $invoice,
                TourRapportGenerator::OUTPUT_TYPE_PDF,
                $this->sacevtEventTemplateTourInvoice,
                $this->sacevtEventTourInvoiceFileNamePattern,
            )
        ;
    }

    protected function checkIsCsrfTokenValid(string $strToken): void
    {
        $container = $this->system->getContainer();

        if (!$this->csrfTokenManager->isTokenValid(new CsrfToken($container->getParameter('contao.csrf_token_name'), $strToken))) {
            throw new InvalidCsrfTokenException('Invalid CSRF token provided.');
        }
    }

    protected function checkIsSignedUrlValid(Request $request, $uriSigner): void
    {
        if (!$uriSigner->check($request->getRequestUri())) {
            $this->message->addError($this->translator->trans('ERR.evt_strn_linkExpired', [], 'contao_default'));
            $this->controller->redirect($this->system->getReferer());
        }
    }

    protected function getInvoice(int $id): CalendarEventsInstructorInvoiceModel
    {
        $invoice = CalendarEventsInstructorInvoiceModel::findByPk($id);

        if (null === $invoice) {
            throw new \InvalidArgumentException('Invoice with ID '.$id.' not found.');
        }

        return $invoice;
    }

    protected function getEvent(int $id): CalendarEventsModel
    {
        $invoice = $this->getInvoice($id);

        /** @var CalendarEventsModel $event */
        $event = $invoice->getRelated('pid');

        if (null === $event) {
            throw new \InvalidArgumentException('Related event not found.');
        }

        return $event;
    }

    protected function getBiller(int $id): UserModel
    {
        $invoice = $this->getInvoice($id);

        $user = UserModel::findByPk($invoice->userPid);

        if (null === $user) {
            throw new \InvalidArgumentException('User with ID '.$invoice->userPid.' not found.');
        }

        return $user;
    }

    protected function getBackUri(Request $request): string
    {
        $arrBag = $this->getSessionBag();

        return $this->urlUtil->makeAbsolute($arrBag['referer'], $request->getSchemeAndHttpHost());
    }

    protected function redirectBackToRefererPage(Request $request): RedirectResponse
    {
        // Get referer url from session
        $url = $this->getBackUri($request);

        $this->clearSessionBag();

        return $this->redirect($url);
    }

    protected function getOrganizers(CalendarEventsModel $event): Collection|null
    {
        $arrIDS = $this->stringUtil->deserialize($event->organizers, true);

        return EventOrganizerModel::findMultipleByIds($arrIDS);
    }

    protected function getRecipients(CalendarEventsModel $event): array
    {
        $arrRecipients = [];

        $organizer = $this->getOrganizers($event);

        if (null !== $organizer) {
            $i = 0;

            while ($organizer->next()) {
                if ($organizer->enableRapportNotification) {
                    ++$i;

                    // We let the user enter the recipients manually,
                    // if the event belongs to more than one organizer,
                    // because we don't want an event to be billed multiple times
                    if ($i > 1 && !empty($organizer->eventRapportNotificationRecipients) && !empty($arrRecipients)) {
                        return [];
                    }

                    $arrRecipients = array_filter(array_unique(array_merge($arrRecipients, explode(',', $organizer->eventRapportNotificationRecipients))));
                }
            }
        }

        return array_filter(array_unique($arrRecipients));
    }

    protected function createAndValidateForm(Request $request, CalendarEventsModel $event, UserModel $biller): Form
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

        // !important otherwise the docx files will be converted
        // and Contao will try to send the email
        $form->setIsSubmitted(false);

        // Preset input fields "subject" and "text" with a default text
        if ('send_tour_rapport_notification_form' !== $request->request->get('FORM_SUBMIT')) {
            if (empty($form->getWidget('recipients')->value) && empty($form->getWidget('text')->value) && empty($form->getWidget('subject')->value)) {
                $form->getWidget('recipients')->value = implode(',', $this->getRecipients($event));

                $subject = $this->twig->render(
                    '@MarkocupicSacEventTool/CalendarEventsInstructorInvoice/send_tour_rapport_notification_subject_template.twig',
                    [
                        'event' => $event,
                        'instructor' => $biller,
                    ]
                );

                $subject = $this->stringUtil->revertInputEncoding($subject);

                $form->getWidget('subject')->value = $subject;

                $text = $this->twig->render(
                    '@MarkocupicSacEventTool/CalendarEventsInstructorInvoice/send_tour_rapport_notification_text_template.twig',
                    [
                        'event' => $event,
                        'instructor' => $biller,
                        'event_url' => $this->events->generateEventUrl($event, true),
                    ]
                );

                $text = $this->stringUtil->revertInputEncoding($text);

                $form->getWidget('text')->value = $text;
            }
        }

        $this->saveFormInputsToSession($form);
        $this->setFormInputsFromSession($form);

        return $form;
    }

    protected function sendEmail(Request $request, Form $form, UserModel $biller, \SplFileObject $rapportFile, \SplFileObject $invoiceFile): bool
    {
        $objEmail = new Email();
        $objEmail->fromName = html_entity_decode($this->sacevtEventAdminName);
        $objEmail->from = $this->sacevtEventAdminEmail;
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

    protected function saveFormInputsToSession(Form $form): void
    {
        $bag = $this->getSessionBag();
        $bag['recipients'] = $form->getWidget('recipients')->value;
        $bag['subject'] = $form->getWidget('subject')->value;
        $bag['text'] = $form->getWidget('text')->value;

        $this->setSessionBag($bag);
    }

    protected function setFormInputsFromSession(Form $form): void
    {
        $bag = $this->getSessionBag();

        $form->getWidget('recipients')->value = $bag['recipients'];
        $form->getWidget('subject')->value = $bag['subject'];
        $form->getWidget('text')->value = $bag['text'];
    }

    protected function setRefererIfNotSet(string $strUri): void
    {
        $arrBag = $this->getSessionBag();

        if (!empty($arrBag['referer'])) {
            return;
        }

        $arrBag['referer'] = $strUri;

        $this->setSessionBag($arrBag);
    }

    protected function getSessionBag(): array
    {
        $session = $this->requestStack->getCurrentRequest()->getSession();
        $bagAll = $session->get(self::SESSION_BAG_KEY, []);

        if (!isset($bagAll[$this->sid])) {
            $bagAll[$this->sid] = [
                'referer' => System::getReferer(),
                'attachments' => [],
                'recipients' => [],
                'subject' => '',
                'text' => '',
            ];

            $session->set(self::SESSION_BAG_KEY, $bagAll);
        }

        return $bagAll[$this->sid];
    }

    protected function setSessionBag(array $arrBag): void
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

    protected function clearSessionBag(): void
    {
        $session = $this->requestStack->getCurrentRequest()->getSession();

        $bagAll = $session->get(self::SESSION_BAG_KEY);
        unset($bagAll[$this->sid]);
        $bagAll = array_values($bagAll);

        $session->set(self::SESSION_BAG_KEY, $bagAll);
    }

    protected function notifyAdminOnError(\Exception $e, int $rapport_id): void
    {
        $adminName = $this->config->get('adminName') ?? 'Administrator';
        $adminEmail = $this->config->get('adminEmail');

        if ($adminEmail && $adminName) {
            $security = System::getContainer()->get('security.helper');

            $email = new Email();
            $email->subject = 'Could not send tour report notification due to an error.';
            $email->text = implode("\r\n\r\n", [
                'Backend User: '.$security->getUser()->username,
                'Rapport ID: '.(string) $rapport_id,
                'Error message: '.$e->getMessage(),
                'Instance of: '.\get_class($e),
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
