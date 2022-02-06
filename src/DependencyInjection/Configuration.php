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
                // Language
                ->scalarNode('locale')->info('Set the default language.')->defaultValue('en')->cannotBeEmpty()->end()
                // Section name
                ->scalarNode('section_name')->info('e.g. SAC Sektion Pilatus')->cannotBeEmpty()->end()
                // Member database sync Zentralverband Bern
                ->arrayNode('member_sync_credentials')
                    ->children()
                        ->scalarNode('hostname')->cannotBeEmpty()->end()
                        ->scalarNode('username')->cannotBeEmpty()->end()
                        ->scalarNode('password')->cannotBeEmpty()->end()
                    ->end()
                ->end()
                // Event admin name
                ->scalarNode('event_admin_name')->cannotBeEmpty()->end()
                // Event admin email
                ->scalarNode('event_admin_email')->cannotBeEmpty()->end()
                // Temp dir e.g system/tmp
                ->scalarNode('temp_dir')->defaultValue('system/tmp')->cannotBeEmpty()->end()
                // Avatars
                ->arrayNode('avatar')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->scalarNode('female')->defaultValue('vendor/markocupic/sac-event-tool-bundle/src/Resources/public/images/avatars/avatar-default-female.png')->end()
                        ->scalarNode('male')->defaultValue('vendor/markocupic/sac-event-tool-bundle/src/Resources/public/images/avatars/avatar-default-male.png')->end()
                    ->end()
                ->end()
                // Backend and frontend users
                ->arrayNode('user')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->arrayNode('backend')
                            ->addDefaultsIfNotSet()
                            ->children()
                                ->scalarNode('home_dir')->defaultValue('files/sac_pilatus/be_user_home_directories')->end()
                            ->end()
                        ->end()
                        ->arrayNode('frontend')
                            ->addDefaultsIfNotSet()
                            ->children()
                                ->scalarNode('home_dir')->defaultValue('files/sac_pilatus/fe_user_home_directories')->end()
                            ->scalarNode('avatar_dir')->defaultValue('files/sac_pilatus/fe_user_home_directories/avatars')->end()
                            ->end()
                        ->end()
                    ->end()
                ->end()
                // Events
                ->arrayNode('event')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->arrayNode('story')
                            ->addDefaultsIfNotSet()
                            ->children()
                                ->scalarNode('asset_dir')->defaultValue('files/sac_pilatus/events/tourenberichte')->end()
                            ->end()
                        ->end()
                        ->arrayNode('template')
                            ->addDefaultsIfNotSet()
                            ->children()
                                // Event tour invoice template
                                ->scalarNode('tour_invoice')->defaultValue('vendor/markocupic/sac-event-tool-bundle/src/Resources/contao/templates/docx/event_invoice_tour.docx')->end()
                                // Event tour rapport template
                                ->scalarNode('tour_rapport')->defaultValue('vendor/markocupic/sac-event-tool-bundle/src/Resources/contao/templates/docx/event_rapport_tour.docx')->end()
                                // Event course confirmation template
                                ->scalarNode('course_confirmation')->defaultValue('vendor/markocupic/sac-event-tool-bundle/src/Resources/contao/templates/docx/course_confirmation.docx')->end()
                            ->end()
                        ->end()
                        // Event tour invoice file name pattern
                        ->scalarNode('tour_invoice_file_name_pattern')->defaultValue('SAC_Sektion_Pilatus_Verguetungsformular-%%s.%%s')->end()
                        // Event tour rapport file name pattern
                        ->scalarNode('tour_rapport_file_name_pattern')->defaultValue('SAC_Sektion_Pilatus_Tour-Rapport-%%s.%%s')->end()
                        // Event course confirmation file name pattern
                        ->scalarNode('course_confirmation_file_name_pattern')->defaultValue('SAC_Sektion_Pilatus_Kursbestaetigung-%%s-regId-%%s.%%s')->end()
                        // Default email text for accepting registrations
                        ->scalarNode('accept_registration_email_body')
                            ->cannotBeEmpty()
                            ->info('Default email text for accepting registrations in the Contao backend')
                            ->defaultValue('Hallo ##participantFirstname## ##participantLastname##{{br}}{{br}}Ich freue mich, dir mitzuteilen, dass du für den Anlass "##eventName##" vom ##eventDates## definitiv angemeldet bist.{{br}}Bitte antworte nicht auf diese E-Mail. Kontaktiere mich bei Rückfragen unter folgender E-Mail-Adresse: ##instructorEmail##.{{br}}{{br}}Liebe Grüsse{{br}}{{br}}##instructorFirstname## ##instructorLastname##{{br}}{{br}}##instructorStreet##{{br}}##instructorPostal## ##instructorCity##{{br}}##instructorPhone##{{br}}##instructorMobile##{{br}}{{br}}{{br}}--------------------------------{{br}}"##eventName##" vom ##eventDates##{{br}}##eventUrl##')
                        ->end()
                    ->end()
                ->end()
            ->end()
        ;

        return $treeBuilder;
    }
}
