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

use Contao\Controller;
use Contao\CoreBundle\DependencyInjection\Attribute\AsCallback;
use Contao\CoreBundle\Security\ContaoCorePermissions;
use Contao\DataContainer;
use Contao\Message;
use Markocupic\SacEventToolBundle\User\FrontendUser\ClearFrontendUserData;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

class Member
{
    public const TABLE = 'tl_member';

    public function __construct(
        private readonly Security $security,
        private readonly ClearFrontendUserData $clearFrontendUserData,
        private readonly RouterInterface $router,
        private readonly TranslatorInterface $translator,
        private readonly Util $util,
    ) {
    }

    /**
     * @throws \Exception
     */
    #[AsCallback(table: 'tl_member', target: 'config.ondelete', priority: 100)]
    public function clearMemberProfile(DataContainer $dc): void
    {
        // Clear personal data f.ex.
        // - Anonymize entries in tl_calendar_events_member
        // - Delete avatar directory

        if (!$dc->id) {
            return;
        }

        if (false === $this->clearFrontendUserData->clearMemberProfile((int) $dc->id)) {
            $arrErrorMsg = $this->translator->trans('ERR.clearMemberProfile', [$dc->id], 'contao_default');
            Message::addError($arrErrorMsg);

            Controller::redirect($this->router->generate('contao_backend', ['do' => 'member']));
        }
    }

    #[AsCallback(table: 'tl_member', target: 'config.onload', priority: 100)]
    public function checkPermission(DataContainer $dc = null): void
    {
        if (!$dc) {
            // The personal data frontend module is triggering the onload callbacks as well,
            // but without the DataContainer $dc method parameter.
            return;
        }

        if ($this->security->isGranted('ROLE_ADMIN')) {
            return;
        }

        // Adding new records is not allowed to non admins.
        $GLOBALS['TL_DCA']['tl_member']['config']['closed'] = true;
        $GLOBALS['TL_DCA']['tl_member']['config']['notCopyable'] = true;
        unset($GLOBALS['TL_DCA']['tl_member']['list']['operations']['copy']);

        // Deleting records is not allowed to non admins.
        $GLOBALS['TL_DCA']['tl_member']['config']['notDeletable'] = true;
        unset($GLOBALS['TL_DCA']['tl_member']['list']['operations']['delete']);

        // Do not show fields without write permission.
        $arrFieldNames = array_keys($GLOBALS['TL_DCA']['tl_member']['fields']);

        foreach ($arrFieldNames as $fieldName) {
            if (!$this->security->isGranted(ContaoCorePermissions::USER_CAN_EDIT_FIELD_OF_TABLE, 'tl_member::'.$fieldName)) {
                $GLOBALS['TL_DCA']['tl_member']['fields'][$fieldName]['eval']['doNotShow'] = true;
            }
        }
    }

    #[AsCallback(table: 'tl_member', target: 'fields.sectionId.options', priority: 100)]
    public function listSacSections(): array
    {
        return $this->util->listSacSections();
    }

    /**
     * Display the section name instead of the section id
     * 4250,4252 becomes SAC PILATUS, SAC PILATUS NAPF.
     */
    #[AsCallback(table: 'tl_member', target: 'config.onshow', priority: 100)]
    public function decryptSectionIds(array $data, array $row, DataContainer $dc): array
    {
        return $this->util->decryptSectionIds($data, $row, $dc, self::TABLE);
    }
}
