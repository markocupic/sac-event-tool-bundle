<?php

declare(strict_types=1);

/*
 * This file is part of SAC Event Tool Bundle.
 *
 * (c) Marko Cupic 2022 <m.cupic@gmx.ch>
 * @license GPL-3.0-or-later
 * For the full copyright and license information,
 * please view the LICENSE file that was distributed with this source code.
 * @link https://github.com/markocupic/sac-event-tool-bundle
 */

namespace Markocupic\SacEventToolBundle\Twig;

use Contao\CoreBundle\Framework\ContaoFramework;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class TwigHelper extends AbstractExtension
{
    private ContaoFramework $framework;

    public function __construct(ContaoFramework $framework)
    {
        $this->framework = $framework;
        $this->framework->initialize(true);
    }

    public function getFunctions()
    {
        return [
            new TwigFunction('addCssToHead', [$this, 'addCssToHead']),
            new TwigFunction('addJsToHead', [$this, 'addJsToHead']),
        ];
    }

    public function addCssToHead(string $res): string
    {
        return '';
    }

    public function addJsToHead(string $res): string
    {
        $GLOBALS['TL_JAVASCRIPT'][] = $res;

        return '';
    }
}
