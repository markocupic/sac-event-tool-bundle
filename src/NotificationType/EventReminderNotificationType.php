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

namespace Markocupic\SacEventToolBundle\NotificationType;

use Terminal42\NotificationCenterBundle\NotificationType\NotificationTypeInterface;
use Terminal42\NotificationCenterBundle\Token\Definition\EmailTokenDefinition;
use Terminal42\NotificationCenterBundle\Token\Definition\Factory\TokenDefinitionFactoryInterface;
use Terminal42\NotificationCenterBundle\Token\Definition\TextTokenDefinition;

/**
 * Reminder sent x days before the event starts.
 * One single e-mail per event:
 * To/Reply-To: ##main_instructor_email##, CC: ##co_instructors_email##, BCC: ##participants_email##.
 */
class EventReminderNotificationType implements NotificationTypeInterface
{
    public const NAME = 'event_reminder';

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

        foreach ($this->getTokenConfig()['text_token'] as $token) {
            $tokenDefinitions[] = $this->factory->create(TextTokenDefinition::class, $token, self::NAME.'.'.$token);
        }

        foreach ($this->getTokenConfig()['email_token'] as $token) {
            $tokenDefinitions[] = $this->factory->create(EmailTokenDefinition::class, $token, self::NAME.'.'.$token);
        }

        return $tokenDefinitions;
    }

    private function getTokenConfig(): array
    {
        return [
            'email_token' => [
                'main_instructor_email',
                'instructors_email',
                'co_instructors_email',
                'participants_email',
            ],
            'text_token' => [
                'event_id',
                'event_title',
                'event_event_type',
                'event_event_type_translated',
                'event_course_id',
                'event_start_date',
                'event_end_date',
                'event_period',
                'event_duration',
                'event_meeting_point',
                'event_leistungen',
                'event_deregistration_limit',
                'event_link_detail',
                'event_raw_*',
                'event_*',
                'main_instructor_name',
                'main_instructor_email',
                'main_instructor_phone',
                'main_instructor_mobile',
                'instructors_names',
                'instructors_email',
                'co_instructors_names',
                'co_instructors_email',
                'participants_names',
                'participants_email',
                'participants_count',
                'reminder_offset_days',
            ],
        ];
    }
}
