<?php

declare(strict_types=1);

/*
 * This file is part of SAC Event Tool Bundle.
 *
 * (c) Marko Cupic 2024 <m.cupic@gmx.ch>
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

class EventRegistrationNotificationType implements NotificationTypeInterface
{
    public const NAME = 'event_registration';

    public function __construct(
        private readonly TokenDefinitionFactoryInterface $factory,
    ) {
    }

    public function getName(): string
    {
        return self::NAME;
    }

    public function getTokenDefinitions(): array
    {
        $tokenDefinitions = [];

        foreach ($this->getTokenConfig()['text_token'] as $token) {
            $tokenDefinitions[] = $this->factory->create(TextTokenDefinition::class, $token, 'event_registration.'.$token);
        }

        foreach ($this->getTokenConfig()['email_token'] as $token) {
            $tokenDefinitions[] = $this->factory->create(EmailTokenDefinition::class, $token, 'event_registration.'.$token);
        }

        return $tokenDefinitions;
    }

    private function getTokenConfig(): array
    {
        return [
            'email_token' => [
                'participant_email',
                'instructor_email',
            ],
            'text_token' => [
                'event_add_iban',
                'event_course_id',
                'event_iban',
                'event_ibanBeneficiary',
                'event_id',
                'event_leistungen',
                'event_link_detail',
                'event_name',
                'event_state',
                'event_type',
                'event_*',
                'instructor_email',
                'instructor_name',
                'participant_ahv_number',
                'participant_city',
                'participant_contao_member_id',
                'participant_date_of_birth',
                'participant_email',
                'participant_emergency_phone',
                'participant_emergency_phone_name',
                'participant_food_habits',
                'participant_has_lead_climbing_education',
                'participant_mobile',
                'participant_name',
                'participant_notes',
                'participant_postal',
                'participant_sac_member_id',
                'participant_section_membership',
                'participant_state_of_subscription',
                'participant_street',
                'participant_uuid',
            ],
        ];
    }
}
