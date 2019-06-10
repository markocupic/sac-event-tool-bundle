<?php

/**
 * SAC Event Tool Web Plugin for Contao
 * Copyright (c) 2008-2019 Marko Cupic
 * @package sac-event-tool-bundle
 * @author Marko Cupic m.cupic@gmx.ch, 2017-2019
 * @link https://github.com/markocupic/sac-event-tool-bundle
 */

/**
 * Table tl_user_temp
 */
$GLOBALS['TL_DCA']['tl_user_temp'] = array
(

    // Config
    'config'   => array
    (
        'dataContainer'     => 'Table',
        'enableVersioning'  => true,
        'onload_callback'   => array
        (//array('tl_user_temp', 'getSacMemberId'),
        ),
        'onsubmit_callback' => array
        (//array('tl_user_temp', 'storeDateAdded')
        ),
        'sql'               => array
        (
            'keys' => array
            (
                'id'          => 'primary',
                'username'    => 'unique',
                'email'       => 'index',
                'sacMemberId' => 'index',
            ),
        ),
    ),

    // List
    'list'     => array
    (
        'sorting'           => array
        (
            'mode'        => 2,
            'fields'      => array('lastname DESC'),
            'flag'        => 1,
            'panelLayout' => 'filter;sort,search,limit',
        ),
        'label'             => array
        (
            'fields'         => array('icon', 'firstname', 'lastname', 'sacMemberId'),
            'showColumns'    => true,
            'label_callback' => array('tl_user_temp', 'addIcon'),
        ),
        'global_operations' => array
        (
            'all' => array
            (
                'label'      => &$GLOBALS['TL_LANG']['MSC']['all'],
                'href'       => 'act=select',
                'class'      => 'header_edit_all',
                'attributes' => 'onclick="Backend.getScrollOffset()" accesskey="e"',
            ),
        ),
        'operations'        => array
        (
            'edit'   => array
            (
                'label'           => &$GLOBALS['TL_LANG']['tl_user_temp']['edit'],
                'href'            => 'act=edit',
                'icon'            => 'edit.svg',
                'button_callback' => array('tl_user_temp', 'editUser'),
            ),
            'copy'   => array
            (
                'label'           => &$GLOBALS['TL_LANG']['tl_user_temp']['copy'],
                'href'            => 'act=copy',
                'icon'            => 'copy.svg',
                'button_callback' => array('tl_user_temp', 'copyUser'),
            ),
            'delete' => array
            (
                'label'           => &$GLOBALS['TL_LANG']['tl_user_temp']['delete'],
                'href'            => 'act=delete',
                'icon'            => 'delete.svg',
                'attributes'      => 'onclick="if(!confirm(\'' . $GLOBALS['TL_LANG']['MSC']['deleteConfirm'] . '\'))return false;Backend.getScrollOffset()"',
                'button_callback' => array('tl_user_temp', 'deleteUser'),
            ),
            'toggle' => array
            (
                'label'           => &$GLOBALS['TL_LANG']['tl_user_temp']['toggle'],
                'icon'            => 'visible.svg',
                'attributes'      => 'onclick="Backend.getScrollOffset();return AjaxRequest.toggleVisibility(this,%s)"',
                'button_callback' => array('tl_user_temp', 'toggleIcon'),
            ),
            'show'   => array
            (
                'label' => &$GLOBALS['TL_LANG']['tl_user_temp']['show'],
                'href'  => 'act=show',
                'icon'  => 'show.svg',
            ),
        ),
    ),

    // Palettes
    'palettes' => array
    (
        'default' => '{name_legend},username,firstname,lastname,name,sacMemberId,sectionId,street,postal,city,mobile,email,userRoleTemp;{backend_legend:hide},language,uploader,showHelp,thumbnails,useRTE,useCE;{theme_legend:hide},backendTheme,fullscreen;{password_legend:hide},pwChange,password;{admin_legend},admin;{groups_legend},groups,inherit;{account_legend},disable,start,stop',
    ),

    // Fields
    'fields'   => array
    (
        'id'           => array
        (
            'sql' => "int(10) unsigned NOT NULL auto_increment",
        ),
        'tstamp'       => array
        (
            'sql' => "int(10) unsigned NOT NULL default '0'",
        ),
        'username'     => array
        (
            'label'     => &$GLOBALS['TL_LANG']['tl_user_temp']['username'],
            'exclude'   => true,
            'search'    => true,
            'sorting'   => true,
            'flag'      => 1,
            'inputType' => 'text',
            'eval'      => array('mandatory' => false, 'rgxp' => 'extnd', 'nospace' => true, 'unique' => true, 'maxlength' => 64, 'tl_class' => 'w50'),
            'sql'       => "varchar(64) BINARY NULL",
        ),
        'name'         => array
        (
            'label'     => &$GLOBALS['TL_LANG']['tl_user_temp']['name'],
            'exclude'   => true,
            'search'    => true,
            'sorting'   => true,
            'flag'      => 1,
            'inputType' => 'text',
            'eval'      => array('mandatory' => false, 'maxlength' => 255, 'tl_class' => 'w50 clr'),
            'sql'       => "varchar(255) NOT NULL default ''",
        ),
        'firstname'    => array
        (
            'label'     => &$GLOBALS['TL_LANG']['tl_user_temp']['name'],
            'exclude'   => true,
            'search'    => true,
            'sorting'   => true,
            'flag'      => 1,
            'inputType' => 'text',
            'eval'      => array('mandatory' => false, 'maxlength' => 255, 'tl_class' => 'w50 clr'),
            'sql'       => "varchar(255) NOT NULL default ''",
        ),
        'sectionId'    => array(
            'label'     => &$GLOBALS['TL_LANG']['tl_member']['sectionId'],
            'reference' => &$GLOBALS['TL_LANG']['tl_member']['section'],
            'inputType' => 'checkbox',
            'filter'    => true,
            'eval'      => array('multiple' => true, 'tl_class' => 'clr'),
            'options'   => range(4250, 4254),
            'sql'       => "blob NULL",
        ),
        'lastname'     => array
        (
            'label'     => &$GLOBALS['TL_LANG']['tl_user_temp']['lastname'],
            'exclude'   => true,
            'search'    => true,
            'sorting'   => true,
            'flag'      => 1,
            'inputType' => 'text',
            'eval'      => array('mandatory' => false, 'maxlength' => 255, 'tl_class' => 'w50 clr'),
            'sql'       => "varchar(255) NOT NULL default ''",
        ),
        'mobile'       => array
        (
            'label'     => &$GLOBALS['TL_LANG']['tl_user_temp']['mobile'],
            'exclude'   => true,
            'search'    => true,
            'sorting'   => true,
            'flag'      => 1,
            'inputType' => 'text',
            'eval'      => array('mandatory' => false, 'maxlength' => 255, 'tl_class' => 'w50 clr'),
            'sql'       => "varchar(255) NOT NULL default ''",
        ),
        'sacMemberId'  => array
        (
            'label'     => &$GLOBALS['TL_LANG']['tl_user_temp']['sacMemberId'],
            'exclude'   => true,
            'search'    => true,
            'sorting'   => true,
            'flag'      => 1,
            'inputType' => 'text',
            'eval'      => array('mandatory' => false, 'maxlength' => 255, 'tl_class' => 'w50 clr'),
            'sql'       => "varchar(255) NOT NULL default ''",
        ),
        'street'       => array
        (
            'label'     => &$GLOBALS['TL_LANG']['tl_user_temp']['street'],
            'exclude'   => true,
            'search'    => true,
            'sorting'   => true,
            'flag'      => 1,
            'inputType' => 'text',
            'eval'      => array('mandatory' => false, 'maxlength' => 255, 'tl_class' => 'w50 clr'),
            'sql'       => "varchar(255) NOT NULL default ''",
        ),
        'postal'       => array
        (
            'label'     => &$GLOBALS['TL_LANG']['tl_user_temp']['postal'],
            'exclude'   => true,
            'search'    => true,
            'sorting'   => true,
            'flag'      => 1,
            'inputType' => 'text',
            'eval'      => array('mandatory' => false, 'maxlength' => 255, 'tl_class' => 'w50 clr'),
            'sql'       => "varchar(255) NOT NULL default ''",
        ),
        'city'         => array
        (
            'label'     => &$GLOBALS['TL_LANG']['tl_user_temp']['city'],
            'exclude'   => true,
            'search'    => true,
            'sorting'   => true,
            'flag'      => 1,
            'inputType' => 'text',
            'eval'      => array('mandatory' => false, 'maxlength' => 255, 'tl_class' => 'w50 clr'),
            'sql'       => "varchar(255) NOT NULL default ''",
        ),
        'email'        => array
        (
            'label'     => &$GLOBALS['TL_LANG']['tl_user_temp']['email'],
            'exclude'   => true,
            'search'    => true,
            'inputType' => 'text',
            'eval'      => array('mandatory' => false, 'rgxp' => 'email', 'maxlength' => 255, 'unique' => true, 'decodeEntities' => true, 'tl_class' => 'w50'),
            'sql'       => "varchar(255) NOT NULL default ''",
        ),
        'language'     => array
        (
            'label'            => &$GLOBALS['TL_LANG']['tl_user_temp']['language'],
            'default'          => str_replace('-', '_', $GLOBALS['TL_LANGUAGE']),
            'exclude'          => true,
            'filter'           => true,
            'inputType'        => 'select',
            'eval'             => array('rgxp' => 'locale', 'tl_class' => 'w50'),
            'options_callback' => function () {
                return System::getLanguages(true);
            },
            'sql'              => "varchar(5) NOT NULL default ''",
        ),
        'userRoleTemp' => array
        (
            'label'     => &$GLOBALS['TL_LANG']['tl_user_temp']['userRoleTemp'],
            'exclude'   => true,
            'filter'    => true,
            'inputType' => 'text',
            'sql'       => "varchar(255) NOT NULL default ''",
            'eval'      => array('tl_class' => 'clr'),

        ),
        'disable'      => array
        (
            'label'         => &$GLOBALS['TL_LANG']['tl_user_temp']['disable'],
            'exclude'       => true,
            'filter'        => true,
            'flag'          => 2,
            'inputType'     => 'checkbox',
            'save_callback' => array
            (//array('tl_user_temp', 'checkAdminDisable')
            ),
            'sql'           => "char(1) NOT NULL default ''",
        ),

    ),
);

