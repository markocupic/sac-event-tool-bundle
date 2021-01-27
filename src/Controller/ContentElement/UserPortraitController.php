<?php

declare(strict_types=1);

/*
 * This file is part of SAC Event Tool Bundle.
 *
 * (c) Marko Cupic 2021 <m.cupic@gmx.ch>
 * @license MIT
 * For the full copyright and license information,
 * please view the LICENSE file that was distributed with this source code.
 * @link https://github.com/markocupic/sac-event-tool-bundle
 */

namespace Markocupic\SacEventToolBundle\Controller\ContentElement;

use Contao\ContentModel;
use Contao\CoreBundle\Controller\ContentElement\AbstractContentElementController;
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\CoreBundle\ServiceAnnotation\ContentElement;
use Contao\Input;
use Contao\PageModel;
use Contao\Template;
use Contao\UserModel;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Class UserPortraitController.
 *
 * @ContentElement("user_portrait", category="sac_event_tool_content_elements", template="ce_user_portrait")
 */
class UserPortraitController extends AbstractContentElementController
{
    /**
     * @var UserModel
     */
    protected $objUser;

    public function __invoke(Request $request, ContentModel $model, string $section, array $classes = null, ?PageModel $pageModel = null): Response
    {
        return parent::__invoke($request, $model, $section, $classes, $pageModel);
    }

    public static function getSubscribedServices(): array
    {
        $services = parent::getSubscribedServices();
        $services['contao.framework'] = ContaoFramework::class;

        return $services;
    }

    protected function getResponse(Template $template, ContentModel $model, Request $request): ?Response
    {
        /** @var Input $inputAdapter */
        $inputAdapter = $this->get('contao.framework')->getAdapter(Input::class);

        /** @var UserModel $userModelAdapter */
        $userModelAdapter = $this->get('contao.framework')->getAdapter(UserModel::class);

        if (!empty($inputAdapter->get('username'))) {
            if (null === ($this->objUser = $userModelAdapter->findByUsername($inputAdapter->get('username')))) {
                return new Response('', Response::HTTP_NO_CONTENT);
            }
        }

        // Do not show disabled users
        if (null === $this->objUser || $this->objUser->disable) {
            return new Response('', Response::HTTP_NO_CONTENT);
        }

        $template->objUser = $this->objUser;

        return $template->getResponse();
    }
}
