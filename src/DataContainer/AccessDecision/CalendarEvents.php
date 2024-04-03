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

namespace Markocupic\SacEventToolBundle\DataContainer\AccessDecision;

use Contao\Backend;
use Contao\CalendarEventsModel;
use Contao\Controller;
use Contao\CoreBundle\DependencyInjection\Attribute\AsCallback;
use Contao\CoreBundle\Exception\AccessDeniedException;
use Contao\CoreBundle\Framework\Adapter;
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\DataContainer;
use Contao\Image;
use Contao\Message;
use Contao\StringUtil;
use Contao\System;
use Contao\Versions;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;
use Markocupic\SacEventToolBundle\CalendarEventsHelper;
use Markocupic\SacEventToolBundle\DataContainer\EventReleaseLevel\EventReleaseLevelUtil;
use Markocupic\SacEventToolBundle\Event\ChangeEventReleaseLevelEvent;
use Markocupic\SacEventToolBundle\Model\EventReleaseLevelPolicyModel;
use Markocupic\SacEventToolBundle\Model\EventReleaseLevelPolicyPackageModel;
use Markocupic\SacEventToolBundle\Security\Voter\CalendarEventsVoter;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Contracts\Translation\TranslatorInterface;

class CalendarEvents
{
    // Adapters
    private Adapter $backend;
    private Adapter $calendarEventsHelper;
    private Adapter $calendarEventsModel;
    private Adapter $controller;
    private Adapter $image;
    private Adapter $message;
    private Adapter $stringUtil;
    private Adapter $system;

    public function __construct(
        private readonly TranslatorInterface $translator,
        private readonly Connection $connection,
        private readonly ContaoFramework $framework,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly EventReleaseLevelUtil $eventReleaseLevelUtil,
        private readonly RequestStack $requestStack,
        private readonly Security $security,
        private readonly LoggerInterface|null $contaoGeneralLogger = null,
    ) {
        // Adapters
        $this->backend = $this->framework->getAdapter(Backend::class);
        $this->calendarEventsHelper = $this->framework->getAdapter(CalendarEventsHelper::class);
        $this->calendarEventsModel = $this->framework->getAdapter(CalendarEventsModel::class);
        $this->controller = $this->framework->getAdapter(Controller::class);
        $this->image = $this->framework->getAdapter(Image::class);
        $this->message = $this->framework->getAdapter(Message::class);
        $this->stringUtil = $this->framework->getAdapter(StringUtil::class);
        $this->system = $this->framework->getAdapter(System::class);
    }

