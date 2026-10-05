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

use Contao\Controller;
use Contao\CoreBundle\DependencyInjection\Attribute\AsCallback;
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\DataContainer;
use Contao\Message;
use Doctrine\DBAL\Exception;
use Markocupic\SacEventToolBundle\Feature\MemberProfileDeletion\MemberProfileDeletion;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

readonly class Member
{
    public const TABLE = 'tl_member';

    public function __construct(
        private ContaoFramework $framework,
        private MemberProfileDeletion $memberProfileDeletion,
        private RouterInterface $router,
        private TranslatorInterface $translator,
        private Util $util,
    ) {
    }

    /**
     * Clear the personal data of the member, e.g. anonymize the event registrations
     * and delete the avatar directory.
     *
     * @throws \Exception
     */
    #[AsCallback(table: 'tl_member', target: 'config.ondelete', priority: 100)]
    public function clearMemberProfile(DataContainer $dc): void
    {
        if (!$dc->id) {
            return;
        }

        if (false === $this->memberProfileDeletion->clearMemberProfile((int) $dc->id)) {
            $this->framework->getAdapter(Message::class)->addError($this->translator->trans('ERR.clearMemberProfile', [$dc->id], 'contao_default'));
            $this->framework->getAdapter(Controller::class)->redirect($this->router->generate('contao_backend', ['do' => 'member']));
        }
    }

    /**
     * The personal data frontend module triggers the onload callbacks as well, but
     * without a DataContainer.
     */
    #[AsCallback(table: 'tl_member', target: 'config.onload', priority: 100)]
    public function checkPermission(DataContainer|null $dc = null): void
    {
        if (null === $dc) {
            return;
        }

        $this->util->restrictDcaForNonAdmins(self::TABLE);
    }

    /**
     * @throws Exception
     */
    #[AsCallback(table: 'tl_member', target: 'fields.sectionId.options', priority: 100)]
    public function listSacSections(): array
    {
        return $this->util->listSacSections();
    }

    /**
     * @throws Exception
     */
    #[AsCallback(table: 'tl_member', target: 'config.onshow', priority: 100)]
    public function decryptSectionIds(array $data, array $row, DataContainer $dc): array
    {
        return $this->util->decryptSectionIds($data, $row, $dc, self::TABLE);
    }
}
