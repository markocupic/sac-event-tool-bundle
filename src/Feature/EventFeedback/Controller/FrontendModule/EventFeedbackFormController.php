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

namespace Markocupic\SacEventToolBundle\Feature\EventFeedback\Controller\FrontendModule;

use Contao\CalendarEventsModel;
use Contao\Controller;
use Contao\CoreBundle\Controller\FrontendModule\AbstractFrontendModuleController;
use Contao\CoreBundle\DependencyInjection\Attribute\AsFrontendModule;
use Contao\CoreBundle\Twig\FragmentTemplate;
use Contao\FormFieldModel;
use Contao\FormModel;
use Contao\FrontendUser;
use Contao\ModuleModel;
use Contao\StringUtil;
use Markocupic\SacEventToolBundle\Feature\EventFeedback\EventFeedbackHelper;
use Markocupic\SacEventToolBundle\Feature\EventFeedback\EventListener\StoreFormDataListener;
use Markocupic\SacEventToolBundle\Feature\EventFeedback\FeedbackToken;
use Markocupic\SacEventToolBundle\Model\CalendarEventsMemberModel;
use Markocupic\SacEventToolBundle\Model\EventFeedbackModel;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Contracts\Translation\TranslatorInterface;
use Terminal42\MultipageFormsBundle\FormManagerFactory;

/**
 * Shows the feedback form to a logged in member who opened the link (with token) from the feedback request.
 *
 * Modes: show_form, form_already_filled_out, checkout (thank-you message after submitting), has_warning.
 */
#[AsFrontendModule(EventFeedbackFormController::TYPE, category: 'event_feedback', template: 'mod_event_feedback_form')]
class EventFeedbackFormController extends AbstractFrontendModuleController
{
    public const TYPE = 'event_feedback_form';

    public const MODE_WARNING = 'has_warning';

    public const MODE_SHOW_FORM = 'show_form';

    public const MODE_CHECKOUT = 'checkout';

    public const MODE_SHOW_FORM_ALREADY_FILLED_OUT = 'form_already_filled_out';

    public function __construct(
        private readonly EventFeedbackHelper $eventFeedbackHelper,
        private readonly FeedbackToken $feedbackToken,
        private readonly FormManagerFactory $formManagerFactory,
        private readonly Security $security,
        private readonly TranslatorInterface $translator,
    ) {
    }

    protected function getResponse(FragmentTemplate $template, ModuleModel $model, Request $request): Response
    {
        $user = $this->security->getUser();

        if (!$user instanceof FrontendUser) {
            return new Response('', Response::HTTP_NO_CONTENT);
        }

        $registrationId = $this->feedbackToken->getRegistrationId((string) $request->query->get('token', ''));

        if (null === $registrationId) {
            return $this->warning($template, 'tokenExpiredOrInvalid');
        }

        $registration = CalendarEventsMemberModel::findById($registrationId);

        if (null === $registration) {
            return $this->warning($template, 'eventRegistrationNotFound');
        }

        // The registration must belong to the logged in member (0 = no SAC member, never a match)
        if ((int) $registration->sacMemberId < 1 || (int) $registration->sacMemberId !== (int) $user->sacMemberId) {
            return $this->warning($template, 'invalidUuidForLoggedInUser');
        }

        $event = CalendarEventsModel::findById($registration->eventId);

        if (null === $event) {
            return $this->warning($template, 'eventMatchingUuidNotFound');
        }

        $form = $this->eventFeedbackHelper->getForm($event);

        if (null === $form) {
            return $this->warning($template, 'formMatchingUuidNotFound');
        }

        $salutation = $this->translator->trans('MSC.sacEvFb.salutation'.ucfirst((string) $registration->gender), [], 'contao_default');
        $session = $request->hasSession() ? $request->getSession() : null;

        // Thank-you message right after submitting the form
        if (null !== $session && $session->get(StoreFormDataListener::SESSION_KEY_LAST_INSERT) === $registration->uuid) {
            $template->set('mode', self::MODE_CHECKOUT);
            $template->set('checkoutMsg', $this->translator->trans('MSC.sacEvFb.checkoutMsg', [$salutation, $registration->firstname], 'contao_default'));
        } elseif (null !== EventFeedbackModel::findOneByUuid($registration->uuid)) {
            $template->set('mode', self::MODE_SHOW_FORM_ALREADY_FILLED_OUT);
            $template->set('formAlreadyFilledOutMsg', $this->translator->trans('MSC.sacEvFb.formAlreadyFilledOutMsg', [$salutation, $registration->firstname, $event->title], 'contao_default'));
        } else {
            $template->set('mode', self::MODE_SHOW_FORM);
            $template->set('formManager', $this->formManagerFactory->forFormId((int) $form->id));
            $template->set('form', Controller::getForm($form->id));
            $template->set('formLabels', json_encode($this->getOptionLabels($form)));
        }

        $template->set('member', $registration->row());
        $template->set('event', $event->row());

        return $template->getResponse();
    }

    private function warning(FragmentTemplate $template, string $errorKey): Response
    {
        $template->set('mode', self::MODE_WARNING);
        $template->set('warningMsg', $this->translator->trans('ERR.sacEvFb.'.$errorKey, [], 'contao_default'));

        return $template->getResponse();
    }

    /**
     * Option labels of the choice fields, used in the summary step of the multipage form.
     *
     * @return array<string, array<string, string>>
     */
    private function getOptionLabels(FormModel $form): array
    {
        $labels = [];

        foreach (FormFieldModel::findByPid($form->id) ?? [] as $formField) {
            if (!\in_array($formField->type, ['select', 'radio', 'checkbox'], true)) {
                continue;
            }

            foreach (StringUtil::deserialize($formField->options, true) as $option) {
                if ('' !== (string) ($option['value'] ?? '')) {
                    $labels[$formField->name][$option['value']] = $option['label'];
                }
            }
        }

        return $labels;
    }
}
