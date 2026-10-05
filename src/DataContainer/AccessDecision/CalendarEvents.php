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

namespace Markocupic\SacEventToolBundle\DataContainer\AccessDecision;

use Contao\Backend;
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
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;
use Markocupic\SacEventToolBundle\DataContainer\CalendarEvents as CalendarEventsDataContainer;
use Markocupic\SacEventToolBundle\DataContainer\EventReleaseLevel\EventReleaseLevelUtil;
use Markocupic\SacEventToolBundle\DataContainer\EventReleaseLevel\Exception\EventReleaseLevelTransitionException;
use Markocupic\SacEventToolBundle\Model\EventReleaseLevelPolicyModel;
use Markocupic\SacEventToolBundle\Model\EventReleaseLevelPolicyPackageModel;
use Markocupic\SacEventToolBundle\Security\Voter\CalendarEventsVoter;
use Markocupic\SacEventToolBundle\Util\CalendarEventsUtil;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Access checks for tl_calendar_events in the backend (non-admin users):
 * - setPermissions(): restricts the actions edit, delete, toggle, paste, deleteAll, cutAll, select, editAll and overrideAll
 * - modifyEventReleaseLevel(): handles the up- and downgrade of the event release level
 * - *Icon(): disables the list operations the user is not allowed to use
 */
class CalendarEvents
{
    private const string TABLE = 'tl_calendar_events';

    private const string ACTION_UPGRADE = 'upgradeEventReleaseLevel';

    private const string ACTION_DOWNGRADE = 'downgradeEventReleaseLevel';

    /**
     * input_field_callback that displays the field value without a form input.
     */
    private const array SHOW_FIELD_VALUE = [CalendarEventsDataContainer::class, 'showFieldValue'];

    // Adapters
    private Adapter $backend;

    private Adapter $calendarEventsModel;

    private Adapter $controller;

    private Adapter $eventReleaseLevelPolicyModel;

    private Adapter $eventReleaseLevelPolicyPackageModel;

    private Adapter $image;

    private Adapter $message;

    private Adapter $stringUtil;

    private Adapter $system;

    public function __construct(
        private readonly CalendarEventsUtil $calendarEventsUtil,
        private readonly Connection $connection,
        private readonly ContaoFramework $framework,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly EventReleaseLevelUtil $eventReleaseLevelUtil,
        private readonly RequestStack $requestStack,
        private readonly Security $security,
        private readonly TranslatorInterface $translator,
        private readonly LoggerInterface|null $contaoGeneralLogger = null,
    ) {
        // Adapters
        $this->backend = $this->framework->getAdapter(Backend::class);
        $this->calendarEventsModel = $this->framework->getAdapter(CalendarEventsModel::class);
        $this->controller = $this->framework->getAdapter(Controller::class);
        $this->eventReleaseLevelPolicyModel = $this->framework->getAdapter(EventReleaseLevelPolicyModel::class);
        $this->eventReleaseLevelPolicyPackageModel = $this->framework->getAdapter(EventReleaseLevelPolicyPackageModel::class);
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
        if ($this->security->isGranted('ROLE_ADMIN')) {
            return;
        }

        $request = $this->requestStack->getCurrentRequest();

        // Minimize header fields for default users
        $GLOBALS['TL_DCA'][self::TABLE]['list']['sorting']['headerFields'] = ['title'];

        // Do not allow some specific operations for default users
        unset(
            $GLOBALS['TL_DCA'][self::TABLE]['list']['operations']['show'],
            $GLOBALS['TL_DCA'][self::TABLE]['list']['global_operations']['plus1year'],
            $GLOBALS['TL_DCA'][self::TABLE]['list']['global_operations']['minus1year'],
            $GLOBALS['TL_DCA'][self::TABLE]['list']['operations']['children'],
        );

        $act = $request->query->get('act');

        switch ($act) {
            case 'edit':
                $this->restrictEdit($dc, $request);
                break;

            case 'delete':
                $this->denyDeleteIfNotAllowed($dc->id);
                break;

            case 'toggle':
                $this->denyPublishIfNotAllowed($dc, $request);
                break;

            case 'paste':
                $this->denyCutIfNotAllowed($dc, $request);
                break;

            case 'deleteAll':
                $this->denyDeleteAllIfNotAllowed();
                break;

            case 'cutAll':
                $this->denyCutAllIfNotAllowed();
                break;

            case 'select':
            case 'editAll':
                $this->requireEventReleaseLevelFilter($dc, $request);
                $this->listWritableEventsOnly($dc);

                if ('editAll' === $act) {
                    $this->restrictFieldsInSelection($request);
                }

                break;

            case 'overrideAll':
                $this->restrictFieldsInSelection($request);
                break;
        }
    }

