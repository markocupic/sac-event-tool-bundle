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

namespace Markocupic\SacEventToolBundle\Cron;

use Contao\CoreBundle\DependencyInjection\Attribute\AsCronJob;
use Markocupic\SacEventToolBundle\Database\SyncEventRegistrationDatabase;

#[AsCronJob('40 4 * * *')]
#[AsCronJob('40 5 * * *')]
readonly class EventRegistrationDatabaseSyncCron
{
    public function __construct(
        private SyncEventRegistrationDatabase $syncEventRegistrationDatabase,
    ) {
    }

    public function __invoke(): void
    {
        $this->syncEventRegistrationDatabase->run();
    }
}
