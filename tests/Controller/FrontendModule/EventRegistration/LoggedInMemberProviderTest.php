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

namespace Markocupic\SacEventToolBundle\Tests\Controller\FrontendModule\EventRegistration;

use Contao\FrontendUser;
use Contao\MemberModel;
use Contao\TestCase\ContaoTestCase;
use Markocupic\SacEventToolBundle\Controller\FrontendModule\EventRegistration\LoggedInMemberProvider;
use Symfony\Bundle\SecurityBundle\Security;

class LoggedInMemberProviderTest extends ContaoTestCase
{
    public function testNoMemberWithoutFrontendUser(): void
    {
        $security = $this->createMock(Security::class);
        $security
            ->method('getUser')
            ->willReturn(null)
        ;

        $this->assertNull((new LoggedInMemberProvider($this->mockContaoFramework(), $security))->getMember());
    }

    public function testReturnsTheMemberOfTheFrontendUser(): void
    {
        $member = $this->mockClassWithProperties(MemberModel::class, ['id' => 5]);

        $memberAdapter = $this->mockAdapter(['findById']);
        $memberAdapter
            ->expects($this->once())
            ->method('findById')
            ->with(5)
            ->willReturn($member)
        ;

        $security = $this->createMock(Security::class);
        $security
            ->method('getUser')
            ->willReturn($this->mockClassWithProperties(FrontendUser::class, ['id' => 5]))
        ;

        $provider = new LoggedInMemberProvider($this->mockContaoFramework([MemberModel::class => $memberAdapter]), $security);

        $this->assertSame($member, $provider->getMember());
    }
}
