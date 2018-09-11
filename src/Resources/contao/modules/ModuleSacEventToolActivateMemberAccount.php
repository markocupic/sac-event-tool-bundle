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
use Contao\Config;
use Contao\Controller;
use Contao\Database;
use Contao\Date;
use Contao\Environment;
use Contao\FrontendUser;
use Contao\Input;
use Contao\MemberModel;
use Contao\Module;
use Contao\StringUtil;
use Haste\Form\Form;
use Haste\Haste;
use Haste\Util\Url;
use NotificationCenter\Model\Notification;
use Patchwork\Utf8;


session_start();

/**
 * Class ModuleSacEventToolActivateMemberAccount
 * @package Markocupic\SacEventToolBundle
 */
class ModuleSacEventToolActivateMemberAccount extends Module
{

    /**
     * Template
     * @var string
     */
    protected $strTemplate = 'mod_sac_event_tool_activate_member_account';

    /**
     * @var
     */
    protected $step;

    /**
     * @var
     */
    protected $objNotification;

    /**
     * @var
     */
    protected $arrGroups;

    /**
     * @var
     */
    protected $objForm;

    /**
     * @var int
     */
    protected $activationLinkLifetime = 3600;

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

            $objTemplate->wildcard = '### ' . Utf8::strtoupper($GLOBALS['TL_LANG']['FMD']['eventToolActivateMemberAccount'][0]) . ' ###';
            $objTemplate->title = $this->headline;
            $objTemplate->id = $this->id;
            $objTemplate->link = $this->name;
            $objTemplate->href = 'contao/main.php?do=themes&amp;table=tl_module&amp;act=edit&amp;id=' . $this->id;

