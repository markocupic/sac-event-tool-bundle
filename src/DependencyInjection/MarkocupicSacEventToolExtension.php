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

        // Friendly configuration
        $rootKey = $this->getAlias();

        $container->setParameter($rootKey.'.section_name', $config['section_name']);
        $container->setParameter($rootKey.'.section_ids', $config['section_ids']);
        $container->setParameter($rootKey.'.member_sync_credentials', $config['member_sync_credentials']);
        $container->setParameter($rootKey.'.event_admin_name', $config['event_admin_name']);
        $container->setParameter($rootKey.'.event_admin_email', $config['event_admin_email']);
        $container->setParameter($rootKey.'.temp_dir', $config['temp_dir']);
        $container->setParameter($rootKey.'.avatar.female', $config['avatar']['female']);
        $container->setParameter($rootKey.'.avatar.male', $config['avatar']['male']);
        $container->setParameter($rootKey.'.user.backend.home_dir', $config['user']['backend']['home_dir']);
        $container->setParameter($rootKey.'.user.frontend.home_dir', $config['user']['frontend']['home_dir']);
        $container->setParameter($rootKey.'.user.frontend.avatar_dir', $config['user']['frontend']['avatar_dir']);
        $container->setParameter($rootKey.'.event.story.asset_dir', $config['event']['story']['asset_dir']);

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
