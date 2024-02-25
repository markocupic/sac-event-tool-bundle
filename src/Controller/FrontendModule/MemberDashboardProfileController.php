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
use Contao\CoreBundle\Framework\Adapter;
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\FrontendUser;
use Contao\ModuleModel;
use Contao\PageModel;
use Contao\StringUtil;
use Contao\Template;
use Markocupic\SacEventToolBundle\Model\SacSectionModel;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;

#[AsFrontendModule(MemberDashboardProfileController::TYPE, category:'sac_event_tool_frontend_modules', template:'mod_member_dashboard_profile')]
class MemberDashboardProfileController extends AbstractFrontendModuleController
{
    public const TYPE = 'member_dashboard_profile';

    private Adapter $sacSectionModel;
    private Adapter $stringUtil;

    private FrontendUser|null $objUser = null;
    private Template|null $template = null;

    public function __construct(
        private readonly ContaoFramework $framework,
        private readonly Security $security,
    ) {
        $this->sacSectionModel = $this->framework->getAdapter(SacSectionModel::class);
        $this->stringUtil = $this->framework->getAdapter(StringUtil::class);
    }

    public function __invoke(Request $request, ModuleModel $model, string $section, array $classes = null, PageModel $page = null): Response
    {
        // Get logged in member object
        if (($objUser = $this->security->getUser()) instanceof FrontendUser) {
            $this->objUser = $objUser;
        }

        if (null !== $page) {
            // Neither cache nor search page
            $page->noSearch = 1;
            $page->cache = 0;
        }

        // Call the parent method
        return parent::__invoke($request, $model, $section, $classes);
    }

    protected function getResponse(Template $template, ModuleModel $model, Request $request): Response
    {
        // Do not allow for not authorized users
        if (null === $this->objUser) {
            throw new UnauthorizedHttpException('Not authorized. Please log in as frontend user.');
        }

        $this->template = $template;
        $this->template->user = $this->objUser;

        // SAC sections user belongs to
        $arrSectionNames = ['-'];
        $arrSectionIds = $this->stringUtil->deserialize($this->objUser->sectionId, true);

        if (null !== ($sections = $this->sacSectionModel->findMultipleBySectionIds($arrSectionIds))) {
            // Override default
            $arrSectionNames = [];

            foreach ($sections as $section) {
                $arrSectionNames[] = $section->name;
            }
        }

        $this->template->sac_sections = $arrSectionNames;

        return $this->template->getResponse();
    }
}
