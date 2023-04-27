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
use Codefog\HasteBundle\UrlParser;
use Contao\BackendTemplate;
use Contao\BackendUser;
use Contao\CalendarEventsModel;
use Contao\Config;
use Contao\Controller;
use Contao\CoreBundle\Framework\Adapter;
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\Email;
use Contao\Events;
use Contao\MemberModel;
use Contao\Message;
use Contao\Validator;
use Markocupic\SacEventToolBundle\CalendarEventsHelper;
use Markocupic\SacEventToolBundle\Config\EventSubscriptionLevel;
use Markocupic\SacEventToolBundle\Model\CalendarEventsMemberModel;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Security\Core\Security;
use Symfony\Contracts\Translation\TranslatorInterface;

class NotifyEventParticipantController
{
    public const ACCEPT_WITH_EMAIL_ACTION = 'accept_with_email';
    public const ADD_TO_WAITING_LIST_WITH_EMAIL_ACTION = 'add_to_waitinglist_with_email';
    public const REFUSE_WITH_EMAIL_ACTION = 'refuse_with_email';
    public const CANCEL_WITH_EMAIL_ACTION = 'cancel_with_email';

    public const ACTIONS = [
        self::ACCEPT_WITH_EMAIL_ACTION,
        self::ADD_TO_WAITING_LIST_WITH_EMAIL_ACTION,
        self::REFUSE_WITH_EMAIL_ACTION,
        self::CANCEL_WITH_EMAIL_ACTION,
    ];

    private CalendarEventsMemberModel|null $registration;
    private CalendarEventsModel|null $event;
    private BackendUser|null $user;
    private string|null $action;
    private array|null $configuration;

    // Adapters
    private Adapter $config;
    private Adapter $message;
    private Adapter $controller;
    private Adapter $validator;
    private Adapter $member;
    private Adapter $events;
    private Adapter $calendarEventsHelper;
    private Adapter $calendarEventsMember;
    private Adapter $calendarEvents;

    public function __construct(
        private readonly ContaoFramework $framework,
        private readonly RequestStack $requestStack,
        private readonly Security $security,
        private readonly UrlParser $urlParser,
        private readonly TranslatorInterface $translator,
        private readonly string $sacevtEventAdminEmail,
        private readonly string $sacevtEventAdminName,
    ) {
        $this->config = $this->framework->getAdapter(Config::class);
        $this->message = $this->framework->getAdapter(Message::class);
        $this->controller = $this->framework->getAdapter(Controller::class);
        $this->validator = $this->framework->getAdapter(Validator::class);
        $this->member = $this->framework->getAdapter(MemberModel::class);
        $this->events = $this->framework->getAdapter(Events::class);
        $this->calendarEventsHelper = $this->framework->getAdapter(CalendarEventsHelper::class);
        $this->calendarEventsMember = $this->framework->getAdapter(CalendarEventsMemberModel::class);
        $this->calendarEvents = $this->framework->getAdapter(CalendarEventsModel::class);
    }

    public function generate(): string
    {
        $this->initialize();

        $template = new BackendTemplate('be_calendar_events_registration_email');
        $template->headline = $this->configuration['headline'];
        $template->form = $this->createAndValidateForm()->generate();
        $template->back = $this->getBackUrl();

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
        $errorUri = $this->urlParser->removeQueryString(['key', 'act']);
        $errorUri = $this->urlParser->addQueryString('act=edit', $errorUri);

        $request = $this->requestStack->getCurrentRequest();

        $id = $request->query->get('id');

        $this->registration = $this->calendarEventsMember->findByPk($id);

        if (null === $this->registration) {
            $this->message->addInfo('Es wurde keine gültige Event-Registrierung gefunden.');
            $this->controller->redirect($errorUri);
        }

        $this->event = $this->calendarEvents->findByPk($this->registration->eventId);

        if (null === $this->event) {
            $this->message->addInfo('Es wurde kein zur Registrierung gehörender Event gefunden.');
            $this->controller->redirect($errorUri);
        }

        $this->user = $this->security->getUser();

        if (!$this->user instanceof BackendUser) {
            throw new \Exception('Access denied! User has to be a logged in backend user.');
        }

        $this->action = $request->query->get('action', null);

        if (empty($this->action) || !\in_array($this->action, self::ACTIONS, true) || null === ($this->configuration = $this->getActionConfig($this->action))) {
            $this->message->addInfo(sprintf('Ungültiger Query-Parameter "action" => "%s".', $this->action));
            $this->controller->redirect($errorUri);
        }
    }

