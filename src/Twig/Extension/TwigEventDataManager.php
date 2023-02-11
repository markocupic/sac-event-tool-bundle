<?php

declare(strict_types=1);

/*
 * This file is part of SAC Event Tool Bundle.
 *
 * (c) Marko Cupic 2023 <m.cupic@gmx.ch>
 * @license GPL-3.0-or-later
 * For the full copyright and license information,
 * please view the LICENSE file that was distributed with this source code.
 * @link https://github.com/markocupic/sac-event-tool-bundle
 */

namespace Markocupic\SacEventToolBundle\Twig\Extension;

use Contao\CalendarEventsModel;
use Contao\CoreBundle\Framework\ContaoFramework;
use Markocupic\SacEventToolBundle\CalendarEventsHelper;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class TwigEventDataManager extends AbstractExtension
{
    public function __construct(
        private readonly ContaoFramework $framework,
    ) {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('get_event_by_pk', [$this, 'getEventByPk']),
            new TwigFunction('get_event_data', [$this, 'getEventData']),
        ];
    }

    public function getEventData(CalendarEventsModel $model, string $prop): mixed
    {
        $this->framework->initialize();

        return CalendarEventsHelper::getEventData($model, $prop);
    }

    public function getEventByPk($id): CalendarEventsModel|null
    {
        $this->framework->initialize();

        return CalendarEventsModel::findByPk($id);
    }
}
