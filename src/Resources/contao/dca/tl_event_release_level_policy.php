<?php

/**
 * SAC Event Tool Web Plugin for Contao
 * Copyright (c) 2008-2017 Marko Cupic
 * @package sac-event-tool-bundle
 * @author Marko Cupic m.cupic@gmx.ch, 2017-2018
 * @link https://sac-kurse.kletterkader.com
 */


$GLOBALS['TL_DCA']['tl_event_release_level_policy'] = array
(
    'config' => array
    (
        'dataContainer'    => 'Table',
        'ptable'           => 'tl_event_release_level_policy_package',
        'doNotCopyRecords' => true,
        'enableVersioning' => true,
        'switchToEdit'     => true,
        'sql'              => array
        (
            'keys' => array
            (
                'id' => 'primary',
            ),
        ),
    ),

    'list'     => array
    (
        'sorting'           => array
        (
            'mode'                  => 4,
            'fields'                => array('level'),
            'panelLayout'           => 'filter;search,limit',
            'headerFields'          => array('level', 'title'),
            'disableGrouping'       => true,
            'child_record_callback' => array('tl_event_release_level_policy', 'listReleaseLevels'),
        ),
        'label'             => array
        (
            'fields'      => array('level', 'title'),
            'showColumns' => true,
        ),
        'global_operations' => array
        (
            'all' => array
            (
                'label'      => &$GLOBALS['TL_LANG']['MSC']['all'],
                'href'       => 'act=select',
                'class'      => 'header_edit_all',
                'attributes' => 'onclick="Backend.getScrollOffset();"',
            ),
        ),
        'operations'        => array
        (
            'edit'   => array
            (
                'label' => &$GLOBALS['TL_LANG']['tl_event_release_level_policy']['edit'],
                'href'  => 'act=edit',
                'icon'  => 'edit.gif',
            ),
            'copy'   => array
            (
                'label' => &$GLOBALS['TL_LANG']['tl_news']['copy'],
                'href'  => 'act=copy',
                'icon'  => 'copy.gif',
            ),
            'delete' => array
            (
                'label'      => &$GLOBALS['TL_LANG']['tl_event_release_level_policy']['delete'],
                'href'       => 'act=delete',
                'icon'       => 'delete.gif',
                'attributes' => 'onclick="if (!confirm(\'' . $GLOBALS['TL_LANG']['MSC']['deleteConfirm'] . '\')) return false; Backend.getScrollOffset();"',
            ),
        ),
    ),
    'palettes' => array
    (
        'default' => 'level,title,description,allowWriteAccessToAuthor,allowWriteAccessToInstructors,allowSwitchingToPrevLevel,allowSwitchingToNextLevel,groupReleaseLevelRights',
    ),

    'fields' => array
    (
        'id'                            => array
        (
            'sql' => "int(10) unsigned NOT NULL auto_increment",
        ),
        'pid'                           => array
        (
            'foreignKey' => 'tl_event_release_level_policy_package.title',
            'sql'        => "int(10) unsigned NOT NULL default '0'",
            'relation'   => array('type' => 'belongsTo', 'load' => 'eager'),
        ),
        'tstamp'                        => array
        (
            'sql' => "int(10) unsigned NOT NULL default '0'",
        ),
        'level'                         => array
        (
            'label'     => &$GLOBALS['TL_LANG']['tl_event_release_level_policy']['level'],
            'exclude'   => true,
            'inputType' => 'select',
            'options'   => range(1, 10),
            'eval'      => array('mandatory' => true, 'tl_class' => 'clr'),
            'sql'       => "smallint(2) unsigned NOT NULL default '0'",
        ),
        'title'                         => array
        (
            'label'     => &$GLOBALS['TL_LANG']['tl_event_release_level_policy']['title'],
            'exclude'   => true,
            'inputType' => 'text',
            'eval'      => array('mandatory' => true, 'maxlength' => 255, 'tl_class' => 'clr'),
            'sql'       => "varchar(255) NOT NULL default ''",
        ),
        'description'                   => array
        (
            'label'     => &$GLOBALS['TL_LANG']['tl_event_release_level_policy']['description'],
            'exclude'   => true,
            'inputType' => 'textarea',
            'eval'      => array('mandatory' => true, 'tl_class' => 'clr'),
            'sql'       => "text NULL",
        ),
        'allowSwitchingToPrevLevel'     => array
        (
            'label'     => &$GLOBALS['TL_LANG']['tl_event_release_level_policy']['allowSwitchingToPrevLevel'],
            'exclude'   => true,
            'filter'    => true,
            'inputType' => 'checkbox',
            'sql'       => "char(1) NOT NULL default ''",
        ),
        'allowSwitchingToNextLevel'     => array
        (
            'label'     => &$GLOBALS['TL_LANG']['tl_event_release_level_policy']['allowSwitchingToNextLevel'],
            'exclude'   => true,
            'filter'    => true,
            'inputType' => 'checkbox',
            'sql'       => "char(1) NOT NULL default ''",
        ),
        'allowWriteAccessToAuthor'      => array
        (
            'label'     => &$GLOBALS['TL_LANG']['tl_event_release_level_policy']['allowWriteAccessToAuthor'],
            'exclude'   => true,
            'filter'    => true,
            'inputType' => 'checkbox',
            'sql'       => "char(1) NOT NULL default ''",
        ),
        'allowWriteAccessToAuthor'      => array
        (
            'label'     => &$GLOBALS['TL_LANG']['tl_event_release_level_policy']['allowWriteAccessToAuthor'],
            'exclude'   => true,
            'filter'    => true,
            'inputType' => 'checkbox',
            'sql'       => "char(1) NOT NULL default ''",
        ),
        'allowWriteAccessToInstructors' => array
        (
            'label'     => &$GLOBALS['TL_LANG']['tl_event_release_level_policy']['allowWriteAccessToInstructors'],
            'exclude'   => true,
            'filter'    => true,
            'inputType' => 'checkbox',
            'sql'       => "char(1) NOT NULL default ''",
        ),
        'groupReleaseLevelRights'       => array(
            'label'     => &$GLOBALS['TL_LANG']['tl_event_release_level_policy']['groupReleaseLevelRights'],
            'exclude'   => true,
            'inputType' => 'multiColumnWizard',
            'eval'      => array
            (
                'mandatory'    => false,
                'columnFields' => array
                (
                    'group'              => array
                    (
                        'label'      => &$GLOBALS['TL_LANG']['tl_event_release_level_policy']['group'],
                        'exclude'    => true,
                        'inputType'  => 'select',
                        'reference'  => &$GLOBALS['TL_LANG']['tl_event_release_level_policy'],
                        'relation'   => array('type' => 'hasMany', 'load' => 'eager'),
                        'foreignKey' => 'tl_user_group.name',
                        'eval'       => array
                        (
                            'style'              => 'width:250px',
                            'mandatory'          => true,
                            'includeBlankOption' => true,
                        ),
                    ),
                    'releaseLevelRights' => array
                    (
                        'label'     => &$GLOBALS['TL_LANG']['tl_event_release_level_policy']['releaseLevelRights'],
                        'exclude'   => true,
                        'inputType' => 'select',
                        'reference' => &$GLOBALS['TL_LANG']['tl_event_release_level_policy'],
                        'options'   => array('up', 'down', 'upAndDown'),
                        'eval'      => array
                        (
                            'style'              => 'width:250px',
                            'mandatory'          => true,
                            'includeBlankOption' => true,
                        ),
                    ),
                    'writeAccess'        => array
                    (
                        'label'     => &$GLOBALS['TL_LANG']['tl_event_release_level_policy']['writeAccess'],
                        'exclude'   => true,
                        'inputType' => 'checkbox',
                        'eval'      => array(
                            'style' => 'width:100px',
                        ),
                    ),
                ),
            ),
            'sql'       => "blob NULL",
        ),
    ),
);

