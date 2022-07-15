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

use Contao\CalendarEventsBlogModel;
use Contao\Config;
use Contao\CoreBundle\Controller\FrontendModule\AbstractFrontendModuleController;
use Contao\CoreBundle\Exception\PageNotFoundException;
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\CoreBundle\ServiceAnnotation\FrontendModule;
use Contao\Environment;
use Contao\FilesModel;
use Contao\MemberModel;
use Contao\Model\Collection;
use Contao\ModuleModel;
use Contao\PageModel;
use Contao\Pagination;
use Contao\StringUtil;
use Contao\Template;
use Contao\Validator;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * @FrontendModule(EventBlogListController::TYPE, category="sac_event_tool_frontend_modules")
 */
class EventBlogListController extends AbstractFrontendModuleController
{
    public const TYPE = 'event_blog_list';

    /**
     * @var Collection
     */
    private $blogs;

    /**
     * @var bool
     */
    private $isAjaxRequest;

    /**
     * @var PageModel
     */
    private $page;

    public function __invoke(Request $request, ModuleModel $model, string $section, array $classes = null, PageModel $page = null): Response
    {
        /** @var CalendarEventsBlogModel $calendarEventsBlogModelAdapter */
        $calendarEventsBlogModelAdapter = $this->get('contao.framework')->getAdapter(CalendarEventsBlogModel::class);

        /** @var StringUtil $stringUtilAdapter */
        $stringUtilAdapter = $this->get('contao.framework')->getAdapter(StringUtil::class);

        /** @var Environment $environmentAdapter */
        $environmentAdapter = $this->get('contao.framework')->getAdapter(Environment::class);

        $this->isAjaxRequest = $environmentAdapter->get('isAjaxRequest');

        $this->page = $page;

        $arrIDS = [];
        $arrOptions = ['order' => 'dateAdded DESC'];

        /** @var CalendarEventsBlogModel $objBlogs */
        $objBlogs = $calendarEventsBlogModelAdapter->findBy(
            ['tl_calendar_events_blog.publishState=?'],
            ['3'],
            $arrOptions
        );

        if (null !== $objBlogs) {
            while ($objBlogs->next()) {
                $arrOrganizers = $stringUtilAdapter->deserialize($objBlogs->organizers, true);

                if (\count(array_intersect($arrOrganizers, $stringUtilAdapter->deserialize($model->eventBlogOrganizers, true))) > 0) {
                    $arrIDS[] = $objBlogs->id;
                }
            }
        }

        $this->blogs = $calendarEventsBlogModelAdapter->findMultipleByIds($arrIDS, $arrOptions);

        if (null === $this->blogs) {
            // Return empty string
            return new Response('', Response::HTTP_NO_CONTENT);
        }

        // Call the parent method
        return parent::__invoke($request, $model, $section, $classes);
    }

    public static function getSubscribedServices(): array
    {
        $services = parent::getSubscribedServices();

        $services['contao.framework'] = ContaoFramework::class;

        return $services;
    }

