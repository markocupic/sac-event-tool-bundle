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

namespace Markocupic\SacEventToolBundle\Controller\FrontendModule\EventRegistration\StepHandler;

use Contao\CalendarEventsModel;
use Contao\Controller;
use Contao\CoreBundle\Exception\AccessDeniedException;
use Contao\CoreBundle\Exception\RedirectResponseException;
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\CoreBundle\Monolog\ContaoContext;
use Contao\Message;
use Contao\ModuleModel;
use Markocupic\SacEventToolBundle\Config\Log;
use Markocupic\SacEventToolBundle\Controller\FrontendModule\EventRegistration\EventRegistrationCreator;
use Markocupic\SacEventToolBundle\Controller\FrontendModule\EventRegistration\EventRegistrationEligibility;
use Markocupic\SacEventToolBundle\Controller\FrontendModule\EventRegistration\EventRegistrationFormFactory;
use Markocupic\SacEventToolBundle\Controller\FrontendModule\EventRegistration\LoggedInMemberProvider;
use Markocupic\SacEventToolBundle\Controller\FrontendModule\Exception\EventRegistrationException;
use Markocupic\SacEventToolBundle\Event\EventRegistrationEvent;
use Markocupic\SacEventToolBundle\Feature\ParticipantEventHistory\ParticipantEventHistoryQuery;
use Markocupic\SacEventToolBundle\Model\CalendarEventsMemberModel;
use Markocupic\SacEventToolBundle\Util\CalendarEventsUtil;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Registration step: shows the registration form and saves the registration.
 *
 * - EventRegistrationEligibility: may the member register for the event?
 * - EventRegistrationFormFactory: the form fields
 * - EventRegistrationCreator: saves the registration (with lock)
 */
#[AutoconfigureTag('sacevt.event_registration.step_handler')]
class RegisterStep implements StepHandlerInterface, ValidationStepInterface
{
    private const string STEP = 'register';

    private const string TEMPLATE = '@Contao_MarkocupicSacEventToolBundle/frontend_module/partials/event_registration/step/register.html.twig';

    private const int PRIORITY = 200;

    public function __construct(
        private readonly CalendarEventsUtil $calendarEventsUtil,
        private readonly ContaoFramework $framework,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly EventRegistrationCreator $eventRegistrationCreator,
        private readonly EventRegistrationEligibility $eventRegistrationEligibility,
        private readonly EventRegistrationFormFactory $eventRegistrationFormFactory,
        private readonly LoggedInMemberProvider $loggedInMemberProvider,
        private readonly TranslatorInterface $translator,
        private readonly string $sacevtSectionName,
        private readonly LoggerInterface|null $contaoErrorLogger = null,
    ) {
    }

    public static function getName(): string
    {
        return self::STEP;
    }

    public static function getPriority(): int
    {
        return self::PRIORITY;
    }

    public function getTemplateName(): string
    {
        return self::TEMPLATE;
    }

    public function doAutoForward(CalendarEventsModel $eventModel, Request $request, ModuleModel $moduleModel): bool
    {
        return true;
    }

    /**
     * Valid as soon as the member is registered for the event.
     */
    public function validate(CalendarEventsModel $eventModel, Request $request, ModuleModel $moduleModel): bool
    {
        $memberModel = $this->loggedInMemberProvider->getMember();

        if (null === $memberModel) {
            return false;
        }

        return $this->framework->getAdapter(CalendarEventsMemberModel::class)->isRegistered((int) $memberModel->id, (int) $eventModel->id);
    }

    public function prepareStep(CalendarEventsModel $eventModel, Request $request, ModuleModel $moduleModel): array
    {
        $memberModel = $this->loggedInMemberProvider->getMember();

        if (null === $memberModel) {
            throw new AccessDeniedException('The logged in Contao Frontend User could not be matched to a member record.');
        }

        $template = ['event_model' => $eventModel->current()];

        // Hint below the "notes" field: the instructors see the participations of the last years (see Feature\ParticipantEventHistory)
        $template['notes_explanation'] = $this->translator->trans('FORM.evt_reg_ffield_expl_notes', [ParticipantEventHistoryQuery::HISTORY_YEARS, $this->sacevtSectionName], 'contao_default');

        try {
            // Throws an EventRegistrationException if the member may not register
            $this->eventRegistrationEligibility->check($eventModel, $memberModel);

            if ($this->calendarEventsUtil->eventIsFullyBooked($eventModel)) {
                $template['eventFullyBooked'] = true;
            }

            $form = $this->eventRegistrationFormFactory->create($eventModel, $memberModel, $request);

            // validate() also checks whether the form has been submitted
            if ($form->validate()) {
                $registrationModel = $this->eventRegistrationCreator->create($eventModel, $memberModel, $form->fetchAll());

                // E.g. notify the member about the registration
                $this->eventDispatcher->dispatch(new EventRegistrationEvent(
                    $request,
                    $registrationModel,
                    $eventModel,
                    $memberModel,
                    $moduleModel,
                    $registrationModel->row(),
                ));

                // Reload the page: the step is valid now and forwards to the next step
                $this->framework->getAdapter(Controller::class)->reload();
            }

            $template['form'] = $form;
        } catch (RedirectResponseException $e) {
            throw $e;
        } catch (EventRegistrationException $e) {
            $this->framework->getAdapter(Message::class)->add($this->translator->trans($e->getTranslatableText(), $e->getParams(), 'contao_default'), $e->getErrorLevel());

            $this->addErrorMessageToTemplate($template, $request);

            if (EventRegistrationException::LEVEL_ERROR === $e->getErrorLevel()) {
                $this->contaoErrorLogger?->error($e->getMessage(), ['contao' => new ContaoContext(__METHOD__, Log::EVENT_SUBSCRIPTION_ERROR)]);
            }
        } catch (\Throwable $e) {
            $this->framework->getAdapter(Message::class)->addError($this->translator->trans('ERR.evt_reg_unknownError', [], 'contao_default'));

            $this->addErrorMessageToTemplate($template, $request);

            // Including the exception (stack trace) for debugging
            $this->contaoErrorLogger?->error($e->getMessage(), ['contao' => new ContaoContext(__METHOD__, Log::EVENT_SUBSCRIPTION_ERROR), 'exception' => $e]);
        }

        return $template;
    }

    private function addErrorMessageToTemplate(array &$template, Request $request): void
    {
        $messageAdapter = $this->framework->getAdapter(Message::class);

        if ($messageAdapter->hasError()) {
            $template['errorMessage'] = $this->getFirstMessage('error', $request);
        }

        if ($messageAdapter->hasInfo()) {
            $template['infoMessage'] = $this->getFirstMessage('info', $request);
        }
    }

    private function getFirstMessage(string $type, Request $request): string|null
    {
        return $request->getSession()->getFlashBag()->get('contao.FE.'.$type)[0] ?? null;
    }
}
