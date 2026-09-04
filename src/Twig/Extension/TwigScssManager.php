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

use ScssPhp\ScssPhp\Compiler;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;

class TwigScssManager extends AbstractExtension
{
    public function __construct(private readonly CacheInterface $cache)
    {
    }

    public function getFilters(): array
    {
        return [
            new TwigFilter('scss', [$this, 'scssParser'], ['is_safe' => ['html']]),
        ];
    }

    public function scssParser(string $str): string
    {
        $cacheKey = 'scss_'.md5($str);

        return $this->cache->get(
            $cacheKey,
            static function (ItemInterface $item) use ($str): string {
                // Cache indefinitely since the compiled CSS only changes if the input changes
                $item->expiresAfter(null);

                $compiler = new Compiler();

                return $compiler->compileString($str)->getCss();
            },
        );
    }
}