    private function getActionConfig(string $action): array|null
    {
        $configs = [
            self::ACCEPT_WITH_EMAIL_ACTION => [
                'formId' => strtolower(self::ACCEPT_WITH_EMAIL_ACTION).'_form',
                'headline' => 'Anmeldeanfrage bestätigen',
                'stateOfSubscription' => EventSubscriptionLevel::SUBSCRIPTION_ACCEPTED,
                'sessionInfoText' => 'Diese Anmeldeanfrage wurde erfolgreich bestätigt und die Person per E-Mail darüber in Kenntnis gesetzt.',
                'emailTemplate' => 'be_email_templ_accept_registration',
                'emailSubject' => 'Zusage für %s "%s"',
            ],
            self::ADD_TO_WAITING_LIST_WITH_EMAIL_ACTION => [
                'formId' => strtolower(self::ADD_TO_WAITING_LIST_WITH_EMAIL_ACTION).'_form',
                'headline' => 'Anmeldestatus auf "Warteliste" ändern',
                'stateOfSubscription' => EventSubscriptionLevel::SUBSCRIPTION_ON_WAITINGLIST,
                'sessionInfoText' => 'Der Status dieser Registrierung wurde erfolgreich auf "Warteliste" gesetzt und die Person darüber per E-Mail in Kenntnis gesetzt.',
                'emailTemplate' => 'be_email_templ_add_to_waitinglist',
                'emailSubject' => 'Auf Warteliste für %s "%s"',
            ],
            self::REFUSE_WITH_EMAIL_ACTION => [
                'formId' => strtolower(self::REFUSE_WITH_EMAIL_ACTION).'_form',
                'headline' => 'Anmeldeanfrage ablehnen',
                'stateOfSubscription' => EventSubscriptionLevel::SUBSCRIPTION_REFUSED,
                'sessionInfoText' => 'Diese Anmeldeanfrage wurde abgelehnt und die Person darüber per E-Mail in Kenntnis gesetzt.',
                'emailTemplate' => 'be_email_templ_refuse_registration',
                'emailSubject' => 'Anmeldeanfrage für %s "%s" abgelehnt',
            ],
            self::CANCEL_WITH_EMAIL_ACTION => [
                'formId' => strtolower(self::CANCEL_WITH_EMAIL_ACTION).'_form',
                'headline' => 'Anmeldeanfrage stornieren',
                'stateOfSubscription' => EventSubscriptionLevel::USER_HAS_UNSUBSCRIBED,
                'sessionInfoText' => 'Diese Anmeldeanfrage wurde storniert und die Person mit einer E-Mail darüber in Kenntnis gesetzt.',
                'emailTemplate' => 'be_email_templ_cancel_registration',
                'emailSubject' => 'Anmeldeanfrage für %s "%s" storniert',
            ],
        ];

        return $configs[$action] ?? null;
    }

