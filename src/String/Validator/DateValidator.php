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

final class DateValidator
{
    public static function isValidDate(mixed $value, string $format): bool
    {
        if (!\is_string($value)) {
            return false;
        }

        $dt = \DateTime::createFromFormat($format, $value);

        if (false === $dt) {
            return false;
        }

        $errors = \DateTime::getLastErrors();

        if (false === $errors) {
            return true;
        }

        return 0 === $errors['warning_count'] && 0 === $errors['error_count'];
    }

    public static function isValidTimestamp(mixed $value): bool
    {
        if (\is_int($value)) {
            return true;
        }

        if (!\is_string($value)) {
            return false;
        }

        // Accept integer strings only:
        // no floats, no exponential notation, no leading zeros,
        // no surrounding whitespace and no integer overflow.
        return (string) (int) $value === $value;
    }
}