    /**
     * @throws \RuntimeException
     */
    #[AsCallback(table: 'tl_calendar_events', target: 'config.onload', priority: 60)]
    public function modifyEventReleaseLevel(DataContainer $dc): void
    {
        $request = $this->requestStack->getCurrentRequest();

        if ('edit' !== $request->query->get('act')) {
            return;
        }

        // Do not change the release level inside a popup (e.g. the versions popup)
        if ($request->query->has('popup')) {
            return;
        }

        $action = $request->query->get('action');

        if (self::ACTION_UPGRADE !== $action && self::ACTION_DOWNGRADE !== $action) {
            return;
        }

        $isUpgrade = self::ACTION_UPGRADE === $action;

        $event = $this->calendarEventsModel->findById($dc->id);

        if (null === $event) {
            throw new \RuntimeException(\sprintf('Event with ID %d not found.', $dc->id));
        }

        $voterAttribute = $isUpgrade ? CalendarEventsVoter::CAN_UPGRADE_EVENT_RELEASE_LEVEL : CalendarEventsVoter::CAN_DOWNGRADE_EVENT_RELEASE_LEVEL;

        if (!$this->security->isGranted($voterAttribute, $dc->id)) {
            $this->redirectBack();
        }

        $currentLevel = $this->eventReleaseLevelPolicyModel->findById($event->eventReleaseLevel);

        if (null === $currentLevel) {
            throw new \RuntimeException(\sprintf('Could not find a valid event release level for event with ID %d.', $dc->id));
        }

        $targetLevelNumber = $isUpgrade ? $currentLevel->level + 1 : $currentLevel->level - 1;

        if (false === $this->eventReleaseLevelPolicyModel->levelExists($dc->id, $targetLevelNumber)) {
            $this->redirectBack();
        }

        if ($isUpgrade) {
            $targetLevel = $this->eventReleaseLevelPolicyModel->findNextLevel($event->eventReleaseLevel);
        } else {
            $targetLevel = $this->eventReleaseLevelPolicyModel->findPrevLevel($event->eventReleaseLevel);
        }

        if (null === $targetLevel) {
            $this->redirectBack();
        }

        try {
            $this->eventReleaseLevelUtil->validateEventReleaseLevelTransition($event, $targetLevel->id);
            $this->eventReleaseLevelUtil->shiftEventReleaseLevel($event, $targetLevel, $isUpgrade ? 'up' : 'down');
        } catch (EventReleaseLevelTransitionException $e) {
            $this->message->add($this->translator->trans($e->getTranslatableText(), $e->getParams(), 'contao_default'), $e->getErrorLevel());
        }

        $this->redirectBack();
    }

    #[AsCallback(table: 'tl_calendar_events', target: 'list.operations.upgradeEventReleaseLevel.button', priority: 100)]
    #[AsCallback(table: 'tl_calendar_events', target: 'list.operations.downgradeEventReleaseLevel.button', priority: 100)]
    public function downOrUpgradeEventReleaseLevelIcon(array $row, string|null $href, string $label, string $title, string|null $icon, string $attributes): string
    {
        $isUpgrade = str_contains((string) $href, self::ACTION_UPGRADE);

        $currentLevel = $this->eventReleaseLevelPolicyModel->findById($row['eventReleaseLevel']);
        $targetLevelNumber = null;

        if (null !== $currentLevel) {
            $targetLevelNumber = $isUpgrade ? $currentLevel->level + 1 : $currentLevel->level - 1;
        }

        $voterAttribute = $isUpgrade ? CalendarEventsVoter::CAN_UPGRADE_EVENT_RELEASE_LEVEL : CalendarEventsVoter::CAN_DOWNGRADE_EVENT_RELEASE_LEVEL;
        $isGranted = $this->security->isGranted($voterAttribute, $row['id']);

        $levelExists = $this->eventReleaseLevelPolicyModel->levelExists($row['id'], $targetLevelNumber);

        if (!$isGranted || !$levelExists) {
            return $this->image->getHtml(str_replace('default', 'disabled', $icon), $label).' ';
        }

        return $this->renderOperationLink($this->backend->addToUrl($href.'&amp;id='.$row['id']), $label, $title, $icon, $attributes);
    }

