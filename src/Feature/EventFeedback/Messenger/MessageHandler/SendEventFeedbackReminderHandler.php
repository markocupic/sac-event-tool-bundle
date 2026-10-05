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

namespace Markocupic\SacEventToolBundle\Feature\EventFeedback\Messenger\MessageHandler;

use Contao\CalendarEventsModel;
use Contao\Config;
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\PageModel;
use Contao\StringUtil;
use Markocupic\SacEventToolBundle\Feature\EventFeedback\EventFeedbackHelper;
use Markocupic\SacEventToolBundle\Feature\EventFeedback\FeedbackReminder;
use Markocupic\SacEventToolBundle\Feature\EventFeedback\FeedbackToken;
use Markocupic\SacEventToolBundle\Feature\EventFeedback\Messenger\Message\SendEventFeedbackReminderMessage;
use Markocupic\SacEventToolBundle\Model\CalendarEventsMemberModel;
use Markocupic\SacEventToolBundle\Model\EventFeedbackModel;
use Markocupic\SacEventToolBundle\Model\EventFeedbackReminderModel;
use Markocupic\SacEventToolBundle\Util\CalendarEventsUtil;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Terminal42\NotificationCenterBundle\NotificationCenter;

/**
 * Sends ONE feedback request to ONE participant and deletes the request afterwards.
 *
 * Nothing is sent if the participant has already given feedback, the registration or event
 * no longer exists or the feedback is not (or no longer) set up correctly.
 */
#[AsMessageHandler]
readonly class SendEventFeedbackReminderHandler
{
    public function __construct(
        private CalendarEventsUtil $calendarEventsUtil,
        private ContaoFramework $framework,
        private EventFeedbackHelper $eventFeedbackHelper,
        private FeedbackReminder $feedbackReminder,
        private FeedbackToken $feedbackToken,
        private NotificationCenter $notificationCenter,
        private LoggerInterface|null $contaoGeneralLogger,
        private LoggerInterface|null $contaoErrorLogger,
    ) {
    }

    public function __invoke(SendEventFeedbackReminderMessage $message): void
    {
        $this->framework->initialize();

        $reminderId = $message->getReminderId();
        $reminder = $this->framework->getAdapter(EventFeedbackReminderModel::class)->findById($reminderId);

        if (null === $reminder) {
            return;
        }

        try {
            $this->send($reminder);
        } finally {
            $this->feedbackReminder->delete($reminderId);
        }
    }

    private function send(EventFeedbackReminderModel $reminder): void
    {
        $registration = $this->framework->getAdapter(CalendarEventsMemberModel::class)->findOneByUuid($reminder->uuid);

        if (null === $registration) {
            return;
        }

        // Feedback already given
        if (null !== $this->framework->getAdapter(EventFeedbackModel::class)->findOneByUuid($registration->uuid)) {
            return;
        }

        $event = $this->framework->getAdapter(CalendarEventsModel::class)->findById($registration->eventId);

        if (null === $event) {
            return;
        }

        if (true !== ($errorCode = $this->eventFeedbackHelper->eventHasValidFeedbackConfiguration($event))) {
            $this->contaoErrorLogger?->error(\sprintf(
                'Could not send event feedback reminder due to misconfiguration. Error code: "%s". Event ID: %d. Reminder UUID: "%s".',
                $errorCode,
                $event->id,
                $reminder->uuid,
            ));

            return;
        }

        if (!$this->feedbackToken->hasSecret()) {
            $this->contaoErrorLogger?->error('Could not send event feedback reminder: "sacevt.feature.event_feedback.secret" is not set.');

            return;
        }

        $receipts = $this->notificationCenter->sendNotification(
            (int) $this->eventFeedbackHelper->getNotificationId($event),
            $this->getTokens($registration, $event, $reminder),
        );

        if (!$receipts->count()) {
            return;
        }

        ++$registration->countOnlineEventFeedbackNotifications;
        $registration->save();

        $this->contaoGeneralLogger?->info(\sprintf(
            'An event feedback reminder for event "%s" ID %d has been sent to "%s %s" (event registration ID %d).',
            StringUtil::revertInputEncoding((string) $event->title),
            $event->id,
            $registration->firstname,
            $registration->lastname,
            $registration->id,
        ));
    }

    /**
     * @return array<string, string>
     */
    private function getTokens(CalendarEventsMemberModel $registration, CalendarEventsModel $event, EventFeedbackReminderModel $reminder): array
    {
        /** @var PageModel $page checked in EventFeedbackHelper::eventHasValidFeedbackConfiguration() */
        $page = $this->eventFeedbackHelper->getPage($event);
        $token = $this->feedbackToken->create((int) $registration->id, (int) $reminder->expiration);
        $instructor = $this->calendarEventsUtil->getMainInstructor($event);

        return [
            'instructor_name' => $this->calendarEventsUtil->getMainInstructorName($event),
            'instructor_email' => null !== $instructor ? (string) $instructor->email : '',
            'admin_email' => $this->getAdminEmail($page),
            'participant_firstname' => (string) $registration->firstname,
            'participant_lastname' => (string) $registration->lastname,
            'participant_email' => (string) $registration->email,
            'participant_uuid' => (string) $registration->uuid,
            'event_title' => StringUtil::revertInputEncoding((string) $event->title),
            'feedback_url' => \sprintf('%s?token=%s', $page->getAbsoluteUrl(), $token),
        ];
    }

    /**
     * $GLOBALS['TL_ADMIN_EMAIL'] is not available on the command line:
     * use the admin e-mail of the root page, otherwise the one of the system settings.
     */
    private function getAdminEmail(PageModel $page): string
    {
        $page->loadDetails();

        if (!empty($page->adminEmail)) {
            return (string) $page->adminEmail;
        }

        return (string) $this->framework->getAdapter(Config::class)->get('adminEmail');
    }
}
