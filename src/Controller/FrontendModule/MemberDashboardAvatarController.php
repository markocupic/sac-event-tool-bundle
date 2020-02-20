<?php

declare(strict_types=1);

/**
 * SAC Event Tool Web Plugin for Contao
 * Copyright (c) 2008-2020 Marko Cupic
 * @package sac-event-tool-bundle
 * @author Marko Cupic m.cupic@gmx.ch, 2017-2020
 * @link https://github.com/markocupic/sac-event-tool-bundle
 */

namespace Markocupic\SacEventToolBundle\Controller\FrontendModule;

use Contao\CoreBundle\Routing\ScopeMatcher;
use Contao\FilesModel;
use Contao\FrontendUser;
use Contao\Controller;
use Contao\StringUtil;
use Contao\Template;
use Contao\PageModel;
use Contao\ModuleModel;
use Contao\CoreBundle\Controller\FrontendModule\AbstractFrontendModuleController;
use Contao\CoreBundle\Framework\ContaoFramework;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Security;
use Contao\CoreBundle\ServiceAnnotation\FrontendModule;

/**
 * Class MemberDashboardAvatarController
 * @package Markocupic\SacEventToolBundle\Controller\FrontendModule
 * @FrontendModule("member_dashboard_avatar", category="sac_event_tool_frontend_modules")
 */
class MemberDashboardAvatarController extends AbstractFrontendModuleController
{
    /**
     * @var RequestStack
     */
    protected $requestStack;

    /**
     * @var ScopeMatcher
     */
    protected $scopeMatcher;

    /**
     * @var string
     */
    protected $projectDir;

    /**
     * @var FrontendUser
     */
    protected $objUser;

    /**
     * MemberDashboardAvatarController constructor.
     * @param RequestStack $requestStack
     * @param ScopeMatcher $scopeMatcher
     */
    public function __construct(RequestStack $requestStack, ScopeMatcher $scopeMatcher)
    {
        $this->requestStack = $requestStack;
        $this->scopeMatcher = $scopeMatcher;
    }

    /**
     * @param Request $request
     * @param ModuleModel $model
     * @param string $section
     * @param array|null $classes
     * @param PageModel|null $page
     * @return Response
     */
    public function __invoke(Request $request, ModuleModel $model, string $section, array $classes = null, PageModel $page = null): Response
    {
        // Get logged in member object
        if (($objUser = $this->get('security.helper')->getUser()) instanceof FrontendUser)
        {
            $this->objUser = $objUser;
        }
        else
        {
            if ($this->isFrontend())
            {
                return new Response('', Response::HTTP_NO_CONTENT);
            }
        }

        $this->projectDir = $this->getParameter('kernel.project_dir');
        $this->requestStack = $this->getParameter('kernel.project_dir');
        $this->requestStack = $this->getParameter('kernel.project_dir');

        // Call the parent method
        return parent::__invoke($request, $model, $section, $classes);
    }

    /**
     * @return array
     */
    public static function getSubscribedServices(): array
    {
        $services = parent::getSubscribedServices();

        $services['contao.framework'] = ContaoFramework::class;
        $services['security.helper'] = Security::class;

        return $services;
    }

    /**
     * @param Template $template
     * @param ModuleModel $model
     * @param Request $request
     * @return null|Response
     */
    protected function getResponse(Template $template, ModuleModel $model, Request $request): ?Response
    {
        $src = getAvatar($this->objUser->id, 'FE');
        if (!empty($src))
        {
            $objFile = FilesModel::findByPath($src);
            if ($objFile !== null && is_file($this->projectDir . '/' . $objFile->path))
            {
                $size = StringUtil::deserialize($model->imgSize);

                if (is_numeric($size))
                {
                    $size = [0, 0, (int) $size];
                }
                elseif (!$size instanceof PictureConfiguration)
                {
                    if (!\is_array($size))
                    {
                        $size = [];
                    }

                    $size += [0, 0, 'crop'];
                }

                // If picture
                if (isset($size[2]) && is_numeric($size[2]))
                {
                    $template->image = Controller::replaceInsertTags(sprintf('{{picture::%s?size=%s&alt=%s&class=%s}}',
                        $objFile->path, $size[2],
                        $this->objUser->firstname . ' ' . $this->objUser->lastname,
                        $model->imageClass
                    ));
                }
                else // If image
                {
                    $template->image = Controller::replaceInsertTags(sprintf('{{image::%s?width=%s&height=%s&mode=%s&alt=%s&class=%s}}',
                        $objFile->path,
                        $size[0],
                        $size[1],
                        $size[2],
                        $this->objUser->firstname . ' ' . $this->objUser->lastname,
                        $model->imageClass
                    ));
                }
            }
        }

        return $template->getResponse();
    }

    /**
     * Identify the Contao scope (TL_MODE) of the current request
     * @return bool
     */
    public function isFrontend()
    {
        return $this->requestStack->getCurrentRequest() !== null ? $this->scopeMatcher->isFrontendRequest($this->requestStack->getCurrentRequest()) : false;
    }

}
