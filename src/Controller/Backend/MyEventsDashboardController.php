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

namespace Markocupic\SacEventToolBundle\Controller\Backend;

use Codefog\HasteBundle\UrlParser;
use Contao\BackendUser;
use Contao\CalendarEventsModel;
use Contao\Config;
use Contao\CoreBundle\Csrf\ContaoCsrfTokenManager;
use Contao\CoreBundle\Framework\Adapter;
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\StringUtil;
use Contao\System;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;
use Doctrine\DBAL\Types\Types;
use Markocupic\SacEventToolBundle\Config\EventType;
use Markocupic\SacEventToolBundle\Controller\BackendModule\EventParticipantEmailController;
use Markocupic\SacEventToolBundle\Util\CalendarEventsUtil;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\UriSigner;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use Twig\Environment as Twig;
use Twig\Error\LoaderError;
use Twig\Error\RuntimeError;
use Twig\Error\SyntaxError;

class MyEventsDashboardController
{
	private Adapter $calendarEventsModelAdapter;

	private Adapter $configAdapter;

	private Adapter $stringUtilAdapter;

	public function __construct(
		private readonly CalendarEventsUtil     $calendarEventsUtil,
		private readonly Connection             $connection,
		private readonly ContaoCsrfTokenManager $contaoCsrfTokenManager,
		private readonly ContaoFramework        $framework,
		private readonly RequestStack           $requestStack,
		private readonly RouterInterface        $router,
		private readonly Security               $security,
		private readonly TranslatorInterface    $translator,
		private readonly Twig                   $twig,
		private readonly UriSigner              $uriSigner,
		private readonly UrlParser              $urlParser,
	)
	{
		// Adapters
		$this->calendarEventsModelAdapter = $this->framework->getAdapter(CalendarEventsModel::class);
		$this->configAdapter = $this->framework->getAdapter(Config::class);
		$this->stringUtilAdapter = $this->framework->getAdapter(StringUtil::class);
	}

	/**
	 * @throws Exception
	 * @throws LoaderError
	 * @throws RuntimeError
	 * @throws SyntaxError
	 */
	public function generate(): Response
	{
		$html = '';

		/** @var BackendUser $user */
		$user = $this->security->getUser();

		$limit = (int)$this->requestStack->getCurrentRequest()->query->get('pastEventsLimit', 10);

		if ($user instanceof BackendUser) {
			$upcomingEvents = $this->getUpcomingEvents($user);
			$pastEvents = $this->getPastEvents($user);
			$pastEventsCount = \count($pastEvents);
			$pastEvents = \array_slice($pastEvents, 0, $limit);

			$events = array_merge(
				[['separator' => 'upcoming-events']],
				$this->prepareForTwig($upcomingEvents, 'upcoming-event'),
				[['separator' => 'past-events']],
				$this->prepareForTwig($pastEvents, 'past-event'),
			);

			$html = $this->twig->render('@MarkocupicSacEventTool/Backend/MyEventsDashboard/my_events_dashboard.html.twig', [
				'events'                           => $events,
				'has_upcoming_events'              => !empty($upcomingEvents),
				'has_past_events'                  => !empty($pastEvents),
				'has_load_more_past_events_button' => $pastEventsCount > $limit,
				'load_more_past_events_url'        => $pastEventsCount > $limit ? $this->urlParser->addQueryString('pastEventsLimit=' . $limit + 10) : null,
			]);
		}

		return new Response($html);
	}

	public function getOperationData(CalendarEventsModel $event): array
	{
		return [
			'be-dashb-op--event-preview'           => $this->getEventPreviewOperation($event),
			'be-dashb-op--event-listing'           => $this->getEventListingOperation($event),
			'be-dashb-op--event-registration-list' => $this->getEventRegistrationListOperation($event),
			'be-dashb-op--send-email'              => $this->getSendEmailOperation($event),
			'be-dashb-op--create-or-edit-report'   => $this->getCreateOrEditReportOperation($event),
			'be-dashb-op--print-report'            => $this->getPrintReportOperation($event),
		];
	}

