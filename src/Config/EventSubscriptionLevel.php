<?php

declare(strict_types=1);

/*
 * This file is part of SAC Event Tool Bundle.
 *
 * (c) Marko Cupic 2022 <m.cupic@gmx.ch>
 * @license GPL-3.0-or-later
 * For the full copyright and license information,
 * please view the LICENSE file that was distributed with this source code.
 * @link https://github.com/markocupic/sac-event-tool-bundle
 */

namespace Markocupic\SacEventToolBundle\Config;

class EventSubscriptionLevel
{
    public const SUBSCRIPTION_NOT_CONFIRMED = 'subscription-not-confirmed';
    public const SUBSCRIPTION_ACCEPTED = 'subscription-accepted';
    public const SUBSCRIPTION_REFUSED = 'subscription-refused';
    public const SUBSCRIPTION_WAITLISTED = 'subscription-waitlisted';
    public const USER_HAS_UNSUBSCRIBED = 'user-has-unsubscribed';
    public const SUBSCRIPTION_STATE_UNDEFINED = 'subscription-state-undefined';
}
