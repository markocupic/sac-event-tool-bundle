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

namespace Markocupic\SacEventToolBundle\Feature\EventStats\Controller;

use Contao\CoreBundle\Controller\AbstractBackendController;
use Contao\CoreBundle\Exception\AccessDeniedException;
use Contao\CoreBundle\Security\ContaoCorePermissions;
use Markocupic\SacEventToolBundle\Config\EventExecutionState;
use Markocupic\SacEventToolBundle\Config\EventMountainGuide;
use Markocupic\SacEventToolBundle\Config\EventState;
use Markocupic\SacEventToolBundle\Config\EventSubscriptionState;
use Markocupic\SacEventToolBundle\Config\EventType;
use Markocupic\SacEventToolBundle\Feature\EventStats\EventStatsQuery;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Backend page with the event statistics of the two previous, the current and the next year.
 */
#[Route('/%contao.backend.route_prefix%/sac_pilatus_event_stats', name: self::class, defaults: ['_scope' => 'backend'])]
class EventStatsController extends AbstractBackendController
{
    public const string BACKEND_MODULE_TYPE = 'sac_pilatus_event_stats';

    public const string BACKEND_MODULE_CATEGORY = 'sac_be_modules';

    private const array PARTICIPANT_LABELS = [
        'total' => 'Total',
        'gender_female' => 'Teiln. weiblich',
        'gender_male' => 'Teiln. männlich',
        'gender_other' => 'Teiln. divers/keine Angabe',
        'age_0_20' => 'Alter: 0-20',
        'age_21_30' => 'Alter: 21-30',
        'age_31_40' => 'Alter: 31-40',
        'age_41_60' => 'Alter: 41-60',
        'age_61_80' => 'Alter: 61-80',
        'age_81_plus' => 'Alter: 81+',
        'age_undefined' => 'Alter: unbekannt',
    ];

    public function __construct(
        private readonly EventStatsQuery $query,
        private readonly Security $security,
        private readonly TranslatorInterface $translator,
    ) {
    }

    public function __invoke(): Response
    {
        if (!$this->security->isGranted('ROLE_ADMIN') && !$this->security->isGranted(ContaoCorePermissions::USER_CAN_ACCESS_MODULE, self::BACKEND_MODULE_TYPE)) {
            throw new AccessDeniedException('Access denied');
        }

        $currentYear = (int) date('Y');
        $years = [$currentYear - 2, $currentYear - 1, $currentYear, $currentYear + 1];

        return $this->render('@MarkocupicSacEventTool/EventStats/be_event_stats.html.twig', [
            'headline' => $this->translator->trans('MOD.'.self::BACKEND_MODULE_TYPE.'.0', [], 'contao_modules'),
            'years' => $years,
            'sections' => [
                $this->getEventSection($years, 'Ausgeschriebene Touren (ab FS3)', EventType::TOUR, ['Anm: Es werden keine Last Minute Touren berücksichtigt.']),
                $this->getEventSection($years, 'Ausgeschriebene Kurse (ab FS3)', EventType::COURSE),
                $this->getOrganizerSection($years),
                $this->getInstructorSection($years),
                $this->getRegistrationSection($years),
                $this->getParticipantSection($years),
                $this->getTourStateSection($years),
            ],
        ]);
    }

    private function getEventSection(array $years, string $headline, string $eventType, array $notes = []): array
    {
        $types = [$eventType];

        return [
            'headline' => $headline,
            'rows' => [
                ['label' => 'Total', 'values' => $this->query->countEvents($years, $types)],
                ['label' => 'Mit Bergführer Angebot', 'values' => $this->query->countEvents($years, $types, EventMountainGuide::WITH_MOUNTAIN_GUIDE_OFFER)],
                ['label' => 'Mit Bergführer', 'values' => $this->query->countEvents($years, $types, EventMountainGuide::WITH_MOUNTAIN_GUIDE)],
                ['label' => 'Ohne Bergführer', 'values' => $this->query->countEvents($years, $types, EventMountainGuide::NO_MOUNTAIN_GUIDE)],
            ],
            'notes' => $notes,
        ];
    }

