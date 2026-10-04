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

namespace Markocupic\SacEventToolBundle\Tests\Feature\BackendUserHomeDirectory\EventListener;

use Contao\BackendUser;
use Contao\FrontendUser;
use Contao\TestCase\ContaoTestCase;
use Contao\UserModel;
use Markocupic\SacEventToolBundle\Feature\BackendUserHomeDirectory\BackendUserHomeDirectory;
use Markocupic\SacEventToolBundle\Feature\BackendUserHomeDirectory\EventListener\CreateHomeDirectoryOnLoginListener;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Http\Authenticator\AuthenticatorInterface;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\Event\LoginSuccessEvent;

final class CreateHomeDirectoryOnLoginListenerTest extends ContaoTestCase
{
    public function testCreatesOnlyTheHomeDirectoryOfTheCurrentUser(): void
    {
        $userModel = $this->createMock(UserModel::class);

        $userModelAdapter = $this->mockAdapter(['findByUsername', 'findAll']);
        $userModelAdapter
            ->expects($this->once())
            ->method('findByUsername')
            ->with('amuster')
            ->willReturn($userModel)
        ;

        $userModelAdapter
            ->expects($this->never())
            ->method('findAll')
        ;

        $homeDirectory = $this->createMock(BackendUserHomeDirectory::class);
        $homeDirectory
            ->expects($this->once())
            ->method('create')
            ->with($userModel)
        ;

        $homeDirectory
            ->expects($this->never())
            ->method('createForAllUsers')
        ;

        $homeDirectory
            ->expects($this->never())
            ->method('archiveOrphanedDirectories')
        ;

        $user = $this->createMock(BackendUser::class);
        $user
            ->method('getUserIdentifier')
            ->willReturn('amuster')
        ;

        $listener = new CreateHomeDirectoryOnLoginListener($this->mockContaoFramework([UserModel::class => $userModelAdapter]), $homeDirectory);
        $listener($this->createEvent($user));
    }

    public function testIgnoresFrontendUsers(): void
    {
        $homeDirectory = $this->createMock(BackendUserHomeDirectory::class);
        $homeDirectory
            ->expects($this->never())
            ->method('create')
        ;

        $listener = new CreateHomeDirectoryOnLoginListener($this->mockContaoFramework(), $homeDirectory);
        $listener($this->createEvent($this->createMock(FrontendUser::class)));
    }

    private function createEvent(UserInterface $user): LoginSuccessEvent
    {
        $token = $this->createMock(TokenInterface::class);
        $token
            ->method('getUser')
            ->willReturn($user)
        ;

        return new LoginSuccessEvent(
            $this->createMock(AuthenticatorInterface::class),
            $this->createMock(Passport::class),
            $token,
            new Request(),
            null,
            'contao_backend',
        );
    }
}