	/**
	 * @throws Exception
	 */
	private function getUpcomingEvents(BackendUser $user): array
	{
		$timeCut = time() - 15 * 24 * 3600; // 14 + 1 days

		$arrAllowedCalIds = $this->getAllowedCalendarIds();
		$arrAllowedCalIds = empty($arrAllowedCalIds) ? [0] : $arrAllowedCalIds;

		$result = $this->connection->executeQuery(
			'SELECT * FROM tl_calendar_events AS t1 WHERE pid IN(' . implode(',', $arrAllowedCalIds) . ') AND (t1.registrationGoesTo = ? OR t1.id IN (SELECT t2.pid FROM tl_calendar_events_instructor AS t2 WHERE t2.userId = ?)) AND t1.startDate > ? ORDER BY t1.startDate',
			[
				$user->id,
				$user->id,
				$timeCut,
			],
		);

		return $result->fetchAllAssociative();
	}

	/**
	 * @throws Exception
	 */
	private function getPastEvents(BackendUser $user): array
	{
		$timeCut = time() - 15 * 24 * 3600; // 14 + 1 days

		$arrAllowedCalIds = $this->getAllowedCalendarIds();
		$arrAllowedCalIds = empty($arrAllowedCalIds) ? [0] : $arrAllowedCalIds;

		$result = $this->connection->executeQuery(
			'SELECT * FROM tl_calendar_events AS t1 WHERE pid IN (' . implode(',', $arrAllowedCalIds) . ') AND (t1.registrationGoesTo = ? OR t1.id IN (SELECT t2.pid FROM tl_calendar_events_instructor AS t2 WHERE t2.userId = ?)) AND t1.startDate <= ? ORDER BY t1.startDate DESC',
			[
				$user->id,
				$user->id,
				$timeCut,
			],
			[
				Types::INTEGER,
				Types::INTEGER,
				Types::INTEGER,
			],
		);

		return $result->fetchAllAssociative();
	}

	/**
	 * @throws Exception
	 * @throws \Exception
	 */
	private function prepareForTwig(array $arrEvents, string $rowClass): array
	{
		$events = [];

		foreach ($arrEvents as $row) {
			$eventModel = $this->calendarEventsModelAdapter->findById($row['id']);
			$title = $this->stringUtilAdapter->decodeEntities($eventModel->title);
			$title = $this->stringUtilAdapter->restoreBasicEntities($title);

			$event = [];
			$event['href_event'] = $this->router->generate('contao_backend', [
				'do'    => 'calendar',
				'table' => 'tl_calendar_events',
				'id'    => $eventModel->id,
				'act'   => 'edit',
				'rt'    => $this->contaoCsrfTokenManager->getDefaultTokenValue(),
				'ref'   => $this->requestStack->getCurrentRequest()->attributes->get('_contao_referer_id'),
			]);

			$event['row_class'] = $rowClass;
			$event['badge'] = $this->calendarEventsUtil->getSubscriptionStateBadges($eventModel);
			$event['title'] = $title;
			$event['date'] = date($this->configAdapter->get('dateFormat'), (int)$eventModel->startDate);
			$event['state_icon'] = $this->calendarEventsUtil->getEventStateIcon($eventModel);
			$event['release_level'] = $this->calendarEventsUtil->getEventReleaseLevelAsString($eventModel);
			$event['has_filled_in_tour_report'] = $eventModel->filledInEventReportForm;

			// Get operations
			$event['operations'] = $this->getOperationData($eventModel);

			$events[] = $event;
		}

		return $events;
	}

