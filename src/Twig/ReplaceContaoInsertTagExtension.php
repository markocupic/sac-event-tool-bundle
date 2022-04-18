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

use Contao\CoreBundle\InsertTag\InsertTagParser;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class ReplaceContaoInsertTagExtension extends AbstractExtension
{
    private InsertTagParser $insertTagParser;

    public function __construct(InsertTagParser $insertTagParser)
    {
        $this->insertTagParser = $insertTagParser;
    }

    public function getFunctions()
    {
        return [
            new TwigFunction('replace_contao_insert_tag', [$this, 'replaceContaoInsertTag']),
        ];
    }

    public function replaceContaoInsertTag(string $strInsertTag): string
    {
        return $this->insertTagParser->replaceInline($strInsertTag);
    }
}
