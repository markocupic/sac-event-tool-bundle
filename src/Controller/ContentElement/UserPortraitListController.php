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
use Contao\CoreBundle\Twig\FragmentTemplate;
use Contao\Model\Collection;
use Contao\PageModel;
use Contao\StringUtil;
use Contao\UserModel;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Types\Types;
use Markocupic\SacEventToolBundle\Avatar\Avatar;
use Markocupic\SacEventToolBundle\Model\UserRoleModel;
use Symfony\Component\Filesystem\Path;
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
        $stringUtilAdapter = $this->getContaoAdapter(StringUtil::class);

        $userCollection = $this->fetchUsers($model);

        if (null !== $userCollection) {
            $items = [];
            $selectedRoleIds = $stringUtilAdapter->deserialize($model->userList_userRoles, true);

            while ($userCollection->next()) {
                $user = $userCollection->current();

                // Build the partial template
                $partialTemplate = $user->row();
                $partialTemplate['jumpTo'] = $model->jumpTo;
                $partialTemplate['showFieldsToGuests'] = $stringUtilAdapter->deserialize($model->userList_showFieldsToGuests, true);

                // Roles
                $roleIds = $stringUtilAdapter->deserialize($user->userRole, true);
                $roleCollection = $this->getContaoAdapter(UserRoleModel::class)->findMultipleByIds($roleIds);
                $roleEmails = [];
                $roles = [];

                if (null !== $roleCollection) {
                    while ($roleCollection->next()) {
                        $role = $roleCollection->current();
                        if (!\in_array($role->id, $selectedRoleIds, false)) {
                            continue;
                        }

                        $partialTemplate['hasRole'] = true;
                        $roles[] = $role->title;

                        if ('' !== $role->email) {
                            $roleEmails[$role->title] = $role->email;
                            $partialTemplate['hasRoleEmail'] = true;
                        }

                        // Override the private address with the role address. Be careful to only apply
                        // this setting once per user.
                        $addressFields = $stringUtilAdapter->deserialize($model->userList_replacePrivateAdressWithRoleAdress, true);

                        foreach ($addressFields as $field) {
                            if ('' !== $role->{$field}) {
                                $partialTemplate[$field] = $role->{$field};
                            }
                        }
                    }
                }

                $partialTemplate['roleEmails'] = $roleEmails;
                $partialTemplate['roles'] = $roles;

                // Get the user profile picture
                $avatarSourcePath = $this->avatar->getAvatarResourcePath($user->current());

                // Add the user profile picture
                if (\strlen($avatarSourcePath)) {
                    if (is_file(Path::join($this->projectDir, $avatarSourcePath))) {
                        $partialTemplate['addImage'] = true;
                        $partialTemplate['imgSize'] = $stringUtilAdapter->deserialize($model->imgSize, true);
                        $partialTemplate['singleSRC'] = $avatarSourcePath;
                    }
                }

                $items[] = $this->twig->render(self::PARTIAL_TEMPLATE, $partialTemplate);
            }

            $template->set('items', implode('', $items));
        }

        return $template->getResponse();
    }

    private function fetchUsers(ContentModel $model): Collection|null
    {
        $userIds = [];
        $stringUtilAdapter = $this->getContaoAdapter(StringUtil::class);

        if ('selectUserRoles' === $model->userList_selectMode) {
            $queryType = $model->userList_queryType;
            $selectedRoleIds = $stringUtilAdapter->deserialize($model->userList_userRoles, true);

            if (\count($selectedRoleIds) > 0) {
                $users = $this->connection->fetchAllAssociative(
                    'SELECT * FROM tl_user WHERE disable = 0 AND (stop = "" OR stop > ?) AND hideUser = 0 ORDER BY lastname, firstname',
                    [
                        time(),
                    ],
                    [
                        Types::INTEGER,
                    ],
                );

                if ('OR' === $queryType) {
                    foreach ($users as $user) {
                        $userRoleIds = $stringUtilAdapter->deserialize($user['userRole'], true);

                        if (\count(array_intersect($userRoleIds, $selectedRoleIds)) > 0) {
                            $userIds[] = $user['id'];
                        }
                    }
                } elseif ('AND' === $queryType) {
                    foreach ($users as $user) {
                        $userRoleIds = $stringUtilAdapter->deserialize($user['userRole'], true);

                        if (\count(array_intersect($userRoleIds, $selectedRoleIds)) === \count($selectedRoleIds)) {
                            $userIds[] = $user['id'];
                        }
                    }
                }
            }
        } elseif ('selectUsers' === $model->userList_selectMode) {
            $ids = $stringUtilAdapter->deserialize($model->userList_users, true);
            $userCollection = $this->getContaoAdapter(UserModel::class)->findMultipleByIds($ids);

            if (null !== $userCollection) {
                while ($userCollection->next()) {
                    $user = $userCollection->current();

                    if ('' !== $user->stop && $user->stop < time()) {
                        continue;
                    }

                    if ($user->disable) {
                        continue;
                    }

                    $userIds[] = $user->id;
                }
            }
        }

        return $this->getContaoAdapter(UserModel::class)->findMultipleByIds($userIds);
    }
}
