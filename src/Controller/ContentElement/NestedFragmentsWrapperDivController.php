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
use Contao\CoreBundle\Twig\FragmentTemplate;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

#[AsContentElement(NestedFragmentsWrapperDivController::TYPE, nestedFragments: true, category: 'sac_event_tool_content_elements')]
class NestedFragmentsWrapperDivController extends AbstractContentElementController
{
    public const TYPE = 'nested_fragments_wrapper_div';

    protected function getResponse(FragmentTemplate $template, ContentModel $model, Request $request): Response
    {
        return $template->getResponse();
    }
}