/**
 * Provide miscellaneous methods that are used by the data configuration array.
 *
 * @author Leo Feyer <https://github.com/leofeyer>
 */
class tl_user_temp extends Backend
{

    /**
     * Import the back end user object
     */
    public function __construct()
    {
        parent::__construct();
        $this->import('BackendUser', 'User');
    }

    public function ggetSacMemberId()
    {
        $objDb = $this->Database->prepare('SELECT * FROM tl_user_temp WHERE sacMemberId = ?')->execute('');
        while ($objDb->next())
        {
            $objMember = $this->Database->prepare('SELECT * FROM tl_member WHERE firstname = ? AND lastname = ? AND city=?')->execute($objDb->firstname, $objDb->lastname, $objDb->city);
            if ($objMember->numRows == 1)
            {
                echo $objDb->sacMemberId . ' ' . $objDb->firstname . ' ' . $objDb->lastname . '<br>';
                $this->Database->prepare('UPDATE tl_user_temp SET sacMemberId = ? WHERE id=?')->execute($objMember->sacMemberId, $objDb->id);
            }
        }
    }

    public function gggetSacMemberId()
    {
        $objDb = $this->Database->prepare('SELECT * FROM tl_user_temp WHERE sacMemberId != ? AND disable=?')->execute('', '');
        while ($objDb->next())
        {
            $objUserModel = $this->Database->prepare('SELECT * FROM tl_user WHERE sacMemberId = ?')->execute($objDb->sacMemberId);

            // Bereits bestehende User mit Gruppenzugehörigkeit füttern
            if ($objUserModel->numRows)
            {
                if ($objDb->userRoleTemp !== '')
                {
                    $userRole = serialize(array_merge(explode(',', $objDb->userRoleTemp), StringUtil::deserialize($objUserModel->userRole, true)));
                    //$this->Database->prepare('UPDATE tl_user SET userRole=? WHERE id=?')->execute($userRole, $objUserModel->id);
                    //echo $objUserModel->firstname . ' ' . $objUserModel->lastname . print_r($userRole,true) . '<br>';
                }
            }
            else // Noch nicht existierende User aufnehmen
            {
                $set = array();
                $username = strtolower($objDb->firstname . $objDb->lastname);
                $username = str_replace('ä', 'ae', $username);
                $username = str_replace('ö', 'oe', $username);
                $username = str_replace('ü', 'ue', $username);
                $username = str_replace('é', 'e', $username);
                $username = str_replace('è', 'e', $username);
                $username = str_replace(' ', '', $username);
                $userModel = \Contao\UserModel::findByUsername($username);
                if ($userModel === null)
                {
                    $set['username'] = $username;
                    $set['firstname'] = $objDb->firstname;
                    $set['lastname'] = $objDb->lastname;
                    $set['name'] = $objDb->firstname . ' ' . $objDb->lastname;
                    $set['dateAdded'] = time();
                    $set['userRole'] = serialize(explode(',', $objDb->userRoleTemp));
                    $set['sacMemberId'] = $objDb->sacMemberId;
                    //echo print_r($set,true);
                    //$this->Database->prepare('INSERT INTO tl_user %s')->set($set)->execute();
                }
            }
        }
    }

