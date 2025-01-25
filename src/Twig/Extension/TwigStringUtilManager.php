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

namespace Markocupic\SacEventToolBundle\Twig\Extension;

use Contao\CoreBundle\Framework\Adapter;
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\StringUtil;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;
use Twig\TwigFunction;

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

    public function getFunctions(): array
    {
        return [
            new TwigFunction('substr', [$this, 'substr']),
        ];
    }

    public function revertInputEncoding(string $str): string
    {
        return $this->getStringUtil()->revertInputEncoding($str);
    }

    public function substr($strString, $intNumberOfChars, string $strEllipsis = ' …'): string
    {
        return $this->getStringUtil()->substr($strString, $intNumberOfChars, $strEllipsis);
    }

    private function getStringUtil(): Adapter
    {
        return $this->framework->getAdapter(StringUtil::class);
    }
}
