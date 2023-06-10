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

namespace Markocupic\SacEventToolBundle\Controller\FrontendModule;

use Contao\Controller;
use Contao\CoreBundle\Controller\FrontendModule\AbstractFrontendModuleController;
use Contao\CoreBundle\DependencyInjection\Attribute\AsFrontendModule;
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\CoreBundle\Monolog\ContaoContext;
use Contao\Environment;
use Contao\Events;
use Contao\FrontendUser;
use Contao\Input;
use Contao\Message;
use Contao\ModuleModel;
use Contao\PageModel;
use Contao\System;
use Contao\Template;
use Contao\UserModel;
use Contao\Validator;
use Markocupic\SacEventToolBundle\Config\EventSubscriptionState;
use Markocupic\SacEventToolBundle\Config\Log;
use Markocupic\SacEventToolBundle\Model\CalendarEventsMemberModel;
use NotificationCenter\Model\Notification;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;
use Symfony\Component\Security\Core\Security;

#[AsFrontendModule(MemberDashboardUpcomingEventsController::TYPE, category:'sac_event_tool_frontend_modules', template:'mod_member_dashboard_upcoming_events')]
class MemberDashboardUpcomingEventsController extends AbstractFrontendModuleController
{
    public const TYPE = 'member_dashboard_upcoming_events';

    private FrontendUser|null $user = null;
    private Template|null $template = null;

    public function __construct(
        private readonly ContaoFramework $framework,
        private readonly Security $security,
        private readonly string $sacevtLocale,
        private readonly LoggerInterface|null $contaoGeneralLogger = null,
    ) {
    }

    public function __invoke(Request $request, ModuleModel $model, string $section, array $classes = null, PageModel $page = null): Response
    {
        // Set adapters
        $inputAdapter = $this->framework->getAdapter(Input::class);
        $systemAdapter = $this->framework->getAdapter(System::class);

        if (($objUser = $this->security->getUser()) instanceof FrontendUser) {
            $this->user = $objUser;
        }

        if (null !== $page) {
            // Neither cache nor search page
            $page->noSearch = 1;
            $page->cache = 0;
        }

        // Sign out from Event
        if ('unregisterUserFromEvent' === $inputAdapter->get('do')) {
            $this->unregisterUserFromEvent((int) $inputAdapter->get('registrationId'), (int) $model->unregisterFromEventNotificationId);

            return $this->redirect($systemAdapter->getReferer());
        }

        // Call the parent method
        return parent::__invoke($request, $model, $section, $classes);
    }

    protected function getResponse(Template $template, ModuleModel $model, Request $request): Response
    {
        // Do not allow for not authorized users
        if (null === $this->user) {
            throw new UnauthorizedHttpException('Not authorized. Please log in as frontend user.');
        }

        $this->template = $template;

        // Set adapters
        $messageAdapter = $this->framework->getAdapter(Message::class);
        $validatorAdapter = $this->framework->getAdapter(Validator::class);
        $calendarEventsMemberModelAdapter = $this->framework->getAdapter(CalendarEventsMemberModel::class);
        $controllerAdapter = $this->framework->getAdapter(Controller::class);

        // Handle messages
        if (empty($this->user->email) || !$validatorAdapter->isEmail($this->user->email)) {
            $messageAdapter->addInfo('Leider wurde für dieses Konto in der Datenbank keine E-Mail-Adresse gefunden. Daher stehen einige Funktionen nur eingeschränkt zur Verfügung. Bitte hinterlegen Sie auf der Internetseite des Zentralverbands Ihre E-Mail-Adresse.');
        }

        // Add messages to template
        $this->addMessagesToTemplate($request);

        // Load language
        $controllerAdapter->loadLanguageFile('tl_calendar_events_member');

        // Upcoming events
        $this->template->arrUpcomingEvents = $calendarEventsMemberModelAdapter->findUpcomingEventsByMemberId($this->user->id);

        return $this->template->getResponse();
    }

