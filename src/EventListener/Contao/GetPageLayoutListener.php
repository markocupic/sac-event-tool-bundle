<?php

/**
 * SAC Event Tool Web Plugin for Contao
 * Copyright (c) 2008-2020 Marko Cupic
 * @package sac-event-tool-bundle
 * @author Marko Cupic m.cupic@gmx.ch, 2017-2020
 * @link https://github.com/markocupic/sac-event-tool-bundle
 */

declare(strict_types=1);

namespace Markocupic\SacEventToolBundle\EventListener\Contao;

use Contao\Automator;
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\FilesModel;
use Contao\LayoutModel;
use Contao\PageModel;
use Contao\PageRegular;
use Contao\StringUtil;
use Symfony\Component\HttpKernel\KernelInterface;

/**
 * Class GetPageLayoutListener
 * @package Markocupic\SacEventToolBundle\EventListener\Contao
 */
class GetPageLayoutListener
{
    /**
     * @var ContaoFramework
     */
    private $framework;

    /**
     * @var KernelInterface
     */
    private $kernel;

    /**
     * @var string
     */
    private $projectDir;

    /**
     * GetPageLayoutListener constructor.
     * @param ContaoFramework $framework
     * @param KernelInterface $kernel
     * @param string $projectDir
     */
    public function __construct(ContaoFramework $framework, KernelInterface $kernel, string $projectDir)
    {
        $this->framework = $framework;
        $this->kernel = $kernel;
        $this->projectDir = $projectDir;
    }

    /**
     * @param $objPage
     * @param $objLayout
     * @param $objPty
     */
    public function purgeScriptCacheInDebugMode(PageModel $objPage, LayoutModel $objLayout, PageRegular $objPty): void
    {
        $stringUtilAdapter = $this->framework->getAdapter(StringUtil::class);
        $filesModelAdapter = $this->framework->getAdapter(FilesModel::class);

        // Purge script cache in dev mode
        if ($this->kernel->isDebug())
        {
            $objAutomator = new Automator();
            $objAutomator->purgeScriptCache();
            if ($objLayout->external !== '')
            {
                $arrExternal = $stringUtilAdapter->deserialize($objLayout->external);
                if (!empty($arrExternal) && is_array($arrExternal))
                {
                    $objFile = $filesModelAdapter->findMultipleByUuids($arrExternal);
                    while ($objFile->next())
                    {
                        if (is_file($this->projectDir . '/' . $objFile->path))
                        {
                            touch($this->projectDir . '/' . $objFile->path);
                        }
                    }
                }
            }
        }
    }
}
