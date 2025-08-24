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

namespace Markocupic\SacEventToolBundle\Twig\Extension;

use Contao\CalendarEventsModel;
use Contao\CoreBundle\Framework\ContaoFramework;
use Markocupic\SacEventToolBundle\Util\CalendarEventsUtil;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class TwigQrCodeManager extends AbstractExtension
{
    public function __construct(
        private readonly CalendarEventsUtil $calendarEventsUtil,
        private readonly ContaoFramework $framework,
    ) {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('getEventQrCode', [$this, 'getEventQrCode']),
        ];
    }

    /**
     * Get the event qr code file path inside your twig template.
     *
     * Inside your Twig template: #event# -> \Contao\CalendarEventsModel or event id
     * {{ getEventQrCode(#event#) }}.
     *
     * @see: https://docs.contao.org/dev/framework/asset-management.
     */
    public function getEventQrCode(CalendarEventsModel|int $varEvent): string
    {
        if (\is_int($varEvent)) {
            $event = $this->framework->getAdapter(CalendarEventsModel::class)->findById($varEvent);
        } else {
            $event = $varEvent;
        }

        return $this->calendarEventsUtil->getEventQrCode($event) ?? '';
    }
}
