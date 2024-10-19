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
use Contao\CoreBundle\Framework\Adapter;
use Contao\CoreBundle\Framework\ContaoFramework;
use Markocupic\SacEventToolBundle\Util\CalendarEventsUtil;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class TwigEventDataManager extends AbstractExtension
{
    private Adapter $calendarEventsUtil;
    private Adapter $calendarEventsModel;

    public function __construct(
        private readonly ContaoFramework $framework,
    ) {
        $this->calendarEventsUtil = $this->framework->getAdapter(CalendarEventsUtil::class);
        $this->calendarEventsModel = $this->framework->getAdapter(CalendarEventsModel::class);
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

        return $this->calendarEventsUtil->getEventData($model, $prop);
    }

    public function getEventByPk($id): CalendarEventsModel|null
    {
        $this->framework->initialize();

        return $this->calendarEventsModel->findByPk($id);
    }
}
