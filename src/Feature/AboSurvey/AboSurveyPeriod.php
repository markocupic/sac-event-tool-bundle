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
 * The survey form is open from 00:00 of the start date until 23:59:59 of the end date
 * (tl_content.aboSurveyStartDate and tl_content.aboSurveyEndDate).
 */
final readonly class AboSurveyPeriod
{
    public const string STATE_NOT_STARTED = 'not_started';

    public const string STATE_OPEN = 'open';

    public const string STATE_CLOSED = 'closed';

    public static function getState(int $startDate, int $endDate, int $now): string
    {
        if ($now < self::getStartOfDay($startDate)) {
            return self::STATE_NOT_STARTED;
        }

        if ($now > self::getEndOfDay($endDate)) {
            return self::STATE_CLOSED;
        }

        return self::STATE_OPEN;
    }

    public static function getStartOfDay(int $timestamp): int
    {
        return (new \DateTimeImmutable())
            ->setTimestamp($timestamp)
            ->setTime(0, 0)
            ->getTimestamp()
        ;
    }

    public static function getEndOfDay(int $timestamp): int
    {
        return (new \DateTimeImmutable())
            ->setTimestamp($timestamp)
            ->setTime(23, 59, 59)
            ->getTimestamp()
        ;
    }
}
