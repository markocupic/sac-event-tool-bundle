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

namespace Markocupic\SacEventToolBundle\Controller\FrontendModule;

use Contao\CoreBundle\Controller\FrontendModule\AbstractFrontendModuleController;
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\CoreBundle\InsertTag\InsertTagParser;
use Contao\CoreBundle\Routing\ScopeMatcher;
use Contao\CoreBundle\ServiceAnnotation\FrontendModule;
use Contao\FrontendUser;
use Contao\MemberModel;
use Contao\ModuleModel;
use Contao\PageModel;
use Contao\StringUtil;
use Contao\Template;
use Markocupic\SacEventToolBundle\Avatar\Avatar;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Security;

/**
 * @FrontendModule(MemberDashboardAvatarController::TYPE, category="sac_event_tool_frontend_modules")
 */
class MemberDashboardAvatarController extends AbstractFrontendModuleController
{
    public const TYPE = 'member_dashboard_avatar';
    protected FrontendUser|null $user = null;
    private ContaoFramework $framework;
    private ScopeMatcher $scopeMatcher;
    private Security $security;
    private InsertTagParser $insertTagParser;
    private Avatar $avatar;
    private string $projectDir;

    public function __construct(ContaoFramework $framework, ScopeMatcher $scopeMatcher, Security $security, InsertTagParser $insertTagParser, Avatar $avatar, string $projectDir)
    {
        $this->framework = $framework;
        $this->scopeMatcher = $scopeMatcher;
        $this->security = $security;
        $this->insertTagParser = $insertTagParser;
        $this->avatar = $avatar;
        $this->projectDir = $projectDir;
    }

    public function __invoke(Request $request, ModuleModel $model, string $section, array $classes = null, PageModel $page = null): Response
    {
        // Get logged in member object
        if (($user = $this->security->getUser()) instanceof FrontendUser) {
            $this->user = $user;
        } else {
            if ($this->isFrontend($request)) {
                return new Response('', Response::HTTP_NO_CONTENT);
            }
        }

        return parent::__invoke($request, $model, $section, $classes);
    }

    private function isFrontend(Request $request): bool
    {
        return $this->scopeMatcher->isFrontendRequest($request);
    }

    protected function getResponse(Template $template, ModuleModel $model, Request $request): Response|null
    {
        $stringUtilAdapter = $this->framework->getAdapter(StringUtil::class);
        $memberModelAdapter = $this->framework->getAdapter(MemberModel::class);

        $resPath = $this->avatar->getAvatarResourcePath($memberModelAdapter->findByPk($this->user->id));

        if (!empty($resPath)) {
            $size = $stringUtilAdapter->deserialize($model->imgSize);

            if (is_numeric($size)) {
                $size = [0, 0, (int) $size];
            } elseif (!$size instanceof PictureConfiguration) {
                if (!\is_array($size)) {
                    $size = [];
                }

                $size += [0, 0, 'crop'];
            }

            // If picture
            if (isset($size[2]) && is_numeric($size[2])) {
                $template->image = $this->insertTagParser->replace(sprintf(
                    '{{picture::%s?size=%s&alt=%s&class=%s}}',
                    $resPath,
                    $size[2],
                    $this->user->firstname.' '.$this->user->lastname,
                    $model->imageClass
                ));
            } else { // If image
                $template->image = $this->insertTagParser->replace(sprintf(
                    '{{image::%s?width=%s&height=%s&mode=%s&alt=%s&class=%s}}',
                    $resPath,
                    $size[0],
                    $size[1],
                    $size[2],
                    $this->user->firstname.' '.$this->user->lastname,
                    $model->imageClass
                ));
            }
        }

        return $template->getResponse();
    }
}
