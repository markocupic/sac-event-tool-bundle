<?php

declare(strict_types=1);

/**
 * SAC Event Tool Web Plugin for Contao
 * Copyright (c) 2008-2020 Marko Cupic
 * @package sac-event-tool-bundle
 * @author Marko Cupic m.cupic@gmx.ch, 2017-2020
 * @link https://github.com/markocupic/sac-event-tool-bundle
 */

namespace Markocupic\SacEventToolBundle\Controller\ContentElement;

use Contao\ContentModel;
use Contao\Controller;
use Contao\CoreBundle\Controller\ContentElement\AbstractContentElementController;
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\Database;
use Contao\FilesModel;
use Contao\FrontendTemplate;
use Contao\StringUtil;
use Contao\System;
use Contao\Template;
use Contao\UserModel;
use Contao\PageModel;
use Contao\UserRoleModel;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Contao\CoreBundle\ServiceAnnotation\ContentElement;

/**
 * Class UserPortraitListController
 * @package Markocupic\SacEventToolBundle\Controller\ContentElement
 * @ContentElement("user_portrait_list", category="sac_event_tool_content_elements", template="ce_user_portrait_list")
 */
class UserPortraitListController extends AbstractContentElementController
{

    /**
     * @param Request $request
     * @param ContentModel $model
     * @param string $section
     * @param array|null $classes
     * @param PageModel|null $pageModel
     * @return Response
     */
    public function __invoke(Request $request, ContentModel $model, string $section, array $classes = null, ?PageModel $pageModel = null): Response
    {
        return parent::__invoke($request, $model, $section, $classes, $pageModel);
    }

    /**
     * @return array
     */
    public static function getSubscribedServices(): array
    {
        $services = parent::getSubscribedServices();
        $services['contao.framework'] = ContaoFramework::class;

        return $services;
    }

    /**
     * @param Template $template
     * @param ContentModel $model
     * @param Request $request
     * @return null|Response
     */
    protected function getResponse(Template $template, ContentModel $model, Request $request): ?Response
    {
        /** @var  FilesModel $filesModelAdapter */
        $filesModelAdapter = $this->get('contao.framework')->getAdapter(FilesModel::class);

        /** @var  Database $databaseAdapter */
        $databaseAdapter = $this->get('contao.framework')->getAdapter(Database::class);

        /** @var  UserModel $userModelAdapter */
        $userModelAdapter = $this->get('contao.framework')->getAdapter(UserModel::class);

        /** @var Controller $controllerAdapter */
        $controllerAdapter = $this->get('contao.framework')->getAdapter(Controller::class);

        /** @var Controller $stringUtilAdapter */
        $stringUtilAdapter = $this->get('contao.framework')->getAdapter(StringUtil::class);

        /** @var  UserRoleModel $userRoleModelAdapter */
        $userRoleModelAdapter = $this->get('contao.framework')->getAdapter(UserRoleModel::class);

        $projectDir = System::getContainer()->getParameter('kernel.project_dir');

        // Get template
        if ($model->userList_template != '')
        {
            $template->strTemplate = $model->userList_template;
        }

        $arrIDS = array();
        $arrSelectedRoles = $stringUtilAdapter->deserialize($model->userList_userRoles, true);
        if ($model->userList_selectMode === 'selectUserRoles')
        {
            $queryType = $model->userList_queryType;
            if (count($arrSelectedRoles) > 0)
            {
                $objDb = $databaseAdapter->getInstance()->prepare('SELECT * FROM tl_user  WHERE disable=? AND hideInFrontendListings =? ORDER BY lastname ASC, firstname ASC')->execute('', '');

                if ($queryType === 'OR')
                {
                    while ($objDb->next())
                    {
                        $arrUserRole = $stringUtilAdapter->deserialize($objDb->userRole, true);

                        if (count(array_intersect($arrUserRole, $arrSelectedRoles)) > 0)
                        {
                            $arrIDS[] = $objDb->id;
                        }
                    }
                }
                elseif ($queryType === 'AND')
                {
                    while ($objDb->next())
                    {
                        $arrUserRole = $stringUtilAdapter->deserialize($objDb->userRole, true);

                        if (count(array_intersect($arrUserRole, $arrSelectedRoles)) === count($arrSelectedRoles))
                        {
                            $arrIDS[] = $objDb->id;
                        }
                    }
                }
            }
        }
        elseif ($model->userList_selectMode === 'selectUsers')
        {
            $ids = $stringUtilAdapter->deserialize($model->userList_users, true);
            $objUser = $userModelAdapter->findMultipleByIds($ids);
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

        $objUser = $userModelAdapter->findMultipleByIds($arrIDS);

        if ($objUser !== null)
        {
            $itemCount = 0;
            $strItems = '';
            while ($objUser->next())
            {
                $itemCount++;

                // Get partial template
                if ($model->userList_partial_template != '')
                {
                    $strTemplatePartial = $model->userList_partial_template;
                }
                /** @var  FrontendTemplate $objTemplate */
                $objTemplate = new FrontendTemplate($strTemplatePartial);
                $objTemplate->setData($objUser->row());
                $objTemplate->jumpTo = $model->jumpTo;
                $objTemplate->showFieldsToGuests = $stringUtilAdapter->deserialize($model->userList_showFieldsToGuests, true);

                // Roles
                $arrIDS = $stringUtilAdapter->deserialize($objUser->userRole, true);
                $objRoles = $userRoleModelAdapter->findMultipleByIds($arrIDS);
                $arrRoleEmails = array();
                $arrRoles = array();
                if ($objRoles !== null)
                {
                    while ($objRoles->next())
                    {
                        if (!in_array($objRoles->id, $arrSelectedRoles,false))
                        {
                            continue;
                        }

                        $objTemplate->hasRole = true;
                        $arrRoles[] = $objRoles->title;

                        if ($objRoles->email !== '')
                        {
                            $arrRoleEmails[$objRoles->title] = $objRoles->email;
                            $objTemplate->hasRoleEmail = true;
                        }

                        // Overwrite private address with role address
                        // Be carefull to only aply this setting once per user
                        $arrAddress = $stringUtilAdapter->deserialize($model->userList_replacePrivateAdressWithRoleAdress, true);
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
                    $objModel = $filesModelAdapter->findByPath($strAvatarSRC);
                    if ($objModel !== null && is_file($projectDir . '/' . $objModel->path))
                    {
                        // Create partial object
                        $objPartial = new \stdClass();
                        $objPartial->uuid = $objModel->uuid;

                        if ($model->imgSize != '')
                        {
                            $size = $stringUtilAdapter->deserialize($model->imgSize);

                            if ($size[0] > 0 || $size[1] > 0 || is_numeric($size[2]))
                            {
                                $objPartial->size = $model->imgSize;
                            }
                        }

                        $objPartial->singleSRC = $objModel->path;
                        $arrUser = (array)$objPartial;
                        $objTemplate->addImage = true;

                        $controllerAdapter->addImageToTemplate($objTemplate, $arrUser, null, null, $objModel);
                    }
                }
                $strItems .= $objTemplate->parse();
            }
            $template->items = $strItems;
        }

        $template->hasMultiple = $itemCount > 1 ? true : false;
        $template->itemCount = $itemCount;

        return $template->getResponse();
    }

}
