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
use Contao\CoreBundle\Security\DataContainer\DeleteAction;
use Doctrine\DBAL\Connection;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\CacheableVoterInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;

/**
 * Only the highest level of a release level system may be deleted, so the levels stay
 * consecutive from 1 (see DataContainer\EventReleaseLevelPolicy::checkLevel()).
 * Levels are therefore deleted from the top down.
 *
 * Denies the DeleteAction for all other levels, also for admins. Contao then disables the delete button
 * and DC_Table::delete() refuses the deletion. Deleting the whole release level system (parent record)
 * is not affected: the child records are deleted without this check.
 *
 * Registered in config/services.yaml with a higher priority than the other voters: with the priority strategy
 * of the back end, the first voter that does not abstain decides. This voter only denies or abstains.
 *
 * @internal
 */
class EventReleaseLevelPolicyDeleteVoter implements CacheableVoterInterface
{
    private const string TABLE = 'tl_event_release_level_policy';

    public function __construct(private readonly Connection $connection)
    {
    }

    public function supportsAttribute(string $attribute): bool
    {
        return ContaoCorePermissions::DC_PREFIX.self::TABLE === $attribute;
    }

    public function supportsType(string $subjectType): bool
    {
        return DeleteAction::class === $subjectType;
    }

    public function vote(TokenInterface $token, mixed $subject, array $attributes, Vote|null $vote = null): int
    {
        if (!$subject instanceof DeleteAction || !\in_array(ContaoCorePermissions::DC_PREFIX.self::TABLE, $attributes, true)) {
            return self::ACCESS_ABSTAIN;
        }

        $id = $subject->getCurrentId();

        if (null === $id) {
            return self::ACCESS_ABSTAIN;
        }

        return $this->isHighestLevel((int) $id) ? self::ACCESS_ABSTAIN : self::ACCESS_DENIED;
    }

    /**
     * Records without a level (new, not yet saved records) may always be deleted.
     */
    public function isHighestLevel(int $id): bool
    {
        $record = $this->connection->fetchAssociative('SELECT pid, level FROM tl_event_release_level_policy WHERE id = ?', [$id]);

        if (false === $record || null === $record['level']) {
            return true;
        }

        $highestLevel = (int) $this->connection->fetchOne(
            'SELECT MAX(level) FROM tl_event_release_level_policy WHERE pid = ?',
            [(int) $record['pid']],
        );

        return (int) $record['level'] >= $highestLevel;
    }
}