    /**
     * @throws Exception
     */
    #[AsCallback(table: 'tl_calendar_events', target: 'config.onload', priority: 60)]
    public function setPermissions(DataContainer $dc): void
    {
        // Skip here if the user is an admin
        if ($this->security->isGranted('ROLE_ADMIN')) {
            return;
        }

        $request = $this->requestStack->getCurrentRequest();

        // Minimize header fields for default users
        $GLOBALS['TL_DCA']['tl_calendar_events']['list']['sorting']['headerFields'] = ['title'];

        // Do not allow some specific operations for default users
        unset(
            $GLOBALS['TL_DCA']['tl_calendar_events']['list']['operations']['show'],
            $GLOBALS['TL_DCA']['tl_calendar_events']['list']['global_operations']['plus1year'],
            $GLOBALS['TL_DCA']['tl_calendar_events']['list']['global_operations']['minus1year'],
            $GLOBALS['TL_DCA']['tl_calendar_events']['list']['operations']['children'],
        );

        $act = $request->query->get('act');

        switch ($act) {
            case 'edit':
                (
                    function () use ($dc): void {
                        // Prevent unauthorized editing
                        $request = $this->requestStack->getCurrentRequest();

                        $objEventsModel = $this->calendarEventsModel->findByPk($dc->id);

                        if (null === EventReleaseLevelPolicyModel::findByPk($objEventsModel->eventReleaseLevel)) {
                            return;
                        }

                        if (!$this->security->isGranted(CalendarEventsVoter::CAN_WRITE_EVENT, $dc->id)) {
                            // User has no write access to the data record, that's why we display field values without a form input
                            foreach (array_keys($GLOBALS['TL_DCA']['tl_calendar_events']['fields']) as $fieldName) {
                                $GLOBALS['TL_DCA']['tl_calendar_events']['fields'][$fieldName]['input_field_callback'] = [\Markocupic\SacEventToolBundle\DataContainer\CalendarEvents::class, 'showFieldValue'];
                            }

                            // User is not allowed to submit any data!
                            if ('tl_calendar_events' === $request->request->get('FORM_SUBMIT')) {
                                $this->message->addError($this->translator->trans('ERR.missingPermissionsToEditEvent', [$dc->id], 'contao_default'));

                                $this->controller->redirect($this->system->getReferer());
                            }
                        } else {
                            // User has write access to all fields on the first e.r.level.
                            // If the e.r.level is > 1 ...
                            // fields with the flag $GLOBALS['TL_DCA']['tl_calendar_events']['fields'][$fieldName]['allowEditingOnFirstReleaseLevelOnly'] === true,
                            // are readonly
                            $objEventReleaseLevelPolicyPackageModel = EventReleaseLevelPolicyPackageModel::findReleaseLevelPolicyPackageModelByEventId($dc->id);

                            // The event belongs not to an e.r.l.package
                            if (null === $objEventReleaseLevelPolicyPackageModel) {
                                return;
                            }

                            // The event has no e.r.level
                            if (empty($objEventsModel->eventReleaseLevel)) {
                                return;
                            }

                            // Get the first e.r.level of the e.r.l.package the event belongs to
                            $objEventReleaseLevelPolicyModel = EventReleaseLevelPolicyModel::findLowestLevelByEventId($dc->id);

                            if (null === $objEventReleaseLevelPolicyModel) {
                                return;
                            }

                            if ($objEventReleaseLevelPolicyModel->id !== $objEventsModel->eventReleaseLevel) {
                                foreach (array_keys($GLOBALS['TL_DCA']['tl_calendar_events']['fields']) as $fieldName) {
                                    if (empty($GLOBALS['TL_DCA']['tl_calendar_events']['fields'][$fieldName]['inputType'])) {
                                        continue;
                                    }

                                    if (true === ($GLOBALS['TL_DCA']['tl_calendar_events']['fields'][$fieldName]['allowEditingOnFirstReleaseLevelOnly'] ?? false)) {
                                        $GLOBALS['TL_DCA']['tl_calendar_events']['fields'][$fieldName]['input_field_callback'] = [\Markocupic\SacEventToolBundle\DataContainer\CalendarEvents::class, 'showFieldValue'];
                                    }
                                }
                            }
                        }
                    }
                )();
                break;

            case 'delete':
                // Prevent unauthorized deletion
                if (!$this->security->isGranted(CalendarEventsVoter::CAN_DELETE_EVENT, $dc->id)) {
                    $this->message->addError($this->translator->trans('ERR.missingPermissionsToDeleteEvent', [$dc->id], 'contao_default'));

                    $this->controller->redirect($this->system->getReferer());
                }

                break;
            case 'toggle':
                // Prevent unauthorized publishing
                if ('published' === $request->query->get('field')) {
                    if (!$this->security->isGranted(CalendarEventsVoter::CAN_WRITE_EVENT, $dc->id)) {
                        $this->message->addError($this->translator->trans('ERR.missingPermissionsToPublishOrUnpublishEvent', [$dc->id], 'contao_default'));
                        $this->controller->redirect($this->system->getReferer());
                    }
                }

                break;

            case 'paste':
                // Check if user has the permission to cut events
                if ('cut' === $request->query->get('mode')) {
                    $blnAllow = $this->security->isGranted(CalendarEventsVoter::CAN_CUT_EVENT, $dc->id);

                    if (!$blnAllow) {
                        $this->message->addError($this->translator->trans('ERR.missingPermissionsToCutEvent', [$dc->id], 'contao_default'));

                        $this->controller->redirect($this->system->getReferer());
                    }
                }

                break;

            case 'deleteAll':
                (
                    function (): void {
                        // Check if user has the permission to run "deleteAll"
                        $session = $this->requestStack->getSession()->get('CURRENT');
                        $arrIDS = $session['IDS'];

                        if (empty($arrIDS) || !\is_array($arrIDS)) {
                            return;
                        }

                        foreach ($arrIDS as $id) {
                            $objEventsModel = $this->calendarEventsModel->findByPk($id);

                            if (null === $objEventsModel) {
                                throw new \RuntimeException(sprintf('Could not find event with ID %d', $id));
                            }

                            if (!$this->security->isGranted(CalendarEventsVoter::CAN_DELETE_EVENT, $id)) {
                                $this->message->addError($this->translator->trans('ERR.missingPermissionsToDeleteEvent', [$id], 'contao_default'));

                                $this->controller->redirect($this->system->getReferer());
                            }

                            $registrationId = $this->connection->fetchOne('SELECT id FROM tl_calendar_events_member WHERE eventId = ?', [$id]);

                            if ($registrationId) {
                                $this->message->addError($this->translator->trans('ERR.deleteEventMembersBeforeDeleteEvent', [$id], 'contao_default'));

                                $this->controller->redirect($this->system->getReferer());
                            }
                        }
                    }
                )();
                break;
            case 'cutAll':
                (
                    function (): void {
                        // Check if user has the permission to cut events in the select all mode
                        $session = $this->requestStack->getSession()->get('CURRENT');
                        $arrIDS = $session['IDS'];

                        if (empty($arrIDS) || !\is_array($arrIDS)) {
                            return;
                        }

                        $blnAllow = true;

                        foreach ($arrIDS as $id) {
                            $objEventsModel = $this->calendarEventsModel->findByPk($id);

                            if (null === $objEventsModel) {
                                $blnAllow = false;
                                break;
                            }

                            if (!$this->security->isGranted(CalendarEventsVoter::CAN_CUT_EVENT, $id)) {
                                $blnAllow = false;
                                break;
                            }
                        }

                        if (!$blnAllow) {
                            $this->message->addError(sprintf('Keine Berechtigung die Events mit IDS %s zu verschieben.', implode(', ', $arrIDS)));
                            $this->controller->redirect($this->system->getReferer());
                        }
                    }
                )();
                break;

            case 'select':
            case 'editAll':
                (
                    function () use ($dc, $request): void {
                        // Allow the "select" and editAll action only, if an "eventReleaseLevel" filter is set.
                        $objSessionBag = $request->getSession()->getBag('contao_backend');

                        $session = $objSessionBag->all();

                        $filter = DataContainer::MODE_PARENT === $GLOBALS['TL_DCA']['tl_calendar_events']['list']['sorting']['mode'] ? 'tl_calendar_events_'.$dc->currentPid : 'tl_calendar_events';

                        if (!isset($session['filter'][$filter]['eventReleaseLevel'])) {
                            $this->message->addError($this->translator->trans('ERR.setEvtRelLevelForSelectAll', [], 'contao_default'));
                            $this->controller->redirect($this->system->getReferer());
                        }
                    }
                )();

                (
                    function () use ($dc): void {
                        // Only list record if the currently logged-in backend user has write-permissions.
                        $arrIDS = [0];

                        $ids = $this->connection->fetchFirstColumn('SELECT id FROM tl_calendar_events WHERE pid = ?', [$dc->currentPid]);

                        foreach ($ids as $id) {
                            if ($this->security->isGranted(CalendarEventsVoter::CAN_WRITE_EVENT, $id)) {
                                $arrIDS[] = $id;
                            }
                        }

                        $GLOBALS['TL_DCA']['tl_calendar_events']['list']['sorting']['root'] = $arrIDS;
                    }
                )();

                (
                    function () use ($act): void {
                        // Do not allow editing write-protected fields in editAll mode
                        // Use input_field_callback to only display the field values without the form input field
                        if ('editAll' !== $act) {
                            return;
                        }

                        $session = $this->requestStack->getSession()->get('CURRENT');
                        $arrIDS = $session['IDS'];

                        if (empty($arrIDS) || !\is_array($arrIDS)) {
                            return;
                        }

                        // !!! Whether an eventReleaseLevel filter is set
                        // has already been checked above.

                        $request = $this->requestStack->getCurrentRequest();

                        foreach (array_keys($GLOBALS['TL_DCA']['tl_calendar_events']['fields'] ?? []) as $fieldName) {
                            if (true === ($GLOBALS['TL_DCA']['tl_calendar_events']['fields'][$fieldName]['allowEditingOnFirstReleaseLevelOnly'] ?? false)) {
                                if ('editAll' === $request->query->get('act')) {
                                    $GLOBALS['TL_DCA']['tl_calendar_events']['fields'][$fieldName]['input_field_callback'] = [\Markocupic\SacEventToolBundle\DataContainer\CalendarEvents::class, 'showFieldValue'];
                                }
                            }
                        }
                    }
                )();

                break;

            case 'overrideAll':
                    (
                        function (): void {
                            // Do not allow editing write-protected fields in editAll mode
                            // Use input_field_callback to only display the field values without the form input field

                            $session = $this->requestStack->getSession()->get('CURRENT');

                            $arrIDS = $session['IDS'];

                            if (empty($arrIDS) || !\is_array($arrIDS)) {
                                return;
                            }

                            $arrFields = $session['tl_calendar_events'];

                            if (empty($arrFields) || !\is_array($arrFields)) {
                                return;
                            }

                            $objEventsModel = $this->calendarEventsModel->findByPk($arrIDS[0]);

                            if (null === $objEventsModel) {
                                return;
                            }

                            if (empty($objEventsModel->eventReleaseLevel)) {
                                return;
                            }

                            // Check if an "eventReleaseLevel" filter is set.
                            $strIds = implode(',', array_map('\intval', $arrIDS));
                            $arrEventReleaseLevel = $this->connection->fetchFirstColumn("SELECT eventReleaseLevel FROM tl_calendar_events WHERE id IN($strIds) GROUP BY eventReleaseLevel");

                            if (1 !== \count($arrEventReleaseLevel)) {
                                throw new AccessDeniedException('Access to the action "overrideAll" denied, because no "eventReleaseLevel" filter has been set.');
                            }

                            $objEventReleaseLevelPolicyModel = EventReleaseLevelPolicyModel::findByPk($arrEventReleaseLevel[0]);

                            if (null === $objEventReleaseLevelPolicyModel) {
                                return;
                            }

                            foreach (array_keys($GLOBALS['TL_DCA']['tl_calendar_events']['fields'] ?? []) as $fieldName) {
                                if (true === ($GLOBALS['TL_DCA']['tl_calendar_events']['fields'][$fieldName]['allowEditingOnFirstReleaseLevelOnly'] ?? false)) {
                                    unset($GLOBALS['TL_DCA']['tl_calendar_events']['fields'][$fieldName]);
                                }
                            }
                        }
                    )();

                break;
        }
    }

