<?php

/**
 * SAC Event Tool Web Plugin for Contao
 * Copyright (c) 2008-2017 Marko Cupic
 * @package sac-event-tool-bundle
 * @author Marko Cupic m.cupic@gmx.ch, 2017
 * @link    https://sac-kurse.kletterkader.com
 */


namespace Markocupic\SacEventToolBundle;

use Contao\ContentElement;
use Contao\FrontendTemplate;
use Contao\File;
use Contao\FilesModel;
use Contao\Email;



/**
 * Class CalendarNewsletter
 * @package Markocupic\SacEventToolBundle
 */
class CalendarNewsletter extends ContentElement
{

    /**
     * Template
     * @var string
     */
    protected $strTemplate = 'newsletter';


    /**
     * Display a login form
     *
     * @return string
     */
    public function generate()
    {
        if(TL_MODE == 'BE'){
           return '';
        }
        return parent::generate();

    }


    /**
     * Generate the module
     */
    protected function compile()
    {
        if(TL_MODE == 'FE'){

            $objEvent = $this->Database->prepare('SELECT * FROM tl_calendar_events')->execute();
            while($objEvent->next())
            {
                $objTemplate = new FrontendTemplate('newsletter_partial');
                $objTemplate->title = $objEvent->title;
                $objTemplate->location = $objEvent->location;
                $objTemplate->teaser = $objEvent->teaser;
                $imgPath = FilesModel::findByUuid($objEvent->singleSRC)->path;
                $objTemplate->singleSRC = 'http://sac-kurse.kletterkader.com/' . \Image::get($imgPath, 300, 300, 'center_center');
                $objTemplate->more = 'http://sac-kurse.kletterkader.com/kurse-detailansicht/' . $objEvent->alias;
                $this->Template->events .= $objTemplate->parse();
            }
            $strFilename = 'files/newsletter/newsletter_' . time(). '.txt';
            $file = new File($strFilename);
            $file->write($this->Template->parse());
            $strFilename = 'files/newsletter/newsletter_' . time();
            $file = new File($strFilename);
            $file->write($this->Template->parse());
            $objEmail = new Email();
            $objEmail->html = $this->Template->parse();
            $objEmail->from = 'm.cupic@gmx.ch';
            $objEmail->fromName = 'Marko Cupic';
            $objEmail->subject = 'Test Newsletter';
            $objEmail->attachFile($strFilename);
            //$objEmail->sendTo('m.cupic@gmx.ch');
            die($this->Template->parse());
        }

    }
}