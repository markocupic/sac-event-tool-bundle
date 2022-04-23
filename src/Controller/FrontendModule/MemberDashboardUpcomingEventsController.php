<?php

declare(strict_types=1);

/*
 * This file is part of SAC Event Tool Bundle.
 *
 * (c) Marko Cupic 2022 <m.cupic@gmx.ch>
 * @license GPL-3.0-or-later
 * For the full copyright and license information,
 * please view the LICENSE file that was distributed with this source code.
 * @link https://github.com/markocupic/sac-event-tool-bundle
 */

namespace Markocupic\SacEventToolBundle\Controller\FrontendModule;

use Contao\CalendarEventsMemberModel;
use Contao\Controller;
use Contao\CoreBundle\Controller\FrontendModule\AbstractFrontendModuleController;
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\CoreBundle\ServiceAnnotation\FrontendModule;
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
use Markocupic\SacEventToolBundle\Config\EventSubscriptionLevel;
use Markocupic\SacEventToolBundle\Config\Log;
use NotificationCenter\Model\Notification;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;
use Symfony\Component\Security\Core\Security;

/**
 * @FrontendModule(MemberDashboardUpcomingEventsController::TYPE, category="sac_event_tool_frontend_modules")
 */
class MemberDashboardUpcomingEventsController extends AbstractFrontendModuleController
{
    public const TYPE = 'member_dashboard_upcoming_events';

    private ContaoFramework $framework;

    private Security $security;

    private string $locale;

    private ?FrontendUser $user = null;

    private ?Template $template = null;

    public function __construct(ContaoFramework $framework, Security $security, string $locale)
    {
        $this->framework = $framework;
        $this->security = $security;
        $this->locale = $locale;
    }

    public function __invoke(Request $request, ModuleModel $model, string $section, array $classes = null, PageModel $page = null): Response
    {
        // Return empty string, if user is not logged in as a frontend user

        // Set adapters
        $inputAdapter = $this->framework->getAdapter(Input::class);

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
            $this->unregisterUserFromEvent($inputAdapter->get('registrationId'), $model->unregisterFromEventNotificationId);
            $this->redirect($page->getFrontendUrl());
        }