    protected function getResponse(Template $template, ModuleModel $model, Request $request): Response|null
    {
        // Get project dir
        $projectDir = $this->getParameter('kernel.project_dir');

        /** @var MemberModel $memberModelModelAdapter */
        $memberModelModelAdapter = $this->get('contao.framework')->getAdapter(MemberModel::class);

        /** @var StringUtil $stringUtilAdapter */
        $stringUtilAdapter = $this->get('contao.framework')->getAdapter(StringUtil::class);

        /** @var Config $configAdapter */
        $configAdapter = $this->get('contao.framework')->getAdapter(Config::class);

        /** @var Validator $validatorAdapter */
        $validatorAdapter = $this->get('contao.framework')->getAdapter(Validator::class);

        /** @var FilesModel $filesModelAdapter */
        $filesModelAdapter = $this->get('contao.framework')->getAdapter(FilesModel::class);

        /** @var Environment $environmentAdapter */
        $environmentAdapter = $this->get('contao.framework')->getAdapter(Environment::class);

        /** @var PageModel $pageModelAdapter */
        $pageModelAdapter = $this->get('contao.framework')->getAdapter(PageModel::class);

        $template->language = $this->page->language;

        $template->isAjaxRequest = $this->isAjaxRequest;

        // Twig callable
        $template->getAvatar = static fn (int $userId, string $scope): string => getAvatar($userId, $scope);

        $objPageModel = null;

        if ($model->jumpTo) {
            $objPageModel = $pageModelAdapter->findByPk($model->jumpTo);
        }

        $arrBlogsAll = [];
        $arrAllIds = [];

        while ($this->blogs->next()) {
            $arrBlog = $this->blogs->row();
            $arrAllIds[] = $arrBlog['id'];
            $objMember = $memberModelModelAdapter->findOneBySacMemberId($arrBlog['sacMemberId']);
            $arrBlog['authorId'] = $objMember->id;
            $arrBlog['authorName'] = null !== $objMember ? $objMember->firstname.' '.$objMember->lastname : $arrBlog['authorName'];
            $arrBlog['href'] = null !== $objPageModel ? ampersand($objPageModel->getFrontendUrl(($configAdapter->get('useAutoItem') ? '/' : '/items/').$this->blogs->id)) : null;

            $multiSRC = $stringUtilAdapter->deserialize($arrBlog['multiSRC'], true);

            // Add a random image to the list
            $arrBlog['singleSRC'] = null;

            if (!empty($multiSRC) && \is_array($multiSRC)) {
                $k = array_rand($multiSRC);
                $singleSRC = $multiSRC[$k];

                if ($validatorAdapter->isUuid($singleSRC)) {
                    $objFiles = $filesModelAdapter->findByUuid($singleSRC);

                    if (null !== $objFiles) {
                        if (is_file($projectDir.'/'.$objFiles->path)) {
                            $arrBlog['singleSRC'] = [
                                'id' => $objFiles->id,
                                'path' => $objFiles->path,
                                'uuid' => $stringUtilAdapter->binToUuid($objFiles->uuid),
                                'name' => $objFiles->name,
                                'singleSRC' => $objFiles->path,
                                'title' => $stringUtilAdapter->specialchars($objFiles->name),
                                'filesModel' => $objFiles->current(),
                            ];
                        }
                    }
                }
            }

            $arrBlogsAll[] = $arrBlog;
        }
        $template->arrAllIds = $arrAllIds;

        $total = \count($arrBlogsAll);
        $limit = $total;
        $offset = 0;

        // Overall limit
        if ($model->eventBlogLimit > 0) {
            $total = min($model->eventBlogLimit, $total);
            $limit = $total;
        }

        // Pagination
        if ($model->perPage > 0) {
            $id = 'page_e'.$model->id;

            $page = !empty($request->query->get($id)) ? $request->query->get($id) : 1;

            // Do not index or cache the page if the page number is outside the range
            if ($page < 1 || $page > max(ceil($total / $model->perPage), 1)) {
                throw new PageNotFoundException('Page not found: '.$environmentAdapter->get('uri'));
            }

            $offset = ($page - 1) * $model->perPage;
            $limit = min($model->perPage + $offset, $total);

            /** @var Pagination $objPagination */
            $objPagination = new Pagination($total, $model->perPage, $configAdapter->get('maxPaginationLinks'), $id);
            $template->pagination = $objPagination->generate("\n  ");
        }

        $arrBlogs = [];

        for ($i = $offset; $i < $limit; ++$i) {
            if (!isset($arrBlogsAll[$i]) || !\is_array($arrBlogsAll[$i])) {
                continue;
            }
            $arrBlogs[] = $arrBlogsAll[$i];
        }
        $template->blogs = $arrBlogs;

        return $template->getResponse();
    }
}
