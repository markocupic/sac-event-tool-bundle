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

class Calendar
{
    public function __construct(
        private AuthorizationCheckerInterface $authorizationChecker,
    ) {
    }

    #[AsCallback(table: 'tl_calendar', target: 'list.sorting.child_record')]
    public function listCalendars(array $arrRow): string
    {
        return $arrRow['title'];
    }

	/**
	 * Do not display the "show" button if the user has not the permission to create
	 * new records.
	 */
    #[AsCallback(table: 'tl_calendar', target: 'list.operations.show.button')]
    public function copyButtonCallback(DataContainerOperation $operation): void
    {
        if (!$this->authorizationChecker->isGranted(ContaoCorePermissions::DC_PREFIX.'tl_calendar', new CreateAction('tl_calendar', $operation->getRecord()))) {
            $operation->disable();
        }
    }
}