    private function getOrganizerSection(array $years): array
    {
        $rows = [
            ['label' => 'Total Events', 'values' => $this->query->countEvents($years, EventStatsQuery::TOURS_AND_COURSES)],
        ];

        foreach ($this->query->getOrganizers() as $organizer) {
            $rows[] = ['label' => $organizer['title'], 'values' => $this->query->countEvents($years, EventStatsQuery::TOURS_AND_COURSES, null, (int) $organizer['id'])];
        }

        return [
            'headline' => 'Ausgeschriebene Events geordnet nach Gruppe (ab FS3)',
            'rows' => $rows,
            'notes' => [
                'Anm: Ein Event kann mehreren Organisatoren/Gruppen zugeordnet werden.',
                'Anm: Es werden keine Last Minute Touren und Veranstaltungen berücksichtigt.',
            ],
        ];
    }

    private function getInstructorSection(array $years): array
    {
        $instructors = $this->query->countInstructors($years);

        return [
            'headline' => 'Leitende aus ausgeschriebenen Events (ab FS3)',
            'rows' => [
                ['label' => 'Anzahl Leitende', 'values' => $instructors['all']],
                ['label' => 'Anzahl Nicht-Bergführer*', 'values' => $instructors['other']],
                ['label' => 'Anzahl Bergführer**', 'values' => $instructors['mountain_guide']],
            ],
            'notes' => [
                'Anm: Es werden keine Last Minute Touren und Veranstaltungen berücksichtigt.',
                '* Leiter darf nicht Bergf. sein.',
                '** Leiter muss Bergf. sein.',
            ],
        ];
    }

    private function getRegistrationSection(array $years): array
    {
        $rows = [
            ['label' => 'Anmeldungen Total', 'values' => $this->query->countRegistrations($years)],
        ];

        foreach (EventSubscriptionState::ALL as $state) {
            $rows[] = ['label' => $this->translator->trans('MSC.'.$state, [], 'contao_default'), 'values' => $this->query->countRegistrations($years, $state)];
        }

        return [
            'headline' => 'Anmeldungen von Teilnehmenden aus ausgeschriebenen Events (ab FS3)',
            'rows' => $rows,
            'notes' => ['Anm: Es werden keine Last Minute Touren und Veranstaltungen berücksichtigt.'],
        ];
    }

    private function getParticipantSection(array $years): array
    {
        $rows = [];

        foreach ($this->query->getParticipantDistribution($years) as $key => $values) {
            $rows[] = ['label' => self::PARTICIPANT_LABELS[$key], 'values' => $values];
        }

        return [
            'headline' => 'Bestätigte Teilnehmende aus ausgeschriebenen Events (ab FS4)',
            'rows' => $rows,
            'notes' => ['Anm: Es werden keine Last Minute Touren und Veranstaltungen berücksichtigt.'],
        ];
    }

    private function getTourStateSection(array $years): array
    {
        return [
            'headline' => 'Event-Status aus ausgeschriebenen Touren (ab FS3)',
            'rows' => [
                ['label' => 'Events stattgefunden', 'values' => $this->query->countTours($years, eventState: '', withTourReport: true)],
                ['label' => 'Events ausgebucht', 'values' => $this->query->countTours($years, eventState: EventState::STATE_FULLY_BOOKED)],
                ['label' => 'Events verschoben', 'values' => $this->query->countTours($years, eventState: EventState::STATE_RESCHEDULED)],
                ['label' => 'Events abgesagt', 'values' => $this->query->countTours($years, eventState: EventState::STATE_CANCELED)],
                ['label' => 'Unbekannt (kein Tourrapport vorhanden)', 'values' => $this->query->countTours($years, eventState: '', executionState: '')],
                ['label' => 'Tour wie ausgeschrieben durchgeführt: Ja', 'values' => $this->query->countTours($years, executionState: EventExecutionState::STATE_EXECUTED_LIKE_PREDICTED)],
                ['label' => 'Tour wie ausgeschrieben durchgeführt: Nein', 'values' => $this->query->countTours($years, executionState: EventExecutionState::STATE_NOT_EXECUTED_LIKE_PREDICTED)],
                ['label' => 'Tour wie ausgeschrieben durchgeführt: Unbekannt', 'values' => $this->query->countTours($years, executionState: '')],
            ],
            'notes' => ['Anm: Es werden keine Kurse, Last Minute Touren und allg. Veranstaltungen berücksichtigt.'],
        ];
    }
}
