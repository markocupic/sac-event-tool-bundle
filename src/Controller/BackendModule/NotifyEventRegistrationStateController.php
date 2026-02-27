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

use Codefog\HasteBundle\Form\Form;
use Codefog\HasteBundle\UrlParser;
use Contao\BackendTemplate;
use Contao\BackendUser;
use Contao\CalendarEventsModel;
use Contao\Config;
use Contao\Controller;
use Contao\CoreBundle\Framework\Adapter;
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\Events;
use Contao\MemberModel;
use Contao\Message;
use Contao\StringUtil;
use Contao\Validator;
use Contao\Versions;
use Markocupic\SacEventToolBundle\Config\EventSubscriptionState;
use Markocupic\SacEventToolBundle\Model\CalendarEventsMemberModel;
use Markocupic\SacEventToolBundle\Util\CalendarEventsUtil;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\UriSigner;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Symfony\Contracts\Translation\TranslatorInterface;
use Twig\Environment;

class NotifyEventRegistrationStateController
{
    // query key assigned to the controller
    public const string PARAM_KEY = 'notify_event_registration_state';

    private CalendarEventsMemberModel|null $registration;

    private CalendarEventsModel|null $event;

    private BackendUser|null $user;

    private string|null $action;

    private array|null $configuration;

    // Adapters
    private Adapter $events;

    private Adapter $stringUtil;

    private Adapter $calendarEvents;

    private Adapter $calendarEventsMember;

    private Adapter $config;

    private Adapter $controller;

    private Adapter $member;

    private Adapter $message;

    private Adapter $validator;

    public function __construct(
        private readonly CalendarEventsUtil $calendarEventsUtil,
        private readonly ContaoFramework $framework,
        private readonly Environment $twig,
        private readonly MailerInterface $mailer,
        #[Autowire(param: 'sacevt.mailer_transports.event_admin')]
        private readonly array $mailerTransport,
        private readonly RequestStack $requestStack,
        private readonly Security $security,
        private readonly TranslatorInterface $translator,
        private readonly UriSigner $uriSigner,
        private readonly UrlParser $urlParser,
        private readonly string $sacevtEventRegistrationConfigEmailAcceptTemplPath,
        private readonly string $sacevtEventRegistrationConfigEmailCancelTemplPath,
        private readonly string $sacevtEventRegistrationConfigEmailRefuseTemplPath,
        private readonly string $sacevtEventRegistrationConfigEmailWaitinglistTemplPath,
    ) {
        $this->stringUtil = $this->framework->getAdapter(StringUtil::class);
        $this->calendarEvents = $this->framework->getAdapter(CalendarEventsModel::class);
        $this->calendarEventsMember = $this->framework->getAdapter(CalendarEventsMemberModel::class);
        $this->config = $this->framework->getAdapter(Config::class);
        $this->controller = $this->framework->getAdapter(Controller::class);
        $this->events = $this->framework->getAdapter(Events::class);
        $this->member = $this->framework->getAdapter(MemberModel::class);
        $this->message = $this->framework->getAdapter(Message::class);
        $this->validator = $this->framework->getAdapter(Validator::class);
    }

    public function generate(): string
    {
        $this->initialize();

        $template = new BackendTemplate('be_notify_event_registration_state');
        $template->headline = $this->configuration['headline'];
        $template->form = $this->createAndValidateForm()->generate();
        $template->back = $this->getBackUri();

        return $template->parse();
    }