            return $objTemplate->parse();
        }

        if (FE_USER_LOGGED_IN)
        {
            $this->objUser = FrontendUser::getInstance();
        }

        // Add groups
        $this->arrGroups = StringUtil::deserialize($this->reg_groups, true);

        // Use terminal42/notification_center
        $this->objNotification = Notification::findByPk($this->activateMemberAccountNotificationId);

        // Add step param
        if (Input::get('step') == '')
        {
            $url = Url::addQueryString('step=1');
            Controller::redirect($url);
        }

        return parent::generate();
    }


    /**
     * Generate the module
     */
    protected function compile()
    {
        $this->step = Input::get('step');
        $this->Template->step = $this->step;
        $this->Template->hasError = false;
        $this->Template->errorMsg = '';


        switch ($this->step)
        {
            case 1:
                unset($_SESSION['SAC_EVT_TOOL']);
                $_SESSION['SAC_EVT_TOOL']['memberAccountActivation']['step'] = 1;
                $this->generateFirstForm();
                if ($this->objForm !== null)
                {
                    $this->Template->form = $this->objForm->generate();
                }
                break;

            case 2:
                $objMember = MemberModel::findByPk($_SESSION['SAC_EVT_TOOL']['memberAccountActivation']['memberId']);
                if ($_SESSION['SAC_EVT_TOOL']['memberAccountActivation']['step'] === 2 && $objMember !== null)
                {
                    $this->generateSecondForm();
                    $this->Template->objMember = $objMember;
                    $this->Template->form = $this->objForm->generate();
                }
                else
                {
                    unset($_SESSION['SAC_EVT_TOOL']);
                    $url = Url::removeQueryString(['step']);
                    Controller::redirect($url);
                }
                break;

            case 3:
                if ($_SESSION['SAC_EVT_TOOL']['memberAccountActivation']['step'] === 3)
                {
                    $this->generateThirdForm();
                    if ($this->objForm !== null)
                    {
                        $this->Template->form = $this->objForm->generate();
                    }
                }
                else
                {
                    unset($_SESSION['SAC_EVT_TOOL']);
                    $url = Url::removeQueryString(['step']);
                    Controller::redirect($url);
                }
                break;

            case 4:
                if ($_SESSION['SAC_EVT_TOOL']['memberAccountActivation']['step'] !== 4)
                {
                    $url = Url::removeQueryString(['step']);
                    Controller::redirect($url);
                }
                unset($_SESSION['SAC_EVT_TOOL']);

                break;
        }
    }


    /**
     * @return null
     */
    protected function generateFirstForm()
    {

        $objForm = new Form('form-activate-member-account', 'POST', function ($objHaste) {
            return Input::post('FORM_SUBMIT') === $objHaste->getFormId();
        });
        $url = Environment::get('uri');
        $objForm->setFormActionFromUri($url);


        $objForm->addFormField('username', array(
            'label'     => 'SAC Mitgliedernummer',
            'inputType' => 'text',
            'eval'      => array('mandatory' => true),
        ));
        $objForm->addFormField('email', array(
            'label'     => 'Deine beim SAC registrierte E-Mail-Adresse',
            'inputType' => 'text',
            'eval'      => array('mandatory' => true, 'maxlength' => 255, 'rgxp' => 'email'),
        ));
        $objForm->addFormField('dateOfBirth', array(
            'label'     => 'Dein Geburtsdatum (dd.mm.YYYY)',
            'inputType' => 'text',
            'eval'      => array('rgxp' => 'date', 'datepicker' => true),
        ));
        $objForm->addFormField('agb', array(
            'label'     => array('', 'Ich akzeptiere die <a href="#" data-toggle="modal" data-target="#agbModal">allg. Gesch&auml;ftsbedingungen.</a>'),
            'inputType' => 'checkbox',
            'eval'      => array('mandatory' => true),
        ));

        // Let's add  a submit button
        $objForm->addFormField('submit', array(
            'label'     => 'Mitgliederkonto aktivieren',
            'inputType' => 'submit',
        ));

        // Automatically add the FORM_SUBMIT and REQUEST_TOKEN hidden fields.
        // DO NOT use this method with generate() as the "form" template provides those fields by default.
        $objForm->addContaoHiddenFields();


        $objWidget = $objForm->getWidget('dateOfBirth');
        $objWidget->addAttribute('placeholder', 'dd.mm.YYYY');


        // validate() also checks whether the form has been submitted
        if ($objForm->validate())
        {
            $hasError = false;

            // Validate sacMemberId
            $objMember = Database::getInstance()->prepare('SELECT * FROM tl_member WHERE sacMemberId=?')->limit(1)->execute(Input::post('username'));
            if (!$objMember->numRows)
            {
                $this->Template->errorMsg = sprintf('Zur eingegebenen Mitgliedernummer %s konnte kein Benutzer zugeordnet werden.', Input::post('username'));
                $hasError = true;
            }

            if (!$hasError)
            {
                if (Date::parse(Config::get('dateFormat'), $objMember->dateOfBirth) !== Input::post('dateOfBirth'))
                {
                    $this->Template->errorMsg = 'Mitgliedernummer und Geburtsdatum stimmen nicht &uuml;berein.';
                    $hasError = true;
                }
            }

            if (!$hasError)
            {
                if (strtolower(Input::post('email')) !== strtolower($objMember->email))
                {
                    $this->Template->errorMsg = 'Mitgliedernummer und E-Mail-Adresse stimmen nicht &uuml;berein.';
                    $hasError = true;
                }
            }

            if (!$hasError)
            {
                if ($objMember->login)
                {
                    $this->Template->errorMsg = sprintf('Das Konto mit der eingegebenen Mitgliedernummer %s wurde bereits aktiviert.', Input::post('username'));
                    $hasError = true;
                }
            }

            if (!$hasError)
            {
                if ($objMember->disable)
                {
                    $this->Template->errorMsg = sprintf('Das Konto mit der eingegebenen Mitgliedernummer %s ist deaktiviert und nicht mehr g&uuml;ltig.', Input::post('username'));
                    $hasError = true;
                }
            }


            $this->Template->hasError = $hasError;


            // Save data to tl_calendar_events_member
            if (!$hasError)
            {
                $objMemberModel = MemberModel::findByPk($objMember->id);
                if ($objMemberModel !== null)
                {
                    $token = rand(111111, 999999);
                    $objMemberModel->activation = $token;
                    $objMemberModel->activationLinkLifetime = time() + $this->activationLinkLifetime;
                    $objMemberModel->activationFalseTokenCounter = 0;
                    $objMemberModel->save();

                    if ($this->notifyMember($objMemberModel))
                    {
                        // Set session dataR
                        $_SESSION['SAC_EVT_TOOL']['memberAccountActivation']['memberId'] = $objMemberModel->id;
                        $_SESSION['SAC_EVT_TOOL']['memberAccountActivation']['step'] = 2;

                        // Redirect
                        $url = Url::removeQueryString(['step']);
                        $url = Url::addQueryString('step=2', $url);
                        Controller::redirect($url);
                    }
                    else
                    {
                        $hasError = true;
                        $this->Template->hasError = $hasError;
                        $this->Template->errorMsg = 'Der Aktivierung konnte nicht abgeschlossen werden. Bitte probiere es nochmals oder nimm mit der Gesch&auml;ftsstelle Kontakt auf.';
                    }
                }
            }
        }

        $this->objForm = $objForm;
    }

    /**
     *
     */
    protected function generateSecondForm()
    {
        $objMember = MemberModel::findByPk($_SESSION['SAC_EVT_TOOL']['memberAccountActivation']['memberId']);
        if ($objMember === null)
        {
            $url = Url::removeQueryString(['step']);
            Controller::redirect($url);
        }
        $objForm = new Form('form-activate-member-account-activation-token', 'POST', function ($objHaste) {
            return Input::post('FORM_SUBMIT') === $objHaste->getFormId();
        });

        $objForm->setFormActionFromUri(Environment::get('uri'));

        // Password
        $objForm->addFormField('activationToken', array(
            'label'     => 'Aktivierungscode eingeben',
            'inputType' => 'text',
            'eval'      => array('mandatory' => true, 'minlength' => 6, 'maxlength' => 6),
        ));

        // Let's add  a submit button
        $objForm->addFormField('submit', array(
            'label'     => 'Aktivierungscode absenden',
            'inputType' => 'submit',
        ));

        // Automatically add the FORM_SUBMIT and REQUEST_TOKEN hidden fields.
        // DO NOT use this method with generate() as the "form" template provides those fields by default.
        $objForm->addContaoHiddenFields();


        // Check activation token
        $hasError = false;

        // validate() also checks whether the form has been submitted
        if ($objForm->validate() && Input::post('activationToken') !== '')
        {

            $token = trim(Input::post('activationToken'));

            $objMember = MemberModel::findByPk($_SESSION['SAC_EVT_TOOL']['memberAccountActivation']['memberId']);
            if ($objMember === null)
            {
                $hasError = true;
                $url = Url::removeQueryString(['step']);
                $this->Template->doNotShowForm = true;
                $this->Template->errorMsg = sprintf('Leider ist die Session abgelaufen. Starte den Aktivierungsprozess von vorne.<br><a href="%s">Aktivierungsprozess neu starten</a>', $url);
            }

            if ($objMember->disable)
            {
                $hasError = true;
                $this->Template->errorMsg = 'Es ist ein Fehler aufgetreten. Dein Mitgliederkonto ist deaktiviert. Bitte informiere die Geschäftsstelle.';
            }

            $objDb = Database::getInstance()->prepare('SELECT * FROM tl_member WHERE id=? AND activation=?')->limit(1)->execute($objMember->id, $token);

            if (!$hasError && !$objDb->numRows)
            {
                $hasError = true;
                $objMember->activationFalseTokenCounter++;
                $objMember->save();
                // Too many tries
                if ($objMember->activationFalseTokenCounter > 5)
                {
                    $objMember->activationFalseTokenCounter = 0;
                    $objMember->activation = '';
                    $objMember->activationLinkLifetime = 0;
                    $objMember->save();
                    unset($_SESSION['SAC_EVT_TOOL']['memberAccountActivation']['memberId']);
                    $url = Url::removeQueryString(['step']);
                    $this->Template->doNotShowForm = true;
                    $this->Template->errorMsg = sprintf('Ungültiger Aktivierungscode und zu viele Anzahl ungültiger Versuche. Bitte starte den Aktivierungsprozess von vorne. <br><a href="%s">Aktivierungsprozess neu starten</a>', $url);
                }
                else
                {
                    // False token
                    $this->Template->errorMsg = 'Ungültiger Aktivierungscode. Bitte erneut versuchen.';
                }
            }
            else
            {
                // Token has expired
                if ($objDb->activationLinkLifetime < time())
                {
                    $hasError = true;
                    $this->Template->doNotShowForm = true;
                    $this->Template->errorMsg = 'Der Aktivierungscode ist abgelaufen. Bitte starte den Aktivierungsprozess von vorne.';
                }
                else
                {
                    // All ok!
                    $objMember->activationFalseTokenCounter = 0;
                    $objMember->activation = '';
                    $objMember->activationLinkLifetime = 0;

                    // Set session data
                    $_SESSION['SAC_EVT_TOOL']['memberAccountActivation']['step'] = 3;

                    // Redirect
                    $url = Url::removeQueryString(['step']);
                    $url = Url::addQueryString('step=3', $url);
                    Controller::redirect($url);
                }
            }
        }

        $this->Template->hasError = $hasError;
        $this->objForm = $objForm;
    }


    /**
     *
     */
    protected function generateThirdForm()
    {

        $objForm = new Form('form-activate-member-account-set-password', 'POST', function ($objHaste) {
            return Input::post('FORM_SUBMIT') === $objHaste->getFormId();
        });

        $objForm->setFormActionFromUri(Environment::get('uri'));

        // Password
        $objForm->addFormField('password', array(
            'label'     => 'Passwort festlegen',
            'inputType' => 'password',
            'eval'      => array('mandatory' => true, 'maxlength' => 255),
        ));

        // Let's add  a submit button
        $objForm->addFormField('submit', array(
            'label'     => 'Mitgliederkonto aktivieren',
            'inputType' => 'submit',
        ));

        // Automatically add the FORM_SUBMIT and REQUEST_TOKEN hidden fields.
        // DO NOT use this method with generate() as the "form" template provides those fields by default.
        $objForm->addContaoHiddenFields();


        // Check activation token
        $hasError = false;

        // Validate session
        $objMemberModel = MemberModel::findByPk($_SESSION['SAC_EVT_TOOL']['memberAccountActivation']['memberId']);
        if ($objMemberModel === null)
        {
            $this->Template->errorMsg = 'Es ist ein Fehler aufgetreten. Die Session ist abgelaufen.';
            $hasError = true;
        }

        $this->Template->hasError = $hasError;


        // validate() also checks whether the form has been submitted
        if ($objForm->validate() && $hasError === false)
        {

            // Save data to tl_member
            if (!$hasError)
            {
                if ($objMemberModel !== null)
                {
                    $objMemberModel->password = password_hash(Input::post('password'), PASSWORD_DEFAULT);
                    $objMemberModel->activation = '';
                    $objMemberModel->activationLinkLifetime = 0;
                    $objMember->activationFalseTokenCounter = 0;
                    $objMemberModel->login = '1';

                    // Add groups
                    $arrGroups = StringUtil::deserialize($objMemberModel->groups, true);
                    $arrGroups = array_merge($arrGroups, $this->arrGroups);
                    $arrGroups = array_unique($arrGroups);
                    $arrGroups = array_filter($arrGroups);
                    $objMemberModel->groups = serialize($arrGroups);
                    $objMemberModel->save();

                    // Set sesion data
                    $_SESSION['SAC_EVT_TOOL']['memberAccountActivation']['step'] = 4;

                    // Redirect
                    $url = Url::removeQueryString(['step']);
                    $url = Url::addQueryString('step=4', $url);
                    Controller::redirect($url);
                }
            }
        }

        $this->objForm = $objForm;
    }


    /**
     * @param $objMember
     * @return bool
     */
    private function notifyMember($objMember)
    {
        // Use terminal42/notification_center
        if ($this->objNotification !== null)
        {
            // Set token array
            $arrTokens = array(
                'firstname'   => html_entity_decode($objMember->firstname),
                'lastname'    => html_entity_decode($objMember->lastname),
                'street'      => html_entity_decode($objMember->street),
                'postal'      => html_entity_decode($objMember->postal),
                'city'        => html_entity_decode($objMember->city),
                'phone'       => html_entity_decode($objMember->phone),
                'activation'  => $objMember->activation,
                'username'    => html_entity_decode($objMember->username),
                'sacMemberId' => html_entity_decode($objMember->username),
                'email'       => $objMember->email,
            );

            $this->objNotification->send($arrTokens, 'de');

            return true;
        }
        return false;
    }

}
