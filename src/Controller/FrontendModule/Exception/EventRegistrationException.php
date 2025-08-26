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

namespace Markocupic\SacEventToolBundle\Controller\FrontendModule\Exception;

class EventRegistrationException extends \RuntimeException
{
    public const string LEVEL_INFO = 'TL_INFO';

    public const string LEVEL_ERROR = 'TL_ERROR';

    public function __construct(
        private readonly string $reason,
        private readonly string $errorLevel,
        private readonly string $translatableText,
        private readonly array $params = [],
    ) {
        if (!\in_array($errorLevel, [self::LEVEL_INFO, self::LEVEL_ERROR], true)) {
            throw new \InvalidArgumentException(\sprintf('Invalid error level "%s". Error type must be one of these: %s.', $errorLevel, implode(', ', [self::LEVEL_INFO, self::LEVEL_ERROR])));
        }

        parent::__construct($reason);
    }

    public function getReason(): string
    {
        return $this->reason;
    }

    public function getErrorLevel(): string
    {
        return $this->errorLevel;
    }

    public function getTranslatableText(): string
    {
        return $this->translatableText;
    }

    public function getParams(): array
    {
        return $this->params;
    }
}