    #[AsCallback(table: 'tl_calendar_events', target: 'config.onload', priority: 60)]
    public function modifyEventReleaseLevel(DataContainer $dc): void
    {
        $request = $this->requestStack->getCurrentRequest();

        if ('edit' !== $request->query->get('act')) {
            return;
        }

        if (7 !== $request->query->count()) {
            // Bypass the versions popup!
            return;
        }

        $action = $request->query->get('action');

        if ('upgradeEventReleaseLevel' !== $action && 'downgradeEventReleaseLevel' !== $action) {
            return;
        }

        $objEvent = $this->calendarEventsModel->findByPk($dc->id);

        if (null === $objEvent) {
            throw new \RuntimeException(sprintf('Event with ID %d not found.', $dc->id));
        }

        if ('upgradeEventReleaseLevel' === $action) {
            if (!$this->security->isGranted(CalendarEventsVoter::CAN_UPGRADE_EVENT_RELEASE_LEVEL, $dc->id)) {
                $this->controller->redirect($this->system->getReferer());
            }
        } else {
            if (!$this->security->isGranted(CalendarEventsVoter::CAN_DOWNGRADE_EVENT_RELEASE_LEVEL, $dc->id)) {
                $this->controller->redirect($this->system->getReferer());
            }
        }

        $objReleaseLevelModel = EventReleaseLevelPolicyModel::findByPk($objEvent->eventReleaseLevel);

        if (null === $objReleaseLevelModel) {
            throw new \RuntimeException(sprintf('Could not find a valid event release level for event with ID %d.', $dc->id));
        }

        $targetReleaseLevel = 'upgradeEventReleaseLevel' === $action ? $objReleaseLevelModel->level + 1 : $objReleaseLevelModel->level - 1;

        if (false === EventReleaseLevelPolicyModel::levelExists($dc->id, $targetReleaseLevel)) {
            $this->controller->redirect($this->system->getReferer());
        }

        $objReleaseLevelModelCurrent = EventReleaseLevelPolicyModel::findByPk($objEvent->eventReleaseLevel);
        $titleCurrent = $objReleaseLevelModelCurrent ? $objReleaseLevelModelCurrent->title : 'not defined';

        if ('upgradeEventReleaseLevel' === $action) {
            $objReleaseLevelModelTarget = EventReleaseLevelPolicyModel::findNextLevel($objEvent->eventReleaseLevel);
        } else {
            $objReleaseLevelModelTarget = EventReleaseLevelPolicyModel::findPrevLevel($objEvent->eventReleaseLevel);
        }

        $titleTarget = $objReleaseLevelModelTarget ? $objReleaseLevelModelTarget->title : 'not defined';

        if (null === $objReleaseLevelModelTarget) {
            $this->controller->redirect($this->system->getReferer());
        }

        $objEvent->eventReleaseLevel = $objReleaseLevelModelTarget->id;

        if ($objEvent->isModified()) {
            $objEvent->tstamp = time();
            $objEvent->save();

            // Dispatch ChangeEventReleaseLevelEvent event
            $event = new ChangeEventReleaseLevelEvent($request, $objEvent, 'upgradeEventReleaseLevel' === $action ? 'up' : 'down');
            $this->eventDispatcher->dispatch($event);

            // Publish or unpublish event
            $this->eventReleaseLevelUtil->publishOrUnpublishEventDependingOnEventReleaseLevel((int) $objEvent->id, (int) $objEvent->eventReleaseLevel);

            // Create new version
            $objVersions = new Versions('tl_calendar_events', $objEvent->id);
            $objVersions->initialize();
            $objVersions->create();

            // System log
            $this->contaoGeneralLogger?->info(
                sprintf(
                    'Event release level for event with ID %d ["%s"] has been %s from "%s" to "%s".',
                    $objEvent->id,
                    $objEvent->title,
                    'upgradeEventReleaseLevel' === $action ? 'upgraded' : 'downgraded',
                    $titleCurrent,
                    $titleTarget,
                ),
            );
        }

        $this->controller->redirect($this->system->getReferer());
    }

