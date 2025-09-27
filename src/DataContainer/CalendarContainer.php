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

use Contao\CoreBundle\DataContainer\DataContainerOperation;
use Contao\CoreBundle\DependencyInjection\Attribute\AsCallback;
use Contao\CoreBundle\Security\ContaoCorePermissions;
use Contao\CoreBundle\Security\DataContainer\CreateAction;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

readonly class CalendarContainer
{
    public function __construct(
        private AuthorizationCheckerInterface $authorizationChecker,
    ) {
    }

    /**
     * Important: To create a new record, the user must have write access to at least
     * one field in the related table.
     */
    #[AsCallback(table: 'tl_calendar_container', target: 'list.operations.copy.button')]
    public function copyButtonCallback(DataContainerOperation $operation): void
    {
        if (!$this->authorizationChecker->isGranted(ContaoCorePermissions::DC_PREFIX.'tl_calendar_container', new CreateAction('tl_calendar_container', $operation->getRecord()))) {
            $operation->disable();
        }
    }

    /**
     * Do not display the "show" button if the user has not the permission to create
     * new records.
     */
    #[AsCallback(table: 'tl_calendar_container', target: 'list.operations.show.button')]
    public function showButtonCallback(DataContainerOperation $operation): void
    {
        $this->copyButtonCallback($operation);
    }
}
