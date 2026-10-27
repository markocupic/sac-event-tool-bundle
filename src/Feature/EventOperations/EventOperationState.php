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

namespace Markocupic\SacEventToolBundle\Feature\EventOperations;

enum EventOperationState: string
{
    /**
     * The user may execute the operation.
     */
    case Enabled = 'enabled';

    /**
     * The operation exists for the event, but the user is not allowed to execute it.
     * The operation is displayed greyed out.
     */
    case Denied = 'denied';

    /**
     * The operation does not exist for the event, e.g. the tour report of a course,
     * the preview without a preview page or the e-mail without registrations.
     */
    case NotAvailable = 'not_available';
}
