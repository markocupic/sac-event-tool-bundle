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

namespace Markocupic\SacEventToolBundle\Feature\AboSurvey\DataContainer;

use Contao\CoreBundle\DependencyInjection\Attribute\AsCallback;
use Contao\CoreBundle\Exception\AccessDeniedException;
use Contao\CoreBundle\Exception\ResponseException;
use Contao\CoreBundle\Security\ContaoCorePermissions;
use Contao\DataContainer;
use Markocupic\SacEventToolBundle\Feature\AboSurvey\AboSurveyCsvExport;
use Markocupic\SacEventToolBundle\Feature\AboSurvey\AboSurveyRepository;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Back end module "abo_survey": CSV export (global operation "exportCsv")
 * and the options of the field "eventOrganizer".
 */
class AboSurveyTable
{
    public const string BACKEND_MODULE_TYPE = 'abo_survey';

    public const string ACTION_EXPORT_CSV = 'exportAboSurveyCsv';

    public function __construct(
        private readonly AboSurveyCsvExport $aboSurveyCsvExport,
        private readonly AboSurveyRepository $aboSurveyRepository,
        private readonly RequestStack $requestStack,
        private readonly Security $security,
    ) {
    }

    /**
     * The titles of tl_event_organizer. A stored title that no longer exists
     * (group renamed or deleted) is kept as option, so it is not lost when saving.
     */
    #[AsCallback(table: AboSurveyRepository::TABLE, target: 'fields.eventOrganizer.options')]
    public function getEventOrganizerOptions(DataContainer|null $dc = null): array
    {
        $titles = $this->aboSurveyRepository->findEventOrganizerTitles();
        $current = (string) ($dc?->activeRecord?->eventOrganizer ?? '');

        if ('' !== $current && !\in_array($current, $titles, true)) {
            $titles[] = $current;
        }

        return array_combine($titles, $titles);
    }

    #[AsCallback(table: AboSurveyRepository::TABLE, target: 'config.onload')]
    public function exportCsv(): void
    {
        $request = $this->requestStack->getCurrentRequest();

        if (null === $request || self::ACTION_EXPORT_CSV !== $request->query->get('action')) {
            return;
        }

        if (!$this->security->isGranted(ContaoCorePermissions::USER_CAN_ACCESS_MODULE, self::BACKEND_MODULE_TYPE)) {
            throw new AccessDeniedException('Not enough permissions to export the abo survey.');
        }

        // Always all entries, the filters of the list view are not applied
        throw new ResponseException($this->aboSurveyCsvExport->generate());
    }
}
