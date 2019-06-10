<?php

/**
 * SAC Event Tool Web Plugin for Contao
 * Copyright (c) 2008-2019 Marko Cupic
 * @package sac-event-tool-bundle
 * @author Marko Cupic m.cupic@gmx.ch, 2017-2019
 * @link https://github.com/markocupic/sac-event-tool-bundle
 */

namespace Markocupic\SacEventToolBundle\DependencyInjection;

use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;

/**
 * Class MarkocupicSacEventToolExtension
 * @package Markocupic\SacEventToolBundle\DependencyInjection
 */
class MarkocupicSacEventToolExtension extends Extension
{
    /**
     * {@inheritdoc}
     */
    public function load(array $mergedConfig, ContainerBuilder $container)
    {
        $loader = new YamlFileLoader(
            $container,
            new FileLocator(__DIR__ . '/../Resources/config')
        );

        $loader->load('listener.yml');
        $loader->load('parameters.yml');
        $loader->load('services.yml');

        // Merge parameters
        if (!empty($GLOBALS['TL_CONFIG']) && is_array($GLOBALS['TL_CONFIG']))
        {
            foreach ($GLOBALS['TL_CONFIG'] as $key => $value)
            {
                if (strpos($key, 'SAC_EVT_') !== false)
                {
                    if (!empty($value) && is_array($value))
                    {
                        $container->setParameter($key, \json_encode($value));
                    }
                    else
                    {
                        $container->setParameter($key, $value);
                    }
                }
            }
        }
    }
}
