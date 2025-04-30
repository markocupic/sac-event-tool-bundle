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

class EventDeregistrationException extends \RuntimeException
{
    public const array TYPE_MAP = [
        'error' => 'TL_ERROR',
        'confirm' => 'TL_CONFIRM',
        'info' => 'TL_INFO',
    ];

    public function __construct(
        private readonly string $reason,
        private readonly string $type,
        private readonly string $translatableText,
        private readonly array $params = [],
    ) {
        if (!\array_key_exists($type, self::TYPE_MAP)) {
            $error = sprintf('Invalid error level "%s". Error type must be one of these: %s.', $type, implode(', ', array_keys(self::TYPE_MAP)));

            throw new \InvalidArgumentException($error);
        }

        parent::__construct($reason);
    }

    public function getReason(): string
    {
        return $this->reason;
    }

    public function getType(): string
    {
        return $this->type;
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