    /**
     * Check permissions to edit table tl_user_temp
     *
     * @throws Contao\CoreBundle\Exception\AccessDeniedException
     */
    public function checkPermission()
    {
        if ($this->User->isAdmin)
        {
            return;
        }

        // Check current action
        switch (Input::get('act'))
        {
            case 'create':
            case 'select':
            case 'show':
                // Allow
                break;

            case 'delete':
                if (Input::get('id') == $this->User->id)
                {
                    throw new Contao\CoreBundle\Exception\AccessDeniedException('Attempt to delete own account ID ' . Input::get('id') . '.');
                }
            // no break;

            case 'edit':
            case 'copy':
            case 'toggle':
            default:
                $objUser = $this->Database->prepare("SELECT admin FROM tl_user_temp WHERE id=?")
                    ->limit(1)
                    ->execute(Input::get('id'));

                if ($objUser->admin && Input::get('act') != '')
                {
                    throw new Contao\CoreBundle\Exception\AccessDeniedException('Not enough permissions to ' . Input::get('act') . ' administrator account ID ' . Input::get('id') . '.');
                }
                break;

            case 'editAll':
            case 'deleteAll':
            case 'overrideAll':
                /** @var Symfony\Component\HttpFoundation\Session\SessionInterface $objSession */
                $objSession = System::getContainer()->get('session');

                $session = $objSession->all();
                $objUser = $this->Database->execute("SELECT id FROM tl_user_temp WHERE admin=1");
                $session['CURRENT']['IDS'] = array_diff($session['CURRENT']['IDS'], $objUser->fetchEach('id'));
                $objSession->replace($session);
                break;
        }
    }

