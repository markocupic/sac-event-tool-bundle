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

use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\StringUtil;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;

class TwigStringUtilManager extends AbstractExtension
{
    public function __construct(
        private readonly ContaoFramework $framework,
    ) {
    }

    public function getFilters(): array
    {
        return [
            new TwigFilter('revert_input_encoding', [$this, 'revertInputEncoding']),
        ];
    }

    public function revertInputEncoding(string $str): string
    {
        $stringUtil = $this->framework->getAdapter(StringUtil::class);

        return $stringUtil->revertInputEncoding($str);
    }
}
