<?php

/**
 * SAC Event Tool Web Plugin for Contao
 * Copyright (c) 2008-2019 Marko Cupic
 * @package sac-event-tool-bundle
 * @author Marko Cupic m.cupic@gmx.ch, 2017-2019
 * @link https://github.com/markocupic/sac-event-tool-bundle
 */

namespace Markocupic\SacEventToolBundle\Controller\FrontendModule;

use Contao\CalendarEventsMemberModel;
use Contao\Config;
use Contao\Controller;
use Contao\CoreBundle\Controller\FrontendModule\AbstractFrontendModuleController;
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\CoreBundle\Routing\ScopeMatcher;
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
use Doctrine\DBAL\Connection;
use NotificationCenter\Model\Notification;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Security;
use Contao\CoreBundle\ServiceAnnotation\FrontendModule;

/**
 * Class MemberDashboardUpcomingEventsController
 * @package Markocupic\SacEventToolBundle\Controller\FrontendModule
 * @FrontendModule(category="sac_event_tool_fe_modules", type="member_dashboard_upcoming_events")
 */
class MemberDashboardUpcomingEventsController extends AbstractFrontendModuleController
{

    /**
     * @var ContaoFramework
     */
    protected $framework;

    /**
     * @var Connection
     */
    protected $connection;

    /**
     * @var Security
     */
    protected $security;

    /**
     * @var RequestStack
     */
    protected $requestStack;

    /**
     * @var ScopeMatcher
     */
    protected $scopeMatcher;

    /**
     * @var FrontendUser
     */
    protected $objUser;

    /**
     * ModuleSacEventToolMemberDashboardBookingsController constructor.
     * @param ContaoFramework $framework
     * @param Connection $connection
     * @param Security $security
     * @param RequestStack $requestStack
     * @param ScopeMatcher $scopeMatcher
     */
    public function __construct(ContaoFramework $framework, Connection $connection, Security $security, RequestStack $requestStack, ScopeMatcher $scopeMatcher)
    {
        $this->framework = $framework;
        $this->connection = $connection;
        $this->security = $security;
        $this->requestStack = $requestStack;
        $this->scopeMatcher = $scopeMatcher;
    }

    /**
     * @param Request $request
     * @param ModuleModel $model
     * @param string $section
     * @param array|null $classes
     * @param PageModel|null $page
     * @return Response
     */
    public function __invoke(Request $request, ModuleModel $model, string $section, array $classes = null, PageModel $page = null): Response
    {
        // Set adapters
        $controllerAdapter = $this->framework->getAdapter(Controller::class);
        $inputAdapter = $this->framework->getAdapter(Input::class);

        // Return empty string, if user is not logged in as a frontend user
        if ($this->isFrontend())
        {
            if (($objUser = $this->security->getUser()) instanceof FrontendUser)
            {
                $this->objUser = $objUser;
            }
        }

        // Neither cache nor search page
        $page->noSearch = 1;
        $page->cache = 0;

        if ($this->objUser === null)
        {
            $controllerAdapter->redirect('');
        }

        // Sign out from Event
        if ($inputAdapter->get('do') === 'unregisterUserFromEvent')
        {
            $this->unregisterUserFromEvent($inputAdapter->get('registrationId'), $model->unregisterFromEventNotificationId);
            $controllerAdapter->redirect($page->getFrontendUrl());
        }

        // Call the parent method
        return parent::__invoke($request, $model, $section, $classes);
    }

