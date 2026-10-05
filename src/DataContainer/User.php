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

namespace Markocupic\SacEventToolBundle\DataContainer;

use Contao\BackendUser;
use Contao\CoreBundle\DependencyInjection\Attribute\AsCallback;
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\CoreBundle\Intl\Countries;
use Contao\DataContainer;
use Contao\Message;
use Contao\UserModel;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;
use Doctrine\DBAL\Types\Types;
use Markocupic\SacEventToolBundle\Feature\BackendUserHomeDirectory\BackendUserHomeDirectory;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Asset\Packages;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Contracts\Translation\TranslatorInterface;

readonly class User
{
    public const TABLE = 'tl_user';

    /**
     * These fields are synchronized from the SAC member database.
     */
    private const array MEMBER_FIELDS = ['gender', 'firstname', 'lastname', 'name', 'email', 'phone', 'mobile', 'street', 'postal', 'city', 'dateOfBirth'];

    public function __construct(
        private Connection $connection,
        private ContaoFramework $framework,
        private Countries $countries,
        private BackendUserHomeDirectory $backendUserHomeDirectory,
        private Packages $packages,
        private RequestStack $requestStack,
        private Security $security,
        private TranslatorInterface $translator,
        private Util $util,
    ) {
    }

    /**
     * @throws Exception
     */
    #[AsCallback(table: 'tl_user', target: 'fields.sectionId.options', priority: 100)]
    public function listSacSections(): array
    {
        return $this->util->listSacSections();
    }

    #[AsCallback(table: 'tl_user', target: 'fields.country.options', priority: 100)]
    public function getCountries(): array
    {
        $countries = $this->countries->getCountries();

        return array_combine(array_map('strtolower', array_keys($countries)), $countries);
    }

    #[AsCallback(table: 'tl_user', target: 'config.onload', priority: 100)]
    public function addBackendAssets(DataContainer $dc): void
    {
        $request = $this->requestStack->getCurrentRequest();

        if ('user' === $request->query->get('do') && 'edit' === $request->query->get('act')) {
            $GLOBALS['TL_JAVASCRIPT'][] = $this->packages->getUrl('js/backend_member_autocomplete.js', 'markocupic_sac_event_tool');
        }
    }

    #[AsCallback(table: 'tl_user', target: 'config.onload', priority: 100)]
    public function checkPermission(DataContainer $dc): void
    {
        $this->util->restrictDcaForNonAdmins(self::TABLE);
    }

    /**
     * Users that are active SAC members cannot edit their member data in their
     * profile. The data is synchronized from the SAC member database.
     *
     * @throws Exception
     */
    #[AsCallback(table: 'tl_user', target: 'config.onload', priority: 100)]
    public function makeFieldsReadonlyInUsersProfile(DataContainer $dc): void
    {
        $user = $this->security->getUser();

        if (!$user instanceof BackendUser || empty($user->sacMemberId)) {
            return;
        }

        $request = $this->requestStack->getCurrentRequest();

        if (!$dc->id || 'login' !== $request->query->get('do') || 'edit' !== $request->query->get('act')) {
            return;
        }

        $member = $this->connection->fetchAssociative(
            'SELECT * FROM tl_member WHERE sacMemberId = :sacMemberId',
            ['sacMemberId' => $user->sacMemberId],
            ['sacMemberId' => Types::INTEGER],
        );

        if (false === $member || $member['disable']) {
            return;
        }

        if ('' !== $member['stop'] && time() > $member['stop']) {
            return;
        }

        foreach (self::MEMBER_FIELDS as $fieldName) {
            $GLOBALS['TL_DCA']['tl_user']['fields'][$fieldName]['eval']['readonly'] = true;
        }

        $this->framework->getAdapter(Message::class)->addInfo(
            $this->translator->trans('MSC.bhs_dashb_howToEditReadonlyProfileData', [], 'contao_default'),
        );
    }

    /**
     * @throws Exception
     */
    #[AsCallback(table: 'tl_user', target: 'config.onshow', priority: 100)]
    public function decryptSectionIds(array $data, array $row, DataContainer $dc): array
    {
        return $this->util->decryptSectionIds($data, $row, $dc, self::TABLE);
    }

    /**
     * Create the home directory of the new user. Users inherit the group
     * permissions and have to set a new password on their first login.
     *
     * @throws Exception
     * @throws \Exception
     */
    #[AsCallback(table: 'tl_user', target: 'config.oncreate', priority: 100)]
    public function setDefaultsOnCreatingNew(string $table, int $id, array $set): void
    {
        $user = $this->framework->getAdapter(UserModel::class)->findById($id);

        if (null === $user) {
            return;
        }

        $this->backendUserHomeDirectory->create($user);

        if ('extend' === ($set['inherit'] ?? null)) {
            return;
        }

        $this->connection->update(
            'tl_user',
            [
                'inherit' => 'extend',
                'pwChange' => true,
                'password' => password_hash(bin2hex(random_bytes(32)), PASSWORD_DEFAULT),
                'tstamp' => 0,
            ],
            ['id' => $id],
        );
    }

    /**
     * @throws Exception
     */
    #[AsCallback(table: 'tl_user', target: 'fields.userRole.options', priority: 100)]
    public function getUserRoles(): array
    {
        return $this->connection->fetchAllKeyValue('SELECT id, title FROM tl_user_role ORDER BY sorting');
    }
}
