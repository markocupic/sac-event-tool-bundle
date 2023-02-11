<?php

declare(strict_types=1);

/*
 * This file is part of SAC Event Tool Bundle.
 *
 * (c) Marko Cupic 2023 <m.cupic@gmx.ch>
 * @license GPL-3.0-or-later
 * For the full copyright and license information,
 * please view the LICENSE file that was distributed with this source code.
 * @link https://github.com/markocupic/sac-event-tool-bundle
 */

namespace Markocupic\SacEventToolBundle\EventListener\Contao;

use Contao\Controller;
use Contao\CoreBundle\DependencyInjection\Attribute\AsHook;
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\PageModel;

#[AsHook('replaceInsertTags', priority: 100)]
class ReplaceInsertTagsListener
{
    public function __construct(
        private readonly ContaoFramework $framework,
    ) {
    }

    public function __invoke(string $strTag): bool|string
    {
        // Set adapters
        $controllerAdapter = $this->framework->getAdapter(Controller::class);
        $pageModelAdapter = $this->framework->getAdapter(PageModel::class);

        // Trim whitespaces
        $strTag = '' !== $strTag ? trim($strTag) : $strTag;

        // Replace external link
        // {{external_link::http://google.ch::more}}
        if (str_contains($strTag, 'external_link')) {
            $elements = explode('::', $strTag);

            if (\is_array($elements) && \count($elements) > 1) {
                $href = $elements[1];
                $label = $href;

                if (isset($elements[2]) && '' !== $elements[2]) {
                    $label = $elements[2];
                }

                return sprintf('<a href="%s" target="_blank">%s</a>', $href, $label);
            }
        }

        // Redirect to an internal page
        // {{redirect::###pageIdOrAlias###::###params###}}
        // {{redirect::konto-aktivieren}}
        // {{redirect::some-page-alias::?foo=bar&var=bla}}
        if (str_contains($strTag, 'redirect')) {
            $elements = explode('::', $strTag);

            if (\is_array($elements) && \count($elements) > 1) {
                $params = '';

                if (isset($elements[2])) {
                    $params = $elements[2];
                }
                $objPage = $pageModelAdapter->findByIdOrAlias($elements[1]);

                if (null !== $objPage) {
                    $strLocation = sprintf('%s%s', $objPage->getFrontendUrl(), $params);
                    $controllerAdapter->redirect($strLocation);
                }
            }
        }

        return false;
    }
}
