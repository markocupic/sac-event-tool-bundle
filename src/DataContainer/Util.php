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

namespace Markocupic\SacEventToolBundle\DataContainer;

use Contao\Backend;
use Contao\DataContainer;
use Contao\Image;
use Contao\StringUtil;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Contracts\Translation\TranslatorInterface;

class Util
{
    private RequestStack $requestStack;
    private TranslatorInterface $translator;

    public function __construct(RequestStack $requestStack, TranslatorInterface $translator)
    {
        $this->requestStack = $requestStack;
        $this->translator = $translator;
    }

    /**
     * Set the correct referer.
     */
    public function setCorrectReferer(): void
    {
        $request = $this->requestStack->getCurrentRequest();

        if ('sac_calendar_events_tool' === $request->query->get('do') && '' !== $request->query->get('ref')) {
            $objSession = $request->getSession();
            $ref = $request->query->get('ref');
            $session = $objSession->get('referer');

            $arrTables = [
                'tl_calendar_container',
                'tl_calendar',
                'tl_calendar_events',
                'tl_calendar_events_instructor_invoice',
            ];

            foreach ($arrTables as $table) {
                if (isset($session[$ref][$table])) {
                    $session[$ref][$table] = str_replace('do=calendar', 'do=sac_calendar_events_tool', $session[$ref][$table]);
                    $objSession->set('referer', $session);
                }
            }
        }
    }

    /**
     * Return the paste button.
     */
    public function pasteButtonCallback(DataContainer $dc, array $row, string $table, bool $cr, array $arrClipboard, ?array $arrChildren = null, ?string $prevLabel = null, ?string $nextlabel = null): string
    {
        $imagePasteAfter = Image::getHtml('pasteafter.gif', $this->translator->trans('DCA.pasteafter.1', [$row['id']], 'contao_default'));
        $imagePasteInto = Image::getHtml('pasteinto.gif', $this->translator->trans('DCA.pasteinto.1', [$row['id']], 'contao_default'));

        if (0 === (int) $row['id']) {
            return $cr ? Image::getHtml('pasteinto_.gif').' ' : '<a href="'.Backend::addToUrl('act='.$arrClipboard['mode'].'&mode=2&pid='.$row['id'].'&id='.$arrClipboard['id']).'" title="'.StringUtil::specialchars($this->translator->trans('DCA.pasteinto.1', [$row['id']], 'contao_default')).'" onclick="Backend.getScrollOffset();">'.$imagePasteInto.'</a> ';
        }

        return ('cut' === $arrClipboard['mode'] && (int) $arrClipboard['id'] === (int) $row['id']) || $cr ? Image::getHtml('pasteafter_.gif').' ' : '<a href="'.Backend::addToUrl('act='.$arrClipboard['mode'].'&mode=1&pid='.$row['id'].'&id='.$arrClipboard['id']).'" title="'.StringUtil::specialchars($this->translator->trans('DCA.pasteafter.1', [$row['id']], 'contao_default')).'" onclick="Backend.getScrollOffset();">'.$imagePasteAfter.'</a> ';
    }
}
