<?php

declare(strict_types=1);

/*
 * This file is part of SAC Event Tool Bundle.
 *
 * (c) Marko Cupic 2021 <m.cupic@gmx.ch>
 * @license MIT
 * For the full copyright and license information,
 * please view the LICENSE file that was distributed with this source code.
 * @link https://github.com/markocupic/sac-event-tool-bundle
 */

namespace Markocupic\SacEventToolBundle\Pdf;

use Contao\Config;
use Contao\FilesModel;
use Contao\System;

/**
 * Class WorkshopTCPDF.
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
    public $backgroundImage;

    /**
     * @var null
     */
    public $backgroundImageBottom;

    // Page header
    public function Header(): void
    {
        // Get root dir
        $rootDir = System::getContainer()->getParameter('kernel.project_dir');

        // Set background-image
        if ('cover' === $this->type) {
            $this->backgroundImage = Config::get('SAC_EVT_WORKSHOP_FLYER_COVER_BACKGROUND_IMAGE');
        } elseif ('TOC' === $this->type) {
            $this->backgroundImage = 'files/fileadmin/page_assets/kursbroschuere/toc.jpg';
            $this->backgroundImageBottom = 'files/fileadmin/page_assets/kursbroschuere/background.png';
        } elseif ('eventPage' === $this->type) {
            // default
            $this->backgroundImage = 'files/fileadmin/page_assets/kursbroschuere/hochtour.jpg';
            $this->backgroundImageBottom = 'files/fileadmin/page_assets/kursbroschuere/background.png';

            // set background image
            if ('' !== $this->Event->singleSRCBroschuere) {
                $objImage = FilesModel::findByUuid($this->Event->singleSRCBroschuere);

                if (null !== $objImage) {
                    $this->backgroundImage = $objImage->path;
                }
            }
        } else {
            $this->backgroundImage = $objImage->path;
        }

        // set the starting point for the page content
        $this->setPageMark();

        // disable auto-page-break
        $this->SetAutoPageBreak(false, 0);
        $this->SetMargins(0, 0, 0, 0);

        // Background-image
        if (null !== $this->backgroundImage) {
            $this->Image($rootDir.'/'.$this->backgroundImage, 0, 0, 210, 297, '', '', '', false, 300, '', false, false, 0);
        }
        // Rec background bottom
        if (null !== $this->backgroundImageBottom) {
            $this->Image($rootDir.'/'.$this->backgroundImageBottom, 0, 0, 210, 297, '', '', '', false, 300, '', false, false, 0);
        }

        if ('cover' === $this->type) {
            // Transparent overlay
            //$this->setAlpha(0.45);
            //$style = array('L' => 0, 'T' => 0, 'R' => 0, 'B' => 0);
            //$this->Rect(0, 0, 220, 300, 'DF', $style, array(0, 0, 0));
        }

        // White background for the table of content
        if ('TOC' === $this->type || 'eventPage' === $this->type) {
            // Transparent header
            $this->setAlpha(0.65);
            $style = ['L' => 0, 'T' => 0, 'R' => 0, 'B' => 0];
            $this->Rect(0, 0, 210, 50, 'DF', $style, [255, 255, 255]);
            $this->setAlpha(1);
        }

        // White background for the table of content
        if ('TOC' === $this->type) {
            //$this->setAlpha(0.95);
            // Border Style
            $style = ['L' => 0, 'T' => 0, 'R' => 0, 'B' => 0];
            $this->Rect(15, 35, 180, 230, 'DF', $style, [255, 255, 255]);
            $this->setAlpha(1);
        }

        // logo
        $this->Image($rootDir.'/files/fileadmin/page_assets/kursbroschuere/logo-sac-2000.png', 150, 5, 50, '', '', '', '', false, 300, '', false, false, 0);

        // Stripe bottom
        if ('eventPage' === $this->type) {
            $style = ['L' => 0, 'T' => 0, 'R' => 0, 'B' => 0];
            $this->Rect(0, 275, 210, 10, 'DF', $style, [255, 255, 255]);
            $this->setY(275);
            $this->setFillColorArray([255, 255, 255]); // white
            $this->setTextColor(55, 55, 55);
            $date = date('Y', (int) $this->Event->startDate);
            $this->Cell(210, 10, 'SAC Sektion Pilatus Ausbildung '.$date, 0, 1, 'C', 1, '', 0);
        }

        // QRCODE,H : QR-CODE Best error correction
        if ('eventPage' === $this->type) {
            // Background-image
            /*
            $style = [
                'border' => 0,
                'padding' => 5,
                'fgcolor' => [0, 0, 0],
                'bgcolor' => [255, 255, 255],
            ];
            // QR-CODE - Im Moment deaktiviert
            $this->write2DBarcode('http://sac-kurse.kletterkader.com/kurse-detail/' . $this->Event->id, 'QRCODE,H', 175, 267, 25, 25, $style, 'N');
            */
        }

        // Restore AutoPageBreak
        $this->SetAutoPageBreak(true, 20);

        // Set margins
        $this->SetMargins(20, 20, 20, 20);

        if ('TOC' === $this->type) {
            $this->SetAutoPageBreak(true, 30);
            $this->SetMargins(20, 40, 20, 20);
        }
    }
}
