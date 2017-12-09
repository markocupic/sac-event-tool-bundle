<?php

/**
 * Contao Open Source CMS
 *
 * Copyright (c) 2005-2017 Leo Feyer
 *
 * @license LGPL-3.0+
 */

namespace Markocupic\SacEventToolBundle;

use Contao\CoreBundle\Exception\PageNotFoundException;
use Patchwork\Utf8;


/**
 * Front end module "event list".
 *
 * @property bool $cal_noSpan
 * @property string $cal_template
 * @property int $cal_limit
 * @property string $cal_order
 * @property array $cal_calendar
 * @property string $cal_format
 * @property bool $cal_ignoreDynamic
 * @property int $cal_readerModule
 * @property bool $cal_hideRunning
 *
 * @author Leo Feyer <https://github.com/leofeyer>
 */
class ModuleSacEventToolEventlist extends \ModuleEventlist
{

    /**
     * Current date object
     * @var Date
     */
    //protected $Date;

    /**
     * Template
     * @var string
     */
    protected $strTemplate = 'mod_eventToolCalendarEventlist';


    /**
     * Display a wildcard in the back end
     *
     * @return string
     */
    public function generate()
    {

        if (TL_MODE == 'BE')
        {
            /** @var BackendTemplate|object $objTemplate */
            $objTemplate = new \BackendTemplate('be_wildcard');
            /** Hacks Marko Cupic 09.12.2017 */
            $objTemplate->wildcard = '### ' . Utf8::strtoupper($GLOBALS['TL_LANG']['FMD']['eventToolCalendarEventlist'][0]) . ' ###';
            $objTemplate->title = $this->headline;
            $objTemplate->id = $this->id;
            $objTemplate->link = $this->name;
            $objTemplate->href = 'contao/main.php?do=themes&amp;table=tl_module&amp;act=edit&amp;id=' . $this->id;

            return $objTemplate->parse();
        }

        $this->cal_calendar = $this->sortOutProtected(\StringUtil::deserialize($this->cal_calendar, true));

        // Return if there are no calendars
        if (!is_array($this->cal_calendar) || empty($this->cal_calendar))
        {
            return '';
        }

        // Show the event reader if an item has been selected
        if ($this->cal_readerModule > 0 && (isset($_GET['events']) || (\Config::get('useAutoItem') && isset($_GET['auto_item']))))
        {
            return $this->getFrontendModule($this->cal_readerModule, $this->strColumn);
        }

        /** Hacks Marko Cupic 09.12.2017 */
        // Do not ignore the year parameter
        if (\Input::get('year') > 2000)
        {
            $this->cal_format = 'cal_year';
        }
        else
        {
            $this->cal_format = 'next_all';
        }

        return \Events::generate();
    }
}
