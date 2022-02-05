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

    public function __construct(RequestStack $requestStack)
    {
        $this->requestStack = $requestStack;
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

}
