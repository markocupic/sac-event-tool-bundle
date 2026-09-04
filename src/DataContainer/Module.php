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

namespace Markocupic\SacEventToolBundle\DataContainer;

use Contao\Controller;
use Contao\CoreBundle\DependencyInjection\Attribute\AsCallback;
use Contao\CoreBundle\Twig\Finder\FinderFactory;
use Contao\System;

class Module
{
    public function __construct(private readonly FinderFactory $finderFactory)
    {
    }

    #[AsCallback(table: 'tl_module', target: 'fields.eventFilterBoardFields.options', priority: 100)]
    public function getEventFilterBoardFields()
    {
        $opt = [];

        Controller::loadDataContainer('tl_event_filter_form');
        System::loadLanguageFile('tl_event_filter_form');

        foreach (array_keys($GLOBALS['TL_DCA']['tl_event_filter_form']['fields']) as $k) {
            $opt[$k] = $GLOBALS['TL_LANG']['tl_event_filter_form'][$k][0] ?? $k;
        }

        return $opt;
    }

    /**
     * Return all templates as array.
     */
    #[AsCallback(table: 'tl_module', target: 'fields.eventListPartialTpl.options', priority: 100)]
    public function getEventListTemplates()
    {
        $finder = $this->finderFactory->create();

        // Find all templates
        $opt = $finder->asTemplateOptions();

        // Filter templates
        return array_filter($opt, static fn ($key) => preg_match('/^frontend_module_partials\/event_list\/(tour|course)(.*)$/', $key), ARRAY_FILTER_USE_KEY);
    }
}
