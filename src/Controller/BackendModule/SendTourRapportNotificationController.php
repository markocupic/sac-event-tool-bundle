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

use CloudConvert\Exceptions\HttpClientException;
use Codefog\HasteBundle\Form\Form;
use Contao\BackendTemplate;
use Contao\CalendarEventsModel;
use Contao\Controller;
use Contao\CoreBundle\Csrf\ContaoCsrfTokenManager;
use Contao\CoreBundle\Framework\Adapter;
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\CoreBundle\Util\UrlUtil;
use Contao\Email;
use Contao\Events;
use Contao\Message;
use Contao\Model\Collection;
use Contao\StringUtil;
use Contao\System;
use Contao\UserModel;
use Markocupic\SacEventToolBundle\DocxTemplator\EventRapport2Docx;
use Markocupic\SacEventToolBundle\Model\CalendarEventsInstructorInvoiceModel;
use Markocupic\SacEventToolBundle\Model\EventOrganizerModel;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
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
#[Route('/contao/send_tour_rapport_notification/{rapport_id}/{sid}/{rt}', name: SendTourRapportNotificationController::class, defaults: ['_scope' => 'backend', '_token_check' => true])]
class SendTourRapportNotificationController extends AbstractController
{
    public const SESSION_BAG_KEY = 'sacevt_send_tour_notification';

    private string|null $sid = null;
    private Adapter $stringUtil;
    private Adapter $urlUtil;
    private Adapter $controller;
    private Adapter $message;
    private Adapter $system;
    private Adapter $events;

    public function __construct(
        private readonly ContaoFramework $framework,
        private readonly ContaoCsrfTokenManager $csrfTokenManager,
        private readonly EventRapport2Docx $eventRapport2Docx,
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
    }

    public function __invoke(int $rapport_id, string $sid, string $rt, Request $request): Response
    {
        $this->framework->initialize();
        $this->sid = $sid;

        // Do some checks
        $this->checkIsCsrfTokenValid($rt);
        $this->checkIsSignedUrlValid($request);
        $this->setRefererIfNotSet($this->system->getReferer());

        $invoice = $this->getInvoice($rapport_id);
        $event = $this->getEvent($rapport_id);
        $biller = $this->getBiller($rapport_id);

        $form = $this->createAndValidateForm($request, $event, $biller);

        if (!$form->isSubmitted()) {
            // Display the email form
            $template = new BackendTemplate('be_send_tour_rapport_notification');
            $template->event = $event;
            $template->back = $this->getBackUri($request);
            $template->form = $form->generate();
            $template->request_token = $rt;

            if ($invoice->countNotifications) {
                // Protect the user from submitting the form multiple times.
                $template->info = $this->translator->trans('MSC.evt_strn_multiFormSubmitWarning', [$invoice->countNotifications, date('d.m.Y H:i', (int) $invoice->notificationSentOn)], 'contao_default');
            }

            return new Response($template->parse());
        }

        // Form inputs have passed validation:
        // I. Generate tour report file and convert from docx to pdf using the Cloudconvert API.
        // II. Generate tour invoice file and convert from docx to pdf using the Cloudconvert API.
        // III. Send notification via email.
        // IV. Redirect back to the referer page

        // I. Generate tour report file and convert from docx to pdf using the Cloudconvert API.
        try {
            $rapportFile = $this->eventRapport2Docx
                ->generateDocument(
                    'rapport',
                    $invoice,
                    EventRapport2Docx::OUTPUT_TYPE_PDF,
                    $this->sacevtEventTemplateTourRapport,
                    $this->sacevtEventTourRapportFileNamePattern,
                )
                ;
        } catch (HttpClientException $e) {
            $pdfConversionError = $this->translator->trans('ERR.evt_strn_cloudconvConversionCreditUsedUp', ['Tourrapport'], 'contao_default');
        } catch (\Exception $e) {
            $pdfConversionError = $this->translator->trans('ERR.evt_strn_cloudconvUnexpectedError', ['Tourrapport'], 'contao_default');
        }

        if (!empty($pdfConversionError)) {
            $this->message->addError($pdfConversionError);

            // IV. Redirect back to the referer page
            return $this->redirectBackToRefererPage($request);
        }

        // II. Generate tour invoice file and convert from docx to pdf using the Cloudconvert API.
        try {
            $invoiceFile = $this->eventRapport2Docx
                ->generateDocument(
                    'invoice',
                    $invoice,
                    EventRapport2Docx::OUTPUT_TYPE_PDF,
                    $this->sacevtEventTemplateTourInvoice,
                    $this->sacevtEventTourInvoiceFileNamePattern,
                )
                ;
        } catch (HttpClientException $e) {
            $pdfConversionError = $this->translator->trans('ERR.evt_strn_cloudconvConversionCreditUsedUp', ['Vergütungsformular'], 'contao_default');
        } catch (\Exception $e) {
            $pdfConversionError = $this->translator->trans('ERR.evt_strn_cloudconvUnexpectedError', ['Vergütungsformular'], 'contao_default');
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

            $msg = $this->translator->trans('MSC.evt_strn_successfullySendNotification', [$strRecipients], 'contao_default');
            $this->message->addConfirmation($msg);
        } else {
            // If sending email fails...
            $msg = $this->translator->trans('ERR.evt_strn_sendNotificationFailed', [$strRecipients], 'contao_default');
            $this->message->addError($msg);
        }
        // IV. Redirect back to the referer page
        return $this->redirectBackToRefererPage($request);
    }

    protected function checkIsCsrfTokenValid(string $strToken): void
    {
        $container = $this->system->getContainer();

        if (!$this->csrfTokenManager->isTokenValid(new CsrfToken($container->getParameter('contao.csrf_token_name'), $strToken))) {
            throw new InvalidCsrfTokenException('Invalid CSRF token provided.');
        }
    }

    protected function checkIsSignedUrlValid(Request $request): void
    {
        $uriSigner = $this->system->getContainer()->get('uri_signer');

        if (!$uriSigner->check($request->getRequestUri())) {
            $this->message->addError($this->translator->trans('MSC.evt_strn_linkExpired', [], 'contao_default'));
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
            while ($organizer->next()) {
                if ($organizer->enableRapportNotification) {
                    $arrRecipients = array_merge($arrRecipients, explode(',', $organizer->eventRapportNotificationRecipients));
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
            'eval' => ['rgxp' => 'emails', 'readonly' => false, 'class' => 'clr', 'mandatory' => true],
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

        if ($form->validate()) {
            $recipients = $form->getWidget('recipients')->value;
            $form->getWidget('recipients')->value = str_replace(' ', '', $recipients);

            $form->setIsSubmitted(true);

            return $form;
        }

        // Preset input fields "subject" and "text" with a default text
        if ('email_app_form' !== $request->request->get('FORM_SUBMIT')) {
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
        } catch (\Exception $e) {
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
}
