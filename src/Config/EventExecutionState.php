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

/**
 * These states are used for the tour report.
 */
class EventExecutionState
{
    public const STATE_EXECUTED_LIKE_PREDICTED = 'event_executed_like_predicted';
    public const STATE_ADAPTED = 'event_adapted';
    public const STATE_CANCELED = 'event_canceled';
    public const STATE_DEFERRED = 'event_deferred';
}
