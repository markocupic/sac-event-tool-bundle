<?php

declare(strict_types=1);

/*
 * This file is part of SAC Event Feedback Bundle.
 *
 * (c) Marko Cupic 2021 <m.cupic@gmx.ch>
 * @license MIT
 * For the full copyright and license information,
 * please view the LICENSE file that was distributed with this source code.
 * @link https://github.com/markocupic/sac-event-feedback
 */

namespace Markocupic\SacEventToolBundle\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

class Configuration implements ConfigurationInterface
{
    public const ROOT_KEY = 'sacevt';

    public function getConfigTreeBuilder()
    {
        $treeBuilder = new TreeBuilder(self::ROOT_KEY);

        $treeBuilder->getRootNode()
            ->children()
                ->scalarNode('test')->defaultValue('foo')->cannotBeEmpty()->end()
                ->arrayNode('member_sync_credentials')
                    //->useAttributeAsKey('name')
                    //->arrayPrototype()
                    ->children()
                        ->scalarNode('hostname')->cannotBeEmpty()->defaultValue('ftpserver.sac-cas.ch')->end()
                        ->scalarNode('username')->cannotBeEmpty()->defaultValue('***')->end()
                        ->scalarNode('password')->cannotBeEmpty()->defaultValue('***')->end()
                    ->end()
                ->end()

            ->end()


        ;

        return $treeBuilder;
    }
}