	private function getEventPreviewOperation(CalendarEventsModel $event): array
	{
		$operation = [];
		$operation['icon_class'] = 'fa-solid fa-fw fa-presentation-screen';

		$operation['href'] = $this->router->generate('contao_backend', [
			'do'    => 'calendar',
			'table' => 'tl_calendar_events',
			'id'    => $event->pid,
			'rt'    => $this->contaoCsrfTokenManager->getDefaultTokenValue(),
			'ref'   => $this->requestStack->getCurrentRequest()->attributes->get('_contao_referer_id'),
		]);
		$operation['title'] = $this->translator->trans('MSC.bhs_dashb_livePreview', [], 'contao_default');
		$operation['link_attributes'] = [
			'data-turbo' => 'false',
			'rel'    => 'noopener',
			'target' => '_blank',
		];
		$operation['label'] = 'Vorschau';
		$operation['primary'] = false;

		return $operation;
	}

	private function getEventListingOperation(CalendarEventsModel $event): array
	{
		$operation = [];
		$operation['icon_class'] = 'fa-solid fa-fw fa-list-tree';

		$operation['href'] = $this->router->generate('contao_backend', [
			'do'    => 'calendar',
			'table' => 'tl_calendar_events',
			'id'    => $event->pid,
			'rt'    => $this->contaoCsrfTokenManager->getDefaultTokenValue(),
			'ref'   => $this->requestStack->getCurrentRequest()->attributes->get('_contao_referer_id'),
		]);
		$operation['title'] = $this->translator->trans('MSC.bhs_dashb_registrationList', [], 'contao_default');
		$operation['link_attributes'] = [
			'data-turbo' => 'false',
		];
		$operation['label'] = 'Event Liste';
		$operation['primary'] = false;

		return $operation;
	}

	private function getEventRegistrationListOperation(CalendarEventsModel $event): array
	{

		$operation = [];
		$operation['icon_class'] = 'fa-solid fa-fw fa-people-group';

		$operation['href'] = $this->router->generate('contao_backend', [
			'do'    => 'calendar',
			'table' => 'tl_calendar_events_member',
			'id'    => $event->id,
			'rt'    => $this->contaoCsrfTokenManager->getDefaultTokenValue(),
			'ref'   => $this->requestStack->getCurrentRequest()->attributes->get('_contao_referer_id'),
		]);
		$operation['title'] = $this->translator->trans('MSC.bhs_dashb_registrationList', [], 'contao_default');
		$operation['link_attributes'] = [
			'data-turbo' => 'false',
		];
		$operation['label'] = 'Anmeldungen';
		$operation['primary'] = false;

		return $operation;
	}


	/**
	 * @throws Exception
	 */
	private function getSendEmailOperation(CalendarEventsModel $event): array
	{

		$operation = [];
		$operation['icon_class'] = 'fa-solid fa-fw fa-at';

		$regId = $this->connection->fetchOne('SELECT id FROM tl_calendar_events_member WHERE eventId = ?', [$event->id]);

		if ($regId) {
			$url = System::getContainer()->get('router')->generate(EventParticipantEmailController::class);

			$url = $this->urlParser->addQueryString('eventId=' . $event->id, $url);
			$url = $this->urlParser->addQueryString('rt=' . $this->contaoCsrfTokenManager->getDefaultTokenValue(), $url);
			$url = $this->urlParser->addQueryString('sid=' . uniqid(), $url);

			$operation['href'] = $this->uriSigner->sign($url);
			$operation['link_attributes'] = [
				'data-turbo' => 'false',
			];
		}

		$operation['title'] = !empty($operation['href']) ? $this->translator->trans('MSC.bhs_dashb_sendEmail', [], 'contao_default') : $this->translator->trans('MSC.bhs_dashb_sendEmailDisabled', [], 'contao_default');
		$operation['label'] = 'E-Mails senden';
		$operation['primary'] = false;

		return $operation;
	}

