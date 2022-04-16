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

use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\CoreBundle\Intl\Countries;
use Contao\CoreBundle\ServiceAnnotation\Callback;
use Contao\DataContainer;
use Contao\Message;
use Contao\UserModel;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Driver\Exception;
use Markocupic\SacEventToolBundle\User\BackendUser\MaintainBackendUsersHomeDirectory;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Contracts\Translation\TranslatorInterface;

class User
{
    public const TABLE = 'tl_user';

    private ContaoFramework $framework;
    private Util $util;
    private RequestStack $requestStack;
    private Connection $connection;
    private TranslatorInterface $translator;
    private Countries $countries;
    private MaintainBackendUsersHomeDirectory $maintainBackendUsersHomeDirectory;

    public function __construct(ContaoFramework $framework, Util $util, RequestStack $requestStack, Connection $connection, TranslatorInterface $translator, Countries $countries, MaintainBackendUsersHomeDirectory $maintainBackendUsersHomeDirectory)
    {
        $this->framework = $framework;
        $this->util = $util;
        $this->requestStack = $requestStack;
        $this->connection = $connection;
        $this->translator = $translator;
        $this->countries = $countries;
        $this->maintainBackendUsersHomeDirectory = $maintainBackendUsersHomeDirectory;
    }

    /**
     * @Callback(table="tl_user", target="fields.sectionId.options")
     *
     * @throws \Doctrine\DBAL\Exception
     */
    public function listSections(): array
    {
        $arrOptions = [];

        $stmt = $this->connection->executeQuery('SELECT * FROM tl_sac_section', []);

        while (false !== ($arrSection = $stmt->fetchAssociative())) {
            $arrOptions[$arrSection['sectionId']] = $arrSection['name'];
        }

        return $arrOptions;
    }

    /**
     * @Callback(table="tl_user", target="fields.country.options")
     */
    public function getCountries(): array
    {
        $arrCountries = $this->countries->getCountries();

        return array_combine(array_map('strtolower', array_keys($arrCountries)), $arrCountries);
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
        if ($dc->id > 0) {
            $stmt = $this->connection->executeQuery('SELECT * FROM tl_user WHERE id = ?', [$dc->id]);

            if (false !== ($arrUser = $stmt->fetchAssociative())) {
                if (!$arrUser['admin']) {
                    if ((int) $arrUser['sacMemberId'] > 0) {
                        $stmt = $this->connection->executeQuery(
                            'SELECT * FROM tl_member WHERE sacMemberId = ?',
                            [$arrUser['sacMemberId']],
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
     * Display the section name instead of the section id
     * 4250,4252 becomes SAC PILATUS, SAC PILATUS NAPF.
     *
     * @Callback(table="tl_user", target="config.onshow")
     */
    public function decryptSectionIds(array $data, array $row, DataContainer $dc): array
    {
        return $this->util->decryptSectionIds($data, $row, $dc, self::TABLE);
    }

    /**
     * Set defaults and auto-create backend users home directory when creating a new user.
     *
     * @throws \Doctrine\DBAL\Exception
     * @Callback(table="tl_user", target="config.oncreate")
     */
    public function setDefaultsOnCreatingNew(string $strTable, int $id, array $arrSet): void
    {
        $userModelAdapter = $this->framework->getAdapter(UserModel::class);

        if (null !== ($objUser = $userModelAdapter->findByPk($id))) {
            // Create backend users home directory
            $this->maintainBackendUsersHomeDirectory->createBackendUsersHomeDirectory($objUser);

            if ('extend' !== $arrSet['inherit']) {
                $randomPassword = sha1((string) random_int(0, getrandmax()));

                $set = [
                    'inherit' => 'extend',
                    'pwChange' => '1',
                    'password' => password_hash($randomPassword, PASSWORD_DEFAULT),
                    'tstamp' => 0,
                ];

                $this->connection->update('tl_user', $set, ['id' => $id]);
            }
        }
    }

    /**
     * @throws Exception
     * @throws \Doctrine\DBAL\Exception
     *
     * @Callback(table="tl_user", target="fields.userRole.options")
     */
    public function getUserRoles(): array
    {
        $options = [];

        $stmt = $this->connection->executeQuery('SELECT * FROM tl_user_role ORDER BY sorting ASC');

        while (false !== ($row = $stmt->fetchAssociative())) {
            $options[$row['id']] = $row['title'];
        }

        return $options;
    }
}
