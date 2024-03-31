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

namespace Markocupic\SacEventToolBundle\Security\User;

use Contao\BackendUser;
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\CoreBundle\Security\User\ContaoUserProvider;
use Contao\FrontendUser;
use Contao\User;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Types\Types;
use Symfony\Component\Security\Core\User\UserProviderInterface;

/**
 * Override the ContaoUserProvider
 * to allow backend login with the sacMemberId.
 *
 * @implements UserProviderInterface<User>
 */
class CustomContaoUserProvider extends ContaoUserProvider
{
    /**
     * @param class-string<User> $userClass
     */
    public function __construct(
        private readonly Connection $connection,
        private readonly ContaoFramework $framework,
        private readonly string $userClass,
    ) {
        if (BackendUser::class !== $userClass && FrontendUser::class !== $userClass) {
            throw new \RuntimeException(sprintf('Unsupported class "%s".', $userClass));
        }

        parent::__construct($framework, $userClass);
    }

    public function loadUserByIdentifier(string $identifier): User
    {
        $this->framework->initialize();

        if (BackendUser::class === $this->userClass && is_numeric($identifier) && 6 === \strlen(trim($identifier))) {
            $username = $this->connection->fetchOne(
                'SELECT username FROM tl_user WHERE sacMemberId = :identifier',
                ['identifier' => (int) $identifier],
                ['identifier' => Types::INTEGER],
            );

            if (false !== $username) {
                /** @var User $adapter */
                $adapter = $this->framework->getAdapter($this->userClass);

                $user = $adapter->loadUserByIdentifier($username);

                if (is_a($user, $this->userClass)) {
                    return $user;
                }
            }
        }

        // Call the parent user provider from the Contao Core
        return parent::loadUserByIdentifier($identifier);
    }
}