    #[AsCallback(table: 'tl_calendar_events', target: 'list.operations.delete.button', priority: 80)]
    public function deleteIcon(array $row, string|null $href, string $label, string $title, string|null $icon, string $attributes): string
    {
        return $this->renderOperation(CalendarEventsVoter::CAN_DELETE_EVENT, $row, $href, $label, $title, $icon, $attributes);
    }

    #[AsCallback(table: 'tl_calendar_events', target: 'list.operations.cut.button', priority: 70)]
    public function cutIcon(array $row, string|null $href, string $label, string $title, string|null $icon, string $attributes): string
    {
        return $this->renderOperation(CalendarEventsVoter::CAN_CUT_EVENT, $row, $href, $label, $title, $icon, $attributes);
    }

    #[AsCallback(table: 'tl_calendar_events', target: 'list.operations.copy.button', priority: 70)]
    public function copyIcon(array $row, string|null $href, string $label, string $title, string|null $icon, string $attributes): string
    {
        return $this->renderOperation(CalendarEventsVoter::CAN_WRITE_EVENT, $row, $href, $label, $title, $icon, $attributes);
    }

    #[AsCallback(table: 'tl_calendar_events', target: 'list.operations.preview.button', priority: 70)]
    public function previewIcon(array $row, string|null $href, string $label, string $title, string|null $icon, string $attributes): string
    {
        $event = $this->calendarEventsModel->findById($row['id']);

        $href = $this->calendarEventsUtil->generateEventPreviewUrl($event);

        return $this->renderOperationLink($href, $label, $title, $icon, $attributes);
    }

    /**
     * act=edit: Users without write access only see the field values and cannot submit the form.
     * Users with write access cannot edit the fields with the flag "allowEditingOnFirstReleaseLevelOnly"
     * once the event has left the first release level.
     */
    private function restrictEdit(DataContainer $dc, Request $request): void
    {
        $event = $this->calendarEventsModel->findById($dc->id);

        if (null === $event) {
            return;
        }

        if (null === $this->eventReleaseLevelPolicyModel->findById($event->eventReleaseLevel)) {
            return;
        }

        if (!$this->security->isGranted(CalendarEventsVoter::CAN_WRITE_EVENT, $dc->id)) {
            $this->showFieldValuesOnly(false, false);

            // User is not allowed to submit any data!
            if (self::TABLE === $request->request->get('FORM_SUBMIT')) {
                $this->addErrorAndRedirectBack('ERR.missingPermissionsToEditEvent', [$dc->id]);
            }

            return;
        }

        // The event does not belong to an event release level policy package
        if (null === $this->eventReleaseLevelPolicyPackageModel->findReleaseLevelPolicyPackageModelByEventId($dc->id)) {
            return;
        }

        // The event has no event release level
        if (empty($event->eventReleaseLevel)) {
            return;
        }

        // The first event release level of the package the event belongs to
        $firstLevel = $this->eventReleaseLevelPolicyModel->findMinLevelByEventId($dc->id);

        if (null === $firstLevel) {
            return;
        }

        if ((int) $firstLevel->id !== (int) $event->eventReleaseLevel) {
            $this->showFieldValuesOnly(true, true);
        }
    }