	private function getCreateOrEditReportOperation(CalendarEventsModel $event): array
	{
		$operation = [];
		$class = 'fa-solid fa-fw fa-comment-pen';
		$operation['icon_class'] = $class . ($event->filledInEventReportForm ? ' filter-green' : ' filter-red');

		if (EventType::TOUR === $event->eventType || EventType::LAST_MINUTE_TOUR === $event->eventType) {
			$operation['href'] = $this->router->generate('contao_backend', [
				'do'    => 'calendar',
				'table' => 'tl_calendar_events',
				'act'   => 'edit',
				'call'  => 'writeTourReport',
				'id'    => $event->id,
				'rt'    => $this->contaoCsrfTokenManager->getDefaultTokenValue(),
				'ref'   => $this->requestStack->getCurrentRequest()->attributes->get('_contao_referer_id'),
			]);
			$operation['link_attributes'] = [
				'data-turbo' => 'false',
			];
		}

		if (!empty($operation['href'])) {
			if ($event->filledInEventReportForm) {
				$operation['title'] = $this->translator->trans('MSC.bhs_dashb_editReport', [], 'contao_default');
			} else {
				$operation['title'] = $this->translator->trans('MSC.bhs_dashb_createReport', [], 'contao_default');
			}
		} else {
			$operation['title'] = $this->translator->trans('MSC.bhs_dashb_createReportDisabled', [], 'contao_default');
		}

		$operation['label'] = $event->filledInEventReportForm ? 'Tourrapport bearbeiten' : 'Tour-Rapport erfassen';
		$operation['primary'] = true;

		return $operation;
	}

	private function getPrintReportOperation(CalendarEventsModel $event): array
	{
		$operation = [];
		$operation['icon_class'] = 'fa-solid fa-fw fa-print';

		if (EventType::TOUR === $event->eventType || EventType::LAST_MINUTE_TOUR === $event->eventType) {
			$operation['href'] = $this->router->generate('contao_backend', [
				'do'    => 'calendar',
				'table' => 'tl_calendar_events_instructor_invoice',
				'id'    => $event->id,
				'rt'    => $this->contaoCsrfTokenManager->getDefaultTokenValue(),
				'ref'   => $this->requestStack->getCurrentRequest()->attributes->get('_contao_referer_id'),
			]);
			$operation['link_attributes'] = [
				'data-turbo' => 'false',
			];
		}

		$operation['title'] = !empty($operation['href']) ? $this->translator->trans('MSC.bhs_dashb_printReport', [], 'contao_default') : $this->translator->trans('MSC.bhs_dashb_printReportDisabled', [], 'contao_default');
		$operation['label'] = 'Rapporte drucken & einreichen';
		$operation['primary'] = false;

		return $operation;
	}

	/**
	 * @return array<int>
	 *
	 * @throws Exception
	 */
	private function getAllowedCalendarContainerIds(): array
	{
		/** @var BackendUser $user */
		$user = $this->security->getUser();

		if ($this->security->isGranted('ROLE_ADMIN')) {
			$arrIds = $this->connection->fetchFirstColumn('SELECT id FROM tl_calendar_container');
		} else {
			$arrIds = $user->calendar_containers;

			if (!\is_array($arrIds) || empty($arrIds)) {
				$arrIds = [];
			}
		}

		return array_map('\intval', $arrIds);
	}

	/**
	 * @return array<int>
	 *
	 * @throws Exception
	 */
	private function getAllowedCalendarIds(): array
	{
		$arrCalContainerIds = $this->getAllowedCalendarContainerIds();

		/** @var BackendUser $user */
		$user = $this->security->getUser();

		if ($this->security->isGranted('ROLE_ADMIN')) {
			$arrCalendarIds = $this->connection->fetchFirstColumn('SELECT id FROM tl_calendar');
		} else {
			$arrCalendarIds = $user->calendars;

			if (!\is_array($arrCalendarIds) || empty($arrCalendarIds)) {
				$arrCalendarIds = [];
			}
		}

		$arrAllowed = [];

		foreach ($arrCalendarIds as $calId) {
			$pid = $this->connection->fetchOne('SELECT pid FROM tl_calendar WHERE id = ?', [$calId]);

			if (false !== $pid) {
				if (\in_array($pid, $arrCalContainerIds, true)) {
					$arrAllowed[] = $calId;
				}
			}
		}

		return array_map('\intval', $arrAllowed);
	}
}
