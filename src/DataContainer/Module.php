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
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\CoreBundle\Twig\Finder\FinderFactory;
use Contao\System;

readonly class Module
{
    public function __construct(
        private ContaoFramework $framework,
        private FinderFactory $finderFactory,
    ) {
    }

    /**
     * All fields of the event filter form.
     */
    #[AsCallback(table: 'tl_module', target: 'fields.eventFilterBoardFields.options', priority: 100)]
    public function getEventFilterBoardFields(): array
    {
        $this->framework->getAdapter(Controller::class)->loadDataContainer('tl_event_filter_form');
        $this->framework->getAdapter(System::class)->loadLanguageFile('tl_event_filter_form');

        $options = [];

        foreach (array_keys($GLOBALS['TL_DCA']['tl_event_filter_form']['fields']) as $fieldName) {
            $options[$fieldName] = $GLOBALS['TL_LANG']['tl_event_filter_form'][$fieldName][0] ?? $fieldName;
        }

        return $options;
    }

    /**
     * All tour and course templates in "frontend_module_partials/event_list".
     */
    #[AsCallback(table: 'tl_module', target: 'fields.eventListPartialTpl.options', priority: 100)]
    public function getEventListTemplates(): array
    {
        $templates = $this->finderFactory->create()->asTemplateOptions();

        return array_filter(
            $templates,
            static fn ($name): bool => 1 === preg_match('#^frontend_module_partials/event_list/(tour|course)#', (string) $name),
            ARRAY_FILTER_USE_KEY,
        );
    }
}