    /**
     * @param Template $template
     * @param ModuleModel $model
     * @param Request $request
     * @return null|Response
     */
    protected function getResponse(Template $template, ModuleModel $model, Request $request): ?Response
    {
        // Set adapters
        $messageAdapter = $this->framework->getAdapter(Message::class);
        $validatorAdapter = $this->framework->getAdapter(Validator::class);
        $calendarEventsMemberModelAdapter = $this->framework->getAdapter(CalendarEventsMemberModel::class);
        $controllerAdapter = $this->framework->getAdapter(Controller::class);

        if ($this->objUser->email == '' || !$validatorAdapter->isEmail($this->objUser->email))
        {
            $messageAdapter->addInfo('Leider wurde f&uuml;r dieses Konto in der Datenbank keine E-Mail-Adresse gefunden. Daher stehen einige Funktionen nur eingeschr&auml;nkt zur Verf&uuml;gung. Bitte hinterlegen Sie auf der Internetseite des Zentralverbands Ihre E-Mail-Adresse.');
        }

        if (Message::hasInfo())
        {
            $template->hasInfoMessage = true;
            $session = System::getContainer()->get('session')->getFlashBag()->get('contao.FE.info');
            $template->infoMessage = $session[0];
        }

        if (Message::hasError())
        {
            $template->hasErrorMessage = true;
            $session = System::getContainer()->get('session')->getFlashBag()->get('contao.FE.error');
            $template->errorMessage = $session[0];
            $template->errorMessages = $session;
        }

        Message::reset();

        $controllerAdapter->loadLanguageFile('tl_calendar_events_member');

        // Upcoming events
        $template->arrUpcomingEvents = $calendarEventsMemberModelAdapter->findUpcomingEventsByMemberId($this->objUser->id);

        return $template->getResponse();
    }

    /**
     * Identify the Contao scope (TL_MODE) of the current request
     * @return bool
     */
    protected function isFrontend(): bool
    {
        return $this->scopeMatcher->isFrontendRequest($this->requestStack->getCurrentRequest());
    }

    /**
     * @param $registrationId
     * @param $notificationId
     */
    protected function unregisterUserFromEvent($registrationId, $notificationId)
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
        $configAdapter = $this->framework->getAdapter(Config::class);

        $blnHasError = true;
        $errorMsg = 'Es ist ein Fehler aufgetreten. Du konntest nicht vom Event abgemeldet werden. Bitte nimm mit dem verantwortlichen Leiter Kontakt auf.';

        $objEventsMember = $calendarEventsMemberModelAdapter->findByPk($registrationId);
        if ($objEventsMember === null)
        {
            $messageAdapter->add($errorMsg, 'TL_ERROR', TL_MODE);
            return;
        }

        // Use terminal42/notification_center
        $objNotification = $notificationAdapter->findByPk($notificationId);

