<?php

declare(strict_types=1);

/**
 * SAC Event Tool Web Plugin for Contao
 * Copyright (c) 2008-2020 Marko Cupic
 * @package sac-event-tool-bundle
 * @author Marko Cupic m.cupic@gmx.ch, 2017-2020
 * @link https://github.com/markocupic/sac-event-tool-bundle
 */

namespace Markocupic\SacEventToolBundle\Contao\EventListener;

use Contao\BackendUser;
use Contao\CalendarEventsModel;
use Contao\Config;
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\CoreBundle\Routing\ScopeMatcher;
use Contao\Email;
use Contao\EventReleaseLevelPolicyModel;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Class ChangeEventReleaseLevelListener
 * @package Markocupic\SacEventToolBundle\Contao\EventListener
 */
class ChangeEventReleaseLevelListener
{
    /**
     * @var ContaoFramework
     */
    private $framework;

    /**
     * @var RequestStack
     */
    private $requestStack;

    /**
     * @var ScopeMatcher
     */
    private $scopeMatcher;

    /**
     * ChangeEventReleaseLevelListener constructor.
     * @param ContaoFramework $framework
     * @param RequestStack $requestStack
     * @param ScopeMatcher $scopeMatcher
     */
    public function __construct(ContaoFramework $framework, RequestStack $requestStack, ScopeMatcher $scopeMatcher)
    {
        $this->framework = $framework;
        $this->requestStack = $requestStack;
        $this->scopeMatcher = $scopeMatcher;
    }

    /**
     * @param CalendarEventsModel $objEvent
     * @param string $strDirection
     * @throws \Exception
     */
    public function onChangeEventReleaseLevel(CalendarEventsModel $objEvent, string $strDirection): void
    {
        if ($this->isBackend())
        {
            $eventReleaseLevelPolicyModelAdapter = $this->framework->getAdapter(EventReleaseLevelPolicyModel::class);
            $backendUserAdapter = $this->framework->getAdapter(BackendUser::class);
            $configAdapter = $this->framework->getAdapter(Config::class);

            $objCalendar = $objEvent->getRelated('pid');
            if ($objCalendar !== null)
            {
                if ($objCalendar->adviceOnEventReleaseLevelChange !== '')
                {
                    $objEventReleaseLevel = $eventReleaseLevelPolicyModelAdapter->findByPk($objEvent->eventReleaseLevel);
                    if ($objEventReleaseLevel !== null)
                    {
                        $objUser = $backendUserAdapter->getInstance();
                        $objEmail = new Email();
                        $objEmail->from = $configAdapter->get('adminEmail');
                        $objEmail->fromName = 'Administrator SAC Pilatus';
                        $objEmail->subject = sprintf('Neue Freigabestufe (%s) für Event %s.', $objEventReleaseLevel->level, $objEvent->title);

                        if ($strDirection === 'down')
                        {
                            $objEmail->text = sprintf("Hallo \nEvent %s wurde soeben durch %s auf Freigabestufe %s (%s) hinuntergestuft. \n\n\nDies ist eine automatische Nachricht mit Ursprung in: %s\n Liebe Grüsse \n\n Administrator SAC Pilatus", $objEvent->title, $objUser->name, $objEventReleaseLevel->level, $objEventReleaseLevel->title, __METHOD__ . ' LINE: ' . __LINE__);
                        }
                        else
                        {
                            $objEmail->text = sprintf("Hallo \nEvent %s wurde soeben durch %s auf Freigabestufe %s (%s) hochgestuft. \n\n\nDies ist eine automatische Nachricht mit Ursprung in: %s\n Liebe Grüsse \n\n Administrator SAC Pilatus", $objEvent->title, $objUser->name, $objEventReleaseLevel->level, $objEventReleaseLevel->title, __METHOD__ . ' LINE: ' . __LINE__);
                        }
                        $objEmail->sendTo($objCalendar->adviceOnEventReleaseLevelChange);
                    }
                }
            }
        }
    }

    /**
     * Identify the Contao scope (TL_MODE) of the current request
     * @return bool
     */
    private function isBackend(): bool
    {
        return $this->scopeMatcher->isBackendRequest($this->requestStack->getCurrentRequest());
    }

}
