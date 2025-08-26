<?php


namespace Markocupic\SacEventToolBundle\Controller\FrontendModule\EventRegistration\StepHandler;


use Contao\CalendarEventsModel;
use Contao\ModuleModel;
use Symfony\Component\HttpFoundation\Request;

interface ValidationStepInterface
{
	public function validate(CalendarEventsModel $eventModel, Request $request, ModuleModel $moduleModel): bool;
}
