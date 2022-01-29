<?php

declare(strict_types=1);

/*
 * This file is part of SAC Event Tool Bundle.
 *
 * (c) Marko Cupic 2022 <m.cupic@gmx.ch>
 * @license GPL-3.0-or-later
 * For the full copyright and license information,
 * please view the LICENSE file that was distributed with this source code.
 * @link https://github.com/markocupic/sac-event-tool-bundle
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
                // Section ids [4250,4251,4252,4253,4254]
                ->arrayNode('section_ids')
                    ->scalarPrototype()
                        ->info('Get section ids at the Zentralstelle in Bern.')
                        ->cannotBeEmpty()
                    ->end()
                ->end()
                // Member database sync Zentralverband Bern
                ->arrayNode('member_sync_credentials')
                    ->children()
                        ->scalarNode('hostname')->cannotBeEmpty()->end()
                        ->scalarNode('username')->cannotBeEmpty()->end()
                        ->scalarNode('password')->cannotBeEmpty()->end()
                    ->end()
                ->end()


            ->end()

        ;

        return $treeBuilder;
    }
}
