<?php

/**
 * SAC Event Tool Web Plugin for Contao
 * Copyright (c) 2008-2020 Marko Cupic
 * @package sac-event-tool-bundle
 * @author Marko Cupic m.cupic@gmx.ch, 2017-2020
 * @link https://github.com/markocupic/sac-event-tool-bundle
 */

declare(strict_types=1);

namespace Markocupic\SacEventToolBundle\EventListener\Contao;

use Contao\BackendUser;
use Contao\CalendarEventsModel;
use Contao\Config;
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\CoreBundle\Routing\ScopeMatcher;
use Contao\Email;
use Markocupic\SacEventToolBundle\ContaoMode\ContaoMode;
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
     * @var ContaoMode;
     */
    private $contaoMode;

    /**
     * PublishEventListener constructor.
     * @param ContaoFramework $framework
     * @param ContaoMode $contaoMode
     */
    public function __construct(ContaoFramework $framework, ContaoMode $contaoMode)
    {
        $this->framework = $framework;
        $this->contaoMode = $contaoMode;
    }

    /**
     * @param CalendarEventsModel $objEvent
     * @throws \Exception
     */
    public function onPublishEvent(CalendarEventsModel $objEvent): void
    {
        if ($this->contaoMode->isBackend())
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

}
