<?php

declare(strict_types=1);

/*
 * This file is part of SAC Event Tool Bundle.
 *
 * (c) Marko Cupic 2023 <m.cupic@gmx.ch>
 * @license GPL-3.0-or-later
 * For the full copyright and license information,
 * please view the LICENSE file that was distributed with this source code.
 * @link https://github.com/markocupic/sac-event-tool-bundle
 */

namespace Markocupic\SacEventToolBundle\Controller\ContentElement;

use Contao\ContentModel;
use Contao\Controller;
use Contao\CoreBundle\Controller\ContentElement\AbstractContentElementController;
use Contao\CoreBundle\DependencyInjection\Attribute\AsContentElement;
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\FrontendTemplate;
use Contao\FrontendUser;
use Contao\PageModel;
use Contao\StringUtil;
use Contao\Template;
use Contao\UserModel;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;
use Markocupic\SacEventToolBundle\Avatar\Avatar;
use Markocupic\SacEventToolBundle\Model\UserRoleModel;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Security;

#[AsContentElement(UserPortraitListController::TYPE, category:'sac_event_tool_content_elements', template:'ce_user_portrait_list')]
class UserPortraitListController extends AbstractContentElementController
{
    public const TYPE = 'user_portrait_list';

    private ContaoFramework $framework;
    private Connection $connection;
	private Security $security;
	private Avatar $avatar;
    private string $projectDir;

	public function __construct(ContaoFramework $framework, Connection $connection, Security $security, Avatar $avatar, string $projectDir)
    {
        $this->framework = $framework;
        $this->connection = $connection;
		$this->security = $security;
        $this->avatar = $avatar;
        $this->projectDir = $projectDir;
	}

    public function __invoke(Request $request, ContentModel $model, string $section, array $classes = null, PageModel $pageModel = null): Response
    {
        return parent::__invoke($request, $model, $section, $classes);
    }

    /**
     * @throws Exception
     */
    protected function getResponse(Template $template, ContentModel $model, Request $request): Response|null
    {
        /** @var UserModel $userModelAdapter */
        $userModelAdapter = $this->framework->getAdapter(UserModel::class);

        /** @var Controller $controllerAdapter */
        $controllerAdapter = $this->framework->getAdapter(Controller::class);

        /** @var StringUtil $stringUtilAdapter */
        $stringUtilAdapter = $this->framework->getAdapter(StringUtil::class);

        /** @var UserRoleModel $userRoleModelAdapter */
        $userRoleModelAdapter = $this->framework->getAdapter(UserRoleModel::class);

        // Get template
        if ('' !== $model->userList_template) {
            $template->strTemplate = $model->userList_template;
        }

        $arrIDS = [];
        $arrSelectedRoles = $stringUtilAdapter->deserialize($model->userList_userRoles, true);

        if ('selectUserRoles' === $model->userList_selectMode) {
            $queryType = $model->userList_queryType;

            if (\count($arrSelectedRoles) > 0) {
                $stmt = $this->connection->executeQuery('SELECT * FROM tl_user  WHERE disable = ? AND hideInFrontendListings = ? ORDER BY lastname ASC, firstname ASC', ['', '']);

                if ('OR' === $queryType) {
                    while (false !== ($arrUser = $stmt->fetchAssociative())) {
                        $arrUserRole = $stringUtilAdapter->deserialize($arrUser['userRole'], true);

                        if (\count(array_intersect($arrUserRole, $arrSelectedRoles)) > 0) {
                            $arrIDS[] = $arrUser['id'];
                        }
                    }
                } elseif ('AND' === $queryType) {
                    while (false !== ($arrUser = $stmt->fetchAssociative())) {
                        $arrUserRole = $stringUtilAdapter->deserialize($arrUser['userRole'], true);

                        if (\count(array_intersect($arrUserRole, $arrSelectedRoles)) === \count($arrSelectedRoles)) {
                            $arrIDS[] = $arrUser['id'];
                        }
                    }
                }
            }
        } elseif ('selectUsers' === $model->userList_selectMode) {
            $ids = $stringUtilAdapter->deserialize($model->userList_users, true);
            $objUser = $userModelAdapter->findMultipleByIds($ids);

            if (null !== $objUser) {
                while ($objUser->next()) {
                    if (!$objUser->disable) {
                        $arrIDS[] = $objUser->id;
                    }
                }
            }
        }

        $objUser = $userModelAdapter->findMultipleByIds($arrIDS);

        $itemCount = 0;

        if (null !== $objUser) {
            $strItems = '';

            while ($objUser->next()) {
                ++$itemCount;

                // Get partial template
                $strTemplatePartial = $model->userList_partial_template;

                $objTemplate = new FrontendTemplate($strTemplatePartial);
                $objTemplate->setData($objUser->row());
                $objTemplate->jumpTo = $model->jumpTo;
                $objTemplate->showFieldsToGuests = $stringUtilAdapter->deserialize($model->userList_showFieldsToGuests, true);

                // Roles
                $arrIDS = $stringUtilAdapter->deserialize($objUser->userRole, true);
                $objRoles = $userRoleModelAdapter->findMultipleByIds($arrIDS);
                $arrRoleEmails = [];
                $arrRoles = [];

                if (null !== $objRoles) {
                    while ($objRoles->next()) {
                        if (!\in_array($objRoles->id, $arrSelectedRoles, false)) {
                            continue;
                        }

                        $objTemplate->hasRole = true;
                        $arrRoles[] = $objRoles->title;

                        if ('' !== $objRoles->email) {
                            $arrRoleEmails[$objRoles->title] = $objRoles->email;
                            $objTemplate->hasRoleEmail = true;
                        }

                        // Overwrite private address with role address
                        // Be carefull to only aply this setting once per user
                        $arrAddress = $stringUtilAdapter->deserialize($model->userList_replacePrivateAdressWithRoleAdress, true);

                        foreach ($arrAddress as $field) {
                            if ('' !== $objRoles->{$field}) {
                                $objTemplate->{$field} = $objRoles->{$field};
                            }
                        }
                    }
                }

                $objTemplate->roleEmails = $arrRoleEmails;
                $objTemplate->roles = $arrRoles;

                // Get users avatar
                $strAvatarSRC = $this->avatar->getAvatarResourcePath($objUser->current());

                // Add image to template
                if (\strlen($strAvatarSRC)) {
                    if (is_file($this->projectDir.'/'.$strAvatarSRC)) {
                        // Create partial object
                        $objPartial = new \stdClass();

                        if ('' !== $model->imgSize) {
                            $size = $stringUtilAdapter->deserialize($model->imgSize);

                            if ($size[0] > 0 || $size[1] > 0 || is_numeric($size[2])) {
                                $objPartial->size = $model->imgSize;
                            }
                        }

                        $objPartial->singleSRC = $strAvatarSRC;
                        $arrUser = (array) $objPartial;
                        $objTemplate->addImage = true;

                        $controllerAdapter->addImageToTemplate($objTemplate, $arrUser);
                    }
                }
                $strItems .= $objTemplate->parse();
            }
            $template->items = $strItems;
        }

		$user = $this->security->getUser();
		$template->hasLoggedInFrontendUser = $user instanceof FrontendUser;

		$template->hasMultiple = $itemCount > 1;
        $template->itemCount = $itemCount;

        return $template->getResponse();
    }
}
