<?php

declare(strict_types=1);

/*
 * This file is part of SAC Event Tool Bundle.
 *
 * (c) Marko Cupic 2023 <m.cupic@gmx.ch>
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
            new FileLocator(__DIR__.'/../../config')
        );

        $loader->load('security.yaml');
        $loader->load('listener.yaml');
        $loader->load('event_subscriber.yaml');
        $loader->load('parameters.yaml');
        $loader->load('controller_download.yaml');
        $loader->load('controller_ajax.yaml');
        $loader->load('controller_contao_frontend_module.yaml');
        $loader->load('controller_contao_content_element.yaml');
        $loader->load('controller_feed.yaml');
        $loader->load('sac_member_database.yaml');
        $loader->load('contao_scope.yaml');
        $loader->load('services.yaml');
        $loader->load('data_container.yaml');
        $loader->load('twig.yaml');
        $loader->load('avatar.yaml');
        $loader->load('contao_backend_maintainance.yaml');

        // Friendly configuration
        $rootKey = $this->getAlias();

        $container->setParameter($rootKey.'.locale', $config['locale']);
        $container->setParameter($rootKey.'.section_name', $config['section_name']);
        $container->setParameter($rootKey.'.member_sync_credentials', $config['member_sync_credentials']);
        $container->setParameter($rootKey.'.event_admin_name', $config['event_admin_name']);
        $container->setParameter($rootKey.'.event_admin_email', $config['event_admin_email']);
        $container->setParameter($rootKey.'.temp_dir', $config['temp_dir']);
        $container->setParameter($rootKey.'.avatar.female', $config['avatar']['female']);
        $container->setParameter($rootKey.'.avatar.male', $config['avatar']['male']);
        $container->setParameter($rootKey.'.user.backend.home_dir', $config['user']['backend']['home_dir']);
        $container->setParameter($rootKey.'.user.backend.reset_user_rights_on_sso_login', $config['user']['backend']['reset_user_rights_on_sso_login']);
        $container->setParameter($rootKey.'.user.frontend.home_dir', $config['user']['frontend']['home_dir']);
        $container->setParameter($rootKey.'.user.frontend.avatar_dir', $config['user']['frontend']['avatar_dir']);
        $container->setParameter($rootKey.'.event.course.booklet_cover_image', $config['event']['course']['booklet_cover_image']);
        $container->setParameter($rootKey.'.event.course.booklet_filename_pattern', $config['event']['course']['booklet_filename_pattern']);
        $container->setParameter($rootKey.'.event.course.fallback_image', $config['event']['course']['fallback_image']);
        $container->setParameter($rootKey.'.event.template.member_list', $config['event']['template']['member_list']);
        $container->setParameter($rootKey.'.event.template.tour_invoice', $config['event']['template']['tour_invoice']);
        $container->setParameter($rootKey.'.event.template.tour_rapport', $config['event']['template']['tour_rapport']);
        $container->setParameter($rootKey.'.event.template.course_confirmation', $config['event']['template']['course_confirmation']);
        $container->setParameter($rootKey.'.event.member_list_file_name_pattern', $config['event']['member_list_file_name_pattern']);
        $container->setParameter($rootKey.'.event.tour_invoice_file_name_pattern', $config['event']['tour_invoice_file_name_pattern']);
        $container->setParameter($rootKey.'.event.tour_rapport_file_name_pattern', $config['event']['tour_rapport_file_name_pattern']);
        $container->setParameter($rootKey.'.event.course_confirmation_file_name_pattern', $config['event']['course_confirmation_file_name_pattern']);
        $container->setParameter($rootKey.'.event.accept_registration_email_body', $config['event']['accept_registration_email_body']);
        $container->setParameter($rootKey.'.event.geo_link', $config['event']['geo_link']);
        $container->setParameter($rootKey.'.event.sac_route_portal_base_link', $config['event']['sac_route_portal_base_link']);

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
