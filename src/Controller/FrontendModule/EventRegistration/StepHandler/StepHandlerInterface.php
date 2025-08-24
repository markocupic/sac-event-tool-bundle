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

namespace Markocupic\SacEventToolBundle\Controller\FrontendModule\EventRegistration\StepHandler;

use Contao\CalendarEventsModel;
use Contao\ModuleModel;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

interface StepHandlerInterface
{
    public static function getType(): string;

    public static function getPriority(): int;

    public function getTemplate(): string;

    public function doAutoForward(CalendarEventsModel $eventModel, Request $request, ModuleModel $moduleModel): bool;

    public function getResponse(CalendarEventsModel $eventModel, Request $request, ModuleModel $moduleModel): Response;
}