    /**
     * act=delete: Events can only be deleted by authorized users and only if there are no registrations.
     */
    private function denyDeleteIfNotAllowed(int|string|null $eventId): void
    {
        if (!$this->security->isGranted(CalendarEventsVoter::CAN_DELETE_EVENT, $eventId)) {
            $this->addErrorAndRedirectBack('ERR.missingPermissionsToDeleteEvent', [$eventId]);
        }

        if ($this->hasRegistrations($eventId)) {
            $this->addErrorAndRedirectBack('ERR.deleteEventMembersBeforeDeleteEvent', [$eventId]);
        }
    }

    /**
     * act=toggle&field=published.
     */
    private function denyPublishIfNotAllowed(DataContainer $dc, Request $request): void
    {
        if ('published' !== $request->query->get('field')) {
            return;
        }

        if (!$this->security->isGranted(CalendarEventsVoter::CAN_WRITE_EVENT, $dc->id)) {
            $this->addErrorAndRedirectBack('ERR.missingPermissionsToPublishOrUnpublishEvent', [$dc->id]);
        }
    }

    /**
     * act=paste&mode=cut.
     */
    private function denyCutIfNotAllowed(DataContainer $dc, Request $request): void
    {
        if ('cut' !== $request->query->get('mode')) {
            return;
        }

        if (!$this->security->isGranted(CalendarEventsVoter::CAN_CUT_EVENT, $dc->id)) {
            $this->addErrorAndRedirectBack('ERR.missingPermissionsToCutEvent', [$dc->id]);
        }
    }

    /**
     * act=deleteAll: Same rules as act=delete for each selected event.
     */
    private function denyDeleteAllIfNotAllowed(): void
    {
        foreach ($this->getSelectedIds() as $id) {
            if (null === $this->calendarEventsModel->findById($id)) {
                throw new \RuntimeException(\sprintf('Could not find event with ID %d', $id));
            }

            if (!$this->security->isGranted(CalendarEventsVoter::CAN_DELETE_EVENT, $id)) {
                $this->addErrorAndRedirectBack('ERR.missingPermissionsToDeleteEvent', [$id]);
            }

            if ($this->hasRegistrations($id)) {
                $this->addErrorAndRedirectBack('ERR.deleteEventMembersBeforeDeleteEvent', [$id]);
            }
        }
    }

    /**
     * act=cutAll: The user must be allowed to cut every selected event.
     */
    private function denyCutAllIfNotAllowed(): void
    {
        $arrIDS = $this->getSelectedIds();
        $blnAllow = true;

        foreach ($arrIDS as $id) {
            if (null === $this->calendarEventsModel->findById($id) || !$this->security->isGranted(CalendarEventsVoter::CAN_CUT_EVENT, $id)) {
                $blnAllow = false;
                break;
            }
        }

        if (!$blnAllow) {
            $this->addErrorAndRedirectBack('ERR.missingPermissionsToCutEvents', [implode(', ', $arrIDS)]);
        }
    }

    /**
     * act=select|editAll: Only allowed if an event release level filter is set.
     */
    private function requireEventReleaseLevelFilter(DataContainer $dc, Request $request): void
    {
        $session = $request->getSession()->getBag('contao_backend')->all();

        $filter = DataContainer::MODE_PARENT === $GLOBALS['TL_DCA'][self::TABLE]['list']['sorting']['mode'] ? 'tl_calendar_events_'.$dc->currentPid : self::TABLE;

        if (!isset($session['filter'][$filter]['eventReleaseLevel'])) {
            $this->addErrorAndRedirectBack('ERR.setEvtRelLevelForSelectAll', []);
        }
    }

    /**
     * act=select|editAll: Only list the events the user has write access to.
     */
    private function listWritableEventsOnly(DataContainer $dc): void
    {
        $arrIDS = [0];

        $ids = $this->connection->fetchFirstColumn('SELECT id FROM tl_calendar_events WHERE pid = ?', [$dc->currentPid]);

        foreach ($ids as $id) {
            if ($this->security->isGranted(CalendarEventsVoter::CAN_WRITE_EVENT, $id)) {
                $arrIDS[] = $id;
            }
        }

        $GLOBALS['TL_DCA'][self::TABLE]['list']['sorting']['root'] = $arrIDS;
    }