    /**
     * Add an image to each record
     *
     * @param array $row
     * @param string $label
     * @param DataContainer $dc
     * @param array $args
     *
     * @return array
     */
    public function addIcon($row, $label, DataContainer $dc, $args)
    {
        $image = $row['admin'] ? 'admin' : 'user';
        $time = \Date::floorToMinute();

        $disabled = $row['start'] !== '' && $row['start'] > $time || $row['stop'] !== '' && $row['stop'] < $time;

        if ($row['disable'] || $disabled)
        {
            $image .= '_';
        }

        $args[0] = sprintf('<div class="list_icon_new" style="background-image:url(\'%ssystem/themes/%s/icons/%s.svg\')" data-icon="%s.svg" data-icon-disabled="%s.svg">&nbsp;</div>', System::getContainer()->get('contao.assets.assets_context')->getStaticUrl(), Backend::getTheme(), $image, $disabled ? $image : rtrim($image, '_'), rtrim($image, '_') . '_');

        return $args;
    }

    /**
     * Return the edit user button
     *
     * @param array $row
     * @param string $href
     * @param string $label
     * @param string $title
     * @param string $icon
     * @param string $attributes
     *
     * @return string
     */
    public function editUser($row, $href, $label, $title, $icon, $attributes)
    {
        return ($this->User->isAdmin || !$row['admin']) ? '<a href="' . $this->addToUrl($href . '&amp;id=' . $row['id']) . '" title="' . StringUtil::specialchars($title) . '"' . $attributes . '>' . Image::getHtml($icon, $label) . '</a> ' : Image::getHtml(preg_replace('/\.svg$/i', '_.svg', $icon)) . ' ';
    }

