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

namespace Markocupic\SacEventToolBundle\DataContainer;

use Contao\Controller;
use Contao\CoreBundle\DependencyInjection\Attribute\AsCallback;
use Contao\DataContainer;
use Contao\Message;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;
use Markocupic\SacEventToolBundle\User\FrontendUser\ClearFrontendUserData;
use Symfony\Contracts\Translation\TranslatorInterface;

class Member
{
    public const TABLE = 'tl_member';

    private Connection $connection;
    private Util $util;
    private TranslatorInterface $translator;
    private ClearFrontendUserData $clearFrontendUserData;

    public function __construct(Connection $connection, Util $util, TranslatorInterface $translator, ClearFrontendUserData $clearFrontendUserData)
    {
        $this->connection = $connection;
        $this->util = $util;
        $this->translator = $translator;
        $this->clearFrontendUserData = $clearFrontendUserData;
    }

    /**
     * @throws \Exception
     */
    #[AsCallback(table: 'tl_member', target: 'config.delete', priority: 100)]
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

            Controller::redirect('contao?do=member');
        }
    }

    /**
     * @throws Exception
     */
    #[AsCallback(table: 'tl_member', target: 'fields.sectionId.options', priority: 100)]
    public function listSections(): array
    {
        return $this->connection
            ->fetchAllKeyValue('SELECT sectionId, name FROM tl_sac_section')
        ;
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