    /**
     * act=editAll|overrideAll (after the fields have been selected): The fields with the flag
     * "allowEditingOnFirstReleaseLevelOnly" are displayed without a form input once the selected
     * events have left the first release level.
     */
    private function restrictFieldsInSelection(Request $request): void
    {
        if ('1' !== $request->query->get('fields')) {
            return;
        }

        $session = $this->requestStack->getSession()->get('CURRENT');

        $arrIDS = $session['IDS'] ?? [];

        if (empty($arrIDS) || !\is_array($arrIDS)) {
            return;
        }

        $arrFields = $session[self::TABLE] ?? [];

        if (empty($arrFields) || !\is_array($arrFields)) {
            return;
        }

        // It is sufficient to check the release level of the first event of the selection. As
        // the event release level filter is set, all events of the selection have the same level.
        $event = $this->calendarEventsModel->findById($arrIDS[0]);

        if (null === $event) {
            throw new \RuntimeException(\sprintf('Event with ID %d not found.', $arrIDS[0]));
        }

        $firstLevel = $this->eventReleaseLevelPolicyModel->findMinLevelByEventId($event->id);

        if (null === $firstLevel) {
            throw new \LogicException('Events in this selection do not belong to an event release level policy. As the event release level filter is set, all events in this selection must be assigned to an event release level policy.');
        }

        // No restrictions on the first event release level
        if ((int) $firstLevel->id === (int) $event->eventReleaseLevel) {
            return;
        }

        $this->showFieldValuesOnly(true, false);
    }

    /**
     * Replaces the form input of the fields by their value (input_field_callback).
     *
     * @param bool $firstReleaseLevelOnlyFields only fields with the flag "allowEditingOnFirstReleaseLevelOnly"
     * @param bool $skipFieldsWithoutInputType  skip fields without "inputType"
     */
    private function showFieldValuesOnly(bool $firstReleaseLevelOnlyFields, bool $skipFieldsWithoutInputType): void
    {
        foreach (array_keys($GLOBALS['TL_DCA'][self::TABLE]['fields'] ?? []) as $fieldName) {
            $field = $GLOBALS['TL_DCA'][self::TABLE]['fields'][$fieldName];

            if ($skipFieldsWithoutInputType && empty($field['inputType'])) {
                continue;
            }

            if ($firstReleaseLevelOnlyFields && true !== ($field['allowEditingOnFirstReleaseLevelOnly'] ?? false)) {
                continue;
            }

            $GLOBALS['TL_DCA'][self::TABLE]['fields'][$fieldName]['input_field_callback'] = self::SHOW_FIELD_VALUE;
        }
    }

    private function hasRegistrations(int|string|null $eventId): bool
    {
        return (bool) $this->connection->fetchOne('SELECT id FROM tl_calendar_events_member WHERE eventId = ?', [$eventId]);
    }

    /**
     * IDs selected in the "select all" mode.
     */
    private function getSelectedIds(): array
    {
        $arrIDS = $this->requestStack->getSession()->get('CURRENT')['IDS'] ?? [];

        return \is_array($arrIDS) ? $arrIDS : [];
    }

    private function renderOperation(string $voterAttribute, array $row, string|null $href, string $label, string $title, string|null $icon, string $attributes): string
    {
        if (!$this->security->isGranted($voterAttribute, $row['id'])) {
            return $this->image->getHtml(str_replace('.svg', '--disabled.svg', $icon), $label).' ';
        }

        return $this->renderOperationLink($this->backend->addToUrl($href.'&amp;id='.$row['id']), $label, $title, $icon, $attributes);
    }

    private function renderOperationLink(string $href, string $label, string $title, string|null $icon, string $attributes): string
    {
        return '<a href="'.$this->stringUtil->specialcharsUrl($href).'" title="'.$this->stringUtil->specialchars($title).'"'.$attributes.'>'.$this->image->getHtml($icon, $label).'</a> ';
    }

    private function addErrorAndRedirectBack(string $translationKey, array $params): void
    {
        $this->message->addError($this->translator->trans($translationKey, $params, 'contao_default'));

        $this->redirectBack();
    }

    private function redirectBack(): void
    {
        $this->controller->redirect($this->system->getReferer());
    }
}
