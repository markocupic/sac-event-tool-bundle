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

use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class TwigAssetManager extends AbstractExtension
{
    public function getFunctions(): array
    {
        return [
            new TwigFunction('addCssResource', [$this, 'addCssResource']),
            new TwigFunction('addJavascriptResource', [$this, 'addJavascriptResource']),
            new TwigFunction('addMootoolsResource', [$this, 'addMootoolsResource']),
            new TwigFunction('addHtmlToBody', [$this, 'addHtmlToBody']),
            new TwigFunction('addHtmlToHead', [$this, 'addHtmlToHead']),
        ];
    }

    /**
     * Append CSS Resource from inside your Twig template.
     *
     * Inside your Twig template:
     * {% do addCssResource('bundles/foobar/css/my.css|static %}.
     *
     * @see: https://docs.contao.org/dev/framework/asset-management.
     */
    public function addCssResource(string $res): void
    {
        $GLOBALS['TL_CSS'][] = $res;
    }

    /**
     * Append Javascript Resource from inside your Twig template.
     *
     * Inside your Twig template:
     * {% do addJavascriptResource('bundles/foobar/js/my.js|static %}.
     *
     * @see: https://docs.contao.org/dev/framework/asset-management.
     */
    public function addJavascriptResource(string $res): void
    {
        $GLOBALS['TL_JAVASCRIPT'][] = $res;
    }

    /**
     * Include HTML before </body>.
     *
     * Inside your Twig template:
     * {% do addMootoolsResource('<script src="bundles/myextension/scripts.js"></script> %}.
     *
     * @see: https://docs.contao.org/dev/framework/asset-management.
     */
    public function addMootoolsResource(string $res): void
    {
        $GLOBALS['TL_MOOTOOLS'][] = $res;
    }

    /**
     * Include HTML before </body>.
     *
     * Inside your Twig template:
     * {% do addHtmlToBody('<script src="bundles/myextension/scripts.js"></script> %}.
     *
     * @see: https://docs.contao.org/dev/framework/asset-management.
     */
    public function addHtmlToBody(string $res): void
    {
        $GLOBALS['TL_BODY'][] = $res;
    }

    /**
     * Include HTML in the <head> of your document.
     *
     * Inside your Twig template:
     * {% do addHtmlToHead('<script src="bundles/myextension/scripts.js"></script> %}.
     *
     * @see: https://docs.contao.org/dev/framework/asset-management.
     */
    public function addHtmlToHead(string $res): void
    {
        $GLOBALS['TL_HEAD'][] = $res;
    }
}
