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
use Contao\Versions;
use Markocupic\SacEventToolBundle\Config\EventSubscriptionState;
use Markocupic\SacEventToolBundle\Model\CalendarEventsMemberModel;
use Markocupic\SacEventToolBundle\Util\CalendarEventsUtil;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Contracts\Translation\TranslatorInterface;
use Twig\Environment;

class NotifyEventRegistrationStateController
{
    // query key assigned to the controller
    public const PARAM_KEY = 'notify_event_registration_state';

    // Actions
    public const ACCEPT_WITH_EMAIL_ACTION = 'accept_with_email';
    public const ADD_TO_WAITING_LIST_WITH_EMAIL_ACTION = 'add_to_waitinglist_with_email';
    public const CANCEL_WITH_EMAIL_ACTION = 'cancel_with_email';
    public const REFUSE_WITH_EMAIL_ACTION = 'refuse_with_email';

    public const ACTIONS = [
        self::ACCEPT_WITH_EMAIL_ACTION,
        self::ADD_TO_WAITING_LIST_WITH_EMAIL_ACTION,
        self::CANCEL_WITH_EMAIL_ACTION,
        self::REFUSE_WITH_EMAIL_ACTION,
    ];

    private CalendarEventsMemberModel|null $registration;
    private CalendarEventsModel|null $event;
    private BackendUser|null $user;
    private string|null $action;
    private array|null $configuration;

    // Adapters
    private Adapter $calendarEvents;
    private Adapter $calendarEventsUtil;
    private Adapter $calendarEventsMember;
    private Adapter $config;
    private Adapter $controller;
    private Adapter $events;
    private Adapter $member;
    private Adapter $message;
    private Adapter $validator;

    public function __construct(
        private readonly Environment $twig,
        private readonly ContaoFramework $framework,
        private readonly RequestStack $requestStack,
        private readonly Security $security,
        private readonly TranslatorInterface $translator,
        private readonly UrlParser $urlParser,
        private readonly string $sacevtEventRegistrationConfigEmailAcceptTemplPath,
        private readonly string $sacevtEventRegistrationConfigEmailCancelTemplPath,
        private readonly string $sacevtEventRegistrationConfigEmailRefuseTemplPath,
        private readonly string $sacevtEventRegistrationConfigEmailWaitinglistTemplPath,
        private readonly string $sacevtEventAdminEmail,
        private readonly string $sacevtEventAdminName,
    ) {
        $this->calendarEvents = $this->framework->getAdapter(CalendarEventsModel::class);
        $this->calendarEventsUtil = $this->framework->getAdapter(CalendarEventsUtil::class);
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

        $this->registration = $this->calendarEventsMember->findByPk($id);

        if (null === $this->registration) {
            $this->message->addInfo('Es wurde keine gültige Event-Registrierung gefunden.');
            $this->controller->redirect($this->getErrorUri());
        }

        $this->event = $this->calendarEvents->findByPk($this->registration->eventId);

        if (null === $this->event) {
            $this->message->addInfo('Es wurde kein zur Registrierung gehörender Event gefunden.');
            $this->controller->redirect($this->getErrorUri());
        }

        $this->user = $this->security->getUser();

        if (!$this->user instanceof BackendUser) {
            throw new \Exception('Access denied! User has to be a logged in Contao backend user.');
        }

        $this->action = $request->query->get('action', null);

        if (empty($this->action) || !\in_array($this->action, self::ACTIONS, true) || null === ($this->configuration = $this->getActionConfig($this->action))) {
            $this->message->addInfo(sprintf('Ungültiger Query-Parameter "action" => "%s".', $this->action));
            $this->controller->redirect($this->getErrorUri());
        }
    }

