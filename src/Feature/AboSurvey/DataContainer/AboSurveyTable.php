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
use Markocupic\SacEventToolBundle\Feature\AboSurvey\AboSurveyAboType;
use Markocupic\SacEventToolBundle\Feature\AboSurvey\AboSurveyCsvExport;
use Markocupic\SacEventToolBundle\Feature\AboSurvey\AboSurveyRepository;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Back end module "abo_survey": CSV export (global operation "exportCsv").
 */
class AboSurveyTable
{
    public const string BACKEND_MODULE_TYPE = 'abo_survey';

    public const string ACTION_EXPORT_CSV = 'exportAboSurveyCsv';

    public function __construct(
        private readonly AboSurveyCsvExport $aboSurveyCsvExport,
        private readonly RequestStack $requestStack,
        private readonly Security $security,
    ) {
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

        $filter = $this->getListFilter($request);

        throw new ResponseException($this->aboSurveyCsvExport->generate($filter['year'], $filter['aboType']));
    }

    /**
     * The year and abo filter the user has set in the list view (panel "filter").
     *
     * @return array{year: int|null, aboType: string|null}
     */
    private function getListFilter(Request $request): array
    {
        $filter = [];

        if ($request->hasSession()) {
            $filter = $request->getSession()->getBag('contao_backend')->get('filter')[AboSurveyRepository::TABLE] ?? [];
        }

        $year = (int) ($filter['year'] ?? 0);
        $aboType = (string) ($filter['aboType'] ?? '');

        return [
            'year' => $year > 0 ? $year : null,
            'aboType' => \in_array($aboType, AboSurveyAboType::ALL, true) ? $aboType : null,
        ];
    }
}