    /**
     * Return the copy page button
     *
     * @param array $row
     * @param string $href
     * @param string $label
     * @param string $title
     * @param string $icon
     * @param string $attributes
     * @param string $table
     *
     * @return string
     */
    public function copyUser($row, $href, $label, $title, $icon, $attributes, $table)
    {
        if ($GLOBALS['TL_DCA'][$table]['config']['closed'])
        {
            return '';
        }

        return ($this->User->isAdmin || !$row['admin']) ? '<a href="' . $this->addToUrl($href . '&amp;id=' . $row['id']) . '" title="' . StringUtil::specialchars($title) . '"' . $attributes . '>' . Image::getHtml($icon, $label) . '</a> ' : Image::getHtml(preg_replace('/\.svg$/i', '_.svg', $icon)) . ' ';
    }

    /**
     * Return the delete page button
     *
     * @param array $row
     * @param string $href
     * @param string $label
     * @param string $title
     * @param string $icon
     * @param string $attributes
     *
     * @return string
     */
    public function deleteUser($row, $href, $label, $title, $icon, $attributes)
    {
        return ($this->User->isAdmin || !$row['admin']) ? '<a href="' . $this->addToUrl($href . '&amp;id=' . $row['id']) . '" title="' . StringUtil::specialchars($title) . '"' . $attributes . '>' . Image::getHtml($icon, $label) . '</a> ' : Image::getHtml(preg_replace('/\.svg$/i', '_.svg', $icon)) . ' ';
    }

    /**
     * Generate a "switch account" button and return it as string
     *
     * @param array $row
     * @param string $href
     * @param string $label
     * @param string $title
     * @param string $icon
     *
     * @return string
     *
     * @throws Exception
     */
    public function switchUser($row, $href, $label, $title, $icon)
    {
        $authorizationChecker = System::getContainer()->get('security.authorization_checker');

        if (!$authorizationChecker->isGranted('ROLE_ALLOWED_TO_SWITCH'))
        {
            return '';
        }

        if ($this->User->id == $row['id'])
        {
            return Image::getHtml(preg_replace('/\.svg$/i', '_.svg', $icon));
        }

        $router = \System::getContainer()->get('router');
        $url = $router->generate('contao_backend', array('_switch_user' => $row['username']));

        return '<a href="' . $url . '" title="' . StringUtil::specialchars($title) . '">' . Image::getHtml($icon, $label) . '</a> ';
    }

