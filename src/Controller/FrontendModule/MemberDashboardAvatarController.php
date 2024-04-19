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

namespace Markocupic\SacEventToolBundle\Controller\FrontendModule;

use Contao\CoreBundle\Controller\FrontendModule\AbstractFrontendModuleController;
use Contao\CoreBundle\DependencyInjection\Attribute\AsFrontendModule;
use Contao\CoreBundle\File\Metadata;
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\CoreBundle\Image\ImageFactoryInterface;
use Contao\CoreBundle\Image\Studio\Studio;
use Contao\CoreBundle\Routing\ScopeMatcher;
use Contao\CoreBundle\Twig\FragmentTemplate;
use Contao\FrontendUser;
use Contao\MemberModel;
use Contao\ModuleModel;
use Contao\PageModel;
use Markocupic\SacEventToolBundle\Avatar\Avatar;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

#[AsFrontendModule(MemberDashboardAvatarController::TYPE, category:'sac_event_tool_frontend_modules', template:'mod_member_dashboard_avatar')]
class MemberDashboardAvatarController extends AbstractFrontendModuleController
{
    public const TYPE = 'member_dashboard_avatar';
    protected FrontendUser|null $user = null;

    public function __construct(
        private readonly ContaoFramework $framework,
        private readonly ImageFactoryInterface $imageFactory,
        private readonly Avatar $avatar,
        private readonly ScopeMatcher $scopeMatcher,
        private readonly Security $security,
        private readonly Studio $studio,
    ) {
    }

    public function __invoke(Request $request, ModuleModel $model, string $section, array $classes = null, PageModel $page = null): Response
    {
        // Get logged in frontend user
        if (($user = $this->security->getUser()) instanceof FrontendUser) {
            $this->user = $user;
        } else {
            if ($this->scopeMatcher->isFrontendRequest($request)) {
                return new Response('', Response::HTTP_NO_CONTENT);
            }
        }

        return parent::__invoke($request, $model, $section, $classes);
    }

    protected function getResponse(FragmentTemplate $template, ModuleModel $model, Request $request): Response
    {
        $memberModelAdapter = $this->framework->getAdapter(MemberModel::class);

        $path = $this->avatar->getAvatarResourcePath($memberModelAdapter->findByPk($this->user->id), true);

        if (strpos($path, 'heic')) {
            return $template->getResponse();
        }

        $image = $this->imageFactory->create($path);

        $figureBuilder = $this->studio
            ->createFigureBuilder()
            ->setMetadata(new Metadata(['alt' => $this->user->firstname.' '.$this->user->lastname]))
            ->setSize($model->imgSize)
            ->from($image)
            ;

        $template->set('figure', $figureBuilder->buildIfResourceExists());

        return $template->getResponse();
    }
}