    private function createAndValidateForm(): Form
    {
        // Generate form fields
        $form = new Form(
            $this->configuration['formId'],
            'POST',
        );

        $form->addContaoHiddenFields();

        $form->addFormField('subject', [
            'label' => 'Betreff',
            'inputType' => 'text',
            'eval' => ['mandatory' => true],
        ]);

        $form->addFormField('text', [
            'label' => 'Nachricht',
            'inputType' => 'textarea',
            'eval' => ['rows' => 20, 'cols' => 80, 'mandatory' => true],
        ]);

        $form->addFormField('submit', [
            'label' => 'Nachricht absenden',
            'inputType' => 'submit',
        ]);

        $request = $this->requestStack->getCurrentRequest();

        // Prefill email form from template
        if (!$request->isMethod('post')) {
            $arrTokens = $this->getTokenArray();

            if ('accept_with_email' === $this->action && $this->event->customizeEventRegistrationConfirmationEmailText && !empty($this->event->customEventRegistrationConfirmationEmailText)) {
                // Only for accept_with_email!!!
                // Replace tags for custom notification set in the events settings (tags can be used case-insensitive!)
                $emailBodyText = $this->event->customEventRegistrationConfirmationEmailText;

                foreach ($arrTokens as $k => $v) {
                    $strPattern = '/##'.$k.'##/i';
                    $emailBodyText = preg_replace($strPattern, $v, $emailBodyText);
                }
                $emailBodyText = strip_tags($emailBodyText);
            } else {
                // Get email body text from template
                $template = new BackendTemplate($this->configuration['emailTemplate']);

                foreach ($arrTokens as $k => $v) {
                    $template->{$k} = $v;
                }

                $emailBodyText = strip_tags($template->parse());
            }

            // Get event type
            $eventType = $this->translator->trans('MSC.'.$this->event->eventType, [], 'contao_default');

            // Add value to fields
            $form->getWidget('subject')->value = sprintf($this->configuration['emailSubject'], $eventType, $this->event->title);
            $form->getWidget('text')->value = $emailBodyText;
        }

        if ($request->request->get('FORM_SUBMIT') === $this->configuration['formId'] && $form->validate()) {
            if ($this->notify($form)) {
                $uri = $this->getBackUrl();
                $this->controller->redirect($uri);
            }
        }

        return $form;
    }

    private function notify(Form $form): bool
    {
        $hasError = false;

        if (!$this->validator->isEmail($this->sacevtEventAdminEmail)) {
            throw new \Exception('Please set a valid email address for the service parameter "sacevt.event_admin_email."');
        }

        $email = new Email();
        $email->fromName = html_entity_decode(html_entity_decode($this->sacevtEventAdminName));
        $email->from = $this->sacevtEventAdminEmail;
        $email->replyTo($this->user->email);
        $email->subject = html_entity_decode((string) $form->getWidget('subject')->value);
        $email->text = html_entity_decode(strip_tags((string) $form->getWidget('text')->value));

        // Check if another event has already been booked at the same time.
        $objMember = $this->member->findOneBySacMemberId($this->registration->sacMemberId);

        if (
            self::ACCEPT_WITH_EMAIL_ACTION === $this->action &&
            null !== $objMember &&
            !$this->registration->allowMultiSignUp &&
            $this->calendarEventsHelper->areBookingDatesOccupied($this->event, $objMember)
        ) {
            $this->message->addError(
                'Es ist ein Fehler aufgetreten. '.
                'Der Teilnehmer kann nicht angemeldet werden, weil er zur selben Zeit bereits an einem anderen Event bestätigt wurde. '.
                'Wenn Sie die Anmeldeanfrage trotzdem bestätigen möchten, so wählen Sie die Option "Mehrfachbuchung zulassen" aus.'
            );
        } elseif (
            self::ACCEPT_WITH_EMAIL_ACTION === $this->action
            && !$this->calendarEventsMember->canAcceptSubscription($this->registration, $this->event)
        ) {
            $this->message->addError(
                'Es ist ein Fehler aufgetreten. '.
                'Da die maximale Teilnehmerzahl bereits erreicht ist, '.
                'kann für den Teilnehmer die Teilnahme am Event nicht bestätigt werden.'
            );
        } elseif ($this->validator->isEmail($this->registration->email)) {
            // Send email notification
            if ($email->sendTo($this->registration->email)) {
                $this->registration->stateOfSubscription = $this->configuration['stateOfSubscription'];
                $this->registration->save();
                $this->message->addInfo($this->configuration['sessionInfoText']);

                return true;
            }

            $hasError = true;
        } else {
            $hasError = true;
        }

        if ($hasError) {
            $this->message->addInfo(
                'Es ist ein Fehler aufgetreten. '.
                'Überprüfen Sie die E-Mail-Adressen. Dem Teilnehmer konnte keine E-Mail versandt werden.'
            );

            return false;
        }

        return true;
    }

    private function getTokenArray(): array
    {
        // Get event dates as a comma separated string
        $eventDates = $this->calendarEventsHelper->getEventTimestamps($this->event);
        $df = $this->config->get('dateFormat');
        $strDates = implode(
            ', ',
            array_map(
                static fn ($tstamp) => date($df, (int) $tstamp),
                $eventDates
            )
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

    private function getBackUrl(): string
    {
        return $this->urlParser->removeQueryString(['key', 'action']);
    }
}