    /**
     * Return a checkbox to delete session data
     *
     * @param DataContainer $dc
     *
     * @return string
     */
    public function sessionField(DataContainer $dc)
    {
        if (Input::post('FORM_SUBMIT') == 'tl_user_temp')
        {
            $arrPurge = Input::post('purge');

            if (\is_array($arrPurge))
            {
                $this->import('Automator');

                if (\in_array('purge_session', $arrPurge))
                {
                    /** @var Symfony\Component\HttpFoundation\Session\Attribute\AttributeBagInterface $objSessionBag */
                    $objSessionBag = System::getContainer()->get('session')->getBag('contao_backend');
                    $objSessionBag->clear();
                    Message::addConfirmation($GLOBALS['TL_LANG']['tl_user_temp']['sessionPurged']);
                }

                if (\in_array('purge_images', $arrPurge))
                {
                    $this->Automator->purgeImageCache();
                    Message::addConfirmation($GLOBALS['TL_LANG']['tl_user_temp']['htmlPurged']);
                }

                if (\in_array('purge_pages', $arrPurge))
                {
                    $this->Automator->purgePageCache();
                    Message::addConfirmation($GLOBALS['TL_LANG']['tl_user_temp']['tempPurged']);
                }
            }
        }

        return '
<div class="widget">
  <fieldset class="tl_checkbox_container">
    <legend>' . $GLOBALS['TL_LANG']['tl_user_temp']['session'][0] . '</legend>
    <input type="checkbox" id="check_all_purge" class="tl_checkbox" onclick="Backend.toggleCheckboxGroup(this, \'ctrl_purge\')"> <label for="check_all_purge" style="color:#a6a6a6"><em>' . $GLOBALS['TL_LANG']['MSC']['selectAll'] . '</em></label><br>
    <input type="checkbox" name="purge[]" id="opt_purge_0" class="tl_checkbox" value="purge_session" onfocus="Backend.getScrollOffset()"> <label for="opt_purge_0">' . $GLOBALS['TL_LANG']['tl_user_temp']['sessionLabel'] . '</label><br>
    <input type="checkbox" name="purge[]" id="opt_purge_1" class="tl_checkbox" value="purge_images" onfocus="Backend.getScrollOffset()"> <label for="opt_purge_1">' . $GLOBALS['TL_LANG']['tl_user_temp']['htmlLabel'] . '</label><br>
    <input type="checkbox" name="purge[]" id="opt_purge_2" class="tl_checkbox" value="purge_pages" onfocus="Backend.getScrollOffset()"> <label for="opt_purge_2">' . $GLOBALS['TL_LANG']['tl_user_temp']['tempLabel'] . '</label>
  </fieldset>' . $dc->help() . '
</div>';
    }

    /**
     * Return all modules except profile modules
     *
     * @return array
     */
    public function getModules()
    {
        $arrModules = array();

        foreach ($GLOBALS['BE_MOD'] as $k => $v)
        {
            if (!empty($v))
            {
                unset($v['undo']);
                $arrModules[$k] = array_keys($v);
            }
        }

        return $arrModules;
    }

    /**
     * Prevent administrators from downgrading their own account
     *
     * @param mixed $varValue
     * @param DataContainer $dc
     *
     * @return mixed
     */
    public function checkAdminStatus($varValue, DataContainer $dc)
    {
        if ($varValue == '' && $this->User->id == $dc->id)
        {
            $varValue = 1;
        }

        return $varValue;
    }

    /**
     * Prevent administrators from disabling their own account
     *
     * @param mixed $varValue
     * @param DataContainer $dc
     *
     * @return mixed
     */
    public function checkAdminDisable($varValue, DataContainer $dc)
    {
        if ($varValue == 1 && $this->User->id == $dc->id)
        {
            $varValue = '';
        }

        return $varValue;
    }

    /**
     * Store the date when the account has been added
     *
     * @param DataContainer $dc
     */
    public function storeDateAdded(DataContainer $dc)
    {
        // Return if there is no active record (override all)
        if (!$dc->activeRecord || $dc->activeRecord->dateAdded > 0)
        {
            return;
        }

        // Fallback solution for existing accounts
        if ($dc->activeRecord->lastLogin > 0)
        {
            $time = $dc->activeRecord->lastLogin;
        }
        else
        {
            $time = time();
        }

        $this->Database->prepare("UPDATE tl_user_temp SET dateAdded=? WHERE id=?")
            ->execute($time, $dc->id);
    }

