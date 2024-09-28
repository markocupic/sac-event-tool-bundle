<?php

declare(strict_types=1);

/*
 * This file is part of SAC Event Tool Bundle.
 *
 * (c) Marko Cupic 2024 <m.cupic@gmx.ch>
 * @license GPL-3.0-or-later
 * For the full copyright and license information,
 * please view the LICENSE file that was distributed with this source code.
 * @link https://github.com/markocupic/sac-event-tool-bundle
 */

namespace Markocupic\SacEventToolBundle\DataContainer;

use Contao\BackendUser;
use Contao\CoreBundle\DependencyInjection\Attribute\AsCallback;
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\CoreBundle\Intl\Countries;
use Contao\CoreBundle\Security\ContaoCorePermissions;
use Contao\DataContainer;
use Contao\Message;
use Contao\UserModel;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;
use Doctrine\DBAL\Types\Types;
use Markocupic\SacEventToolBundle\Config\Bundle;
use Markocupic\SacEventToolBundle\User\BackendUser\MaintainBackendUsersHomeDirectory;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Contracts\Translation\TranslatorInterface;

class User
{
    public const TABLE = 'tl_user';

    public function __construct(
        private readonly Connection $connection,
        private readonly ContaoFramework $framework,
        private readonly Countries $countries,
        private readonly MaintainBackendUsersHomeDirectory $maintainBackendUsersHomeDirectory,
        private readonly RequestStack $requestStack,
        private readonly Security $security,
        private readonly TranslatorInterface $translator,
        private readonly Util $util,
    ) {
    }

    #[AsCallback(table: 'tl_user', target: 'fields.sectionId.options', priority: 100)]
    public function listSacSections(): array
    {
        return $this->util->listSacSections();
    }

    #[AsCallback(table: 'tl_user', target: 'fields.country.options', priority: 100)]
    public function getCountries(): array
    {
        $arrCountries = $this->countries->getCountries();

        return array_combine(array_map('strtolower', array_keys($arrCountries)), $arrCountries);
    }

    /**
     * Add backend assets.
     */
    #[AsCallback(table: 'tl_user', target: 'config.onload', priority: 100)]
    public function addBackendAssets(DataContainer $dc): void
    {
        $request = $this->requestStack->getCurrentRequest();

        if ('user' === $request->query->get('do') && 'edit' === $request->query->get('act') && '' !== $request->query->get('ref')) {
            $GLOBALS['TL_JAVASCRIPT'][] = Bundle::ASSET_DIR.'/js/backend_member_autocomplete.js';
        }
    }

    #[AsCallback(table: 'tl_user', target: 'config.onload', priority: 100)]
    public function doNotShowFieldIfCanNotEdit(DataContainer $dc): void
    {
        $arrFieldNames = array_keys($GLOBALS['TL_DCA']['tl_user']['fields']);

        foreach ($arrFieldNames as $fieldName) {
            if (!$this->security->isGranted(ContaoCorePermissions::USER_CAN_EDIT_FIELD_OF_TABLE, 'tl_user::'.$fieldName)) {
                $GLOBALS['TL_DCA']['tl_user']['fields'][$fieldName]['eval']['doNotShow'] = true;
            }
        }
    }

    /**
     * Make fields readonly in backend users profile.
     *
     * @throws Exception
     */
    #[AsCallback(table: 'tl_user', target: 'config.onload', priority: 100)]
    public function makeFieldsReadonlyInUsersProfile(DataContainer $dc): void
    {
        $user = $this->security->getUser();

        if (!$user instanceof BackendUser) {
            return;
        }

        if (empty($user->sacMemberId)) {
            return;
        }

        $request = $this->requestStack->getCurrentRequest();

        if (!$dc->id > 0 || 'login' !== $request->get('do') || 'edit' !== $request->get('act')) {
            return;
        }

        $arrMember = $this->connection->fetchAssociative(
            'SELECT * FROM tl_member WHERE sacMemberId = :sacMemberId',
            ['sacMemberId' => $user->sacMemberId],
            ['sacMemberId' => Types::INTEGER],
        );

        if (false === $arrMember || $arrMember['disable']) {
            return;
        }

        if ('' !== $arrMember['stop'] && time() > $arrMember['stop']) {
            return;
        }

        $arrReadonlyFields = ['gender', 'firstname', 'lastname', 'name', 'email', 'phone', 'mobile', 'street', 'postal', 'city', 'dateOfBirth'];

        foreach ($arrReadonlyFields as $fieldName) {
            $GLOBALS['TL_DCA']['tl_user']['fields'][$fieldName]['eval']['readonly'] = true;
        }

        // Display a message
        $messageAdapter = $this->framework->getAdapter(Message::class);

        $messageAdapter->addInfo(
            $this->translator->trans('MSC.bhs_dashb_howToEditReadonlyProfileData', [], 'contao_default')
        );
    }

    /**
     * Display the section name instead of the section id
     * 4250,4252 becomes SAC PILATUS, SAC PILATUS NAPF.
     */
    #[AsCallback(table: 'tl_user', target: 'config.onshow', priority: 100)]
    public function decryptSectionIds(array $data, array $row, DataContainer $dc): array
    {
        return $this->util->decryptSectionIds($data, $row, $dc, self::TABLE);
    }

    /**
     * Set defaults and auto-create backend users home directory when creating a new user.
     *
     * @throws Exception
     * @throws \Exception
     */
    #[AsCallback(table: 'tl_user', target: 'config.oncreate', priority: 100)]
    public function setDefaultsOnCreatingNew(string $strTable, int $id, array $arrSet): void
    {
        $userModelAdapter = $this->framework->getAdapter(UserModel::class);

        if (null !== ($objUser = $userModelAdapter->findByPk($id))) {
            // Create backend users home directory
            $this->maintainBackendUsersHomeDirectory->createBackendUsersHomeDirectory($objUser);

            if ('extend' !== ($arrSet['inherit'] ?? null)) {
                $randomPassword = sha1((string) random_int(0, getrandmax()));

                $set = [
                    'inherit' => 'extend',
                    'pwChange' => true,
                    'password' => password_hash($randomPassword, PASSWORD_DEFAULT),
                    'tstamp' => 0,
                ];

                $this->connection->update('tl_user', $set, ['id' => $id]);
            }
        }
    }

    /**
     * @throws Exception
     */
    #[AsCallback(table: 'tl_user', target: 'fields.userRole.options', priority: 100)]
    public function getUserRoles(): array
    {
        $options = [];

        $stmt = $this->connection->executeQuery('SELECT * FROM tl_user_role ORDER BY sorting');

        while (false !== ($row = $stmt->fetchAssociative())) {
            $options[$row['id']] = $row['title'];
        }

        return $options;
    }
}
