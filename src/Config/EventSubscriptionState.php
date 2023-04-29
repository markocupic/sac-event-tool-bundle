<?php

declare(strict_types=1);

/*
 * This file is part of SAC Event Tool Bundle.
 *
 * (c) Marko Cupic 2023 <m.cupic@gmx.ch>
 * @license GPL-3.0-or-later
 * For the full copyright and license information,
 * please view the LICENSE file that was distributed with this source code.
 * @link https://github.com/markocupic/sac-event-tool-bundle
 */

namespace Markocupic\SacEventToolBundle\Config;

class EventSubscriptionState
{
    public const ALL = [
        self::SUBSCRIPTION_NOT_CONFIRMED,
        self::SUBSCRIPTION_ACCEPTED,
        self::SUBSCRIPTION_REFUSED,
        self::SUBSCRIPTION_ON_WAITING_LIST,
        self::USER_HAS_UNSUBSCRIBED,
        self::SUBSCRIPTION_STATE_UNDEFINED,
    ];

    public const SUBSCRIPTION_NOT_CONFIRMED = 'subscription-not-confirmed';
    public const SUBSCRIPTION_ACCEPTED = 'subscription-accepted';
    public const SUBSCRIPTION_REFUSED = 'subscription-refused';
    public const SUBSCRIPTION_ON_WAITING_LIST = 'subscription-on-waiting-list';
    public const USER_HAS_UNSUBSCRIBED = 'user-has-unsubscribed';
    public const SUBSCRIPTION_STATE_UNDEFINED = 'subscription-state-undefined';
}
