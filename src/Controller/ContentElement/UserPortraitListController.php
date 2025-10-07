<?php

declare(strict_types=1);

/*
 * This file is part of SAC Event Tool Bundle.
 *
 * (c) Marko Cupic <m.cupic@gmx.ch>
 * @license GPL-3.0-or-later
 * For the full copyright and license information,
 * please view the LICENSE file that was distributed with this source code.
 * @link https://github.com/markocupic/sac-event-tool-bundle
 */

namespace Markocupic\SacEventToolBundle\Controller\ContentElement;

use Contao\ContentModel;
use Contao\CoreBundle\Controller\ContentElement\AbstractContentElementController;
use Contao\CoreBundle\DependencyInjection\Attribute\AsContentElement;
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\CoreBundle\Twig\FragmentTemplate;
use Contao\PageModel;
use Contao\StringUtil;
use Contao\UserModel;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Types\Types;
use Markocupic\SacEventToolBundle\Avatar\Avatar;
use Markocupic\SacEventToolBundle\Model\UserRoleModel;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Twig\Environment;

#[AsContentElement(UserPortraitListController::TYPE, category: 'sac_event_tool_content_elements')]
class UserPortraitListController extends AbstractContentElementController
{
    public const string TYPE = 'user_portrait_list';

    public const string PARTIAL_TEMPLATE = '@Contao_MarkocupicSacEventToolBundle/content_element_partials/user_portrait_list/item.html.twig';

    public function __construct(
        private readonly Avatar $avatar,
        private readonly Connection $connection,
        private readonly ContaoFramework $framework,
        private readonly Environment $twig,
        private readonly string $projectDir,
    ) {
    }

    public function __invoke(Request $request, ContentModel $model, string $section, array|null $classes = null, PageModel|null $pageModel = null): Response
    {
        return parent::__invoke($request, $model, $section, $classes);
    }

    protected function getResponse(FragmentTemplate $template, ContentModel $model, Request $request): Response
    {
        $userModelAdapter = $this->framework->getAdapter(UserModel::class);
        $stringUtilAdapter = $this->framework->getAdapter(StringUtil::class);
        $userRoleModelAdapter = $this->framework->getAdapter(UserRoleModel::class);

        $arrIDS = [];
        $arrSelectedRoles = $stringUtilAdapter->deserialize($model->userList_userRoles, true);

        if ('selectUserRoles' === $model->userList_selectMode) {
            $queryType = $model->userList_queryType;

            if (\count($arrSelectedRoles) > 0) {
                $arrUsers = $this->connection->fetchAllAssociative(
                    'SELECT * FROM tl_user WHERE disable = 0 AND (stop = "" OR stop > ?) AND hideUser = 0 ORDER BY lastname, firstname',
                    [
                        time(),
                    ],
                    [
                        Types::INTEGER,
                    ],
                );

                if ('OR' === $queryType) {
                    foreach ($arrUsers as $arrUser) {
                        $arrUserRole = $stringUtilAdapter->deserialize($arrUser['userRole'], true);

                        if (\count(array_intersect($arrUserRole, $arrSelectedRoles)) > 0) {
                            $arrIDS[] = $arrUser['id'];
                        }
                    }
                } elseif ('AND' === $queryType) {
                    foreach ($arrUsers as $arrUser) {
                        $arrUserRole = $stringUtilAdapter->deserialize($arrUser['userRole'], true);

                        if (\count(array_intersect($arrUserRole, $arrSelectedRoles)) === \count($arrSelectedRoles)) {
                            $arrIDS[] = $arrUser['id'];
                        }
                    }
                }
            }
        } elseif ('selectUsers' === $model->userList_selectMode) {
            $ids = $stringUtilAdapter->deserialize($model->userList_users, true);
            $user = $userModelAdapter->findMultipleByIds($ids);

            if (null !== $user) {
                while ($user->next()) {
                    if ('' !== $user->stop && $user->stop < time()) {
                        continue;
                    }

                    if ($user->disable) {
                        continue;
                    }

                    $arrIDS[] = $user->id;
                }
            }
        }

        $user = $userModelAdapter->findMultipleByIds($arrIDS);

        if (null !== $user) {
            $items = [];

            while ($user->next()) {
                // Build the partial template
                $partialTemplate = $user->row();
                $partialTemplate['jumpTo'] = $model->jumpTo;
                $partialTemplate['showFieldsToGuests'] = $stringUtilAdapter->deserialize($model->userList_showFieldsToGuests, true);

                // Roles
                $arrIDS = $stringUtilAdapter->deserialize($user->userRole, true);
                $roleCollection = $userRoleModelAdapter->findMultipleByIds($arrIDS);
                $arrRoleEmails = [];
                $roles = [];

                if (null !== $roleCollection) {
                    while ($roleCollection->next()) {
                        $role = $roleCollection->current();
                        if (!\in_array($role->id, $arrSelectedRoles, false)) {
                            continue;
                        }

                        $partialTemplate['hasRole'] = true;
                        $roles[] = $role->title;

                        if ('' !== $role->email) {
                            $arrRoleEmails[$role->title] = $role->email;
                            $partialTemplate['hasRoleEmail'] = true;
                        }

                        // Override the private address with the role address. Be careful to only apply
                        // this setting once per user.
                        $arrAddress = $stringUtilAdapter->deserialize($model->userList_replacePrivateAdressWithRoleAdress, true);

                        foreach ($arrAddress as $field) {
                            if ('' !== $role->{$field}) {
                                $partialTemplate[$field] = $role->{$field};
                            }
                        }
                    }
                }

                $partialTemplate['roleEmails'] = $arrRoleEmails;
                $partialTemplate['roles'] = $roles;

                // Get the user profile picture
                $avatarSourcePath = $this->avatar->getAvatarResourcePath($user->current());

                // Add the user profile picture
                if (\strlen($avatarSourcePath)) {
                    if (is_file($this->projectDir.'/'.$avatarSourcePath)) {
                        $partialTemplate['addImage'] = true;
                        $partialTemplate['imgSize'] = $model->imgSize;
                        $partialTemplate['singleSRC'] = $avatarSourcePath;
                    }
                }

                $items[] = $this->twig->render(self::PARTIAL_TEMPLATE, $partialTemplate);
            }

            $template->set('items', implode('', $items));
        }

        return $template->getResponse();
    }
}
