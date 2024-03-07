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

namespace Markocupic\SacEventToolBundle\Twig\Extension;

use Contao\CalendarEventsModel;
use Contao\CoreBundle\Framework\Adapter;
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\Events;
use Contao\FrontendTemplate;
use Markocupic\SacEventToolBundle\CalendarEventsHelper;
use Symfony\Component\HttpFoundation\RequestStack;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class TwigSchemaOrgDataManager extends AbstractExtension
{
	private Adapter $calendarEventsHelper;
	private Adapter $events;

	public function __construct(
		private readonly ContaoFramework $framework,
		private readonly RequestStack $requestStack,
		private readonly string $sacevtSectionName,
	) {
		$this->calendarEventsHelper = $this->framework->getAdapter(CalendarEventsHelper::class);
		$this->events = $this->framework->getAdapter(Events::class);
	}

	public function getFunctions(): array
	{
		return [
			new TwigFunction('add_event_schema_org_data', [$this, 'addEventSchemaOrgData']),
			new TwigFunction('get_event_schema_org_data', [$this, 'getEventSchemaOrgData']),
		];
	}

	public function addEventSchemaOrgData(CalendarEventsModel $model, FrontendTemplate $template): void
	{
		$this->framework->initialize();

		$template->addSchemaOrg($this->getEventSchemaOrgData($model, $template));
	}

	public function getEventSchemaOrgData(CalendarEventsModel $model, FrontendTemplate $template): array
	{
		$this->framework->initialize();

		$request = $this->requestStack->getCurrentRequest();

		$jsonLd = $this->events->getSchemaOrgData($model);
		$jsonLd['location'] = $model->location;
		$jsonLd['tourguide'] = implode(', ', $this->calendarEventsHelper->getInstructorNamesAsArray($model));

		$mainSection = $this->sacevtSectionName;
		$organizers = array_map(
			static fn($el) => $mainSection.': '.$el,
			$this->calendarEventsHelper->getEventOrganizersAsArray($model, 'title'),
		);

		$jsonLd['organizer'] = [
			'@type' => 'Organization',
			'name'  => implode(', ', $organizers),
			'url'   => $request->getSchemeAndHttpHost(),
		];

		if ($template->addImage && $template->figure) {
			$jsonLd['image'] = $template->figure->getSchemaOrgData();
		}

		return $jsonLd;
	}
}
