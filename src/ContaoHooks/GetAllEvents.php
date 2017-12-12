<?php

/**
 * SAC Event Tool Web Plugin for Contao
 * Copyright (c) 2008-2017 Marko Cupic
 * @package sac-event-tool-bundle
 * @author Marko Cupic m.cupic@gmx.ch, 2017
 * @link    https://sac-kurse.kletterkader.com
 */

namespace Markocupic\SacEventToolBundle\ContaoHooks;

use Contao\CoreBundle\Framework\ContaoFrameworkInterface;
use Contao\Module;
use Contao\Input;
use Contao\Date;

/**
 * Class GetAllEvents
 * @package Markocupic\SacEventToolBundle\ContaoHooks
 */
class GetAllEvents
{
    /**
     * @var ContaoFrameworkInterface
     */
    private $framework;


    /**
     * Constructor.
     *
     * @param ContaoFrameworkInterface $framework
     */
    public function __construct(ContaoFrameworkInterface $framework)
    {
        $this->framework = $framework;
    }


    /**
     * @param $arrEvents
     * @param $arrCalendars
     * @param $intStart
     * @param $intEnd
     * @param Module $objModule
     * @return mixed
     */
    public function getAllEvents($arrEvents, $arrCalendars, $intStart, $intEnd, Module $objModule)
    {

        return $arrEvents;

        // Disabled

        // Special handling for tour and course calendar
        // Do not ignore $_GET['year'] parameter if cal_format is set to 'cal_all'
        if ($objModule->cal_format === 'cal_all')
        {
            foreach ($arrEvents as $key_1 => $arrLevel_1)
            {
                foreach ($arrLevel_1 as $key_2 => $arrLevel_2)
                {
                    foreach ($arrLevel_2 as $key_3 => $arrEvent)
                    {
                        if (Input::get('year') > 2000)
                        {
                            if (strpos(Date::parse('Y', $arrEvent['startDate']), Input::get('year')) === false && strpos(Date::parse('Y', $arrEvent['endDate']), Input::get('year')) === false)
                            {
                                unset($arrEvents[$key_1][$key_2][$key_3]);
                            }
                        }
                        else
                        {
                            // Show upcoming events
                            if ((time() + 86400) > $arrEvent['endDate'])
                            {
                                unset($arrEvents[$key_1][$key_2][$key_3]);
                            }
                        }
                    }
                }
            }
        }

        return array_filter($arrEvents);
    }

}

