<?php

declare(strict_types=1);

/**
 * SAC Event Tool Web Plugin for Contao
 * Copyright (c) 2008-2020 Marko Cupic
 * @package sac-event-tool-bundle
 * @author Marko Cupic m.cupic@gmx.ch, 2017-2020
 * @link https://github.com/markocupic/sac-event-tool-bundle
 */

namespace Markocupic\SacEventToolBundle\Pdf;

use Contao\System;
use Contao\Config;

/**
 * Class WorkshopTCPDF
 * @package Markocupic\SacEventToolBundle\Pdf
 */
class WorkshopTCPDF extends \TCPDF
{
    /**
     * @var Event db-object
     */
    public $Event;

    /**
     * @var page type
     */
    public $type;
    /**
     * @var null
     */
    public $backgroundImage = null;

    /**
     * @var null
     */
    public $backgroundImageBottom = null;

    // Page header
    public function Header()
    {
        // Get root dir
        $rootDir = System::getContainer()->getParameter('kernel.project_dir');

        // Set background-image
        if ($this->type == 'cover')
        {
            $this->backgroundImage = Config::get('SAC_EVT_WORKSHOP_FLYER_COVER_BACKGROUND_IMAGE');;
        }
        elseif ($this->type == 'TOC')
        {
            $this->backgroundImage = 'files/fileadmin/page_assets/kursbroschuere/toc.jpg';
            $this->backgroundImageBottom = 'files/fileadmin/page_assets/kursbroschuere/background.png';
        }
        elseif ($this->type == 'eventPage')
        {
            // default
            $this->backgroundImage = 'files/fileadmin/page_assets/kursbroschuere/hochtour.jpg';
            $this->backgroundImageBottom = 'files/fileadmin/page_assets/kursbroschuere/background.png';

            // set background image
            if ($this->Event->singleSRCBroschuere != '')
            {
                $objImage = \FilesModel::findByUuid($this->Event->singleSRCBroschuere);
                if ($objImage !== null)
                {
                    $this->backgroundImage = $objImage->path;
                }
            }
        }
        else
        {
            $this->backgroundImage = $objImage->path;
        }

        // set the starting point for the page content
        $this->setPageMark();

        // disable auto-page-break
        $this->SetAutoPageBreak(false, 0);
        $this->SetMargins(0, 0, 0, 0);

        // Background-image
        if ($this->backgroundImage !== null)
        {
            $this->Image($rootDir . '/' . $this->backgroundImage, 0, 0, 210, 297, '', '', '', false, 300, '', false, false, 0);
        }
        // Rec background bottom
        if ($this->backgroundImageBottom !== null)
        {
            $this->Image($rootDir . '/' . $this->backgroundImageBottom, 0, 0, 210, 297, '', '', '', false, 300, '', false, false, 0);
        }

        if ($this->type == 'cover')
        {
            // Transparent overlay
            //$this->setAlpha(0.45);
            //$style = array('L' => 0, 'T' => 0, 'R' => 0, 'B' => 0);
            //$this->Rect(0, 0, 220, 300, 'DF', $style, array(0, 0, 0));
        }

        // White background for the table of content
        if ($this->type == 'TOC' || $this->type == 'eventPage')
        {
            // Transparent header
            $this->setAlpha(0.65);
            $style = ['L' => 0, 'T' => 0, 'R' => 0, 'B' => 0];
            $this->Rect(0, 0, 210, 50, 'DF', $style, [255, 255, 255]);
            $this->setAlpha(1);
        }

        // White background for the table of content
        if ($this->type == 'TOC')
        {
            //$this->setAlpha(0.95);
            // Border Style
            $style = ['L' => 0, 'T' => 0, 'R' => 0, 'B' => 0];
            $this->Rect(15, 35, 180, 230, 'DF', $style, [255, 255, 255]);
            $this->setAlpha(1);
        }

        // logo
        $this->Image($rootDir . '/files/fileadmin/page_assets/kursbroschuere/logo-sac-2000.png', 150, 5, 50, '', '', '', '', false, 300, '', false, false, 0);

        // Stripe bottom
        if ($this->type == 'eventPage')
        {
            $style = ['L' => 0, 'T' => 0, 'R' => 0, 'B' => 0];
            $this->Rect(0, 275, 210, 10, 'DF', $style, [255, 255, 255]);
            $this->setY(275);
            $this->setFillColorArray([255, 255, 255]);  // white
            $this->setTextColor(55, 55, 55);
            $date = \Date('Y', (int) $this->Event->startDate);
            $this->Cell(210, 10, 'SAC Sektion Pilatus Ausbildung ' . $date, 0, 1, 'C', 1, '', 0);
        }

        // QRCODE,H : QR-CODE Best error correction
        if ($this->type == 'eventPage')
        {
            // Background-image
            $style = [
                'border'  => 0,
                'padding' => 5,
                'fgcolor' => [0, 0, 0],
                'bgcolor' => [255, 255, 255],
            ];
            // QR-CODE - Im Moment deaktiviert
            //$this->write2DBarcode('http://sac-kurse.kletterkader.com/kurse-detail/' . $this->Event->id, 'QRCODE,H', 175, 267, 25, 25, $style, 'N');
        }

        // Restore AutoPageBreak
        $this->SetAutoPageBreak(true, 20);

        // Set margins
        $this->SetMargins(20, 20, 20, 20);
        if ($this->type == 'TOC')
        {
            $this->SetAutoPageBreak(true, 30);
            $this->SetMargins(20, 40, 20, 20);
        }
    }
}