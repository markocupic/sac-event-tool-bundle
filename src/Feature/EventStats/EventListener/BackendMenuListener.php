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

namespace Markocupic\SacEventToolBundle\Feature\EventStats\EventListener;

use Contao\CoreBundle\Event\ContaoCoreEvents;
use Contao\CoreBundle\Event\MenuEvent;
use Contao\CoreBundle\Security\ContaoCorePermissions;
use Markocupic\SacEventToolBundle\Feature\EventStats\Controller\EventStatsController;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Adds the event statistics to the backend menu (group "SAC Module").
 */
#[AsEventListener(ContaoCoreEvents::BACKEND_MENU_BUILD, priority: -255)]
readonly class BackendMenuListener
{
    public function __construct(
        private RequestStack $requestStack,
        private RouterInterface $router,
        private Security $security,
        private TranslatorInterface $translator,
    ) {
    }

    public function __invoke(MenuEvent $event): void
    {
        $tree = $event->getTree();

        if ('mainMenu' !== $tree->getName()) {
            return;
        }

        if (!$this->security->isGranted('ROLE_ADMIN') && !$this->security->isGranted(ContaoCorePermissions::USER_CAN_ACCESS_MODULE, EventStatsController::BACKEND_MODULE_TYPE)) {
            return;
        }

        $category = $tree->getChild(EventStatsController::BACKEND_MODULE_CATEGORY);

        if (null === $category) {
            return;
        }

        $type = EventStatsController::BACKEND_MODULE_TYPE;

        $node = $event->getFactory()
            ->createItem($type)
            ->setUri($this->router->generate(EventStatsController::class))
            ->setLabel($this->translator->trans('MOD.'.$type.'.0', [], 'contao_modules'))
            ->setLinkAttribute('title', $this->translator->trans('MOD.'.$type.'.1', [], 'contao_modules'))
            ->setLinkAttribute('class', $type)
            ->setLinkAttribute('data-turbo-prefetch', 'false')
            ->setCurrent(EventStatsController::class === $this->requestStack->getCurrentRequest()?->get('_controller'))
        ;

        $category->addChild($node);
    }
}
