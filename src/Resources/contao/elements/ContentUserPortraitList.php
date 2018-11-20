<?php

/**
 * Contao Open Source CMS
 *
 * Copyright (c) 2005-2017 Leo Feyer
 *
 * @license LGPL-3.0+
 */

namespace Markocupic\SacEventToolBundle;

use Contao\BackendTemplate;
use Contao\ContentElement;
use Contao\Database;
use Contao\FilesModel;
use Contao\FrontendTemplate;
use Contao\StringUtil;
use Contao\UserModel;
use Contao\UserRoleModel;
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
    protected $strTemplate = 'ce_user_portrait_list_multiple';

    /**
     * Partial template
     * @var string
     */
    protected $strTemplatePartial = 'user_portrait_list_partial_multiple';


    /**
     * Return if the image does not exist
     *
     * @return string
     */
    public function generate()
    {


        // Get template
        if ($this->userList_template != '')
        {
            $this->strTemplate = $this->userList_template;
        }

        // Get partial template
        if ($this->userList_partial_template != '')
        {
            $this->strTemplatePartial = $this->userList_partial_template;
        }

        if (TL_MODE == 'BE')
        {
            /** @var BackendTemplate|object $objTemplate */
            $objTemplate = new BackendTemplate('be_wildcard');
            $objTemplate->wildcard = '### ' . Utf8::strtoupper($GLOBALS['TL_LANG']['CTE']['userPortraitList'][0]) . ' ###';
            $objTemplate->title = $this->headline;
            return $objTemplate->parse();
        }


        return parent::generate();
    }


    /**
     * Generate the content element
     */
    protected function compile()
    {
        $arrIDS = array();
        if ($this->userList_selectMode === 'selectUserRoles')
        {
            $arrUserRoles = StringUtil::deserialize($this->userList_userRoles, true);
            $queryType = $this->userList_queryType;
            if (count($arrUserRoles) > 0)
            {
                $objDb = Database::getInstance()->prepare('SELECT * FROM tl_user  WHERE disable=? AND hideInFrontendListings =? ORDER BY lastname ASC, firstname ASC')->execute('', '');

                if ($queryType === 'OR')
                {
                    while ($objDb->next())
                    {
                        $arrUserRole = StringUtil::deserialize($objDb->userRole, true);

                        if (count(array_intersect($arrUserRole, $arrUserRoles)) > 0)
                        {
                            $arrIDS[] = $objDb->id;
                        }
                    }
                }
                elseif ($queryType === 'AND')
                {
                    while ($objDb->next())
                    {
                        $arrUserRole = StringUtil::deserialize($objDb->userRole, true);

                        if (count(array_intersect($arrUserRole, $arrUserRoles)) === count($arrUserRoles))
                        {
                            $arrIDS[] = $objDb->id;
                        }
                    }
                }
            }
        }
        elseif ($this->userList_selectMode === 'selectUsers')
        {
            $ids = StringUtil::deserialize($this->userList_users, true);
            $objUser = UserModel::findMultipleByIds($ids);
            if ($objUser !== null)
            {
                while ($objUser->next())
                {
                    if (!$objUser->disable)
                    {
                        $arrIDS[] = $objUser->id;
                    }
                }
            }
        }


        $objUser = UserModel::findMultipleByIds($arrIDS);

        if ($objUser !== null)
        {

            $strItems = '';
            while ($objUser->next())
            {
                $objTemplate = new FrontendTemplate($this->strTemplatePartial);
                $objTemplate->setData($objUser->row());
                $objTemplate->jumpTo = $this->jumpTo;
                $objTemplate->showFieldsToGuests = StringUtil::deserialize($this->userList_showFieldsToGuests, true);
                $objTemplate->userList_hideRoleEmail = $this->userList_hideRoleEmail;


                // Roles
                $arrIDS = StringUtil::deserialize($objUser->userRole, true);
                $objRoles = UserRoleModel::findMultipleByIds($arrIDS);
                $arrRoleEmails = array();
                $arrRoles = array();
                if ($objRoles !== null)
                {
                    while ($objRoles->next())
                    {
                        $objTemplate->hasRole = true;
                        $arrRoles[] = $objRoles->title;

                        if ($objRoles->email !== '')
                        {
                            $arrRoleEmails[$objRoles->title] = $objRoles->email;
                            $objTemplate->hasRoleEmail = true;
                        }

                        // overwrite private address with role address
                        // Be carefull to only aply this setting once per user
                        $arrAddress = StringUtil::deserialize($this->userList_replacePrivateAdressWithRoleAdress, true);
                        foreach ($arrAddress as $field)
                        {
                            if ($objRoles->{$field} != '')
                            {
                                $objTemplate->{$field} = $objRoles->{$field};
                            }
                        }

                    }
                }
                $objTemplate->roleEmails = $arrRoleEmails;
                $objTemplate->roles = $arrRoles;


                // Get users avatar
                $strAvatarSRC = getAvatar($objUser->id);

                // Add image to template
                if (strlen($strAvatarSRC))
                {
                    $objModel = FilesModel::findByPath($strAvatarSRC);
                    if ($objModel !== null && is_file(TL_ROOT . '/' . $objModel->path))
                    {
                        // Create partial object
                        $objPartial = new \stdClass();
                        $objPartial->uuid = $objModel->uuid;

                        if ($this->imgSize != '')
                        {
                            $size = \StringUtil::deserialize($this->imgSize);

                            if ($size[0] > 0 || $size[1] > 0 || is_numeric($size[2]))
                            {
                                $objPartial->size = $this->imgSize;
                            }
                        }

                        $objPartial->singleSRC = $objModel->path;
                        $arrUser = (array)$objPartial;
                        $objTemplate->addImage = true;

                        $this->addImageToTemplate($objTemplate, $arrUser, null, null, $objModel);
                    }
                }
                $strItems .= $objTemplate->parse();
            }
            $this->Template->items = $strItems;
        }
    }
}