    /**
     * Has to be called first on every request.
     *
     * Check query params and set up class properties:
     * - registration model
     * - event model
     * - user
     * - action
     */
    private function initialize(): void
    {
        $request = $this->requestStack->getCurrentRequest();

        $id = $request->query->get('id');

        if (!$this->uriSigner->checkRequest($request)) {
            $this->message->addInfo('Zugriff verweigert.');
            $this->controller->redirect($this->getErrorUri());
        }

        $this->registration = $this->calendarEventsMember->findById($id);

        if (null === $this->registration) {
            $this->message->addInfo('Es wurde keine gültige Event-Registrierung gefunden.');
            $this->controller->redirect($this->getErrorUri());
        }

        $this->event = $this->calendarEvents->findById($this->registration->eventId);

        if (null === $this->event) {
            $this->message->addInfo('Es wurde kein zur Registrierung gehörender Event gefunden.');
            $this->controller->redirect($this->getErrorUri());
        }

        $this->user = $this->security->getUser();

        if (!$this->user instanceof BackendUser) {
            throw new \Exception('Access denied! User has to be a logged in as a Contao backend user.');
        }

        $this->action = $request->query->get('action', null);

        if (empty($this->action) || !\in_array($this->action, EventSubscriptionState::ALL, true) || null === ($this->configuration = $this->getActionConfig($this->action))) {
            $this->message->addInfo(\sprintf('Ungültiger Query-Parameter "action" => "%s".', $this->action));
            $this->controller->redirect($this->getErrorUri());
        }
    }

    private function getActionConfig(string $action): array|null
    {
        $configs = [
            EventSubscriptionState::SUBSCRIPTION_ACCEPTED => [
                'formId' => strtolower(EventSubscriptionState::SUBSCRIPTION_ACCEPTED).'_form',
                'headline' => 'Anmeldeanfrage bestätigen',
                'stateOfSubscription' => EventSubscriptionState::SUBSCRIPTION_ACCEPTED,
                'backendMessage' => 'Die Anmeldeanfrage wurde erfolgreich bestätigt und die Person wurde darüber per E-Mail in Kenntnis gesetzt.',
                'templatePath' => $this->sacevtEventRegistrationConfigEmailAcceptTemplPath,
            ],
            EventSubscriptionState::USER_HAS_UNSUBSCRIBED => [
                'formId' => strtolower(EventSubscriptionState::USER_HAS_UNSUBSCRIBED).'_form',
                'headline' => 'Anmeldeanfrage stornieren',
                'stateOfSubscription' => EventSubscriptionState::USER_HAS_UNSUBSCRIBED,
                'backendMessage' => 'Die Anmeldeanfrage wurde storniert und die Person wurde darüber per E-Mail in Kenntnis gesetzt.',
                'templatePath' => $this->sacevtEventRegistrationConfigEmailCancelTemplPath,
            ],
            EventSubscriptionState::SUBSCRIPTION_REFUSED => [
                'formId' => strtolower(EventSubscriptionState::SUBSCRIPTION_REFUSED).'_form',
                'headline' => 'Anmeldeanfrage ablehnen',
                'stateOfSubscription' => EventSubscriptionState::SUBSCRIPTION_REFUSED,
                'backendMessage' => 'Die Anmeldeanfrage wurde abgelehnt und die Person wurde darüber per E-Mail in Kenntnis gesetzt.',
                'templatePath' => $this->sacevtEventRegistrationConfigEmailRefuseTemplPath,
            ],
            EventSubscriptionState::SUBSCRIPTION_ON_WAITING_LIST => [
                'formId' => strtolower(EventSubscriptionState::SUBSCRIPTION_ON_WAITING_LIST).'_form',
                'headline' => 'Anmeldestatus auf "Warteliste" ändern',
                'stateOfSubscription' => EventSubscriptionState::SUBSCRIPTION_ON_WAITING_LIST,
                'backendMessage' => 'Der Status dieser Registrierung wurde erfolgreich auf "Warteliste" gesetzt und die Person wurde darüber per E-Mail in Kenntnis gesetzt.',
                'templatePath' => $this->sacevtEventRegistrationConfigEmailWaitinglistTemplPath,
            ],
        ];

        return $configs[$action] ?? null;
    }

