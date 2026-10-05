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

namespace Markocupic\SacEventToolBundle\Feature\EventFeedback;

use ReallySimpleJWT\Token;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Personal access token (JWT) in the link to the feedback form.
 *
 * Payload "user_id" = tl_calendar_events_member.id, the token expires with the feedback period.
 * Secret: sacevt.feature.event_feedback.secret
 */
readonly class FeedbackToken
{
    private const ISSUER = 'localhost';

    public function __construct(
        #[Autowire(param: 'sacevt.feature.event_feedback.secret')]
        private string $secret,
    ) {
    }

    public function hasSecret(): bool
    {
        return '' !== $this->secret;
    }

    public function create(int $registrationId, int $expiration): string
    {
        return Token::create($registrationId, $this->secret, $expiration, self::ISSUER);
    }

    /**
     * The registration ID (tl_calendar_events_member.id) of a valid token, null if the token is invalid or expired.
     */
    public function getRegistrationId(string $token): int|null
    {
        if ('' === $token || !$this->hasSecret() || !Token::validate($token, $this->secret)) {
            return null;
        }

        $payload = Token::getPayload($token, $this->secret);

        return isset($payload['user_id']) ? (int) $payload['user_id'] : null;
    }
}