        // Call the parent method
        return parent::__invoke($request, $model, $section, $classes);
    }

    protected function getResponse(Template $template, ModuleModel $model, Request $request): ?Response
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
        $this->addMessagesToTemplate();

        // Load language
        $controllerAdapter->loadLanguageFile('tl_calendar_events_member');

        // Upcoming events
        $this->template->arrUpcomingEvents = $calendarEventsMemberModelAdapter->findUpcomingEventsByMemberId($this->user->id);

        return $this->template->getResponse();
    }

    /**
     * @param $registrationId
     * @param $notificationId
     */
    protected function unregisterUserFromEvent($registrationId, $notificationId): void
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
        $systemAdapter = $this->framework->getAdapter(System::class);

        $blnHasError = true;
        $errorMsg = 'Es ist ein Fehler aufgetreten. Du konntest nicht vom Event abgemeldet werden. Bitte nimm mit dem verantwortlichen Leiter Kontakt auf.';

        $objEventsMember = $calendarEventsMemberModelAdapter->findByPk($registrationId);

        if (null === $objEventsMember) {
            $messageAdapter->addError($errorMsg);

            return;
        }

        // Use terminal42/notification_center
        $objNotification = $notificationAdapter->findByPk($notificationId);

        if (null !== $objNotification) {
            $objEvent = $objEventsMember->getRelated('eventId');

            if (null !== $objEvent) {
                $objInstructor = $objEvent->getRelated('mainInstructor');

                if (EventSubscriptionLevel::SUBSCRIPTION_REJECTED === $objEventsMember->stateOfSubscription) {
                    $objEventsMember->delete();
                    $systemAdapter->log(sprintf('User with SAC-User-ID %s has unsubscribed himself from event with ID: %s ("%s")', $objEventsMember->sacMemberId, $objEventsMember->eventId, $objEventsMember->eventName), __FILE__.' Line: '.__LINE__, Log::EVENT_UNSUBSCRIPTION);

                    return;
                }

                if (EventSubscriptionLevel::USER_HAS_UNSUBSCRIBED === $objEventsMember->stateOfSubscription) {
                    $errorMsg = 'Abmeldung fehlgeschlagen! Du hast dich vom Event "'.$objEvent->title.'" bereits abgemeldet.';
                } elseif (EventSubscriptionLevel::SUBSCRIPTION_NOT_CONFIRMED === $objEventsMember->stateOfSubscription || EventSubscriptionLevel::SUBSCRIPTION_WAITLISTED === $objEventsMember->stateOfSubscription) {
                    // allow unregistering if member is not confirmed on the event
                    // allow unregistering if member is waitlisted on the event
                    $blnHasError = false;
                } elseif (!$objEvent->allowDeregistration) {
                    $errorMsg = $objEvent->allowDeregistration.'Du kannst dich vom Event "'.$objEvent->title.'" nicht abmelden. Die Anmeldung ist definitiv. Nimm Kontakt mit dem Event-Organisator auf.';
                } elseif ($objEvent->startDate < time()) {
                    $errorMsg = 'Du konntest nicht vom Event "'.$objEvent->title.'" abgemeldet werden, da der Event bereits vorbei ist.';
                } elseif ($objEvent->allowDeregistration && ($objEvent->startDate < (time() + $objEvent->deregistrationLimit * 25 * 3600))) {
                    $errorMsg = 'Du konntest nicht vom Event "'.$objEvent->title.'" abgemeldet werden, da die Abmeldefrist von '.$objEvent->deregistrationLimit.' Tag(en) abgelaufen ist. Nimm, falls nötig, Kontakt mit dem Event-Organisator auf.';
                } elseif (empty($this->user->email) || !$validatorAdapter->isEmail($this->user->email)) {
                    $errorMsg = 'Leider wurde für dieses Konto in der Datenbank keine E-Mail-Adresse gefunden. Daher stehen einige Funktionen nur eingeschränkt zur Verfügung. Bitte hinterlegen Sie auf der Internetseite des Zentralverbands Ihre E-Mail-Adresse.';
                    $blnHasError = true;
                } elseif ($objEventsMember->sacMemberId !== $this->user->sacMemberId) {
                    $errorMsg = 'Du hast nicht die nötigen Benutzerrechte um dich vom Event "'.$objEvent->title.'" abzumelden.';
                    $blnHasError = true;
                } elseif (null !== $objInstructor) {
                    // unregister from event
                    $blnHasError = false;
                } else {
                    $errorMsg = 'Es ist ein Fehler aufgetreten. Du konntest nicht vom Event "'.$objEvent->title.'" abgemeldet werden. Nimm, falls nötig, Kontakt mit dem Event-Organisator auf.';
                    $blnHasError = true;
                }

                // Unregister from event
                if (!$blnHasError) {
                    $objEventsMember->stateOfSubscription = EventSubscriptionLevel::USER_HAS_UNSUBSCRIBED;

                    // Save data record in tl_calendar_events_member
                    $objEventsMember->save();

                    // Load language file
                    $controllerAdapter->loadLanguageFile('tl_calendar_events_member');

                    $arrTokens = [
                        'state_of_subscription' => $GLOBALS['TL_LANG']['MSC'][$objEventsMember->stateOfSubscription],
                        'event_course_id' => $objEvent->courseId,
                        'event_name' => $objEvent->title,
                        'event_type' => $objEvent->eventType,
                        'instructor_name' => $objInstructor->name,
                        'instructor_email' => $objInstructor->email,
                        'participant_name' => $objEventsMember->firstname.' '.$objEventsMember->lastname,
                        'participant_email' => $objEventsMember->email,
                        'event_link_detail' => $environmentAdapter->get('url').'/'.$eventsAdapter->generateEventUrl($objEvent),
                        'sac_member_id' => '' !== $objEventsMember->sacMemberId ? $objEventsMember->sacMemberId : 'keine',
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

                    $messageAdapter->addInfo('Du hast dich vom Event "'.$objEventsMember->eventName.'" abgemeldet. Der Leiter wurde per E-Mail informiert. Zur Bestätigung findest du in deinem Postfach eine Kopie dieser Nachricht.');

                    // Log
                    $systemAdapter->log(sprintf('User with SAC-User-ID %s has unsubscribed himself from event with ID: %s ("%s")', $objEventsMember->sacMemberId, $objEventsMember->eventId, $objEventsMember->eventName), __FILE__.' Line: '.__LINE__, Log::EVENT_UNSUBSCRIPTION);

                    $objNotification->send($arrTokens, $this->locale);
                }
            }
        }

        if ($blnHasError) {
            $messageAdapter->addError($errorMsg);
        }
    }

    /**
     * Add messages from session to template.
     */
    protected function addMessagesToTemplate(): void
    {
        $messageAdapter = $this->framework->getAdapter(Message::class);
        $systemAdapter = $this->framework->getAdapter(System::class);

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
}
