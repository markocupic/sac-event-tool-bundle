<?php

/**
 * Contao Open Source CMS
 *
 * Copyright (c) 2005-2017 Leo Feyer
 *
 * @license LGPL-3.0+
 */

namespace Markocupic\SacEventToolBundle;

use Contao\ContentElement;
use Contao\FilesModel;
use Contao\Database;
use Contao\BackendTemplate;
use Patchwork\Utf8;


/**
 * Class ContentUserPortraitList
 * @package Markocupic\SacEventToolBundle
 */
class ContentUserPortraitList extends ContentElement
{

    /**
     * Template
     * @var string
     */
    protected $strTemplate = 'ce_user_portrait_list';

    /**
     * Files model
     * @var FilesModel
     */
    protected $objFilesModel;


    /**
     * Return if the image does not exist
     *
     * @return string
     */
    public function generate()
    {

        if (TL_MODE == 'BE')
        {
            /** @var BackendTemplate|object $objTemplate */
            $objTemplate = new BackendTemplate('be_wildcard');

            $objTemplate->wildcard = '### ' . Utf8::strtoupper($GLOBALS['TL_LANG']['CTE']['userPortraitList'][0]) . ' ###';
            $objTemplate->title = $this->headline;
            $objTemplate->id = $this->id;
            $objTemplate->link = $this->name;
            $objTemplate->href = 'contao/main.php?do=themes&amp;table=tl_module&amp;act=edit&amp;id=' . $this->id;

            return $objTemplate->parse();
        }


        return parent::generate();
    }


    /**
     * Generate the content element
     */
    protected function compile()
    {
       //
    }
}
