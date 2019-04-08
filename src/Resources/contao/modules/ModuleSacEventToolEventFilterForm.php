<?php

/**
 * SAC Event Tool Web Plugin for Contao
 * Copyright (c) 2008-2019 Marko Cupic
 * @package sac-event-tool-bundle
 * @author Marko Cupic m.cupic@gmx.ch, 2017-2019
 * @link https://github.com/markocupic/sac-event-tool-bundle
 */

namespace Markocupic\SacEventToolBundle;

use Contao\BackendTemplate;
use Contao\Controller;
use Contao\Input;
use Contao\Module;
use Contao\PageModel;
use Contao\StringUtil;
use Haste\Form\Form;
use Patchwork\Utf8;

/**
 * Class ModuleSacEventToolEventEventFilterForm
 * @package Markocupic\SacEventToolBundle
 */
class ModuleSacEventToolEventEventFilterForm extends Module
{

    /**
     * Template
     * @var string
     */
    protected $strTemplate = 'mod_eventToolEventFilterForm';

    /**
     * @var
     */
    protected static $arrAllowedFields;

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
            $objTemplate = new BackendTemplate('be_wildcard');

            $objTemplate->wildcard = '### ' . Utf8::strtoupper($GLOBALS['TL_LANG']['FMD']['eventToolEventFilterForm'][0]) . ' ###';
            $objTemplate->title = $this->headline;
            $objTemplate->id = $this->id;
            $objTemplate->link = $this->name;
            $objTemplate->href = 'contao/main.php?do=themes&amp;table=tl_module&amp;act=edit&amp;id=' . $this->id;

            return $objTemplate->parse();
        }

        static::$arrAllowedFields = StringUtil::deserialize($this->eventFilterBoardFields, true);

        return parent::generate();
    }

    /**
     * Generate the module
     */
    protected function compile()
    {
        $this->Template->fields = static::$arrAllowedFields;
        $this->generateForm();
    }

    /**
     * Generate form
     */
    protected function generateForm()
    {
        Controller::loadLanguageFile('tl_event_filter_form');

        // Generate form
        $objForm = new Form('event-filter-board-form', 'GET', function () {
            return Input::get('eventFilter') === 'eventFilter';
        });

        // Action
        global $objPage;
        $objPageModel = PageModel::findByPk($objPage->id);
        $url = $objPageModel->getFrontendUrl();
        $objForm->setFormActionFromUri($url);

        $objForm->addFieldsFromDca('tl_event_filter_form', function (&$strField, &$arrDca) {
            // Make sure to skip elements without inputType or you will get an exception
            if (!isset($arrDca['inputType']))
            {
                return false;
            }

            if (!in_array($strField, static::$arrAllowedFields))
            {
                return false;
            }

            // You must return true otherwise the field will be skipped
            return true;
        });

        // Let's add  a submit button
        $objForm->addFormField('submit', array(
            'label'     => $GLOBALS['TL_LANG']['tl_event_filter_form']['submitBtn'],
            'inputType' => 'submit'
        ));

        // Set formfield value from $_GET
        if (isset($_GET) && is_array(static::$arrAllowedFields) && !empty(static::$arrAllowedFields))
        {
            foreach (static::$arrAllowedFields as $k)
            {
                if (Input::get($k) != '')
                {
                    if ($objForm->hasFormField($k))
                    {
                        $objWidget = $objForm->getWidget($k);
                        $objWidget->value = Input::get($k);
                    }
                }
            }
        }

        $this->Template->form = $objForm;
    }
}