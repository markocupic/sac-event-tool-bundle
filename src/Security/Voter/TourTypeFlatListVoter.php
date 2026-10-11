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

namespace Markocupic\SacEventToolBundle\Security\Voter;

use Contao\CoreBundle\Security\ContaoCorePermissions;
use Contao\CoreBundle\Security\DataContainer\CreateAction;
use Contao\CoreBundle\Security\DataContainer\UpdateAction;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\CacheableVoterInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;

/**
 * tl_tour_type is a flat list without parent or child table. To make the records manually sortable
 * (sorting field), the DCA uses MODE_TREE with all records on the root level (pid = 0).
 *
 * This voter keeps the list flat: it denies creating a record inside another record or moving a record
 * into another record (new pid > 0), also for admins. Contao then disables the "paste into" buttons.
 * "Paste after" and pasting on the root level are not affected.
 *
 * Registered in config/services.yaml with a higher priority than the other voters: with the priority strategy
 * of the back end, the first voter that does not abstain decides. This voter only denies or abstains.
 *
 * @internal
 */
class TourTypeFlatListVoter implements CacheableVoterInterface
{
    private const string TABLE = 'tl_tour_type';

    public function supportsAttribute(string $attribute): bool
    {
        return ContaoCorePermissions::DC_PREFIX.self::TABLE === $attribute;
    }

    public function supportsType(string $subjectType): bool
    {
        return \in_array($subjectType, [CreateAction::class, UpdateAction::class], true);
    }

    public function vote(TokenInterface $token, mixed $subject, array $attributes, Vote|null $vote = null): int
    {
        if (!$subject instanceof CreateAction && !$subject instanceof UpdateAction) {
            return self::ACCESS_ABSTAIN;
        }

        if (!\in_array(ContaoCorePermissions::DC_PREFIX.self::TABLE, $attributes, true)) {
            return self::ACCESS_ABSTAIN;
        }

        $newPid = $subject->getNewPid();

        if (null === $newPid || 0 === (int) $newPid) {
            return self::ACCESS_ABSTAIN;
        }

        return self::ACCESS_DENIED;
    }
}
