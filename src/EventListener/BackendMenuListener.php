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

namespace Markocupic\SacEventToolBundle\EventListener;

use Contao\CoreBundle\Event\ContaoCoreEvents;
use Contao\CoreBundle\Event\MenuEvent;
use Contao\CoreBundle\Security\ContaoCorePermissions;
use Markocupic\SacEventToolBundle\Controller\BackendModule\SacBackendUserRolesExportController;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

readonly class BackendMenuListener
{
    public function __construct(
        private RequestStack $requestStack,
        private RouterInterface $router,
        private Security $security,
        private TranslatorInterface $translator,
    ) {
    }

    #[AsEventListener(ContaoCoreEvents::BACKEND_MENU_BUILD, priority: -255)]
    public function addUserRolesExportMenuItem(MenuEvent $event): void
    {
        if (!$this->checkPermission(SacBackendUserRolesExportController::BACKEND_MODULE_TYPE)) {
            return;
        }

        $factory = $event->getFactory();
        $tree = $event->getTree();

        if ('mainMenu' !== $tree->getName()) {
            return;
        }

        // Add an entry to the Contao backend menu
        $contentNode = $tree->getChild(SacBackendUserRolesExportController::BACKEND_MODULE_CATEGORY);

        $node = $factory
            ->createItem(SacBackendUserRolesExportController::BACKEND_MODULE_TYPE)
            ->setUri($this->router->generate(SacBackendUserRolesExportController::class))
            ->setLabel($this->translator->trans('MOD.'.SacBackendUserRolesExportController::BACKEND_MODULE_TYPE.'.0', [], 'contao_default'))
            ->setLinkAttribute('title', $this->translator->trans('MOD.'.SacBackendUserRolesExportController::BACKEND_MODULE_TYPE.'.1', [], 'contao_default'))
            ->setLinkAttribute('class', SacBackendUserRolesExportController::BACKEND_MODULE_TYPE)
            ->setLinkAttribute('data-turbo-prefetch', 'false')
            ->setCurrent(SacBackendUserRolesExportController::class === $this->requestStack->getCurrentRequest()->get('_controller'))
        ;

        $contentNode->addChild($node);
    }

    private function checkPermission(string $moduleType): bool
    {
        if ($this->security->isGranted('ROLE_ADMIN')) {
            return true;
        }

        return $this->security->isGranted(ContaoCorePermissions::USER_CAN_ACCESS_MODULE, $moduleType);
    }
}
