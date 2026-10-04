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

namespace Markocupic\SacEventToolBundle\Controller\FrontendModule\EventRegistration;

use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\FrontendUser;
use Contao\MemberModel;
use Symfony\Bundle\SecurityBundle\Security;

/**
 * Returns the member record (tl_member) of the logged-in frontend user.
 */
class LoggedInMemberProvider
{
    public function __construct(
        private readonly ContaoFramework $framework,
        private readonly Security $security,
    ) {
    }

    /**
     * @return MemberModel|null null if no frontend user is logged in or the member record does not exist
     */
    public function getMember(): MemberModel|null
    {
        $user = $this->security->getUser();

        if (!$user instanceof FrontendUser) {
            return null;
        }

        return $this->framework->getAdapter(MemberModel::class)->findById($user->id);
    }
}
