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

namespace Markocupic\SacEventToolBundle\EventListener\Contao;

use Contao\CalendarEventsModel;
use Contao\CoreBundle\Framework\Adapter;
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\FrontendUser;
use Contao\Input;
use Contao\Template;
use Markocupic\SacEventToolBundle\CalendarEventsHelper;
use Markocupic\SacEventToolBundle\ContaoScope\ContaoScope;
use Symfony\Component\Security\Core\Security;

class EventParseTemplateListener
{
    private ContaoFramework $framework;
    private Security $security;
    private ContaoScope $contaoScope;

    // Adapters
    private Adapter $calendarEventsHelperAdapter;
    private Adapter $inputAdapter;
    private Adapter $calendarEventsModelAdapter;

    public function __construct(ContaoFramework $framework, Security $security, ContaoScope $contaoScope)
    {
        $this->framework = $framework;
        $this->security = $security;
        $this->contaoScope = $contaoScope;

        // Adapters
        $this->calendarEventsHelperAdapter = $this->framework->getAdapter(CalendarEventsHelper::class);
        $this->inputAdapter = $this->framework->getAdapter(Input::class);
        $this->calendarEventsModelAdapter = $this->framework->getAdapter(CalendarEventsModel::class);
    }

    /**
     * Augment event detail template with more data from the CalendarEventsHelper class.
     *
     * @throws \Exception
     */
    public function onParseTemplate(Template $template): void
    {
        if (!empty($template->id) && str_starts_with($template->getName(), 'event_') && $this->contaoScope->isFrontend()) {
            // Run this code for event detail modules only
            if ($this->inputAdapter->get('events')) {
                if (null !== ($event = $this->calendarEventsModelAdapter->findByIdOrAlias($this->inputAdapter->get('events')))) {
                    $template->contaoScope = $this->contaoScope;

                    $user = $this->security->getUser();
                    $template->hasLoggedInFrontendUser = $user instanceof FrontendUser;

                    // Add twig callable "schemaOrg()"
                    $template->addSchemaOrg = static function () use ($template): void {
                        $schemaOrg = $template->getSchemaOrgData();

                        if ($schemaOrg && $template->hasDetails()) {
                            $schemaOrg['description'] = $template->rawHtmlToPlainText($template->details);
                        }

                        $template->addSchemaOrg($schemaOrg);
                    };

                    // Add twig callable "getEventData()"
                    $template->getEventData = (static fn ($prop) => CalendarEventsHelper::getEventData($event, $prop));

                    $this->calendarEventsHelperAdapter->addEventDataToTemplate($template);
                }
            }
        }
    }
}
