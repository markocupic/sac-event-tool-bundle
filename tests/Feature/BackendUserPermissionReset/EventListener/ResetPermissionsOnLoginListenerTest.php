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

namespace Markocupic\SacEventToolBundle\Tests\Feature\BackendUserPermissionReset\EventListener;

use Contao\BackendUser;
use Contao\FrontendUser;
use Markocupic\SacEventToolBundle\Feature\BackendUserPermissionReset\BackendUserPermissionReset;
use Markocupic\SacEventToolBundle\Feature\BackendUserPermissionReset\EventListener\ResetPermissionsOnLoginListener;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Http\Authenticator\AuthenticatorInterface;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\Event\LoginSuccessEvent;

final class ResetPermissionsOnLoginListenerTest extends TestCase
{
    public function testResetsTheResettableBackendUser(): void
    {
        $reset = $this->createMock(BackendUserPermissionReset::class);
        $reset->method('isResettable')->with('amuster')->willReturn(true);
        $reset->expects($this->once())->method('resetUser')->with('amuster');

        (new ResetPermissionsOnLoginListener($reset, true))($this->createEvent($this->createBackendUser()));
    }

    public function testDoesNothingIfDisabled(): void
    {
        $reset = $this->createMock(BackendUserPermissionReset::class);
        $reset->expects($this->never())->method('resetUser');

        (new ResetPermissionsOnLoginListener($reset, false))($this->createEvent($this->createBackendUser()));
    }

    public function testDoesNotResetAdminsOrUsersWithoutGroupInheritance(): void
    {
        $reset = $this->createMock(BackendUserPermissionReset::class);
        $reset->method('isResettable')->willReturn(false);
        $reset->expects($this->never())->method('resetUser');

        (new ResetPermissionsOnLoginListener($reset, true))($this->createEvent($this->createBackendUser()));
    }

    public function testIgnoresFrontendUsers(): void
    {
        $reset = $this->createMock(BackendUserPermissionReset::class);
        $reset->expects($this->never())->method('isResettable');

        (new ResetPermissionsOnLoginListener($reset, true))($this->createEvent($this->createMock(FrontendUser::class)));
    }

    private function createBackendUser(): BackendUser
    {
        $user = $this->createMock(BackendUser::class);
        $user->method('getUserIdentifier')->willReturn('amuster');

        return $user;
    }

    private function createEvent(UserInterface $user): LoginSuccessEvent
    {
        $token = $this->createMock(TokenInterface::class);
        $token->method('getUser')->willReturn($user);

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
