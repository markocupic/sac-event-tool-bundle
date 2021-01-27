<?php

declare(strict_types=1);

/*
 * This file is part of SAC Event Tool Bundle.
 *
 * (c) Marko Cupic 2021 <m.cupic@gmx.ch>
 * @license MIT
 * For the full copyright and license information,
 * please view the LICENSE file that was distributed with this source code.
 * @link https://github.com/markocupic/sac-event-tool-bundle
 */

namespace Markocupic\SacEventToolBundle\ContaoManager;

use Contao\ManagerPlugin\Bundle\BundlePluginInterface;
use Contao\ManagerPlugin\Bundle\Config\BundleConfig;
use Contao\ManagerPlugin\Bundle\Parser\ParserInterface;
use Contao\ManagerPlugin\Config\ConfigPluginInterface;
use Contao\ManagerPlugin\Routing\RoutingPluginInterface;
use Symfony\Component\Config\Loader\LoaderInterface;
use Symfony\Component\Config\Loader\LoaderResolverInterface;
use Symfony\Component\HttpKernel\KernelInterface;

/**
 * Class Plugin
 * Plugin for the Contao Manager.
 */
class Plugin implements BundlePluginInterface, RoutingPluginInterface, ConfigPluginInterface
{
    /**
     * {@inheritdoc}
     */
    public function getBundles(ParserInterface $parser)
    {
        return [
            BundleConfig::create('Markocupic\SacEventToolBundle\MarkocupicSacEventToolBundle')
                ->setLoadAfter(['Contao\CoreBundle\ContaoCoreBundle'])
                ->setLoadAfter(['Contao\CalendarBundle\ContaoCalendarBundle']),
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function getRouteCollection(LoaderResolverInterface $resolver, KernelInterface $kernel)
    {
        return $resolver
            ->resolve(__DIR__.'/../Resources/config/routing.yml')
            ->load(__DIR__.'/../Resources/config/routing.yml')
        ;
    }

    /**
     * @throws \Exception
     */
    public function registerContainerConfiguration(LoaderInterface $loader, array $managerConfig): void
    {
        $loader->load(__DIR__.'/../Resources/config/listener.yml');
        $loader->load(__DIR__.'/../Resources/config/parameters.yml');
        $loader->load(__DIR__.'/../Resources/config/controller-download.yml');
        $loader->load(__DIR__.'/../Resources/config/controller-ajax.yml');
        $loader->load(__DIR__.'/../Resources/config/controller-contao-frontend-module.yml');
        $loader->load(__DIR__.'/../Resources/config/controller-contao-content-element.yml');
        $loader->load(__DIR__.'/../Resources/config/sac-member-database.yml');
        $loader->load(__DIR__.'/../Resources/config/cache.yml');
        $loader->load(__DIR__.'/../Resources/config/cron.yml');
        $loader->load(__DIR__.'/../Resources/config/contao-mode.yml');
        $loader->load(__DIR__.'/../Resources/config/services.yml');
    }
}
