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

namespace Markocupic\SacEventToolBundle\Feature\EventCompletionReminder;

/**
 * One open task of one event, as shown in the to-do list.
 */
final readonly class TaskItem
{
    public function __construct(
        public string $name,
        public string $label,
        public string $url,
    ) {
    }
}
