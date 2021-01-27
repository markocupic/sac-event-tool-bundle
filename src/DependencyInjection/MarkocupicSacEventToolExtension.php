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

namespace Markocupic\SacEventToolBundle\DependencyInjection;

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;

/**
 * Class MarkocupicSacEventToolExtension.
 */
class MarkocupicSacEventToolExtension extends Extension
{
    /**
     * {@inheritdoc}
     */
    public function load(array $mergedConfig, ContainerBuilder $container): void
    {
        // Merge parameters
        if (!empty($GLOBALS['TL_CONFIG']) && \is_array($GLOBALS['TL_CONFIG'])) {
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