    /**
     * @throws \Exception
     */
    #[AsCallback(table: 'tl_calendar_events', target: 'list.operations.upgradeEventReleaseLevel.button', priority: 100)]
    #[AsCallback(table: 'tl_calendar_events', target: 'list.operations.downgradeEventReleaseLevel.button', priority: 100)]
    public function downOrUpgradeEventReleaseLevelIcon(array $row, string|null $href, string $label, string $title, string|null $icon, string $attributes): string
    {
        $mode = str_contains((string) $href, 'upgradeEventReleaseLevel') ? 'upgradeEventReleaseLevel' : 'downgradeEventReleaseLevel';

        $blnAllow = true;
        $objReleaseLevelModel = EventReleaseLevelPolicyModel::findByPk($row['eventReleaseLevel']);
        $targetReleaseLevel = null;

        if ('upgradeEventReleaseLevel' === $mode) {
            if (null !== $objReleaseLevelModel) {
                $targetReleaseLevel = $objReleaseLevelModel->level + 1;
            }

            if (!$this->security->isGranted(CalendarEventsVoter::CAN_UPGRADE_EVENT_RELEASE_LEVEL, $row['id'])) {
                $blnAllow = false;
            }
        } else {
            if (null !== $objReleaseLevelModel) {
                $targetReleaseLevel = $objReleaseLevelModel->level - 1;
            }

            if (!$this->security->isGranted(CalendarEventsVoter::CAN_DOWNGRADE_EVENT_RELEASE_LEVEL, $row['id'])) {
                $blnAllow = false;
            }
        }

        if (!EventReleaseLevelPolicyModel::levelExists($row['id'], $targetReleaseLevel)) {
            $blnAllow = false;
        }

        if (!$blnAllow) {
            $icon = str_replace('default', 'disabled', $icon);

            return $this->image->getHtml($icon, $label).' ';
        }

        return '<a href="'.$this->backend->addToUrl($href.'&amp;id='.$row['id']).'" title="'.$this->stringUtil->specialchars($title).'"'.$attributes.'>'.$this->image->getHtml($icon, $label).'</a> ';
    }