    /**
     * Return the "toggle visibility" button
     *
     * @param array $row
     * @param string $href
     * @param string $label
     * @param string $title
     * @param string $icon
     * @param string $attributes
     *
     * @return string
     */
    public function toggleIcon($row, $href, $label, $title, $icon, $attributes)
    {
        if (\strlen(Input::get('tid')))
        {
            $this->toggleVisibility(Input::get('tid'), (Input::get('state') == 1), (@func_get_arg(12) ?: null));
            $this->redirect($this->getReferer());
        }

        // Check permissions AFTER checking the tid, so hacking attempts are logged
        if (!$this->User->hasAccess('tl_user_temp::disable', 'alexf'))
        {
            return '';
        }

        $href .= '&amp;tid=' . $row['id'] . '&amp;state=' . $row['disable'];

        if ($row['disable'])
        {
            $icon = 'invisible.svg';
        }

        // Protect admin accounts
        if (!$this->User->isAdmin && $row['admin'])
        {
            return Image::getHtml($icon) . ' ';
        }

        return '<a href="' . $this->addToUrl($href) . '" title="' . StringUtil::specialchars($title) . '"' . $attributes . '>' . Image::getHtml($icon, $label, 'data-state="' . ($row['disable'] ? 0 : 1) . '"') . '</a> ';
    }

    /**
     * Disable/enable a user group
     *
     * @param integer $intId
     * @param boolean $blnVisible
     * @param DataContainer $dc
     *
     * @throws Contao\CoreBundle\Exception\AccessDeniedException
     */
    public function toggleVisibility($intId, $blnVisible, DataContainer $dc = null)
    {
        // Set the ID and action
        Input::setGet('id', $intId);
        Input::setGet('act', 'toggle');

        if ($dc)
        {
            $dc->id = $intId; // see #8043
        }

        // Protect own account
        if ($this->User->id == $intId)
        {
            return;
        }

        // Trigger the onload_callback
        if (\is_array($GLOBALS['TL_DCA']['tl_user_temp']['config']['onload_callback']))
        {
            foreach ($GLOBALS['TL_DCA']['tl_user_temp']['config']['onload_callback'] as $callback)
            {
                if (\is_array($callback))
                {
                    $this->import($callback[0]);
                    $this->{$callback[0]}->{$callback[1]}($dc);
                }
                elseif (\is_callable($callback))
                {
                    $callback($dc);
                }
            }
        }

        // Check the field access
        if (!$this->User->hasAccess('tl_user_temp::disable', 'alexf'))
        {
            throw new Contao\CoreBundle\Exception\AccessDeniedException('Not enough permissions to activate/deactivate user ID ' . $intId . '.');
        }

        // Get the current record
        if ($dc)
        {
            $objRow = $this->Database->prepare("SELECT * FROM tl_user_temp WHERE id=?")
                ->limit(1)
                ->execute($intId);

            if ($objRow->numRows)
            {
                $dc->activeRecord = $objRow;
            }
        }

        $objVersions = new Versions('tl_user_temp', $intId);
        $objVersions->initialize();

        // Reverse the logic (users have disable=1)
        $blnVisible = !$blnVisible;

        // Trigger the save_callback
        if (\is_array($GLOBALS['TL_DCA']['tl_user_temp']['fields']['disable']['save_callback']))
        {
            foreach ($GLOBALS['TL_DCA']['tl_user_temp']['fields']['disable']['save_callback'] as $callback)
            {
                if (\is_array($callback))
                {
                    $this->import($callback[0]);
                    $blnVisible = $this->{$callback[0]}->{$callback[1]}($blnVisible, $dc);
                }
                elseif (\is_callable($callback))
                {
                    $blnVisible = $callback($blnVisible, $dc);
                }
            }
        }

        $time = time();

        // Update the database
        $this->Database->prepare("UPDATE tl_user_temp SET tstamp=$time, disable='" . ($blnVisible ? '1' : '') . "' WHERE id=?")
            ->execute($intId);

        if ($dc)
        {
            $dc->activeRecord->tstamp = $time;
            $dc->activeRecord->disable = ($blnVisible ? '1' : '');
        }

        // Trigger the onsubmit_callback
        if (\is_array($GLOBALS['TL_DCA']['tl_user_temp']['config']['onsubmit_callback']))
        {
            foreach ($GLOBALS['TL_DCA']['tl_user_temp']['config']['onsubmit_callback'] as $callback)
            {
                if (\is_array($callback))
                {
                    $this->import($callback[0]);
                    $this->{$callback[0]}->{$callback[1]}($dc);
                }
                elseif (\is_callable($callback))
                {
                    $callback($dc);
                }
            }
        }

        $objVersions->create();
    }
}
