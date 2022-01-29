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

use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;

/**
 * Class MarkocupicSacEventToolExtension.
 */
class MarkocupicSacEventToolExtension extends Extension
{

    /**
     * {@inheritdoc}
     */
    public function getAlias(): string
    {
        return Configuration::ROOT_KEY;
    }

    /**
     * @throws \Exception
     */
    public function load(array $configs, ContainerBuilder $container): void
    {
        $configuration = new Configuration();

        $config = $this->processConfiguration($configuration, $configs);

        $loader = new YamlFileLoader(
            $container,
            new FileLocator(__DIR__.'/../Resources/config')
        );

        $loader->load('listener.yml');
        $loader->load('subscriber.yml');
        $loader->load('parameters.yml');
        $loader->load('controller-download.yml');
        $loader->load('controller-ajax.yml');
        $loader->load('controller-contao-frontend-module.yml');
        $loader->load('controller-contao-content-element.yml');
        $loader->load('controller-feed.yml');
        $loader->load('sac-member-database.yml');
        $loader->load('cron.yml');
        $loader->load('contao-mode.yml');
        $loader->load('services.yml');
        $loader->load('data-container.yml');


        $rootKey = $this->getAlias();

        $container->setParameter($rootKey.'.test', $config['test']);
        $container->setParameter($rootKey.'.member_sync_credentials', $config['member_sync_credentials']);

        $this->merge($container);
    }


    public function merge(ContainerBuilder $container): void
    {
        // Merge parameters
        if (isset($GLOBALS['TL_CONFIG']) && \is_array($GLOBALS['TL_CONFIG'])) {
            foreach ($GLOBALS['TL_CONFIG'] as $key => $value) {
                if (false !== strpos($key, 'SAC_EVT_')) {
                    if (!empty($value) && \is_array($value)) {
                        $container->setParameter($key, json_encode($value));
                    } else {
                        $container->setParameter($key, $value);
                    }
                }
            }
        }
    }
}
