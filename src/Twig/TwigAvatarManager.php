<?php

declare(strict_types=1);

/*
 * This file is part of SAC Event Tool Bundle.
 *
 * (c) Marko Cupic 2022 <m.cupic@gmx.ch>
 * @license GPL-3.0-or-later
 * For the full copyright and license information,
 * please view the LICENSE file that was distributed with this source code.
 * @link https://github.com/markocupic/sac-event-tool-bundle
 */

namespace Markocupic\SacEventToolBundle\Twig;

use Contao\MemberModel;
use Contao\UserModel;
use Markocupic\SacEventToolBundle\Avatar\Avatar;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class TwigAvatarManager extends AbstractExtension
{
    private Avatar $avatar;

    public function __construct(Avatar $avatar)
    {
        $this->avatar = $avatar;
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('getAvatarResourcePath', [$this, 'getAvatarResourcePath']),
        ];
    }

    /**
     * Get the resource path to the avatar inside your Twig template.
     *
     * Inside your Twig template:
     * #user# -> \Contao\UserModel or \Contao\MemberModel
     * {{ getAvatarResourcePAth(#user#) }}.
     *
     * @see: https://docs.contao.org/dev/framework/asset-management.
     */
    public function getAvatarResourcePath(MemberModel|UserModel $userModel): string
    {
        return $this->avatar->getAvatarResourcePath($userModel);
    }
}
