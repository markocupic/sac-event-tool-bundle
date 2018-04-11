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


/**
 * Class ContentCabanneSacDetail
 * @package Markocupic\SacEventToolBundle
 */
class ContentCabanneSacDetail extends ContentElement
{

    /**
     * Template
     * @var string
     */
    protected $strTemplate = 'ce_cabanne_sac_detail';

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




        return parent::generate();
    }


    /**
     * Generate the content element
     */
    protected function compile()
    {
        // Add data to template
        $objDb = Database::getInstance()->prepare('SELECT * FROM tl_cabanne_sac WHERE id=?')->execute($this->cabanneSac);
        if ($objDb->numRows)
        {
            $arrData = $objDb->fetchAssoc();
            //die(print_r($arrData,true));
            $skip = array('id', 'tstamp');
            foreach ($arrData as $k => $v)
            {
                if (!in_array($k, $skip))
                {
                    $this->Template->$k = $v;
                    $this->arrData[$k] = $v;
                }

            }
        }
        $objFile = FilesModel::findByUuid($objDb->singleSRC);

        if ($objFile !== null && is_file(TL_ROOT . '/' . $objFile->path))
        {
            $this->singleSRC = $objFile->path;
            $this->objFilesModel = $objFile;

            $this->addImageToTemplate($this->Template, $this->arrData, null, null, $this->objFilesModel);
        }

        // coordsCH1903
        if(strpos($this->coordsCH1903, '/') !== false)
        {
            $arrCoord = explode('/', $this->coordsCH1903);
            $this->Template->coordsCH1903X = trim($arrCoord[0]);
            $this->Template->coordsCH1903Y = trim($arrCoord[1]);
        }

    }
}