    #[AsCallback(table: 'tl_calendar_events', target: 'list.operations.delete.button', priority: 80)]
    public function deleteIcon(array $row, string|null $href, string $label, string $title, string|null $icon, string $attributes): string
    {
        $blnAllow = $this->security->isGranted(CalendarEventsVoter::CAN_DELETE_EVENT, $row['id']);

        if (!$blnAllow) {
            $icon = str_replace('.svg', '--disabled.svg', $icon);

            return $this->image->getHtml($icon, $label).' ';
        }

        return '<a href="'.$this->backend->addToUrl($href.'&amp;id='.$row['id']).'" title="'.$this->stringUtil->specialchars($title).'"'.$attributes.'>'.$this->image->getHtml($icon, $label).'</a> ';
    }

    #[AsCallback(table: 'tl_calendar_events', target: 'list.operations.cut.button', priority: 70)]
    public function cutIcon(array $row, string|null $href, string $label, string $title, string|null $icon, string $attributes): string
    {
        $blnAllow = $this->security->isGranted(CalendarEventsVoter::CAN_CUT_EVENT, $row['id']);

        if (!$blnAllow) {
            $icon = str_replace('.svg', '--disabled.svg', $icon);

            return $this->image->getHtml($icon, $label).' ';
        }

        return '<a href="'.$this->backend->addToUrl($href.'&amp;id='.$row['id']).'" title="'.$this->stringUtil->specialchars($title).'"'.$attributes.'>'.$this->image->getHtml($icon, $label).'</a> ';
    }