    private function createAndValidateForm(): Form
    {
        $form = new Form(
            $this->configuration['formId'],
            'POST',
        );

        $form->addContaoHiddenFields();

        $form->addFormField('subject', [
            'label' => 'Betreff',
            'inputType' => 'text',
            'eval' => ['mandatory' => true, 'useRawRequestData' => true],
        ]);

        $form->addFormField('text', [
            'label' => 'Nachricht',
            'inputType' => 'textarea',
            'eval' => ['rows' => 20, 'cols' => 80, 'mandatory' => true, 'useRawRequestData' => true],
        ]);

        $form->addFormField('submit', [
            'label' => 'Nachricht absenden',
            'inputType' => 'submit',
        ]);

        $request = $this->requestStack->getCurrentRequest();

        // Prefill the email form with text from the template
        if (!$request->isMethod('post')) {
            $textTokens = $this->getTokenArray();

            if (EventSubscriptionState::SUBSCRIPTION_ACCEPTED === $this->action && $this->event->customizeEventRegistrationConfirmationEmailText && !empty($this->event->customEventRegistrationConfirmationEmailText)) {
                // Only for accept_with_email!!! Replace tags for custom notification set in the
                // events settings (tags can be used case-insensitive!)
                $emailText = $this->event->customEventRegistrationConfirmationEmailText;

                foreach ($textTokens as $k => $v) {
                    $strPattern = '/##'.$k.'##/i';
                    $emailText = preg_replace($strPattern, $v, $emailText);
                }
            } else {
                // Render the email body text from a twig template
                $textTokens['renderEmailText'] = true;
                $emailText = $this->twig->createTemplate(file_get_contents($this->configuration['templatePath']))->render($textTokens);
            }

            // Get event type
            $eventType = $this->translator->trans('MSC.'.$this->event->eventType, [], 'contao_default');

            // Add value to fields
            $subjectTokens = [
                'eventType' => $eventType,
                'eventTitle' => $this->event->title,
                'renderEmailSubject' => true,
            ];

            $emailText = $this->stringUtil->revertInputEncoding($emailText);
            $emailSubject = $this->twig->createTemplate(file_get_contents($this->configuration['templatePath']))->render($subjectTokens);
            $emailSubject = $this->stringUtil->revertInputEncoding($emailSubject);

            $form->getWidget('subject')->value = $emailSubject;
            $form->getWidget('text')->value = $emailText;
        }

        if ($request->request->get('FORM_SUBMIT') === $this->configuration['formId'] && $form->validate()) {
            if ($this->notify($form)) {
                $uri = $this->getBackUri();
                $this->controller->redirect($uri);
            }
        }

        return $form;
    }

