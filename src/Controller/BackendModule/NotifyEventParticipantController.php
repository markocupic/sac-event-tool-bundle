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

class NotifyEventParticipantController
{
    private const ACTIONS = [
        'accept_with_email',
        'add_to_waitlist',
        'refuse_with_email',
        'cancel_with_email',
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

        if (empty($this->action) || !\in_array($this->action, self::ACTIONS, true) || null === ($this->configuration = $this->getConfiguration($this->action))) {
            $this->message->addInfo(sprintf('Ungültiger Query-Parameter "action" => "%s".', $this->action));
            $this->controller->redirect($errorUri);
        }
    }

    private function getConfiguration(string $action): array|null
    {
        $configs = [
            'accept_with_email' => [
                'formId' => 'subscription-accepted-form',
                'headline' => 'Zusage zum Event',
                'stateOfSubscription' => EventSubscriptionLevel::SUBSCRIPTION_ACCEPTED,
                'sessionInfoText' => 'Dem Benutzer wurde mit einer E-Mail eine Zusage für diesen Event versandt.',
                'emailTemplate' => 'be_email_templ_accept_registration',
                'emailSubject' => 'Zusage für %s',
            ],
            'add_to_waitlist' => [
                'formId' => 'subscription-waitlisted-form',
                'headline' => 'Auf Warteliste setzen',
                'stateOfSubscription' => EventSubscriptionLevel::SUBSCRIPTION_WAITLISTED,
                'sessionInfoText' => 'Dem Benutzer wurde auf die Warteliste gesetzt und mit einer E-Mail darüber informiert.',
                'emailTemplate' => 'be_email_templ_added_to_waitlist',
                'emailSubject' => 'Auf Warteliste für %s',
            ],
            'refuse_with_email' => [
                'formId' => 'subscription-refused-form',
                'headline' => 'Absage kommunizieren',
                'stateOfSubscription' => EventSubscriptionLevel::SUBSCRIPTION_REJECTED,
                'sessionInfoText' => 'Dem Benutzer wurde mit einer E-Mail eine Absage versandt.',
                'emailTemplate' => 'be_email_templ_refuse_registration',
                'emailSubject' => 'Absage für %s',
            ],
            'cancel_with_email' => [
                'formId' => 'cancel-registration-form',
                'headline' => 'Stornierung kommunizieren',
                'stateOfSubscription' => EventSubscriptionLevel::USER_HAS_UNSUBSCRIBED,
                'sessionInfoText' => 'Die Registrierung wurde storniert und die Person mit einer E-Mail darüber in Kenntnis gesetzt.',
                'emailTemplate' => 'be_email_templ_cancel_registration',
                'emailSubject' => 'Stornierung %s',
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

        // Now let's add form fields:
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
                // Build email text from template
                $template = new BackendTemplate($this->configuration['emailTemplate']);

                foreach ($arrTokens as $k => $v) {
                    $template->{$k} = $v;
                }
                $emailBodyText = strip_tags($template->parse());
            }

            // Get event type
            $eventType = \strlen((string) $GLOBALS['TL_LANG']['MSC'][$this->event->eventType]) ? $GLOBALS['TL_LANG']['MSC'][$this->event->eventType].': ' : 'Event: ';

            // Add value to fields
            $form->getWidget('subject')->value = sprintf($this->configuration['emailSubject'], $eventType.$this->event->title);
            $form->getWidget('text')->value = $emailBodyText;
        }

        if ($form->validate()) {
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
            throw new \Exception('Please set a valid email address in parameter sacevt.event_admin_email.');
        }

        $email = new Email();
        $email->fromName = html_entity_decode(html_entity_decode($this->sacevtEventAdminName));
        $email->from = $this->sacevtEventAdminEmail;
        $email->replyTo($this->user->email);
        $email->subject = html_entity_decode((string) $form->getWidget('subject')->value);
        $email->text = html_entity_decode(strip_tags((string) $form->getWidget('text')->value));

        // Check if member has already booked at the same time
        $objMember = $this->member->findOneBySacMemberId($this->registration->sacMemberId);

        if ('accept_with_email' === $this->action && null !== $objMember && !$this->registration->allowMultiSignUp && $this->calendarEventsHelper->areBookingDatesOccupied($this->event, $objMember)) {
            $this->message->addError('Es ist ein Fehler aufgetreten. Der Teilnehmer kann nicht angemeldet werden, weil er zu dieser Zeit bereits an einem anderen Event bestätigt wurde. Wenn Sie das trotzdem erlauben möchten, dann setzen Sie das Flag "Mehrfachbuchung zulassen".');
        } elseif ('accept_with_email' === $this->action && !$this->calendarEventsMember->canAcceptSubscription($this->registration, $this->event)) {
            $this->message->addError('Es ist ein Fehler aufgetreten. Da die maximale Teilnehmerzahl bereits erreicht ist, kann für den Teilnehmer die Teilnahme am Event nicht bestätigt werden.');
        } // Send email
        elseif ($this->validator->isEmail($this->registration->email)) {
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
            $this->message->addInfo('Es ist ein Fehler aufgetreten. Überprüfen Sie die E-Mail-Adressen. Dem Teilnehmer konnte keine E-Mail versandt werden.');

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
