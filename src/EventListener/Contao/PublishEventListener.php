<?php

declare(strict_types=1);

/**
 * SAC Event Tool Web Plugin for Contao
 * Copyright (c) 2008-2020 Marko Cupic
 * @package sac-event-tool-bundle
 * @author Marko Cupic m.cupic@gmx.ch, 2017-2020
 * @link https://github.com/markocupic/sac-event-tool-bundle
 */

namespace Markocupic\SacEventToolBundle\EventListener\Contao;

use Contao\BackendUser;
use Contao\CalendarEventsModel;
use Contao\Config;
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\CoreBundle\Routing\ScopeMatcher;
use Contao\Email;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Class PublishEventListener
 * @package Markocupic\SacEventToolBundle\EventListener\Contao
 */
class PublishEventListener
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
     * PublishEventListener constructor.
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
     * @throws \Exception
     */
    public function onPublishEvent(CalendarEventsModel $objEvent): void
    {
        if ($this->isBackend())
        {
            $backendUserAdapter = $this->framework->getAdapter(BackendUser::class);
            $configAdapter = $this->framework->getAdapter(Config::class);
            $objCalendar = $objEvent->getRelated('pid');
            if ($objCalendar !== null)
            {
                if ($objCalendar->adviceOnEventReleaseLevelChange !== '')
                {
                    $objUser = $backendUserAdapter->getInstance();
                    $objEmail = new Email();
                    $objEmail->from = $configAdapter->get('adminEmail');
                    $objEmail->fromName = 'Administrator SAC Pilatus';
                    $objEmail->subject = sprintf('Event %s wurde veröffentlicht', $objEvent->title);
                    $objEmail->text = sprintf("Hallo \nEvent %s wurde soeben durch %s veröffentlicht. \n\nDies ist eine automatische Nachricht mit Ursprung in: %s\n Liebe Grüsse\n\nAdministrator SAC Pilatus", $objEvent->title, $objUser->name, __METHOD__ . ' LINE: ' . __LINE__);
                    $objEmail->sendTo($objCalendar->adviceOnEventReleaseLevelChange);
                }
            }
        }
    }

    /**
     * Identify the Contao scope (TL_MODE) of the current request
     * @return bool
     */
    protected function isBackend(): bool
    {
        return $this->scopeMatcher->isBackendRequest($this->requestStack->getCurrentRequest());
    }

}