    /**
     * @throws \Exception
     */
    private function unregisterUserFromEvent(int $registrationId, int $notificationId): void
    {
        // Set adapters
        $messageAdapter = $this->framework->getAdapter(Message::class);
        $notificationAdapter = $this->framework->getAdapter(Notification::class);
        $calendarEventsMemberModelAdapter = $this->framework->getAdapter(CalendarEventsMemberModel::class);
        $validatorAdapter = $this->framework->getAdapter(Validator::class);
        $controllerAdapter = $this->framework->getAdapter(Controller::class);
        $eventsAdapter = $this->framework->getAdapter(Events::class);
        $userModelAdapter = $this->framework->getAdapter(UserModel::class);
        $environmentAdapter = $this->framework->getAdapter(Environment::class);

        $blnHasError = true;

        $objEventRegistration = $calendarEventsMemberModelAdapter->findByPk($registrationId);

        if (null === $objEventRegistration) {
            $errorMsg = sprintf(
                'Zur Registrierungs-ID %d wurde keine Event-Registrierung gefunden. '.
                'Bitte nimm mit dem verantwortlichen Leiter Kontakt auf, falls du glaubst, dass es sich hierbei um einen Fehler handelt.',
                $registrationId,
            );

            $messageAdapter->addError($errorMsg);

            return;
        }

        // Use terminal42/notification_center
        $objNotification = $notificationAdapter->findByPk($notificationId);

        if (null !== $objNotification) {
            $objEvent = $objEventRegistration->getRelated('eventId');

            if (null !== $objEvent) {
                $objInstructor = $objEvent->getRelated('mainInstructor');

                if (EventSubscriptionState::SUBSCRIPTION_REFUSED === $objEventRegistration->stateOfSubscription) {
                    $objEventRegistration->delete();

                    $this->contaoGeneralLogger?->info(
                        sprintf(
                            'User with SAC-User-ID %d has unsubscribed himself from event with ID: %d ("%s")',
                            $objEventRegistration->sacMemberId,
                            $objEventRegistration->eventId,
                            $objEventRegistration->eventName
                        ),
                        ['contao' => new ContaoContext(__METHOD__, Log::EVENT_UNSUBSCRIPTION)]
                    );

                    return;
                }

                if (EventSubscriptionState::USER_HAS_UNSUBSCRIBED === $objEventRegistration->stateOfSubscription) {
                    $errorMsg = 'Abmeldung fehlgeschlagen! Du hast dich vom Event "'.$objEvent->title.'" bereits abgemeldet.';
                } elseif (EventSubscriptionState::SUBSCRIPTION_NOT_CONFIRMED === $objEventRegistration->stateOfSubscription || EventSubscriptionState::SUBSCRIPTION_ON_WAITING_LIST === $objEventRegistration->stateOfSubscription) {
                    // allow unregistering if member is not confirmed on the event
                    // or member is on the waiting list for this event
                    $blnHasError = false;
                } elseif (!$objEvent->allowDeregistration) {
                    $errorMsg = 'Du kannst dich vom Event "'.$objEvent->title.'" nicht abmelden. Die Anmeldung ist definitiv. Nimm Kontakt mit dem Event-Organisator auf.';
                } elseif ($objEvent->startDate < time()) {
                    $errorMsg = 'Du konntest nicht vom Event "'.$objEvent->title.'" abgemeldet werden, da der Event bereits vorbei ist.';
                } elseif ($objEvent->allowDeregistration && ($objEvent->startDate < (time() + $objEvent->deregistrationLimit * 25 * 3600))) {
                    $errorMsg = 'Du konntest nicht vom Event "'.$objEvent->title.'" abgemeldet werden, da die Abmeldefrist von '.$objEvent->deregistrationLimit.' Tag(en) abgelaufen ist. Nimm, falls nötig, Kontakt mit dem Event-Organisator auf.';
                } elseif (empty($this->user->email) || !$validatorAdapter->isEmail($this->user->email)) {
                    $errorMsg = 'Leider wurde für dieses Konto in der Datenbank keine E-Mail-Adresse gefunden. Daher stehen einige Funktionen nur eingeschränkt zur Verfügung. Bitte hinterlegen Sie auf der Internetseite des Zentralverbands Ihre E-Mail-Adresse.';
                } elseif ((int) $objEventRegistration->sacMemberId !== (int) $this->user->sacMemberId) {
                    $errorMsg = 'Du hast nicht die nötigen Benutzerrechte um dich vom Event "'.$objEvent->title.'" abzumelden.';
                } elseif (null !== $objInstructor) {
                    // unregister from event
                    $blnHasError = false;
                } else {
                    $errorMsg = 'Es ist ein Fehler aufgetreten. Du konntest nicht vom Event "'.$objEvent->title.'" abgemeldet werden. Nimm, falls nötig, Kontakt mit dem Event-Organisator auf.';
                }

                // Unregister from event
                if (!$blnHasError) {
                    $objEventRegistration->stateOfSubscription = EventSubscriptionState::USER_HAS_UNSUBSCRIBED;

                    // Save data record in tl_calendar_events_member
                    $objEventRegistration->save();

                    // Load language file
                    $controllerAdapter->loadLanguageFile('tl_calendar_events_member');

                    $arrTokens = [
                        'state_of_subscription' => $GLOBALS['TL_LANG']['MSC'][$objEventRegistration->stateOfSubscription],
                        'event_course_id' => $objEvent->courseId,
                        'event_name' => $objEvent->title,
                        'event_type' => $objEvent->eventType,
                        'instructor_name' => $objInstructor->name,
                        'instructor_email' => $objInstructor->email,
                        'participant_name' => $objEventRegistration->firstname.' '.$objEventRegistration->lastname,
                        'participant_email' => $objEventRegistration->email,
                        'event_link_detail' => $environmentAdapter->get('url').'/'.$eventsAdapter->generateEventUrl($objEvent),
                        'sac_member_id' => !empty($objEventRegistration->sacMemberId) ? $objEventRegistration->sacMemberId : 'keine',
                    ];

                    if ($objEvent->registrationGoesTo > 0) {
                        $objUser = $userModelAdapter->findByPk($objEvent->registrationGoesTo);

                        if (null !== $objUser) {
                            if ('' !== $objUser->email) {
                                if ($validatorAdapter->isEmail($objUser->email)) {
                                    $arrTokens['instructor_name'] = $objUser->name;
                                    $arrTokens['instructor_email'] = $objUser->email;
                                }
                            }
                        }
                    }

                    $messageAdapter->addInfo('Du hast dich vom Event "'.$objEventRegistration->eventName.'" abgemeldet. Der Leiter wurde per E-Mail informiert. Zur Bestätigung findest du in deinem Postfach eine Kopie dieser Nachricht.');

                    $this->contaoGeneralLogger?->info(
                        sprintf(
                            'User with SAC-User-ID %d has unsubscribed himself from event with ID: %d ("%s")',
                            $objEventRegistration->sacMemberId,
                            $objEventRegistration->eventId,
                            $objEventRegistration->eventName
                        ),
                        ['contao' => new ContaoContext(__METHOD__, Log::EVENT_UNSUBSCRIPTION)]
                    );

                    $objNotification->send($arrTokens, $this->sacevtLocale);
                }
            }
        }

        if ($blnHasError) {
            $messageAdapter->addError($errorMsg);
        }
    }

    /**
     * Add messages from session flash to template.
     */
    private function addMessagesToTemplate(Request $request): void
    {
        $messageAdapter = $this->framework->getAdapter(Message::class);

        $this->template->hasInfoMessage = false;
        $this->template->hasErrorMessage = false;

        if ($messageAdapter->hasInfo()) {
            $this->template->hasInfoMessage = true;
            $session = $request->getSession()->getFlashBag()->get('contao.FE.info');
            $this->template->infoMessage = $session[0];
        }

        if ($messageAdapter->hasError()) {
            $this->template->hasErrorMessage = true;
            $session = $request->getSession()->getFlashBag()->get('contao.FE.error');
            $this->template->errorMessage = $session[0];
            $this->template->errorMessages = $session;
        }

        $messageAdapter->reset();
    }
}
