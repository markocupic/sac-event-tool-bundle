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

namespace Markocupic\SacEventToolBundle\Feature\EventFeedback\EventListener;

use Contao\CoreBundle\DependencyInjection\Attribute\AsHook;
use Contao\Form;
use Contao\FrontendUser;
use Markocupic\SacEventToolBundle\Feature\EventFeedback\FeedbackReminder;
use Markocupic\SacEventToolBundle\Feature\EventFeedback\FeedbackToken;
use Markocupic\SacEventToolBundle\Model\CalendarEventsMemberModel;
use Markocupic\SacEventToolBundle\Model\EventFeedbackModel;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Completes a submitted feedback (event, registration UUID, form, date) and deletes
 * the remaining feedback requests of the registration.
 */
#[AsHook('storeFormData', priority: 100)]
readonly class StoreFormDataListener
{
    public const SESSION_KEY_LAST_INSERT = 'sacevt_event_feedback_last_insert';

    public function __construct(
        private FeedbackReminder $feedbackReminder,
        private FeedbackToken $feedbackToken,
        private RequestStack $requestStack,
        private Security $security,
    ) {
    }

    public function __invoke(array $data, Form $form): array
    {
        if (!$form->isSacEventFeedbackForm) {
            return $data;
        }

        $request = $this->requestStack->getCurrentRequest();
        $user = $this->security->getUser();

        if (null === $request || !$user instanceof FrontendUser) {
            throw new \RuntimeException('The event feedback form is only accessible to logged in members.');
        }

        $registrationId = $this->feedbackToken->getRegistrationId((string) $request->query->get('token', ''));
        $registration = null !== $registrationId ? CalendarEventsMemberModel::findById($registrationId) : null;

        if (null === $registration) {
            throw new \RuntimeException('Invalid token or no registration matches the token.');
        }

        if ((int) $registration->sacMemberId < 1 || (int) $registration->sacMemberId !== (int) $user->sacMemberId) {
            throw new \RuntimeException('The registration does not belong to the logged in member.');
        }

        if (null !== EventFeedbackModel::findOneByUuid($registration->uuid)) {
            throw new \RuntimeException('A feedback for this registration already exists.');
        }

        $data['form'] = $form->id;
        $data['uuid'] = $registration->uuid;
        $data['pid'] = $registration->eventId;
        $data['dateAdded'] = time();
        $data['tstamp'] = time();

        $this->feedbackReminder->deleteByUuid((string) $registration->uuid);

        // Show the thank-you message after the redirect
        if ($request->hasSession()) {
            $request->getSession()->set(self::SESSION_KEY_LAST_INSERT, $registration->uuid);
        }

        return $data;
    }
}
