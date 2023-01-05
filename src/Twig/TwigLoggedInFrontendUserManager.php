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

namespace Markocupic\SacEventToolBundle\Twig;

use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\FrontendUser;
use Contao\MemberModel;
use Symfony\Component\Security\Core\Security;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class TwigLoggedInFrontendUserManager extends AbstractExtension
{
    private ContaoFramework $framework;
    private Security $security;

    public function __construct(ContaoFramework $framework, Security $security)
    {
        $this->framework = $framework;
        $this->security = $security;
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('hasLoggedInFrontendUser', [$this, 'hasLoggedInFrontendUser']),
            new TwigFunction('getLoggedInFrontendUser', [$this, 'getLoggedInFrontendUser']),
        ];
    }

    /**
     * Returns true if a Contao frontend member is logged in.
     *
     * Inside your Twig template:
     * {% if hasLoggedInFrontendUser() is sames as true %}Frontend user logged in{% endif %}
     *
     * @see: https://docs.contao.org/dev/framework/asset-management.
     */
    public function hasLoggedInFrontendUser(): bool
    {
        return null !== $this->getLoggedInFrontendUser();
    }

    /**
     * Returns the logged in Contao member (\Contao\MemberModel) if there is a logged in Contao frontend user
     * or null if there is no logged in Contao frontend user.
     *
     * Inside your Twig template:
     * {% set user = getLoggedInFrontendUser() %}
     * Hi, my name is {{ user.firstname }}
     *
     * @see: https://docs.contao.org/dev/framework/asset-management.
     */
    public function getLoggedInFrontendUser(): MemberModel|null
    {
        $user = $this->security->getUser();

        if ($user instanceof FrontendUser) {
            $memberModelAdapter = $this->framework->getAdapter(MemberModel::class);

            if (null !== ($model = $memberModelAdapter->findByPk($user->id))) {
                return $model;
            }
        }

        return null;
    }
}
