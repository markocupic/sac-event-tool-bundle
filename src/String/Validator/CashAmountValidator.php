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

namespace Markocupic\SacEventToolBundle\String\Validator;

final class CashAmountValidator
{
    /**
     * Validates if the given input is a valid positive money amount.
     *
     * Valid values:
     * - 0, 123, 12345 (Integers)
     * - 12.4, 123.45 (Decimals with 1 or 2 digits)
     *
     * Invalid values:
     * - -123, -12.45 (Negative numbers)
     * - 0123 (Leading zeros)
     * - 12.345 (More than 2 decimal places)
     * - 'abc' (Non-numeric strings)
     */
    public static function isPositiveCashAmount(float|int|string $amount): bool
    {
        return 1 === preg_match('/^(?:0|[1-9]\d*)(?:\.\d{1,2})?$/', (string) $amount);
    }
}
