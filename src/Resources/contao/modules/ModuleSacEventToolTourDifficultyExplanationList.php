<?php

/**
 * SAC Event Tool Web Plugin for Contao
 * Copyright (c) 2008-2017 Marko Cupic
 * @package sac-event-tool-bundle
 * @author Marko Cupic m.cupic@gmx.ch, 2017-2018
 * @link https://sac-kurse.kletterkader.com
 */

namespace Markocupic\SacEventToolBundle;

use Contao\BackendTemplate;
use Contao\Module;
use Contao\System;
use Contao\TourDifficultyCategoryModel;
use Contao\TourDifficultyModel;
use Patchwork\Utf8;


/**
 * Class ModuleSacEventToolTourDifficultyExplanationList
 * @package Markocupic\SacEventToolBundle
 */
class ModuleSacEventToolTourDifficultyExplanationList extends Module
{

    /**
     * @var
     */
    protected $stories;

    /**
     * Template
     * @var string
     */
    protected $strTemplate = 'mod_event_tool_tour_explanation_list';


    /**
     * @return string
     */
    public function generate()
    {
        if (TL_MODE == 'BE')
        {
            /** @var BackendTemplate|object $objTemplate */
            $objTemplate = new BackendTemplate('be_wildcard');

            $objTemplate->wildcard = '### ' . Utf8::strtoupper($GLOBALS['TL_LANG']['FMD']['eventTourDifficultyExplanationList'][0]) . ' ###';
            $objTemplate->title = $this->headline;
            $objTemplate->id = $this->id;
            $objTemplate->link = $this->name;
            $objTemplate->href = 'contao/main.php?do=themes&amp;table=tl_module&amp;act=edit&amp;id=' . $this->id;

            return $objTemplate->parse();
        }


        return parent::generate();
    }


    /**
     * Generate the module
     */
    protected function compile()
    {

        // Get root dir
        // $rootDir = System::getContainer()->getParameter('kernel.project_dir');
        $arrDiff = array();
        $pid = 0;
        $options = array('order' => 'sorting DESC');
        $objDifficulty = TourDifficultyModel::findAll($options);

        if ($objDifficulty !== null)
        {
            while ($objDifficulty->next())
            {
                if($pid !== $objDifficulty->pid)
                {
                    $objDifficulty->catStart = true;
                    $objDifficulty->catTitle = TourDifficultyCategoryModel::findByPk($objDifficulty->pid)->title;
                }
                $pid = $objDifficulty->pid;
                $arrDiff[] = $objDifficulty->row();
            }
        }


        $this->Template->difficulties = $arrDiff;

    }
}
