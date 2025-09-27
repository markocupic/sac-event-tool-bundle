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

namespace Markocupic\SacEventToolBundle\DataContainer;

use Contao\BackendUser;
use Contao\CoreBundle\DataContainer\DataContainerOperation;
use Contao\CoreBundle\DependencyInjection\Attribute\AsCallback;
use Contao\CoreBundle\Security\DataContainer\CreateAction;
use Contao\StringUtil;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Types\Types;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\Attribute\AttributeBagInterface;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

readonly class CalendarContainer
{
    public function __construct(
        private AuthorizationCheckerInterface $authorizationChecker,
        private Connection $connection,
        private RequestStack $requestStack,
        private Security $security,
    ) {
    }

    /**
     * Adjusts permissions for calendar container records.
     *
     * This method ensures that the correct permissions are set when calendar container
     * records are created. It checks if the user is an admin, retrieves the user's calendar
     * container permissions, and determines whether adjustments are necessary.
     *
     * For non-admin users:
     * - If the `oncreate_callback` passes an `$insertId` of a new record, the method ensures
     *   that the ID is properly validated and added to the user's or group's list of
     *   accessible calendar containers, provided they have appropriate permissions (`create`).
     * - Modifications are applied at both group-level and individual user-level.
     *
     * Updates the session's newly created records and reflects changes on the user object.
     * Ensures consistency of permissions for custom and group inheritance levels.
     */
    #[AsCallback(table: 'tl_calendar_container', target: 'config.onload')]
    public function adjustPermissions(): void
    {
        // The oncreate_callback passes $insertId as the second argument
        if (4 === \func_num_args()) {
            $insertId = func_get_arg(1);
        }

        if ($this->security->isGranted('ROLE_ADMIN')) {
            return;
        }

        /** @var BackendUser $user */
        $user = $this->security->getUser();

        // Set root IDs
        if (empty($user->calendar_containers) || !\is_array($user->calendar_containers)) {
            $root = [0];
        } else {
            $root = $user->calendar_containers;
        }

        // The calendar is enabled already
        if (isset($insertId) && \in_array($insertId, $root, true)) {
            return;
        }

        /** @var AttributeBagInterface $objSessionBag */
        $objSessionBag = $this->requestStack->getSession()->getBag('contao_backend');
        $arrNew = $objSessionBag->get('new_records');

        if (empty($insertId)) {
            return;
        }

        if (empty($arrNew['tl_calendar_container']) || !\is_array($arrNew['tl_calendar_container'])) {
            return;
        }

        if (!\in_array($insertId, $arrNew['tl_calendar_container'], true)) {
            return;
        }

        // Add the permissions on group-level
        if ('custom' !== $user->inherit) {
            $arrGroups = $this->connection->fetchAllAssociative('SELECT id, calendar_containers, calendar_containerp FROM tl_user_group WHERE id IN('.implode(',', array_map('\intval', $user->groups)).')');

            foreach ($arrGroups as $arrGroup) {
                $arrCalendarContainerp = StringUtil::deserialize($arrGroup['calendar_containerp']);

                if (\is_array($arrCalendarContainerp) && \in_array('create', $arrCalendarContainerp, true)) {
                    $arrCalendarContainers = StringUtil::deserialize($arrGroup['calendar_containers'], true);
                    $arrCalendarContainers[] = $insertId;

                    $set = [
                        'calendar_containers' => serialize($arrCalendarContainers),
                    ];

                    $this->connection->update('tl_user_group', $set, ['id' => $arrGroup['id']], ['id' => Types::INTEGER]);
                }
            }
        }

        // Add the permissions on user-level
        if ('group' !== $user->inherit) {
            $arrUser = $this->connection->fetchAssociative('SELECT calendar_containerp,calendar_containers FROM tl_user WHERE id = ?', [$user->id], [Types::INTEGER]);

            $arrCalendarContainerp = StringUtil::deserialize($arrUser['calendar_containerp']);

            if (\is_array($arrCalendarContainerp) && \in_array('create', $arrCalendarContainerp, true)) {
                $arrCalendarContainers = StringUtil::deserialize($arrUser['calendar_containers'], true);
                $arrCalendarContainers[] = $insertId;

                $set = [
                    'calendar_containers' => serialize($arrCalendarContainers),
                ];

                $this->connection->update('tl_user', $set, ['id' => $user->id], ['id' => Types::INTEGER]);
            }
        }

        // Add the new element to the user object
        $root[] = $insertId;
        $user->calendar_containers = $root;
    }

    /**
     * Important: To create a new record, the user must have write-access to at least
     * one field in the related table.
     */
    #[AsCallback(table: 'tl_calendar_container', target: 'list.operations.copy.button')]
    public function copyButtonCallback(DataContainerOperation $operation): void
    {
        if (!$this->authorizationChecker->isGranted('contao_dc.tl_calendar_container', new CreateAction('tl_calendar_container', $operation->getRecord()))) {
            $operation->disable();
        }
    }

    /**
     * Do not display the "show" button if the user cannot create new records.
     */
    #[AsCallback(table: 'tl_calendar_container', target: 'list.operations.show.button')]
    public function showButtonCallback(DataContainerOperation $operation): void
    {
        $this->copyButtonCallback($operation);
    }
}
