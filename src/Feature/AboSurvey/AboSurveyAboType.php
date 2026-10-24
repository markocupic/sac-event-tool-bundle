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

namespace Markocupic\SacEventToolBundle\Feature\AboSurvey;

/**
 * The abos a member can choose in the abo survey (tl_abo_survey.aboType).
 * The labels are in contao/languages/<lang>/tl_abo_survey.php.
 */
final class AboSurveyAboType
{
    public const string WHITE_RISK = 'whiteRisk';

    public const string SCHWEIZ_MOBIL = 'schweizMobil';

    public const array ALL = [
        self::WHITE_RISK,
        self::SCHWEIZ_MOBIL,
    ];
}
