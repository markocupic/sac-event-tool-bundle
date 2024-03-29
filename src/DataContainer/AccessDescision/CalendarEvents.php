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

namespace Markocupic\SacEventToolBundle\DataContainer\AccessDescision;

use Contao\Backend;
use Contao\BackendUser;
use Contao\CalendarEventsModel;
use Contao\Controller;
use Contao\CoreBundle\DependencyInjection\Attribute\AsCallback;
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

class CalendarEvents
{
    // Adapters
    private Adapter $image;
    private Adapter $backend;
    private Adapter $calendarEventsHelper;
    private Adapter $calendarEventsModel;
    private Adapter $controller;
    private Adapter $message;
    private Adapter $stringUtil;
    private Adapter $system;

    public function __construct(
        private readonly ContaoFramework $framework,
        private readonly RequestStack $requestStack,
        private readonly Connection $connection,
        private readonly Security $security,
        private readonly EventReleaseLevelUtil $eventReleaseLevelUtil,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly LoggerInterface|null $contaoGeneralLogger = null,
    ) {
        // Adapters
        $this->backend = $this->framework->getAdapter(Backend::class);
        $this->image = $this->framework->getAdapter(Image::class);
        $this->calendarEventsHelper = $this->framework->getAdapter(CalendarEventsHelper::class);
        $this->calendarEventsModel = $this->framework->getAdapter(CalendarEventsModel::class);
        $this->controller = $this->framework->getAdapter(Controller::class);
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

        // Prevent unauthorized deletion
        if ('delete' === $request->query->get('act')) {
            $eventId = $this->connection->fetchOne('SELECT id FROM tl_calendar_events WHERE id = ?', [$dc->id]);

            if ($eventId) {
                if (!$this->security->isGranted(CalendarEventsVoter::CAN_DELETE_EVENT, $eventId)) {
                    $this->message->addError(sprintf($GLOBALS['TL_LANG']['MSC']['missingPermissionsToDeleteEvent'], $eventId));
                    $this->controller->redirect($this->system->getReferer());
                }
            }
        }

        // Prevent unauthorized publishing
        if ($request->query->has('tid')) {
            $tid = $request->query->get('tid');
            $eventId = $this->connection->fetchOne('SELECT id FROM tl_calendar_events WHERE id = ?', [$tid]);

            if ($eventId && !$this->security->isGranted(CalendarEventsVoter::CAN_WRITE_EVENT, $eventId)) {
                $this->message->addError(sprintf($GLOBALS['TL_LANG']['MSC']['missingPermissionsToPublishOrUnpublishEvent'], $eventId));
                $this->controller->redirect($this->system->getReferer());
            }
        }

        // Prevent unauthorized editing
        if ('edit' === $request->query->get('act')) {
            // An anonymous function increases the readability of the code
            (
                function (): void {
                    $request = $this->requestStack->getCurrentRequest();

                    $objEventsModel = $this->calendarEventsModel->findByPk($request->query->get('id'));

                    if (null === $objEventsModel) {
                        return;
                    }

                    if (null === EventReleaseLevelPolicyModel::findByPk($objEventsModel->eventReleaseLevel)) {
                        return;
                    }

                    /** @var BackendUser $user */
                    $user = $this->security->getUser();

                    if ($user->id !== $objEventsModel->registrationGoesTo && !$this->security->isGranted(CalendarEventsVoter::CAN_WRITE_EVENT, $objEventsModel->id)) {
                        // User has no write access to the data record, that's why we display field values without a form input
                        foreach (array_keys($GLOBALS['TL_DCA']['tl_calendar_events']['fields']) as $fieldName) {
                            $GLOBALS['TL_DCA']['tl_calendar_events']['fields'][$fieldName]['input_field_callback'] = [\Markocupic\SacEventToolBundle\DataContainer\CalendarEvents::class, 'showFieldValue'];
                        }

                        // User is not allowed to submit any data!
                        if ('tl_calendar_events' === $request->request->get('FORM_SUBMIT')) {
                            $this->message->addError(sprintf($GLOBALS['TL_LANG']['MSC']['missingPermissionsToEditEvent'], $objEventsModel->id));
                            $this->controller->redirect($this->system->getReferer());
                        }
                    } else {
                        // User has write access to all fields on the first e.r.level.
                        // If the e.r.level is > 1 ...
                        // fields with the flag $GLOBALS['TL_DCA']['tl_calendar_events']['fields'][$fieldName]['allowEditingOnFirstReleaseLevelOnly'] === true,
                        // are readonly
                        $objEventReleaseLevelPolicyPackageModel = EventReleaseLevelPolicyPackageModel::findReleaseLevelPolicyPackageModelByEventId($objEventsModel->id);

                        // The event belongs not to an e.r.l.package
                        if (null === $objEventReleaseLevelPolicyPackageModel) {
                            return;
                        }

                        // The event has no e.r.level
                        if (empty($objEventsModel->eventReleaseLevel)) {
                            return;
                        }

                        // Get the first e.r.level of the e.r.l.package the event belongs to
                        $objEventReleaseLevelPolicyModel = EventReleaseLevelPolicyModel::findLowestLevelByEventId($objEventsModel->id);

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
        }
        // End prevent unauthorized editing

        // Check if user has the permission to cut events
        if ('paste' === $request->query->get('act') && 'cut' === $request->query->get('mode') && $request->query->has('id')) {
            $eventId = $request->query->get('id');
            $blnAllow = $this->security->isGranted(CalendarEventsVoter::CAN_CUT_EVENT, $eventId);

            if (!$blnAllow) {
                $this->message->addInfo(sprintf('Du hast keine Berechtigung den Event mit ID %d zu verschieben', $eventId));
                $this->controller->redirect($this->system->getReferer());
            }
        }

        // Check if user has the permission to cut events in the select all mode
        if ('cutAll' === $request->query->get('act')) {
            // An anonymous function increases the readability of the code
            (
                function (): void {
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
        }

        // Allow select mode only, if an eventReleaseLevel filter is set
        if ('select' === $request->query->get('act')) {
            $objSessionBag = $request->getSession()->getBag('contao_backend');

            $session = $objSessionBag->all();

            $filter = DataContainer::MODE_PARENT === $GLOBALS['TL_DCA']['tl_calendar_events']['list']['sorting']['mode'] ? 'tl_calendar_events_'.$dc->currentPid : 'tl_calendar_events';

            if (!isset($session['filter'][$filter]['eventReleaseLevel'])) {
                $this->message->addInfo('"Mehrere bearbeiten" nur möglich, wenn ein Freigabestufen-Filter gesetzt wurde."');
                $this->controller->redirect($this->system->getReferer());
            }
        }

        // Only list record if the currently logged-in backend user has write-permissions.
        if ('select' === $request->query->get('act') || 'editAll' === $request->query->get('act')) {
            $arrIDS = [0];

            $ids = $this->connection->fetchFirstColumn('SELECT id FROM tl_calendar_events WHERE pid = ?', [$dc->currentPid]);

            foreach ($ids as $id) {
                if ($this->security->isGranted(CalendarEventsVoter::CAN_WRITE_EVENT, $id)) {
                    $arrIDS[] = $id;
                }
            }

            $GLOBALS['TL_DCA']['tl_calendar_events']['list']['sorting']['root'] = $arrIDS;
        }

        // Do not allow editing write-protected fields in editAll mode
        // Use input_field_callback to only display the field values without the form input field
        if ('editAll' === $request->query->get('act') || 'overrideAll' === $request->query->get('act')) {
            // An anonymous function increases the readability of the code
            (
                function (): void {
                    $session = $this->requestStack->getSession()->get('CURRENT');
                    $arrIDS = $session['IDS'];

                    if (empty($arrIDS) || !\is_array($arrIDS)) {
                        return;
                    }

                    $objEventsModel = $this->calendarEventsModel->findByPk($arrIDS[0]);

                    if (null === $objEventsModel) {
                        return;
                    }

                    if (empty($objEventsModel->eventReleaseLevel)) {
                        return;
                    }

                    $objEventReleaseLevelPolicyModel = EventReleaseLevelPolicyModel::findLowestLevelByEventId($objEventsModel->id);

                    if (null === $objEventReleaseLevelPolicyModel) {
                        return;
                    }

                    if ($objEventReleaseLevelPolicyModel->id === $objEventsModel->eventReleaseLevel) {
                        return;
                    }

                    $request = $this->requestStack->getCurrentRequest();

                    foreach (array_keys($GLOBALS['TL_DCA']['tl_calendar_events']['fields'] ?? []) as $fieldName) {
                        if (true === ($GLOBALS['TL_DCA']['tl_calendar_events']['fields'][$fieldName]['allowEditingOnFirstReleaseLevelOnly'] ?? false)) {
                            if ('editAll' === $request->query->get('act')) {
                                $GLOBALS['TL_DCA']['tl_calendar_events']['fields'][$fieldName]['input_field_callback'] = [\Markocupic\SacEventToolBundle\DataContainer\CalendarEvents::class, 'showFieldValue'];
                            } else {
                                unset($GLOBALS['TL_DCA']['tl_calendar_events']['fields'][$fieldName]);
                            }
                        }
                    }
                }
            )();
        }
    }

    /**
     * Push event to next release level.
     *
     * @throws \Exception
     */
    #[AsCallback(table: 'tl_calendar_events', target: 'list.operations.releaseLevelNext.button', priority: 100)]
    public function releaseLevelNext(array $row, string|null $href, string $label, string $title, string|null $icon, string $attributes): string
    {
        $request = $this->requestStack->getCurrentRequest();

        $strDirection = 'up';

        $blnAllow = false;
        $objReleaseLevelModel = EventReleaseLevelPolicyModel::findByPk($row['eventReleaseLevel']);
        $nextReleaseLevel = null;

        if (null !== $objReleaseLevelModel) {
            $nextReleaseLevel = $objReleaseLevelModel->level + 1;
        }

        // Save to database
        if ('releaseLevelNext' === $request->query->get('action') && (int) $request->query->get('eventId') === (int) $row['id']) {
            if (true === $this->security->isGranted(CalendarEventsVoter::CAN_UPGRADE_EVENT_RELEASE_LEVEL, $row['id']) && true === EventReleaseLevelPolicyModel::levelExists($row['id'], $nextReleaseLevel)) {
                $objEvent = $this->calendarEventsModel->findByPk($request->query->get('eventId'));

                if (null !== $objEvent) {
                    $objReleaseLevelModelCurrent = EventReleaseLevelPolicyModel::findByPk($objEvent->eventReleaseLevel);
                    $titleCurrent = $objReleaseLevelModelCurrent ? $objReleaseLevelModelCurrent->title : 'not defined';

                    $objReleaseLevelModelNew = EventReleaseLevelPolicyModel::findNextLevel($objEvent->eventReleaseLevel);
                    $titleNew = $objReleaseLevelModelNew ? $objReleaseLevelModelNew->title : 'not defined';

                    if (null !== $objReleaseLevelModelNew) {
                        $objEvent->eventReleaseLevel = $objReleaseLevelModelNew->id;
                        $objEvent->save();

                        // Create new version
                        $objVersions = new Versions('tl_calendar_events', $objEvent->id);
                        $objVersions->initialize();
                        $objVersions->create();

                        // System log
                        $this->contaoGeneralLogger?->info(
                            sprintf(
                                'Event release level for event with ID %d ["%s"] pushed %s from "%s" to "%s".',
                                $objEvent->id,
                                $objEvent->title,
                                $strDirection,
                                $titleCurrent,
                                $titleNew,
                            ),
                        );

                        $this->eventReleaseLevelUtil->handleEventReleaseLevelAndPublishUnpublish((int) $objEvent->id, (int) $objEvent->eventReleaseLevel);

                        // Dispatch ChangeEventReleaseLevelEvent event
                        $event = new ChangeEventReleaseLevelEvent($request, $objEvent, $strDirection);
                        $this->eventDispatcher->dispatch($event);
                    }
                }
            }

            $this->controller->redirect($this->system->getReferer());
        }

        if (true === $this->security->isGranted(CalendarEventsVoter::CAN_UPGRADE_EVENT_RELEASE_LEVEL, $row['id']) && true === EventReleaseLevelPolicyModel::levelExists($row['id'], $nextReleaseLevel)) {
            $blnAllow = true;
        }

        if (!$blnAllow) {
            $icon = str_replace('default', 'disabled', $icon);

            return $this->image->getHtml($icon, $label).' ';
        }

        return '<a href="'.$this->backend->addToUrl($href.'&amp;eventId='.$row['id']).'" title="'.$this->stringUtil->specialchars($title).'"'.$attributes.'>'.$this->image->getHtml($icon, $label).'</a> ';
    }

    /**
     * Downgrade event to the previous release level.
     *
     * @throws \Exception
     */
    #[AsCallback(table: 'tl_calendar_events', target: 'list.operations.releaseLevelPrev.button', priority: 90)]
    public function releaseLevelPrev(array $row, string|null $href, string $label, string $title, string|null $icon, string $attributes): string
    {
        $request = $this->requestStack->getCurrentRequest();

        $strDirection = 'down';

        $blnAllow = false;
        $prevReleaseLevel = null;
        $objReleaseLevelModel = EventReleaseLevelPolicyModel::findByPk($row['eventReleaseLevel']);

        if (null !== $objReleaseLevelModel) {
            $prevReleaseLevel = $objReleaseLevelModel->level - 1;
        }

        // Save to database
        if ('releaseLevelPrev' === $request->query->get('action') && (int) $request->query->get('eventId') === (int) $row['id']) {
            if (true === $this->security->isGranted(CalendarEventsVoter::CAN_DOWNGRADE_EVENT_RELEASE_LEVEL, $row['id']) && true === EventReleaseLevelPolicyModel::levelExists($row['id'], $prevReleaseLevel)) {
                $objEvent = $this->calendarEventsModel->findByPk($request->query->get('eventId'));

                if (null !== $objEvent) {
                    $objReleaseLevelModelCurrent = EventReleaseLevelPolicyModel::findByPk($objEvent->eventReleaseLevel);
                    $titleCurrent = $objReleaseLevelModelCurrent ? $objReleaseLevelModelCurrent->title : 'not defined';

                    $objReleaseLevelModelNew = EventReleaseLevelPolicyModel::findPrevLevel($objEvent->eventReleaseLevel);
                    $titleNew = $objReleaseLevelModelNew ? $objReleaseLevelModelNew->title : 'not defined';

                    if (null !== $objReleaseLevelModelNew) {
                        $objEvent->eventReleaseLevel = $objReleaseLevelModelNew->id;
                        $objEvent->save();

                        // Create new version
                        $objVersions = new Versions('tl_calendar_events', $objEvent->id);
                        $objVersions->initialize();
                        $objVersions->create();

                        // System log
                        $this->contaoGeneralLogger?->info(
                            sprintf(
                                'Event release level for event with ID %d ["%s"] pushed %s from "%s" to "%s".',
                                $objEvent->id,
                                $objEvent->title,
                                $strDirection,
                                $titleCurrent,
                                $titleNew,
                            ),
                        );

                        $this->eventReleaseLevelUtil->handleEventReleaseLevelAndPublishUnpublish((int) $objEvent->id, (int) $objEvent->eventReleaseLevel);

                        // Dispatch ChangeEventReleaseLevelEvent event
                        $event = new ChangeEventReleaseLevelEvent($request, $objEvent, $strDirection);
                        $this->eventDispatcher->dispatch($event);
                    }
                }
            }

            $this->controller->redirect($this->system->getReferer());
        }

        if (true === $this->security->isGranted(CalendarEventsVoter::CAN_DOWNGRADE_EVENT_RELEASE_LEVEL, $row['id']) && true === EventReleaseLevelPolicyModel::levelExists($row['id'], $prevReleaseLevel)) {
            $blnAllow = true;
        }

        if (!$blnAllow) {
            $icon = str_replace('default', 'disabled', $icon);

            return $this->image->getHtml($icon, $label).' ';
        }

        return '<a href="'.$this->backend->addToUrl($href.'&amp;eventId='.$row['id']).'" title="'.$this->stringUtil->specialchars($title).'"'.$attributes.'>'.$this->image->getHtml($icon, $label).'</a> ';
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
