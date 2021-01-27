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

namespace Markocupic\SacEventToolBundle\EventListener\Contao;

use Contao\Controller;
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\FrontendUser;
use Contao\MemberModel;
use Contao\PageModel;

/**
 * Class ReplaceInsertTagsListener.
 */
class ReplaceInsertTagsListener
{
    /**
     * @var ContaoFramework
     */
    private $framework;

    /**
     * ReplaceInsertTagsListener constructor.
     */
    public function __construct(ContaoFramework $framework)
    {
        $this->framework = $framework;
    }

    /**
     * @param $strTag
     *
     * @return bool|string
     */
    public function onReplaceInsertTags($strTag)
    {
        // Set adapters
        $controllerAdapter = $this->framework->getAdapter(Controller::class);
        $memberModelAdapter = $this->framework->getAdapter(MemberModel::class);
        $frontendUserAdapter = $this->framework->getAdapter(FrontendUser::class);
        $pageModelAdapter = $this->framework->getAdapter(PageModel::class);

        // Trim whitespaces
        $strTag = '' !== $strTag ? trim($strTag) : $strTag;

        // Replace external link
        // {{external_link::http://google.ch::more}}
        if (false !== strpos($strTag, 'external_link')) {
            $elements = explode('::', $strTag);

            if (\is_array($elements) && \count($elements) > 1) {
                $href = $elements[1];
                $label = $href;

                if (isset($elements[2]) && '' !== $elements[2]) {
                    $label = $elements[2];
                }

                return sprintf('<a href="%s" target="_blank">%s</a>', $href, $label);
            }
        }

        // {{member_avatar::###pictureSizeID###}}
        // Return picture of logged in member
        if (false !== strpos($strTag, 'member_avatar')) {
            $elements = explode('::', $strTag);

            if (\is_array($elements) && \count($elements) > 1) {
                $size = $elements[1];

                if (FE_USER_LOGGED_IN) {
                    $objUser = $memberModelAdapter->findByPk($frontendUserAdapter->getInstance()->id);

                    if (null !== $objUser) {
                        $strUrl = getAvatar($objUser->id, 'FE');
                        $strInsertTag = sprintf('{{picture::%s?size=%s&alt=&s}}', $strUrl, $size, $objUser->firstname.' '.$objUser->lastname);

                        return $controllerAdapter->replaceInsertTags($strInsertTag);
                    }
                }
            }
        }

        // Redirect to an internal page
        // {{redirect::###pageIdOrAlias###::###params###}}
        // {{redirect::konto-aktivieren}}
        // {{redirect::some-page-alias::?foo=bar&var=bla}}
        if (false !== strpos($strTag, 'redirect')) {
            $elements = explode('::', $strTag);

            if (\is_array($elements) && \count($elements) > 1) {
                $params = '';

                if (isset($elements[2])) {
                    $params = $elements[2];
                }
                $objPage = $pageModelAdapter->findByIdOrAlias($elements[1]);

                if (null !== $objPage) {
                    $strLocation = sprintf('%s%s', $objPage->getFrontendUrl(), $params);
                    $controllerAdapter->redirect($strLocation);
                }
            }
        }

        return false;
    }
}
