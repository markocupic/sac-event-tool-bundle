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

namespace Markocupic\SacEventToolBundle\Feature\BackendUserPermissionReset\Cron;

use Contao\CoreBundle\DependencyInjection\Attribute\AsCronJob;
use Markocupic\SacEventToolBundle\Feature\BackendUserPermissionReset\BackendUserPermissionReset;

/**
 * Daily: resets the permissions of all backend users who inherit the permissions of their groups.
 * Runs after Feature\BackendUserHomeDirectory\Cron\BackendUserHomeDirectoryCron (03:15),
 * so the home directories exist and are added as file mount.
 *
 * See docs/features/backend-user-permission-reset.md
 */
#[AsCronJob('45 3 * * *')]
readonly class BackendUserPermissionResetCron
{
    public function __construct(private BackendUserPermissionReset $backendUserPermissionReset)
    {
    }

    public function __invoke(): void
    {
        $this->backendUserPermissionReset->resetAll();
    }
}
