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

namespace Markocupic\SacEventToolBundle\DependencyInjection\Compiler;

use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * Aliases the cache tag service of the installed Contao version
 * (Contao 6: "contao.cache.tag_manager", Contao 5.3: "contao.cache.entity_tags").
 */
class CacheTagManagerPass implements CompilerPassInterface
{
    public const string ALIAS = 'sacevt.contao_cache_tag_manager';

    private const array SERVICE_IDS = [
        'contao.cache.tag_manager',
        'contao.cache.entity_tags',
    ];

    public function process(ContainerBuilder $container): void
    {
        foreach (self::SERVICE_IDS as $serviceId) {
            if ($container->has($serviceId)) {
                $container->setAlias(self::ALIAS, $serviceId);

                return;
            }
        }
    }
}
