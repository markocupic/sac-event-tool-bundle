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

namespace Markocupic\SacEventToolBundle\Feature\EventCompletionReminder\NotificationType;

use Terminal42\NotificationCenterBundle\NotificationType\NotificationTypeInterface;
use Terminal42\NotificationCenterBundle\Token\Definition\EmailTokenDefinition;
use Terminal42\NotificationCenterBundle\Token\Definition\Factory\TokenDefinitionFactoryInterface;
use Terminal42\NotificationCenterBundle\Token\Definition\HtmlTokenDefinition;
use Terminal42\NotificationCenterBundle\Token\Definition\TextTokenDefinition;

/**
 * Reminds an instructor or registration coordinator of the open post-event tasks
 * (tour report, participation confirmation) of all due events of one calendar.
 * One notification per (recipient, calendar).
 *
 * See docs/features/event-completion-reminder.md
 */
class EventCompletionReminderNotificationType implements NotificationTypeInterface
{
    public const NAME = 'event_completion_reminder';

    public function __construct(private readonly TokenDefinitionFactoryInterface $factory)
    {
    }

    public function getName(): string
    {
        return self::NAME;
    }

    public function getTokenDefinitions(): array
    {
        $tokenDefinitions = [];

        foreach ($this->getTokenConfig()['email_token'] as $token) {
            $tokenDefinitions[] = $this->factory->create(EmailTokenDefinition::class, $token, self::NAME.'.'.$token);
        }

        foreach ($this->getTokenConfig()['html_token'] as $token) {
            $tokenDefinitions[] = $this->factory->create(HtmlTokenDefinition::class, $token, self::NAME.'.'.$token);
        }

        foreach ($this->getTokenConfig()['text_token'] as $token) {
            $tokenDefinitions[] = $this->factory->create(TextTokenDefinition::class, $token, self::NAME.'.'.$token);
        }

        return $tokenDefinitions;
    }

    private function getTokenConfig(): array
    {
        return [
            'email_token' => [
                'recipient_email',
                'instructor_email',
            ],
            'html_token' => [
                'task_list_html',
            ],
            // Email tokens are available in text and HTML fields as well
            'text_token' => [
                'instructor_firstname',
                'instructor_lastname',
                'instructor_name',
                'calendar_title',
                'task_list_text',
                'open_task_count',
                'event_count',
                'first_offset_days',
                'interval_days',
                'reminder_count',
                'link_my_events_dashboard',
            ],
        ];
    }
}
