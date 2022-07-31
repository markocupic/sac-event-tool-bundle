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
                                ->scalarNode('home_dir')->defaultValue('files/sektion/be_user_home_directories')->end()
                                ->booleanNode('clear_user_rights_on_sso_login')->defaultFalse()->end()
                            ->end()
                        ->end()
                        ->arrayNode('frontend')
                            ->addDefaultsIfNotSet()
                            ->children()
                                ->scalarNode('home_dir')->defaultValue('files/sektion/fe_user_home_directories')->end()
                            ->scalarNode('avatar_dir')->defaultValue('files/sektion/fe_user_home_directories/avatars')->end()
                            ->end()
                        ->end()
                    ->end()
                ->end()
                // Events
                ->arrayNode('event')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->arrayNode('course')
                            ->addDefaultsIfNotSet()
                            ->children()
                                ->scalarNode('booklet_cover_image')->defaultValue('vendor/markocupic/sac-event-tool-bundle/src/Resources/public/images/events/course/booklet/cover.jpg')->end()
                                ->scalarNode('booklet_filename_pattern')->defaultValue('Kursprogramm_%%s.pdf')->end()
                                ->scalarNode('fallback_image')->defaultValue('vendor/markocupic/sac-event-tool-bundle/src/Resources/public/images/events/course/fallback_image.svg')->end()
                            ->end()
                        ->end()
                        ->arrayNode('template')
                            ->addDefaultsIfNotSet()
                            ->children()
                                // Event member list docx template
                                ->scalarNode('member_list')->defaultValue('vendor/markocupic/sac-event-tool-bundle/src/Resources/contao/templates/docx/event_memberlist.docx')->end()
                                // Event tour invoice docx template
                                ->scalarNode('tour_invoice')->defaultValue('vendor/markocupic/sac-event-tool-bundle/src/Resources/contao/templates/docx/event_invoice_tour.docx')->end()
                                // Event tour rapport docx template
                                ->scalarNode('tour_rapport')->defaultValue('vendor/markocupic/sac-event-tool-bundle/src/Resources/contao/templates/docx/event_rapport_tour.docx')->end()
                                // Event course confirmation docx template
                                ->scalarNode('course_confirmation')->defaultValue('vendor/markocupic/sac-event-tool-bundle/src/Resources/contao/templates/docx/course_confirmation.docx')->end()
                            ->end()
                        ->end()
                        // Member list file name pattern
                        ->scalarNode('member_list_file_name_pattern')->defaultValue('SAC_Event_Teilnehmerliste_%%s.%%s')->end()
                        // Event tour invoice file name pattern
                        ->scalarNode('tour_invoice_file_name_pattern')->defaultValue('SAC_Event_Verguetungsformular_%%s.%%s')->end()
                        // Event tour rapport file name pattern
                        ->scalarNode('tour_rapport_file_name_pattern')->defaultValue('SAC_Event_Tour-Rapport_%%s.%%s')->end()
                        // Event course confirmation file name pattern
                        ->scalarNode('course_confirmation_file_name_pattern')->defaultValue('SAC_Event_Kursbestaetigung_%%s_regId_%%s.%%s')->end()
                        // Default email text for accepting registrations
                        ->scalarNode('accept_registration_email_body')
                            ->cannotBeEmpty()
                            ->info('Default email text for accepting registrations in the Contao backend')
                            ->defaultValue('Hallo ##participantFirstname## ##participantLastname##{{br}}{{br}}Ich freue mich, dir mitzuteilen, dass du für den Anlass "##eventName##" vom ##eventDates## definitiv angemeldet bist.{{br}}Bitte antworte nicht auf diese E-Mail. Kontaktiere mich bei Rückfragen unter folgender E-Mail-Adresse: ##instructorEmail##.{{br}}{{br}}Liebe Grüsse{{br}}{{br}}##instructorFirstname## ##instructorLastname##{{br}}{{br}}##instructorStreet##{{br}}##instructorPostal## ##instructorCity##{{br}}##instructorPhone##{{br}}##instructorMobile##{{br}}{{br}}{{br}}--------------------------------{{br}}"##eventName##" vom ##eventDates##{{br}}##eventUrl##')
                        ->end()
                        // Coordinates
                        ->scalarNode('geo_link')
                            ->cannotBeEmpty()
                            // The coord "%s" placeholders have to be escaped by an additional percent char => %%s
                            ->defaultValue('https://map.geo.admin.ch/embed.html?lang=de&topic=ech&bgLayer=ch.swisstopo.pixelkarte-farbe&layers=ch.bav.haltestellen-oev,ch.swisstopo.swisstlm3d-wanderwege,ch.swisstopo-karto.skitouren,ch.astra.wanderland-sperrungen_umleitungen&E=%%s&N=%%s&zoom=6&crosshair=marker')
                        ->end()
                        // SAC Route Portal Base Link
                        ->scalarNode('sac_route_portal_base_link')
                            ->cannotBeEmpty()
                            ->defaultValue('https://www.sac-cas.ch/de/huetten-und-touren/sac-tourenportal/')
                        ->end()
                    ->end()
                ->end()
            ->end()
        ;

        return $treeBuilder;
    }
}
