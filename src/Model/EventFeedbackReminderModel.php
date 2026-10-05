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

namespace Markocupic\SacEventToolBundle\Model;

use Contao\Model;

/**
 * A scheduled feedback request to a participant (tl_event_feedback_reminder).
 *
 * See docs/features/event-feedback.md
 */
class EventFeedbackReminderModel extends Model
{
    protected static $strTable = 'tl_event_feedback_reminder';
}
