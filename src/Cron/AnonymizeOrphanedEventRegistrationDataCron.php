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
use Contao\CoreBundle\Framework\ContaoFramework;
use Markocupic\SacEventToolBundle\User\FrontendUser\ClearFrontendUserData;

#[AsCronJob('daily')]
class AnonymizeOrphanedEventRegistrationDataCron
{
	public function __construct(
		private readonly ContaoFramework $framework,
		private readonly ClearFrontendUserData $clearFrontendUserData,
	) {
	}

	/**
	 * Anonymize orphaned event registration data records
	 * (tl_calendar_events_member).
	 */
	public function __invoke(): void
	{
		// Initialize contao framework
		$this->framework->initialize();

		$this->clearFrontendUserData->anonymizeOrphanedEventRegistrations();
	}
}
