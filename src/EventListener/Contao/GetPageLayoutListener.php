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

use Contao\Automator;
use Contao\CoreBundle\DependencyInjection\Attribute\AsHook;
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\FilesModel;
use Contao\LayoutModel;
use Contao\PageModel;
use Contao\PageRegular;
use Contao\StringUtil;
use Symfony\Component\HttpKernel\KernelInterface;

/**
 * Purge script cache in debug mode.
 */
#[AsHook('getPageLayout', priority: 100)]
class GetPageLayoutListener
{
    public function __construct(
        private readonly ContaoFramework $framework,
        private readonly KernelInterface $kernel,
        private readonly string $projectDir,
    ) {
    }

    public function __invoke(PageModel $objPage, LayoutModel $objLayout, PageRegular $objPty): void
    {
        $stringUtilAdapter = $this->framework->getAdapter(StringUtil::class);
        $filesModelAdapter = $this->framework->getAdapter(FilesModel::class);

        // Purge script cache in dev mode
        if ($this->kernel->isDebug()) {
            $objAutomator = new Automator();
            $objAutomator->purgeScriptCache();

            if ('' !== $objLayout->external) {
                $arrExternal = $stringUtilAdapter->deserialize($objLayout->external);

                if (!empty($arrExternal) && \is_array($arrExternal)) {
                    $objFile = $filesModelAdapter->findMultipleByUuids($arrExternal);

                    while ($objFile->next()) {
                        if (is_file($this->projectDir.'/'.$objFile->path)) {
                            touch($this->projectDir.'/'.$objFile->path);
                        }
                    }
                }
            }
        }
    }
}