    #[AsCallback(table: 'tl_calendar_events', target: 'list.operations.copy.button', priority: 70)]
    public function copyIcon(array $row, string|null $href, string $label, string $title, string|null $icon, string $attributes): string
    {
        $blnAllow = $this->security->isGranted(CalendarEventsVoter::CAN_WRITE_EVENT, $row['id']);

        if (!$blnAllow) {
            $icon = str_replace('.svg', '--disabled.svg', $icon);

            return $this->image->getHtml($icon, $label).' ';
        }

        return '<a href="'.$this->backend->addToUrl($href.'&amp;id='.$row['id']).'" title="'.$this->stringUtil->specialchars($title).'"'.$attributes.'>'.$this->image->getHtml($icon, $label).'</a> ';
    }

    #[AsCallback(table: 'tl_calendar_events', target: 'list.operations.preview.button', priority: 70)]
    public function previewIcon(array $row, string|null $href, string $label, string $title, string|null $icon, string $attributes): string
    {
        $eventModel = $this->calendarEventsModel->findByPk($row['id']);

        $href = $this->calendarEventsHelper->generateEventPreviewUrl($eventModel);

        return '<a href="'.$href.'" title="'.$this->stringUtil->specialchars($title).'"'.$attributes.'>'.$this->image->getHtml($icon, $label).'</a> ';
    }
}
