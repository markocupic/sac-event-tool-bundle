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

namespace Markocupic\SacEventToolBundle\Feature\EventRegistrationReminder\NotificationType;

use Terminal42\NotificationCenterBundle\NotificationType\NotificationTypeInterface;
use Terminal42\NotificationCenterBundle\Token\Definition\EmailTokenDefinition;
use Terminal42\NotificationCenterBundle\Token\Definition\Factory\TokenDefinitionFactoryInterface;
use Terminal42\NotificationCenterBundle\Token\Definition\HtmlTokenDefinition;
use Terminal42\NotificationCenterBundle\Token\Definition\TextTokenDefinition;

/**
 * Reminds the registration coordinator or main instructor of unconfirmed event registrations.
 * One notification per (user, calendar).
 *
 * See docs/features/event-registration-reminder.md
 */
class EventRegistrationReminderNotificationType implements NotificationTypeInterface
{
    public const NAME = 'event_registration_reminder';

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
                'instructor_email',
                'admin_email',
            ],
            'html_token' => [
                'registrations_html',
            ],
            'text_token' => [
                'instructor_firstname',
                'instructor_lastname',
                'instructor_name',
                'registrations',
                'send_first_reminder_after',
                'send_reminder_each',
                'link_event_tool',
            ],
        ];
    }
}
