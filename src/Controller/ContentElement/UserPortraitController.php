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

namespace Markocupic\SacEventToolBundle\Controller\ContentElement;

use Contao\ContentModel;
use Contao\CoreBundle\Controller\ContentElement\AbstractContentElementController;
use Contao\CoreBundle\DependencyInjection\Attribute\AsContentElement;
use Contao\CoreBundle\Exception\PageNotFoundException;
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\CoreBundle\Twig\FragmentTemplate;
use Contao\UserModel;
use Markocupic\SacEventToolBundle\Util\CalendarEventsUtil;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

#[AsContentElement(UserPortraitController::TYPE, category: 'sac_event_tool_content_elements')]
class UserPortraitController extends AbstractContentElementController
{
    public const TYPE = 'user_portrait';

    public function __construct(
        private readonly CalendarEventsUtil $calendarEventsUtil,
        private readonly ContaoFramework $framework,
    ) {
    }

    protected function getResponse(FragmentTemplate $template, ContentModel $model, Request $request): Response
    {
        $user = null;

        if ($request->query->has('username')) {
            $username = $request->query->get('username');
            $user = $this->framework->getAdapter(UserModel::class)->findByUsername($username);
        }

        // Do not display the profile of a disabled or deleted user.
        if (null === $user || $user->disable || ('' !== $user->start && $user->start > time()) || ('' !== $user->stop && $user->stop < time()) || ('' !== $user->start && $user->start > time())) {
            return new Response('', Response::HTTP_NO_CONTENT);
        }

        if ($user->hideUser) {
            throw new PageNotFoundException();
        }

        $arrUser = $user->row();
        $arrUser['mainQualification'] = $this->calendarEventsUtil->getMainQualification($user);
        $template->set('user', $arrUser);
        $template->set('userModel', $user);

        return $template->getResponse();
    }
}
