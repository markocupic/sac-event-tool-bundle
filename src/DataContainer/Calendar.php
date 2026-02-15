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

use Codefog\HasteBundle\UrlParser;
use Contao\Controller;
use Contao\CoreBundle\DataContainer\DataContainerOperation;
use Contao\CoreBundle\DependencyInjection\Attribute\AsCallback;
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\CoreBundle\Security\ContaoCorePermissions;
use Contao\CoreBundle\Security\DataContainer\CreateAction;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

readonly class Calendar
{
    public function __construct(
        private AuthorizationCheckerInterface $authorizationChecker,
        private ContaoFramework $framework,
        private RequestStack $requestStack,
        private UrlParser $urlParser,
    ) {
    }

    #[AsCallback(table: 'tl_calendar', target: 'config.onload')]
    public function copyCalendarWithoutChildRecords(): void
    {
        $request = $this->requestStack->getCurrentRequest();

        $sessionData = $request->getSession()->all();

        if (!isset($sessionData['CLIPBOARD']['tl_calendar']['children'])) {
            return;
        }

        $doCopyChildRecords = $sessionData['CLIPBOARD']['tl_calendar']['children'];

        if ($request->query->has('children')) {
            $url = $this->urlParser->removeQueryString(['children']);
            $this->framework->getAdapter(Controller::class)->redirect($url);
        }

        if ('copy' !== $request->query->get('act')) {
            return;
        }

        if ('0' !== $doCopyChildRecords) {
            // This is the normal behavior in the Contao calendar extension
            return;
        }

        $GLOBALS['TL_DCA']['tl_calendar_events']['config']['doNotCopyRecords'] = true;
    }

    #[AsCallback(table: 'tl_calendar', target: 'list.sorting.child_record')]
    public function listCalendars(array $arrRow): string
    {
        return $arrRow['title'];
    }

    /**
     * Do not display the "copy" buttons if the user has not the permission to create
     * new records.
     */
    #[AsCallback(table: 'tl_calendar', target: 'list.operations.copy.button')]
    #[AsCallback(table: 'tl_calendar', target: 'list.operations.copyWithoutChildRecords.button')]
    public function copyButtonCallback(DataContainerOperation $operation): void
    {
        if (!$this->authorizationChecker->isGranted(ContaoCorePermissions::DC_PREFIX.'tl_calendar', new CreateAction('tl_calendar', $operation->getRecord()))) {
            $operation->disable();
        }
    }

    /**
     * Do not display the "show" button if the user has not the permission to create
     * new records.
     */
    #[AsCallback(table: 'tl_calendar', target: 'list.operations.show.button')]
    public function showButtonCallback(DataContainerOperation $operation): void
    {
        if (!$this->authorizationChecker->isGranted(ContaoCorePermissions::DC_PREFIX.'tl_calendar', new CreateAction('tl_calendar', $operation->getRecord()))) {
            $operation->disable();
        }
    }
}
