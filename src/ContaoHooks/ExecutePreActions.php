<?php

/**
 * SAC Event Tool Web Plugin for Contao
 * Copyright (c) 2008-2017 Marko Cupic
 * @package sac-event-tool-bundle
 * @author Marko Cupic m.cupic@gmx.ch, 2017-2018
 * @link https://sac-kurse.kletterkader.com
 */

namespace Markocupic\SacEventToolBundle\ContaoHooks;

use Contao\Config;
use Contao\CoreBundle\Framework\ContaoFrameworkInterface;
use Contao\Date;
use Contao\Input;
use Contao\MemberModel;


/**
 * Class ExecutePreActions
 * @package Markocupic\SacEventToolBundle\ContaoHooks
 */
class ExecutePreActions
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
     * @param string $strAction
     */
    public function executePreActions($strAction = '')
    {
        // Autocompleter when registrating event members manually in the backend
        if ($strAction === 'autocompleterLoadMemberDataFromSacMemberId')
        {
            // Output
            $json = array('status' => 'error');
            $objMemberModel = MemberModel::findBySacMemberId(Input::post('sacMemberId'));
            if ($objMemberModel !== null)
            {
                $json = $objMemberModel->row();
                $json['dateOfBirth'] = Date::parse(Config::get('dateFormat'), $json['dateOfBirth']);
                $json['status'] = 'success';

                $html = '<div>';
                $html .= '<h1>Mitglied gefunden</h1>';
                $html .= sprintf('<div>Sollen die Daten von %s %s &uuml;bernommen werden?</div>', $objMemberModel->firstname, $objMemberModel->lastname);
                $html .= '<button class="tl_button">Ja</button> <button class="tl_button">nein</button>';
                $json['html'] = $html;
            }


            // Send it to the browser
            echo html_entity_decode(json_encode($json));
            exit();
        }
    }
}


