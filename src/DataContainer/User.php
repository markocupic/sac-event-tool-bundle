<?php

declare(strict_types=1);

/*
 * This file is part of SAC Event Tool Bundle.
 *
 * (c) Marko Cupic 2022 <m.cupic@gmx.ch>
 * @license GPL-3.0-or-later
 * For the full copyright and license information,
 * please view the LICENSE file that was distributed with this source code.
 * @link https://github.com/markocupic/sac-event-tool-bundle
 */

namespace Markocupic\SacEventToolBundle\DataContainer;

use Contao\BackendUser;
use Contao\Config;
use Contao\Controller;
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\CoreBundle\ServiceAnnotation\Callback;
use Contao\DataContainer;
use Contao\Message;
use Contao\UserModel;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Driver\Exception;
use Markocupic\SacEventToolBundle\User\BackendUser\MaintainBackendUsersHomeDirectory;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Security\Core\Security;
use Symfony\Contracts\Translation\TranslatorInterface;

class User
{
    private ContaoFramework $framework;
    private RequestStack $requestStack;
    private Connection $connection;
    private Security $security;
    private TranslatorInterface $translator;
    private MaintainBackendUsersHomeDirectory $maintainBackendUsersHomeDirectory;

    /**
     * Import the back end user object.
     */
    public function __construct(ContaoFramework $framework, RequestStack $requestStack, Connection $connection, Security $security, TranslatorInterface $translator, MaintainBackendUsersHomeDirectory $maintainBackendUsersHomeDirectory)
    {
        $this->framework = $framework;
        $this->requestStack = $requestStack;
        $this->connection = $connection;
        $this->security = $security;
        $this->translator = $translator;
        $this->maintainBackendUsersHomeDirectory = $maintainBackendUsersHomeDirectory;
    }

    /**
     * Add backend assets.
     *
     * @Callback(table="tl_user", target="config.onload")
     */
    public function addBackendAssets(DataContainer $dc): void
    {
        $request = $this->requestStack->getCurrentRequest();

        if ('user' === $request->query->get('do') && 'edit' === $request->query->get('act') && '' !== $request->query->get('ref')) {
            $GLOBALS['TL_JAVASCRIPT'][] = 'bundles/markocupicsaceventtool/js/backend_member_autocomplete.js';
        }
    }

    /**
     * Make fields readonly in the user profile section of the Contao backend.
     *
     * @throws Exception
     * @throws \Doctrine\DBAL\Exception
     * @Callback(table="tl_user", target="config.onload")
     */
    public function addReadonlyAttributeToSyncedFields(DataContainer $dc): void
    {
        $user = $this->security->getUser();

        if (!$user instanceof BackendUser) {
            return;
        }

        $request = $this->requestStack->getCurrentRequest();

        if ('login' === $request->query->get('do')) {
            $id = $user->id;
        } else {
            $id = $dc->id;
        }

        if ($id > 0) {
            if (!$user->admin) {
                if ((int) $user->sacMemberId > 0) {
                    $stmt = $this->connection->executeQuery(
                        'SELECT * FROM tl_member WHERE sacMemberId = ? LIMIT 0, 1',
                        [$user->sacMemberId],
                    );

                    if (false !== ($arrMember = $stmt->fetchAssociative())) {
                        if (!$arrMember['disable']) {
                            $GLOBALS['TL_DCA']['tl_user']['fields']['gender']['eval']['readonly'] = true;
                            $GLOBALS['TL_DCA']['tl_user']['fields']['firstname']['eval']['readonly'] = true;
                            $GLOBALS['TL_DCA']['tl_user']['fields']['lastname']['eval']['readonly'] = true;
                            $GLOBALS['TL_DCA']['tl_user']['fields']['name']['eval']['readonly'] = true;
                            $GLOBALS['TL_DCA']['tl_user']['fields']['email']['eval']['readonly'] = true;
                            $GLOBALS['TL_DCA']['tl_user']['fields']['phone']['eval']['readonly'] = true;
                            $GLOBALS['TL_DCA']['tl_user']['fields']['mobile']['eval']['readonly'] = true;
                            $GLOBALS['TL_DCA']['tl_user']['fields']['street']['eval']['readonly'] = true;
                            $GLOBALS['TL_DCA']['tl_user']['fields']['postal']['eval']['readonly'] = true;
                            $GLOBALS['TL_DCA']['tl_user']['fields']['city']['eval']['readonly'] = true;
                            $GLOBALS['TL_DCA']['tl_user']['fields']['dateOfBirth']['eval']['readonly'] = true;
                        }
                    }
                }
            }
        }
    }

    /**
     * Show message in the user profile section of the Contao backend.
     *
     * @Callback(table="tl_user", target="config.onload")
     */
    public function showReadonlyFieldsInfoMessage(DataContainer $dc): void
    {
        $request = $this->requestStack->getCurrentRequest();

        if (!$dc->id || 'login' !== $request->query->get('do')) {
            return;
        }

        $messageAdapter = $this->framework->getAdapter(Message::class);

        $messageAdapter->addInfo(
            $this->translator->trans('MSC.bmd_howToEditReadonlyProfileData', [], 'contao_default')
        );
    }

    /**
     * Show message in the user profile section of the Contao backend.
     *
     * @Callback(table="tl_user", target="config.oncreate")
     */
    public function oncreateCallback(string $strTable, int $id, array $arrSet): void
    {
        $configAdapter = $this->framework->getAdapter(Config::class);
        $userModelAdapter = $this->framework->getAdapter(UserModel::class);
        $controllerAdapter = $this->framework->getAdapter(Controller::class);

        $objUser = $userModelAdapter->findByPk($id);

        if (null !== $objUser) {
            // Create backend users home directory
            $this->maintainBackendUsersHomeDirectory->createBackendUsersHomeDirectory($objUser);

            if ('extend' !== $arrSet['inherit']) {
                $objUser->inherit = 'extend';
                $objUser->pwChange = '1';
                $defaultPassword = $configAdapter->get('SAC_EVT_DEFAULT_BACKEND_PASSWORD');
                $objUser->password = password_hash($defaultPassword, PASSWORD_DEFAULT);
                $objUser->tstamp = 0;
                $objUser->save();
                $controllerAdapter->reload();
            }
        }
    }

    /**
     * @throws Exception
     * @throws \Doctrine\DBAL\Exception
     *
     * @Callback(table="tl_user", target="fields.userRole.options")
     */
    public function optionsCallbackUserRoles(): array
    {
        $options = [];

        $stmt = $this->connection->executeQuery('SELECT * FROM tl_user_role ORDER BY sorting ASC');

        while (false !== ($row = $stmt->fetchAssociative())) {
            $options[$row['id']] = $row['title'];
        }

        return $options;
    }
}
