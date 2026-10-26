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
use Contao\CoreBundle\EventListener\DataContainer\UserRootListener;
use Contao\CoreBundle\Security\ContaoCorePermissions;
use Contao\CoreBundle\Security\DataContainer\CreateAction;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

readonly class CalendarContainer
{
    public function __construct(
        private AuthorizationCheckerInterface $authorizationChecker,
        private Security $security,
    ) {
    }

    /**
     * Contao 5.3: Only list the calendar containers enabled for the user
     * (tl_user(_group).calendar_containers). Otherwise, DC_Table denies reading
     * the containers the user has no access to and the whole list fails.
     *
     * As of Contao 5.7, the core UserRootListener does this (see "userRoot" in
     * the DCA). Remove this callback when dropping support for Contao 5.3.
     */
    #[AsCallback(table: 'tl_calendar_container', target: 'config.onload')]
    public function filterRecordsContao53(): void
    {
        if (class_exists(UserRootListener::class)) {
            return;
        }

        $user = $this->security->getUser();

        if (!$user instanceof BackendUser || $this->security->isGranted('ROLE_ADMIN')) {
            return;
        }

        $root = array_filter((array) $user->calendar_containers, is_numeric(...));

        if ([] === $root) {
            $root = [0];
        }

        $GLOBALS['TL_DCA']['tl_calendar_container']['list']['sorting']['root'] = array_map(intval(...), array_values($root));
    }

    /**
     * Only users who may create calendar containers get the "copy" and "show"
     * operations. Important: To create a new record, the user must have write
     * access to at least one field in the related table.
     */
    #[AsCallback(table: 'tl_calendar_container', target: 'list.operations.copy.button')]
    #[AsCallback(table: 'tl_calendar_container', target: 'list.operations.show.button')]
    public function disableIfCreateNotGranted(DataContainerOperation $operation): void
    {
        if (!$this->authorizationChecker->isGranted(ContaoCorePermissions::DC_PREFIX.'tl_calendar_container', new CreateAction('tl_calendar_container', $operation->getRecord()))) {
            $operation->disable();
        }
    }
}
