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

namespace Markocupic\SacEventToolBundle\Twig\Extension;

use Contao\CalendarEventsModel;
use Markocupic\SacEventToolBundle\CalendarEventsHelper;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class TwigQrCodeManager extends AbstractExtension
{
    public function getFunctions(): array
    {
        return [
            new TwigFunction('getEventQrCode', [$this, 'getEventQrCode']),
        ];
    }

    /**
     * Get the event qr code file path inside your twig template.
     *
     * Inside your Twig template:
     * #event# -> \Contao\CalendarEventsModel or event id
     * {{ getEventQrCode(#event#) }}.
     *
     * @see: https://docs.contao.org/dev/framework/asset-management.
     */
    public function getEventQrCode(CalendarEventsModel|int $varEvent): string
    {
        if (\is_int($varEvent)) {
            $event = CalendarEventsModel::findByPk($varEvent);
        } else {
            $event = $varEvent;
        }

        return CalendarEventsHelper::getEventQrCode($event) ?? '';
    }
}
