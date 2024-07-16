<?php

declare(strict_types=1);

/*
 * This file is part of SAC Event Tool Bundle.
 *
 * (c) Marko Cupic 2024 <m.cupic@gmx.ch>
 * @license GPL-3.0-or-later
 * For the full copyright and license information,
 * please view the LICENSE file that was distributed with this source code.
 * @link https://github.com/markocupic/sac-event-tool-bundle
 */

namespace Markocupic\SacEventToolBundle\Twig\Extension;

use Contao\CoreBundle\Csrf\ContaoCsrfTokenManager;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class TwigCsrfTokenManager extends AbstractExtension
{
    public function __construct(
        private readonly ContaoCsrfTokenManager $csrfTokenManager,
    ) {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('get_csrf_token', [$this, 'getCsrfToken']),
        ];
    }

    public function getCsrfToken(): string
    {
        return $this->csrfTokenManager->getDefaultTokenValue();
    }
}
