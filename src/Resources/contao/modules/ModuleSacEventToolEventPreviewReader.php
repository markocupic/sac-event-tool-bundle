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

namespace Markocupic\SacEventToolBundle;

use Contao\BackendTemplate;
use Contao\CalendarEventsModel;
use Contao\Config;
use Contao\CoreBundle\Exception\PageNotFoundException;
use Contao\Environment;
use Contao\Input;
use Contao\Module;
use Contao\ModuleEventReader;
use Contao\PageModel;
use Contao\StringUtil;
use Contao\System;

class ModuleSacEventToolEventPreviewReader extends ModuleEventReader
{
    /**
     * Template.
     *
     * @var string
     */
    protected $strTemplate = 'mod_eventreader';

    /**
     * Display a token-protected preview of the edited event.
     * Use the compile method of \Contao\ModuleEventReader.
     */
    public function generate(): string
    {
        $request = System::getContainer()->get('request_stack')->getCurrentRequest();

        if ($request && System::getContainer()->get('contao.routing.scope_matcher')->isBackendRequest($request)) {
            $objTemplate = new BackendTemplate('be_wildcard');
            $objTemplate->wildcard = '### '.$GLOBALS['TL_LANG']['FMD']['eventToolCalendarEventPreviewReader'][0].' ###';
            $objTemplate->title = $this->headline;
            $objTemplate->id = $this->id;
            $objTemplate->link = $this->name;
            $objTemplate->href = StringUtil::specialcharsUrl(System::getContainer()->get('router')->generate('contao_backend', ['do' => 'themes', 'table' => 'tl_module', 'act' => 'edit', 'id' => $this->id]));

            return $objTemplate->parse();
        }

        // Set the item from the auto_item parameter
        if (!isset($_GET['events']) && Config::get('useAutoItem') && isset($_GET['auto_item'])) {
            Input::setGet('events', Input::get('auto_item'));
        }

        $blnShow = false;

        if ('' !== Input::get('events')) {
            $objEvent = CalendarEventsModel::findByIdOrAlias(Input::get('events'));

            if (null !== $objEvent) {
                if ($objEvent->eventToken === Input::get('eventToken')) {
                    $blnShow = true;
                }
            }
        }

        if (!$blnShow) {
            throw new PageNotFoundException('Page not found: '.Environment::get('uri'));
        }

        /** @var PageModel $objPage */
        global $objPage;

        $objPage->noSearch = 1;
        $objPage->cache = 0;

        // These settings must be made temporarily, otherwise
        // the parent::_compile() method will throw an error.
        $objEvent->source = 'default';
        $objEvent->published = true;
        $this->cal_calendar = [$objEvent->pid];

        return Module::generate();
    }
}