        if (null !== $objNotification && null !== $objEventsMember)
        {
            $objEvent = $objEventsMember->getRelated('eventId');
            if ($objEvent !== null)
            {
                $objInstructor = $objEvent->getRelated('mainInstructor');
                if ($objEventsMember->stateOfSubscription === 'subscription-refused')
                {
                    $objEventsMember->delete();
                    $systemAdapter->log(sprintf('User with SAC-User-ID %s has unsubscribed himself from event with ID: %s ("%s")', $objEventsMember->sacMemberId, $objEventsMember->eventId, $objEventsMember->eventName), __FILE__ . ' Line: ' . __LINE__, Config::get('SAC_EVT_LOG_EVENT_UNSUBSCRIPTION'));
                    return;
                }
                elseif ($objEventsMember->stateOfSubscription === 'user-has-unsubscribed')
                {
                    $errorMsg = 'Abmeldung fehlgeschlagen! Du hast dich vom Event "' . $objEvent->title . '" bereits abgemeldet.';
                    $blnHasError = true;
                }
                elseif ($objEventsMember->stateOfSubscription === 'subscription-not-confirmed' || $objEventsMember->stateOfSubscription === 'subscription-waitlisted')
                {
                    // allow unregistering if member is not confirmed on the event
                    // allow unregistering if member is waitlisted on the event
                    $blnHasError = false;
                }
                elseif (!$objEvent->allowDeregistration)
                {
                    $errorMsg = $objEvent->allowDeregistration . 'Du kannst dich vom Event "' . $objEvent->title . '" nicht abmelden. Die Anmeldung ist definitiv. Nimm Kontakt mit dem Event-Organisator auf.';
                    $blnHasError = true;
                }
                elseif ($objEvent->startDate < time())
                {
                    $errorMsg = 'Du konntest nicht vom Event "' . $objEvent->title . '" abgemeldet werden, da der Event bereits vorbei ist.';
                    $blnHasError = true;
                }
                elseif ($objEvent->allowDeregistration && ($objEvent->startDate < (time() + $objEvent->deregistrationLimit * 25 * 3600)))
                {
                    $errorMsg = 'Du konntest nicht vom Event "' . $objEvent->title . '" abgemeldet werden, da die Abmeldefrist von ' . $objEvent->deregistrationLimit . ' Tag(en) abgelaufen ist. Nimm, falls nötig, Kontakt mit dem Event-Organisator auf.';
                    $blnHasError = true;
                }
                elseif ($this->objUser->email == '' || !$validatorAdapter->isEmail($this->objUser->email))
                {
                    $errorMsg = 'Leider wurde f&uuml;r dieses Konto in der Datenbank keine E-Mail-Adresse gefunden. Daher stehen einige Funktionen nur eingeschr&auml;nkt zur Verf&uuml;gung. Bitte hinterlegen Sie auf der Internetseite des Zentralverbands Ihre E-Mail-Adresse.';
                    $blnHasError = true;
                }
                elseif ($objEventsMember->sacMemberId != $this->objUser->sacMemberId)
                {
                    $errorMsg = 'Du hast nicht die nötigen Benutzerrechte um dich vom Event "' . $objEvent->title . '" abzumelden.';
                    $blnHasError = true;
                }
                elseif ($objInstructor !== null)
                {
                    // unregister from event
                    $blnHasError = false;
                }
                else
                {
                    $errorMsg = 'Es ist ein Fehler aufgetreten. Du konntest nicht vom Event "' . $objEvent->title . '" abgemeldet werden. Nimm, falls nötig, Kontakt mit dem Event-Organisator auf.';
                    $blnHasError = true;
                }

                // Unregister from event
                if (!$blnHasError)
                {
                    $objEventsMember->stateOfSubscription = 'user-has-unsubscribed';

                    // Save data record in tl_calendar_events_member
                    $objEventsMember->save();

                    // Load language file
                    $controllerAdapter->loadLanguageFile('tl_calendar_events_member');

                    $arrTokens = array(
                        'state_of_subscription' => $GLOBALS['TL_LANG']['tl_calendar_events_member'][$objEventsMember->stateOfSubscription],
                        'event_course_id'       => $objEvent->courseId,
                        'event_name'            => $objEvent->title,
                        'event_type'            => $objEvent->eventType,
                        'instructor_name'       => $objInstructor->name,
                        'instructor_email'      => $objInstructor->email,
                        'participant_name'      => $objEventsMember->firstname . ' ' . $objEventsMember->lastname,
                        'participant_email'     => $objEventsMember->email,
                        'event_link_detail'     => $environmentAdapter->get('url') . '/' . $eventsAdapter->generateEventUrl($objEvent),
                        'sac_member_id'         => $objEventsMember->sacMemberId != '' ? $objEventsMember->sacMemberId : 'keine',
                    );

                    if ($objEvent->registrationGoesTo > 0)
                    {
                        $objUser = $userModelAdapter->findByPk($objEvent->registrationGoesTo);
                        if ($objUser !== null)
                        {
                            if ($objUser->email != '')
                            {
                                if ($validatorAdapter->isEmail($objUser->email))
                                {
                                    $arrTokens['instructor_name'] = $objUser->name;
                                    $arrTokens['instructor_email'] = $objUser->email;
                                }
                            }
                        }
                    }

                    $messageAdapter->add('Du hast dich vom Event "' . $objEventsMember->eventName . '" abgemeldet. Der Leiter wurde per E-Mail informiert. Zur Bestätigung findest du in deinem Postfach eine Kopie dieser Nachricht.', 'TL_INFO', TL_MODE);

                    // Log
                    $systemAdapter->log(sprintf('User with SAC-User-ID %s has unsubscribed himself from event with ID: %s ("%s")', $objEventsMember->sacMemberId, $objEventsMember->eventId, $objEventsMember->eventName), __FILE__ . ' Line: ' . __LINE__, $configAdapter->get('SAC_EVT_LOG_EVENT_UNSUBSCRIPTION'));

                    $objNotification->send($arrTokens, 'de');
                }
            }
        }
        if ($blnHasError)
        {
            $messageAdapter->add($errorMsg, 'TL_ERROR', TL_MODE);
        }
    }

}