    private function getActionConfig(string $action): array|null
    {
        $configs = [
            self::ACCEPT_WITH_EMAIL_ACTION => [
                'formId' => strtolower(self::ACCEPT_WITH_EMAIL_ACTION).'_form',
                'headline' => 'Anmeldeanfrage bestätigen',
                'stateOfSubscription' => EventSubscriptionState::SUBSCRIPTION_ACCEPTED,
                'backendMessage' => 'Die Anmeldeanfrage wurde erfolgreich bestätigt und die Person wurde darüber per E-Mail in Kenntnis gesetzt.',
                'templatePath' => $this->sacevtEventRegistrationConfigEmailAcceptTemplPath,
            ],
            self::CANCEL_WITH_EMAIL_ACTION => [
                'formId' => strtolower(self::CANCEL_WITH_EMAIL_ACTION).'_form',
                'headline' => 'Anmeldeanfrage stornieren',
                'stateOfSubscription' => EventSubscriptionState::USER_HAS_UNSUBSCRIBED,
                'backendMessage' => 'Die Anmeldeanfrage wurde storniert und die Person wurde darüber per E-Mail in Kenntnis gesetzt.',
                'templatePath' => $this->sacevtEventRegistrationConfigEmailCancelTemplPath,
            ],
            self::REFUSE_WITH_EMAIL_ACTION => [
                'formId' => strtolower(self::REFUSE_WITH_EMAIL_ACTION).'_form',
                'headline' => 'Anmeldeanfrage ablehnen',
                'stateOfSubscription' => EventSubscriptionState::SUBSCRIPTION_REFUSED,
                'backendMessage' => 'Die Anmeldeanfrage wurde abgelehnt und die Person wurde darüber per E-Mail in Kenntnis gesetzt.',
                'templatePath' => $this->sacevtEventRegistrationConfigEmailRefuseTemplPath,
            ],
            self::ADD_TO_WAITING_LIST_WITH_EMAIL_ACTION => [
                'formId' => strtolower(self::ADD_TO_WAITING_LIST_WITH_EMAIL_ACTION).'_form',
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
            $arrEmailTextTokens = $this->getTokenArray();

            if (self::ACCEPT_WITH_EMAIL_ACTION === $this->action && $this->event->customizeEventRegistrationConfirmationEmailText && !empty($this->event->customEventRegistrationConfirmationEmailText)) {
                // Only for accept_with_email!!!
                // Replace tags for custom notification set in the events settings (tags can be used case-insensitive!)
                $emailBodyText = $this->event->customEventRegistrationConfirmationEmailText;

                foreach ($arrEmailTextTokens as $k => $v) {
                    $strPattern = '/##'.$k.'##/i';
                    $emailBodyText = preg_replace($strPattern, $v, $emailBodyText);
                }
                $emailBodyText = strip_tags($emailBodyText);
            } else {
                // Render email body text from twig template
                $arrEmailTextTokens['renderEmailText'] = true;
                $emailBodyText = $this->twig->createTemplate(file_get_contents($this->configuration['templatePath']))->render($arrEmailTextTokens);
            }

            // Get event type
            $eventType = $this->translator->trans('MSC.'.$this->event->eventType, [], 'contao_default');

            // Add value to fields
            $arrSubjectTokens = [
                'eventType' => $eventType,
                'eventTitle' => $this->event->title,
                'renderEmailSubject' => true,
            ];
            $form->getWidget('subject')->value = $this->twig->createTemplate(file_get_contents($this->configuration['templatePath']))->render($arrSubjectTokens);
            $form->getWidget('text')->value = $emailBodyText;
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

        if (!$this->validator->isEmail($this->sacevtEventAdminEmail)) {
            throw new \Exception('Please set a valid email address for the service parameter "sacevt.event_admin_email."');
        }

        $email = new Email();
        $email->fromName = html_entity_decode(html_entity_decode($this->sacevtEventAdminName));
        $email->from = $this->sacevtEventAdminEmail;
        $email->replyTo($this->user->email);
        $email->subject = html_entity_decode((string) $form->getWidget('subject')->value);
        $email->text = html_entity_decode(strip_tags((string) $form->getWidget('text')->value));

        // Check if event participant has already been booked on another event at the same time.
        $objMember = $this->member->findOneBySacMemberId($this->registration->sacMemberId);

        if (
            self::ACCEPT_WITH_EMAIL_ACTION === $this->action &&
            null !== $objMember &&
            !$this->registration->allowMultiSignUp &&
            $this->calendarEventsUtil->areBookingDatesOccupied($this->event, $objMember)
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

                if ($this->registration->isModified()) {
                    $this->registration->tstamp = time();
                    $this->registration->save();

                    // Create new version
                    $objVersions = new Versions($this->registration->getTable(), $this->registration->id);
                    $objVersions->initialize();
                    $objVersions->create();
                }

                $this->message->addInfo($this->configuration['backendMessage']);

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
        $eventDates = $this->calendarEventsUtil->getEventTimestamps($this->event);
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