    private function notify(Form $form): bool
    {
        $hasError = false;

        if (!$this->validator->isEmail($this->mailerTransport['sender_email'])) {
            throw new \Exception('Please set a valid email address for the sender.');
        }

        $senderAddress = new Address($this->mailerTransport['sender_email'], $this->stringUtil->revertInputEncoding($this->mailerTransport['sender_name']));
        $replyToAddress = new Address($this->user->email, $this->stringUtil->revertInputEncoding($this->user->name));

        $subject = $form->getWidget('subject')->value;
        $text = $form->getWidget('text')->value;

        $email = new Email();
        $email->getHeaders()->addTextHeader('X-Transport', $this->mailerTransport['transport_name']);
        $email->from($senderAddress);
        $email->sender($senderAddress);
        $email->returnPath($senderAddress);
        $email->replyTo($replyToAddress);

        $email->subject($subject);
        $email->text($text);
        $email->html('<p>'.nl2br(htmlspecialchars($text)).'</p>');

        // Add some headers to prevent the email from being marked as spam
        $email->getHeaders()->addTextHeader(
            'List-Unsubscribe',
            \sprintf('<mailto:%s?subject=unsubscribe>', $senderAddress->getAddress()),
        );

        $email->getHeaders()->addTextHeader(
            'List-Unsubscribe-Post',
            'List-Unsubscribe=One-Click',
        );

        // Check if the event participant has already been booked on another event at the
        // same time.
        $objMember = $this->member->findOneBySacMemberId($this->registration->sacMemberId);

        if (
            EventSubscriptionState::SUBSCRIPTION_ACCEPTED === $this->action
            && null !== $objMember
            && !$this->registration->allowMultiSignUp
            && $this->calendarEventsUtil->areBookingDatesOccupied($this->event, $objMember)
        ) {
            $this->message->addError(
                'Es ist ein Fehler aufgetreten. '.
                'Der Teilnehmer kann nicht angemeldet werden, weil er zur selben Zeit bereits an einem anderen Event bestätigt wurde. '.
                'Wenn Sie die Anmeldeanfrage trotzdem bestätigen möchten, so wählen Sie die Option "Mehrfachbuchung zulassen" aus.',
            );
        } elseif (
            EventSubscriptionState::SUBSCRIPTION_ACCEPTED === $this->action
            && !$this->calendarEventsMember->canAcceptSubscription($this->registration, $this->event)
        ) {
            $this->message->addError(
                'Es ist ein Fehler aufgetreten. '.
                'Da die maximale Teilnehmerzahl bereits erreicht ist, '.
                'kann für den Teilnehmer die Teilnahme am Event nicht bestätigt werden.',
            );
        } elseif ($this->validator->isEmail($this->registration->email)) {
            try {
                // Send email notification
                $email = $email->to(new Address($this->registration->email, $this->registration->firstname.' '.$this->registration->lastname));
                $this->mailer->send($email);

                $this->registration->stateOfSubscription = $this->configuration['stateOfSubscription'];

                if ($this->registration->isModified()) {
                    $this->registration->tstamp = time();
                    $this->registration->save();

                    // Create a new version
                    $objVersions = new Versions($this->registration->getTable(), $this->registration->id);
                    $objVersions->initialize();
                    $objVersions->create();
                }

                $this->message->addInfo($this->configuration['backendMessage']);

                return true;
            } catch (\Exception $e) {
                $hasError = true;
            }
        } else {
            $hasError = true;
        }

        if ($hasError) {
            $this->message->addInfo(
                'Es ist ein Fehler aufgetreten. '.
                'Überprüfen Sie die E-Mail-Adressen. Dem Teilnehmer konnte keine E-Mail versandt werden.',
            );

            return false;
        }

        return true;
    }

    private function getTokenArray(): array
    {
        // Get event dates as a comma-separated string
        $eventDates = $this->calendarEventsUtil->getEventTimestamps($this->event);
        $df = $this->config->get('dateFormat');
        $strDates = implode(
            ', ',
            array_map(
                static fn ($tstamp) => date($df, (int) $tstamp),
                $eventDates,
            ),
        );

        return [
            'participantFirstname' => $this->registration->firstname,
            'participantLastname' => $this->registration->lastname,
            'participant_uuid' => $this->registration->uuid,
            'eventName' => $this->event->title,
            'eventIban' => $this->event->addIban ? $this->event->iban : '',
            'eventIbanBeneficiary' => $this->event->addIban ? $this->event->ibanBeneficiary : '',
            'courseId' => $this->event->courseId,
            'eventType' => $this->event->eventType,
            'eventUrl' => $this->events->generateEventUrl($this->event, true),
            'eventDates' => $strDates,
            'instructorName' => $this->user->name,
            'instructorFirstname' => $this->user->firstname,
            'instructorLastname' => $this->user->lastname,
            'instructorPhone' => $this->user->phone,
            'instructorMobile' => $this->user->mobile,
            'instructorStreet' => $this->user->street,
            'instructorPostal' => $this->user->postal,
            'instructorCity' => $this->user->city,
            'instructorEmail' => $this->user->email,
        ];
    }

    private function getBackUri(): string
    {
        $uri = $this->urlParser->removeQueryString(['act', 'id', 'key', 'action']);

        return $this->urlParser->addQueryString('id='.$this->event->id, $uri);
    }

    private function getErrorUri(): string
    {
        $uri = $this->urlParser->removeQueryString(['key', 'act']);

        return $this->urlParser->addQueryString('act=edit', $uri);
    }
}
